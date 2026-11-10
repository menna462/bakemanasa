<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StudentProgress extends Model
{
    use HasFactory;

    protected $table = 'student_progress';

    protected $fillable = [
        'activation_code_id',
        'lesson_id',
        'student_name',
        'student_phone',
        'grade',
        'watch_progress',
        'current_seconds',
        'total_watch_minutes',
        'completed',
        'quiz_score',
        'replay_count',
        'device',
        'last_watched_at',
    ];

    protected function casts(): array
    {
        return [
            'watch_progress' => 'integer',
            'current_seconds' => 'integer',
            'total_watch_minutes' => 'integer',
            'completed' => 'boolean',
            'quiz_score' => 'integer',
            'replay_count' => 'integer',
            'last_watched_at' => 'datetime',
        ];
    }

    public function lesson(): BelongsTo
    {
        return $this->belongsTo(Lesson::class);
    }

    public function activationCode(): BelongsTo
    {
        return $this->belongsTo(ActivationCode::class);
    }
}
