<?php

namespace App\Http\Controllers\Api\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Destination;
use App\Models\Ticket;
use Illuminate\Http\Request;
use Spatie\Permission\Models\Role;
use Carbon\Carbon;

class DashboardController extends Controller
{
    public function index()
    {
        $adminRole      = Role::where('name', 'admin')->first();
        $superAdminRole = Role::where('name', 'super_admin')->first();
        $userRole       = Role::where('name', 'user')->first();

        $totalPendapatan = Ticket::whereIn('status', ['confirmed', 'used'])
            ->sum('total_price');

        $stats = [
            'total_users'        => User::count(),
            'total_destinasi'    => Destination::count(),
            'total_tiket'        => Ticket::count(),
            'total_pendapatan'   => $totalPendapatan,
            'total_admins'       => $adminRole      ? $adminRole->users()->count()      : 0,
            'total_super_admins' => $superAdminRole ? $superAdminRole->users()->count() : 0,
            'total_users_role'   => $userRole       ? $userRole->users()->count()       : 0,
            'tiket_confirmed'    => Ticket::where('status', 'confirmed')->count(),
            'tiket_used'         => Ticket::where('status', 'used')->count(),
            'tiket_pending'      => Ticket::where('status', 'pending')->count(),
            'tiket_cancelled'    => Ticket::where('status', 'cancelled')->count(),
        ];

        $bulanLabels = ['Jan','Feb','Mar','Apr','Mei','Jun',
                        'Jul','Agu','Sep','Okt','Nov','Des'];

        // ── KUNCI FIX: mulai dari awal bulan ini, lalu mundur ──
        // Pakai Carbon::today()->startOfMonth() sebagai titik awal
        // agar tidak ada efek overflow tanggal 31
        $startOfThisMonth = Carbon::today()->startOfMonth();

        $chartData = [];
        for ($i = 5; $i >= 0; $i--) {
            // Clone startOfThisMonth, lalu mundur $i bulan
            // Karena sudah dari tgl 1, subMonths tidak akan overflow
            $bulan  = $startOfThisMonth->copy()->subMonths($i);
            $tahun  = $bulan->year;
            $bulanN = $bulan->month;

            $chartData[] = [
                'bulan'      => $bulanLabels[$bulanN - 1],
                'pengguna'   => User::whereYear('created_at', $tahun)
                                    ->whereMonth('created_at', $bulanN)
                                    ->count(),
                'tiket'      => Ticket::whereYear('created_at', $tahun)
                                    ->whereMonth('created_at', $bulanN)
                                    ->count(),
                'pendapatan' => (int) Ticket::whereYear('created_at', $tahun)
                                    ->whereMonth('created_at', $bulanN)
                                    ->whereIn('status', ['confirmed', 'used'])
                                    ->sum('total_price'),
            ];
        }

        return response()->json([
            'stats'      => $stats,
            'chart_data' => $chartData,
        ]);
    }

    public function settings()
    {
        try {
            $settings = \App\Models\Setting::pluck('value', 'key')->toArray();
            return response()->json($settings);
        } catch (\Exception $e) {
            return response()->json([
                'app_name'        => 'Pronojiwo Nature Escape',
                'contact_phone'   => '',
                'contact_email'   => '',
                'contact_address' => '',
                'about_text'      => '',
            ]);
        }
    }

    public function updateSettings(Request $request)
    {
        try {
            foreach ($request->all() as $key => $value) {
                \App\Models\Setting::updateOrCreate(
                    ['key' => $key],
                    ['value' => $value]
                );
            }
            return response()->json(['message' => 'Pengaturan berhasil disimpan']);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Gagal: ' . $e->getMessage()], 500);
        }
    }
}
