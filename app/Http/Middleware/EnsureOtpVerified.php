<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureOtpVerified
{
    /**
     * Gate the app behind the email-OTP challenge whenever a session is flagged
     * as pending verification. The challenge routes + logout stay reachable.
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (auth()->check() && $request->session()->get('otp.pending')) {
            $allowed = $request->routeIs('otp.*')
                || $request->routeIs('logout')
                || $request->is('logout');

            if (! $allowed) {
                return redirect()->route('otp.challenge');
            }
        }

        return $next($request);
    }
}
