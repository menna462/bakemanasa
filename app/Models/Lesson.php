<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Lesson extends Model
{
    use HasFactory;

    protected $fillable = [
        'unit_id',
        'grade_level_id',
        'order_number',
        'title',
        'description',
        'duration',
        'duration_seconds',
        'size',
        'views_count',
        'status',
        'video_source',
        'video_url',
        'video_provider',
        'has_pdf',
        'pdf_title',
        'pdf_url',
        'has_quiz',
        'quiz_title',
        'quiz_questions_count',
        'quiz_duration',
        'is_free',
    ];

    protected function casts(): array
    {
        return [
            'order_number' => 'integer',
            'duration_seconds' => 'integer',
            'views_count' => 'integer',
            'has_pdf' => 'boolean',
            'has_quiz' => 'boolean',
            'quiz_questions_count' => 'integer',
            'is_free' => 'boolean',
        ];
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }

    public function gradeLevel(): BelongsTo
    {
        return $this->belongsTo(GradeLevel::class);
    }

    public function activationCodes(): HasMany
    {
        return $this->hasMany(ActivationCode::class);
    }

    public function studentProgress(): HasMany
    {
        return $this->hasMany(StudentProgress::class);
    }

    public function notes(): HasMany
    {
        return $this->hasMany(Note::class);
    }
}
