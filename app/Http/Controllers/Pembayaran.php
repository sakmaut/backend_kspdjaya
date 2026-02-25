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
            'amount' => 'required|numeric'
        ]);

        $orderId = Str::uuid();

        $payment = ModelsPembayaran::create([
            'order_id' => $orderId,
            'amount' => $request->amount,
            'status' => 'PENDING'
        ]);

        ProcessPaymentJob::dispatch($payment->id);

        return response()->json([
            'message' => 'Payment created',
            'data' => $payment
        ]);
    }
}
