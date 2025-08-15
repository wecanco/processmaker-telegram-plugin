<?php

namespace ProcessMaker\TelegramPlugin;

use Illuminate\Support\ServiceProvider;
use Illuminate\Database\Eloquent\Factory;

class PluginServiceProvider extends ServiceProvider
{
    public function boot()
    {
        $this->loadRoutesFrom(__DIR__.'/../routes/web.php');
        $this->loadRoutesFrom(__DIR__.'/../routes/api.php');
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'telegram-plugin');
        
        $this->publishes([
            __DIR__.'/../config/telegram.php' => config_path('telegram.php'),
        ], 'config');
    }
	
	public function register()
    {
        $this->app->singleton('telegram-service', function ($app) {
            return new \ProcessMaker\TelegramPlugin\Services\TelegramService(
                config('telegram.bot_token')
            );
        });

        $this->commands([
            \ProcessMaker\TelegramPlugin\Console\Commands\SetupTelegramWebhook::class,
        ]);
    }
}