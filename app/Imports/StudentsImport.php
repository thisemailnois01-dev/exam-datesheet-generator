<?php

declare(strict_types=1);

namespace App\Imports;

use App\Models\Batch;
use App\Models\Course;
use App\Models\Department;
use App\Models\Enrollment;
use App\Models\Student;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

class StudentsImport implements ToCollection, WithHeadingRow
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

            $rollNumber = trim((string)($row['roll_number'] ?? ''));
            $studentName = trim((string)($row['student_name'] ?? ''));
            $batchName = trim((string)($row['batch_name'] ?? ''));
            $courseCode = trim((string)($row['course_code'] ?? ''));
            $type = strtolower(trim((string)($row['enrollment_type'] ?? 'regular'))) ?: 'regular';

            if (empty($rollNumber)) {
                $this->errors[] = sprintf('Row %d: roll_number is required.', $rowNumber);
                continue;
            }

            if (empty($studentName)) {
                $this->errors[] = sprintf('Row %d: student_name is required.', $rowNumber);
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

            $student = Student::updateOrCreate(
                [
                    'department_id' => $department->id,
                    'roll_number' => $rollNumber,
                ],
                [
                    'name' => $studentName,
                    'batch_id' => $batchId,
                ]
            );

            if (!empty($courseCode)) {
                $course = Course::where('department_id', $department->id)
                    ->where('code', $courseCode)
                    ->first();

                if (!$course) {
                    $this->errors[] = sprintf('Row %d: course code "%s" not found in department.', $rowNumber, $courseCode);
                    continue;
                }

                Enrollment::updateOrCreate(
                    [
                        'student_id' => $student->id,
                        'course_id' => $course->id,
                    ],
                    [
                        'batch_id' => $student->batch_id,
                        'type' => in_array($type, ['regular', 'repeater', 'improvement', 'elective'], true) ? $type : 'regular',
                    ]
                );
            }

            $this->importedCount++;
        }
    }
}
