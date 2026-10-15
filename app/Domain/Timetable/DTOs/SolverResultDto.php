<?php

declare(strict_types=1);

namespace App\Domain\Timetable\DTOs;

final class SolverResultDto
{
    /**
     * @param array<ScheduleAssignmentDto> $assignments
     * @param array<string> $hardViolations
     * @param array<string> $softViolations
     * @param array<string> $relaxationSuggestions
     */
    public function __construct(
        public readonly bool $isSuccess,
        public readonly array $assignments = [],
        public readonly array $hardViolations = [],
        public readonly array $softViolations = [],
        public readonly int $softScore = 0,
        public readonly ?string $bottleneckExplanation = null,
        public readonly array $relaxationSuggestions = [],
        public readonly float $executionTimeSeconds = 0.0,
        public readonly array $metrics = [],
    ) {}
}
