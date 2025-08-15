<?php

namespace ProcessMaker\TelegramPlugin\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;
use Illuminate\Support\HtmlString;

class TaskNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public $tries = 3;
    public $afterCommit = true;

    protected $task;
    protected $actions;

    public function __construct($task, array $actions)
    {
        $this->task = $task;
        $this->actions = $actions;
    }

    public function via($notifiable)
    {
        return $this->shouldSend($notifiable) ? ['telegram'] : [];
    }

    public function toTelegram($notifiable)
    {
        return [
            'text' => $this->buildMessageText(),
            'buttons' => $this->buildActionButtons(),
            'disable_notification' => false
        ];
    }

    protected function buildMessageText()
    {
        return new HtmlString(
            "📋 <b>New Task Assignment</b>\n" .
            "────────────────────\n" .
            "• <b>Task:</b> {$this->task->name}\n" .
            "• <b>Process:</b> {$this->task->process->name}\n" .
            "• <b>Created:</b> {$this->task->created_at->format('M j, Y H:i')}\n" .
            "• <b>Due:</b> " . ($this->task->due_at?->format('M j, Y H:i') ?? 'None') . "\n\n" .
            "Please select an action:"
        );
    }

    protected function buildActionButtons()
    {
        return collect($this->actions)->map(function ($label, $action) {
            return [
                'text' => $label,
                'action' => "task:{$this->task->id}:{$action}"
            ];
        })->values()->toArray();
    }

    public function shouldSend($notifiable)
    {
        return !empty($notifiable->telegram_chat_id) &&
            !empty($this->actions) &&
            $this->task->user_id == $notifiable->id;
    }
}