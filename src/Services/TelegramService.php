<?php

namespace ProcessMaker\TelegramPlugin\Services;

use GuzzleHttp\Client;
use Illuminate\Support\Facades\Log;

class TelegramService
{
    protected $client;
    protected $botToken;
    protected $apiUrl = 'https://api.telegram.org/bot';

    public function __construct($botToken)
    {
        $this->botToken = $botToken;
        $this->client = new Client([
            'base_uri' => $this->apiUrl . $this->botToken . '/',
            'timeout' => 5.0,
        ]);
    }

    public function sendMessage($chatId, $text, $buttons = [])
    {
        return $this->makeRequest('sendMessage', [
            'chat_id' => $chatId,
            'text' => $text,
            'parse_mode' => 'HTML',
            'reply_markup' => $this->buildReplyMarkup($buttons),
            'disable_web_page_preview' => true
        ]);
    }

    public function editMessage($chatId, $messageId, $text, $buttons = [])
    {
        return $this->makeRequest('editMessageText', [
            'chat_id' => $chatId,
            'message_id' => $messageId,
            'text' => $text,
            'parse_mode' => 'HTML',
            'reply_markup' => $this->buildReplyMarkup($buttons)
        ]);
    }

    public function deleteMessage($chatId, $messageId)
    {
        return $this->makeRequest('deleteMessage', [
            'chat_id' => $chatId,
            'message_id' => $messageId
        ]);
    }

    protected function buildReplyMarkup($buttons)
    {
        if (empty($buttons)) return null;

        $inlineKeyboard = array_chunk(array_map(function ($button) {
            return [
                'text' => $button['text'],
                'callback_data' => $button['action']
            ];
        }, $buttons), 2); // 2 buttons per row

        return json_encode(['inline_keyboard' => $inlineKeyboard]);
    }

    protected function makeRequest($endpoint, $data)
    {
        try {
            $response = $this->client->post($endpoint, [
                'json' => array_filter($data),
                'timeout' => 10,
                'connect_timeout' => 5
            ]);

            $result = json_decode($response->getBody(), true);

            if (!$result['ok']) {
                Log::error("Telegram API error: {$result['description']}", $data);
            }

            return $result;

        } catch (GuzzleException $e) {
            Log::error("Telegram API request failed: " . $e->getMessage());
            return false;
        }
    }

//    public function setWebhook($url)
//    {
//        try {
//            $response = $this->client->post('setWebhook', [
//                'json' => ['url' => $url]
//            ]);
//
//            return json_decode($response->getBody(), true);
//        } catch (\Exception $e) {
//            Log::error('Telegram setWebhook error: ' . $e->getMessage());
//            return false;
//        }
//    }

    public function setWebhook($url)
    {
        try {
            $response = $this->client->post('setWebhook', [
                'json' => [
                    'url' => $url,
                    'drop_pending_updates' => true,
                    'allowed_updates' => ['message', 'callback_query']
                ]
            ]);

            return json_decode($response->getBody(), true);
        } catch (\Exception $e) {
            Log::error('Telegram setWebhook error: ' . $e->getMessage());
            return false;
        }
    }

    public function answerCallbackQuery($callbackQueryId, $text = null)
    {
        try {
            $response = $this->client->post('answerCallbackQuery', [
                'json' => array_filter([
                    'callback_query_id' => $callbackQueryId,
                    'text' => $text,
                    'show_alert' => (bool)$text
                ])
            ]);

            return json_decode($response->getBody(), true);
        } catch (\Exception $e) {
            Log::error('Telegram answerCallbackQuery error: ' . $e->getMessage());
            return false;
        }
    }

    public function deleteWebhook()
    {
        try {
            $response = $this->client->post('deleteWebhook', [
                'json' => ['drop_pending_updates' => true]
            ]);

            return json_decode($response->getBody(), true);
        } catch (\Exception $e) {
            Log::error('Telegram deleteWebhook error: ' . $e->getMessage());
            return false;
        }
    }
}