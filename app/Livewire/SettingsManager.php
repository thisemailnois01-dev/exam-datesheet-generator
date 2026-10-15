<?php

declare(strict_types=1);

namespace App\Livewire;

use App\Domain\Timetable\Services\SlotGenerator;
use App\Models\Department;
use App\Models\ExamSession;
use App\Models\Holiday;
use App\Models\Room;
use App\Models\Setting;
use App\Models\Shift;
use Carbon\Carbon;
use Livewire\Component;

class SettingsManager extends Component
{
    public ?int $selectedSessionId = null;
    public ?int $selectedDepartmentId = null;

    // Timing model settings
    public int $defaultDurationMinutes = 60;
    public int $breakMinutes = 30;
    public bool $shiftRuleStrict = true;
    public array $workingDays = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];

    // Session date range
    public string $sessionStartDate = '';
    public string $sessionEndDate = '';

    // Soft constraint weights
    public array $weights = [];

    // Institute Branding & Document Format
    public string $universityName = 'Baba Guru Nanak University, Nankana Sahib';
    public string $departmentName = 'Department of Computer Science & IT';
    public string $examTitle = 'DATE SHEET FOR FINAL TERM EXAMINATION';
    public string $sessionTerm = 'SPRING 2026';
    public string $refNo = 'BGNU/CS&IT/2026/46';
    public string $issueDate = '03.06.2026';
    public string $chairpersonName = 'Dr. Muhammad Usman Younus';
    public string $chairpersonTitle = 'Chairperson';
    public string $leftLogo = 'images/dept_logo.png';
    public string $rightLogo = 'images/bgnu_logo.png';
    public string $signatureImg = 'images/signature.png';
    public string $ccList = "1. Controller of Examination\n2. Director Academics\n3. All Chairs of Concerned Departments\n4. All Concerned Faculty\n5. Office Copy";

    // New shift form
    public string $newShiftName = '';
    public string $newShiftStart = '08:00';
    public string $newShiftEnd = '13:00';

    // New room form
    public string $newRoomName = '';
    public int $newRoomCapacity = 50;
    public string $newRoomType = 'classroom';

    // Projected slots calculation
    public int $projectedSlotsCount = 0;

    public function mount(SlotGenerator $slotGenerator): void
    {
        $session = ExamSession::with('department')->latest()->first();
        if ($session) {
            $this->selectedSessionId = $session->id;
            $this->selectedDepartmentId = $session->department_id;
            $this->sessionStartDate = $session->start_date->format('Y-m-d');
            $this->sessionEndDate = $session->end_date->format('Y-m-d');

            $setting = Setting::where('exam_session_id', $session->id)->first()
                ?? Setting::where('department_id', $session->department_id)->first();

            $defaultBranding = Setting::defaultBranding($session->department->name);

            if ($setting) {
                $this->defaultDurationMinutes = $setting->default_duration_minutes;
                $this->breakMinutes = $setting->break_minutes;
                $this->shiftRuleStrict = $setting->shift_rule_strict;
                $this->workingDays = $setting->working_days ?? Setting::defaultWorkingDays();
                $this->weights = $setting->weights ?? Setting::defaultWeights();

                $branding = array_merge($defaultBranding, $setting->branding ?? []);
                $this->universityName = $branding['university_name'] ?? $defaultBranding['university_name'];
                $this->departmentName = $branding['department_name'] ?? $defaultBranding['department_name'];
                $this->examTitle = $branding['exam_title'] ?? $defaultBranding['exam_title'];
                $this->sessionTerm = $branding['session_term'] ?? $defaultBranding['session_term'];
                $this->refNo = $branding['ref_no'] ?? $defaultBranding['ref_no'];
                $this->issueDate = $branding['issue_date'] ?? $defaultBranding['issue_date'];
                $this->chairpersonName = $branding['chairperson_name'] ?? $defaultBranding['chairperson_name'];
                $this->chairpersonTitle = $branding['chairperson_title'] ?? $defaultBranding['chairperson_title'];
                $this->leftLogo = $branding['left_logo'] ?? $defaultBranding['left_logo'];
                $this->rightLogo = $branding['right_logo'] ?? $defaultBranding['right_logo'];
                $this->signatureImg = $branding['signature_img'] ?? $defaultBranding['signature_img'];
                $this->ccList = is_array($branding['cc_list'] ?? null) ? implode("\n", $branding['cc_list']) : ($branding['cc_list'] ?? $defaultBranding['cc_list']);
            } else {
                $this->weights = Setting::defaultWeights();
            }

            $this->calculateProjectedSlots($slotGenerator);
        }
    }

    public function calculateProjectedSlots(SlotGenerator $slotGenerator): void
    {
        if (!$this->selectedDepartmentId || empty($this->sessionStartDate) || empty($this->sessionEndDate)) {
            $this->projectedSlotsCount = 0;
            return;
        }

        $shifts = Shift::where('department_id', $this->selectedDepartmentId)->where('is_active', true)->get()->toArray();
        $holidays = Holiday::where('exam_session_id', $this->selectedSessionId)->get()->map(fn($h) => [
            'date' => $h->date->format('Y-m-d'),
            'title' => $h->title,
            'start_time' => $h->start_time,
            'end_time' => $h->end_time,
        ])->toArray();

        $slots = $slotGenerator->generateSlots(
            startDate: $this->sessionStartDate,
            endDate: $this->sessionEndDate,
            workingDays: $this->workingDays,
            shifts: $shifts,
            durationMinutes: $this->defaultDurationMinutes,
            breakMinutes: $this->breakMinutes,
            holidays: $holidays,
        );

        $this->projectedSlotsCount = count($slots);
    }

    public function saveSettings(SlotGenerator $slotGenerator): void
    {
        $session = ExamSession::findOrFail($this->selectedSessionId);
        $session->update([
            'start_date' => $this->sessionStartDate,
            'end_date' => $this->sessionEndDate,
        ]);

        $department = Department::find($session->department_id);
        if ($department && !empty($this->departmentName)) {
            $department->update(['name' => trim($this->departmentName)]);
        }

        $ccItems = array_values(array_filter(array_map('trim', explode("\n", str_replace("\r", '', $this->ccList)))));

        Setting::updateOrCreate(
            [
                'department_id' => $session->department_id,
                'exam_session_id' => $session->id,
            ],
            [
                'default_duration_minutes' => $this->defaultDurationMinutes,
                'break_minutes' => $this->breakMinutes,
                'shift_rule_strict' => $this->shiftRuleStrict,
                'working_days' => $this->workingDays,
                'weights' => $this->weights,
                'branding' => [
                    'university_name' => trim($this->universityName),
                    'department_name' => trim($this->departmentName),
                    'exam_title' => trim($this->examTitle),
                    'session_term' => trim($this->sessionTerm),
                    'ref_no' => trim($this->refNo),
                    'issue_date' => trim($this->issueDate),
                    'chairperson_name' => trim($this->chairpersonName),
                    'chairperson_title' => trim($this->chairpersonTitle),
                    'left_logo' => trim($this->leftLogo),
                    'right_logo' => trim($this->rightLogo),
                    'signature_img' => trim($this->signatureImg),
                    'cc_list' => $ccItems,
                ],
            ]
        );

        $this->calculateProjectedSlots($slotGenerator);
        session()->flash('success', 'Examination settings & institute branding updated successfully!');
    }

    public function addShift(SlotGenerator $slotGenerator): void
    {
        if (empty($this->newShiftName)) {
            return;
        }

        Shift::create([
            'department_id' => $this->selectedDepartmentId,
            'name' => $this->newShiftName,
            'start_time' => $this->newShiftStart,
            'end_time' => $this->newShiftEnd,
            'sort_order' => Shift::where('department_id', $this->selectedDepartmentId)->count() + 1,
            'is_active' => true,
        ]);

        $this->newShiftName = '';
        $this->calculateProjectedSlots($slotGenerator);
        session()->flash('success', 'New shift added successfully.');
    }

    public function updatedSessionStartDate(string $value, SlotGenerator $slotGenerator): void
    {
        if (!empty($value)) {
            $this->sessionEndDate = Carbon::parse($value)->addDays(21)->format('Y-m-d');
            $this->calculateProjectedSlots($slotGenerator);
        }
    }

    public function toggleShift(int $shiftId, SlotGenerator $slotGenerator): void
    {
        $shift = Shift::findOrFail($shiftId);
        $shift->update(['is_active' => !$shift->is_active]);
        $this->calculateProjectedSlots($slotGenerator);
    }

    public function deleteShift(int $shiftId, SlotGenerator $slotGenerator): void
    {
        $shift = Shift::findOrFail($shiftId);
        \App\Models\Batch::where('shift_id', $shiftId)->update(['shift_id' => null]);
        $shift->delete();
        $this->calculateProjectedSlots($slotGenerator);
        session()->flash('success', 'Shift deleted successfully.');
    }

    public function deleteRoom(int $roomId): void
    {
        $room = Room::findOrFail($roomId);
        $room->exams()->detach();
        $room->delete();
        session()->flash('success', 'Room deleted successfully.');
    }

    public function addRoom(): void
    {
        if (empty($this->newRoomName)) {
            return;
        }

        Room::create([
            'department_id' => $this->selectedDepartmentId,
            'name' => $this->newRoomName,
            'capacity' => $this->newRoomCapacity ?: 50,
            'type' => $this->newRoomType ?: 'classroom',
            'is_active' => true,
        ]);

        $this->newRoomName = '';
        session()->flash('success', 'New room added successfully.');
    }

    public function toggleRoom(int $roomId): void
    {
        $room = Room::findOrFail($roomId);
        $room->update(['is_active' => !$room->is_active]);
    }

    public function resetToCleanSlate(SlotGenerator $slotGenerator): void
    {
        \Illuminate\Support\Facades\DB::statement('SET FOREIGN_KEY_CHECKS=0;');
        \App\Models\ExamRoomAssignment::truncate();
        \App\Models\ExamInvigilator::truncate();
        \App\Models\Exam::truncate();
        \App\Models\Datesheet::truncate();
        \App\Models\Enrollment::truncate();
        \App\Models\TeacherCourse::truncate();
        \App\Models\Course::truncate();
        \App\Models\Batch::truncate();
        \Illuminate\Support\Facades\DB::statement('SET FOREIGN_KEY_CHECKS=1;');

        $this->calculateProjectedSlots($slotGenerator);
        session()->flash('success', 'Workspace reset to clean slate! All courses, batches, and datesheets have been cleared for a fresh start.');
    }

    public function loadDemoData(SlotGenerator $slotGenerator): void
    {
        \Illuminate\Support\Facades\Artisan::call('datesheet:demo');
        $this->mount($slotGenerator);
        session()->flash('success', 'Official BGNU demo data loaded successfully with 0 hard conflicts!');
    }

    public function render()
    {
        $shifts = $this->selectedDepartmentId
            ? Shift::where('department_id', $this->selectedDepartmentId)->orderBy('sort_order')->get()
            : collect();

        $rooms = $this->selectedDepartmentId
            ? Room::where('department_id', $this->selectedDepartmentId)->orderBy('name')->get()
            : collect();

        $holidays = $this->selectedSessionId
            ? Holiday::where('exam_session_id', $this->selectedSessionId)->orderBy('date')->get()
            : collect();

        return view('livewire.settings-manager', [
            'shifts' => $shifts,
            'rooms' => $rooms,
            'holidays' => $holidays,
        ])->layout('layouts.app', ['title' => 'Settings — Datesheet Generator']);
    }
}
