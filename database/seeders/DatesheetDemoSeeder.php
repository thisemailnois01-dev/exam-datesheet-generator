<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Domain\Timetable\Services\DemoDataGenerator;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatesheetDemoSeeder extends Seeder
{
    public function run(DemoDataGenerator $generator): void
    {
        // Create default Admin and Viewer users
        User::firstOrCreate(
            ['email' => 'admin@university.edu.pk'],
            [
                'name' => 'Department Admin',
                'password' => Hash::make('password'),
                'role' => 'admin',
            ]
        );

        User::firstOrCreate(
            ['email' => 'viewer@university.edu.pk'],
            [
                'name' => 'Faculty Viewer',
                'password' => Hash::make('password'),
                'role' => 'viewer',
            ]
        );

        $generator->generateStandardDemo();
    }
}
