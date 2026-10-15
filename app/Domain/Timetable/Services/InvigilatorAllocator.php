<?php

declare(strict_types=1);

namespace App\Domain\Timetable\Services;

use App\Domain\Timetable\DTOs\CourseNodeDto;
use App\Domain\Timetable\DTOs\ScheduleAssignmentDto;

final class InvigilatorAllocator
{
    /**
     * Allocate teachers as invigilators with balanced duty counts and clash avoidance.
     *
     * @param array<ScheduleAssignmentDto> $assignments
     * @param array<string, CourseNodeDto> $coursesByKey
     * @param array<array{id: int, name: string, max_duties_per_day: int, max_duties_per_session: int}> $teachers
     * @param array<array{teacher_id: int, date: string, slot_index: ?int, is_available: bool}> $unavailabilities
     * @return array<ScheduleAssignmentDto>
     */
    public function allocateInvigilators(
        array $assignments,
        array $coursesByKey,
        array $teachers,
        array $unavailabilities = [],
    ): array {
        if (empty($teachers)) {
            return $assignments;
        }

        // Map unavailabilities: [teacher_id][date][slot_index_or_zero] => true
        $blackoutMap = [];
        foreach ($unavailabilities as $unavail) {
            if (!($unavail['is_available'] ?? false)) {
                $teacherId = (int) $unavail['teacher_id'];
                $date = $unavail['date'];
                $slotIdx = $unavail['slot_index'] ?? 0;
                $blackoutMap[$teacherId][$date][$slotIdx] = true;
            }
        }

        // Track total duties per teacher for fair load-balancing
        $dutyCounts = [];
        $dayDutyCounts = []; // [date][teacher_id] => count
        foreach ($teachers as $t) {
            $dutyCounts[$t['id']] = 0;
        }

        // Group assignments by slot
        $assignmentsBySlot = [];
        foreach ($assignments as $index => $assignment) {
            $slotKey = $assignment->slot->getKey();
            $assignmentsBySlot[$slotKey][] = $index;
        }

        foreach ($assignmentsBySlot as $slotKey => $assignmentIndices) {
            $slot = $assignments[$assignmentIndices[0]]->slot;
            $slotDate = $slot->date;
            $slotIndex = $slot->slotIndex;

            $assignedInSlot = [];

            foreach ($assignmentIndices as $idx) {
                $cKey = $assignments[$idx]->getKey();
                $cNode = $coursesByKey[$cKey] ?? null;
                $preferredTeacherIds = $cNode?->teacherIds ?? [];

                $bestTeacherId = null;

                // Priority 1: Course's own instructor if free in slot and not exceeding daily limit
                foreach ($preferredTeacherIds as $pId) {
                    if (isset($assignedInSlot[$pId])) {
                        continue;
                    }
                    $dayDuties = $dayDutyCounts[$slotDate][$pId] ?? 0;
                    $teacherMeta = null;
                    foreach ($teachers as $t) {
                        if ($t['id'] === $pId) {
                            $teacherMeta = $t;
                            break;
                        }
                    }
                    $maxPerDay = $teacherMeta['max_duties_per_day'] ?? 2;
                    if ($dayDuties >= $maxPerDay) {
                        continue;
                    }
                    if (isset($blackoutMap[$pId][$slotDate][0]) || isset($blackoutMap[$pId][$slotDate][$slotIndex])) {
                        continue;
                    }

                    $bestTeacherId = $pId;
                    break;
                }

                // Priority 2: If course instructor unavailable, select teacher with minimum accumulated duties
                if ($bestTeacherId === null) {
                    $minDuties = PHP_INT_MAX;
                    foreach ($teachers as $t) {
                        $tId = $t['id'];
                        if (isset($assignedInSlot[$tId])) {
                            continue;
                        }
                        $dayDuties = $dayDutyCounts[$slotDate][$tId] ?? 0;
                        $maxPerDay = $t['max_duties_per_day'] ?? 2;
                        if ($dayDuties >= $maxPerDay) {
                            continue;
                        }
                        if (isset($blackoutMap[$tId][$slotDate][0]) || isset($blackoutMap[$tId][$slotDate][$slotIndex])) {
                            continue;
                        }

                        if ($dutyCounts[$tId] < $minDuties) {
                            $minDuties = $dutyCounts[$tId];
                            $bestTeacherId = $tId;
                        }
                    }
                }

                if ($bestTeacherId !== null) {
                    $assignments[$idx]->invigilatorIds = [$bestTeacherId];
                    $assignedInSlot[$bestTeacherId] = true;
                    $dutyCounts[$bestTeacherId] = ($dutyCounts[$bestTeacherId] ?? 0) + 1;
                    $dayDutyCounts[$slotDate][$bestTeacherId] = ($dayDutyCounts[$slotDate][$bestTeacherId] ?? 0) + 1;
                }
            }
        }

        return $assignments;
    }
}
