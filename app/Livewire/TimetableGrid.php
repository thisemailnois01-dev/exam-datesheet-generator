<?php

declare(strict_types=1);

namespace App\Livewire;

use App\Domain\Timetable\DTOs\CourseNodeDto;
use App\Domain\Timetable\DTOs\ScheduleAssignmentDto;
use App\Domain\Timetable\DTOs\SlotDto;
use App\Domain\Timetable\Services\DatesheetValidator;
use App\Domain\Timetable\Services\SlotGenerator;
use App\Domain\Timetable\Solvers\NativePhpSolver;
use App\Jobs\GenerateDatesheetJob;
use App\Models\Batch;
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
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Livewire\Component;

class TimetableGrid extends Component
{
    public ?int $selectedSessionId = null;
    public ?int $selectedDatesheetId = null;

    // Custom Start Date & Consecutive Daily Options
    public string $startDate = '2026-06-08';
    public bool $dailyConsecutiveMode = true;
    public string $viewMode = 'official'; // 'official' (matching sample PDF) or 'matrix' (Day x Slot)
    public ?int $selectedBatchId = null;

    // Filter properties
    public ?int $filterBatch = null;
    public ?int $filterShift = null;
    public ?int $filterRoom = null;
    public ?int $filterTeacher = null;
    public string $searchCourse = '';

    // Generation state
    public bool $isGenerating = false;
    public int $generationProgress = 0;
    public string $generationMessage = '';
    public ?string $generationError = null;
    public array $generationSuggestions = [];

    // Manual Edit Modal state
    public bool $showMoveModal = false;
    public ?int $movingExamId = null;
    public ?string $newExamDate = null;
    public ?int $newSlotIndex = null;
    public ?int $newShiftId = null;
    public ?string $moveValidationError = null;

    public function mount(): void
    {
        $session = ExamSession::where('is_active', true)->latest()->first();
        if ($session) {
            $this->selectedSessionId = $session->id;
            $this->startDate = $session->start_date ? $session->start_date->format('Y-m-d') : '2026-06-08';
            $latestDatesheet = Datesheet::where('exam_session_id', $session->id)->latest()->first();
            if ($latestDatesheet) {
                $this->selectedDatesheetId = $latestDatesheet->id;
            }
        }
    }

    public function pollGenerationProgress(): void
    {
        if (!$this->selectedSessionId) {
            return;
        }

        $cacheKey = "datesheet_generation_{$this->selectedSessionId}";
        $state = Cache::get($cacheKey);

        if ($state) {
            $this->generationProgress = $state['progress'] ?? 0;
            $this->generationMessage = $state['message'] ?? '';

            if (($state['status'] ?? '') === 'completed') {
                $this->isGenerating = false;
                $this->selectedDatesheetId = $state['datesheet_id'] ?? $this->selectedDatesheetId;
                Cache::forget($cacheKey);
                session()->flash('success', 'Datesheet generated successfully with 0 hard conflicts!');
            } elseif (($state['status'] ?? '') === 'failed') {
                $this->isGenerating = false;
                $this->generationError = $state['explanation'] ?? 'Generation failed.';
                $this->generationSuggestions = $state['suggestions'] ?? [];
                Cache::forget($cacheKey);
            }
        }
    }

    public function triggerGeneration(bool $keepLocked = true): void
    {
        if (!$this->selectedSessionId) {
            $this->generationError = 'No active exam session selected.';
            return;
        }

        $this->isGenerating = true;
        $this->generationProgress = 5;
        $this->generationMessage = 'Starting datesheet generation job...';
        $this->generationError = null;
        $this->generationSuggestions = [];

        // Run synchronously or via queue
        GenerateDatesheetJob::dispatchSync(
            examSessionId: $this->selectedSessionId,
            randomSeed: mt_rand(1, 999999),
            keepLockedExams: $keepLocked,
            existingDatesheetId: $this->selectedDatesheetId,
            startDate: $this->startDate,
            dailyConsecutiveMode: $this->dailyConsecutiveMode,
        );

        $this->pollGenerationProgress();
    }

    public function toggleExamLock(int $examId): void
    {
        $exam = Exam::findOrFail($examId);
        $exam->update(['is_locked' => !$exam->is_locked]);
    }

    public function openMoveModal(int $examId): void
    {
        $exam = Exam::findOrFail($examId);
        $this->movingExamId = $exam->id;
        $this->newExamDate = $exam->exam_date->format('Y-m-d');
        $this->newSlotIndex = $exam->slot_index;
        $this->newShiftId = $exam->shift_id;
        $this->moveValidationError = null;
        $this->showMoveModal = true;
    }

    public function moveExam(DatesheetValidator $validator, SlotGenerator $slotGenerator): void
    {
        if (!$this->movingExamId || !$this->newExamDate || !$this->newSlotIndex || !$this->newShiftId) {
            $this->moveValidationError = 'Please specify valid destination date, shift and slot.';
            return;
        }

        $movingExam = Exam::with(['course', 'batch', 'shift'])->findOrFail($this->movingExamId);
        $datesheet = Datesheet::findOrFail($movingExam->datesheet_id);
        $session = $datesheet->examSession;
        $setting = Setting::where('exam_session_id', $session->id)->first() ?? new Setting();
        $targetShift = Shift::findOrFail($this->newShiftId);

        // Generate valid slots to verify target slot exists
        $shifts = Shift::where('department_id', $datesheet->department_id)->where('is_active', true)->get()->toArray();
        $holidays = Holiday::where('exam_session_id', $session->id)->get()->map(fn($h) => [
            'date' => $h->date->format('Y-m-d'),
            'title' => $h->title,
            'start_time' => $h->start_time,
            'end_time' => $h->end_time,
        ])->toArray();

        $validSlots = $slotGenerator->generateSlots(
            startDate: $session->start_date->format('Y-m-d'),
            endDate: $session->end_date->format('Y-m-d'),
            workingDays: $setting->working_days ?? Setting::defaultWorkingDays(),
            shifts: $shifts,
            durationMinutes: $setting->default_duration_minutes,
            breakMinutes: $setting->break_minutes,
            holidays: $holidays,
        );

        $validSlotsByKey = [];
        $targetSlotDto = null;
        foreach ($validSlots as $s) {
            $validSlotsByKey[$s->getKey()] = $s;
            if ($s->date === $this->newExamDate && $s->shiftId === $this->newShiftId && $s->slotIndex === $this->newSlotIndex) {
                $targetSlotDto = $s;
            }
        }

        if ($targetSlotDto === null) {
            $this->moveValidationError = 'Destination slot is not a valid exam slot (may fall on a holiday, blackout window, or outside shift hours).';
            return;
        }

        // Live validation against other exams in this datesheet
        $otherExams = Exam::where('datesheet_id', $datesheet->id)
            ->where('id', '!=', $movingExam->id)
            ->with(['course', 'batch', 'rooms', 'invigilators'])
            ->get();

        // 1. Check Batch Clash
        foreach ($otherExams as $oe) {
            if ($oe->batch_id === $movingExam->batch_id &&
                $oe->exam_date->format('Y-m-d') === $this->newExamDate &&
                $oe->slot_index === $this->newSlotIndex) {
                $this->moveValidationError = sprintf(
                    'BATCH CLASH DETECTED: Batch "%s" already has exam "%s" scheduled in this exact slot!',
                    $movingExam->batch->name,
                    $oe->course->code
                );
                return;
            }
        }

        // 2. Check Strict Shift Mismatch
        if ($setting->shift_rule_strict && $movingExam->batch->shift_id !== null && $movingExam->batch->shift_id !== $this->newShiftId) {
            $this->moveValidationError = sprintf(
                'SHIFT MISMATCH: Batch "%s" belongs to Shift ID %d, cannot be placed in Shift "%s" under strict policy.',
                $movingExam->batch->name,
                $movingExam->batch->shift_id,
                $targetShift->name
            );
            return;
        }

        // Apply move in DB transaction
        DB::transaction(function () use ($movingExam, $targetSlotDto) {
            $movingExam->update([
                'exam_date' => $targetSlotDto->date,
                'shift_id' => $targetSlotDto->shiftId,
                'slot_index' => $targetSlotDto->slotIndex,
                'start_time' => $targetSlotDto->startTime,
                'end_time' => $targetSlotDto->endTime,
            ]);
        });

        $this->showMoveModal = false;
        $this->movingExamId = null;
        session()->flash('success', sprintf('Exam "%s" moved successfully with 0 clashes verified.', $movingExam->course->code));
    }

    public function render()
    {
        $sessions = ExamSession::with('department')->latest()->get();
        $datesheet = $this->selectedDatesheetId
            ? Datesheet::with(['examSession', 'department'])->find($this->selectedDatesheetId)
            : null;

        $examsQuery = $datesheet
            ? $datesheet->exams()->with(['course', 'batch', 'shift', 'rooms', 'invigilators'])
            : null;

        if ($examsQuery) {
            if ($this->filterBatch) {
                $examsQuery->where('batch_id', $this->filterBatch);
            }
            if ($this->filterShift) {
                $examsQuery->where('shift_id', $this->filterShift);
            }
            if ($this->filterRoom) {
                $examsQuery->whereHas('rooms', fn($q) => $q->where('rooms.id', $this->filterRoom));
            }
            if ($this->filterTeacher) {
                $examsQuery->whereHas('invigilators', fn($q) => $q->where('teachers.id', $this->filterTeacher));
            }
            if (!empty($this->searchCourse)) {
                $examsQuery->whereHas('course', function ($q) {
                    $q->where('code', 'like', "%{$this->searchCourse}%")
                      ->orWhere('name', 'like', "%{$this->searchCourse}%");
                });
            }
        }

        $exams = $examsQuery ? $examsQuery->get() : collect();

        // Group exams by date and slot
        $groupedExams = [];
        $uniqueDates = [];
        $uniqueSlots = [];

        foreach ($exams as $exam) {
            $d = $exam->exam_date->format('Y-m-d');
            $s = $exam->slot_index;
            $groupedExams[$d][$s][] = $exam;
            $uniqueDates[$d] = true;
            $uniqueSlots[$s] = true;
        }

        ksort($uniqueDates);
        ksort($uniqueSlots);

        $departmentId = $datesheet->department_id ?? ($sessions->first()->department_id ?? null);
        $batches = $departmentId ? Batch::where('department_id', $departmentId)->orderBy('name')->get() : collect();
        $shifts = $departmentId ? Shift::where('department_id', $departmentId)->orderBy('sort_order')->get() : collect();
        $rooms = $departmentId ? Room::where('department_id', $departmentId)->where('is_active', true)->orderBy('name')->get() : collect();
        $teachers = $departmentId ? Teacher::where('department_id', $departmentId)->orderBy('name')->get() : collect();

        $allBatchGroups = $exams->groupBy('batch_id');
        $officialBatchGroups = ($this->selectedBatchId && $allBatchGroups->has($this->selectedBatchId))
            ? collect([$this->selectedBatchId => $allBatchGroups->get($this->selectedBatchId)])
            : $allBatchGroups;

        return view('livewire.timetable-grid', [
            'sessions' => $sessions,
            'datesheet' => $datesheet,
            'groupedExams' => $groupedExams,
            'examsGroupedByBatch' => $officialBatchGroups,
            'allBatchGroupsCount' => $allBatchGroups->count(),
            'uniqueDates' => array_keys($uniqueDates),
            'uniqueSlots' => array_keys($uniqueSlots),
            'batches' => $batches,
            'shifts' => $shifts,
            'rooms' => $rooms,
            'teachers' => $teachers,
            'totalExamsCount' => $exams->count(),
        ])->layout('layouts.app', ['title' => 'Timetable Grid — Datesheet Generator']);
    }
}
