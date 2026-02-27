<?php

namespace App\Http\Controllers;

use App\Jobs\ProcessPaymentJob;
use App\Models\Pembayaran as ModelsPembayaran;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class Pembayaran extends Controller
{
    public function store(Request $request)
    {
        $request->validate([
            'amount' => 'required' // hanya required
        ]);

        $paymentId = (string) Str::uuid();
        $orderId   = $request->loan;

        $payment = ModelsPembayaran::create([
            'id' => $paymentId,
            'order_id' => $orderId,
            'amount' => $request->amount, // bisa string
            'status' => 'PENDING',
            'gateway_response' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        ProcessPaymentJob::dispatch($payment->id);

        return response()->json([
            'message' => 'Payment created successfully',
            'data' => $payment
        ], 201);
    }
}
