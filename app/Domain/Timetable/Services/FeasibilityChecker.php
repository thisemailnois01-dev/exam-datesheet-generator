<?php

declare(strict_types=1);

namespace App\Domain\Timetable\Services;

use App\Domain\Timetable\DTOs\CourseNodeDto;
use App\Domain\Timetable\DTOs\SlotDto;

final class FeasibilityChecker
{
    /**
     * Check theoretical capacity and constraints before attempting to solve.
     *
     * @param array<CourseNodeDto> $courses
     * @param array<SlotDto> $slots
     * @param int $totalActiveRooms
     * @param bool $isShiftStrict
     * @return array{
     *     is_feasible: bool,
     *     bottlenecks: array<string>,
     *     suggestions: array<string>,
     *     metrics: array<string, mixed>
     * }
     */
    public function checkFeasibility(
        array $courses,
        array $slots,
        int $totalActiveRooms,
        bool $isShiftStrict = true,
    ): array {
        $bottlenecks = [];
        $suggestions = [];

        $totalCourses = count($courses);
        $totalSlots = count($slots);
        $totalRoomSlots = $totalSlots * $totalActiveRooms;

        // Metric 1: Zero rooms or slots available
        if ($totalActiveRooms <= 0) {
            $bottlenecks[] = 'No active examination rooms configured.';
            $suggestions[] = 'Add or activate at least one examination room in Room Settings.';
            return [
                'is_feasible' => false,
                'bottlenecks' => $bottlenecks,
                'suggestions' => $suggestions,
                'metrics' => ['total_courses' => $totalCourses, 'available_rooms' => 0],
            ];
        }

        if ($totalSlots <= 0) {
            $bottlenecks[] = 'Zero valid time slots generated from the current calendar and shift windows.';
            $suggestions[] = 'Verify that the Exam Session start/end dates contain working weekdays and that shift start/end times allow at least one duration window.';
            return [
                'is_feasible' => false,
                'bottlenecks' => $bottlenecks,
                'suggestions' => $suggestions,
                'metrics' => ['total_courses' => $totalCourses, 'available_slots' => 0],
            ];
        }

        // Metric 2: Global room-slot capacity
        if ($totalCourses > $totalRoomSlots) {
            $shortfall = $totalCourses - $totalRoomSlots;
            $bottlenecks[] = sprintf(
                'Total course exams (%d) exceed overall room-slot capacity (%d). Shortfall: %d exam(s).',
                $totalCourses,
                $totalRoomSlots,
                $shortfall
            );
            $suggestions[] = sprintf('Add at least %d room(s) or extend the exam calendar dates.', (int) ceil($shortfall / max(1, $totalSlots)));
        }

        // Metric 3: Per-shift slot & room capacity
        $coursesPerShift = [];
        foreach ($courses as $course) {
            $shiftId = $course->shiftId ?? 0;
            $coursesPerShift[$shiftId] = ($coursesPerShift[$shiftId] ?? 0) + 1;
        }

        $slotsPerShift = [];
        foreach ($slots as $slot) {
            $slotsPerShift[$slot->shiftId] = ($slotsPerShift[$slot->shiftId] ?? 0) + 1;
        }

        if ($isShiftStrict) {
            foreach ($coursesPerShift as $shiftId => $courseCount) {
                $availableSlotsInShift = $slotsPerShift[$shiftId] ?? 0;
                $roomSlotsInShift = $availableSlotsInShift * $totalActiveRooms;

                if ($courseCount > $roomSlotsInShift) {
                    $deficit = $courseCount - $roomSlotsInShift;
                    $bottlenecks[] = sprintf(
                        'Shift ID %d has %d exams scheduled, but only %d room-slots available (%d slots × %d rooms). Deficit: %d.',
                        $shiftId,
                        $courseCount,
                        $roomSlotsInShift,
                        $availableSlotsInShift,
                        $totalActiveRooms,
                        $deficit
                    );
                    $suggestions[] = sprintf(
                        'Widen the time window for Shift ID %d, add exam calendar days, or switch Shift Rule to Preferred (soft).',
                        $shiftId
                    );
                }
            }
        }

        // Metric 4: Per-batch slot availability (no batch can have 2 exams in 1 slot)
        $coursesPerBatch = [];
        $batchShiftMap = [];
        foreach ($courses as $course) {
            $coursesPerBatch[$course->batchId] = ($coursesPerBatch[$course->batchId] ?? 0) + 1;
            $batchShiftMap[$course->batchId] = $course->shiftId;
        }

        foreach ($coursesPerBatch as $batchId => $batchCourseCount) {
            $shiftId = $batchShiftMap[$batchId];
            $availableShiftSlots = $isShiftStrict
                ? ($slotsPerShift[$shiftId] ?? 0)
                : $totalSlots;

            if ($batchCourseCount > $availableShiftSlots) {
                $bottlenecks[] = sprintf(
                    'Batch ID %d has %d courses to take, but its shift offers only %d distinct time slots.',
                    $batchId,
                    $batchCourseCount,
                    $availableShiftSlots
                );
                $neededDays = (int) ceil(($batchCourseCount - $availableShiftSlots) / 2);
                $suggestions[] = sprintf(
                    'Batch ID %d needs at least %d distinct exam slots. Extend the exam period by at least %d day(s).',
                    $batchId,
                    $batchCourseCount,
                    max(1, $neededDays)
                );
            }
        }

        $isFeasible = empty($bottlenecks);

        return [
            'is_feasible' => $isFeasible,
            'bottlenecks' => $bottlenecks,
            'suggestions' => $suggestions,
            'metrics' => [
                'total_courses' => $totalCourses,
                'total_slots' => $totalSlots,
                'total_active_rooms' => $totalActiveRooms,
                'total_room_slots' => $totalRoomSlots,
                'is_shift_strict' => $isShiftStrict,
            ],
        ];
    }
}
