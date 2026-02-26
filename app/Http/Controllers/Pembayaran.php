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
        // Validasi
        $request->validate([
            'amount' => 'required|numeric|min:1000'
        ]);

        $orderId   = (string) Str::uuid();

        // Simpan ke database
        $payment = ModelsPembayaran::create([
            'order_id' => $orderId,
            'amount' => $request->amount,
            'status' => 'PENDING',
            'gateway_response' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Dispatch ke Job (Queue)
        ProcessPaymentJob::dispatch($payment->id);

        return response()->json([
            'message' => 'Payment created successfully',
            'data' => $payment
        ], 201);
    }
}
