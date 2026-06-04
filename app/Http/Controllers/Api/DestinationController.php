<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Destination;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class DestinationController extends Controller
{
    // GET /api/v1/destinations — PUBLIC
    public function index()
    {
        $destinations = Destination::where('is_active', true)
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(fn($d) => $this->formatFoto($d));

        return response()->json(['data' => $destinations]);
    }

    // GET /api/v1/destinations/{id} — PUBLIC
    public function show($id)
    {
        $d = Destination::findOrFail($id);
        return response()->json(['data' => $this->formatFoto($d)]);
    }

    // GET /api/v1/admin/destinations — ADMIN
    public function adminIndex()
    {
        $user   = auth()->user();
        $destId = $user->destination_id;

        // Super admin lihat SEMUA
        if ($user->hasRole('super_admin')) {
            $destinations = Destination::orderBy('created_at', 'desc')
                ->get()
                ->map(fn($d) => $this->formatFoto($d));
            return response()->json(['data' => $destinations]);
        }

        // Admin biasa — belum di-assign
        if (!$destId) {
            return response()->json([
                'data'    => [],
                'warning' => 'Belum di-assign ke destinasi. Hubungi Super Admin.',
            ]);
        }

        // Admin biasa — hanya miliknya
        $destinations = Destination::where('id', $destId)
            ->get()
            ->map(fn($d) => $this->formatFoto($d));

        return response()->json(['data' => $destinations]);
    }

    // POST /api/v1/admin/destinations — hanya super_admin
    public function store(Request $request)
    {
        $user = auth()->user();
        if (!$user->hasRole('super_admin')) {
            return response()->json(['message' => 'Hanya Super Admin yang bisa membuat destinasi baru.'], 403);
        }

        $request->validate([
            'nama_wisata' => 'required|string|max:255',
            'kategori'   => 'nullable|string|max:50',
            'harga_tiket' => 'required|numeric|min:0',
            'kapasitas'   => 'required|integer|min:1',
            'deskripsi'   => 'nullable|string',
            'lokasi_rute' => 'nullable|string',
            'is_active'   => 'nullable',
            'foto'        => 'nullable|file|mimes:jpg,jpeg,png,webp,gif,avif|max:5120',
            'rating'      => 'nullable|numeric|min:0|max:5',
            'koordinat'   => 'nullable|string|max:100',
            'fasilitas'   => 'nullable|string',
        ]);

        $data = [
            'nama_wisata' => $request->nama_wisata,
            'kategori'    => $request->input('kategori', 'Wisata Alam') ?: 'Wisata Alam',
            'deskripsi'   => $request->deskripsi,
            'lokasi_rute' => $request->lokasi_rute,
            'harga_tiket' => $request->harga_tiket,
            'kapasitas'   => $request->kapasitas,
            'is_active'   => $request->input('is_active', '1') == '1',
            'rating'      => $request->rating ?: null,
            'koordinat'   => $request->koordinat,
            'fasilitas'   => $request->fasilitas,
        ];

        if ($request->hasFile('foto')) {
            $data['foto'] = $request->file('foto')->store('destinations', 'public');
        }

        $destination = Destination::create($data);
        return response()->json([
            'data'    => $this->formatFoto($destination),
            'message' => 'Destinasi berhasil ditambahkan'
        ], 201);
    }

    // POST /api/v1/admin/destinations/{id} — update
    public function update(Request $request, $id)
    {
        $destination = Destination::findOrFail($id);
        $user        = auth()->user();

        if (!$user->hasRole('super_admin') && $user->destination_id != $id) {
            return response()->json(['message' => 'Tidak diizinkan mengubah destinasi ini.'], 403);
        }

        $request->validate([
            'nama_wisata' => 'sometimes|string|max:255',
            'kategori'   => 'nullable|string|max:50',
            'harga_tiket' => 'sometimes|numeric|min:0',
            'kapasitas'   => 'sometimes|integer|min:1',
            'deskripsi'   => 'nullable|string',
            'lokasi_rute' => 'nullable|string',
            'is_active'   => 'nullable',
            'foto'        => 'nullable|file|mimes:jpg,jpeg,png,webp,gif,avif|max:5120',
            'rating'      => 'nullable|numeric|min:0|max:5',
            'koordinat'   => 'nullable|string|max:100',
            'fasilitas'   => 'nullable|string',
        ]);

        $data = [];
        if ($request->filled('nama_wisata')) $data['nama_wisata'] = $request->nama_wisata;
        if ($request->has('kategori'))       $data['kategori']    = $request->kategori ?: 'Wisata Alam';
        if ($request->filled('deskripsi'))   $data['deskripsi']   = $request->deskripsi;
        if ($request->filled('lokasi_rute')) $data['lokasi_rute'] = $request->lokasi_rute;
        if ($request->filled('harga_tiket')) $data['harga_tiket'] = $request->harga_tiket;
        if ($request->filled('kapasitas'))   $data['kapasitas']   = $request->kapasitas;
        if ($request->filled('rating'))      $data['rating']      = $request->rating;
        if ($request->filled('koordinat'))   $data['koordinat']   = $request->koordinat;
        if ($request->has('fasilitas'))      $data['fasilitas']   = $request->fasilitas;
        $data['is_active'] = $request->input('is_active', '1') == '1';

        if ($request->hasFile('foto')) {
            if ($destination->foto && !str_starts_with($destination->foto, 'http')) {
                Storage::disk('public')->delete($destination->foto);
            }
            $data['foto'] = $request->file('foto')->store('destinations', 'public');
        }

        $destination->update($data);
        return response()->json([
            'data'    => $this->formatFoto($destination->fresh()),
            'message' => 'Destinasi berhasil diupdate'
        ]);
    }

    // DELETE — hanya super_admin
    public function destroy($id)
    {
        $user = auth()->user();
        if (!$user->hasRole('super_admin')) {
            return response()->json(['message' => 'Hanya Super Admin yang bisa menghapus destinasi.'], 403);
        }
        $destination = Destination::findOrFail($id);
        if ($destination->foto && !str_starts_with($destination->foto, 'http')) {
            Storage::disk('public')->delete($destination->foto);
        }
        $destination->delete();
        return response()->json(['message' => 'Destinasi berhasil dihapus']);
    }

    private function formatFoto($d)
    {
        if ($d->foto && !str_starts_with($d->foto, 'http')) {
            $d->foto = url('storage/' . $d->foto);
        }
        return $d;
    }
}