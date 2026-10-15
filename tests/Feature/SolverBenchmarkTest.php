<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Domain\Timetable\DTOs\CourseNodeDto;
use App\Domain\Timetable\Services\DemoDataGenerator;
use App\Domain\Timetable\Services\SlotGenerator;
use App\Domain\Timetable\Solvers\NativePhpSolver;
use App\Models\Course;
use App\Models\ExamSession;
use App\Models\Room;
use App\Models\Setting;
use App\Models\Shift;
use App\Models\Teacher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SolverBenchmarkTest extends TestCase
{
    use RefreshDatabase;

    private DemoDataGenerator $demoGenerator;
    private SlotGenerator $slotGenerator;
    private NativePhpSolver $solver;

    protected function setUp(): void
    {
        parent::setUp();
        $this->demoGenerator = new DemoDataGenerator();
        $this->slotGenerator = new SlotGenerator();
        $this->solver = new NativePhpSolver();
    }

    public function test_native_solver_produces_zero_hard_conflicts_within_performance_target(): void
    {
        // 1. Seed demo dataset (8 batches, 40 courses, 12 rooms, 15 teachers)
        $demo = $this->demoGenerator->generateStandardDemo(
            batchCount: 8,
            coursesPerBatch: 5,
            roomCount: 12,
            teacherCount: 15
        );

        $session = $demo['session'];
        $setting = Setting::where('exam_session_id', $session->id)->first();
        $shifts = Shift::where('department_id', $session->department_id)->get()->toArray();

        // 2. Generate dynamic slots
        $slots = $this->slotGenerator->generateSlots(
            startDate: $session->start_date->format('Y-m-d'),
            endDate: $session->end_date->format('Y-m-d'),
            workingDays: $setting->working_days,
            shifts: $shifts,
            durationMinutes: $setting->default_duration_minutes,
            breakMinutes: $setting->break_minutes,
        );

        // 3. Prepare courses, rooms, teachers
        $courses = Course::where('department_id', $session->department_id)
            ->with(['batch', 'students', 'teachers'])
            ->get();

        $courseNodes = $courses->map(function ($c) {
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
                studentIds: $c->students->pluck('id')->all(),
                teacherIds: $c->teachers->pluck('id')->all(),
            );
        })->all();

        $rooms = Room::where('department_id', $session->department_id)->get()->toArray();
        $teachers = Teacher::where('department_id', $session->department_id)->get()->toArray();

        // 4. Solve
        $startTime = microtime(true);
        $result = $this->solver->solve(
            courses: $courseNodes,
            slots: $slots,
            rooms: $rooms,
            teachers: $teachers,
            weights: $setting->weights,
            isShiftStrict: true,
            randomSeed: 123
        );
        $elapsed = microtime(true) - $startTime;

        // 5. Assertions
        $this->assertTrue($result->isSuccess, 'Solver failed to produce a valid datesheet: ' . implode(', ', $result->hardViolations));
        $this->assertEmpty($result->hardViolations, 'Found hard violations in datesheet: ' . implode(', ', $result->hardViolations));
        $this->assertCount(40, $result->assignments, 'Not all 40 courses were scheduled.');
        $this->assertLessThan(30.0, $elapsed, 'Solver exceeded the 30-second performance target.');
    }

    public function test_zero_hardcoding_works_with_varying_room_counts(): void
    {
        // Prove solver works with 3 rooms, 12 rooms, and 30 rooms
        foreach ([4, 12, 30] as $roomCount) {
            $demo = $this->demoGenerator->generateStandardDemo(
                batchCount: 4,
                coursesPerBatch: 3,
                roomCount: $roomCount,
                teacherCount: 10,
                departmentCode: 'DEPT-ROOMS-' . $roomCount
            );

            $session = $demo['session'];
            $setting = Setting::where('exam_session_id', $session->id)->first();
            $shifts = Shift::where('department_id', $session->department_id)->get()->toArray();

            $slots = $this->slotGenerator->generateSlots(
                startDate: $session->start_date->format('Y-m-d'),
                endDate: $session->end_date->format('Y-m-d'),
                workingDays: $setting->working_days,
                shifts: $shifts,
                durationMinutes: 60,
                breakMinutes: 30,
            );

            $courses = Course::where('department_id', $session->department_id)
                ->with(['batch', 'students', 'teachers'])
                ->get();

            $courseNodes = $courses->map(fn($c) => new CourseNodeDto(
                courseId: $c->id,
                batchId: $c->batch_id,
                shiftId: $c->batch->shift_id ?? null,
                code: $c->code,
                name: $c->name,
                studentCount: 30,
                durationMinutes: null,
                isCore: false,
                isHard: false
            ))->all();

            $rooms = Room::where('department_id', $session->department_id)->get()->toArray();
            $teachers = Teacher::where('department_id', $session->department_id)->get()->toArray();

            $result = $this->solver->solve(
                courses: $courseNodes,
                slots: $slots,
                rooms: $rooms,
                teachers: $teachers,
                isShiftStrict: true,
                randomSeed: 99
            );

            $this->assertTrue($result->isSuccess, sprintf('Failed with room count %d: %s', $roomCount, implode(', ', $result->hardViolations)));
            $this->assertEmpty($result->hardViolations);
        }
    }

    public function test_infeasible_scenario_provides_actionable_diagnostics_without_crashing(): void
    {
        $demo = $this->demoGenerator->generateInfeasibleDemo();
        $session = $demo['session'];
        $setting = Setting::where('exam_session_id', $session->id)->first();
        $shifts = Shift::where('department_id', $session->department_id)->get()->toArray();

        $slots = $this->slotGenerator->generateSlots(
            startDate: $session->start_date->format('Y-m-d'),
            endDate: $session->end_date->format('Y-m-d'),
            workingDays: $setting->working_days,
            shifts: $shifts,
            durationMinutes: 60,
            breakMinutes: 30,
        );

        $courses = Course::where('department_id', $session->department_id)
            ->with(['batch'])
            ->get();

        $courseNodes = $courses->map(fn($c) => new CourseNodeDto(
            courseId: $c->id,
            batchId: $c->batch_id,
            shiftId: $c->batch->shift_id ?? null,
            code: $c->code,
            name: $c->name,
            studentCount: 30,
            durationMinutes: null,
            isCore: false,
            isHard: false
        ))->all();

        $rooms = Room::where('department_id', $session->department_id)->get()->toArray();
        $teachers = Teacher::where('department_id', $session->department_id)->get()->toArray();

        $result = $this->solver->solve(
            courses: $courseNodes,
            slots: $slots,
            rooms: $rooms,
            teachers: $teachers,
            isShiftStrict: true
        );

        $this->assertFalse($result->isSuccess);
        $this->assertNotEmpty($result->hardViolations);
        $this->assertNotNull($result->bottleneckExplanation);
        $this->assertNotEmpty($result->relaxationSuggestions);
    }
}
