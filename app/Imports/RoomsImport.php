<?php

declare(strict_types=1);

namespace App\Imports;

use App\Models\Department;
use App\Models\Room;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

class RoomsImport implements ToCollection, WithHeadingRow
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

            $roomName = trim((string)($row['room_name'] ?? ''));
            $capacity = (int)($row['capacity'] ?? 0);
            $type = strtolower(trim((string)($row['type'] ?? 'classroom'))) ?: 'classroom';
            $isActive = !in_array(strtolower(trim((string)($row['is_active'] ?? 'yes'))), ['no', 'false', '0'], true);

            if (empty($roomName)) {
                $this->errors[] = sprintf('Row %d: room_name is required.', $rowNumber);
                continue;
            }

            if ($capacity <= 0) {
                $this->errors[] = sprintf('Row %d: capacity must be a positive integer (found "%s").', $rowNumber, $row['capacity'] ?? '');
                continue;
            }

            Room::updateOrCreate(
                [
                    'department_id' => $department->id,
                    'name' => $roomName,
                ],
                [
                    'capacity' => $capacity,
                    'type' => in_array($type, ['hall', 'classroom', 'lab'], true) ? $type : 'classroom',
                    'is_active' => $isActive,
                ]
            );

            $this->importedCount++;
        }
    }
}
