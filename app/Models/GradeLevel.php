<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;

class GradeLevel extends Model
{
    use HasFactory;

    protected $fillable = [
        'title',
        'subtitle',
        'order_number',
        'is_active',
        'status_text',
        'status_type',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'order_number' => 'integer',
        ];
    }

    public function units(): HasMany
    {
        return $this->hasMany(Unit::class)->orderBy('unit_number');
    }

    public function lessons(): HasMany
    {
        return $this->hasMany(Lesson::class)->orderBy('order_number');
    }

    public function activationCodes(): HasMany
    {
        return $this->hasMany(ActivationCode::class);
    }
}
