<?php

namespace ProcessMaker\TelegramPlugin\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use ProcessMaker\Facades\WorkflowManager;
use ProcessMaker\Models\ProcessRequestToken;
use ProcessMaker\Models\User;
use ProcessMaker\TelegramPlugin\Services\TelegramService;

class ProcessTaskAction implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $maxExceptions = 2;
    public array $backoff = [30, 120, 300];
    public int $timeout = 120;

    protected int $userId;
    protected string $taskId;
    protected string $action;
    protected ?int $messageId;
    protected array $actionData;

    public function __construct(int $userId, string $taskId, string $action, ?int $messageId = null, array $actionData = [])
    {
        $this->userId = $userId;
        $this->taskId = $taskId;
        $this->action = $action;
        $this->messageId = $messageId;
        $this->actionData = $actionData;
    }

    public function handle(TelegramService $telegram): void
    {
        try {
            $task = ProcessRequestToken::findOrFail($this->taskId);
            $user = User::findOrFail($this->userId);

            $this->validateTaskAccess($task, $user);

            Log::info('Processing task action', [
                'user_id' => $this->userId,
                'task_id' => $this->taskId,
                'action' => $this->action
            ]);

            $result = $this->executeTaskAction($task, $user);
            $this->notifySuccess($telegram, $user, $task, $result);

        } catch (\Exception $e) {
            Log::error('Task action processing failed', [
                'user_id' => $this->userId,
                'task_id' => $this->taskId,
                'action' => $this->action,
                'error' => $e->getMessage()
            ]);

            $this->notifyError($telegram, $e->getMessage());
            throw $e;
        }
    }

    protected function validateTaskAccess(ProcessRequestToken $task, User $user): void
    {
        if (!in_array($task->status, ['ACTIVE', 'OVERDUE'])) {
            throw new \Exception("Task {$this->taskId} is not active (status: {$task->status})");
        }

        if ($this->action !== 'claim' && $task->user_id !== $user->id) {
            throw new \Exception("User {$user->id} is not assigned to task {$this->taskId}");
        }

        if ($this->action === 'claim' && $task->user_id !== null) {
            throw new \Exception("Task {$this->taskId} is already claimed");
        }
    }

    protected function executeTaskAction(ProcessRequestToken $task, User $user): array
    {
        return match($this->action) {
            'complete' => $this->completeTask($task, $user),
            'claim' => $this->claimTask($task, $user),
            'approve' => $this->approveTask($task, $user),
            'reject' => $this->rejectTask($task, $user),
            'delegate' => $this->delegateTask($task, $user),
            default => throw new \Exception("Invalid action: {$this->action}")
        };
    }

    protected function completeTask(ProcessRequestToken $task, User $user): array
    {
        $data = $this->actionData['form_data'] ?? [];

        $result = WorkflowManager::completeTask(
            $task,
            $data,
            $user->id
        );

        return [
            'status' => 'completed',
            'action' => 'complete',
            'message' => 'Task completed successfully'
        ];
    }

    protected function claimTask(ProcessRequestToken $task, User $user): array
    {
        $task->update([
            'user_id' => $user->id,
            'status' => 'ACTIVE'
        ]);

        return [
            'status' => 'claimed',
            'action' => 'claim',
            'message' => 'Task claimed successfully'
        ];
    }

    protected function approveTask(ProcessRequestToken $task, User $user): array
    {
        return $this->completeTask($task, $user);
    }

    protected function rejectTask(ProcessRequestToken $task, User $user): array
    {
        $reason = $this->actionData['rejection_reason'] ?? 'Rejected via Telegram';

        // Implementation depends on ProcessMaker's rejection mechanism
        $task->update(['status' => 'CLOSED']);

        return [
            'status' => 'rejected',
            'action' => 'reject',
            'message' => 'Task rejected',
            'reason' => $reason
        ];
    }

    protected function delegateTask(ProcessRequestToken $task, User $user): array
    {
        $targetUserId = $this->actionData['target_user_id'] ?? null;
        if (!$targetUserId) {
            throw new \Exception('Target user ID required for delegation');
        }

        $targetUser = User::findOrFail($targetUserId);

        $task->update(['user_id' => $targetUser->id]);

        return [
            'status' => 'delegated',
            'action' => 'delegate',
            'message' => "Task delegated to {$targetUser->fullname}",
            'target_user' => $targetUser->fullname
        ];
    }

    protected function notifySuccess(TelegramService $telegram, User $user, ProcessRequestToken $task, array $result): void
    {
        if (!$this->messageId) {
            return;
        }

        $statusEmojis = [
            'completed' => '✅',
            'claimed' => '👋',
            'approved' => '✅',
            'rejected' => '❌',
            'delegated' => '🔄'
        ];

        $emoji = $statusEmojis[$result['status']] ?? '✅';

        $message = "{$emoji} <b>Action Completed</b>\n" .
            "━━━━━━━━━━━━━━━━━━━━\n" .
            "📝 <b>Task:</b> {$task->element_name}\n" .
            "⚡ <b>Action:</b> " . ucfirst($result['action']) . "\n" .
            "✨ <b>Result:</b> {$result['message']}\n" .
            "🕐 <b>Processed:</b> " . now()->format('M j, Y H:i');

        $telegram->editMessage($user->telegram_chat_id, $this->messageId, $message, []);
    }

    protected function notifyError(TelegramService $telegram, string $error): void
    {
        if (!$this->messageId) {
            return;
        }

        $user = User::find($this->userId);
        if (!$user || !$user->telegram_chat_id) {
            return;
        }

        $message = "❌ <b>Action Failed</b>\n" .
            "━━━━━━━━━━━━━━━━━━━━\n" .
            "⚠️ <b>Error:</b> " . substr($error, 0, 200) . "\n" .
            "🔄 Please try again or contact support";

        $telegram->editMessage($user->telegram_chat_id, $this->messageId, $message, []);
    }

    public function failed(\Throwable $exception): void
    {
        Log::critical('ProcessTaskAction job failed permanently', [
            'user_id' => $this->userId,
            'task_id' => $this->taskId,
            'action' => $this->action,
            'error' => $exception->getMessage(),
            'trace' => $exception->getTraceAsString()
        ]);
    }

    public function uniqueId(): string
    {
        return "task_action_{$this->userId}_{$this->taskId}_{$this->action}";
    }
}
