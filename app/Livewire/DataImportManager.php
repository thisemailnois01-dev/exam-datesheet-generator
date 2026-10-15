<?php

declare(strict_types=1);

namespace App\Livewire;

use App\Imports\CoursesImport;
use App\Imports\RoomsImport;
use App\Imports\StudentsImport;
use App\Imports\TeachersImport;
use App\Models\Department;
use App\Models\ExamSession;
use Livewire\Component;
use Livewire\WithFileUploads;
use Maatwebsite\Excel\Facades\Excel;

class DataImportManager extends Component
{
    use WithFileUploads;

    public ?int $selectedDepartmentId = null;

    // Uploaded files
    public $coursesFile;
    public $roomsFile;
    public $teachersFile;
    public $studentsFile;

    // Feedback
    public array $importErrors = [];
    public ?string $importSuccessMessage = null;

    public function mount(): void
    {
        $session = ExamSession::with('department')->latest()->first();
        if ($session) {
            $this->selectedDepartmentId = $session->department_id;
        } else {
            $dept = Department::first();
            $this->selectedDepartmentId = $dept?->id;
        }
    }

    public function importCourses(): void
    {
        $this->validate(['coursesFile' => 'required|file|mimes:xlsx,xls,csv|max:10240']);

        $importer = new CoursesImport($this->selectedDepartmentId);
        Excel::import($importer, $this->coursesFile->getRealPath());

        $this->importErrors = $importer->errors;
        if (empty($this->importErrors)) {
            $this->importSuccessMessage = sprintf('Successfully imported %d course offerings!', $importer->importedCount);
        }
        $this->reset('coursesFile');
    }

    public function importRooms(): void
    {
        $this->validate(['roomsFile' => 'required|file|mimes:xlsx,xls,csv|max:10240']);

        $importer = new RoomsImport($this->selectedDepartmentId);
        Excel::import($importer, $this->roomsFile->getRealPath());

        $this->importErrors = $importer->errors;
        if (empty($this->importErrors)) {
            $this->importSuccessMessage = sprintf('Successfully imported %d examination rooms!', $importer->importedCount);
        }
        $this->reset('roomsFile');
    }

    public function importTeachers(): void
    {
        $this->validate(['teachersFile' => 'required|file|mimes:xlsx,xls,csv|max:10240']);

        $importer = new TeachersImport($this->selectedDepartmentId);
        Excel::import($importer, $this->teachersFile->getRealPath());

        $this->importErrors = $importer->errors;
        if (empty($this->importErrors)) {
            $this->importSuccessMessage = sprintf('Successfully imported %d faculty teachers!', $importer->importedCount);
        }
        $this->reset('teachersFile');
    }

    public function importStudents(): void
    {
        $this->validate(['studentsFile' => 'required|file|mimes:xlsx,xls,csv|max:10240']);

        $importer = new StudentsImport($this->selectedDepartmentId);
        Excel::import($importer, $this->studentsFile->getRealPath());

        $this->importErrors = $importer->errors;
        if (empty($this->importErrors)) {
            $this->importSuccessMessage = sprintf('Successfully imported %d students and course enrollments!', $importer->importedCount);
        }
        $this->reset('studentsFile');
    }

    public function render()
    {
        $departments = Department::orderBy('name')->get();

        return view('livewire.data-import-manager', [
            'departments' => $departments,
        ])->layout('layouts.app', ['title' => 'Excel Import & Templates — Datesheet Generator']);
    }
}
