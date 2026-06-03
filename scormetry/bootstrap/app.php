<?php

use App\Console\Commands\SendDefenseReminders;
use App\Http\Middleware\EnsureUserIsApproved;
use App\Http\Middleware\EnsureUserRole;
use App\Http\Middleware\HandleAppearance;
use App\Http\Middleware\HandleInertiaRequests;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Exceptions\PostTooLargeException;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withCommands([
        SendDefenseReminders::class,
    ])
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->encryptCookies(except: ['appearance', 'sidebar_state']);

        $middleware->web(append: [
            HandleAppearance::class,
            HandleInertiaRequests::class,
            AddLinkHeadersForPreloadedAssets::class,
        ]);

        $middleware->alias([
            'role' => EnsureUserRole::class,
            'approved' => EnsureUserIsApproved::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Return a friendly redirect instead of a raw PHP 413 page when the
        // uploaded file exceeds PHP's post_max_size / upload_max_filesize.
        $exceptions->render(function (PostTooLargeException $e, \Illuminate\Http\Request $request) {
            $maxMb = (int) ini_get('post_max_size');
            return back()
                ->withErrors(['file' => "The uploaded file is too large. The server limit is {$maxMb} MB. Please reduce your file size and try again."]);
        });
    })->create();
