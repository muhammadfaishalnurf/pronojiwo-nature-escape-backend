<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Destination extends Model
{
    protected $fillable = [
        'nama_wisata',
        'deskripsi',
        'lokasi_rute',
        'harga_tiket',
        'kapasitas',
        'foto',
        'rating',
        'is_active',
    ];

    protected $casts = [
        'is_active'   => 'boolean',
        'harga_tiket' => 'float',
        'rating'      => 'float',
        'kapasitas'   => 'integer',
    ];

    public function tickets()
    {
        return $this->hasMany(\App\Models\Ticket::class);
    }

    public function reviews()
    {
        return $this->hasMany(\App\Models\Review::class);
    }
}