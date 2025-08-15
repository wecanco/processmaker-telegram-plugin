<?php

namespace ProcessMaker\TelegramPlugin\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use ProcessMaker\Models\User;
use ProcessMaker\TelegramPlugin\Services\TelegramService;

class TelegramBotController
{
    protected $telegramService;

    public function __construct(TelegramService $telegramService)
    {
        $this->telegramService = $telegramService;
    }

    public function webhook(Request $request)
    {
        $this->validateRequest($request);
        $update = $request->json()->all();

        if (isset($update['message'])) {
            $this->handleMessage($update['message']);
        }

        if (isset($update['callback_query'])) {
            $this->handleCallbackQuery($update['callback_query']);
        }

        return response()->json(['status' => 'success']);
    }

    protected function validateRequest($request)
    {
        $ip = $request->ip();
        $allowedIps = config('telegram.allowed_ips', []);

        if (!empty($allowedIps) && !in_array($ip, $allowedIps)) {
            Log::warning("Unauthorized Telegram webhook access from IP: $ip");
            abort(403);
        }
    }

    protected function handleMessage($message)
    {
        $text = $message['text'] ?? '';
        $chatId = $message['chat']['id'];

        if (str_starts_with($text, '/start')) {
            $this->handleStartCommand($chatId, $text);
        }
    }

    protected function handleStartCommand($chatId, $text)
    {
        $token = trim(str_replace('/start', '', $text));

        if (!$user = User::where('telegram_auth_token', $token)->first()) {
            $this->telegramService->sendMessage(
                $chatId,
                "❌ Invalid or expired token. Please generate a new one from your ProcessMaker profile."
            );
            return;
        }

        $user->update([
            'telegram_chat_id' => $chatId,
            'telegram_verified_at' => now(),
            'telegram_auth_token' => null
        ]);

        $this->telegramService->sendMessage(
            $chatId,
            "✅ *Authentication Successful!*\n\n" .
            "You will now receive task notifications from ProcessMaker.\n\n" .
            "🔐 Account: " . $user->username . "\n" .
            "🕒 Connected at: " . now()->format('Y-m-d H:i')
        );
    }

    protected function handleCallbackQuery($callbackQuery)
    {
        $data = $callbackQuery['data'];
        $chatId = $callbackQuery['message']['chat']['id'];
        $messageId = $callbackQuery['message']['message_id'];
        $userId = optional(User::where('telegram_chat_id', $chatId)->first())->id;

        if (!$userId) {
            $this->telegramService->answerCallbackQuery(
                $callbackQuery['id'],
                "❌ Unauthorized action. Please reconnect your Telegram account."
            );
            return;
        }

        if (preg_match('/^task:(\d+):([a-z_]+)$/', $data, $matches)) {
            ProcessTaskAction::dispatch(
                $userId,
                $matches[1],
                $matches[2],
                $messageId
            )->onQueue('telegram_actions');

            $this->telegramService->answerCallbackQuery(
                $callbackQuery['id'],
                "🔄 Processing your request..."
            );
        }
    }
}