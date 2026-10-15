<?php

declare(strict_types=1);

namespace App\Domain\Timetable\Services;

use App\Domain\Timetable\DTOs\CourseNodeDto;
use App\Domain\Timetable\DTOs\ScheduleAssignmentDto;

final class RoomAllocator
{
    /**
     * Assign available rooms to scheduled exams per slot.
     *
     * @param array<ScheduleAssignmentDto> $assignments
     * @param array<string, CourseNodeDto> $coursesByKey
     * @param array<array{id: int, name: string, capacity: int, type: string, is_active: bool}> $rooms
     * @return array<ScheduleAssignmentDto>
     */
    public function allocateRooms(
        array $assignments,
        array $coursesByKey,
        array $rooms,
    ): array {
        // Filter active rooms
        $activeRooms = array_values(array_filter($rooms, fn($r) => ($r['is_active'] ?? true)));

        if (empty($activeRooms)) {
            return $assignments;
        }

        // Group assignments by slot key: 'date_shiftId_slotIndex'
        $assignmentsBySlot = [];
        foreach ($assignments as $index => $assignment) {
            $slotKey = $assignment->slot->getKey();
            $assignmentsBySlot[$slotKey][] = $index;
        }

        $batchPreferredRoom = [];

        foreach ($assignmentsBySlot as $slotKey => $assignmentIndices) {
            $usedRoomsInSlot = [];
            $unassignedIndices = [];

            // Pass 1: Assign batch's preferred room if free in this slot
            foreach ($assignmentIndices as $idx) {
                $bId = $assignments[$idx]->batchId;
                if (isset($batchPreferredRoom[$bId]) && !isset($usedRoomsInSlot[$batchPreferredRoom[$bId]])) {
                    $roomId = $batchPreferredRoom[$bId];
                    $assignments[$idx]->roomIds = [$roomId];
                    $usedRoomsInSlot[$roomId] = true;
                } else {
                    $unassignedIndices[] = $idx;
                }
            }

            // Pass 2: Assign remaining free rooms
            $availableRooms = array_values(array_filter($activeRooms, fn($r) => !isset($usedRoomsInSlot[$r['id']])));
            $rIndex = 0;
            foreach ($unassignedIndices as $idx) {
                if ($rIndex < count($availableRooms)) {
                    $room = $availableRooms[$rIndex];
                    $assignments[$idx]->roomIds = [$room['id']];
                    $usedRoomsInSlot[$room['id']] = true;
                    $batchPreferredRoom[$assignments[$idx]->batchId] = $room['id'];
                    $rIndex++;
                }
            }
        }

        return $assignments;
    }
}
