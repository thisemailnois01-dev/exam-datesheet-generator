<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Exports\BatchDatesheetExport;
use App\Exports\RoomAllocationSheetExport;
use App\Exports\TeacherDutyRosterExport;
use App\Imports\CoursesImport;
use App\Imports\RoomsImport;
use App\Models\Datesheet;
use App\Models\Department;
use App\Models\Exam;
use App\Models\ExamSession;
use App\Models\Batch;
use App\Models\Course;
use App\Models\Room;
use App\Models\Shift;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Tests\TestCase;

class ImportExportTest extends TestCase
{
    use RefreshDatabase;

    public function test_batch_datesheet_export_generates_correct_rows(): void
    {
        $dept = Department::create(['code' => 'TEST-CS', 'name' => 'Computer Science']);
        $session = ExamSession::create([
            'department_id' => $dept->id,
            'name' => 'Fall 2026',
            'start_date' => '2026-10-12',
            'end_date' => '2026-10-20',
        ]);
        $shift = Shift::create([
            'department_id' => $dept->id,
            'name' => 'Morning',
            'start_time' => '08:00',
            'end_time' => '13:00',
        ]);
        $batch = Batch::create([
            'department_id' => $dept->id,
            'shift_id' => $shift->id,
            'name' => 'BSCS-8A',
        ]);
        $course = Course::create([
            'department_id' => $dept->id,
            'batch_id' => $batch->id,
            'code' => 'CS-101',
            'name' => 'Programming',
        ]);
        $datesheet = Datesheet::create([
            'exam_session_id' => $session->id,
            'department_id' => $dept->id,
            'name' => 'Test Datesheet',
        ]);
        $exam = Exam::create([
            'datesheet_id' => $datesheet->id,
            'course_id' => $course->id,
            'batch_id' => $batch->id,
            'exam_date' => '2026-10-12',
            'slot_index' => 1,
            'shift_id' => $shift->id,
            'start_time' => '08:00',
            'end_time' => '09:00',
        ]);

        $export = new BatchDatesheetExport($datesheet->id);
        $collection = $export->collection();

        $this->assertCount(1, $collection);
        $this->assertSame('BSCS-8A', $collection[0]['batch']);
        $this->assertSame('CS-101', $collection[0]['course_code']);
        $this->assertSame('08:00 - 09:00', $collection[0]['time']);
    }

    public function test_courses_import_validates_required_fields_with_row_errors(): void
    {
        $dept = Department::create(['code' => 'TEST-CS', 'name' => 'Computer Science']);
        $importer = new CoursesImport($dept->id);

        $fakeRows = collect([
            [
                'course_code' => '', // Missing!
                'course_name' => 'Some Course',
                'batch_name' => 'BSCS-8A',
            ],
            [
                'course_code' => 'CS-202',
                'course_name' => 'Database Systems',
                'batch_name' => 'BSCS-8A',
                'credit_hours' => 4,
            ]
        ]);

        $importer->collection($fakeRows);

        $this->assertCount(1, $importer->errors);
        $this->assertStringContainsString('Row 2: course_code is required', $importer->errors[0]);
        $this->assertSame(1, $importer->importedCount);
        $this->assertDatabaseHas('courses', ['code' => 'CS-202', 'name' => 'Database Systems']);
    }

    public function test_rooms_import_validates_positive_capacity(): void
    {
        $dept = Department::create(['code' => 'TEST-CS', 'name' => 'Computer Science']);
        $importer = new RoomsImport($dept->id);

        $fakeRows = collect([
            [
                'room_name' => 'Room 101',
                'capacity' => 0, // Invalid!
                'type' => 'classroom',
            ],
            [
                'room_name' => 'Room 102',
                'capacity' => 60, // Valid
                'type' => 'classroom',
            ]
        ]);

        $importer->collection($fakeRows);

        $this->assertCount(1, $importer->errors);
        $this->assertStringContainsString('Row 2: capacity must be a positive integer', $importer->errors[0]);
        $this->assertSame(1, $importer->importedCount);
        $this->assertDatabaseHas('rooms', ['name' => 'Room 102', 'capacity' => 60]);
    }
}
