<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Review;
use App\Models\Destination;
use Illuminate\Http\Request;

class ReviewController extends Controller
{
    // GET /api/v1/reviews — PUBLIC
    public function index()
    {
        $reviews = Review::with('destination')
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(fn($r) => $this->format($r));

        return response()->json(['data' => $reviews]);
    }

    // GET /api/v1/admin/reviews — ADMIN
    public function adminIndex()
    {
        $reviews = Review::with(['user', 'destination'])
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(fn($r) => $this->format($r));

        return response()->json(['data' => $reviews]);
    }

    // POST /api/v1/reviews — user login bisa review
    public function store(Request $request)
    {
        $request->validate([
            'destination_id' => 'required|exists:destinations,id',
            'rating'         => 'required|integer|min:1|max:5',
            'ulasan'         => 'required|string|min:5',
        ]);

        $review = Review::create([
            'user_id'        => auth()->id(),
            'nama'           => auth()->user()->name,
            'destination_id' => $request->destination_id,
            'rating'         => $request->rating,
            'ulasan'         => $request->ulasan,
            'tanggal_label'  => 'Baru saja',
        ]);

        return response()->json(['data' => $this->format($review->load('destination'))], 201);
    }

    // POST /api/v1/super-admin/reviews — super admin input manual
    public function superAdminStore(Request $request)
    {
        $request->validate([
            'nama'           => 'required|string|max:100',
            'destination_id' => 'required|exists:destinations,id',
            'rating'         => 'required|integer|min:1|max:5',
            'ulasan'         => 'required|string|min:5',
            'tanggal_label'  => 'nullable|string|max:50',
        ]);

        $review = Review::create([
            'user_id'        => null,
            'nama'           => $request->nama,
            'destination_id' => $request->destination_id,
            'rating'         => $request->rating,
            'ulasan'         => $request->ulasan,
            'tanggal_label'  => $request->tanggal_label ?: 'Baru saja',
        ]);

        return response()->json(['data' => $this->format($review->load('destination'))], 201);
    }

    // PUT /api/v1/super-admin/reviews/{id} — edit review manual
    public function superAdminUpdate(Request $request, $id)
    {
        $review = Review::findOrFail($id);

        $request->validate([
            'nama'           => 'sometimes|string|max:100',
            'destination_id' => 'sometimes|exists:destinations,id',
            'rating'         => 'sometimes|integer|min:1|max:5',
            'ulasan'         => 'sometimes|string|min:5',
            'tanggal_label'  => 'nullable|string|max:50',
        ]);

        $review->update($request->only(['nama','destination_id','rating','ulasan','tanggal_label']));

        return response()->json(['data' => $this->format($review->fresh('destination'))]);
    }

    // DELETE /api/v1/admin/reviews/{id}
    public function destroy($id)
    {
        Review::findOrFail($id)->delete();
        return response()->json(['message' => 'Ulasan berhasil dihapus']);
    }

    private function format($r)
    {
        $r->nama_display = $r->nama ?? $r->user?->name ?? 'Pengunjung';
        return $r;
    }
}