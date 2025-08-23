<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Telegram Bot Token
    |--------------------------------------------------------------------------
    |
    | Your Telegram Bot Token obtained from @BotFather
    |
    */
    'bot_token' => env('TELEGRAM_BOT_TOKEN', ''),

    /*
    |--------------------------------------------------------------------------
    | Telegram API Base URL
    |--------------------------------------------------------------------------
    |
    | The base URL for Telegram Bot API
    |
    */
    'base_url' => env('TELEGRAM_API_BASE_URL', 'https://api.telegram.org/bot'),

    /*
    |--------------------------------------------------------------------------
    | Webhook Settings
    |--------------------------------------------------------------------------
    |
    | Configuration for Telegram webhook
    |
    */
    'webhook' => [
        'url' => env('TELEGRAM_WEBHOOK_URL', ''),
        'certificate' => env('TELEGRAM_WEBHOOK_CERTIFICATE', ''),
        'max_connections' => env('TELEGRAM_WEBHOOK_MAX_CONNECTIONS', 40),
        'allowed_updates' => [
            'message',
            'callback_query',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Default Chat Settings
    |--------------------------------------------------------------------------
    |
    | Default settings for chat notifications
    |
    */
    'default_chat_id' => env('TELEGRAM_DEFAULT_CHAT_ID', ''),

    /*
    |--------------------------------------------------------------------------
    | Notification Settings
    |--------------------------------------------------------------------------
    |
    | Configuration for ProcessMaker notifications
    |
    */
    'notifications' => [
        'enabled' => env('TELEGRAM_NOTIFICATIONS_ENABLED', true),
        'queue' => env('TELEGRAM_NOTIFICATIONS_QUEUE', 'default'),
        'timeout' => env('TELEGRAM_TIMEOUT', 30),
        'retry_attempts' => env('TELEGRAM_RETRY_ATTEMPTS', 3),
    ],

    /*
    |--------------------------------------------------------------------------
    | Message Templates
    |--------------------------------------------------------------------------
    |
    | Default message templates for different notification types
    |
    */
    'templates' => [
        'task_assigned' => '📋 *Task Assigned*\n\nProcess: {process_name}\nTask: {task_name}\nAssigned to: {assignee}\n\nDue: {due_date}',
        'task_completed' => '✅ *Task Completed*\n\nProcess: {process_name}\nTask: {task_name}\nCompleted by: {completed_by}',
        'process_started' => '🚀 *Process Started*\n\nProcess: {process_name}\nStarted by: {started_by}\nRequest ID: {request_id}',
        'process_completed' => '🎉 *Process Completed*\n\nProcess: {process_name}\nCompleted on: {completed_date}',
    ],
];