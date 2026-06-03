<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ActingRoleController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        abort_unless(config('app.role_view_enabled'), 403);

        $validated = $request->validate([
            'role' => ['required', 'in:admin,teacher,student'],
        ]);

        if ($validated['role'] === $request->user()->role) {
            $request->session()->forget('acting_role');
        } else {
            $request->session()->put('acting_role', $validated['role']);
        }

        return back();
    }

    public function destroy(Request $request): RedirectResponse
    {
        abort_unless(config('app.role_view_enabled'), 403);

        $request->session()->forget('acting_role');

        return back();
    }
}
