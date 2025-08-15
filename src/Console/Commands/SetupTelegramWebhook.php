<?php

namespace ProcessMaker\TelegramPlugin\Console\Commands;

use Illuminate\Console\Command;
use ProcessMaker\TelegramPlugin\Services\TelegramService;

class SetupTelegramWebhook extends Command
{
    protected $signature = 'telegram:setup-webhook 
                            {--remove : Remove the webhook}';
    protected $description = 'Setup Telegram webhook URL';

    public function handle(TelegramService $telegram)
    {
        if ($this->option('remove')) {
            $result = $telegram->deleteWebhook();
            
            if ($result && $result['ok']) {
                $this->info('✅ Webhook successfully removed');
            } else {
                $this->error('❌ Failed to remove webhook');
                $this->error($result['description'] ?? 'Unknown error');
            }
            return;
        }

        $url = config('telegram.webhook_url');
        
        if (empty($url)) {
            $this->error('Webhook URL not configured. Set TELEGRAM_WEBHOOK_URL in .env');
            return;
        }

        $this->info("Setting webhook URL to: {$url}");
        $result = $telegram->setWebhook($url);

        if ($result && $result['ok']) {
            $this->info('✅ Webhook successfully set');
        } else {
            $this->error('❌ Failed to set webhook');
            $this->error($result['description'] ?? 'Unknown error');
        }
    }
}