<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Fortify\TwoFactorAuthenticatable;

#[Fillable(['name', 'email', 'password', 'role', 'status', 'google_id', 'is_blocked', 'otp_login_enabled'])]
#[Hidden(['password', 'two_factor_secret', 'two_factor_recovery_codes', 'remember_token'])]
class User extends Authenticatable implements MustVerifyEmail
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable, TwoFactorAuthenticatable;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'two_factor_confirmed_at' => 'datetime',
            'is_blocked' => 'boolean',
            'otp_login_enabled' => 'boolean',
        ];
    }

    public function isAdmin(): bool
    {
        return $this->role === 'admin';
    }

    public function isTeacher(): bool
    {
        return $this->role === 'teacher';
    }

    public function isStudent(): bool
    {
        return $this->role === 'student';
    }

    public function isApproved(): bool
    {
        return $this->status === 'approved';
    }

    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    public function isBlocked(): bool
    {
        return $this->is_blocked === true || $this->status === 'blocked';
    }

    /** @return HasMany<Subject, $this> */
    public function teachingSubjects(): HasMany
    {
        return $this->hasMany(Subject::class, 'teacher_id');
    }

    /** @return BelongsToMany<Subject, $this> */
    public function enrolledSubjects(): BelongsToMany
    {
        return $this->belongsToMany(Subject::class, 'subject_members')
            ->withPivotValue('role', 'student')
            ->wherePivot('status', 'approved')
            ->withPivot(['status', 'role_label', 'created_at'])
            ->withTimestamps();
    }

    /** @return BelongsToMany<Subject, $this> */
    public function reviewingSubjects(): BelongsToMany
    {
        return $this->belongsToMany(Subject::class, 'subject_members')
            ->wherePivot('role', '!=', 'student')
            ->wherePivot('status', 'approved')
            ->withPivot(['status', 'role_label', 'created_at'])
            ->withPivot('role')
            ->withTimestamps();
    }

    /** @return BelongsToMany<Team, $this> */
    public function teams(): BelongsToMany
    {
        return $this->belongsToMany(Team::class, 'team_members')->withTimestamps();
    }

    /** @return HasMany<Review, $this> */
    public function reviews(): HasMany
    {
        return $this->hasMany(Review::class, 'reviewer_id');
    }

    /** @return BelongsToMany<Subject, $this> */
    public function pinnedSubjects(): BelongsToMany
    {
        return $this->belongsToMany(Subject::class, 'subject_user_pins')->withTimestamps();
    }

    /** @return HasMany<SubjectUserOrder, $this> */
    public function subjectOrders(): HasMany
    {
        return $this->hasMany(SubjectUserOrder::class);
    }
}
