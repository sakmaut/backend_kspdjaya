<?php

namespace App\Jobs;

use App\Models\Pembayaran;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ProcessPaymentJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Create a new job instance.
     */
     public function __construct(public $paymentId) {}

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $payment = Pembayaran::where('order_id',$this->paymentId)->first();

        if (!$payment) {
            return;
        }

        // ✅ VALIDASI STATUS DI SINI
        if (!in_array($payment->status, ['PENDING', 'FAILED'])) {
            return;
        }

        $payment->update([
            'status' => 'PROCESSING'
        ]);

        try {

            sleep(3);

            $payment->update([
                'status' => 'SUCCESS',
                'gateway_response' => 'Payment successful'
            ]);
        } catch (\Exception $e) {

            $payment->update([
                'status' => 'FAILED',
                'gateway_response' => $e->getMessage()
            ]);

            throw $e;
        }
    }
}
