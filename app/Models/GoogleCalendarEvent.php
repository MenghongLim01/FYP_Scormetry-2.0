<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'user_id',
    'defense_attempt_id',
    'google_event_id',
    'status',
    'last_synced_at',
])]
class GoogleCalendarEvent extends Model
{
    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'last_synced_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<DefenseAttempt, $this> */
    public function attempt(): BelongsTo
    {
        return $this->belongsTo(DefenseAttempt::class, 'defense_attempt_id');
    }
}
