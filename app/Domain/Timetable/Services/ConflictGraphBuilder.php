<?php

declare(strict_types=1);

namespace App\Domain\Timetable\Services;

use App\Domain\Timetable\DTOs\CourseNodeDto;

final class ConflictGraphBuilder
{
    /**
     * Build adjacency list representation of conflict graph G = (V, E).
     *
     * @param array<CourseNodeDto> $nodes
     * @param bool $checkTeacherConflicts
     * @return array{
     *     nodes: array<string, CourseNodeDto>,
     *     adjacency: array<string, array<string, bool>>,
     *     degrees: array<string, int>,
     *     conflict_reasons: array<string, array<string, string>>
     * }
     */
    public function buildGraph(array $nodes, bool $checkTeacherConflicts = true): array
    {
        $indexedNodes = [];
        $adjacency = [];
        $degrees = [];
        $conflictReasons = [];

        foreach ($nodes as $node) {
            $key = $node->getKey();
            $indexedNodes[$key] = $node;
            $adjacency[$key] = [];
            $degrees[$key] = 0;
            $conflictReasons[$key] = [];
        }

        $nodeKeys = array_keys($indexedNodes);
        $totalNodes = count($nodeKeys);

        for ($i = 0; $i < $totalNodes; $i++) {
            $keyA = $nodeKeys[$i];
            $nodeA = $indexedNodes[$keyA];

            for ($j = $i + 1; $j < $totalNodes; $j++) {
                $keyB = $nodeKeys[$j];
                $nodeB = $indexedNodes[$keyB];

                $hasConflict = false;
                $reason = '';

                // Condition 1: Same batch
                if ($nodeA->batchId === $nodeB->batchId) {
                    $hasConflict = true;
                    $reason = sprintf('Same batch (%s)', $nodeA->batchId);
                }
                // Condition 2: Shared student enrollments (electives, repeaters)
                elseif (!empty($nodeA->studentIds) && !empty($nodeB->studentIds)) {
                    $sharedStudents = array_intersect($nodeA->studentIds, $nodeB->studentIds);
                    if (!empty($sharedStudents)) {
                        $hasConflict = true;
                        $reason = sprintf('%d shared student(s)', count($sharedStudents));
                    }
                }

                // Condition 3: Shared teachers (if enabled)
                if (!$hasConflict && $checkTeacherConflicts && !empty($nodeA->teacherIds) && !empty($nodeB->teacherIds)) {
                    $sharedTeachers = array_intersect($nodeA->teacherIds, $nodeB->teacherIds);
                    if (!empty($sharedTeachers)) {
                        $hasConflict = true;
                        $reason = sprintf('%d shared teacher(s)', count($sharedTeachers));
                    }
                }

                if ($hasConflict) {
                    $adjacency[$keyA][$keyB] = true;
                    $adjacency[$keyB][$keyA] = true;
                    $degrees[$keyA]++;
                    $degrees[$keyB]++;
                    $conflictReasons[$keyA][$keyB] = $reason;
                    $conflictReasons[$keyB][$keyA] = $reason;
                }
            }
        }

        return [
            'nodes' => $indexedNodes,
            'adjacency' => $adjacency,
            'degrees' => $degrees,
            'conflict_reasons' => $conflictReasons,
        ];
    }
}
