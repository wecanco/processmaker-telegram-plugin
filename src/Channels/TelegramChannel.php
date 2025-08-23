<?php

namespace ProcessMaker\TelegramPlugin\Channels;

use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Log;
use ProcessMaker\TelegramPlugin\Services\TelegramService;

class TelegramChannel
{
    protected TelegramService $telegram;

    public function __construct(TelegramService $telegram)
    {
        $this->telegram = $telegram;
    }

    /**
     * Send the given notification
     */
    public function send($notifiable, Notification $notification): array|false
    {
        if (!$this->shouldSendNotification($notifiable, $notification)) {
            return false;
        }

        try {
            $telegramData = $notification->toTelegram($notifiable);

            $this->validateTelegramData($telegramData);

            $response = $this->telegram->sendMessage(
                $notifiable->telegram_chat_id,
                $telegramData['text'],
                $telegramData['buttons'] ?? [],
                $telegramData['options'] ?? []
            );

            $this->logNotificationResult($notifiable, $notification, $response);

            return $response;

        } catch (\Exception $e) {
            $this->logNotificationError($notifiable, $notification, $e);
            throw $e;
        }
    }

    /**
     * Check if notification should be sent
     */
    protected function shouldSendNotification($notifiable, Notification $notification): bool
    {
        // Check if user has Telegram chat ID
        if (empty($notifiable->telegram_chat_id)) {
            Log::debug('Telegram notification skipped: No chat ID', [
                'user_id' => $notifiable->id,
                'notification' => get_class($notification)
            ]);
            return false;
        }

        // Check if Telegram notifications are enabled
        if (!config('telegram.notifications.enabled', true)) {
            Log::debug('Telegram notification skipped: Notifications disabled', [
                'user_id' => $notifiable->id,
                'notification' => get_class($notification)
            ]);
            return false;
        }

        // Check if user has Telegram notifications enabled (if user preference exists)
        if (method_exists($notifiable, 'hasTelegramNotificationsEnabled')) {
            if (!$notifiable->hasTelegramNotificationsEnabled()) {
                Log::debug('Telegram notification skipped: User disabled notifications', [
                    'user_id' => $notifiable->id,
                    'notification' => get_class($notification)
                ]);
                return false;
            }
        }

        return true;
    }

    /**
     * Validate Telegram notification data
     */
    protected function validateTelegramData(array $data): void
    {
        if (!isset($data['text']) || empty($data['text'])) {
            throw new \InvalidArgumentException('Telegram notification must have text content');
        }

        if (mb_strlen($data['text']) > 4096) {
            throw new \InvalidArgumentException('Telegram message text too long (max 4096 characters)');
        }

        if (isset($data['buttons']) && !is_array($data['buttons'])) {
            throw new \InvalidArgumentException('Telegram buttons must be an array');
        }

        if (isset($data['options']) && !is_array($data['options'])) {
            throw new \InvalidArgumentException('Telegram options must be an array');
        }
    }

    /**
     * Log notification result
     */
    protected function logNotificationResult($notifiable, Notification $notification, $response): void
    {
        if ($response && ($response['ok'] ?? false)) {
            Log::info('Telegram notification sent successfully', [
                'user_id' => $notifiable->id,
                'chat_id' => $notifiable->telegram_chat_id,
                'notification' => get_class($notification),
                'message_id' => $response['result']['message_id'] ?? null
            ]);
        } else {
            Log::warning('Telegram notification failed', [
                'user_id' => $notifiable->id,
                'chat_id' => $notifiable->telegram_chat_id,
                'notification' => get_class($notification),
                'error' => $response['description'] ?? 'Unknown error'
            ]);
        }
    }

    /**
     * Log notification error
     */
    protected function logNotificationError($notifiable, Notification $notification, \Exception $e): void
    {
        Log::error('Telegram notification exception', [
            'user_id' => $notifiable->id,
            'chat_id' => $notifiable->telegram_chat_id ?? 'N/A',
            'notification' => get_class($notification),
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString()
        ]);
    }
}