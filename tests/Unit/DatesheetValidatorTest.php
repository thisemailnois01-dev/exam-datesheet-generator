<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Domain\Timetable\DTOs\CourseNodeDto;
use App\Domain\Timetable\DTOs\ScheduleAssignmentDto;
use App\Domain\Timetable\DTOs\SlotDto;
use App\Domain\Timetable\Services\DatesheetValidator;
use PHPUnit\Framework\TestCase;

class DatesheetValidatorTest extends TestCase
{
    private DatesheetValidator $validator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->validator = new DatesheetValidator();
    }

    public function test_validates_clean_schedule_with_zero_violations(): void
    {
        $slot1 = new SlotDto('2026-10-12', 1, 'Morning', 1, '08:00', '09:00', 60);
        $slot2 = new SlotDto('2026-10-14', 1, 'Morning', 1, '08:00', '09:00', 60);

        $course1 = new CourseNodeDto(1, 10, 1, 'CS-101', 'Intro', 30, 60, false, false);
        $course2 = new CourseNodeDto(2, 10, 1, 'CS-102', 'Math', 30, 60, false, false);

        $assignment1 = new ScheduleAssignmentDto(1, 10, $slot1, [1], [1]);
        $assignment2 = new ScheduleAssignmentDto(2, 10, $slot2, [1], [1]);

        $validSlots = [$slot1->getKey() => $slot1, $slot2->getKey() => $slot2];
        $courses = [$course1->getKey() => $course1, $course2->getKey() => $course2];

        $result = $this->validator->validate(
            assignments: [$assignment1, $assignment2],
            coursesByKey: $courses,
            validSlotsByKey: $validSlots,
        );

        $this->assertTrue($result->isValid);
        $this->assertEmpty($result->hardViolations);
    }

    public function test_detects_deliberate_batch_clash(): void
    {
        // Same slot for two courses of the same batch!
        $slot = new SlotDto('2026-10-12', 1, 'Morning', 1, '08:00', '09:00', 60);

        $course1 = new CourseNodeDto(1, 10, 1, 'CS-101', 'Intro', 30, 60, false, false);
        $course2 = new CourseNodeDto(2, 10, 1, 'CS-102', 'Math', 30, 60, false, false);

        $assignment1 = new ScheduleAssignmentDto(1, 10, $slot, [1]);
        $assignment2 = new ScheduleAssignmentDto(2, 10, $slot, [2]);

        $validSlots = [$slot->getKey() => $slot];
        $courses = [$course1->getKey() => $course1, $course2->getKey() => $course2];

        $result = $this->validator->validate(
            assignments: [$assignment1, $assignment2],
            coursesByKey: $courses,
            validSlotsByKey: $validSlots,
        );

        $this->assertFalse($result->isValid);
        $this->assertCount(1, $result->hardViolations);
        $this->assertStringContainsString('Batch clash', $result->hardViolations[0]);
    }

    public function test_detects_deliberate_student_clash(): void
    {
        // Two courses from different batches with shared repeater student placed in same slot
        $slot = new SlotDto('2026-10-12', 1, 'Morning', 1, '08:00', '09:00', 60);

        $course1 = new CourseNodeDto(1, 10, 1, 'CS-101', 'Intro', 30, 60, false, false, studentIds: [99]);
        $course2 = new CourseNodeDto(2, 20, 1, 'CS-202', 'Advanced', 30, 60, false, false, studentIds: [99]);

        $assignment1 = new ScheduleAssignmentDto(1, 10, $slot, [1]);
        $assignment2 = new ScheduleAssignmentDto(2, 20, $slot, [2]);

        $validSlots = [$slot->getKey() => $slot];
        $courses = [$course1->getKey() => $course1, $course2->getKey() => $course2];

        $result = $this->validator->validate(
            assignments: [$assignment1, $assignment2],
            coursesByKey: $courses,
            validSlotsByKey: $validSlots,
        );

        $this->assertFalse($result->isValid);
        $this->assertStringContainsString('Student clash', $result->hardViolations[0]);
    }

    public function test_detects_room_clash(): void
    {
        // Same room assigned twice in same slot
        $slot = new SlotDto('2026-10-12', 1, 'Morning', 1, '08:00', '09:00', 60);

        $course1 = new CourseNodeDto(1, 10, 1, 'CS-101', 'Intro', 30, 60, false, false);
        $course2 = new CourseNodeDto(2, 20, 1, 'CS-202', 'Advanced', 30, 60, false, false);

        $assignment1 = new ScheduleAssignmentDto(1, 10, $slot, [1]); // Room 1
        $assignment2 = new ScheduleAssignmentDto(2, 20, $slot, [1]); // Room 1 again!

        $validSlots = [$slot->getKey() => $slot];
        $courses = [$course1->getKey() => $course1, $course2->getKey() => $course2];

        $result = $this->validator->validate(
            assignments: [$assignment1, $assignment2],
            coursesByKey: $courses,
            validSlotsByKey: $validSlots,
        );

        $this->assertFalse($result->isValid);
        $this->assertStringContainsString('Room clash', $result->hardViolations[0]);
    }

    public function test_detects_shift_mismatch_under_strict_policy(): void
    {
        // Morning course scheduled in Evening slot
        $eveningSlot = new SlotDto('2026-10-12', 2, 'Evening', 1, '15:00', '16:00', 60);

        $morningCourse = new CourseNodeDto(1, 10, 1, 'CS-101', 'Intro', 30, 60, false, false); // shiftId = 1 (Morning)

        $assignment = new ScheduleAssignmentDto(1, 10, $eveningSlot, [1]);

        $validSlots = [$eveningSlot->getKey() => $eveningSlot];
        $courses = [$morningCourse->getKey() => $morningCourse];

        $result = $this->validator->validate(
            assignments: [$assignment],
            coursesByKey: $courses,
            validSlotsByKey: $validSlots,
            isShiftStrict: true,
        );

        $this->assertFalse($result->isValid);
        $this->assertStringContainsString('mismatched Shift', $result->hardViolations[0]);
    }

    public function test_detects_paper_leak_violation_when_same_shift_parallel_sections_have_different_slots(): void
    {
        // Two morning classes taking the same subject "Programming Fundamentals" in different slots
        $slot1 = new SlotDto('2026-10-12', 1, 'Morning', 1, '08:00', '09:00', 60);
        $slot2 = new SlotDto('2026-10-12', 1, 'Morning', 2, '09:30', '10:30', 60);

        $course1 = new CourseNodeDto(1, 10, 1, 'CS-101', 'Programming Fundamentals', 30, 60, false, false);
        $course2 = new CourseNodeDto(2, 20, 1, 'IT-101', 'Programming Fundamentals', 30, 60, false, false);

        $assignment1 = new ScheduleAssignmentDto(1, 10, $slot1, [1]);
        $assignment2 = new ScheduleAssignmentDto(2, 20, $slot2, [2]);

        $validSlots = [$slot1->getKey() => $slot1, $slot2->getKey() => $slot2];
        $courses = [$course1->getKey() => $course1, $course2->getKey() => $course2];

        $result = $this->validator->validate(
            assignments: [$assignment1, $assignment2],
            coursesByKey: $courses,
            validSlotsByKey: $validSlots,
        );

        $this->assertFalse($result->isValid);
        $this->assertStringContainsString('Paper Leak Violation (H10)', $result->hardViolations[0]);
        $this->assertStringContainsString('Programming Fundamentals', $result->hardViolations[0]);
    }

    public function test_passes_when_same_shift_parallel_sections_share_exact_same_slot(): void
    {
        // Two morning classes taking the same subject "Programming Fundamentals" in the EXACT same slot
        $slot1 = new SlotDto('2026-10-12', 1, 'Morning', 1, '08:00', '09:00', 60);

        $course1 = new CourseNodeDto(1, 10, 1, 'CS-101', 'Programming Fundamentals', 30, 60, false, false);
        $course2 = new CourseNodeDto(2, 20, 1, 'IT-101', 'Programming Fundamentals', 30, 60, false, false);

        $assignment1 = new ScheduleAssignmentDto(1, 10, $slot1, [1]);
        $assignment2 = new ScheduleAssignmentDto(2, 20, $slot1, [2]);

        $validSlots = [$slot1->getKey() => $slot1];
        $courses = [$course1->getKey() => $course1, $course2->getKey() => $course2];

        $result = $this->validator->validate(
            assignments: [$assignment1, $assignment2],
            coursesByKey: $courses,
            validSlotsByKey: $validSlots,
        );

        $this->assertTrue($result->isValid);
        $this->assertEmpty($result->hardViolations);
    }

    public function test_allows_morning_and_evening_to_have_independent_slots_for_different_papers(): void
    {
        // Morning class takes Morning Paper in Slot 1, Evening class takes Evening Paper in Slot 2
        $morningSlot = new SlotDto('2026-10-12', 1, 'Morning', 1, '08:00', '09:00', 60);
        $eveningSlot = new SlotDto('2026-10-12', 2, 'Evening', 1, '14:00', '15:00', 60);

        $courseMorning = new CourseNodeDto(1, 10, 1, 'CS-101M', 'Programming Fundamentals', 30, 60, false, false);
        $courseEvening = new CourseNodeDto(2, 20, 2, 'CS-101E', 'Programming Fundamentals', 30, 60, false, false);

        $assignment1 = new ScheduleAssignmentDto(1, 10, $morningSlot, [1]);
        $assignment2 = new ScheduleAssignmentDto(2, 20, $eveningSlot, [1]);

        $validSlots = [$morningSlot->getKey() => $morningSlot, $eveningSlot->getKey() => $eveningSlot];
        $courses = [$courseMorning->getKey() => $courseMorning, $courseEvening->getKey() => $courseEvening];

        $result = $this->validator->validate(
            assignments: [$assignment1, $assignment2],
            coursesByKey: $courses,
            validSlotsByKey: $validSlots,
        );

        $this->assertTrue($result->isValid);
        $this->assertEmpty($result->hardViolations);
    }
}
