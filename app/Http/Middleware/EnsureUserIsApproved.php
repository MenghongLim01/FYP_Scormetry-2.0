<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserIsApproved
{
    /** @param  Closure(Request): (Response)  $next */
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->user() && $request->user()->isPending()) {
            return redirect()->route('pending-approval');
        }

        if ($request->user() && $request->user()->isBlocked()) {
            abort(403, 'Your account has been blocked.');
        }

        return $next($request);
    }
}
