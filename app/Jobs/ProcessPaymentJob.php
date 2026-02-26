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
        $payment = Pembayaran::find($this->paymentId);

        if (!$payment) return;

        $payment->update([
            'status' => 'PROCESSING'
        ]);

        try {

            // VALIDASI TYPE STRICT
            if (!is_int($payment->amount)) {
                throw new \Exception("Amount must be integer, string detected.");
            }

            if ($payment->amount < 1000) {
                throw new \Exception("Minimum amount is 1000.");
            }

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

            throw $e; // supaya masuk failed_jobs
        }
    }
}
