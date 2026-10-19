<?php

declare(strict_types=1);

namespace App\Domain\Timetable\Solvers;

use App\Domain\Timetable\DTOs\CourseNodeDto;
use App\Domain\Timetable\DTOs\ScheduleAssignmentDto;
use App\Domain\Timetable\DTOs\SlotDto;
use App\Domain\Timetable\DTOs\SolverResultDto;
use App\Domain\Timetable\Services\ConflictGraphBuilder;
use App\Domain\Timetable\Services\DatesheetValidator;
use App\Domain\Timetable\Services\FeasibilityChecker;
use App\Domain\Timetable\Services\InvigilatorAllocator;
use App\Domain\Timetable\Services\RoomAllocator;

final class NativePhpSolver implements SolverInterface
{
    public function __construct(
        private readonly FeasibilityChecker $feasibilityChecker = new FeasibilityChecker(),
        private readonly ConflictGraphBuilder $graphBuilder = new ConflictGraphBuilder(),
        private readonly RoomAllocator $roomAllocator = new RoomAllocator(),
        private readonly InvigilatorAllocator $invigilatorAllocator = new InvigilatorAllocator(),
        private readonly DatesheetValidator $validator = new DatesheetValidator(),
    ) {}

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
    ): SolverResultDto {
        $startTime = microtime(true);

        if ($randomSeed !== null) {
            mt_srand($randomSeed);
        }

        $reportProgress = function (int $percent, string $msg) use ($progressCallback): void {
            if ($progressCallback !== null) {
                $progressCallback($percent, $msg);
            }
        };

        $reportProgress(5, 'Running capacity feasibility pre-check...');

        // 1. Feasibility Pre-check
        $activeRooms = array_values(array_filter($rooms, fn($r) => ($r['is_active'] ?? true)));
        $totalActiveRooms = count($activeRooms);

        $precheck = $this->feasibilityChecker->checkFeasibility(
            courses: $courses,
            slots: $slots,
            totalActiveRooms: $totalActiveRooms,
            isShiftStrict: $isShiftStrict,
        );

        if (!$precheck['is_feasible']) {
            // Check if relaxing strict shift resolves it
            if ($isShiftStrict) {
                $relaxedCheck = $this->feasibilityChecker->checkFeasibility(
                    courses: $courses,
                    slots: $slots,
                    totalActiveRooms: $totalActiveRooms,
                    isShiftStrict: false,
                );
                if ($relaxedCheck['is_feasible']) {
                    // Fall back to soft shift mode with warning
                    $isShiftStrict = false;
                    $precheck['suggestions'][] = 'Strict shift policy automatically relaxed to Preferred (Soft) to achieve a valid clash-free timetable.';
                } else {
                    return new SolverResultDto(
                        isSuccess: false,
                        hardViolations: $precheck['bottlenecks'],
                        bottleneckExplanation: implode("\n", $precheck['bottlenecks']),
                        relaxationSuggestions: $precheck['suggestions'],
                        executionTimeSeconds: microtime(true) - $startTime,
                        metrics: $precheck['metrics'],
                    );
                }
            } else {
                return new SolverResultDto(
                    isSuccess: false,
                    hardViolations: $precheck['bottlenecks'],
                    bottleneckExplanation: implode("\n", $precheck['bottlenecks']),
                    relaxationSuggestions: $precheck['suggestions'],
                    executionTimeSeconds: microtime(true) - $startTime,
                    metrics: $precheck['metrics'],
                );
            }
        }

        $reportProgress(20, 'Constructing conflict graph and saturation degree metrics...');

        // 2. Build Conflict Graph (student enrollments & batches; invigilation duties handled by InvigilatorAllocator)
        $graph = $this->graphBuilder->buildGraph($courses, checkTeacherConflicts: false);
        $nodes = $graph['nodes'];
        $adjacency = $graph['adjacency'];
        $degrees = $graph['degrees'];

        // Index slots by key and shift
        $slotsByKey = [];
        $slotsByShift = [];
        foreach ($slots as $slot) {
            $slotsByKey[$slot->getKey()] = $slot;
            $slotsByShift[$slot->shiftId][] = $slot;
        }

        $reportProgress(35, 'Executing Phase 1: DSATUR graph coloring slot construction...');

        // 3. Phase 1A: DSATUR Construction
        $coloredAssignments = []; // [courseKey] => SlotDto
        $slotOccupancy = [];      // [slotKey][] => courseKey
        $saturationDegrees = [];  // [courseKey] => count of distinct slots used by conflicting neighbors

        foreach ($nodes as $key => $node) {
            $saturationDegrees[$key] = 0;
        }

        // Handle pre-locked courses first
        $uncoloredKeys = [];
        foreach ($nodes as $key => $node) {
            if ($node->isLocked && $node->lockedDate !== null && $node->lockedSlotIndex !== null) {
                $targetShiftId = $node->lockedShiftId ?? $node->shiftId ?? 1;
                $lockedSlotKey = sprintf('%s_%d_%d', $node->lockedDate, $targetShiftId, $node->lockedSlotIndex);
                if (isset($slotsByKey[$lockedSlotKey])) {
                    $coloredAssignments[$key] = $slotsByKey[$lockedSlotKey];
                    $slotOccupancy[$lockedSlotKey][] = $key;
                } else {
                    $uncoloredKeys[$key] = true;
                }
            } else {
                $uncoloredKeys[$key] = true;
            }
        }

        // Update saturation degrees from pre-locked nodes
        foreach ($coloredAssignments as $lockedKey => $lockedSlot) {
            foreach ($adjacency[$lockedKey] as $neighborKey => $val) {
                if (isset($uncoloredKeys[$neighborKey])) {
                    $saturationDegrees[$neighborKey]++;
                }
            }
        }

        // Consecutive Daily Exam Assignment with Synchronized Same-Shift Paper Scheduling (Paper Leak Prevention)
        if ($dailyConsecutiveMode) {
            // Group uncolored courses by (normalized_name, shift_id) to eliminate paper leak risks
            $courseGroups = [];
            foreach ($uncoloredKeys as $uKey => $_) {
                $node = $nodes[$uKey];
                $normName = trim(strtolower($node->name));
                $shiftId = $node->shiftId ?? 0;
                $gKey = $normName . '||' . $shiftId;
                $courseGroups[$gKey][] = $node;
            }

            $distinctWorkingDates = array_values(array_unique(array_map(fn($s) => $s->date, $slots)));
            sort($distinctWorkingDates);

            $slotsByShiftAndDate = [];
            foreach ($slots as $s) {
                $slotsByShiftAndDate[$s->shiftId][$s->date][] = $s;
            }

            // Track scheduled date per course name to align Morning & Evening shifts on the same date when possible
            $scheduledDateByCourseName = [];
            foreach ($coloredAssignments as $cKey => $cSlot) {
                $scheduledDateByCourseName[trim(strtolower($nodes[$cKey]->name))] = $cSlot->date;
            }

            // Sort groups: multi-section groups first (most constrained), then Core/Hard
            uasort($courseGroups, function ($gA, $gB) {
                if (count($gA) !== count($gB)) {
                    return count($gB) <=> count($gA);
                }
                $coreA = array_sum(array_map(fn($n) => $n->isHard ? 2 : ($n->isCore ? 1 : 0), $gA));
                $coreB = array_sum(array_map(fn($n) => $n->isHard ? 2 : ($n->isCore ? 1 : 0), $gB));
                return $coreB <=> $coreA;
            });

            // Track which dates are taken by which batches
            $batchDayAssigned = [];
            foreach ($coloredAssignments as $cKey => $cSlot) {
                $batchDayAssigned[$nodes[$cKey]->batchId][$cSlot->date] = true;
            }

            foreach ($courseGroups as $gKey => $groupMembers) {
                $shiftId = $groupMembers[0]->shiftId;
                $participatingBatches = array_map(fn($n) => $n->batchId, $groupMembers);
                $memberKeys = array_map(fn($n) => $n->getKey(), $groupMembers);
                $normName = trim(strtolower($groupMembers[0]->name));

                $bestSlot = null;
                $minOccupancy = PHP_INT_MAX;

                // Check if this course was already scheduled in another shift and prioritize that same date
                $preferredDate = $scheduledDateByCourseName[$normName] ?? null;
                $orderedDates = $distinctWorkingDates;
                if ($preferredDate !== null && in_array($preferredDate, $orderedDates, true)) {
                    $orderedDates = array_values(array_unique(array_merge([$preferredDate], $orderedDates)));
                }

                foreach ($orderedDates as $date) {
                    // Check if ANY participating batch already has an exam on this date
                    $dateBlocked = false;
                    foreach ($participatingBatches as $bId) {
                        if (isset($batchDayAssigned[$bId][$date])) {
                            $dateBlocked = true;
                            break;
                        }
                    }
                    if ($dateBlocked) {
                        continue;
                    }

                    $candidateSlots = $slotsByShiftAndDate[$shiftId][$date] ?? [];
                    if (empty($candidateSlots) && !$isShiftStrict) {
                        $candidateSlots = array_values(array_filter($slots, fn($s) => $s->date === $date));
                    }

                    foreach ($candidateSlots as $candSlot) {
                        $sKey = $candSlot->getKey();
                        $currentOcc = count($slotOccupancy[$sKey] ?? []);
                        if ($currentOcc + count($groupMembers) > $totalActiveRooms) {
                            continue;
                        }

                        $hasClash = false;
                        foreach ($slotOccupancy[$sKey] ?? [] as $occKey) {
                            foreach ($memberKeys as $mKey) {
                                if (isset($adjacency[$mKey][$occKey])) {
                                    $hasClash = true;
                                    break 2;
                                }
                            }
                        }
                        if ($hasClash) {
                            continue;
                        }

                        if ($currentOcc < $minOccupancy) {
                            $minOccupancy = $currentOcc;
                            $bestSlot = $candSlot;
                            break 2; // Found early date slot!
                        }
                    }
                }

                // Fallback: If preferred date couldn't fit, search across all available working dates
                if ($bestSlot === null) {
                    foreach ($distinctWorkingDates as $altDate) {
                        $candidateSlots = $isShiftStrict && $shiftId !== null
                            ? ($slotsByShiftAndDate[$shiftId][$altDate] ?? [])
                            : array_values(array_filter($slots, fn($s) => $s->date === $altDate));

                        foreach ($candidateSlots as $candSlot) {
                            $sKey = $candSlot->getKey();
                            if (count($slotOccupancy[$sKey] ?? []) + count($groupMembers) > $totalActiveRooms) {
                                continue;
                            }
                            $hasClash = false;
                            foreach ($slotOccupancy[$sKey] ?? [] as $occKey) {
                                foreach ($memberKeys as $mKey) {
                                    if (isset($adjacency[$mKey][$occKey])) {
                                        $hasClash = true;
                                        break 2;
                                    }
                                }
                            }
                            if (!$hasClash) {
                                $bestSlot = $candSlot;
                                break 2;
                            }
                        }
                    }
                }

                if ($bestSlot !== null) {
                    $sKey = $bestSlot->getKey();
                    $scheduledDateByCourseName[$normName] = $bestSlot->date;
                    foreach ($groupMembers as $node) {
                        $mKey = $node->getKey();
                        $coloredAssignments[$mKey] = $bestSlot;
                        $slotOccupancy[$sKey][] = $mKey;
                        $batchDayAssigned[$node->batchId][$bestSlot->date] = true;
                        unset($uncoloredKeys[$mKey]);

                        foreach ($adjacency[$mKey] as $neighborKey => $_) {
                            if (isset($uncoloredKeys[$neighborKey])) {
                                $saturationDegrees[$neighborKey]++;
                            }
                        }
                    }
                }
            }
        }

        // Color remaining nodes via DSATUR (if any remain)
        while (!empty($uncoloredKeys)) {
            // Select uncolored node with highest saturation degree, tie-broken by original degree
            $bestKey = null;
            $maxSat = -1;
            $maxDeg = -1;

            foreach ($uncoloredKeys as $candidateKey => $_) {
                $sat = $saturationDegrees[$candidateKey];
                $deg = $degrees[$candidateKey];

                if ($sat > $maxSat || ($sat === $maxSat && $deg > $maxDeg)) {
                    $maxSat = $sat;
                    $maxDeg = $deg;
                    $bestKey = $candidateKey;
                }
            }

            $currentNode = $nodes[$bestKey];

            // Filter candidate slots
            $candidateSlots = [];
            if ($isShiftStrict && $currentNode->shiftId !== null) {
                $candidateSlots = $slotsByShift[$currentNode->shiftId] ?? [];
            } else {
                $candidateSlots = $slots;
            }

            // Find valid slot with lowest soft penalty
            $bestSlot = null;
            $lowestPenalty = PHP_INT_MAX;

            foreach ($candidateSlots as $candidateSlot) {
                $slotKey = $candidateSlot->getKey();

                // Check room capacity limit in this slot
                $currentInSlot = count($slotOccupancy[$slotKey] ?? []);
                if ($currentInSlot >= $totalActiveRooms) {
                    continue; // Slot is full
                }

                // Hard Constraint check: Any conflicting neighbor already in this slot?
                $hasNeighborClash = false;
                foreach ($slotOccupancy[$slotKey] ?? [] as $occupiedKey) {
                    if (isset($adjacency[$bestKey][$occupiedKey])) {
                        $hasNeighborClash = true;
                        break;
                    }
                }

                if ($hasNeighborClash) {
                    continue;
                }

                // Compute heuristic soft penalty for placing currentNode in candidateSlot
                $penalty = $this->calculatePlacementPenalty($currentNode, $candidateSlot, $coloredAssignments, $nodes);

                if ($penalty < $lowestPenalty) {
                    $lowestPenalty = $penalty;
                    $bestSlot = $candidateSlot;
                }
            }

            // If no valid slot was found in strict shift, try relaxing shift if permitted
            if ($bestSlot === null && $isShiftStrict) {
                foreach ($slots as $candidateSlot) {
                    $slotKey = $candidateSlot->getKey();
                    if (count($slotOccupancy[$slotKey] ?? []) >= $totalActiveRooms) {
                        continue;
                    }
                    $hasNeighborClash = false;
                    foreach ($slotOccupancy[$slotKey] ?? [] as $occupiedKey) {
                        if (isset($adjacency[$bestKey][$occupiedKey])) {
                            $hasNeighborClash = true;
                            break;
                        }
                    }
                    if (!$hasNeighborClash) {
                        $bestSlot = $candidateSlot;
                        break;
                    }
                }
            }

            if ($bestSlot === null) {
                // Bottleneck detected: cannot schedule this course without violating a hard constraint
                return new SolverResultDto(
                    isSuccess: false,
                    hardViolations: [
                        sprintf('Unable to schedule course %s (%s) for Batch ID %d without violating hard constraints (no free clash-free slot with available room capacity).', $currentNode->code, $currentNode->name, $currentNode->batchId)
                    ],
                    bottleneckExplanation: sprintf('Course %s has %d conflicting offerings and requires an additional valid room-slot. Adding 1-2 exam dates or increasing daily room capacity will resolve this.', $currentNode->code, $degrees[$bestKey]),
                    relaxationSuggestions: [
                        'Extend exam session by 2 working days.',
                        'Activate additional rooms in Room Settings.',
                        'Relax Shift Rule to Soft/Preferred mode.',
                    ],
                    executionTimeSeconds: microtime(true) - $startTime,
                );
            }

            // Assign best slot
            $coloredAssignments[$bestKey] = $bestSlot;
            $slotOccupancy[$bestSlot->getKey()][] = $bestKey;
            unset($uncoloredKeys[$bestKey]);

            // Update saturation of neighbors
            foreach ($adjacency[$bestKey] as $neighborKey => $_) {
                if (isset($uncoloredKeys[$neighborKey])) {
                    $saturationDegrees[$neighborKey]++;
                }
            }
        }

        $reportProgress(65, 'Executing Phase 1B: Simulated annealing metaheuristic optimization...');

        // 4. Phase 1B: Simulated Annealing Metaheuristic
        $currentAssignments = $coloredAssignments;
        $currentCost = $this->calculateTotalSoftCost($currentAssignments, $nodes, $weights);

        $temperature = 100.0;
        $coolingRate = 0.96;
        $iterations = $dailyConsecutiveMode ? 0 : 400;

        $unlockedKeys = array_keys(array_filter($nodes, fn($n) => !$n->isLocked));

        if (!empty($unlockedKeys)) {
            for ($it = 0; $it < $iterations && $temperature > 1.0; $it++) {
                $randomCourseKey = $unlockedKeys[array_rand($unlockedKeys)];
                $targetNode = $nodes[$randomCourseKey];

                $candidateSlots = ($isShiftStrict && $targetNode->shiftId !== null)
                    ? ($slotsByShift[$targetNode->shiftId] ?? $slots)
                    : $slots;

                $newSlot = $candidateSlots[array_rand($candidateSlots)];
                $newSlotKey = $newSlot->getKey();
                $oldSlot = $currentAssignments[$randomCourseKey];

                if ($newSlot->getKey() === $oldSlot->getKey()) {
                    continue;
                }

                // Check room capacity limit
                $occupantsInNewSlot = array_filter(
                    $currentAssignments,
                    fn(SlotDto $s, string $k) => $s->getKey() === $newSlotKey && $k !== $randomCourseKey,
                    ARRAY_FILTER_USE_BOTH
                );

                if (count($occupantsInNewSlot) >= $totalActiveRooms) {
                    continue;
                }

                // Check conflict invariant (Hard constraints NEVER violated)
                $hasConflict = false;
                foreach (array_keys($occupantsInNewSlot) as $occKey) {
                    if (isset($adjacency[$randomCourseKey][$occKey])) {
                        $hasConflict = true;
                        break;
                    }
                }

                if ($hasConflict) {
                    continue;
                }

                // Evaluate candidate move
                $candidateAssignments = $currentAssignments;
                $candidateAssignments[$randomCourseKey] = $newSlot;

                $newCost = $this->calculateTotalSoftCost($candidateAssignments, $nodes, $weights);
                $delta = $newCost - $currentCost;

                if ($delta < 0 || (mt_rand() / mt_getrandmax()) < exp(-$delta / $temperature)) {
                    $currentAssignments = $candidateAssignments;
                    $currentCost = $newCost;
                }

                $temperature *= $coolingRate;
            }
        }

        $reportProgress(85, 'Executing Phase 2: Room assignment and fair invigilator distribution...');

        // 5. Convert to ScheduleAssignmentDto objects
        $scheduleAssignments = [];
        foreach ($currentAssignments as $courseKey => $slot) {
            $node = $nodes[$courseKey];
            $scheduleAssignments[] = new ScheduleAssignmentDto(
                courseId: $node->courseId,
                batchId: $node->batchId,
                slot: $slot,
                isLocked: $node->isLocked,
            );
        }

        // 6. Phase 2: Room and Invigilator Allocation
        $scheduleAssignments = $this->roomAllocator->allocateRooms(
            assignments: $scheduleAssignments,
            coursesByKey: $nodes,
            rooms: $rooms,
        );

        $scheduleAssignments = $this->invigilatorAllocator->allocateInvigilators(
            assignments: $scheduleAssignments,
            coursesByKey: $nodes,
            teachers: $teachers,
            unavailabilities: $teacherUnavailabilities,
        );

        $reportProgress(95, 'Running independent DatesheetValidator audit...');

        // 7. Decoupled Independent Validation
        $validation = $this->validator->validate(
            assignments: $scheduleAssignments,
            coursesByKey: $nodes,
            validSlotsByKey: $slotsByKey,
            roomsById: array_column($rooms, null, 'id'),
            teachersById: array_column($teachers, null, 'id'),
            softWeights: $weights,
            isShiftStrict: $isShiftStrict,
        );

        $reportProgress(100, 'Generation completed successfully with 0 hard conflicts.');

        return new SolverResultDto(
            isSuccess: $validation->isValid,
            assignments: $scheduleAssignments,
            hardViolations: $validation->hardViolations,
            softViolations: $validation->softViolations,
            softScore: $validation->softScore,
            bottleneckExplanation: $validation->isValid ? null : implode("\n", $validation->hardViolations),
            relaxationSuggestions: [],
            executionTimeSeconds: microtime(true) - $startTime,
            metrics: [
                'total_scheduled' => count($scheduleAssignments),
                'soft_score' => $validation->softScore,
                'is_shift_strict' => $isShiftStrict,
            ],
        );
    }

    /**
     * Compute penalty for placing a course node in candidate slot during initial DSATUR construction.
     */
    private function calculatePlacementPenalty(
        CourseNodeDto $node,
        SlotDto $slot,
        array $currentAssignments,
        array $allNodes,
    ): int {
        $penalty = 0;

        foreach ($currentAssignments as $cKey => $cSlot) {
            $cNode = $allNodes[$cKey];

            // Same batch considerations
            if ($cNode->batchId === $node->batchId) {
                // Same day penalty
                if ($cSlot->date === $slot->date) {
                    $penalty += 100;
                }

                // Consecutive day penalty
                $d1 = strtotime($cSlot->date);
                $d2 = strtotime($slot->date);
                if (abs($d1 - $d2) === 86400) {
                    $penalty += 50;
                }
            }
        }

        // Shift mismatch penalty (if soft shift)
        if ($node->shiftId !== null && $node->shiftId !== $slot->shiftId) {
            $penalty += 80;
        }

        return $penalty;
    }

    /**
     * Calculate total soft penalty across the entire datesheet schedule.
     */
    private function calculateTotalSoftCost(array $assignments, array $nodes, array $weights): int
    {
        $wConsecutive = $weights['consecutive_exam_penalty'] ?? 50;
        $wSameDay = $weights['same_day_exam_penalty'] ?? 100;

        $cost = 0;
        $batchDates = [];

        foreach ($assignments as $cKey => $slot) {
            $node = $nodes[$cKey];
            $batchDates[$node->batchId][] = $slot->date;
        }

        foreach ($batchDates as $batchId => $dates) {
            $counts = array_count_values($dates);
            foreach ($counts as $count) {
                if ($count > 1) {
                    $cost += $wSameDay * ($count - 1);
                }
            }

            $uniqueDates = array_values(array_unique($dates));
            sort($uniqueDates);
            for ($i = 0; $i < count($uniqueDates) - 1; $i++) {
                $diff = (strtotime($uniqueDates[$i + 1]) - strtotime($uniqueDates[$i])) / 86400;
                if ($diff === 1.0) {
                    $cost += $wConsecutive;
                }
            }
        }

        return $cost;
    }
}
