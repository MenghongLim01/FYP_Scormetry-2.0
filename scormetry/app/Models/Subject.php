<?php

namespace App\Models;

use Database\Factories\SubjectFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Fillable(['title', 'description', 'teacher_id', 'passing_score', 'join_code', 'reviewer_code', 'require_approval'])]
class Subject extends Model
{
    /** @use HasFactory<SubjectFactory> */
    use HasFactory;

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'require_approval' => 'boolean',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function teacher(): BelongsTo
    {
        return $this->belongsTo(User::class, 'teacher_id');
    }

    /** @return BelongsToMany<User, $this> */
    public function students(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'subject_members')
            ->withPivotValue('role', 'student')
            ->wherePivot('status', 'approved')
            ->withPivot(['status', 'role_label', 'created_at'])
            ->withTimestamps();
    }

    /** @return BelongsToMany<User, $this> */
    public function reviewers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'subject_members')
            ->wherePivot('role', '!=', 'student')
            ->wherePivot('status', 'approved')
            ->withPivot(['role', 'status', 'role_label', 'created_at'])
            ->withPivot('role')
            ->withTimestamps();
    }

    /** @return HasMany<SubjectMember, $this> */
    public function memberships(): HasMany
    {
        return $this->hasMany(SubjectMember::class);
    }

    /** @return HasMany<SubjectMember, $this> */
    public function pendingMembers(): HasMany
    {
        return $this->memberships()->where('status', 'pending');
    }

    /** @return HasOne<Rubric, $this> */
    public function rubric(): HasOne
    {
        return $this->hasOne(Rubric::class);
    }

    /** @return HasMany<Rubric, $this> */
    public function rubrics(): HasMany
    {
        return $this->hasMany(Rubric::class);
    }

    /** @return HasMany<DefensePeriod, $this> */
    public function defensePeriods(): HasMany
    {
        return $this->hasMany(DefensePeriod::class)->orderBy('sequence');
    }

    /** @return HasMany<Paper, $this> */
    public function papers(): HasMany
    {
        return $this->hasMany(Paper::class);
    }

    /** @return HasMany<Team, $this> */
    public function teams(): HasMany
    {
        return $this->hasMany(Team::class);
    }

    /** @return HasMany<SubjectInvitation, $this> */
    public function pendingInvitations(): HasMany
    {
        return $this->hasMany(SubjectInvitation::class)->whereNull('accepted_at');
    }
}
