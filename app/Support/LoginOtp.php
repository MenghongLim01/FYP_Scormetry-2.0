<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;

/**
 * Generates, stores, and verifies the one-time email login code. The code is
 * kept in the cache (hashed) with a short TTL — no extra DB table needed.
 */
class LoginOtp
{
    private const TTL_SECONDS = 600;        // 10 minutes
    private const MAX_ATTEMPTS = 5;

    public static function generate(User $user): string
    {
        $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);

        Cache::put(self::key($user), [
            'hash' => Hash::make($code),
            'attempts' => 0,
        ], self::TTL_SECONDS);

        return $code;
    }

    public static function verify(User $user, string $code): bool
    {
        $record = Cache::get(self::key($user));
        if (! $record) {
            return false;
        }

        if (($record['attempts'] ?? 0) >= self::MAX_ATTEMPTS) {
            Cache::forget(self::key($user));

            return false;
        }

        if (Hash::check(trim($code), $record['hash'])) {
            Cache::forget(self::key($user));

            return true;
        }

        $record['attempts'] = ($record['attempts'] ?? 0) + 1;
        Cache::put(self::key($user), $record, self::TTL_SECONDS);

        return false;
    }

    public static function clear(User $user): void
    {
        Cache::forget(self::key($user));
    }

    private static function key(User $user): string
    {
        return 'login-otp:'.$user->id;
    }
}
