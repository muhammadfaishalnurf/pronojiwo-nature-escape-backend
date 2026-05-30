<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Destination;
use App\Models\Ticket;
use App\Models\Review;

class DashboardController extends Controller
{
    private function adminDestId()
    {
        return auth()->user()->destination_id;
    }

    public function index()
    {
        $user   = auth()->user();
        $isSuperAdmin = $user->hasRole('super_admin');
        $destId = $this->adminDestId();

        if (!$isSuperAdmin && !$destId) {
            return response()->json([
                'stats'          => ['total_tiket' => 0, 'total_review' => 0, 'total_pendapatan' => 0, 'destinasi' => null],
                'recent_tickets' => [],
                'chart_data'     => [],
                'warning'        => 'Akun admin belum di-assign ke destinasi. Hubungi Super Admin.',
            ]);
        }

        $destination = $destId ? Destination::find($destId) : null;

        // Query builder — filter per destinasi kalau bukan super_admin
        $ticketQuery  = Ticket::query();
        $reviewQuery  = Review::query();

        if (!$isSuperAdmin && $destId) {
            $ticketQuery->where('destination_id', $destId);
            $reviewQuery->where('destination_id', $destId);
        }

        // Pendapatan = confirmed + used (keduanya sudah bayar)
        $pendapatan = (clone $ticketQuery)
            ->whereIn('status', ['confirmed', 'used'])
            ->sum('total_price');

        $stats = [
            'total_tiket'      => (clone $ticketQuery)->count(),
            'total_review'     => $reviewQuery->count(),
            'total_pendapatan' => $pendapatan,
            'destinasi'        => $destination?->nama_wisata,
        ];

        $recentTickets = (clone $ticketQuery)
            ->with(['user', 'destination'])
            ->orderBy('created_at', 'desc')
            ->limit(8)
            ->get();

        // Chart 6 bulan terakhir
        $chartData = [];
        for ($i = 5; $i >= 0; $i--) {
            $date  = now()->subMonths($i);
            $base  = (clone $ticketQuery)
                ->whereYear('created_at', $date->year)
                ->whereMonth('created_at', $date->month);

            $chartData[] = [
                'bulan'      => $date->format('M'),
                'tiket'      => (clone $base)->count(),
                'pendapatan' => (int) (clone $base)
                    ->whereIn('status', ['confirmed', 'used'])
                    ->sum('total_price'),
            ];
        }

        return response()->json([
            'stats'          => $stats,
            'recent_tickets' => $recentTickets,
            'chart_data'     => $chartData,
        ]);
    }
}
