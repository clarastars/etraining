<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Support\ChatAppMode;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class ChatAppModeMiddleware
{
    /**
     * Keep the Capacitor chat APK (and chat-app source sessions) on chat/login only.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (ChatAppMode::shouldActivate($request)) {
            ChatAppMode::activate($request);
        }

        if (! ChatAppMode::isActive($request)) {
            return $next($request);
        }

        if (Auth::check() && ! ChatAppMode::isAllowedPath($request)) {
            return redirect(ChatAppMode::homePath());
        }

        return $next($request);
    }
}
