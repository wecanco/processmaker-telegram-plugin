<?php

namespace ProcessMaker\TelegramPlugin\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use ProcessMaker\Http\Controllers\Controller;
use ProcessMaker\Models\User;
use ProcessMaker\TelegramPlugin\Jobs\ProcessTaskAction;
use ProcessMaker\TelegramPlugin\Services\TelegramService;

class TelegramBotController extends Controller
{
    protected TelegramService $telegramService;

    public function __construct(TelegramService $telegramService)
    {
        $this->telegramService = $telegramService;
    }

    /**
     * Handle incoming webhook from Telegram
     */
    public function webhook(Request $request): JsonResponse
    {
        try {
            $this->validateWebhookRequest($request);
            $update = $request->json()->all();

            Log::debug('Telegram webhook received', [
                'update_id' => $update['update_id'] ?? null,
                'type' => $this->getUpdateType($update)
            ]);

            // Handle different types of updates
            if (isset($update['message'])) {
                $this->handleMessage($update['message']);
            } elseif (isset($update['callback_query'])) {
                $this->handleCallbackQuery($update['callback_query']);
            } elseif (isset($update['edited_message'])) {
                $this->handleEditedMessage($update['edited_message']);
            }

            return response()->json(['ok' => true]);

        } catch (\Exception $e) {
            Log::error('Telegram webhook error: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
                'request_data' => $request->all()
            ]);

            return response()->json(['error' => 'Internal error'], 500);
        }
    }

    /**
     * Validate incoming webhook request
     */
    protected function validateWebhookRequest(Request $request): void
    {
        // Validate secret token if configured
        $secretToken = config('telegram.webhook.secret_token');
        if ($secretToken) {
            $providedToken = $request->header('X-Telegram-Bot-Api-Secret-Token');
            if (!hash_equals($secretToken, $providedToken ?? '')) {
                Log::warning('Invalid webhook secret token');
                abort(403, 'Invalid secret token');
            }
        }

        // Validate IP if configured
        $allowedIps = config('telegram.webhook.allowed_ips', []);
        if (!empty($allowedIps)) {
            $clientIp = $request->ip();
            if (!in_array($clientIp, $allowedIps, true)) {
                Log::warning("Unauthorized webhook access from IP: {$clientIp}");
                abort(403, 'Unauthorized IP');
            }
        }

        // Validate JSON payload
        if (!$request->isJson()) {
            Log::warning('Non-JSON webhook request received');
            abort(400, 'Invalid content type');
        }
    }

    /**
     * Handle incoming text messages
     */
    protected function handleMessage(array $message): void
    {
        $text = trim($message['text'] ?? '');
        $chatId = $message['chat']['id'] ?? null;
        $userId = $message['from']['id'] ?? null;

        if (!$chatId || !$text) {
            return;
        }

        Log::debug('Processing message', [
            'chat_id' => $chatId,
            'user_id' => $userId,
            'text' => Str::limit($text, 100)
        ]);

        // Handle different commands
        if (str_starts_with($text, '/start')) {
            $this->handleStartCommand($chatId, $text, $message['from'] ?? []);
        } elseif (str_starts_with($text, '/help')) {
            $this->handleHelpCommand($chatId);
        } elseif (str_starts_with($text, '/status')) {
            $this->handleStatusCommand($chatId);
        } else {
            $this->handleUnknownCommand($chatId);
        }
    }

    /**
     * Handle /start command with authentication
     */
    protected function handleStartCommand(string $chatId, string $text, array $from): void
    {
        // Extract token from /start command
        $parts = explode(' ', $text, 2);
        $token = $parts[1] ?? '';

        if (empty($token)) {
            $this->telegramService->sendMessage(
                $chatId,
                "👋 <b>Welcome to ProcessMaker!</b>\n\n" .
                "To connect your account:\n" .
                "1. Go to your ProcessMaker profile\n" .
                "2. Navigate to Telegram integration\n" .
                "3. Get your connection token\n" .
                "4. Start the bot with: <code>/start YOUR_TOKEN</code>"
            );
            return;
        }

        // Find user by hashed token
        $hashedToken = hash('sha256', $token);
        $user = User::where('telegram_auth_token', $hashedToken)->first();

        if (!$user) {
            $this->telegramService->sendMessage(
                $chatId,
                "❌ <b>Invalid or expired token</b>\n\n" .
                "Please generate a new token from your ProcessMaker profile."
            );
            return;
        }

        // Check if another account is already using this chat
        $existingUser = User::where('telegram_chat_id', $chatId)
            ->where('id', '!=', $user->id)
            ->first();

        if ($existingUser) {
            $this->telegramService->sendMessage(
                $chatId,
                "⚠️ <b>Chat already connected</b>\n\n" .
                "This Telegram account is already connected to: <b>{$existingUser->fullname}</b>\n\n" .
                "Please disconnect first or use a different Telegram account."
            );
            return;
        }

        // Update user with Telegram info
        $user->update([
            'telegram_chat_id' => $chatId,
            'telegram_verified_at' => now(),
            'telegram_auth_token' => null, // Clear token after use
            'telegram_username' => $from['username'] ?? null,
            'telegram_first_name' => $from['first_name'] ?? null,
        ]);

        $this->telegramService->sendMessage(
            $chatId,
            "🤖 <b>ProcessMaker Bot Help</b>\n\n" .
            "<b>Available Commands:</b>\n" .
            "• /help - Show this help message\n" .
            "• /status - Check connection status\n\n" .
            "<b>Features:</b>\n" .
            "📋 Receive task notifications\n" .
            "⚡ Quick task actions via buttons\n" .
            "🔔 Real-time process updates\n\n" .
            "<b>Connected Account:</b>\n" .
            "👤 {$user->fullname} (<code>{$user->username}</code>)\n" .
            "📅 Since: " . $user->telegram_verified_at->format('M j, Y')
        );
    }

    /**
     * Handle /status command
     */
    protected function handleStatusCommand(string $chatId): void
    {
        $user = User::where('telegram_chat_id', $chatId)->first();

        if (!$user) {
            $this->telegramService->sendMessage(
                $chatId,
                "❌ <b>Not Connected</b>\n\nPlease connect your account first using /start command."
            );
            return;
        }

        $this->telegramService->sendMessage(
            $chatId,
            "✅ <b>Connection Status: Active</b>\n\n" .
            "👤 <b>Account:</b> {$user->fullname}\n" .
            "🆔 <b>Username:</b> <code>{$user->username}</code>\n" .
            "📅 <b>Connected:</b> " . $user->telegram_verified_at->format('M j, Y \a\t H:i') . "\n" .
            "💬 <b>Chat ID:</b> <code>{$chatId}</code>\n\n" .
            "🔔 <b>Notifications:</b> " . (config('telegram.notifications.enabled', true) ? 'Enabled' : 'Disabled')
        );
    }

    /**
     * Handle unknown commands
     */
    protected function handleUnknownCommand(string $chatId): void
    {
        $user = User::where('telegram_chat_id', $chatId)->first();

        if (!$user) {
            return; // Ignore messages from unconnected users
        }

        $this->telegramService->sendMessage(
            $chatId,
            "🤔 I don't understand that command.\n\nUse /help to see available commands.",
            [],
            ['silent' => true]
        );
    }

    /**
     * Handle callback queries (button clicks)
     */
    protected function handleCallbackQuery(array $callbackQuery): void
    {
        $callbackId = $callbackQuery['id'];
        $data = $callbackQuery['data'] ?? '';
        $chatId = $callbackQuery['message']['chat']['id'] ?? null;
        $messageId = $callbackQuery['message']['message_id'] ?? null;

        if (!$chatId || !$data) {
            $this->telegramService->answerCallbackQuery($callbackId, '❌ Invalid request');
            return;
        }

        // Find user by chat ID
        $user = User::where('telegram_chat_id', $chatId)->first();
        if (!$user) {
            $this->telegramService->answerCallbackQuery(
                $callbackId,
                '❌ Please reconnect your account'
            );
            return;
        }

        Log::debug('Processing callback query', [
            'user_id' => $user->id,
            'chat_id' => $chatId,
            'data' => $data
        ]);

        // Parse callback data
        if (preg_match('/^task:(\d+):([a-z_]+)$/', $data, $matches)) {
            $this->handleTaskAction($user, $matches[1], $matches[2], $messageId, $callbackId);
        } elseif (preg_match('/^process:(\d+):([a-z_]+)$/', $data, $matches)) {
            $this->handleProcessAction($user, $matches[1], $matches[2], $messageId, $callbackId);
        } else {
            $this->telegramService->answerCallbackQuery($callbackId, '❌ Unknown action');
        }
    }

    /**
     * Handle task-related actions
     */
    protected function handleTaskAction(User $user, string $taskId, string $action, ?int $messageId, string $callbackId): void
    {
        // Validate action
        $allowedActions = ['complete', 'claim', 'view', 'reject'];
        if (!in_array($action, $allowedActions, true)) {
            $this->telegramService->answerCallbackQuery($callbackId, '❌ Invalid action');
            return;
        }

        // Dispatch job to process the action
        ProcessTaskAction::dispatch($user->id, $taskId, $action, $messageId)
            ->onQueue(config('telegram.notifications.queue', 'default'));

        // Immediate feedback
        $actionLabels = [
            'complete' => '✅ Completing task...',
            'claim' => '👋 Claiming task...',
            'view' => '👀 Opening task...',
            'reject' => '❌ Rejecting task...'
        ];

        $this->telegramService->answerCallbackQuery(
            $callbackId,
            $actionLabels[$action] ?? '🔄 Processing...'
        );

        Log::info('Task action dispatched', [
            'user_id' => $user->id,
            'task_id' => $taskId,
            'action' => $action
        ]);
    }

    /**
     * Handle process-related actions
     */
    protected function handleProcessAction(User $user, string $processId, string $action, ?int $messageId, string $callbackId): void
    {
        // Process actions can be implemented here
        $this->telegramService->answerCallbackQuery($callbackId, '🔄 Processing...');
    }

    /**
     * Handle edited messages
     */
    protected function handleEditedMessage(array $message): void
    {
        // Log edited messages for security/audit purposes
        Log::debug('Message edited', [
            'chat_id' => $message['chat']['id'] ?? null,
            'message_id' => $message['message_id'] ?? null,
            'edit_date' => $message['edit_date'] ?? null
        ]);
    }

    /**
     * Get update type for logging
     */
    protected function getUpdateType(array $update): string
    {
        if (isset($update['message'])) return 'message';
        if (isset($update['callback_query'])) return 'callback_query';
        if (isset($update['edited_message'])) return 'edited_message';
        if (isset($update['inline_query'])) return 'inline_query';
        if (isset($update['chosen_inline_result'])) return 'chosen_inline_result';

        return 'unknown';
    }
}