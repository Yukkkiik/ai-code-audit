<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

/**
 * Akun pengguna yang login melalui Google OAuth (tanpa password).
 */
#[Fillable(['google_id', 'name', 'email', 'avatar_url', 'last_login_at'])]
#[Hidden(['google_id'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'last_login_at' => 'datetime',
        ];
    }

    /**
     * @return HasMany<AuditScan, $this>
     */
    public function auditScans(): HasMany
    {
        return $this->hasMany(AuditScan::class);
    }

    /**
     * @return HasMany<AiUsageLog, $this>
     */
    public function aiUsageLogs(): HasMany
    {
        return $this->hasMany(AiUsageLog::class);
    }

    /**
     * @return HasMany<AiRefactoring, $this>
     */
    public function requestedRefactorings(): HasMany
    {
        return $this->hasMany(AiRefactoring::class, 'requested_by_user_id');
    }
}
