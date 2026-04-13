<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class TelegramService
{
    /**
     * Send a text message to the configured Telegram chat.
     *
     * @param string $message
     * @return bool
     */
    public function sendMessage(string $message): bool
    {
        $botToken = config('services.telegram.bot_token');
        $chatId = config('services.telegram.chat_id');

        if (empty($botToken) || empty($chatId)) {
            Log::warning('Telegram bot_token or chat_id not configured.');
            return false;
        }

        try {
            $url = "https://api.telegram.org/bot{$botToken}/sendMessage";
            $response = Http::timeout(5)->post($url, [
                'chat_id' => $chatId,
                'text' => $message,
                'parse_mode' => 'Markdown',
            ]);

            if ($response->successful()) {
                return true;
            }

            Log::error("Telegram API Error: " . $response->body());
            return false;
        } catch (\Exception $e) {
            Log::error("Telegram Exception: " . $e->getMessage());
            return false;
        }
    }
}
