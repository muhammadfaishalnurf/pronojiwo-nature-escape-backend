<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Ticket;
use App\Models\Payment;
use App\Models\Destination;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class PaymentController extends Controller
{
    private function serverKey()
    {
        return config('midtrans.server_key', env('MIDTRANS_SERVER_KEY', ''));
    }

    // POST /api/v1/payments/create
    public function create(Request $request)
    {
        $request->validate([
            'destination_id' => 'required|exists:destinations,id',
            'visit_date'     => 'required|date',
            'quantity'       => 'required|integer|min:1|max:50',
            'nama_ketua'     => 'required|string|max:255',
            'jenis_kelamin'  => 'required|in:laki-laki,perempuan',
            'no_hp'          => 'required|string|max:20',
            'kebangsaan'     => 'required|string|max:100',
        ]);

        $destination = Destination::findOrFail($request->destination_id);
        $subtotal    = $destination->harga_tiket * $request->quantity;
        $tax         = round($subtotal * 0.05);
        $total       = $subtotal + $tax;
        $orderId     = 'ORDER-' . strtoupper(Str::random(10)) . '-' . time();
        $user        = auth()->user();

        $ticket = Ticket::create([
            'user_id'        => $user->id,
            'destination_id' => $request->destination_id,
            'ticket_code'    => 'TKT-' . strtoupper(substr(uniqid(), -6)),
            'nama_ketua'     => $request->nama_ketua,
            'jenis_kelamin'  => $request->jenis_kelamin,
            'no_hp'          => $request->no_hp,
            'kebangsaan'     => $request->kebangsaan,
            'visit_date'     => $request->visit_date,
            'quantity'       => $request->quantity,
            'total_price'    => $total,
            'status'         => 'pending',
            'order_id'       => $orderId,
        ]);

        Payment::create([
            'ticket_id'      => $ticket->id,
            'user_id'        => $user->id,
            'order_id'       => $orderId,
            'amount'         => $total,
            'status'         => 'pending',
            'payment_method' => null,
        ]);

        $payload = [
            'transaction_details' => [
                'order_id'     => $orderId,
                'gross_amount' => (int) $total,
            ],
            'item_details' => [
                [
                    'id'       => (string) $destination->id,
                    'price'    => (int) $destination->harga_tiket,
                    'quantity' => $request->quantity,
                    'name'     => $destination->nama_wisata,
                ],
                [
                    'id'       => 'tax',
                    'price'    => (int) $tax,
                    'quantity' => 1,
                    'name'     => 'Biaya Layanan (5%)',
                ],
            ],
            'customer_details' => [
                'first_name' => $request->nama_ketua,
                'email'      => $user->email,
                'phone'      => $request->no_hp,
            ],
            'callbacks' => [
                'finish' => env('FRONTEND_URL', 'http://localhost:5173') . '/pembayaran/selesai',
            ],
        ];

        $response = Http::withBasicAuth($this->serverKey(), '')
            ->withHeaders(['Content-Type' => 'application/json'])
            ->post('https://app.sandbox.midtrans.com/snap/v1/transactions', $payload);

        if ($response->failed()) {
            Payment::where('order_id', $orderId)->delete();
            $ticket->delete();
            return response()->json([
                'message' => 'Gagal membuat transaksi Midtrans.',
                'error'   => $response->json()
            ], 500);
        }

        $snapData = $response->json();

        return response()->json([
            'snap_token' => $snapData['token'],
            'snap_url'   => $snapData['redirect_url'],
            'order_id'   => $orderId,
            'ticket_id'  => $ticket->id,
            'total'      => $total,
        ]);
    }

    // POST /api/v1/payments/notification — webhook Midtrans
    public function notification(Request $request)
    {
        $data        = $request->all();
        $orderId     = $data['order_id']           ?? null;
        $txStatus    = $data['transaction_status'] ?? null;
        $fraudStatus = $data['fraud_status']       ?? null;

        if (!$orderId) return response()->json(['message' => 'Invalid'], 400);

        $serverKey    = $this->serverKey();
        $grossAmount  = $data['gross_amount'] ?? '0.00';
        $statusCode   = $data['status_code']  ?? '200';
        $signatureKey = hash('sha512', $orderId . $statusCode . $grossAmount . $serverKey);

        if ($signatureKey !== ($data['signature_key'] ?? '')) {
            return response()->json(['message' => 'Invalid signature'], 403);
        }

        $this->updateStatusByOrderId($orderId, $txStatus, $fraudStatus, $data);
        return response()->json(['message' => 'OK']);
    }

    // POST /api/v1/payments/check-status — cek & update status dari frontend
    public function checkStatus(Request $request)
    {
        $request->validate(['order_id' => 'required|string']);
        $orderId = $request->order_id;

        $response = Http::withBasicAuth($this->serverKey(), '')
            ->get("https://api.sandbox.midtrans.com/v2/{$orderId}/status");

        if ($response->failed()) {
            return response()->json(['message' => 'Gagal cek status'], 500);
        }

        $data        = $response->json();
        $txStatus    = $data['transaction_status'] ?? null;
        $fraudStatus = $data['fraud_status']       ?? null;
        $newStatus   = $this->updateStatusByOrderId($orderId, $txStatus, $fraudStatus, $data);

        return response()->json([
            'order_id'           => $orderId,
            'transaction_status' => $txStatus,
            'status'             => $newStatus,
            'payment_type'       => $data['payment_type'] ?? null,
        ]);
    }

    // GET /api/v1/payments/status/{orderId}
    public function status($orderId)
    {
        $payment = Payment::where('order_id', $orderId)
            ->where('user_id', auth()->id())
            ->with('ticket.destination')
            ->firstOrFail();

        return response()->json(['data' => $payment]);
    }

    // Helper — update status
    private function updateStatusByOrderId($orderId, $txStatus, $fraudStatus, $data)
    {
        $newStatus = 'pending';

        if ($txStatus === 'capture') {
            $newStatus = ($fraudStatus === 'challenge') ? 'pending' : 'confirmed';
        } elseif ($txStatus === 'settlement') {
            $newStatus = 'confirmed';
        } elseif (in_array($txStatus, ['cancel', 'deny', 'expire'])) {
            $newStatus = 'cancelled';
        } elseif ($txStatus === 'pending') {
            $newStatus = 'pending';
        }

        $payment = Payment::where('order_id', $orderId)->first();
        $ticket  = Ticket::where('order_id', $orderId)->first();

        if ($payment) {
            $payment->update([
                'status'         => $newStatus,
                'payment_method' => $data['payment_type'] ?? $payment->payment_method,
                'paid_at'        => $newStatus === 'confirmed' ? now() : $payment->paid_at,
                'midtrans_data'  => json_encode($data),
            ]);
        }

        if ($ticket) {
            $ticket->update(['status' => $newStatus]);
        }

        return $newStatus;
    }
}
