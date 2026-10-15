<?php

namespace Tests\Feature;

use App\Domain\Timetable\Services\DemoDataGenerator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    use RefreshDatabase;

    /**
     * A basic test example.
     */
    public function test_the_application_returns_a_successful_response(): void
    {
        $generator = new DemoDataGenerator();
        $generator->generateStandardDemo();

        $response = $this->get('/');

        $response->assertStatus(200);
    }
}

