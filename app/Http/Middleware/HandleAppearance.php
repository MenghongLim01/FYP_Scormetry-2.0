<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\View;
use Symfony\Component\HttpFoundation\Response;

class HandleAppearance
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Scormetry defaults to light mode and never follows the OS theme.
        // Only an explicit 'dark' preference is honoured; everything else
        // (no cookie, legacy 'system', or invalid values) falls back to light.
        $appearance = $request->cookie('appearance') === 'dark' ? 'dark' : 'light';

        View::share('appearance', $appearance);

        return $next($request);
    }
}
