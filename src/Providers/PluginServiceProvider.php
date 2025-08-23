<?php

namespace ProcessMaker\TelegramPlugin\Providers;

use Illuminate\Support\ServiceProvider;
use ProcessMaker\TelegramPlugin\Services\TelegramService;
use ProcessMaker\TelegramPlugin\Console\Commands\SetupTelegramWebhook;

class PluginServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap the application services.
     */
    public function boot()
    {
        $this->bootRoutes();
        $this->bootMigrations();
        $this->bootViews();
        $this->bootPublishables();
        $this->bootConfig();
        $this->bootNotificationChannels();
    }

    /**
     * Load plugin routes
     */
    protected function bootRoutes()
    {
        if (file_exists(__DIR__ . '/../../routes/web.php')) {
            $this->loadRoutesFrom(__DIR__ . '/../../routes/web.php');
        }

        if (file_exists(__DIR__ . '/../../routes/api.php')) {
            $this->loadRoutesFrom(__DIR__ . '/../../routes/api.php');
        }
    }

    /**
     * Load plugin migrations
     */
    protected function bootMigrations()
    {
        if (is_dir(__DIR__ . '/../../database/migrations')) {
            $this->loadMigrationsFrom(__DIR__ . '/../../database/migrations');
        }
    }

    /**
     * Load plugin views
     */
    protected function bootViews()
    {
        if (is_dir(__DIR__ . '/../../resources/views')) {
            $this->loadViewsFrom(__DIR__ . '/../../resources/views', 'telegram-plugin');
        }
    }

    /**
     * Setup publishable assets
     */
    protected function bootPublishables()
    {
        // Config file
        $this->publishes([
            __DIR__ . '/../../config/telegram.php' => config_path('telegram.php'),
        ], 'telegram-config');

        // Assets
        if (is_dir(__DIR__ . '/../../resources/assets')) {
            $this->publishes([
                __DIR__ . '/../../resources/assets' => public_path('vendor/telegram-plugin'),
            ], 'telegram-assets');
        }
    }

    /**
     * Setup configuration
     */
    protected function bootConfig()
    {
        $configPath = __DIR__ . '/../../config/telegram.php';
        if (file_exists($configPath)) {
            $this->mergeConfigFrom($configPath, 'telegram');
        }
    }

    /**
     * Register notification channels
     */
    protected function bootNotificationChannels()
    {
        $this->app->when(\ProcessMaker\TelegramPlugin\Channels\TelegramChannel::class)
            ->needs(TelegramService::class)
            ->give(function ($app) {
                return $app->make(TelegramService::class);
            });
    }

    /**
     * Register the application services.
     */
    public function register()
    {
        // Register TelegramService as singleton
        $this->app->singleton(TelegramService::class, function ($app) {
            return new TelegramService(
                config('telegram.bot_token', ''),
                config('telegram.base_url', 'https://api.telegram.org/bot')
            );
        });

//        $this->app->singleton(TelegramService::class, function ($app) {
//            return new TelegramService(
//                config('telegram.bot_token', ''),
//                config('telegram.base_url', 'https://api.telegram.org/bot')
//            );
//        });


        // Register alias for easier access
        $this->app->alias(TelegramService::class, 'telegram-service');

        // Register console commands
        if ($this->app->runningInConsole()) {
            $this->commands([
                SetupTelegramWebhook::class,
            ]);
        }

        // Bind the service in container
        $this->app->bind('ProcessMaker\TelegramPlugin\Services\TelegramService', function ($app) {
            return $app->make(TelegramService::class);
        });
    }

    /**
     * Get the services provided by the provider.
     *
     * @return array
     */
    public function provides()
    {
        return [
            TelegramService::class,
            'telegram-service',
        ];
    }
}