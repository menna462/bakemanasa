<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Note extends Model
{
    use HasFactory;

    protected $fillable = [
        'lesson_id',
        'activation_code_id',
        'student_name',
        'time_formatted',
        'time_seconds',
        'content',
    ];

    protected function casts(): array
    {
        return [
            'time_seconds' => 'integer',
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
