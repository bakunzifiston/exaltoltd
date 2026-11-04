<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use App\Exceptions\InsufficientStockException;
use App\Http\Middleware\EnsureAdminSession;
use Illuminate\Http\Exceptions\PostTooLargeException;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->alias([
            'admin.session' => EnsureAdminSession::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        // Stock shortfalls are a business condition, not a crash — never show a 500.
        $exceptions->render(function (InsufficientStockException $e, Request $request) {
            if ($request->expectsJson()) {
                return response()->json(['message' => $e->getMessage()], 422);
            }

            return back()->withInput()->withErrors(['stock' => $e->getMessage()]);
        });

        $exceptions->render(function (PostTooLargeException $e, Request $request): Response {
            if ($request->expectsJson()) {
                return response()->json([
                    'message' => 'Upload failed: request body is too large. Reduce file size or increase PHP post_max_size/upload_max_filesize.',
                ], 413);
            }

            return back()->withErrors([
                'video_files' => 'Upload failed: selected files are too large for current server limits. Increase PHP post_max_size/upload_max_filesize or upload smaller files.',
            ]);
        });
    })->create();
