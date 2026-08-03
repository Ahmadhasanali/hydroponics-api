<?php

namespace App\Models;

use App\Notifications\ResetPasswordNotification;
use App\Notifications\VerifyEmailNotification;
use Illuminate\Auth\MustVerifyEmail;
use Illuminate\Contracts\Auth\MustVerifyEmail as MustVerifyEmailContract;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable(['name', 'email', 'password'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable implements MustVerifyEmailContract
{
    /** @use HasFactory<UserFactory> */
    use HasFactory;

    use MustVerifyEmail;
    use Notifiable;
    use SoftDeletes;

    public function farms(): BelongsToMany
    {
        return $this->belongsToMany(Farm::class, 'farm_users')
            ->using(Farm\FarmUser::class)
            ->withPivot('role')
            ->withTimestamps();
    }

    /**
     * @return HasMany<Farm,User>
     */
    public function ownedFarms(): HasMany
    {
        return $this->hasMany(Farm::class, 'created_by');
    }

    /**
     * @return MorphMany<PushSubscription,User>
     */
    public function pushSubscriptions(): MorphMany
    {
        return $this->morphMany(PushSubscription::class, 'subscribable');
    }

    /**
     * Determine if the user is an administrator.
     */
    public function isAdmin(): bool
    {
        return (bool) $this->is_admin;
    }

    /**
     * Determine if the user is a super administrator.
     */
    public function isSuperAdmin(): bool
    {
        return $this->isAdmin();
    }

    /**
     * Send the password reset notification.
     */
    public function sendPasswordResetNotification($token): void
    {
        $this->notify(new ResetPasswordNotification($token));
    }

    /**
     * Send the email verification notification.
     */
    public function sendEmailVerificationNotification(): void
    {
        $this->notify(new VerifyEmailNotification);
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_admin' => 'boolean',
            'password' => 'hashed',
        ];
    }
}
