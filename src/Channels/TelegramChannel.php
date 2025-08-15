<?php

namespace ProcessMaker\TelegramPlugin\Channels;

use Illuminate\Notifications\Notification;
use ProcessMaker\TelegramPlugin\Services\TelegramService;

class TelegramChannel
{
    protected $telegram;

    public function __construct(TelegramService $telegram)
    {
        $this->telegram = $telegram;
    }

    public function send($notifiable, Notification $notification)
    {
        $message = $notification->toTelegram($notifiable);
        
        return $this->telegram->sendMessage(
            $notifiable->telegram_chat_id,
            $message['text'],
            $message['buttons'] ?? []
        );
    }
}