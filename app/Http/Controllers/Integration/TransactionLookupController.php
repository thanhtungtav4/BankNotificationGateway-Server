<?php

namespace App\Http\Controllers\Integration;

use App\Http\Controllers\Controller;
use App\Models\ParsedTransaction;
use Illuminate\Http\Request;

class TransactionLookupController extends Controller
{
    public function lookup(Request $request)
    {
        $request->validate([
            'booking_id' => 'required|string',
            'amount' => 'required|numeric',
        ]);

        $bookingId = $request->input('booking_id');
        $amount = $request->input('amount');

        // Find matching transaction for the authenticated tenant user's tenant
        $transaction = ParsedTransaction::where('tenant_id', $request->user()->tenant_id)
            ->where('order_code', $bookingId)
            ->where('amount', '>=', $amount)
            ->where('status', 'parsed')
            ->first();

        if ($transaction) {
            return response()->json([
                'success' => true,
                'found' => true,
                'transaction' => [
                    'transaction_id' => $transaction->id,
                    'bank_name' => $transaction->bank_name,
                    'amount' => $transaction->amount,
                    'content' => $transaction->transfer_content,
                    'order_code' => $transaction->order_code,
                    'transaction_time' => $transaction->created_at->toIso8601String(),
                ]
            ]);
        }

        return response()->json([
            'success' => true,
            'found' => false
        ]);
    }
}
