<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\M_TelegramBotSend;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class TelegramBotConfig extends Controller
{

    protected $entity;
    protected $request;

    function __construct(M_TelegramBotSend $entity, Request $request)
    {
        $this->entity = $entity;
        $this->request = $request;
    }

    function create($data)
    {
        DB::beginTransaction();
        try {
            $this->entity::create([
                'endpoint' => $this->request->url(),
                'messages' => json_encode($data),
                'status' => 'new',
                "created_at" => Carbon::now()
            ]);

            DB::commit();
            return response()->json(['message' => 'OK'], 200);
        } catch (\Exception $e) {
            DB::rollback();
            return response()->json(['message' => $e->getMessage(), "status" => 500], 500);
        }
    }

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
