<?php

namespace ProcessMaker\TelegramPlugin\Services;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Exception\RequestException;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

class TelegramService
{
    protected Client $client;
    protected string $botToken;
    protected string $baseUrl;
    protected array $config;

    /**
     * @param string $botToken
     * @param string|null $baseUrl
     */
    public function __construct(string $botToken, ?string $baseUrl = null)
    {
        $this->botToken = $botToken;
        $this->baseUrl = $baseUrl ?? 'https://api.telegram.org/bot';
        $this->config = config('telegram', []);

        $this->client = new Client([
            'base_uri' => $this->baseUrl . $this->botToken . '/',
            'timeout' => $this->config['notifications']['timeout'] ?? 30,
            'connect_timeout' => 10,
            'verify' => true,
            'headers' => [
                'User-Agent' => 'ProcessMaker-Telegram-Plugin/1.0'
            ]
        ]);
    }

    /**
     * Send message to Telegram chat
     */
    public function sendMessage(string $chatId, string $text, array $buttons = [], array $options = []): array|false
    {
        $payload = array_merge([
            'chat_id' => $chatId,
            'text' => $this->truncateText($text, 4096),
            'parse_mode' => 'HTML',
            'disable_web_page_preview' => true,
            'disable_notification' => $options['silent'] ?? false,
        ], $options);

        if (!empty($buttons)) {
            $payload['reply_markup'] = $this->buildReplyMarkup($buttons);
        }

        return $this->makeRequest('sendMessage', $payload);
    }

    /**
     * Edit existing message
     */
    public function editMessage(string $chatId, int $messageId, string $text, array $buttons = []): array|false
    {
        $payload = [
            'chat_id' => $chatId,
            'message_id' => $messageId,
            'text' => $this->truncateText($text, 4096),
            'parse_mode' => 'HTML',
        ];

        if (!empty($buttons)) {
            $payload['reply_markup'] = $this->buildReplyMarkup($buttons);
        }

        return $this->makeRequest('editMessageText', $payload);
    }

    /**
     * Delete message
     */
    public function deleteMessage(string $chatId, int $messageId): array|false
    {
        return $this->makeRequest('deleteMessage', [
            'chat_id' => $chatId,
            'message_id' => $messageId
        ]);
    }

    /**
     * Answer callback query
     */
    public function answerCallbackQuery(string $callbackQueryId, ?string $text = null, bool $showAlert = false): array|false
    {
        $payload = array_filter([
            'callback_query_id' => $callbackQueryId,
            'text' => $text ? $this->truncateText($text, 200) : null,
            'show_alert' => $showAlert
        ]);

        return $this->makeRequest('answerCallbackQuery', $payload);
    }

    /**
     * Set webhook URL
     */
    public function setWebhook(string $url, array $options = []): array|false
    {
        $payload = array_merge([
            'url' => $url,
            'drop_pending_updates' => true,
            'allowed_updates' => $this->config['webhook']['allowed_updates'] ?? ['message', 'callback_query'],
            'max_connections' => $this->config['webhook']['max_connections'] ?? 40,
        ], $options);

        return $this->makeRequest('setWebhook', $payload);
    }

    /**
     * Delete webhook
     */
    public function deleteWebhook(bool $dropPendingUpdates = true): array|false
    {
        return $this->makeRequest('deleteWebhook', [
            'drop_pending_updates' => $dropPendingUpdates
        ]);
    }

    /**
     * Get webhook info
     */
    public function getWebhookInfo(): array|false
    {
        return $this->makeRequest('getWebhookInfo');
    }

    /**
     * Get bot info
     */
    public function getMe(): array|false
    {
        $cacheKey = "telegram_bot_info_{$this->botToken}";

        return Cache::remember($cacheKey, 3600, function () {
            return $this->makeRequest('getMe');
        });
    }

    /**
     * Build inline keyboard markup
     */
    protected function buildReplyMarkup(array $buttons): string
    {
        if (empty($buttons)) {
            return json_encode(['inline_keyboard' => []]);
        }

        // Group buttons into rows (max 8 buttons per row for better UX)
        $rows = [];
        $currentRow = [];

        foreach ($buttons as $button) {
            if (!isset($button['text']) || !isset($button['action'])) {
                continue;
            }

            $currentRow[] = [
                'text' => $this->truncateText($button['text'], 64),
                'callback_data' => substr($button['action'], 0, 64)
            ];

            // Start new row after 2 buttons (optimal for mobile)
            if (count($currentRow) >= 2) {
                $rows[] = $currentRow;
                $currentRow = [];
            }
        }

        // Add remaining buttons
        if (!empty($currentRow)) {
            $rows[] = $currentRow;
        }

        return json_encode(['inline_keyboard' => $rows]);
    }

    /**
     * Make HTTP request to Telegram API
     */
    protected function makeRequest(string $endpoint, array $data = []): array|false
    {
        $maxRetries = $this->config['notifications']['retry_attempts'] ?? 3;
        $attempt = 0;

        while ($attempt < $maxRetries) {
            try {
                $response = $this->client->post($endpoint, [
                    'json' => array_filter($data, function ($value) {
                        return $value !== null && $value !== '';
                    })
                ]);

                $result = json_decode($response->getBody()->getContents(), true);

                if (!is_array($result)) {
                    throw new \RuntimeException('Invalid JSON response from Telegram API');
                }

                if (!($result['ok'] ?? false)) {
                    $errorCode = $result['error_code'] ?? 0;
                    $description = $result['description'] ?? 'Unknown error';

                    // Handle rate limiting
                    if ($errorCode === 429) {
                        $retryAfter = $result['parameters']['retry_after'] ?? 60;
                        Log::warning("Telegram rate limit hit, retry after {$retryAfter}s", [
                            'endpoint' => $endpoint,
                            'retry_after' => $retryAfter
                        ]);

                        if ($attempt < $maxRetries - 1) {
                            sleep(min($retryAfter, 60));
                            $attempt++;
                            continue;
                        }
                    }

                    Log::error("Telegram API error [{$errorCode}]: {$description}", [
                        'endpoint' => $endpoint,
                        'data' => $data,
                        'attempt' => $attempt + 1
                    ]);
                }

                return $result;

            } catch (RequestException $e) {
                $statusCode = $e->getResponse() ? $e->getResponse()->getStatusCode() : 0;

                Log::error("Telegram API request failed: " . $e->getMessage(), [
                    'endpoint' => $endpoint,
                    'status_code' => $statusCode,
                    'attempt' => $attempt + 1
                ]);

                // Retry on server errors (5xx) or network issues
                if ($statusCode >= 500 || $statusCode === 0) {
                    if ($attempt < $maxRetries - 1) {
                        sleep(pow(2, $attempt)); // Exponential backoff
                        $attempt++;
                        continue;
                    }
                }

                break;

            } catch (GuzzleException $e) {
                Log::error("Telegram HTTP client error: " . $e->getMessage(), [
                    'endpoint' => $endpoint,
                    'attempt' => $attempt + 1
                ]);
                break;
            }
        }

        return false;
    }

    /**
     * Truncate text to fit Telegram limits
     */
    protected function truncateText(string $text, int $limit): string
    {
        if (mb_strlen($text) <= $limit) {
            return $text;
        }

        return mb_substr($text, 0, $limit - 3) . '...';
    }

    /**
     * Validate bot token format
     */
    public static function validateToken(string $token): bool
    {
        return preg_match('/^\d{8,10}:[a-zA-Z0-9_-]{35}$/', $token) === 1;
    }

    /**
     * Get bot username from token info
     */
    public function getBotUsername(): ?string
    {
        $botInfo = $this->getMe();
        return $botInfo['result']['username'] ?? null;
    }
}