<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Review;
use Illuminate\Http\Request;

class ReviewController extends Controller
{
    // GET /api/v1/reviews — PUBLIC
    public function index()
    {
        $reviews = Review::with(['user', 'destination'])
            ->orderBy('created_at', 'desc')
            ->limit(10)
            ->get();
        return response()->json(['data' => $reviews]);
    }

    // POST /api/v1/reviews — butuh login
    public function store(Request $request)
    {
        $request->validate([
            'destination_id' => 'required|exists:destinations,id',
            'rating'         => 'required|integer|between:1,5',
            'ulasan'         => 'required|string|max:1000',
        ]);

        $review = Review::create([
            'user_id'        => auth()->id(),
            'destination_id' => $request->destination_id,
            'rating'         => $request->rating,
            'ulasan'         => $request->ulasan,
        ]);

        $review->load(['user', 'destination']);
        return response()->json(['data' => $review], 201);
    }

    // GET /api/v1/admin/reviews — ADMIN
    public function adminIndex()
    {
        $reviews = Review::with(['user', 'destination'])
            ->orderBy('created_at', 'desc')
            ->get();
        return response()->json(['data' => $reviews]);
    }

    // DELETE /api/v1/admin/reviews/{id} — ADMIN
    public function destroy($id)
    {
        $review = Review::findOrFail($id);
        $review->delete();
        return response()->json(['message' => 'Ulasan berhasil dihapus']);
    }
}