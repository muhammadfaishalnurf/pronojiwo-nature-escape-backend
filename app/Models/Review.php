<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Review extends Model
{
    protected $fillable = [
        'user_id',
        'destination_id',
        'rating',
        'ulasan',
    ];

    protected $casts = [
        'rating' => 'integer',
    ];

    public function user()
    {
        return $this->belongsTo(\App\Models\User::class);
    }

    public function destination()
    {
        return $this->belongsTo(\App\Models\Destination::class);
    }
}