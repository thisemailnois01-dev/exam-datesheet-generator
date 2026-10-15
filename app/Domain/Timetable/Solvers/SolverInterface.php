<?php

declare(strict_types=1);

namespace App\Domain\Timetable\Solvers;

use App\Domain\Timetable\DTOs\CourseNodeDto;
use App\Domain\Timetable\DTOs\SlotDto;
use App\Domain\Timetable\DTOs\SolverResultDto;

interface SolverInterface
{
    /**
     * Solve the timetable scheduling problem.
     *
     * @param array<CourseNodeDto> $courses
     * @param array<SlotDto> $slots
     * @param array<array{id: int, name: string, capacity: int, type: string, is_active: bool}> $rooms
     * @param array<array{id: int, name: string, max_duties_per_day: int, max_duties_per_session: int}> $teachers
     * @param array<array{teacher_id: int, date: string, slot_index: ?int, is_available: bool}> $teacherUnavailabilities
     * @param array<string, int> $weights
     * @param bool $isShiftStrict
     * @param int|null $randomSeed
     * @param callable|null $progressCallback function(int $percentage, string $statusMessage): void
     * @return SolverResultDto
     */
    public function solve(
        array $courses,
        array $slots,
        array $rooms,
        array $teachers,
        array $teacherUnavailabilities = [],
        array $weights = [],
        bool $isShiftStrict = true,
        ?int $randomSeed = null,
        ?callable $progressCallback = null,
        bool $dailyConsecutiveMode = true,
    ): SolverResultDto;
}
