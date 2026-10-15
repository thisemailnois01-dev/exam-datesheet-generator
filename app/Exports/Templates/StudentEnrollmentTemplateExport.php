<?php

declare(strict_types=1);

namespace App\Exports\Templates;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class StudentEnrollmentTemplateExport implements FromArray, WithHeadings, ShouldAutoSize, WithStyles
{
    public function array(): array
    {
        return [
            ['BSCS-001', 'Ali Hassan', 'BSCS-8A', 'CS-101', 'regular'],
            ['BSCS-002', 'Fatima Zahra', 'BSCS-8A', 'CS-101', 'regular'],
            ['BSCS-003', 'Zain Ahmed', 'BSCS-8A', 'CS-201', 'repeater'],
            ['BSSE-015', 'Bilal Tariq', 'BSSE-6A', 'CS-101', 'elective'],
        ];
    }

    public function headings(): array
    {
        return [
            'roll_number',
            'student_name',
            'batch_name',
            'course_code',
            'enrollment_type',
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
