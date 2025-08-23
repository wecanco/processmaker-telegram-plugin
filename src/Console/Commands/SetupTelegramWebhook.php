<?php

namespace ProcessMaker\TelegramPlugin\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use ProcessMaker\TelegramPlugin\Services\TelegramService;

class SetupTelegramWebhook extends Command
{
    protected $signature = 'telegram:webhook 
                            {action : Action to perform (set|remove|info)}
                            {--url= : Webhook URL (required for set action)}
                            {--secret= : Secret token for webhook verification}
                            {--certificate= : Path to SSL certificate file}
                            {--max-connections=40 : Maximum number of connections}
                            {--allowed-updates= : Comma-separated list of allowed update types}
                            {--drop-pending : Drop pending updates}';

    protected $description = 'Manage Telegram webhook configuration';

    protected TelegramService $telegram;

    public function handle(): int
    {
        $this->telegram = new TelegramService(config('telegram.bot_token', ''));

        $action = $this->argument('action');

        if (!in_array($action, ['set', 'remove', 'info'])) {
            $this->error('Invalid action. Use: set, remove, or info');
            return 1;
        }

        try {
            return match($action) {
                'set' => $this->setWebhook(),
                'remove' => $this->removeWebhook(),
                'info' => $this->showWebhookInfo(),
            };
        } catch (\Exception $e) {
            $this->error("Command failed: {$e->getMessage()}");
            Log::error('Telegram webhook command failed', [
                'action' => $action,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return 1;
        }
    }

    protected function setWebhook(): int
    {
        $url = $this->option('url') ?: config('telegram.webhook.url');

        if (empty($url)) {
            $this->error('Webhook URL is required. Use --url option or set TELEGRAM_WEBHOOK_URL in .env');
            return 1;
        }

        if (!filter_var($url, FILTER_VALIDATE_URL)) {
            $this->error('Invalid webhook URL format');
            return 1;
        }

        if (!str_starts_with($url, 'https://')) {
            $this->error('Webhook URL must use HTTPS');
            return 1;
        }

        $this->info("Setting webhook URL: {$url}");

        $options = $this->buildWebhookOptions();
        $result = $this->telegram->setWebhook($url, $options);

        if (!$result || !($result['ok'] ?? false)) {
            $this->error('Failed to set webhook: ' . ($result['description'] ?? 'Unknown error'));
            return 1;
        }

        $this->info('✅ Webhook successfully configured');

        if ($this->option('verbose')) {
            $this->showWebhookDetails($result);
        }

        Log::info('Telegram webhook configured', [
            'url' => $url,
            'options' => $options
        ]);

        return 0;
    }

    protected function removeWebhook(): int
    {
        $this->info('Removing webhook...');

        $dropPending = $this->option('drop-pending') || $this->confirm('Drop pending updates?', true);
        $result = $this->telegram->deleteWebhook($dropPending);

        if (!$result || !($result['ok'] ?? false)) {
            $this->error('Failed to remove webhook: ' . ($result['description'] ?? 'Unknown error'));
            return 1;
        }

        $this->info('✅ Webhook successfully removed');

        if ($this->option('verbose')) {
            $this->showWebhookDetails($result);
        }

        Log::info('Telegram webhook removed');

        return 0;
    }

    protected function showWebhookInfo(): int
    {
        $this->info('Fetching webhook information...');

        $result = $this->telegram->getWebhookInfo();

        if (!$result || !($result['ok'] ?? false)) {
            $this->error('Failed to get webhook info: ' . ($result['description'] ?? 'Unknown error'));
            return 1;
        }

        $info = $result['result'];

        $this->info('📋 Webhook Information:');
        $this->table(
            ['Property', 'Value'],
            [
                ['URL', $info['url'] ?: 'Not set'],
                ['Has Custom Certificate', $info['has_custom_certificate'] ? 'Yes' : 'No'],
                ['Pending Update Count', $info['pending_update_count'] ?? 0],
                ['Last Error Date', $info['last_error_date'] ? date('Y-m-d H:i:s', $info['last_error_date']) : 'None'],
                ['Last Error Message', $info['last_error_message'] ?? 'None'],
                ['Max Connections', $info['max_connections'] ?? 'Default'],
                ['Allowed Updates', implode(', ', $info['allowed_updates'] ?? ['All'])],
            ]
        );

        if ($info['last_error_date'] ?? false) {
            $this->warn('⚠️  There were recent webhook errors. Check the logs.');
        }

        if (empty($info['url'])) {
            $this->warn('⚠️  No webhook URL is set. Use "set" action to configure.');
        } else {
            $this->info('✅ Webhook is configured and active');
        }

        return 0;
    }

    protected function buildWebhookOptions(): array
    {
        $options = [];

        if ($secret = $this->option('secret')) {
            $options['secret_token'] = $secret;
        }

        if ($certificate = $this->option('certificate')) {
            if (!file_exists($certificate)) {
                throw new \Exception("Certificate file not found: {$certificate}");
            }
            $options['certificate'] = fopen($certificate, 'r');
        }

        if ($maxConnections = $this->option('max-connections')) {
            $options['max_connections'] = (int) $maxConnections;
        }

        if ($allowedUpdates = $this->option('allowed-updates')) {
            $options['allowed_updates'] = explode(',', $allowedUpdates);
        } else {
            $options['allowed_updates'] = config('telegram.webhook.allowed_updates', ['message', 'callback_query']);
        }

        $options['drop_pending_updates'] = $this->option('drop-pending') ?? true;

        return array_filter($options, function($value) {
            return $value !== null && $value !== '';
        });
    }

    protected function showWebhookDetails(array $result): void
    {
        if (isset($result['result'])) {
            $this->line('Response details:');
            foreach ($result['result'] as $key => $value) {
                if (is_array($value)) {
                    $value = implode(', ', $value);
                } elseif (is_bool($value)) {
                    $value = $value ? 'true' : 'false';
                }
                $this->line("  {$key}: {$value}");
            }
        }
    }

    protected function validateBotToken(): bool
    {
        $botInfo = $this->telegram->getMe();

        if (!$botInfo || !($botInfo['ok'] ?? false)) {
            $this->error('Invalid bot token or API connection failed');
            return false;
        }

        $bot = $botInfo['result'];
        $this->info("Connected to bot: {$bot['first_name']} (@{$bot['username']})");

        return true;
    }
}