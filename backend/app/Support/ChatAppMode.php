<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Http\Request;

final class ChatAppMode
{
    public const SESSION_KEY = 'chat_app_mode';

    public const SOURCE_QUERY = 'chat-app';

    public const USER_AGENT_MARKER = 'eTrainingChatApp';

    public static function activate(Request $request): void
    {
        $request->session()->put(self::SESSION_KEY, true);
    }

    public static function isActive(Request $request): bool
    {
        if ($request->session()->get(self::SESSION_KEY) === true) {
            return true;
        }

        return self::shouldActivate($request);
    }

    public static function shouldActivate(Request $request): bool
    {
        $source = (string) $request->query('source', '');
        if ($source === self::SOURCE_QUERY || $source === 'chat-app') {
            return true;
        }

        $userAgent = (string) $request->userAgent();

        return $userAgent !== '' && str_contains($userAgent, self::USER_AGENT_MARKER);
    }

    public static function homePath(): string
    {
        return '/back/chat?source='.self::SOURCE_QUERY;
    }

    public static function isAllowedPath(Request $request): bool
    {
        $path = trim($request->path(), '/');

        if ($path === '') {
            return false;
        }

        $allowedExact = [
            'login',
            'logout',
            'caller',
        ];

        if (in_array($path, $allowedExact, true)) {
            return true;
        }

        $allowedPrefixes = [
            'login/',
            'back/chat',
            'caller/',
            'sanctum/',
            'broadcasting/',
            'storage/',
            'livewire/',
            'nova-api/',
        ];

        foreach ($allowedPrefixes as $prefix) {
            if ($path === rtrim($prefix, '/') || str_starts_with($path, $prefix)) {
                return true;
            }
        }

        // Public static assets (favicon, PWA, compiled JS/CSS)
        if (preg_match('#^(css|js|img|fonts|vendor|images)/#', $path) === 1) {
            return true;
        }

        if (preg_match('#\.(js|css|map|png|jpe?g|gif|svg|ico|webp|woff2?|ttf|webmanifest)$#i', $path) === 1) {
            return true;
        }

        return false;
    }
}
