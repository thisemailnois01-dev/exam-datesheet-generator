<?php

declare(strict_types=1);

namespace App\Domain\Timetable\Services;

use App\Models\Batch;
use App\Models\Course;
use App\Models\Department;
use App\Models\Datesheet;
use App\Models\Enrollment;
use App\Models\ExamSession;
use App\Models\Holiday;
use App\Models\Room;
use App\Models\Setting;
use App\Models\Shift;
use App\Models\Student;
use App\Models\Teacher;
use App\Models\TeacherCourse;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

final class DemoDataGenerator
{
    /**
     * Generate standard realistic demo dataset.
     */
    public function generateStandardDemo(
        int $batchCount = 8,
        int $coursesPerBatch = 5,
        int $roomCount = 12,
        int $teacherCount = 15,
        int $studentsPerBatch = 40,
        string $departmentCode = 'CS-DEPT',
    ): array {
        return DB::transaction(function () use (
            $batchCount,
            $coursesPerBatch,
            $roomCount,
            $teacherCount,
            $studentsPerBatch,
            $departmentCode
        ) {
            // 1. Department (purge existing demo department cleanly)
            $this->purgeDepartment($departmentCode);

            $department = Department::create([
                'code' => $departmentCode,
                'name' => 'Department of Computer Science & Software Engineering (' . $departmentCode . ')',
            ]);

            // 2. Exam Session (2 weeks: 10 working days)
            $startDate = Carbon::now()->addDays(7)->startOfWeek(); // Next Monday
            $endDate = (clone $startDate)->addDays(12); // Following Saturday

            $session = ExamSession::create([
                'department_id' => $department->id,
                'name' => 'Spring 2026 Final Examinations (Demo ' . $departmentCode . ')',
                'start_date' => $startDate->format('Y-m-d'),
                'end_date' => $endDate->format('Y-m-d'),
                'is_active' => true,
            ]);

            // 3. Shifts (Morning 08:00-13:00, Evening 15:00-20:00)
            $morningShift = Shift::firstOrCreate(
                ['department_id' => $department->id, 'name' => 'Morning'],
                [
                    'start_time' => '08:00',
                    'end_time' => '13:00',
                    'sort_order' => 1,
                    'is_active' => true,
                ]
            );

            $eveningShift = Shift::firstOrCreate(
                ['department_id' => $department->id, 'name' => 'Evening'],
                [
                    'start_time' => '15:00',
                    'end_time' => '20:00',
                    'sort_order' => 2,
                    'is_active' => true,
                ]
            );

            // 4. Settings
            $setting = Setting::create([
                'department_id' => $department->id,
                'exam_session_id' => $session->id,
                'default_duration_minutes' => 60,
                'break_minutes' => 30,
                'shift_rule_strict' => true,
                'working_days' => Setting::defaultWorkingDays(),
                'weights' => Setting::defaultWeights(),
            ]);

            // 5. Friday prayer blackout & one holiday
            Holiday::create([
                'exam_session_id' => $session->id,
                'date' => (clone $startDate)->addDays(4)->format('Y-m-d'), // Friday
                'title' => 'Friday Prayer Break',
                'start_time' => '12:30',
                'end_time' => '14:30',
            ]);

            // 6. Rooms (12 rooms, capacity 50-70)
            $rooms = [];
            for ($r = 1; $r <= $roomCount; $r++) {
                $rooms[] = Room::create([
                    'department_id' => $department->id,
                    'name' => sprintf('Room %02d (Hall %s)', $r, chr(64 + (int)ceil($r / 4))),
                    'capacity' => 50 + ($r % 3) * 10,
                    'type' => $r <= 2 ? 'hall' : 'classroom',
                    'is_active' => true,
                ]);
            }

            // 7. Teachers (15 teachers)
            $teachers = [];
            $teacherNames = [
                'Dr. Tariq Mahmood', 'Prof. Sarah Ahmed', 'Dr. Usman Farooq',
                'Engr. Bilal Khan', 'Ms. Ayesha Siddiqui', 'Dr. Imran Ali',
                'Engr. Zainab Raza', 'Mr. Hamza Javed', 'Dr. Nadia Qureshi',
                'Prof. Mansoor Shah', 'Ms. Fatima Noor', 'Mr. Omer Khalid',
                'Dr. Rehan Sheikh', 'Engr. Sana Mir', 'Mr. Asad Mehmood'
            ];

            for ($t = 0; $t < $teacherCount; $t++) {
                $name = $teacherNames[$t] ?? sprintf('Teacher %d', $t + 1);
                $teachers[] = Teacher::create([
                    'department_id' => $department->id,
                    'name' => $name,
                    'email' => strtolower(str_replace(['Dr. ', 'Prof. ', 'Engr. ', 'Ms. ', 'Mr. ', ' '], ['', '', '', '', '', '.'], $name)) . '@university.edu.pk',
                    'max_duties_per_day' => 2,
                    'max_duties_per_session' => 8,
                ]);
            }

            // 8. Batches (4 morning batches, 4 evening batches)
            $batchNames = [
                ['name' => 'BSCS-8A', 'shift' => $morningShift],
                ['name' => 'BSCS-8B', 'shift' => $morningShift],
                ['name' => 'BSSE-6A', 'shift' => $morningShift],
                ['name' => 'BSAI-4A', 'shift' => $morningShift],
                ['name' => 'BSCS-8E', 'shift' => $eveningShift],
                ['name' => 'BSSE-6E', 'shift' => $eveningShift],
                ['name' => 'BSAI-4E', 'shift' => $eveningShift],
                ['name' => 'MSCS-2E', 'shift' => $eveningShift],
            ];

            $batches = [];
            foreach (array_slice($batchNames, 0, $batchCount) as $bData) {
                $batches[] = Batch::create([
                    'department_id' => $department->id,
                    'shift_id' => $bData['shift']->id,
                    'name' => $bData['name'],
                    'student_count' => $studentsPerBatch,
                ]);
            }

            // 9. Courses and Student Enrollments
            $courses = [];
            $subjectPool = [
                ['code' => 'CS-101', 'name' => 'Programming Fundamentals', 'credit' => 4, 'is_core' => true, 'is_hard' => true],
                ['code' => 'CS-201', 'name' => 'Data Structures & Algorithms', 'credit' => 4, 'is_core' => true, 'is_hard' => true],
                ['code' => 'CS-301', 'name' => 'Operating Systems', 'credit' => 3, 'is_core' => true, 'is_hard' => false],
                ['code' => 'CS-401', 'name' => 'Database Systems', 'credit' => 4, 'is_core' => true, 'is_hard' => false],
                ['code' => 'CS-501', 'name' => 'Artificial Intelligence', 'credit' => 3, 'is_core' => false, 'is_hard' => true],
                ['code' => 'CS-601', 'name' => 'Compiler Construction', 'credit' => 3, 'is_core' => false, 'is_hard' => true],
                ['code' => 'SE-302', 'name' => 'Software Engineering', 'credit' => 3, 'is_core' => true, 'is_hard' => false],
                ['code' => 'AI-402', 'name' => 'Machine Learning', 'credit' => 3, 'is_core' => false, 'is_hard' => true],
                ['code' => 'CS-701', 'name' => 'Cloud Computing (Elective)', 'credit' => 3, 'is_core' => false, 'is_hard' => false],
                ['code' => 'CS-801', 'name' => 'Cyber Security (Elective)', 'credit' => 3, 'is_core' => false, 'is_hard' => false],
            ];

            $allStudents = [];
            $studentIndex = 1;

            foreach ($batches as $bIndex => $batch) {
                // Create students for batch
                $batchStudents = [];
                for ($s = 1; $s <= 10; $s++) { // Create 10 tracked individual student records per batch for conflict testing
                    $student = Student::create([
                        'department_id' => $department->id,
                        'batch_id' => $batch->id,
                        'roll_number' => sprintf('%s-%03d', $batch->name, $s),
                        'name' => sprintf('Student %s #%d', $batch->name, $s),
                    ]);
                    $batchStudents[] = $student;
                    $allStudents[] = $student;
                    $studentIndex++;
                }

                // Create courses for batch
                for ($c = 0; $c < $coursesPerBatch; $c++) {
                    $template = $subjectPool[($bIndex * 2 + $c) % count($subjectPool)];
                    $courseCode = sprintf('%s-%s', $template['code'], $batch->name);

                    $course = Course::create([
                        'department_id' => $department->id,
                        'batch_id' => $batch->id,
                        'code' => $courseCode,
                        'name' => $template['name'],
                        'credit_hours' => $template['credit'],
                        'type' => 'theory',
                        'duration_minutes' => null, // default 60 min
                        'is_core' => $template['is_core'],
                        'is_hard' => $template['is_hard'],
                    ]);

                    $courses[] = $course;

                    // Assign primary teacher
                    $assignedTeacher = $teachers[($bIndex + $c) % count($teachers)];
                    TeacherCourse::create([
                        'teacher_id' => $assignedTeacher->id,
                        'course_id' => $course->id,
                        'is_primary' => true,
                    ]);

                    // Enroll batch students
                    foreach ($batchStudents as $st) {
                        Enrollment::create([
                            'student_id' => $st->id,
                            'course_id' => $course->id,
                            'batch_id' => $batch->id,
                            'type' => 'regular',
                        ]);
                    }
                }
            }

            // 10. Cross-batch shared elective & repeater enrollments (tests student clash edge)
            if (count($courses) >= 2 && count($allStudents) >= 5) {
                // Cross-enroll student 1 into an elective course from another batch
                $repeaterStudent = $allStudents[0];
                $crossCourse = $courses[count($courses) - 1];
                Enrollment::firstOrCreate([
                    'student_id' => $repeaterStudent->id,
                    'course_id' => $crossCourse->id,
                ], [
                    'batch_id' => $repeaterStudent->batch_id,
                    'type' => 'repeater',
                ]);
            }

            return [
                'department' => $department,
                'session' => $session,
                'shifts' => [$morningShift, $eveningShift],
                'setting' => $setting,
                'rooms_count' => count($rooms),
                'batches_count' => count($batches),
                'courses_count' => count($courses),
                'teachers_count' => count($teachers),
            ];
        });
    }

    /**
     * Generate the complete authentic Baba Guru Nanak University 20-batch dataset from the official PDF sample.
     */
    public function generateBgnuDemo(?string $startDateStr = '2026-06-08'): array
    {
        return DB::transaction(function () use ($startDateStr) {
            $departmentCode = 'CS-DEPT';
            $this->purgeDepartment($departmentCode);

            // 1. Department
            $department = Department::create([
                'code' => $departmentCode,
                'name' => 'Department of Computer Science & IT',
            ]);

            // 2. Exam Session
            $startDate = Carbon::parse($startDateStr ?? '2026-06-08');
            $endDate = (clone $startDate)->addDays(14); // 2 full weeks covering up to 12 working days

            $session = ExamSession::create([
                'department_id' => $department->id,
                'name' => 'Final Term Examination, Spring 2026',
                'start_date' => $startDate->format('Y-m-d'),
                'end_date' => $endDate->format('Y-m-d'),
                'is_active' => true,
            ]);

            // 3. Shifts (09:00 - 11:00, 11:30 - 01:30, 02:00 - 04:00)
            $shift1 = Shift::create([
                'department_id' => $department->id,
                'name' => 'Morning Early (09:00 - 11:00)',
                'start_time' => '09:00',
                'end_time' => '11:00',
                'sort_order' => 1,
                'is_active' => true,
            ]);

            $shift2 = Shift::create([
                'department_id' => $department->id,
                'name' => 'Mid-Morning (11:30 - 01:30)',
                'start_time' => '11:30',
                'end_time' => '13:30',
                'sort_order' => 2,
                'is_active' => true,
            ]);

            $shift3 = Shift::create([
                'department_id' => $department->id,
                'name' => 'Replica (02:00 - 04:00)',
                'start_time' => '14:00',
                'end_time' => '16:00',
                'sort_order' => 3,
                'is_active' => true,
            ]);

            // 4. Settings
            $setting = Setting::create([
                'department_id' => $department->id,
                'exam_session_id' => $session->id,
                'default_duration_minutes' => 120,
                'break_minutes' => 30,
                'shift_rule_strict' => true,
                'working_days' => ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'],
                'weights' => array_merge(Setting::defaultWeights(), [
                    'consecutive_exam_penalty' => 0,
                    'allow_consecutive_daily' => true,
                ]),
            ]);

            // 5. Rooms (exact rooms from university timetable)
            $roomNames = [
                'Room # 01', 'Room # 02', 'Room # 04', 'Room # 05', 'Room # 06',
                'Room # 07', 'Room # 08', 'Room # 09', 'Room # 14',
                'PHYSICS LAB', 'COMPUTER LAB 1', 'COMPUTER LAB 3', 'S/W LAB',
            ];
            $rooms = [];
            foreach ($roomNames as $rName) {
                $rooms[] = Room::create([
                    'department_id' => $department->id,
                    'name' => $rName,
                    'capacity' => 50,
                    'type' => str_contains($rName, 'LAB') ? 'lab' : 'classroom',
                    'is_active' => true,
                ]);
            }

            // 6. Faculty (exact names extracted from CLASSES TIME TABLE FALL 2026 V3)
            $teacherList = [
                'Dr. Bilal',
                'Dr. Dilshad',
                'Dr. Iftikhar',
                'Dr. M. Usman Younus',
                'Dr. Muhammad Usman Younus',
                'Dr. Shehzad Nazir',
                'Mr. Adnan',
                'Mr. Amir Nazir',
                'Mr. Bilal Khan',
                'Mr. Bilal Pervaiz',
                'Mr. Faiz ul Hassan',
                'Mr. Hamza Mehmood',
                'Mr. Hassan Iftikhar',
                'Mr. Iqbal Haider',
                'Mr. Jawad',
                'Mr. M. Khalid',
                'Mr. M. Usman Shoukat',
                'Mr. Qamar Abbas',
                'Mr. Usman',
                'Ms. Amina',
                'Ms. Arooj Fatima',
                'Ms. Fizza',
                'Ms. Iqra Rafaqat',
                'Ms. Kashifa Nawab',
                'Ms. Noor Fatima',
                'Ms. Reeha',
                'Ms. Rimsha',
                'Ms. Robina',
            ];

            $teachers = [];
            foreach ($teacherList as $tName) {
                $teachers[$tName] = Teacher::create([
                    'department_id' => $department->id,
                    'name' => $tName,
                    'email' => strtolower(str_replace(['Dr. ', 'Prof. ', 'Engr. ', 'Ms. ', 'Mr. ', ' '], ['', '', '', '', '', '.'], $tName)) . '@bgnu.edu.pk',
                    'max_duties_per_day' => 3,
                    'max_duties_per_session' => 15,
                ]);
            }

            // 7. Batches & Courses definitions (exact 24 batches from timetable PDF)
            $batchDefs = [
                [
                    'name' => 'BSCS 2023-2027 (Regular)',
                    'shift' => $shift1,
                    'room' => 'Room # 6',
                    'courses' => [
                        ['Compiler Construction', 'Ms. Noor Fatima'],
                        ['Translation of Holy Quran - IV', 'Mr. Iqbal Haider'],
                        ['Cyber Security', 'Ms. Kashifa Nawab'],
                        ['Technical and Business Writing', 'Ms. Arooj Fatima'],
                        ['Telecommunication System', 'Dr. M. Usman Younus'],
                        ['Advanced Database Management System', 'Mr. Hamza Mehmood'],
                        ['Numerical Analysis', 'Mr. M. Usman Shoukat'],
                    ],
                ],
                [
                    'name' => 'BSCS 2023-2027 (Replica)',
                    'shift' => $shift3,
                    'room' => 'Room # 6',
                    'courses' => [
                        ['Compiler Construction', 'Ms. Noor Fatima'],
                        ['Cyber Security', 'Ms. Kashifa Nawab'],
                        ['Technical and Business Writing', 'Ms. Arooj Fatima'],
                        ['Telecommunication System', 'Dr. M. Usman Younus'],
                        ['Advanced Database Management System', 'Mr. Hamza Mehmood'],
                        ['Numerical Analysis', 'Mr. M. Usman Shoukat'],
                        ['Translation of Holy Quran - IV', 'Mr. Iqbal Haider'],
                    ],
                ],
                [
                    'name' => 'BSCS 2024-2028 (Regular)',
                    'shift' => $shift1,
                    'room' => 'Room # 8',
                    'courses' => [
                        ['Computer Networks', 'Mr. M. Usman Shoukat'],
                        ['Operating Systems', 'Dr. Shehzad Nazir'],
                        ['HCI & Computer Graphics', 'Ms. Zama Ramzan'],
                        ['Cyber Security', 'Ms. Kashifa Nawab'],
                        ['Translation of Holy Quran - III', 'Mr. Amir Nazir'],
                    ],
                ],
                [
                    'name' => 'BSCS 2024-2028 (Replica)',
                    'shift' => $shift3,
                    'room' => 'Room # 8',
                    'courses' => [
                        ['HCI & Computer Graphics', 'Ms. Zama Ramzan'],
                        ['Computer Networks', 'Mr. M. Usman Shoukat'],
                        ['Translation of Holy Quran - III', 'Mr. Amir Nazir'],
                        ['Operating Systems', 'Dr. Shehzad Nazir'],
                        ['Cyber Security', 'Ms. Kashifa Nawab'],
                    ],
                ],
                [
                    'name' => 'BSCS 2025-2029 (Regular)',
                    'shift' => $shift1,
                    'room' => 'Room # 01',
                    'courses' => [
                        ['Elementary Linear Algebra', 'Mr. Qamar Abbas'],
                        ['Expository Writing', 'Ms. Rimsha'],
                        ['Computer Organization & Assembly Language', 'Mr. Jawad'],
                        ['Database Systems', 'Ms. Zama Ramzan'],
                        ['Understanding of Quran - II', 'Mr. Iqbal Haider'],
                    ],
                ],
                [
                    'name' => 'BSCS 2025-2029 (Replica)',
                    'shift' => $shift3,
                    'room' => 'Room # 01',
                    'courses' => [
                        ['Understanding of Quran - II', 'Mr. Iqbal Haider'],
                        ['Computer Organization & Assembly Language', 'Mr. M. Khalid'],
                        ['Database Systems', 'Ms. Zama Ramzan'],
                        ['Expository Writing', 'Ms. Rimsha'],
                        ['Elementary Linear Algebra', 'Dr. Iftikhar'],
                    ],
                ],
                [
                    'name' => 'BSCS 2026-2030 (Regular)',
                    'shift' => $shift1,
                    'room' => 'Room # 9',
                    'courses' => [
                        ['Understanding of Quran - I', 'Mr. Amir Nazir'],
                        ['Calculus & Analytical Geometry', 'Dr. Bilal'],
                        ['Functional English', 'Ms. Rimsha'],
                        ['Programming Fundamentals', 'Dr. Shehzad Nazir'],
                        ['Introduction to Management', 'Mr. Usman'],
                        ['Information & Communication Technology', 'Mr. Hassan Iftikhar'],
                    ],
                ],
                [
                    'name' => 'BSCS 2026-2030 (Replica)',
                    'shift' => $shift3,
                    'room' => 'Room # 9',
                    'courses' => [
                        ['Functional English', 'Ms. Rimsha'],
                        ['Calculus & Analytical Geometry', 'Mr. Bilal Khan'],
                        ['Introduction to Management', 'Mr. Usman'],
                        ['Understanding of Quran - I', 'Mr. Amir Nazir'],
                        ['Programming Fundamentals', 'Mr. Hassan Iftikhar'],
                    ],
                ],
                [
                    'name' => 'BSIT 2023-2027 (Regular)',
                    'shift' => $shift1,
                    'room' => 'Room # 5',
                    'courses' => [
                        ['Enterprise Systems', 'Ms. Iqra Rafaqat'],
                        ['Technical and Business Writing', 'Ms. Amina'],
                        ['Translation of Holy Quran - IV', 'Mr. Iqbal Haider'],
                        ['Telecommunication System', 'Dr. M. Usman Younus'],
                        ['Database Administration & Management', 'Mr. M. Usman Shoukat'],
                        ['Vulnerability Assessment & Security', 'Mr. Faiz ul Hassan'],
                    ],
                ],
                [
                    'name' => 'BSIT 2023-2027 (Replica)',
                    'shift' => $shift3,
                    'room' => 'Room # 1',
                    'courses' => [
                        ['Technical and Business Writing', 'Ms. Amina'],
                        ['Enterprise Systems', 'Ms. Iqra Rafaqat'],
                        ['Telecommunication System', 'Dr. M. Usman Younus'],
                        ['Database Administration & Management', 'Mr. M. Usman Shoukat'],
                        ['Vulnerability Assessment & Security', 'Mr. Faiz ul Hassan'],
                        ['Translation of Holy Quran - IV', 'Mr. Iqbal Haider'],
                    ],
                ],
                [
                    'name' => 'BSIT 2024-2028 (Regular)',
                    'shift' => $shift1,
                    'room' => 'Room # 14',
                    'courses' => [
                        ['HCI & Computer Graphics', 'Ms. Zama Ramzan'],
                        ['Translation of Holy Quran - III', 'Mr. Amir Nazir'],
                        ['Computer Networks', 'Mr. Bilal Pervaiz'],
                        ['Web Technologies', 'Mr. Hassan Iftikhar'],
                        ['Principles of Microeconomics', 'Mr. Adnan'],
                        ['Operating Systems', 'Dr. Shehzad Nazir'],
                        ['Cyber Security', 'Ms. Kashifa Nawab'],
                    ],
                ],
                [
                    'name' => 'BSIT 2024-2028 (Replica)',
                    'shift' => $shift3,
                    'room' => 'Room # 5',
                    'courses' => [
                        ['Computer Networks', 'Mr. M. Usman Shoukat'],
                        ['Cyber Security', 'Ms. Kashifa Nawab'],
                        ['Principles of Microeconomics', 'Mr. Adnan'],
                        ['Web Technologies', 'Mr. Hassan Iftikhar'],
                        ['Operating Systems', 'Dr. Shehzad Nazir'],
                        ['HCI & Computer Graphics', 'Ms. Zama Ramzan'],
                        ['Translation of Holy Quran - III', 'Mr. Amir Nazir'],
                    ],
                ],
                [
                    'name' => 'BSIT 2025-2029 (Regular)',
                    'shift' => $shift1,
                    'room' => 'Room # 1',
                    'courses' => [
                        ['Computer Organization & Assembly Language', 'Mr. M. Khalid'],
                        ['Database Systems', 'Mr. Hamza Mehmood'],
                        ['Elementary Linear Algebra', 'Dr. Iftikhar'],
                        ['Understanding of Quran - II', 'Mr. Iqbal Haider'],
                        ['Digital Logic Design', 'Dr. Dilshad'],
                    ],
                ],
                [
                    'name' => 'BSIT 2025-2029 (Replica)',
                    'shift' => $shift3,
                    'room' => 'Room # 2',
                    'courses' => [
                        ['Digital Logic Design', 'Dr. Dilshad'],
                        ['Understanding of Quran - II', 'Mr. Iqbal Haider'],
                        ['Elementary Linear Algebra', 'Mr. Qamar Abbas'],
                        ['Database Systems', 'Mr. Hamza Mehmood'],
                        ['Computer Organization & Assembly Language', 'Mr. M. Khalid'],
                    ],
                ],
                [
                    'name' => 'BSIT 2026-2030 (Regular)',
                    'shift' => $shift1,
                    'room' => 'Room # 4',
                    'courses' => [
                        ['Programming Fundamentals', 'Mr. Hassan Iftikhar'],
                        ['Basic Electronics', 'Mr. Hamza Mehmood'],
                        ['Calculus & Analytical Geometry', 'Ms. Reeha'],
                        ['Functional English', 'Ms. Fizza'],
                        ['Understanding of Quran - I', 'Mr. Iqbal Haider'],
                    ],
                ],
                [
                    'name' => 'BSIT 2026-2030 (Replica)',
                    'shift' => $shift3,
                    'room' => 'Room # 4',
                    'courses' => [
                        ['Programming Fundamentals', 'Ms. Noor Fatima'],
                        ['Calculus & Analytical Geometry', 'Ms. Reeha'],
                        ['Functional English', 'Ms. Fizza'],
                        ['Basic Electronics', 'Mr. Hamza Mehmood'],
                        ['Understanding of Quran - I', 'Mr. Iqbal Haider'],
                    ],
                ],
                [
                    'name' => 'BSDS 2025-2029 (Regular)',
                    'shift' => $shift1,
                    'room' => 'Room # 2',
                    'courses' => [
                        ['Understanding of Quran - II', 'Mr. Iqbal Haider'],
                        ['Elementary Linear Algebra', 'Mr. Qamar Abbas'],
                        ['Database Systems', 'Mr. Hassan Iftikhar'],
                        ['Computer Organization & Assembly Language', 'Mr. M. Usman Shoukat'],
                    ],
                ],
                [
                    'name' => 'BSDS 2025-2029 (Replica)',
                    'shift' => $shift3,
                    'room' => 'Room # 14',
                    'courses' => [
                        ['Elementary Linear Algebra', 'Dr. Iftikhar'],
                        ['Understanding of Quran - II', 'Mr. Amir Nazir'],
                        ['Database Systems', 'Ms. Zama Ramzan'],
                        ['Computer Organization & Assembly Language', 'Mr. M. Usman Shoukat'],
                    ],
                ],
                [
                    'name' => 'BSDS 2026-2030 (Regular)',
                    'shift' => $shift1,
                    'room' => 'Room # 7',
                    'courses' => [
                        ['Programming Fundamentals', 'Ms. Noor Fatima'],
                        ['Functional English', 'Ms. Fizza'],
                        ['Calculus & Analytical Geometry', 'Ms. Reeha'],
                        ['Applied Physics', 'Dr. Dilshad'],
                        ['Understanding of Quran - I', 'Mr. Amir Nazir'],
                    ],
                ],
                [
                    'name' => 'BSDS 2026-2030 (Replica)',
                    'shift' => $shift3,
                    'room' => 'Room # 7',
                    'courses' => [
                        ['Functional English', 'Ms. Fizza'],
                        ['Applied Physics', 'Dr. Dilshad'],
                        ['Calculus & Analytical Geometry', 'Dr. Bilal'],
                        ['Understanding of Quran - I', 'Mr. Iqbal Haider'],
                        ['Information & Communication Technology', 'Ms. Robina'],
                    ],
                ],
                [
                    'name' => 'BSAI 2025-2029 (Regular)',
                    'shift' => $shift1,
                    'room' => 'Room # 01',
                    'courses' => [
                        ['Elementary Linear Algebra', 'Dr. Iftikhar'],
                        ['Computer Organization & Assembly Language', 'Mr. Jawad'],
                        ['Database Systems', 'Mr. Hamza Mehmood'],
                        ['Understanding of Quran - II', 'Mr. Amir Nazir'],
                        ['Expository Writing', 'Ms. Arooj Fatima'],
                    ],
                ],
                [
                    'name' => 'BSAI 2025-2029 (Replica)',
                    'shift' => $shift3,
                    'room' => 'Room # 01',
                    'courses' => [
                        ['Database Systems', 'Mr. Hamza Mehmood'],
                        ['Computer Organization & Assembly Language', 'Mr. Jawad'],
                        ['Understanding of Quran - II', 'Mr. Iqbal Haider'],
                        ['Expository Writing', 'Ms. Arooj Fatima'],
                        ['Elementary Linear Algebra', 'Mr. Qamar Abbas'],
                    ],
                ],
                [
                    'name' => 'BSAI 2026-2030 (Regular)',
                    'shift' => $shift1,
                    'room' => 'PHYSICS LAB',
                    'courses' => [
                        ['Programming Fundamentals', 'Ms. Iqra Rafaqat'],
                        ['Understanding of Quran - I', 'Mr. Iqbal Haider'],
                        ['Calculus & Analytical Geometry', 'Dr. Bilal'],
                        ['Basic Electronics', 'Ms. Kashifa Nawab'],
                        ['Functional English', 'Mr. Amir Nazir'],
                    ],
                ],
                [
                    'name' => 'BSAI 2026-2030 (Replica)',
                    'shift' => $shift3,
                    'room' => 'PHYSICS LAB',
                    'courses' => [
                        ['Programming Fundamentals', 'Ms. Iqra Rafaqat'],
                        ['Basic Electronics', 'Ms. Zama Ramzan'],
                        ['Information & Communication Technology', 'Ms. Robina'],
                        ['Calculus & Analytical Geometry', 'Ms. Reeha'],
                        ['Understanding of Quran - I', 'Mr. Amir Nazir'],
                        ['Functional English', 'Ms. Robina'],
                    ],
                ],
            ];

            $batches = [];
            $courses = [];
            $courseCodeCounter = 100;

            foreach ($batchDefs as $bIndex => $bDef) {
                $batch = Batch::create([
                    'department_id' => $department->id,
                    'shift_id' => $bDef['shift']->id,
                    'name' => $bDef['name'],
                    'student_count' => 45,
                ]);
                $batches[] = $batch;

                // Create students for batch
                $batchStudents = [];
                for ($s = 1; $s <= 10; $s++) {
                    $student = Student::create([
                        'department_id' => $department->id,
                        'batch_id' => $batch->id,
                        'roll_number' => sprintf('BGNU-%s-%02d', preg_replace('/[^A-Za-z0-9]/', '', $batch->name), $s),
                        'name' => sprintf('Student %s #%d', $batch->name, $s),
                    ]);
                    $batchStudents[] = $student;
                }

                foreach ($bDef['courses'] as $cDef) {
                    $cName = $cDef[0];
                    $tName = $cDef[1];
                    $cCode = sprintf('CS-%03d', $courseCodeCounter++);

                    $course = Course::create([
                        'department_id' => $department->id,
                        'batch_id' => $batch->id,
                        'code' => $cCode,
                        'name' => $cName,
                        'credit_hours' => 3,
                        'type' => 'theory',
                        'duration_minutes' => 120,
                        'is_core' => true,
                    ]);
                    $courses[] = $course;

                    // Enroll batch students
                    foreach ($batchStudents as $std) {
                        Enrollment::create([
                            'student_id' => $std->id,
                            'course_id' => $course->id,
                        ]);
                    }

                    // Link course teacher
                    $matchedTeacher = $teachers[$tName] ?? null;
                    if (!$matchedTeacher) {
                        foreach ($teachers as $tCandName => $tObj) {
                            if (strcasecmp($tCandName, $tName) === 0 || str_contains(strtolower($tCandName), strtolower($tName)) || str_contains(strtolower($tName), strtolower($tCandName))) {
                                $matchedTeacher = $tObj;
                                break;
                            }
                        }
                    }
                    if ($matchedTeacher) {
                        TeacherCourse::create([
                            'teacher_id' => $matchedTeacher->id,
                            'course_id' => $course->id,
                            'is_primary' => true,
                        ]);
                    }
                }
            }

            return [
                'department' => $department,
                'session' => $session,
                'shifts' => [$shift1, $shift2, $shift3],
                'setting' => $setting,
                'rooms_count' => count($rooms),
                'batches_count' => count($batches),
                'courses_count' => count($courses),
                'teachers_count' => count($teachers),
            ];
        });
    }

    /**
     * Generate a deliberately infeasible scenario to demonstrate diagnostic explanation.
     */
    public function generateInfeasibleDemo(): array
    {
        return DB::transaction(function () {
            $this->purgeDepartment('INFEASIBLE-DEPT');

            $department = Department::create([
                'code' => 'INFEASIBLE-DEPT',
                'name' => 'Test Department (Infeasible Demo Scenario)',
            ]);

            // Infeasible: Only 1 day, 1 room, but 20 courses to schedule!
            $singleDate = Carbon::now()->addDays(14)->format('Y-m-d');
            $session = ExamSession::create([
                'department_id' => $department->id,
                'name' => 'Deliberately Infeasible Session (1 Day / 1 Room)',
                'start_date' => $singleDate,
                'end_date' => $singleDate,
                'is_active' => true,
            ]);

            $morningShift = Shift::create([
                'department_id' => $department->id,
                'name' => 'Morning',
                'start_time' => '08:00',
                'end_time' => '11:00', // only 2 slots: 08:00-09:00, 09:30-10:30
                'sort_order' => 1,
                'is_active' => true,
            ]);

            Setting::create([
                'department_id' => $department->id,
                'exam_session_id' => $session->id,
                'default_duration_minutes' => 60,
                'break_minutes' => 30,
                'shift_rule_strict' => true,
                'working_days' => Setting::defaultWorkingDays(),
                'weights' => Setting::defaultWeights(),
            ]);

            // Only 1 room!
            $room = Room::create([
                'department_id' => $department->id,
                'name' => 'Single Solitary Room',
                'capacity' => 50,
                'type' => 'classroom',
                'is_active' => true,
            ]);

            // 1 Teacher
            $teacher = Teacher::create([
                'department_id' => $department->id,
                'name' => 'Single Teacher',
                'email' => 'single@university.edu',
            ]);

            // 3 batches each with 4 courses = 12 courses, but only 2 slots × 1 room = 2 room-slots!
            for ($b = 1; $b <= 3; $b++) {
                $batch = Batch::create([
                    'department_id' => $department->id,
                    'shift_id' => $morningShift->id,
                    'name' => sprintf('Overloaded-Batch-%d', $b),
                    'student_count' => 30,
                ]);

                for ($c = 1; $c <= 4; $c++) {
                    $course = Course::create([
                        'department_id' => $department->id,
                        'batch_id' => $batch->id,
                        'code' => sprintf('INF-%d-%d', $b, $c),
                        'name' => sprintf('Course %d of Batch %d', $c, $b),
                        'credit_hours' => 3,
                    ]);

                    TeacherCourse::create([
                        'teacher_id' => $teacher->id,
                        'course_id' => $course->id,
                        'is_primary' => true,
                    ]);
                }
            }

            return [
                'department' => $department,
                'session' => $session,
                'total_courses' => 12,
                'available_room_slots' => 2,
            ];
        });
    }

    /**
     * Completely and safely purge existing department records before re-seeding.
     */
    private function purgeDepartment(string $departmentCode): void
    {
        $dept = Department::where('code', $departmentCode)->first();
        if (! $dept) {
            return;
        }

        Schema::disableForeignKeyConstraints();

        // 1. Datesheet cascade
        $datesheetIds = Datesheet::where('department_id', $dept->id)->pluck('id')->toArray();
        if (! empty($datesheetIds)) {
            $examIds = DB::table('exams')->whereIn('datesheet_id', $datesheetIds)->pluck('id')->toArray();
            if (! empty($examIds)) {
                DB::table('exam_invigilators')->whereIn('exam_id', $examIds)->delete();
                DB::table('exam_room_assignments')->whereIn('exam_id', $examIds)->delete();
            }
            DB::table('exams')->whereIn('datesheet_id', $datesheetIds)->delete();
            Datesheet::whereIn('id', $datesheetIds)->delete();
        }

        // 2. Courses cascade
        $courseIds = Course::where('department_id', $dept->id)->pluck('id')->toArray();
        if (! empty($courseIds)) {
            DB::table('enrollments')->whereIn('course_id', $courseIds)->delete();
            DB::table('teacher_courses')->whereIn('course_id', $courseIds)->delete();
            Course::whereIn('id', $courseIds)->delete();
        }

        // 3. Batches & Students
        Student::where('department_id', $dept->id)->delete();
        Batch::where('department_id', $dept->id)->delete();

        // 4. Teachers
        $teacherIds = Teacher::where('department_id', $dept->id)->pluck('id')->toArray();
        if (! empty($teacherIds)) {
            DB::table('teacher_availabilities')->whereIn('teacher_id', $teacherIds)->delete();
            Teacher::whereIn('id', $teacherIds)->delete();
        }

        // 5. Rooms
        Room::where('department_id', $dept->id)->delete();

        // 6. Settings, Holidays & Sessions
        Setting::where('department_id', $dept->id)->delete();
        $sessionIds = ExamSession::where('department_id', $dept->id)->pluck('id')->toArray();
        if (! empty($sessionIds)) {
            Holiday::whereIn('exam_session_id', $sessionIds)->delete();
            ExamSession::whereIn('id', $sessionIds)->delete();
        }

        // 7. Shifts & Department
        Shift::where('department_id', $dept->id)->delete();
        $dept->delete();

        Schema::enableForeignKeyConstraints();
    }
}
