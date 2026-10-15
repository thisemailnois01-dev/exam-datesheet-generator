<?php

declare(strict_types=1);

namespace App\Domain\Timetable\DTOs;

final class SlotDto
{
    public function __construct(
        public readonly string $date,             // 'YYYY-MM-DD'
        public readonly int $shiftId,
        public readonly string $shiftName,       // 'Morning', 'Evening'
        public readonly int $slotIndex,          // 1, 2, 3...
        public readonly string $startTime,       // '08:00'
        public readonly string $endTime,         // '09:00'
        public readonly int $durationMinutes,    // 60
    ) {}

    public function getKey(): string
    {
        return sprintf('%s_%d_%d', $this->date, $this->shiftId, $this->slotIndex);
    }

    public function toArray(): array
    {
        return [
            'date' => $this->date,
            'shift_id' => $this->shiftId,
            'shift_name' => $this->shiftName,
            'slot_index' => $this->slotIndex,
            'start_time' => $this->startTime,
            'end_time' => $this->endTime,
            'duration_minutes' => $this->durationMinutes,
        ];
    }

    public static function fromArray(array $data): self
    {
        return new self(
            date: $data['date'],
            shiftId: (int) $data['shift_id'],
            shiftName: $data['shift_name'],
            slotIndex: (int) $data['slot_index'],
            startTime: $data['start_time'],
            endTime: $data['end_time'],
            durationMinutes: (int) ($data['duration_minutes'] ?? 60),
        );
    }
}
