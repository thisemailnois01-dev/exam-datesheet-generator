<?php

declare(strict_types=1);

namespace App\Livewire;

use App\Domain\Timetable\DTOs\CourseNodeDto;
use App\Domain\Timetable\Services\FeasibilityChecker;
use App\Domain\Timetable\Services\SlotGenerator;
use App\Domain\Timetable\Solvers\NativePhpSolver;
use App\Models\Course;
use App\Models\Datesheet;
use App\Models\ExamSession;
use App\Models\Holiday;
use App\Models\Room;
use App\Models\Setting;
use App\Models\Shift;
use App\Models\Teacher;
use Livewire\Component;

class WhatIfSimulator extends Component
{
    public ?int $selectedSessionId = null;

    // Simulation parameters
    public int $simDuration = 60;
    public int $simBreak = 45; // Test changing break from 30 to 45
    public int $simRoomCount = 10; // Test with fewer rooms
    public bool $simShiftStrict = true;

    // Simulation output state
    public bool $hasRun = false;
    public bool $simSuccess = false;
    public int $simSlotsCount = 0;
    public int $simSoftScore = 0;
    public float $simTime = 0.0;
    public ?string $simExplanation = null;
    public array $simSuggestions = [];

    // Current baseline values
    public int $baselineDuration = 60;
    public int $baselineBreak = 30;
    public int $baselineRoomCount = 12;
    public ?int $baselineSoftScore = null;

    public function mount(): void
    {
        $session = ExamSession::with('department')->latest()->first();
        if ($session) {
            $this->selectedSessionId = $session->id;
            $setting = Setting::where('exam_session_id', $session->id)->first();
            $roomsCount = Room::where('department_id', $session->department_id)->where('is_active', true)->count();
            $latestDatesheet = Datesheet::where('exam_session_id', $session->id)->latest()->first();

            if ($setting) {
                $this->baselineDuration = $setting->default_duration_minutes;
                $this->baselineBreak = $setting->break_minutes;
                $this->simDuration = $setting->default_duration_minutes;
                $this->simBreak = 45; // preset what-if scenario
            }

            $this->baselineRoomCount = $roomsCount;
            $this->simRoomCount = max(1, $roomsCount - 2);
            $this->baselineSoftScore = $latestDatesheet?->soft_score;
        }
    }

    public function runSimulation(SlotGenerator $slotGenerator, NativePhpSolver $solver): void
    {
        if (!$this->selectedSessionId) {
            return;
        }

        $session = ExamSession::findOrFail($this->selectedSessionId);
        $departmentId = $session->department_id;
        $setting = Setting::where('exam_session_id', $session->id)->first() ?? new Setting();

        $shifts = Shift::where('department_id', $departmentId)->where('is_active', true)->get()->toArray();
        $holidays = Holiday::where('exam_session_id', $session->id)->get()->map(fn($h) => [
            'date' => $h->date->format('Y-m-d'),
            'title' => $h->title,
            'start_time' => $h->start_time,
            'end_time' => $h->end_time,
        ])->toArray();

        // Generate simulated slots
        $slots = $slotGenerator->generateSlots(
            startDate: $session->start_date->format('Y-m-d'),
            endDate: $session->end_date->format('Y-m-d'),
            workingDays: $setting->working_days ?? Setting::defaultWorkingDays(),
            shifts: $shifts,
            durationMinutes: $this->simDuration,
            breakMinutes: $this->simBreak,
            holidays: $holidays,
        );

        $this->simSlotsCount = count($slots);

        // Load courses
        $courses = Course::where('department_id', $departmentId)
            ->with(['batch', 'students', 'teachers'])
            ->get();

        $courseNodes = $courses->map(fn($c) => new CourseNodeDto(
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
        ))->all();

        // Simulate rooms by slicing available rooms
        $allRooms = Room::where('department_id', $departmentId)->where('is_active', true)->get()->toArray();
        $simRooms = array_slice($allRooms, 0, $this->simRoomCount);
        $teachers = Teacher::where('department_id', $departmentId)->get()->toArray();

        // Run solver in-memory (0 DB writes)
        $result = $solver->solve(
            courses: $courseNodes,
            slots: $slots,
            rooms: $simRooms,
            teachers: $teachers,
            weights: $setting->weights ?? Setting::defaultWeights(),
            isShiftStrict: $this->simShiftStrict,
            randomSeed: 42
        );

        $this->hasRun = true;
        $this->simSuccess = $result->isSuccess;
        $this->simSoftScore = $result->softScore;
        $this->simTime = $result->executionTimeSeconds;
        $this->simExplanation = $result->bottleneckExplanation;
        $this->simSuggestions = $result->relaxationSuggestions;
    }

    public function render()
    {
        return view('livewire.what-if-simulator')->layout('layouts.app', ['title' => 'What-If Simulation Sandbox']);
    }
}
