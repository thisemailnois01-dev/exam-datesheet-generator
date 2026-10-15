<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Setting extends Model
{
    use HasFactory;

    protected $fillable = [
        'department_id',
        'exam_session_id',
        'default_duration_minutes',
        'break_minutes',
        'shift_rule_strict',
        'working_days',
        'weights',
        'branding',
    ];

    protected $casts = [
        'default_duration_minutes' => 'integer',
        'break_minutes' => 'integer',
        'shift_rule_strict' => 'boolean',
        'working_days' => 'array',
        'weights' => 'array',
        'branding' => 'array',
    ];

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function examSession(): BelongsTo
    {
        return $this->belongsTo(ExamSession::class);
    }

    /**
     * Sane default soft constraint weights
     */
    public static function defaultWeights(): array
    {
        return [
            'consecutive_exam_penalty' => 50,
            'same_day_exam_penalty' => 100,
            'core_course_late_penalty' => 20,
            'daily_workload_variance' => 15,
            'soft_shift_violation' => 80,
            'teacher_duty_variance' => 10,
            'compact_schedule_reward' => 25,
        ];
    }

    /**
     * Default working days
     */
    public static function defaultWorkingDays(): array
    {
        return ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
    }

    /**
     * Default branding and document header metadata
     */
    public static function defaultBranding(?string $deptName = null): array
    {
        return [
            'university_name' => 'Baba Guru Nanak University, Nankana Sahib',
            'department_name' => $deptName ?? 'Department of Computer Science & IT',
            'exam_title' => 'DATE SHEET FOR FINAL TERM EXAMINATION',
            'session_term' => 'SPRING 2026',
            'ref_no' => 'BGNU/CS&IT/2026/46',
            'issue_date' => '03.06.2026',
            'chairperson_name' => 'Dr. Muhammad Usman Younus',
            'chairperson_title' => 'Chairperson',
            'left_logo' => 'images/dept_logo.png',
            'right_logo' => 'images/bgnu_logo.png',
            'signature_img' => 'images/signature.png',
            'cc_list' => "1. Controller of Examination\n2. Director Academics\n3. All Chairs of Concerned Departments\n4. All Concerned Faculty\n5. Office Copy",
        ];
    }
}
