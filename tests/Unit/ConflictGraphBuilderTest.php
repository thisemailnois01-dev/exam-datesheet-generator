<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Domain\Timetable\DTOs\CourseNodeDto;
use App\Domain\Timetable\Services\ConflictGraphBuilder;
use PHPUnit\Framework\TestCase;

class ConflictGraphBuilderTest extends TestCase
{
    private ConflictGraphBuilder $builder;

    protected function setUp(): void
    {
        parent::setUp();
        $this->builder = new ConflictGraphBuilder();
    }

    public function test_creates_conflict_edge_between_courses_of_same_batch(): void
    {
        $node1 = new CourseNodeDto(
            courseId: 101,
            batchId: 1,
            shiftId: 1,
            code: 'CS-101',
            name: 'Programming',
            studentCount: 40,
            durationMinutes: 60,
            isCore: true,
            isHard: false
        );

        $node2 = new CourseNodeDto(
            courseId: 102,
            batchId: 1, // Same batch
            shiftId: 1,
            code: 'CS-102',
            name: 'Discrete Math',
            studentCount: 40,
            durationMinutes: 60,
            isCore: true,
            isHard: false
        );

        $graph = $this->builder->buildGraph([$node1, $node2]);

        $this->assertTrue($graph['adjacency'][$node1->getKey()][$node2->getKey()]);
        $this->assertSame(1, $graph['degrees'][$node1->getKey()]);
        $this->assertSame(1, $graph['degrees'][$node2->getKey()]);
    }

    public function test_creates_conflict_edge_for_shared_student_enrollments(): void
    {
        // Two courses from different batches sharing a repeater student
        $nodeA = new CourseNodeDto(
            courseId: 201,
            batchId: 10,
            shiftId: 1,
            code: 'CS-201',
            name: 'Algorithms',
            studentCount: 30,
            durationMinutes: 60,
            isCore: true,
            isHard: true,
            studentIds: [501, 502, 999] // Student 999 is in both
        );

        $nodeB = new CourseNodeDto(
            courseId: 301,
            batchId: 20, // Different batch
            shiftId: 1,
            code: 'CS-301',
            name: 'Databases',
            studentCount: 30,
            durationMinutes: 60,
            isCore: true,
            isHard: false,
            studentIds: [601, 602, 999] // Student 999 is in both
        );

        $graph = $this->builder->buildGraph([$nodeA, $nodeB]);

        $this->assertTrue($graph['adjacency'][$nodeA->getKey()][$nodeB->getKey()]);
        $this->assertStringContainsString('shared student', $graph['conflict_reasons'][$nodeA->getKey()][$nodeB->getKey()]);
    }

    public function test_no_edge_when_independent_batches_and_students(): void
    {
        $nodeA = new CourseNodeDto(
            courseId: 1,
            batchId: 10,
            shiftId: 1,
            code: 'C-1',
            name: 'Course 1',
            studentCount: 20,
            durationMinutes: 60,
            isCore: false,
            isHard: false,
            studentIds: [1, 2]
        );

        $nodeB = new CourseNodeDto(
            courseId: 2,
            batchId: 20,
            shiftId: 1,
            code: 'C-2',
            name: 'Course 2',
            studentCount: 20,
            durationMinutes: 60,
            isCore: false,
            isHard: false,
            studentIds: [3, 4]
        );

        $graph = $this->builder->buildGraph([$nodeA, $nodeB], checkTeacherConflicts: false);

        $this->assertEmpty($graph['adjacency'][$nodeA->getKey()]);
        $this->assertSame(0, $graph['degrees'][$nodeA->getKey()]);
    }
}
