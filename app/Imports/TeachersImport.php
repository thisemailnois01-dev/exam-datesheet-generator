<?php

declare(strict_types=1);

namespace App\Imports;

use App\Models\Department;
use App\Models\Teacher;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

class TeachersImport implements ToCollection, WithHeadingRow
{
    /** @var array<string> */
    public array $errors = [];

    public int $importedCount = 0;

    public function __construct(
        private readonly int $departmentId,
    ) {}

    public function collection(Collection $rows)
    {
        $department = Department::findOrFail($this->departmentId);

        foreach ($rows as $index => $row) {
            $rowNumber = $index + 2;

            $teacherName = trim((string)($row['teacher_name'] ?? ''));
            $email = trim((string)($row['email'] ?? ''));
            $maxPerDay = (int)($row['max_duties_per_day'] ?? 2) ?: 2;
            $maxPerSession = (int)($row['max_duties_per_session'] ?? 10) ?: 10;

            if (empty($teacherName)) {
                $this->errors[] = sprintf('Row %d: teacher_name is required.', $rowNumber);
                continue;
            }

            if (!empty($email) && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $this->errors[] = sprintf('Row %d: invalid email address "%s".', $rowNumber, $email);
                continue;
            }

            Teacher::updateOrCreate(
                [
                    'department_id' => $department->id,
                    'name' => $teacherName,
                ],
                [
                    'email' => $email ?: null,
                    'max_duties_per_day' => $maxPerDay,
                    'max_duties_per_session' => $maxPerSession,
                ]
            );

            $this->importedCount++;
        }
    }
}
