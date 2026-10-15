<?php

declare(strict_types=1);

namespace App\Exports\Templates;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class TeacherTemplateExport implements FromArray, WithHeadings, ShouldAutoSize, WithStyles
{
    public function array(): array
    {
        return [
            ['Dr. Tariq Mahmood', 'tariq.mahmood@university.edu.pk', 2, 8],
            ['Prof. Sarah Ahmed', 'sarah.ahmed@university.edu.pk', 2, 8],
            ['Engr. Bilal Khan', 'bilal.khan@university.edu.pk', 1, 6],
            ['Ms. Ayesha Siddiqui', 'ayesha.siddiqui@university.edu.pk', 2, 8],
        ];
    }

    public function headings(): array
    {
        return [
            'teacher_name',
            'email',
            'max_duties_per_day',
            'max_duties_per_session',
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
