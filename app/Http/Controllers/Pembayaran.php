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
            'amount' => 'required'
        ]);

        $orderId = $request->loan;

        // 🔍 Cek payment terakhir dengan order_id sama
        $existingPayment = ModelsPembayaran::where('order_id', $orderId)
            ->latest()
            ->first();

        // ❌ Kalau masih processing → tolak
        if ($existingPayment && $existingPayment->status === 'PROCESSING') {
            return response()->json([
                'message' => 'Payment still processing'
            ], 409);
        }

        // ❌ Kalau masih pending → tolak
        if ($existingPayment && $existingPayment->status === 'PENDING') {
            return response()->json([
                'message' => 'Payment already pending'
            ], 409);
        }

        // ✅ Kalau SUCCESS → boleh lanjut buat baru
        // ✅ Kalau FAILED → juga boleh buat baru

        $payment = ModelsPembayaran::create([
            'id' => (string) Str::uuid(),
            'order_id' => $orderId,
            'amount' => $request->amount,
            'status' => 'PENDING',
            'gateway_response' => null,
        ]);

        ProcessPaymentJob::dispatch($payment->id);

        return response()->json([
            'message' => 'Payment created successfully',
            'data' => $payment
        ], 201);
    }
}
