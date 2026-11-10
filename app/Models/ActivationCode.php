<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ActivationCode extends Model
{
    use HasFactory;

    protected $fillable = [
        'code',
        'group_name',
        'grade_level_id',
        'lesson_id',
        'created_by_admin_id',
        'validity_days',
        'status',
        'used_by',
        'student_phone',
        'device_fingerprint',
        'activated_at',
        'expires_at',
    ];

    protected function casts(): array
    {
        return [
            'validity_days' => 'integer',
            'activated_at' => 'datetime',
            'expires_at' => 'datetime',
        ];
    }

    public function lesson(): BelongsTo
    {
        return $this->belongsTo(Lesson::class);
    }

    public function gradeLevel(): BelongsTo
    {
        return $this->belongsTo(GradeLevel::class);
    }

    public function createdByAdmin(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'created_by_admin_id');
    }

    public function studentProgress(): HasMany
    {
        return $this->hasMany(StudentProgress::class);
    }

    public function notes(): HasMany
    {
        return $this->hasMany(Note::class);
    }

    public function scopeActiveAvailable(Builder $query): Builder
    {
        return $query->where('status', 'new');
    }

    public function scopeUsedActive(Builder $query): Builder
    {
        return $query->where('status', 'used')
            ->where(function ($q) {
                $q->whereNull('expires_at')
                  ->orWhere('expires_at', '>', Carbon::now());
            });
    }

    public function scopeExpired(Builder $query): Builder
    {
        return $query->where('status', 'expired')
            ->orWhere(function ($q) {
                $q->whereNotNull('expires_at')
                  ->where('expires_at', '<=', Carbon::now());
            });
    }
}
