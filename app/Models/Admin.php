<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class Admin extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    protected $fillable = [
        'full_name',
        'short_title',
        'initials',
        'email',
        'password',
        'role',
        'role_id',
        'status',
        'is_active',
        'avatar_color',
        'is_founder',
        'last_activity',
        'last_activity_type',
        'last_login_at',
        'last_login_ip',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'is_active' => 'boolean',
            'is_founder' => 'boolean',
            'last_login_at' => 'datetime',
        ];
    }

    public function roleRef(): BelongsTo
    {
        return $this->belongsTo(Role::class, 'role_id');
    }

    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class, 'admin_roles');
    }

    public function createdActivationCodes(): HasMany
    {
        return $this->hasMany(ActivationCode::class, 'created_by_admin_id');
    }

    public function hasPermission(string $permissionName): bool
    {
        if ($this->role === 'super_admin' || $this->is_founder) {
            return true;
        }

        if ($this->roleRef && $this->roleRef->permissions()->where('name', $permissionName)->exists()) {
            return true;
        }

        return false;
    }
}
