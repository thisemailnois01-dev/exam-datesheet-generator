<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Domain\Timetable\Services\DemoDataGenerator;
use App\Models\Datesheet;
use App\Models\Department;
use App\Models\Exam;
use App\Models\ExamSession;
use App\Models\Setting;
use App\Models\Shift;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WebInterfaceTest extends TestCase
{
    use RefreshDatabase;

    public function test_timetable_grid_renders_successfully(): void
    {
        $generator = new DemoDataGenerator();
        $demo = $generator->generateStandardDemo();

        $response = $this->get('/');
        $response->assertStatus(200);
        $response->assertSee('Examination Timetable Grid');
        $response->assertSee('Generate Datesheet');
    }

    public function test_settings_page_renders_successfully(): void
    {
        $generator = new DemoDataGenerator();
        $generator->generateStandardDemo();

        $response = $this->get('/settings');
        $response->assertStatus(200);
        $response->assertSee('System Settings & Timing Model', false);
        $response->assertSee('Exam Duration D');
    }

    public function test_what_if_page_renders_successfully(): void
    {
        $generator = new DemoDataGenerator();
        $generator->generateStandardDemo();

        $response = $this->get('/what-if');
        $response->assertStatus(200);
        $response->assertSee('What-If Simulation Sandbox', false);
    }

    public function test_versions_page_renders_successfully(): void
    {
        $generator = new DemoDataGenerator();
        $generator->generateStandardDemo();

        $response = $this->get('/versions');
        $response->assertStatus(200);
        $response->assertSee('Version History & Audit Trail', false);
    }

    public function test_data_import_page_renders_successfully(): void
    {
        $generator = new DemoDataGenerator();
        $generator->generateStandardDemo();

        $response = $this->get('/import-data');
        $response->assertStatus(200);
        $response->assertSee('Data Management & Excel Import', false);
        $response->assertSee('Courses Template');
    }

    public function test_printable_pdf_route_renders_successfully(): void
    {
        $generator = new DemoDataGenerator();
        $demo = $generator->generateStandardDemo();

        $datesheet = Datesheet::create([
            'exam_session_id' => $demo['session']->id,
            'department_id' => $demo['department']->id,
            'name' => 'Spring 2026 Final Datesheet',
            'version' => 1,
            'status' => 'draft',
        ]);

        $response = $this->get('/export/pdf/' . $datesheet->id);
        $response->assertStatus(200);
        $response->assertSee('Baba Guru Nanak University', false);
        $response->assertSee('Official Examination Datesheet');
    }

    public function test_downloadable_template_route_returns_excel_download(): void
    {
        $response = $this->get('/export/template/courses');
        $response->assertStatus(200);
        $response->assertHeader('content-disposition');
    }
}
