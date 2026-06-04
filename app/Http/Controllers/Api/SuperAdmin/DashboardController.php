<?php

namespace App\Http\Controllers\Api\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Destination;
use App\Models\Ticket;
use App\Models\Setting;
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

        $totalPendapatan = Ticket::whereIn('status', ['confirmed', 'used'])->sum('total_price');

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

        $bulanLabels      = ['Jan','Feb','Mar','Apr','Mei','Jun','Jul','Agu','Sep','Okt','Nov','Des'];
        $startOfThisMonth = Carbon::today()->startOfMonth();

        $chartData = [];
        for ($i = 5; $i >= 0; $i--) {
            $bulan  = $startOfThisMonth->copy()->subMonths($i);
            $tahun  = $bulan->year;
            $bulanN = $bulan->month;
            $chartData[] = [
                'bulan'      => $bulanLabels[$bulanN - 1],
                'pengguna'   => User::whereYear('created_at', $tahun)->whereMonth('created_at', $bulanN)->count(),
                'tiket'      => Ticket::whereYear('created_at', $tahun)->whereMonth('created_at', $bulanN)->count(),
                'pendapatan' => (int) Ticket::whereYear('created_at', $tahun)->whereMonth('created_at', $bulanN)
                                    ->whereIn('status', ['confirmed', 'used'])->sum('total_price'),
            ];
        }

        return response()->json([
            'stats'      => $stats,
            'chart_data' => $chartData,
        ]);
    }

    // GET /api/v1/super-admin/settings
    public function settings()
    {
        $settings = Setting::getAllAsArray();
        return response()->json($settings);
    }

    // PUT /api/v1/super-admin/settings
    public function updateSettings(Request $request)
    {
        $request->validate([
            'app_name'        => 'nullable|string|max:255',
            'contact_phone'   => 'nullable|string|max:20',
            'contact_email'   => 'nullable|email|max:255',
            'contact_address' => 'nullable|string|max:500',
            'about_text'      => 'nullable|string',
        ]);

        foreach ($request->only(['app_name','contact_phone','contact_email','contact_address','about_text']) as $key => $value) {
            Setting::updateOrCreate(['key' => $key], ['value' => $value ?? '']);
        }

        return response()->json(['message' => 'Pengaturan berhasil disimpan']);
    }
}