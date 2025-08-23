<?php

namespace ProcessMaker\TelegramPlugin\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Log;
use ProcessMaker\Models\ProcessRequestToken;
use ProcessMaker\Models\User;

class TaskNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;
    public array $backoff = [60, 300, 600];
    public bool $afterCommit = true;

    protected ProcessRequestToken $task;
    protected array $actions;
    protected string $notificationType;
    protected array $customData;

    /**
     * Create a new notification instance
     */
    public function __construct(
        ProcessRequestToken $task,
        array $actions = [],
        string $type = 'task_assigned',
        array $customData = []
    ) {
        $this->task = $task;
        $this->actions = $actions;
        $this->notificationType = $type;
        $this->customData = $customData;

        // Set queue based on priority
        $this->queue = config('telegram.notifications.queue', 'default');

        Log::debug('TaskNotification created', [
            'task_id' => $task->id,
            'type' => $type,
            'actions_count' => count($actions)
        ]);
    }

    /**
     * Get the notification's delivery channels
     */
    public function via(object $notifiable): array
    {
        if (!$this->shouldSend($notifiable)) {
            Log::debug('TaskNotification skipped', [
                'user_id' => $notifiable->id,
                'task_id' => $this->task->id,
                'reason' => 'shouldSend returned false'
            ]);
            return [];
        }

        return ['telegram'];
    }

    /**
     * Get the Telegram representation of the notification
     */
    public function toTelegram(object $notifiable): array
    {
        return [
            'text' => $this->buildMessageText(),
            'buttons' => $this->buildActionButtons(),
            'options' => [
                'disable_notification' => $this->shouldSendSilently(),
                'disable_web_page_preview' => true,
            ]
        ];
    }

    /**
     * Build the notification message text
     */
    protected function buildMessageText(): string
    {
        $template = $this->getMessageTemplate();
        $variables = $this->getTemplateVariables();

        $message = $template;
        foreach ($variables as $key => $value) {
            $message = str_replace('{' . $key . '}', $value, $message);
        }

        return $message;
    }

    /**
     * Get message template based on notification type
     */
    protected function getMessageTemplate(): string
    {
        $templates = config('telegram.templates', []);

        return $templates[$this->notificationType] ?? $this->getDefaultTemplate();
    }

    /**
     * Get default template if specific template not found
     */
    protected function getDefaultTemplate(): string
    {
        return match($this->notificationType) {
            'task_assigned' =>
                "📋 <b>New Task Assignment</b>\n" .
                "━━━━━━━━━━━━━━━━━━━━\n" .
                "📝 <b>Task:</b> {task_name}\n" .
                "⚙️ <b>Process:</b> {process_name}\n" .
                "👤 <b>Assigned to:</b> {assignee}\n" .
                "📅 <b>Created:</b> {created_at}\n" .
                "⏰ <b>Due:</b> {due_date}\n" .
                "🔗 <b>Request ID:</b> <code>{request_id}</code>",

            'task_completed' =>
                "✅ <b>Task Completed</b>\n" .
                "━━━━━━━━━━━━━━━━━━━━\n" .
                "📝 <b>Task:</b> {task_name}\n" .
                "⚙️ <b>Process:</b> {process_name}\n" .
                "👤 <b>Completed by:</b> {completed_by}\n" .
                "📅 <b>Completed:</b> {completed_at}",

            'task_overdue' =>
                "⚠️ <b>Task Overdue</b>\n" .
                "━━━━━━━━━━━━━━━━━━━━\n" .
                "📝 <b>Task:</b> {task_name}\n" .
                "⚙️ <b>Process:</b> {process_name}\n" .
                "📅 <b>Due date:</b> {due_date}\n" .
                "🕒 <b>Overdue by:</b> {overdue_duration}",

            default =>
                "🔔 <b>Task Notification</b>\n" .
                "━━━━━━━━━━━━━━━━━━━━\n" .
                "📝 <b>Task:</b> {task_name}\n" .
                "⚙️ <b>Process:</b> {process_name}"
        };
    }

    /**
     * Get template variables for message
     */
    protected function getTemplateVariables(): array
    {
        $assignee = $this->task->user;
        $process = $this->task->process;
        $request = $this->task->processRequest;

        return array_merge([
            'task_id' => $this->task->id,
            'task_name' => $this->task->element_name ?? 'Unknown Task',
            'process_name' => $process->name ?? 'Unknown Process',
            'process_id' => $process->id ?? 'N/A',
            'request_id' => $request->id ?? 'N/A',
            'assignee' => $assignee->fullname ?? 'Unassigned',
            'assignee_username' => $assignee->username ?? 'N/A',
            'created_at' => $this->task->created_at?->format('M j, Y H:i') ?? 'N/A',
            'due_date' => $this->task->due_at?->format('M j, Y H:i') ?? 'No due date',
            'status' => $this->task->status ?? 'ACTIVE',
            'priority' => $this->customData['priority'] ?? 'Normal',
            'completed_by' => $this->customData['completed_by'] ?? $assignee->fullname ?? 'Unknown',
            'completed_at' => $this->customData['completed_at'] ?? now()->format('M j, Y H:i'),
            'overdue_duration' => $this->customData['overdue_duration'] ?? 'Unknown',
        ], $this->customData);
    }

    /**
     * Build action buttons for the notification
     */
    protected function buildActionButtons(): array
    {
        if (empty($this->actions) || $this->notificationType === 'task_completed') {
            return [];
        }

        $buttons = [];
        $buttonLabels = [
            'complete' => '✅ Complete',
            'claim' => '👋 Claim',
            'view' => '👀 View',
            'reject' => '❌ Reject',
            'approve' => '✅ Approve',
            'delegate' => '🔄 Delegate',
        ];

        foreach ($this->actions as $action) {
            if (!isset($buttonLabels[$action])) {
                continue;
            }

            $buttons[] = [
                'text' => $buttonLabels[$action],
                'action' => "task:{$this->task->id}:{$action}"
            ];
        }

        return $buttons;
    }

    /**
     * Determine if notification should be sent
     */
    protected function shouldSend(object $notifiable): bool
    {
        // Check if user has Telegram connected
        if (empty($notifiable->telegram_chat_id)) {
            return false;
        }

        // Check if notifications are enabled globally
        if (!config('telegram.notifications.enabled', true)) {
            return false;
        }

        // Check if user is the assigned user (for task assignments)
        if ($this->notificationType === 'task_assigned' && $this->task->user_id !== $notifiable->id) {
            return false;
        }

        // Check if task is still active
        if (!in_array($this->task->status, ['ACTIVE', 'OVERDUE'])) {
            return false;
        }

        return true;
    }

    /**
     * Determine if notification should be sent silently
     */
    protected function shouldSendSilently(): bool
    {
        // Send silently for non-urgent notifications
        $silentTypes = ['task_completed', 'process_completed'];
        return in_array($this->notificationType, $silentTypes);
    }

    /**
     * Handle notification failure
     */
    public function failed(\Throwable $exception): void
    {
        Log::error('TaskNotification failed', [
            'task_id' => $this->task->id,
            'notification_type' => $this->notificationType,
            'error' => $exception->getMessage(),
            'trace' => $exception->getTraceAsString()
        ]);
    }

    /**
     * Get unique key for notification deduplication
     */
    public function uniqueId(): string
    {
        return "telegram_task_{$this->task->id}_{$this->notificationType}_" . md5(serialize($this->actions));
    }
}