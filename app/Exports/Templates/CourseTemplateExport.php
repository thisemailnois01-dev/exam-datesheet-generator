<?php

declare(strict_types=1);

namespace App\Exports\Templates;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class CourseTemplateExport implements FromArray, WithHeadings, ShouldAutoSize, WithStyles
{
    public function array(): array
    {
        return [
            ['CS-101', 'Programming Fundamentals', 'BSCS-8A', 4, 'theory', 60, 'yes', 'no'],
            ['CS-201', 'Data Structures', 'BSCS-8A', 4, 'theory', 60, 'yes', 'yes'],
            ['SE-301', 'Software Engineering', 'BSSE-6A', 3, 'theory', '', 'no', 'no'],
            ['AI-401', 'Artificial Intelligence', 'BSAI-4A', 3, 'theory', 90, 'no', 'yes'],
        ];
    }

    public function headings(): array
    {
        return [
            'course_code',
            'course_name',
            'batch_name',
            'credit_hours',
            'type',
            'duration_override_minutes',
            'is_core',
            'is_hard',
        ];
    }

    public function styles(Worksheet $sheet): array
    {
        return [
            1 => [
                'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
                'fill' => [
                    'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                    'startColor' => ['rgb' => '1F2937'],
                ],
            ],
        ];
    }
}
