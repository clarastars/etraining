<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Support\ChatAppMode;
use Illuminate\Http\Request;
use Tests\TestCase;

class ChatAppModeTest extends TestCase
{
    public function test_detects_source_query_and_user_agent(): void
    {
        $byQuery = Request::create('/back/chat', 'GET', ['source' => 'chat-app']);
        $this->assertTrue(ChatAppMode::shouldActivate($byQuery));

        $byUa = Request::create('/back/chat', 'GET');
        $byUa->headers->set('User-Agent', 'Mozilla/5.0 eTrainingChatApp/1.0');
        $this->assertTrue(ChatAppMode::shouldActivate($byUa));

        $normal = Request::create('/dashboard', 'GET');
        $normal->headers->set('User-Agent', 'Mozilla/5.0');
        $this->assertFalse(ChatAppMode::shouldActivate($normal));
    }

    public function test_allows_chat_and_login_paths_only(): void
    {
        $this->assertTrue(ChatAppMode::isAllowedPath(Request::create('/back/chat', 'GET')));
        $this->assertTrue(ChatAppMode::isAllowedPath(Request::create('/back/chat/conversations', 'GET')));
        $this->assertTrue(ChatAppMode::isAllowedPath(Request::create('/login', 'GET')));
        $this->assertTrue(ChatAppMode::isAllowedPath(Request::create('/caller/dial', 'POST')));
        $this->assertFalse(ChatAppMode::isAllowedPath(Request::create('/dashboard', 'GET')));
        $this->assertFalse(ChatAppMode::isAllowedPath(Request::create('/back/companies', 'GET')));
    }
}
