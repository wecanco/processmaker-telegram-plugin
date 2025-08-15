<?php

namespace ProcessMaker\TelegramPlugin\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use ProcessMaker\Models\User;
use ProcessMaker\TelegramPlugin\Services\TelegramService;

class SendTelegramNotification implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 3;
    public $backoff = [60, 300, 600];

    protected $userId;
    protected $message;
    protected $buttons;
    protected $messageId;

    public function __construct($userId, $message, array $buttons = [], $messageId = null)
    {
        $this->userId = $userId;
        $this->message = $message;
        $this->buttons = $buttons;
        $this->messageId = $messageId;
    }

    public function handle(TelegramService $telegram)
    {
        try {
            $user = User::findOrFail($this->userId);

            if (!$user->telegram_chat_id) {
                throw new \Exception("User {$this->userId} has no Telegram chat ID");
            }

            $response = $this->messageId
                ? $telegram->editMessage($user->telegram_chat_id, $this->messageId, $this->message, $this->buttons)
                : $telegram->sendMessage($user->telegram_chat_id, $this->message, $this->buttons);

            if (!$response || !$response['ok']) {
                throw new \Exception($response['description'] ?? 'Unknown Telegram API error');
            }

            Log::info("Telegram notification sent to user {$this->userId}", [
                'chat_id' => $user->telegram_chat_id,
                'message_id' => $response['result']['message_id'] ?? null
            ]);

        } catch (\Exception $e) {
            Log::error("Telegram notification failed for user {$this->userId}: " . $e->getMessage());
            throw $e;
        }
    }

    public function failed(\Throwable $exception)
    {
        Log::critical("Telegram notification job failed after retries", [
            'user_id' => $this->userId,
            'error' => $exception->getMessage(),
            'trace' => $exception->getTraceAsString()
        ]);
    }
}