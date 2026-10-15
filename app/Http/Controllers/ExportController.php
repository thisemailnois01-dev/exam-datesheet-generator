<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Exports\BatchDatesheetExport;
use App\Exports\RoomAllocationSheetExport;
use App\Exports\TeacherDutyRosterExport;
use App\Exports\Templates\CourseTemplateExport;
use App\Exports\Templates\RoomTemplateExport;
use App\Exports\Templates\StudentEnrollmentTemplateExport;
use App\Exports\Templates\TeacherTemplateExport;
use App\Models\Datesheet;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;

class ExportController extends Controller
{
    public function exportDatesheetExcel(int $id, Request $request)
    {
        $batchId = $request->query('batch_id') ? (int) $request->query('batch_id') : null;
        $datesheet = Datesheet::findOrFail($id);
        $filename = sprintf('Datesheet_%s_v%d.xlsx', str_replace(' ', '_', $datesheet->name), $datesheet->version);

        return Excel::download(new BatchDatesheetExport($id, $batchId), $filename);
    }

    public function exportTeacherDutyExcel(int $id)
    {
        $datesheet = Datesheet::findOrFail($id);
        $filename = sprintf('Faculty_Duty_Roster_v%d.xlsx', $datesheet->version);

        return Excel::download(new TeacherDutyRosterExport($id), $filename);
    }

    public function exportRoomAllocationExcel(int $id)
    {
        $datesheet = Datesheet::findOrFail($id);
        $filename = sprintf('Room_Allocation_Sheet_v%d.xlsx', $datesheet->version);

        return Excel::download(new RoomAllocationSheetExport($id), $filename);
    }

    public function downloadTemplate(string $type)
    {
        return match ($type) {
            'courses' => Excel::download(new CourseTemplateExport(), 'courses_template.xlsx'),
            'rooms' => Excel::download(new RoomTemplateExport(), 'rooms_template.xlsx'),
            'teachers' => Excel::download(new TeacherTemplateExport(), 'teachers_template.xlsx'),
            'students' => Excel::download(new StudentEnrollmentTemplateExport(), 'student_enrollments_template.xlsx'),
            default => abort(404, 'Invalid template type'),
        };
    }

    public function exportDatesheetPdf(int $id, Request $request)
    {
        $batchId = $request->query('batch_id') ? (int) $request->query('batch_id') : null;

        $datesheet = Datesheet::with([
            'examSession',
            'department',
            'exams.course.teachers',
            'exams.batch.shift',
            'exams.shift',
            'exams.rooms',
            'exams.invigilators'
        ])->findOrFail($id);

        $allExams = $datesheet->exams->sortBy(function ($exam) {
            return sprintf('%s_%03d', $exam->exam_date->format('Y-m-d'), $exam->slot_index);
        });

        $allBatches = $datesheet->exams->pluck('batch')->filter()->unique('id')->values();

        if ($batchId) {
            $allExams = $allExams->where('batch_id', $batchId);
        }

        $examsGroupedByBatch = $allExams->groupBy('batch_id');

        return view('pdf.datesheet', compact('datesheet', 'examsGroupedByBatch', 'allBatches', 'batchId'));
    }
}
