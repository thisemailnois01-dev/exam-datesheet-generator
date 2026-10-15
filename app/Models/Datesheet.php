<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class Datesheet extends Model
{
    use HasFactory, LogsActivity;

    protected $fillable = [
        'exam_session_id',
        'department_id',
        'name',
        'version',
        'status',
        'soft_score',
        'metrics',
        'created_by',
    ];

    protected $casts = [
        'version' => 'integer',
        'soft_score' => 'integer',
        'metrics' => 'array',
    ];

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['name', 'version', 'status', 'soft_score'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    public function examSession(): BelongsTo
    {
        return $this->belongsTo(ExamSession::class);
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function exams(): HasMany
    {
        return $this->hasMany(Exam::class);
    }
}
