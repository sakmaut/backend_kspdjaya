<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class TelegramBotConfig extends Controller
{
    function sendMessage($message)
    {
        $chat_id = "1268179862";
        $bot_token = "7442053832:AAG3pOPydTmWRiOaMbSPHVuNYMeNuiUKtS4";
        // Telegram API URL
        $url = "https://api.telegram.org/bot$bot_token/sendMessage";

        // Parameters to send in the HTTP request
        $data = [
            'chat_id' => $chat_id,
            'text' => $message
        ];

        // Use http_build_query to encode the parameters
        $query_data = http_build_query($data);

        // Make the HTTP request to send the message
        $response = file_get_contents($url . '?' . $query_data);

        // Optionally, you can check the response to ensure success
        if ($response) {
            echo "Message sent successfully!";
        } else {
            echo "Failed to send message.";
        }
    }
}
