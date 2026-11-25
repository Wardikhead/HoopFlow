<?php

/** Eloquent model for User. */

namespace App\Models;

use App\Notifications\ResetPasswordNotification;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable(['first_name', 'last_name', 'email', 'password', 'phone', 'status'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * Casts.
     *
     * @return array<mixed> The result.
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    /**
     * Get name attribute.
     *
     * @return string The result.
     */
    public function getNameAttribute(): string
    {
        return trim($this->first_name.' '.$this->last_name);
    }

    /**
     * Send password reset notification.
     *
     * @param  mixed  $token  The token.
     * @return void Nothing.
     */
    public function sendPasswordResetNotification(#[\SensitiveParameter] $token): void
    {
        $this->notify(new ResetPasswordNotification($token));
    }

    /**
     * @return BelongsToMany<Role, $this>
     */
    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class, 'user_roles', 'user_id', 'role_id');
    }

    /**
     * Has role.
     *
     * @param  string  $key  The key.
     * @return bool The result.
     */
    public function hasRole(string $key): bool
    {
        return $this->roles()->where('key', $key)->exists();
    }

    /**
     * Has any role.
     *
     * @param  string...  $keys  The keys.
     * @return bool The result.
     */
    public function hasAnyRole(string ...$keys): bool
    {
        return $this->roles()->whereIn('key', $keys)->exists();
    }

    /**
     * Is active.
     *
     * @return bool The result.
     */
    public function isActive(): bool
    {
        return strtolower(trim((string) $this->status)) === 'active';
    }
}
