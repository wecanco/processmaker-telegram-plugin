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

    public int $tries = 3;
    public array $backoff = [60, 300, 600];
    public int $timeout = 30;

    protected int $userId;
    protected string $message;
    protected array $buttons;
    protected ?int $messageId;
    protected array $options;

    public function __construct(
        int $userId,
        string $message,
        array $buttons = [],
        ?int $messageId = null,
        array $options = []
    ) {
        $this->userId = $userId;
        $this->message = $message;
        $this->buttons = $buttons;
        $this->messageId = $messageId;
        $this->options = $options;
    }

    public function handle(TelegramService $telegram): void
    {
        try {
            $user = User::findOrFail($this->userId);

            if (!$user->telegram_chat_id) {
                throw new \Exception("User {$this->userId} has no Telegram chat ID");
            }

            $response = $this->messageId
                ? $telegram->editMessage($user->telegram_chat_id, $this->messageId, $this->message, $this->buttons)
                : $telegram->sendMessage($user->telegram_chat_id, $this->message, $this->buttons, $this->options);

            if (!$response || !($response['ok'] ?? false)) {
                throw new \Exception($response['description'] ?? 'Unknown Telegram API error');
            }

            Log::info("Telegram notification sent successfully", [
                'user_id' => $this->userId,
                'chat_id' => $user->telegram_chat_id,
                'message_id' => $response['result']['message_id'] ?? $this->messageId,
                'type' => $this->messageId ? 'edit' : 'send'
            ]);

        } catch (\Exception $e) {
            Log::error("Telegram notification failed", [
                'user_id' => $this->userId,
                'error' => $e->getMessage(),
                'attempt' => $this->attempts()
            ]);
            throw $e;
        }
    }

    public function failed(\Throwable $exception): void
    {
        Log::critical("SendTelegramNotification job failed permanently", [
            'user_id' => $this->userId,
            'error' => $exception->getMessage(),
            'trace' => $exception->getTraceAsString()
        ]);
    }

    public function uniqueId(): string
    {
        return "telegram_notification_{$this->userId}_" . md5($this->message);
    }
}