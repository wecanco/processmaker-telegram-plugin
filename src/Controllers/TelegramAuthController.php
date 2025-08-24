<?php

namespace ProcessMaker\TelegramPlugin\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use ProcessMaker\Http\Controllers\Controller;
use ProcessMaker\Models\User;
use ProcessMaker\TelegramPlugin\Services\TelegramService;

class TelegramAuthController extends Controller
{
    protected TelegramService $telegramService;

    public function __construct(TelegramService $telegramService)
    {
        $this->telegramService = $telegramService;
    }

    /**
     * Show Telegram integration page
     */
    public function show(): View
    {
        /** @var User $user */
        $user = Auth::user();

//        $user->update([
//            'telegram_chat_id' => 282120410,
//            'telegram_verified_at' => now(),
//            'telegram_auth_token' => null,
//            'telegram_username' => 'wecanco' ?? null,
//            'telegram_first_name' => 'WeCanCo',
//        ]);
//        dd('done');

        $data = [
            'user' => $user,
            'isConnected' => !empty($user->telegram_chat_id),
            'connectedAt' => $user->telegram_verified_at,
//            'botUsername' => $this->getBotUsername(),
            'botUsername' => config('telegram.bot_username'),
        ];

        // Generate auth token if not connected
        if (!$data['isConnected']) {
            $data['authToken'] = $this->generateAuthToken($user);
        }

        return view('telegram-plugin::profile.telegram', $data);
    }

    /**
     * Generate connection token
     */
    public function connect(Request $request): RedirectResponse
    {
        /** @var User $user */
        $user = Auth::user();

        if ($user->telegram_chat_id) {
            return redirect()
                ->route('telegram.show')
                ->with('warning', 'Telegram is already connected to your account.');
        }

        $token = $this->generateAuthToken($user);

        Log::info('Telegram connection token generated', [
            'user_id' => $user->id,
            'username' => $user->username,
        ]);

        return redirect()
            ->route('telegram.show')
            ->with('success', 'New connection token generated. Please use it in Telegram bot.');
    }

    /**
     * Disconnect Telegram account
     */
    public function disconnect(Request $request): RedirectResponse
    {
        /** @var User $user */
        $user = Auth::user();

        if (!$user->telegram_chat_id) {
            return redirect()
                ->route('telegram.show')
                ->with('warning', 'Telegram is not connected to your account.');
        }

        // Clear Telegram data
        $user->update([
            'telegram_chat_id' => null,
            'telegram_auth_token' => null,
            'telegram_verified_at' => null,
            'telegram_username' => null,
            'telegram_first_name' => null,
        ]);

        Log::info('Telegram account disconnected', [
            'user_id' => $user->id,
            'username' => $user->username,
        ]);

        return redirect()
            ->route('telegram.show')
            ->with('success', 'Telegram has been disconnected from your account.');
    }

    /**
     * Regenerate authentication token
     */
    public function regenerateToken(Request $request): RedirectResponse
    {
        /** @var User $user */
        $user = Auth::user();

        if ($user->telegram_chat_id) {
            return redirect()
                ->route('telegram.show')
                ->with('warning', 'Please disconnect first to regenerate token.');
        }

        $this->generateAuthToken($user);

        Log::info('Telegram auth token regenerated', [
            'user_id' => $user->id,
            'username' => $user->username,
        ]);

        return redirect()
            ->route('telegram.show')
            ->with('success', 'New authentication token generated.');
    }

    /**
     * Generate and store auth token for user
     */
    protected function generateAuthToken(User $user): string
    {
        $token = Str::random(32) . '_' . $user->id . '_' . time();

        $user->update([
            'telegram_auth_token' => hash('sha256', $token)
        ]);

        return $token;
    }

    /**
     * Get bot username from Telegram API
     */
    protected function getBotUsername(): ?string
    {
        try {
            return $this->telegramService->getBotUsername();
        } catch (\Exception $e) {
            Log::error('Failed to get bot username: ' . $e->getMessage());
            return config('telegram.bot_username', 'processmaker_bot');
        }
    }
}