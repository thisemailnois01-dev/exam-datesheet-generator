<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Add role column to users if not present
        if (!Schema::hasColumn('users', 'role')) {
            Schema::table('users', function (Blueprint $table) {
                $table->string('role')->default('admin')->after('email');
            });
        }

        Schema::create('departments', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->string('name');
            $table->timestamps();
        });

        Schema::create('exam_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('department_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->date('start_date');
            $table->date('end_date');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('shifts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('department_id')->constrained()->cascadeOnDelete();
            $table->string('name'); // Morning, Evening, etc.
            $table->string('start_time', 10); // '08:00'
            $table->string('end_time', 10);   // '13:00'
            $table->unsignedInteger('sort_order')->default(1);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['department_id', 'name']);
        });

        Schema::create('settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('department_id')->constrained()->cascadeOnDelete();
            $table->foreignId('exam_session_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedInteger('default_duration_minutes')->default(60);
            $table->unsignedInteger('break_minutes')->default(30);
            $table->boolean('shift_rule_strict')->default(true);
            $table->json('working_days'); // ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday']
            $table->json('weights');      // soft constraint weights
            $table->timestamps();

            $table->unique(['department_id', 'exam_session_id']);
        });

        Schema::create('batches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('department_id')->constrained()->cascadeOnDelete();
            $table->foreignId('shift_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name'); // e.g. BSCS-8A, BSSE-4B
            $table->unsignedInteger('student_count')->default(40);
            $table->timestamps();

            $table->unique(['department_id', 'name']);
        });

        Schema::create('rooms', function (Blueprint $table) {
            $table->id();
            $table->foreignId('department_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->unsignedInteger('capacity')->default(50);
            $table->string('type')->default('classroom'); // classroom, lab, hall
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['department_id', 'name']);
        });

        Schema::create('teachers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('department_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('email')->nullable();
            $table->unsignedInteger('max_duties_per_day')->default(2);
            $table->unsignedInteger('max_duties_per_session')->default(10);
            $table->timestamps();
        });

        Schema::create('courses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('department_id')->constrained()->cascadeOnDelete();
            $table->foreignId('batch_id')->nullable()->constrained()->nullOnDelete();
            $table->string('code'); // CS-101, etc.
            $table->string('name');
            $table->unsignedTinyInteger('credit_hours')->default(3);
            $table->string('type')->default('theory'); // theory, practical, viva
            $table->unsignedInteger('duration_minutes')->nullable(); // optional override
            $table->boolean('is_core')->default(false);
            $table->boolean('is_hard')->default(false);
            $table->timestamps();
        });

        Schema::create('students', function (Blueprint $table) {
            $table->id();
            $table->foreignId('department_id')->constrained()->cascadeOnDelete();
            $table->foreignId('batch_id')->nullable()->constrained()->nullOnDelete();
            $table->string('roll_number');
            $table->string('name');
            $table->timestamps();

            $table->unique(['department_id', 'roll_number']);
        });

        Schema::create('enrollments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();
            $table->foreignId('course_id')->constrained()->cascadeOnDelete();
            $table->foreignId('batch_id')->nullable()->constrained()->nullOnDelete();
            $table->string('type')->default('regular'); // regular, repeater, improvement, elective
            $table->timestamps();

            $table->unique(['student_id', 'course_id']);
        });

        Schema::create('teacher_courses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('teacher_id')->constrained()->cascadeOnDelete();
            $table->foreignId('course_id')->constrained()->cascadeOnDelete();
            $table->boolean('is_primary')->default(true);
            $table->timestamps();

            $table->unique(['teacher_id', 'course_id']);
        });

        Schema::create('teacher_availabilities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('teacher_id')->constrained()->cascadeOnDelete();
            $table->date('date');
            $table->unsignedInteger('slot_index')->nullable(); // null means entire day
            $table->foreignId('shift_id')->nullable()->constrained()->nullOnDelete();
            $table->boolean('is_available')->default(false);
            $table->string('reason')->nullable();
            $table->timestamps();
        });

        Schema::create('holidays', function (Blueprint $table) {
            $table->id();
            $table->foreignId('exam_session_id')->constrained()->cascadeOnDelete();
            $table->date('date');
            $table->string('title');
            $table->string('start_time', 10)->nullable(); // for partial day blackout (e.g. Friday prayer)
            $table->string('end_time', 10)->nullable();
            $table->timestamps();
        });

        Schema::create('datesheets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('exam_session_id')->constrained()->cascadeOnDelete();
            $table->foreignId('department_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->unsignedInteger('version')->default(1);
            $table->string('status')->default('draft'); // draft, published, archived
            $table->integer('soft_score')->default(0);
            $table->json('metrics')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('exams', function (Blueprint $table) {
            $table->id();
            $table->foreignId('datesheet_id')->constrained()->cascadeOnDelete();
            $table->foreignId('course_id')->constrained()->cascadeOnDelete();
            $table->foreignId('batch_id')->constrained()->cascadeOnDelete();
            $table->date('exam_date');
            $table->unsignedInteger('slot_index');
            $table->foreignId('shift_id')->constrained()->cascadeOnDelete();
            $table->string('start_time', 10);
            $table->string('end_time', 10);
            $table->boolean('is_locked')->default(false);
            $table->timestamps();

            // Unique constraint: No batch can have two exams in the same date and slot_index
            $table->unique(['datesheet_id', 'batch_id', 'exam_date', 'slot_index'], 'unique_batch_slot_per_datesheet');
            $table->index(['datesheet_id', 'exam_date', 'slot_index']);
        });

        Schema::create('exam_room_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('exam_id')->constrained()->cascadeOnDelete();
            $table->foreignId('room_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('student_count')->default(0);
            $table->timestamps();

            $table->unique(['exam_id', 'room_id']);
        });

        Schema::create('exam_invigilators', function (Blueprint $table) {
            $table->id();
            $table->foreignId('exam_id')->constrained()->cascadeOnDelete();
            $table->foreignId('teacher_id')->constrained()->cascadeOnDelete();
            $table->foreignId('room_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();

            $table->unique(['exam_id', 'teacher_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('exam_invigilators');
        Schema::dropIfExists('exam_room_assignments');
        Schema::dropIfExists('exams');
        Schema::dropIfExists('datesheets');
        Schema::dropIfExists('holidays');
        Schema::dropIfExists('teacher_availabilities');
        Schema::dropIfExists('teacher_courses');
        Schema::dropIfExists('enrollments');
        Schema::dropIfExists('students');
        Schema::dropIfExists('courses');
        Schema::dropIfExists('teachers');
        Schema::dropIfExists('rooms');
        Schema::dropIfExists('batches');
        Schema::dropIfExists('settings');
        Schema::dropIfExists('shifts');
        Schema::dropIfExists('exam_sessions');
        Schema::dropIfExists('departments');

        if (Schema::hasColumn('users', 'role')) {
            Schema::table('users', function (Blueprint $table) {
                $table->dropColumn('role');
            });
        }
    }
};
