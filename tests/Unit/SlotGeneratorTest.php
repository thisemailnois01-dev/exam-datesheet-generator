<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Domain\Timetable\Services\SlotGenerator;
use PHPUnit\Framework\TestCase;

class SlotGeneratorTest extends TestCase
{
    private SlotGenerator $generator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->generator = new SlotGenerator();
    }

    public function test_generates_correct_slots_for_standard_shift_window(): void
    {
        // Window 08:00 to 13:00 (300 mins)
        // D = 60 mins, B = 30 mins
        // Slot 1: 08:00 - 09:00
        // Break: 09:00 - 09:30
        // Slot 2: 09:30 - 10:30
        // Break: 10:30 - 11:00
        // Slot 3: 11:00 - 12:00
        // Break: 12:00 - 12:30
        // Slot 4: 12:30 - 13:30 (Exceeds 13:00, so only 3 slots generated)
        $shifts = [
            ['id' => 1, 'name' => 'Morning', 'start_time' => '08:00', 'end_time' => '13:00']
        ];

        $slots = $this->generator->generateSlots(
            startDate: '2026-10-12', // Monday
            endDate: '2026-10-12',
            workingDays: ['Monday'],
            shifts: $shifts,
            durationMinutes: 60,
            breakMinutes: 30,
        );

        $this->assertCount(3, $slots);
        $this->assertSame('08:00', $slots[0]->startTime);
        $this->assertSame('09:00', $slots[0]->endTime);
        $this->assertSame('09:30', $slots[1]->startTime);
        $this->assertSame('10:30', $slots[1]->endTime);
        $this->assertSame('11:00', $slots[2]->startTime);
        $this->assertSame('12:00', $slots[2]->endTime);
    }

    public function test_supports_non_uniform_durations_and_breaks(): void
    {
        // D = 90 mins, B = 15 mins, Window 09:00 - 13:00
        // Slot 1: 09:00 - 10:30
        // Break: 10:30 - 10:45
        // Slot 2: 10:45 - 12:15
        // Break: 12:15 - 12:30
        // Next would end at 14:00 > 13:00 => exactly 2 slots
        $shifts = [
            ['id' => 1, 'name' => 'Morning', 'start_time' => '09:00', 'end_time' => '13:00']
        ];

        $slots = $this->generator->generateSlots(
            startDate: '2026-10-12',
            endDate: '2026-10-12',
            workingDays: ['Monday'],
            shifts: $shifts,
            durationMinutes: 90,
            breakMinutes: 15,
        );

        $this->assertCount(2, $slots);
        $this->assertSame('09:00', $slots[0]->startTime);
        $this->assertSame('10:30', $slots[0]->endTime);
        $this->assertSame('10:45', $slots[1]->startTime);
        $this->assertSame('12:15', $slots[1]->endTime);
    }

    public function test_prunes_slots_overlapping_friday_blackout_window(): void
    {
        $shifts = [
            ['id' => 1, 'name' => 'Morning', 'start_time' => '08:00', 'end_time' => '14:00']
        ];

        // 08:00-09:00, 09:30-10:30, 11:00-12:00, 12:30-13:30 (overlaps with 12:30-14:00 prayer)
        $holidays = [
            [
                'date' => '2026-10-16', // Friday
                'title' => 'Friday Prayer',
                'start_time' => '12:30',
                'end_time' => '14:00',
            ]
        ];

        $slots = $this->generator->generateSlots(
            startDate: '2026-10-16',
            endDate: '2026-10-16',
            workingDays: ['Friday'],
            shifts: $shifts,
            durationMinutes: 60,
            breakMinutes: 30,
            holidays: $holidays,
        );

        // 4th slot 12:30-13:30 should be pruned
        $this->assertCount(3, $slots);
        foreach ($slots as $slot) {
            $this->assertLessThanOrEqual('12:00', $slot->endTime);
        }
    }

    public function test_excludes_non_working_days_and_full_day_holidays(): void
    {
        $shifts = [
            ['id' => 1, 'name' => 'Morning', 'start_time' => '08:00', 'end_time' => '11:00']
        ];

        $holidays = [
            ['date' => '2026-10-13', 'title' => 'National Holiday', 'start_time' => null, 'end_time' => null]
        ];

        // Period: Mon 12 to Wed 14 Oct. Tuesday is holiday. Sunday is non-working.
        $slots = $this->generator->generateSlots(
            startDate: '2026-10-12', // Mon
            endDate: '2026-10-14',   // Wed
            workingDays: ['Monday', 'Wednesday'], // Tuesday omitted from working days
            shifts: $shifts,
            durationMinutes: 60,
            breakMinutes: 30,
            holidays: $holidays,
        );

        $dates = array_unique(array_map(fn($s) => $s->date, $slots));
        $this->assertContains('2026-10-12', $dates);
        $this->assertContains('2026-10-14', $dates);
        $this->assertNotContains('2026-10-13', $dates);
    }
}
