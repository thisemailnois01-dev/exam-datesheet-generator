<?php

declare(strict_types=1);

namespace App\Domain\Timetable\Services;

use App\Domain\Timetable\DTOs\CourseNodeDto;
use App\Domain\Timetable\DTOs\ScheduleAssignmentDto;
use App\Domain\Timetable\DTOs\SlotDto;
use App\Domain\Timetable\DTOs\ValidationResultDto;

final class DatesheetValidator
{
    /**
     * Validate an entire datesheet schedule against all hard and soft constraints.
     *
     * @param array<ScheduleAssignmentDto> $assignments
     * @param array<string, CourseNodeDto> $coursesByKey
     * @param array<string, SlotDto> $validSlotsByKey
     * @param array<int, array{id: int, name: string, capacity: int}> $roomsById
     * @param array<int, array{id: int, name: string}> $teachersById
     * @param array<string, int> $softWeights
     * @param bool $isShiftStrict
     * @param int $mandatoryBreakMinutes
     * @return ValidationResultDto
     */
    public function validate(
        array $assignments,
        array $coursesByKey,
        array $validSlotsByKey,
        array $roomsById = [],
        array $teachersById = [],
        array $softWeights = [],
        bool $isShiftStrict = true,
        int $mandatoryBreakMinutes = 30,
    ): ValidationResultDto {
        $hardViolations = [];
        $softViolations = [];
        $totalPenalty = 0;

        // Default weights
        $wConsecutive = $softWeights['consecutive_exam_penalty'] ?? 50;
        $wSameDay = $softWeights['same_day_exam_penalty'] ?? 100;
        $wCoreLate = $softWeights['core_course_late_penalty'] ?? 20;
        $wShiftSoft = $softWeights['soft_shift_violation'] ?? 80;

        // 1. Verify H7: Every course is scheduled exactly once
        $scheduledCourseKeys = [];
        foreach ($assignments as $assignment) {
            $key = $assignment->getKey();
            if (isset($scheduledCourseKeys[$key])) {
                $hardViolations[] = sprintf('Course key %s is scheduled multiple times in the datesheet.', $key);
            }
            $scheduledCourseKeys[$key] = true;
        }

        foreach ($coursesByKey as $key => $course) {
            if (!isset($scheduledCourseKeys[$key])) {
                $hardViolations[] = sprintf('Required course %s (%s) was not scheduled.', $course->code, $course->name);
            }
        }

        // Tracking structures for collision detection
        $batchSlotTracker = [];         // [date_slotKey][batch_id] => course_code
        $studentSlotTracker = [];       // [date_slotKey][student_id] => course_code
        $teacherSlotTracker = [];       // [date_slotKey][teacher_id] => course_code / room_id
        $roomSlotTracker = [];          // [date_slotKey][room_id] => course_code
        $batchDatesTracker = [];        // [batch_id][] => exam_date
        $teacherDutyTracker = [];       // [teacher_id] => duty_count
        $dayExamCountTracker = [];      // [date] => exam_count

        // Determine all unique dates sorted to detect late placement for core courses
        $allDates = array_values(array_unique(array_map(fn(SlotDto $s) => $s->date, $validSlotsByKey)));
        sort($allDates);
        $totalDateCount = count($allDates);
        $cutoffIndex = (int) floor($totalDateCount * 0.70);
        $lateDates = array_slice($allDates, $cutoffIndex);
        $lateDatesLookup = array_fill_keys($lateDates, true);

        foreach ($assignments as $assignment) {
            $courseKey = $assignment->getKey();
            $course = $coursesByKey[$courseKey] ?? null;

            if ($course === null) {
                $hardViolations[] = sprintf('Assignment references unknown course key %s.', $courseKey);
                continue;
            }

            $slot = $assignment->slot;
            $slotKey = $slot->getKey();

            // 2. Verify H6: Valid Day & Slot
            if (!isset($validSlotsByKey[$slotKey])) {
                $hardViolations[] = sprintf(
                    'Course %s is assigned to an invalid or blacked-out slot (%s on %s).',
                    $course->code,
                    $slot->startTime,
                    $slot->date
                );
            }

            // 3. Verify H8: Locked Exams Respected
            if ($course->isLocked) {
                if ($course->lockedDate !== null && $course->lockedDate !== $slot->date) {
                    $hardViolations[] = sprintf(
                        'Locked course %s was moved from locked date %s to %s.',
                        $course->code,
                        $course->lockedDate,
                        $slot->date
                    );
                }
                if ($course->lockedSlotIndex !== null && $course->lockedSlotIndex !== $slot->slotIndex) {
                    $hardViolations[] = sprintf(
                        'Locked course %s was moved from locked slot %d to %d.',
                        $course->code,
                        $course->lockedSlotIndex,
                        $slot->slotIndex
                    );
                }
            }

            // 4. Verify H9: Shift Rule
            if ($course->shiftId !== null && $course->shiftId !== $slot->shiftId) {
                if ($isShiftStrict) {
                    $hardViolations[] = sprintf(
                        'Course %s (Batch Shift %d) scheduled in mismatched Shift %d (%s) under strict shift policy.',
                        $course->code,
                        $course->shiftId,
                        $slot->shiftId,
                        $slot->shiftName
                    );
                } else {
                    $softViolations[] = sprintf(
                        'Course %s placed in cross-shift %s (Shift ID %d).',
                        $course->code,
                        $slot->shiftName,
                        $slot->shiftId
                    );
                    $totalPenalty += $wShiftSoft;
                }
            }

            // 5. Verify H1: No Batch Clash
            if (isset($batchSlotTracker[$slotKey][$course->batchId])) {
                $hardViolations[] = sprintf(
                    'Batch clash: Batch ID %d has multiple exams (%s and %s) in the same slot on %s (%s).',
                    $course->batchId,
                    $batchSlotTracker[$slotKey][$course->batchId],
                    $course->code,
                    $slot->date,
                    $slot->startTime
                );
            }
            $batchSlotTracker[$slotKey][$course->batchId] = $course->code;

            // 6. Verify H2: No Student Clash (shared electives, repeaters)
            foreach ($course->studentIds as $studentId) {
                if (isset($studentSlotTracker[$slotKey][$studentId])) {
                    $hardViolations[] = sprintf(
                        'Student clash: Student ID %d is enrolled in both %s and %s scheduled in the same slot on %s (%s).',
                        $studentId,
                        $studentSlotTracker[$slotKey][$studentId],
                        $course->code,
                        $slot->date,
                        $slot->startTime
                    );
                }
                $studentSlotTracker[$slotKey][$studentId] = $course->code;
            }

            // 7. Verify H4: No Room Clash
            foreach ($assignment->roomIds as $roomId) {
                if (isset($roomSlotTracker[$slotKey][$roomId])) {
                    $hardViolations[] = sprintf(
                        'Room clash: Room ID %d is assigned to both %s and %s in slot on %s (%s).',
                        $roomId,
                        $roomSlotTracker[$slotKey][$roomId],
                        $course->code,
                        $slot->date,
                        $slot->startTime
                    );
                }
                $roomSlotTracker[$slotKey][$roomId] = $course->code;
            }

            // 8. Verify H3: No Teacher Clash (invigilators)
            foreach ($assignment->invigilatorIds as $teacherId) {
                if (isset($teacherSlotTracker[$slotKey][$teacherId])) {
                    $hardViolations[] = sprintf(
                        'Teacher clash: Teacher ID %d is invigilating multiple exams in slot on %s (%s).',
                        $teacherId,
                        $slot->date,
                        $slot->startTime
                    );
                }
                $teacherSlotTracker[$slotKey][$teacherId] = $course->code;
                $teacherDutyTracker[$teacherId] = ($teacherDutyTracker[$teacherId] ?? 0) + 1;
            }

            // Collect soft constraint tracking data
            $batchDatesTracker[$course->batchId][] = $slot->date;
            $dayExamCountTracker[$slot->date] = ($dayExamCountTracker[$slot->date] ?? 0) + 1;

            // Soft constraint S3: Core/Hard courses earlier
            if (($course->isCore || $course->isHard) && isset($lateDatesLookup[$slot->date])) {
                $softViolations[] = sprintf(
                    'Core/Hard course %s scheduled in late phase of exam session on %s.',
                    $course->code,
                    $slot->date
                );
                $totalPenalty += $wCoreLate;
            }
        }

        // 9. Soft Constraint S1 & S2: Spacing & Max per day per batch
        foreach ($batchDatesTracker as $batchId => $dates) {
            sort($dates);
            $dateCounts = array_count_values($dates);

            // S2: Same day exams
            foreach ($dateCounts as $date => $count) {
                if ($count > 1) {
                    $softViolations[] = sprintf('Batch ID %d has %d exams on the same day (%s).', $batchId, $count, $date);
                    $totalPenalty += $wSameDay * ($count - 1);
                }
            }

            // S1: Consecutive days (only penalized if allow_consecutive_daily is explicitly false)
            $allowConsecutiveDaily = $softWeights['allow_consecutive_daily'] ?? true;
            if (!$allowConsecutiveDaily) {
                $uniqueDates = array_values(array_unique($dates));
                for ($k = 0; $k < count($uniqueDates) - 1; $k++) {
                    $d1 = strtotime($uniqueDates[$k]);
                    $d2 = strtotime($uniqueDates[$k + 1]);
                    $diffDays = ($d2 - $d1) / 86400;

                    if ($diffDays === 1.0) {
                        $softViolations[] = sprintf(
                            'Batch ID %d has consecutive exam days (%s and %s).',
                            $batchId,
                            $uniqueDates[$k],
                            $uniqueDates[$k + 1]
                        );
                        $totalPenalty += $wConsecutive;
                    }
                }
            }
        }

        $isValid = empty($hardViolations);

        return new ValidationResultDto(
            isValid: $isValid,
            hardViolations: $hardViolations,
            softViolations: $softViolations,
            softScore: $totalPenalty,
            metrics: [
                'total_scheduled' => count($assignments),
                'total_required' => count($coursesByKey),
                'hard_violation_count' => count($hardViolations),
                'soft_violation_count' => count($softViolations),
                'soft_penalty' => $totalPenalty,
            ]
        );
    }
}
