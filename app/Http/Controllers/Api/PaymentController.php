<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Ticket;
use App\Models\Destination;
use App\Models\Payment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class PaymentController extends Controller
{
    private function midtransServerKey()
    {
        return config('midtrans.server_key', env('MIDTRANS_SERVER_KEY', ''));
    }

    private function midtransBaseUrl()
    {
        return config('midtrans.is_production', false)
            ? 'https://app.midtrans.com/snap/v1'
            : 'https://app.sandbox.midtrans.com/snap/v1';
    }

    private function midtransApiUrl()
    {
        return config('midtrans.is_production', false)
            ? 'https://api.midtrans.com/v2'
            : 'https://api.sandbox.midtrans.com/v2';
    }

    // POST /api/v1/payments/create
    public function create(Request $request)
    {
        $request->validate([
            'destination_id' => 'required|exists:destinations,id',
            'visit_date'     => 'required|date',
            'quantity'       => 'required|integer|min:1|max:50',
            'nama_ketua'     => 'required|string',
            'jenis_kelamin'  => 'required|in:laki-laki,perempuan',
            'no_hp'          => 'required|string',
            'kebangsaan'     => 'required|string',
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
            'visit_date'     => $request->visit_date,
            'quantity'       => $request->quantity,
            'total_price'    => $total,
            'status'         => 'pending',
            'order_id'       => $orderId,
            'nama_ketua'     => $request->nama_ketua,
            'jenis_kelamin'  => $request->jenis_kelamin,
            'no_hp'          => $request->no_hp,
            'kebangsaan'     => $request->kebangsaan,
        ]);

        $snapData = $this->createMidtransTransaction(
            $orderId, $total, $destination, $request->quantity, $tax, $user
        );

        if (!$snapData) {
            $ticket->delete();
            return response()->json(['message' => 'Gagal membuat transaksi Midtrans.'], 500);
        }

        // Cek apakah kolom snap_token ada di tabel payments
        $paymentData = [
            'ticket_id'      => $ticket->id,
            'user_id'        => $user->id,
            'order_id'       => $orderId,
            'amount'         => $total,
            'status'         => 'pending',
            'payment_method' => null,
        ];

        // Tambah snap_token kalau kolomnya ada
        if (\Schema::hasColumn('payments', 'snap_token')) {
            $paymentData['snap_token'] = $snapData['token'];
        }

        Payment::create($paymentData);

        return response()->json([
            'snap_token' => $snapData['token'],
            'snap_url'   => $snapData['redirect_url'],
            'order_id'   => $orderId,
            'ticket_id'  => $ticket->id,
            'total'      => $total,
        ]);
    }

    // POST /api/v1/payments/check-status
    public function checkStatus(Request $request)
    {
        $request->validate(['order_id' => 'required|string']);
        $orderId = $request->order_id;

        $payment = Payment::where('order_id', $orderId)
            ->where('user_id', auth()->id())
            ->with('ticket.destination')
            ->first();

        if (!$payment) {
            return response()->json(['message' => 'Order tidak ditemukan.'], 404);
        }

        if (in_array($payment->status, ['confirmed', 'cancelled'])) {
            return response()->json(['data' => $payment]);
        }

        // Cek ke Midtrans API
        $response = Http::withBasicAuth($this->midtransServerKey(), '')
            ->get($this->midtransApiUrl() . '/' . $orderId . '/status');

        if ($response->successful()) {
            $data        = $response->json();
            $txStatus    = $data['transaction_status'] ?? 'pending';
            $fraudStatus = $data['fraud_status'] ?? null;

            $newStatus = 'pending';
            if ($txStatus === 'capture') {
                $newStatus = ($fraudStatus === 'challenge') ? 'pending' : 'confirmed';
            } elseif ($txStatus === 'settlement') {
                $newStatus = 'confirmed';
            } elseif (in_array($txStatus, ['cancel', 'deny', 'expire'])) {
                $newStatus = 'cancelled';
            }

            if ($newStatus !== 'pending') {
                $payment->update([
                    'status'         => $newStatus,
                    'payment_method' => $data['payment_type'] ?? null,
                    'paid_at'        => $newStatus === 'confirmed' ? now() : null,
                ]);
                $payment->ticket->update(['status' => $newStatus]);
                $payment->refresh();
            }
        }

        return response()->json(['data' => $payment->load('ticket.destination')]);
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

    // GET /api/v1/payments/pending
    public function pendingList()
    {
        $payments = Payment::where('user_id', auth()->id())
            ->where('status', 'pending')
            ->with('ticket.destination')
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json(['data' => $payments]);
    }

    // POST /api/v1/payments/{orderId}/reopen
    // Buka ulang popup Midtrans untuk tiket pending
    public function reopen($orderId)
    {
        try {
            $payment = Payment::where('order_id', $orderId)
                ->where('user_id', auth()->id())
                ->where('status', 'pending')
                ->with('ticket.destination')
                ->first();

            if (!$payment) {
                return response()->json([
                    'message' => 'Order tidak ditemukan atau sudah selesai.'
                ], 404);
            }

            $ticket      = $payment->ticket;
            $destination = $ticket?->destination;

            if (!$ticket || !$destination) {
                return response()->json([
                    'message' => 'Data tiket tidak lengkap.'
                ], 422);
            }

            // Cek apakah snap_token masih ada & belum 24 jam
            $hasSnapToken   = \Schema::hasColumn('payments', 'snap_token');
            $existingToken  = $hasSnapToken ? $payment->snap_token : null;
            $createdHoursAgo = $payment->created_at->diffInHours(now());
            $isExpired      = $createdHoursAgo >= 24;

            // Kalau token masih ada dan belum expired, pakai yang lama
            if ($existingToken && !$isExpired) {
                return response()->json([
                    'snap_token' => $existingToken,
                    'order_id'   => $orderId,
                ]);
            }

            // Buat snap_token baru ke Midtrans
            $user     = auth()->user();
            $quantity = $ticket->quantity;
            $total    = $payment->amount;
            $tax      = round($total - ($destination->harga_tiket * $quantity));

            $snapData = $this->createMidtransTransaction(
                $orderId,
                $total,
                $destination,
                $quantity,
                $tax,
                $user
            );

            if (!$snapData) {
                return response()->json([
                    'message' => 'Gagal membuat ulang transaksi. Coba beberapa saat lagi.'
                ], 500);
            }

            // Simpan snap_token baru kalau kolomnya ada
            if ($hasSnapToken) {
                $payment->update(['snap_token' => $snapData['token']]);
            }

            return response()->json([
                'snap_token' => $snapData['token'],
                'order_id'   => $orderId,
            ]);

        } catch (\Exception $e) {
            \Log::error('Payment reopen error: ' . $e->getMessage(), [
                'order_id' => $orderId,
                'trace'    => $e->getTraceAsString(),
            ]);

            return response()->json([
                'message' => 'Terjadi kesalahan: ' . $e->getMessage()
            ], 500);
        }
    }

    // POST /api/v1/payments/notification — Webhook Midtrans
    public function notification(Request $request)
    {
        $data        = $request->all();
        $orderId     = $data['order_id']           ?? null;
        $txStatus    = $data['transaction_status'] ?? null;
        $fraudStatus = $data['fraud_status']       ?? null;

        if (!$orderId) {
            return response()->json(['message' => 'Invalid notification'], 400);
        }

        $serverKey    = $this->midtransServerKey();
        $grossAmount  = $data['gross_amount'] ?? '0.00';
        $statusCode   = $data['status_code']  ?? '200';
        $signatureKey = hash('sha512', $orderId . $statusCode . $grossAmount . $serverKey);

        if ($signatureKey !== ($data['signature_key'] ?? '')) {
            return response()->json(['message' => 'Invalid signature'], 403);
        }

        $payment = Payment::where('order_id', $orderId)->first();
        $ticket  = Ticket::where('order_id', $orderId)->first();

        if (!$payment || !$ticket) {
            return response()->json(['message' => 'Order not found'], 404);
        }

        $newStatus = 'pending';
        if ($txStatus === 'capture') {
            $newStatus = ($fraudStatus === 'challenge') ? 'pending' : 'confirmed';
        } elseif ($txStatus === 'settlement') {
            $newStatus = 'confirmed';
        } elseif (in_array($txStatus, ['cancel', 'deny', 'expire'])) {
            $newStatus = 'cancelled';
        }

        $payment->update([
            'status'         => $newStatus,
            'payment_method' => $data['payment_type'] ?? null,
            'paid_at'        => $newStatus === 'confirmed' ? now() : null,
            'midtrans_data'  => json_encode($data),
        ]);

        $ticket->update(['status' => $newStatus]);

        return response()->json(['message' => 'OK']);
    }

    // Helper — buat transaksi ke Midtrans Snap API
    private function createMidtransTransaction($orderId, $total, $destination, $quantity, $tax, $user)
    {
        $hargaSatuan = $destination->harga_tiket;

        $payload = [
            'transaction_details' => [
                'order_id'     => $orderId,
                'gross_amount' => (int) $total,
            ],
            'item_details' => [
                [
                    'id'       => (string) $destination->id,
                    'price'    => (int) $hargaSatuan,
                    'quantity' => (int) $quantity,
                    'name'     => substr($destination->nama_wisata, 0, 50),
                ],
                [
                    'id'       => 'tax',
                    'price'    => (int) $tax,
                    'quantity' => 1,
                    'name'     => 'Biaya Layanan (5%)',
                ],
            ],
            'customer_details' => [
                'first_name' => $user->name,
                'email'      => $user->email,
            ],
            'callbacks' => [
                'finish' => env('FRONTEND_URL', 'http://localhost:5173') . '/pembayaran/selesai',
            ],
        ];

        try {
            $response = Http::withBasicAuth($this->midtransServerKey(), '')
                ->withHeaders(['Content-Type' => 'application/json'])
                ->post($this->midtransBaseUrl() . '/transactions', $payload);

            if ($response->successful()) {
                return $response->json();
            }

            \Log::error('Midtrans API error', [
                'status'   => $response->status(),
                'response' => $response->json(),
                'order_id' => $orderId,
            ]);

            return null;
        } catch (\Exception $e) {
            \Log::error('Midtrans request exception: ' . $e->getMessage());
            return null;
        }
    }
}
