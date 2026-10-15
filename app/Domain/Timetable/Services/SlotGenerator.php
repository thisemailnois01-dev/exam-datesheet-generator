<?php

declare(strict_types=1);

namespace App\Domain\Timetable\Services;

use App\Domain\Timetable\DTOs\SlotDto;
use Carbon\Carbon;
use Carbon\CarbonPeriod;

final class SlotGenerator
{
    /**
     * Generate all valid (date, shift, slot) intervals.
     *
     * @param string $startDate 'YYYY-MM-DD'
     * @param string $endDate   'YYYY-MM-DD'
     * @param array<string> $workingDays ['Monday', 'Tuesday', ...]
     * @param array<array{id: int, name: string, start_time: string, end_time: string}> $shifts
     * @param int $durationMinutes default duration D
     * @param int $breakMinutes mandatory break B
     * @param array<array{date: string, start_time: ?string, end_time: ?string, title: string}> $holidays
     * @return array<SlotDto>
     */
    public function generateSlots(
        string $startDate,
        string $endDate,
        array $workingDays,
        array $shifts,
        int $durationMinutes = 60,
        int $breakMinutes = 30,
        array $holidays = [],
    ): array {
        $slots = [];

        // Normalize working days to title case (e.g., 'Monday')
        $normalizedWorkingDays = array_map(
            fn(string $day): string => ucfirst(strtolower(trim($day))),
            $workingDays
        );

        // Index holidays: full day vs partial blackout windows
        $fullDayHolidays = [];
        $partialBlackouts = []; // date => array of [start, end] in minutes from midnight

        foreach ($holidays as $holiday) {
            $date = $holiday['date'];
            if (empty($holiday['start_time']) || empty($holiday['end_time'])) {
                $fullDayHolidays[$date] = true;
            } else {
                $partialBlackouts[$date][] = [
                    'start' => $this->timeToMinutes($holiday['start_time']),
                    'end' => $this->timeToMinutes($holiday['end_time']),
                ];
            }
        }

        $period = CarbonPeriod::create($startDate, $endDate);

        foreach ($period as $currentDate) {
            $dateString = $currentDate->format('Y-m-d');
            $dayOfWeek = $currentDate->format('l');

            // Skip non-working weekdays
            if (!in_array($dayOfWeek, $normalizedWorkingDays, true)) {
                continue;
            }

            // Skip full-day holidays
            if (isset($fullDayHolidays[$dateString])) {
                continue;
            }

            // Generate slots for each shift
            foreach ($shifts as $shift) {
                $shiftId = (int) $shift['id'];
                $shiftName = $shift['name'];
                $windowStart = $this->timeToMinutes($shift['start_time']);
                $windowEnd = $this->timeToMinutes($shift['end_time']);

                $currentStart = $windowStart;
                $slotIndex = 1;

                while (true) {
                    $currentEnd = $currentStart + $durationMinutes;

                    // Condition: slot is valid only if its end <= window_end
                    if ($currentEnd > $windowEnd) {
                        break;
                    }

                    // Check if slot overlaps with any partial blackout window on this date
                    $overlapsBlackout = false;
                    if (isset($partialBlackouts[$dateString])) {
                        foreach ($partialBlackouts[$dateString] as $blackout) {
                            if ($this->hasOverlap($currentStart, $currentEnd, $blackout['start'], $blackout['end'])) {
                                $overlapsBlackout = true;
                                break;
                            }
                        }
                    }

                    if (!$overlapsBlackout) {
                        $slots[] = new SlotDto(
                            date: $dateString,
                            shiftId: $shiftId,
                            shiftName: $shiftName,
                            slotIndex: $slotIndex,
                            startTime: $this->minutesToTime($currentStart),
                            endTime: $this->minutesToTime($currentEnd),
                            durationMinutes: $durationMinutes,
                        );
                    }

                    // Next slot starts at currentEnd + mandatory break B
                    $currentStart = $currentEnd + $breakMinutes;
                    $slotIndex++;
                }
            }
        }

        return $slots;
    }

    /**
     * Convert 'HH:MM' string to total minutes from midnight.
     */
    public function timeToMinutes(string $time): int
    {
        $parts = explode(':', trim($time));
        $hours = (int) ($parts[0] ?? 0);
        $minutes = (int) ($parts[1] ?? 0);
        return ($hours * 60) + $minutes;
    }

    /**
     * Convert total minutes from midnight to 'HH:MM' string.
     */
    public function minutesToTime(int $minutes): string
    {
        $hours = intdiv($minutes, 60);
        $mins = $minutes % 60;
        return sprintf('%02d:%02d', $hours, $mins);
    }

    /**
     * Check if two time intervals [start1, end1] and [start2, end2] overlap.
     */
    private function hasOverlap(int $start1, int $end1, int $start2, int $end2): bool
    {
        return max($start1, $start2) < min($end1, $end2);
    }
}
