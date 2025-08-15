<?php

namespace ProcessMaker\TelegramPlugin\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use ProcessMaker\Models\User;
use ProcessMaker\TelegramPlugin\Services\TelegramService;

class TelegramAuthController
{
    protected $telegramService;

    public function __construct(TelegramService $telegramService)
    {
        $this->telegramService = $telegramService;
    }

    public function connect()
    {
        $user = Auth::user();
        $token = Str::random(40);
        
        $user->update([
            'telegram_auth_token' => $token
        ]);
        
        $botUsername = config('telegram.bot_username');
        
        return view('telegram-plugin::profile.telegram', [
            'botUsername' => $botUsername,
            'authToken' => $token
        ]);
    }

    public function disconnect()
    {
        Auth::user()->update([
            'telegram_chat_id' => null,
            'telegram_verified_at' => null
        ]);
        
        return redirect()->back()->with('status', 'Telegram disconnected!');
    }
}