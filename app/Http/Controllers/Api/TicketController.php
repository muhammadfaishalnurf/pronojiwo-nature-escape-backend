<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Ticket;
use App\Models\Destination;
use Illuminate\Http\Request;

class TicketController extends Controller
{
    // GET /api/v1/tickets — tiket milik user login
    public function index()
    {
        $tickets = Ticket::with('destination')
            ->where('user_id', auth()->id())
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(fn($t) => $this->withQr($t));

        return response()->json(['data' => $tickets]);
    }

    // POST /api/v1/tickets
    public function store(Request $request)
    {
        $request->validate([
            'destination_id' => 'required|exists:destinations,id',
            'visit_date'     => 'required|date',
            'quantity'       => 'required|integer|min:1|max:50',
        ]);

        $destination = Destination::findOrFail($request->destination_id);
        $subtotal    = $destination->harga_tiket * $request->quantity;
        $tax         = round($subtotal * 0.05);
        $total       = $subtotal + $tax;

        $ticket = Ticket::create([
            'user_id'        => auth()->id(),
            'destination_id' => $request->destination_id,
            'ticket_code'    => 'TKT-' . strtoupper(substr(uniqid(), -6)),
            'visit_date'     => $request->visit_date,
            'quantity'       => $request->quantity,
            'total_price'    => $total,
            'status'         => 'confirmed',
        ]);

        return response()->json(['data' => $this->withQr($ticket->load('destination'))], 201);
    }

    // GET /api/v1/admin/tickets — hanya tiket destinasi milik admin
    public function adminIndex()
    {
        $user   = auth()->user();
        $query  = Ticket::with(['user', 'destination']);

        // Admin biasa hanya lihat tiket destinasinya sendiri
        if (!$user->hasRole('super_admin') && $user->destination_id) {
            $query->where('destination_id', $user->destination_id);
        }

        $tickets = $query->orderBy('created_at', 'desc')
            ->get()
            ->map(fn($t) => $this->withQr($t));

        return response()->json(['data' => $tickets]);
    }

    // PATCH /api/v1/admin/tickets/{id}/status
    public function updateStatus(Request $request, $id)
    {
        $request->validate([
            'status' => 'required|in:pending,confirmed,cancelled,used'
        ]);

        $ticket = Ticket::findOrFail($id);
        $this->checkAdminAccess($ticket);

        $data = ['status' => $request->status];
        if ($request->status === 'used') $data['used_at'] = now();

        $ticket->update($data);
        return response()->json([
            'data'    => $this->withQr($ticket->load(['user', 'destination'])),
            'message' => 'Status berhasil diupdate'
        ]);
    }

    // GET /api/v1/admin/tickets/scan/{ticketCode}
    public function scan($ticketCode)
    {
        $ticket = Ticket::with(['user', 'destination'])
            ->where('ticket_code', $ticketCode)
            ->first();

        if (!$ticket) {
            return response()->json(['valid' => false, 'message' => 'Tiket tidak ditemukan.'], 404);
        }

        // Admin hanya bisa scan tiket destinasinya
        $user = auth()->user();
        if (!$user->hasRole('super_admin') && $user->destination_id && $ticket->destination_id != $user->destination_id) {
            return response()->json(['valid' => false, 'message' => 'Tiket ini bukan untuk destinasi Anda.'], 403);
        }

        $status = match($ticket->status) {
            'confirmed' => ['valid' => true,  'message' => 'Tiket valid. Siap digunakan.'],
            'used'      => ['valid' => false, 'message' => 'Tiket sudah digunakan pada ' . $ticket->used_at?->format('d M Y H:i')],
            'pending'   => ['valid' => false, 'message' => 'Pembayaran belum selesai.'],
            'cancelled' => ['valid' => false, 'message' => 'Tiket telah dibatalkan.'],
            default     => ['valid' => false, 'message' => 'Status tidak diketahui.'],
        };

        return response()->json([...$status, 'ticket' => $this->withQr($ticket)]);
    }

    // POST /api/v1/admin/tickets/{id}/use
    public function markUsed($id)
    {
        $ticket = Ticket::with(['user', 'destination'])->findOrFail($id);
        $this->checkAdminAccess($ticket);

        if ($ticket->status === 'used') {
            return response()->json(['success' => false, 'message' => 'Tiket sudah digunakan sebelumnya.'], 422);
        }

        if ($ticket->status !== 'confirmed') {
            return response()->json(['success' => false, 'message' => 'Tiket tidak valid. Status: ' . $ticket->status], 422);
        }

        $ticket->update(['status' => 'used', 'used_at' => now()]);

        return response()->json([
            'success' => true,
            'message' => 'Tiket berhasil dikonfirmasi. Pengunjung diizinkan masuk.',
            'ticket'  => $this->withQr($ticket->fresh(['user', 'destination'])),
        ]);
    }

    // Helper — cek admin hanya bisa akses tiket destinasinya
    private function checkAdminAccess($ticket)
    {
        $user = auth()->user();
        if (!$user->hasRole('super_admin') && $user->destination_id && $ticket->destination_id != $user->destination_id) {
            abort(403, 'Tidak diizinkan mengakses tiket ini.');
        }
    }

    // Helper — tambah URL QR Code
    private function withQr($ticket)
    {
        $ticket->qr_code_url = 'https://api.qrserver.com/v1/create-qr-code/?data='
            . urlencode($ticket->ticket_code)
            . '&size=300x300&margin=10&format=png';
        return $ticket;
    }
}
