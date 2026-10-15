<?php

declare(strict_types=1);

namespace App\Domain\Timetable\DTOs;

final class CourseNodeDto
{
    /**
     * @param array<int> $studentIds
     * @param array<int> $teacherIds
     */
    public function __construct(
        public readonly int $courseId,
        public readonly int $batchId,
        public readonly ?int $shiftId,
        public readonly string $code,
        public readonly string $name,
        public readonly int $studentCount,
        public readonly ?int $durationMinutes,
        public readonly bool $isCore,
        public readonly bool $isHard,
        public readonly bool $isLocked = false,
        public readonly ?string $lockedDate = null,
        public readonly ?int $lockedSlotIndex = null,
        public readonly ?int $lockedShiftId = null,
        public readonly array $studentIds = [],
        public readonly array $teacherIds = [],
    ) {}

    public function getKey(): string
    {
        return sprintf('%d_%d', $this->courseId, $this->batchId);
    }
}
