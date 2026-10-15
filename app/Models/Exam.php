<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class Exam extends Model
{
    use HasFactory, LogsActivity;

    protected $fillable = [
        'datesheet_id',
        'course_id',
        'batch_id',
        'exam_date',
        'slot_index',
        'shift_id',
        'start_time',
        'end_time',
        'is_locked',
    ];

    protected $casts = [
        'exam_date' => 'date',
        'slot_index' => 'integer',
        'is_locked' => 'boolean',
    ];

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['exam_date', 'slot_index', 'shift_id', 'is_locked'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    public function datesheet(): BelongsTo
    {
        return $this->belongsTo(Datesheet::class);
    }

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    public function batch(): BelongsTo
    {
        return $this->belongsTo(Batch::class);
    }

    public function shift(): BelongsTo
    {
        return $this->belongsTo(Shift::class);
    }

    public function roomAssignments(): HasMany
    {
        return $this->hasMany(ExamRoomAssignment::class);
    }

    public function rooms(): BelongsToMany
    {
        return $this->belongsToMany(Room::class, 'exam_room_assignments')
            ->withPivot('student_count')
            ->withTimestamps();
    }

    public function invigilatorAssignments(): HasMany
    {
        return $this->hasMany(ExamInvigilator::class);
    }

    public function invigilators(): BelongsToMany
    {
        return $this->belongsToMany(Teacher::class, 'exam_invigilators')
            ->withPivot('room_id')
            ->withTimestamps();
    }
}
