<?php

namespace App\Support;

use App\Models\User;
use App\Notifications\GenericNotification;
use Illuminate\Support\Collection;

/**
 * Thin helper around in-app notifications so call sites stay one line.
 */
class Notify
{
    public static function send(?User $user, string $title, string $body, ?string $url = null, string $category = 'system'): void
    {
        if (! $user) {
            return;
        }

        $user->notify(new GenericNotification($title, $body, $url, $category));
    }

    /**
     * @param  Collection<int, User>|array<int, User>  $users
     */
    public static function many(iterable $users, string $title, string $body, ?string $url = null, string $category = 'system'): void
    {
        $seen = [];
        foreach ($users as $user) {
            if (! $user || in_array($user->id, $seen, true)) {
                continue;
            }
            $seen[] = $user->id;
            $user->notify(new GenericNotification($title, $body, $url, $category));
        }
    }
}
