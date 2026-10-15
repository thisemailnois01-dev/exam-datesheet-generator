<?php

declare(strict_types=1);

namespace App\Exports;

use App\Models\Datesheet;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class TeacherDutyRosterExport implements FromCollection, WithHeadings, ShouldAutoSize, WithStyles
{
    public function __construct(
        private readonly int $datesheetId,
    ) {}

    public function collection()
    {
        $datesheet = Datesheet::findOrFail($this->datesheetId);

        $exams = $datesheet->exams()
            ->with(['course', 'batch', 'shift', 'rooms', 'invigilatorAssignments.teacher'])
            ->orderBy('exam_date')
            ->orderBy('slot_index')
            ->get();

        $rows = collect();

        foreach ($exams as $exam) {
            foreach ($exam->invigilatorAssignments as $invigAssignment) {
                $teacher = $invigAssignment->teacher;
                $rows->push([
                    'teacher_name' => $teacher->name,
                    'email' => $teacher->email,
                    'exam_date' => $exam->exam_date->format('Y-m-d'),
                    'day' => $exam->exam_date->format('l'),
                    'shift' => $exam->shift->name,
                    'timing' => sprintf('%s - %s', $exam->start_time, $exam->end_time),
                    'room' => $exam->rooms->pluck('name')->implode(', ') ?: 'General Hall',
                    'course' => sprintf('%s: %s', $exam->course->code, $exam->course->name),
                    'batch' => $exam->batch->name,
                ]);
            }
        }

        return $rows->sortBy('teacher_name');
    }

    public function headings(): array
    {
        return [
            'Teacher / Invigilator',
            'Email',
            'Duty Date',
            'Day',
            'Shift',
            'Duty Timing',
            'Assigned Room',
            'Course Offering',
            'Batch Supervised',
        ];
    }

    public function styles(Worksheet $sheet): array
    {
        return [
            1 => [
                'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
                'fill' => [
                    'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                    'startColor' => ['rgb' => '065F46'], // Emerald Green
                ],
            ],
        ];
    }
}
