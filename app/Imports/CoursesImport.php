<?php

declare(strict_types=1);

namespace App\Imports;

use App\Models\Batch;
use App\Models\Course;
use App\Models\Department;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

class CoursesImport implements ToCollection, WithHeadingRow
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
            $rowNumber = $index + 2; // Accounting for 1-based index and header row

            $courseCode = trim((string)($row['course_code'] ?? ''));
            $courseName = trim((string)($row['course_name'] ?? ''));
            $batchName = trim((string)($row['batch_name'] ?? ''));
            $creditHours = (int)($row['credit_hours'] ?? 3);
            $type = strtolower(trim((string)($row['type'] ?? 'theory'))) ?: 'theory';
            $duration = !empty($row['duration_override_minutes']) ? (int)$row['duration_override_minutes'] : null;
            $isCore = in_array(strtolower(trim((string)($row['is_core'] ?? ''))), ['yes', 'true', '1'], true);
            $isHard = in_array(strtolower(trim((string)($row['is_hard'] ?? ''))), ['yes', 'true', '1'], true);

            if (empty($courseCode)) {
                $this->errors[] = sprintf('Row %d: course_code is required.', $rowNumber);
                continue;
            }

            if (empty($courseName)) {
                $this->errors[] = sprintf('Row %d: course_name is required.', $rowNumber);
                continue;
            }

            $batchId = null;
            if (!empty($batchName)) {
                $batch = Batch::firstOrCreate(
                    ['department_id' => $department->id, 'name' => $batchName],
                    ['student_count' => 40]
                );
                $batchId = $batch->id;
            }

            // Create or update course
            Course::updateOrCreate(
                [
                    'department_id' => $department->id,
                    'code' => $courseCode,
                    'batch_id' => $batchId,
                ],
                [
                    'name' => $courseName,
                    'credit_hours' => $creditHours ?: 3,
                    'type' => in_array($type, ['theory', 'practical', 'viva'], true) ? $type : 'theory',
                    'duration_minutes' => $duration,
                    'is_core' => $isCore,
                    'is_hard' => $isHard,
                ]
            );

            $this->importedCount++;
        }
    }
}
