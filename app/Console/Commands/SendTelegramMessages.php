<?php

namespace App\Console\Commands;

use App\Http\Controllers\API\TelegramBotConfig;
use App\Models\M_TelegramBotSend;
use Illuminate\Console\Command;

class SendTelegramMessages extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:send-telegram-messages';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    public function handle()
    {
        $newMessages = M_TelegramBotSend::where('status', 'new')->get();

        foreach ($newMessages as $message) {

            $response = TelegramBotConfig::sendMessage($message->messages);

            if ($response) {
                $message->status = 'send';
                $message->save();
            }
        }
    }
}
