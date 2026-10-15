<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Domain\Timetable\DTOs\CourseNodeDto;
use App\Domain\Timetable\Services\SlotGenerator;
use App\Domain\Timetable\Solvers\NativePhpSolver;
use App\Models\Course;
use App\Models\Datesheet;
use App\Models\Exam;
use App\Models\ExamInvigilator;
use App\Models\ExamRoomAssignment;
use App\Models\ExamSession;
use App\Models\Holiday;
use App\Models\Room;
use App\Models\Setting;
use App\Models\Shift;
use App\Models\Teacher;
use App\Models\TeacherAvailability;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class GenerateDatesheetJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public readonly int $examSessionId,
        public readonly ?int $randomSeed = null,
        public readonly bool $keepLockedExams = true,
        public readonly ?int $existingDatesheetId = null,
        public readonly ?string $startDate = null,
        public readonly bool $dailyConsecutiveMode = true,
    ) {}

    public function handle(
        SlotGenerator $slotGenerator,
        NativePhpSolver $solver
    ): void {
        $cacheKey = "datesheet_generation_{$this->examSessionId}";

        $updateProgress = function (int $pct, string $msg) use ($cacheKey): void {
            Cache::put($cacheKey, [
                'status' => 'running',
                'progress' => $pct,
                'message' => $msg,
            ], 300);
        };

        $updateProgress(5, 'Initializing session and timetable rules...');

        $session = ExamSession::findOrFail($this->examSessionId);

        // Update start date if custom start date specified
        if ($this->startDate) {
            $parsedStart = \Carbon\Carbon::parse($this->startDate);
            $session->update([
                'start_date' => $parsedStart->format('Y-m-d'),
                'end_date' => (clone $parsedStart)->addDays(14)->format('Y-m-d'),
            ]);
            $session->refresh();
        }

        $departmentId = $session->department_id;
        $setting = Setting::where('exam_session_id', $session->id)->first()
            ?? Setting::where('department_id', $departmentId)->first()
            ?? new Setting([
                'default_duration_minutes' => 60,
                'break_minutes' => 30,
                'shift_rule_strict' => true,
                'working_days' => Setting::defaultWorkingDays(),
                'weights' => Setting::defaultWeights(),
            ]);

        $shifts = Shift::where('department_id', $departmentId)->where('is_active', true)->get()->toArray();
        $holidays = Holiday::where('exam_session_id', $session->id)->get()->map(fn($h) => [
            'date' => $h->date->format('Y-m-d'),
            'title' => $h->title,
            'start_time' => $h->start_time,
            'end_time' => $h->end_time,
        ])->toArray();

        // 1. Generate Slots
        $updateProgress(15, 'Generating discrete slots from duration D and break B...');
        $slots = $slotGenerator->generateSlots(
            startDate: $session->start_date->format('Y-m-d'),
            endDate: $session->end_date->format('Y-m-d'),
            workingDays: $setting->working_days ?? Setting::defaultWorkingDays(),
            shifts: $shifts,
            durationMinutes: $setting->default_duration_minutes,
            breakMinutes: $setting->break_minutes,
            holidays: $holidays,
        );

        // 2. Identify locked exams if partial regeneration
        $lockedMap = [];
        if ($this->keepLockedExams && $this->existingDatesheetId !== null) {
            $lockedExams = Exam::where('datesheet_id', $this->existingDatesheetId)
                ->where('is_locked', true)
                ->get();

            foreach ($lockedExams as $le) {
                $lockedMap[$le->course_id . '_' . $le->batch_id] = [
                    'date' => $le->exam_date->format('Y-m-d'),
                    'slot_index' => $le->slot_index,
                    'shift_id' => $le->shift_id,
                ];
            }
        }

        // 3. Load Courses
        $courses = Course::where('department_id', $departmentId)
            ->with(['batch', 'students', 'teachers'])
            ->get();

        $courseNodes = $courses->map(function ($c) use ($lockedMap) {
            $lockKey = $c->id . '_' . $c->batch_id;
            $isLocked = isset($lockedMap[$lockKey]);
            $lockInfo = $lockedMap[$lockKey] ?? null;

            return new CourseNodeDto(
                courseId: $c->id,
                batchId: $c->batch_id,
                shiftId: $c->batch->shift_id ?? null,
                code: $c->code,
                name: $c->name,
                studentCount: $c->batch->student_count ?? 40,
                durationMinutes: $c->duration_minutes,
                isCore: $c->is_core,
                isHard: $c->is_hard,
                isLocked: $isLocked,
                lockedDate: $lockInfo['date'] ?? null,
                lockedSlotIndex: $lockInfo['slot_index'] ?? null,
                lockedShiftId: $lockInfo['shift_id'] ?? null,
                studentIds: $c->students->pluck('id')->all(),
                teacherIds: $c->teachers->pluck('id')->all(),
            );
        })->all();

        $rooms = Room::where('department_id', $departmentId)->where('is_active', true)->get()->toArray();
        $teachers = Teacher::where('department_id', $departmentId)->get()->toArray();
        $unavailabilities = TeacherAvailability::whereIn('teacher_id', array_column($teachers, 'id'))
            ->get()
            ->map(fn($u) => [
                'teacher_id' => $u->teacher_id,
                'date' => $u->date->format('Y-m-d'),
                'slot_index' => $u->slot_index,
                'is_available' => $u->is_available,
            ])
            ->toArray();

        // 4. Solve
        $result = $solver->solve(
            courses: $courseNodes,
            slots: $slots,
            rooms: $rooms,
            teachers: $teachers,
            teacherUnavailabilities: $unavailabilities,
            weights: $setting->weights ?? Setting::defaultWeights(),
            isShiftStrict: $setting->shift_rule_strict,
            randomSeed: $this->randomSeed ?? mt_rand(1, 999999),
            progressCallback: function (int $pct, string $msg) use ($updateProgress): void {
                $updateProgress($pct, $msg);
            },
            dailyConsecutiveMode: $this->dailyConsecutiveMode,
        );

        if (!$result->isSuccess) {
            Cache::put($cacheKey, [
                'status' => 'failed',
                'progress' => 100,
                'message' => 'Generation infeasible.',
                'explanation' => $result->bottleneckExplanation,
                'suggestions' => $result->relaxationSuggestions,
            ], 600);
            return;
        }

        // 5. Persist to Database in atomic transaction
        $updateProgress(90, 'Persisting schedule to database...');
        $datesheet = DB::transaction(function () use ($session, $result, $setting) {
            $latestVersion = Datesheet::where('exam_session_id', $session->id)->max('version') ?? 0;

            $branding = $setting->branding ?? \App\Models\Setting::defaultBranding($session->department->name);

            $officialMetrics = array_merge($result->metrics ?? [], [
                'university_name' => $branding['university_name'] ?? 'Baba Guru Nanak University, Nankana Sahib',
                'department_name' => $branding['department_name'] ?? $session->department->name,
                'exam_title' => $branding['exam_title'] ?? 'DATE SHEET FOR FINAL TERM EXAMINATION',
                'session_term' => $branding['session_term'] ?? 'SPRING 2026',
                'ref_no' => $branding['ref_no'] ?? 'BGNU/CS&IT/2026/46',
                'issue_date' => $branding['issue_date'] ?? '03.06.2026',
                'chairperson_name' => $branding['chairperson_name'] ?? 'Dr. Muhammad Usman Younus',
                'chairperson_title' => $branding['chairperson_title'] ?? 'Chairperson',
                'left_logo' => $branding['left_logo'] ?? 'images/dept_logo.png',
                'right_logo' => $branding['right_logo'] ?? 'images/bgnu_logo.png',
                'signature_img' => $branding['signature_img'] ?? 'images/signature.png',
                'cc_list' => $branding['cc_list'] ?? "1. Controller of Examination\n2. Director Academics\n3. All Chairs of Concerned Departments\n4. All Concerned Faculty\n5. Office Copy",
            ]);

            $ds = Datesheet::create([
                'exam_session_id' => $session->id,
                'department_id' => $session->department_id,
                'name' => sprintf('Datesheet v%d (%s)', $latestVersion + 1, now()->format('M d, H:i')),
                'version' => $latestVersion + 1,
                'status' => 'draft',
                'soft_score' => $result->softScore,
                'metrics' => $officialMetrics,
            ]);

            foreach ($result->assignments as $assignment) {
                $exam = Exam::create([
                    'datesheet_id' => $ds->id,
                    'course_id' => $assignment->courseId,
                    'batch_id' => $assignment->batchId,
                    'exam_date' => $assignment->slot->date,
                    'slot_index' => $assignment->slot->slotIndex,
                    'shift_id' => $assignment->slot->shiftId,
                    'start_time' => $assignment->slot->startTime,
                    'end_time' => $assignment->slot->endTime,
                    'is_locked' => $assignment->isLocked,
                ]);

                foreach ($assignment->roomIds as $rId) {
                    ExamRoomAssignment::create([
                        'exam_id' => $exam->id,
                        'room_id' => $rId,
                        'student_count' => 40,
                    ]);
                }

                foreach ($assignment->invigilatorIds as $tId) {
                    ExamInvigilator::create([
                        'exam_id' => $exam->id,
                        'teacher_id' => $tId,
                        'room_id' => $assignment->roomIds[0] ?? null,
                    ]);
                }
            }

            return $ds;
        });

        Cache::put($cacheKey, [
            'status' => 'completed',
            'progress' => 100,
            'message' => 'Datesheet generated successfully with 0 hard conflicts!',
            'datesheet_id' => $datesheet->id,
            'soft_score' => $result->softScore,
            'execution_time' => sprintf('%.2f seconds', $result->executionTimeSeconds),
        ], 600);
    }
}
