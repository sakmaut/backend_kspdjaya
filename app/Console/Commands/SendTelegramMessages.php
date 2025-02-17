<?php

namespace App\Console\Commands;

use App\Http\Controllers\API\TelegramBotConfig;
use App\Models\M_Branch;
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
            $getJson = $message->messages;

            if (!empty($getJson)) {
                $convert = json_decode($getJson, true);
                $findBRanch = M_Branch::find($convert['BRANCH_CODE']);

                // Wrap ternary operator in parentheses for correct evaluation
                $buildMsg = ($findBRanch ? strtoupper($findBRanch->NAME) : '') . " MINTA APPROVAL PEMBAYARAN " . "\n" .
                    'Tgl Transaksi = ' . $convert['TGL_TRANSAKSI'] . "\n" .
                    'Tipe = ' . $convert['PAYMENT_TYPE'] . "\n" .
                    'Metode Bayar = ' . $convert['METODE_PEMBAYARAN'] . "\n" .
                    'Total Bayar = ' . number_format($convert['TOTAL_BAYAR']) . "\n" .
                    'Status = Pending' . "\n" .
                    'No Transaksi = ' . $convert['NO_TRANSAKSI'] . "\n" .
                    'No Kontrak = ' . $convert['LOAN_NUMBER'];

                TelegramBotConfig::sendMessage($buildMsg);

                $update = M_TelegramBotSend::find($message->id);

                if ($update) {
                    $update->update(['status' => 'send']);
                }
            }
        }
    }
}
