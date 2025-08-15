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
use ProcessMaker\TelegramPlugin\Services\TelegramService;

class ProcessTaskAction implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 3;
    public $maxExceptions = 2;

    protected $userId;
    protected $taskId;
    protected $action;
    protected $messageId;

    public function __construct($userId, $taskId, $action, $messageId = null)
    {
        $this->userId = $userId;
        $this->taskId = $taskId;
        $this->action = $action;
        $this->messageId = $messageId;
    }

    public function handle(TelegramService $telegram)
    {
        try {
            $task = ProcessRequestToken::findOrFail($this->taskId);
            $user = $task->user;

            if ($user->id != $this->userId) {
                throw new \Exception("User {$this->userId} is not assigned to task {$this->taskId}");
            }

            $result = $this->processAction($task);

            if ($this->messageId) {
                $telegram->editMessage(
                    $user->telegram_chat_id,
                    $this->messageId,
                    "✅ Task #{$task->id} processed\nAction: {$this->action}\nStatus: {$result['status']}",
                    []
                );
            }

            Log::info("Telegram task action processed", [
                'task_id' => $this->taskId,
                'action' => $this->action,
                'user_id' => $this->userId
            ]);

        } catch (\Exception $e) {
            Log::error("Task action failed: " . $e->getMessage());
            $this->notifyFailure($telegram, $e->getMessage());
            throw $e;
        }
    }

    protected function processAction($task)
    {
        switch ($this->action) {
            case 'complete':
                return WorkflowManager::completeTask($task, [], $this->userId);
            case 'claim':
                $task->update(['user_id' => $this->userId]);
                return ['status' => 'claimed'];
            default:
                throw new \Exception("Invalid action: {$this->action}");
        }
    }

    protected function notifyFailure($telegram, $error)
    {
        if ($user = User::find($this->userId)) {
            $telegram->sendMessage(
                $user->telegram_chat_id,
                "❌ Action failed: " . substr($error, 0, 200)
            );
        }
    }
}