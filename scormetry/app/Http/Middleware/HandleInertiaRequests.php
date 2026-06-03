<?php

namespace App\Http\Middleware;

use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        // Google login is only usable when real credentials are set AND the
        // socialite package is installed. Placeholder values from .env.example
        // are treated as "not configured".
        $googleClientId = config('services.google.client_id', '');
        $googleEnabled  = ! empty($googleClientId)
            && $googleClientId !== 'your-google-client-id'
            && class_exists(\Laravel\Socialite\Facades\Socialite::class);

        return [
            ...parent::share($request),
            'name' => config('app.name'),
            'auth' => [
                'user' => $request->user(),
                'actingRole' => $request->user()?->isAdmin() && config('app.role_view_enabled')
                    ? $request->session()->get('acting_role')
                    : null,
            ],
            'roleViewEnabled'    => config('app.role_view_enabled'),
            'googleLoginEnabled' => $googleEnabled,
            'sidebarOpen' => ! $request->hasCookie('sidebar_state') || $request->cookie('sidebar_state') === 'true',
            'flash' => [
                'success' => $request->session()->get('success'),
                'error' => $request->session()->get('error'),
            ],
        ];
    }
}
