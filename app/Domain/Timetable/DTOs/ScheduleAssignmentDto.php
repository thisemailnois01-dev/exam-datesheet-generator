<?php

declare(strict_types=1);

namespace App\Domain\Timetable\DTOs;

final class ScheduleAssignmentDto
{
    /**
     * @param array<int> $roomIds
     * @param array<int> $invigilatorIds
     */
    public function __construct(
        public readonly int $courseId,
        public readonly int $batchId,
        public readonly SlotDto $slot,
        public array $roomIds = [],
        public array $invigilatorIds = [],
        public bool $isLocked = false,
    ) {}

    public function getKey(): string
    {
        return sprintf('%d_%d', $this->courseId, $this->batchId);
    }
}
