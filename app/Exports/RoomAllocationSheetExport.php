<?php

declare(strict_types=1);

namespace App\Exports;

use App\Models\Datesheet;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class RoomAllocationSheetExport implements FromCollection, WithHeadings, ShouldAutoSize, WithStyles
{
    public function __construct(
        private readonly int $datesheetId,
    ) {}

    public function collection()
    {
        $datesheet = Datesheet::findOrFail($this->datesheetId);

        $exams = $datesheet->exams()
            ->with(['course', 'batch', 'shift', 'roomAssignments.room', 'invigilators'])
            ->orderBy('exam_date')
            ->orderBy('slot_index')
            ->get();

        $rows = collect();

        foreach ($exams as $exam) {
            foreach ($exam->roomAssignments as $assignment) {
                $room = $assignment->room;
                $rows->push([
                    'room_name' => $room->name,
                    'capacity' => $room->capacity,
                    'exam_date' => $exam->exam_date->format('Y-m-d'),
                    'day' => $exam->exam_date->format('l'),
                    'shift' => $exam->shift->name,
                    'slot' => 'Slot ' . $exam->slot_index,
                    'timing' => sprintf('%s - %s', $exam->start_time, $exam->end_time),
                    'course' => sprintf('%s: %s', $exam->course->code, $exam->course->name),
                    'batch' => $exam->batch->name,
                    'students_seated' => $assignment->student_count ?: $exam->batch->student_count,
                    'invigilators' => $exam->invigilators->pluck('name')->implode(', ') ?: 'Assigned Staff',
                ]);
            }
        }

        return $rows->sortBy(['room_name', 'exam_date', 'slot']);
    }

    public function headings(): array
    {
        return [
            'Room Name',
            'Room Capacity',
            'Exam Date',
            'Day',
            'Shift',
            'Slot',
            'Time Window',
            'Course Examination',
            'Batch Cohort',
            'Students Seated',
            'Invigilation Staff',
        ];
    }

    public function styles(Worksheet $sheet): array
    {
        return [
            1 => [
                'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
                'fill' => [
                    'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                    'startColor' => ['rgb' => '4338CA'], // Indigo
                ],
            ],
        ];
    }
}
