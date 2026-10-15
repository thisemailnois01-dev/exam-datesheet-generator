<?php

declare(strict_types=1);

namespace App\Exports;

use App\Models\Datesheet;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class BatchDatesheetExport implements FromCollection, WithHeadings, ShouldAutoSize, WithStyles
{
    public function __construct(
        private readonly int $datesheetId,
        private readonly ?int $batchId = null,
    ) {}

    public function collection()
    {
        $query = Datesheet::findOrFail($this->datesheetId)
            ->exams()
            ->with(['course', 'batch', 'shift', 'rooms', 'invigilators'])
            ->orderBy('exam_date')
            ->orderBy('slot_index');

        if ($this->batchId !== null) {
            $query->where('batch_id', $this->batchId);
        }

        return $query->get()->map(function ($exam) {
            return [
                'batch' => $exam->batch->name,
                'date' => $exam->exam_date->format('Y-m-d'),
                'day' => $exam->exam_date->format('l'),
                'shift' => $exam->shift->name,
                'slot' => 'Slot ' . $exam->slot_index,
                'time' => sprintf('%s - %s', $exam->start_time, $exam->end_time),
                'course_code' => $exam->course->code,
                'course_name' => $exam->course->name,
                'rooms' => $exam->rooms->pluck('name')->implode(', ') ?: 'Unassigned',
                'invigilators' => $exam->invigilators->pluck('name')->implode(', ') ?: 'Unassigned',
            ];
        });
    }

    public function headings(): array
    {
        return [
            'Batch / Section',
            'Exam Date',
            'Day',
            'Shift',
            'Slot',
            'Exam Timing',
            'Course Code',
            'Course Title',
            'Allocated Room(s)',
            'Assigned Invigilator(s)',
        ];
    }

    public function styles(Worksheet $sheet): array
    {
        return [
            1 => [
                'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
                'fill' => [
                    'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                    'startColor' => ['rgb' => '1E3A8A'], // Navy
                ],
            ],
        ];
    }
}
