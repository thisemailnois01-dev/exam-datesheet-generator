<?php

declare(strict_types=1);

namespace App\Livewire;

use App\Models\Datesheet;
use App\Models\ExamSession;
use Livewire\Component;
use Spatie\Activitylog\Models\Activity;

class VersionManager extends Component
{
    public ?int $selectedSessionId = null;
    public ?int $compareVersion1Id = null;
    public ?int $compareVersion2Id = null;

    public function mount(): void
    {
        $session = ExamSession::with('department')->latest()->first();
        if ($session) {
            $this->selectedSessionId = $session->id;
        }
    }

    public function restoreVersion(int $datesheetId): void
    {
        $datesheet = Datesheet::findOrFail($datesheetId);
        $latestVersion = Datesheet::where('exam_session_id', $datesheet->exam_session_id)->max('version') ?? 0;

        // Clone this version as the new active version
        $restored = $datesheet->replicate();
        $restored->name = sprintf('Restored from v%d (%s)', $datesheet->version, now()->format('M d, H:i'));
        $restored->version = $latestVersion + 1;
        $restored->status = 'draft';
        $restored->save();

        // Replicate exams
        foreach ($datesheet->exams()->with(['roomAssignments', 'invigilatorAssignments'])->get() as $exam) {
            $newExam = $exam->replicate();
            $newExam->datesheet_id = $restored->id;
            $newExam->save();

            foreach ($exam->roomAssignments as $ra) {
                $newRa = $ra->replicate();
                $newRa->exam_id = $newExam->id;
                $newRa->save();
            }

            foreach ($exam->invigilatorAssignments as $ia) {
                $newIa = $ia->replicate();
                $newIa->exam_id = $newExam->id;
                $newIa->save();
            }
        }

        activity()
            ->performedOn($restored)
            ->log(sprintf('Restored datesheet version %d to new active version %d', $datesheet->version, $restored->version));

        session()->flash('success', sprintf('Version %d successfully restored as new active version %d!', $datesheet->version, $restored->version));
    }

    public function render()
    {
        $versions = $this->selectedSessionId
            ? Datesheet::where('exam_session_id', $this->selectedSessionId)->with('exams')->orderByDesc('version')->get()
            : collect();

        $activities = Activity::latest()->take(20)->get();

        $comp1 = $this->compareVersion1Id ? Datesheet::with('exams')->find($this->compareVersion1Id) : null;
        $comp2 = $this->compareVersion2Id ? Datesheet::with('exams')->find($this->compareVersion2Id) : null;

        return view('livewire.version-manager', [
            'versions' => $versions,
            'activities' => $activities,
            'comp1' => $comp1,
            'comp2' => $comp2,
        ])->layout('layouts.app', ['title' => 'Datesheet Versions & Audit Logs']);
    }
}
