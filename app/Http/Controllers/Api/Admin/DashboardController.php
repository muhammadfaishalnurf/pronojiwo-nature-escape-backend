<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Destination;
use App\Models\Ticket;
use App\Models\Review;
use Carbon\Carbon;

class DashboardController extends Controller
{
    public function index()
    {
        $user         = auth()->user();
        $isSuperAdmin = $user->hasRole('super_admin');
        $destId       = $user->destination_id;

        if (!$isSuperAdmin && !$destId) {
            return response()->json([
                'stats' => [
                    'total_tiket'      => 0,
                    'total_review'     => 0,
                    'total_pendapatan' => 0,
                    'total_destinasi'  => 0,
                    'destinasi'        => null,
                    'tiket_confirmed'  => 0,
                    'tiket_used'       => 0,
                    'tiket_pending'    => 0,
                    'tiket_cancelled'  => 0,
                ],
                'recent_tickets' => [],
                'chart_data'     => [],
                'warning'        => 'Akun admin belum di-assign ke destinasi. Hubungi Super Admin.',
            ]);
        }

        $bulanLabels      = ['Jan','Feb','Mar','Apr','Mei','Jun',
                             'Jul','Agu','Sep','Okt','Nov','Des'];
        $startOfThisMonth = Carbon::today()->startOfMonth();

        if ($isSuperAdmin) {
            $totalDestinasi  = Destination::count();
            $totalTiket      = Ticket::count();
            $totalReview     = Review::count();
            $totalPendapatan = Ticket::whereIn('status', ['confirmed','used'])->sum('total_price');
            $destNama        = 'Semua Destinasi';

            $tiketConfirmed = Ticket::where('status', 'confirmed')->count();
            $tiketUsed      = Ticket::where('status', 'used')->count();
            $tiketPending   = Ticket::where('status', 'pending')->count();
            $tiketCancelled = Ticket::where('status', 'cancelled')->count();

            $recentTickets = Ticket::with(['user', 'destination'])
                ->orderBy('created_at', 'desc')->limit(8)->get();

            $chartData = [];
            for ($i = 5; $i >= 0; $i--) {
                $bulan  = $startOfThisMonth->copy()->subMonths($i);
                $tahun  = $bulan->year;
                $bulanN = $bulan->month;
                $chartData[] = [
                    'bulan'      => $bulanLabels[$bulanN - 1],
                    'tiket'      => Ticket::whereYear('created_at', $tahun)
                                        ->whereMonth('created_at', $bulanN)->count(),
                    'pendapatan' => (int) Ticket::whereYear('created_at', $tahun)
                                        ->whereMonth('created_at', $bulanN)
                                        ->whereIn('status', ['confirmed','used'])->sum('total_price'),
                ];
            }
        } else {
            // Admin biasa — filter by destination_id
            $destination     = Destination::find($destId);
            $totalDestinasi  = 1;
            $totalTiket      = Ticket::where('destination_id', $destId)->count();
            $totalReview     = Review::where('destination_id', $destId)->count();
            $totalPendapatan = Ticket::where('destination_id', $destId)
                                    ->whereIn('status', ['confirmed','used'])->sum('total_price');
            $destNama        = $destination?->nama_wisata;

            // Breakdown status — hanya destinasi miliknya
            $tiketConfirmed = Ticket::where('destination_id', $destId)->where('status', 'confirmed')->count();
            $tiketUsed      = Ticket::where('destination_id', $destId)->where('status', 'used')->count();
            $tiketPending   = Ticket::where('destination_id', $destId)->where('status', 'pending')->count();
            $tiketCancelled = Ticket::where('destination_id', $destId)->where('status', 'cancelled')->count();

            $recentTickets = Ticket::with(['user', 'destination'])
                ->where('destination_id', $destId)
                ->orderBy('created_at', 'desc')->limit(8)->get();

            $chartData = [];
            for ($i = 5; $i >= 0; $i--) {
                $bulan  = $startOfThisMonth->copy()->subMonths($i);
                $tahun  = $bulan->year;
                $bulanN = $bulan->month;
                $chartData[] = [
                    'bulan'      => $bulanLabels[$bulanN - 1],
                    'tiket'      => Ticket::where('destination_id', $destId)
                                        ->whereYear('created_at', $tahun)
                                        ->whereMonth('created_at', $bulanN)->count(),
                    'pendapatan' => (int) Ticket::where('destination_id', $destId)
                                        ->whereYear('created_at', $tahun)
                                        ->whereMonth('created_at', $bulanN)
                                        ->whereIn('status', ['confirmed','used'])->sum('total_price'),
                ];
            }
        }

        return response()->json([
            'stats' => [
                'total_tiket'      => $totalTiket,
                'total_review'     => $totalReview,
                'total_pendapatan' => $totalPendapatan,
                'total_destinasi'  => $totalDestinasi,
                'destinasi'        => $destNama,
                'tiket_confirmed'  => $tiketConfirmed,
                'tiket_used'       => $tiketUsed,
                'tiket_pending'    => $tiketPending,
                'tiket_cancelled'  => $tiketCancelled,
            ],
            'recent_tickets' => $recentTickets,
            'chart_data'     => $chartData,
        ]);
    }
}
