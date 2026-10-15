<?php

declare(strict_types=1);

namespace App\Domain\Timetable\DTOs;

final class ValidationResultDto
{
    /**
     * @param array<string> $hardViolations
     * @param array<string> $softViolations
     */
    public function __construct(
        public readonly bool $isValid,
        public readonly array $hardViolations = [],
        public readonly array $softViolations = [],
        public readonly int $softScore = 0,
        public readonly array $metrics = [],
    ) {}

    public function hasHardViolations(): bool
    {
        return !empty($this->hardViolations);
    }
}
