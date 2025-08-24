# ProcessMaker Telegram Plugin

[![License](https://img.shields.io/badge/license-MIT-blue.svg)](LICENSE.md)

A ProcessMaker plugin that sends task notifications to Telegram with inline action buttons.

## Features

- 🔔 Real-time task notifications in Telegram
- 🛠 Inline action buttons for task processing
- 🔗 Secure Telegram account linking
- 📊 Supports all task types and processes
- ⚡ Asynchronous processing via queues

## Requirements

- ProcessMaker 4.x
- PHP 8.0+
- Telegram Bot Token
- SSL Certificate (for webhooks)

## Installation

1. Add the package to your ProcessMaker installation:

```bash
composer require wecanco/processmaker-telegram-plugin
```
for local developing:
```bash
composer config repositories.processmaker-telegram-plugin path ../processmaker-telegram-plugin
composer require wecanco/processmaker-telegram-plugin:*
```

2.	Publish the configuration:

```bash
php artisan vendor:publish --tag=telegram-config
```

3.	Run migrations:

```bash
php artisan migrate
```

## Configuration

Add to your `.env`:

```ini
TELEGRAM_BOT_TOKEN=your_bot_token
TELEGRAM_BOT_USERNAME=your_bot_username
TELEGRAM_WEBHOOK_URL=https://yourdomain.com/telegram/webhook
```
[User.php](../processmaker/ProcessMaker/Models/User.php)
Set up the webhook:

```bash
php artisan telegram:webhook set
```

Config `User` Model:
```php
namespace App\Models;

//...
use ProcessMaker\TelegramPlugin\Traits\HasTelegramFields; // <----  add this
//...

class User extends Authenticatable implements HasMedia
{
    use HasTelegramFields; // <----  add this

    //...
}
```

## Usage

### For Users
1. Navigate to your profile in ProcessMaker
2. Click "Connect Telegram Account"
3. Start a chat with your bot in Telegram
4. Send the provided authentication token

### For Administrators
Send notifications from your processes:

```php
use ProcessMaker\TelegramPlugin\Notifications\TaskNotification;

$user->notify(new TaskNotification($task, [
    'complete' => '✅ Complete',
    'reject' => '❌ Reject',
    'request_info' => 'ℹ️ More Info'
]));
```

## Testing
Run the test suite:

```bash
composer test
```

Generate test coverage:

```bash
composer test-coverage
```

## Security Considerations
- Always use HTTPS for webhooks
- Regularly rotate bot tokens
- Implement IP whitelisting for webhook endpoints
- Validate all incoming Telegram updates

## Troubleshooting
**Webhook not working:**
- Verify SSL certificate is valid
- Check bot token is correct
- Ensure the webhook URL is accessible

**Notifications not arriving:**
- Confirm user has connected Telegram account
- Check queue workers are running
- Verify Telegram chat ID is stored correctly

## Contributing
- Fork the project
- Create your feature branch
- Submit a pull request

## License
@WeCanCo