<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Destination extends Model
{
    protected $fillable = [
        'nama_wisata',
        'kategori',
        'deskripsi',
        'lokasi_rute',
        'harga_tiket',
        'kapasitas',
        'foto',
        'is_active',
        'rating',
        'koordinat',
        'fasilitas',
    ];

    protected $casts = [
        'harga_tiket' => 'float',
        'kapasitas'   => 'integer',
        'rating'      => 'float',
        'is_active'   => 'boolean',
    ];

    public function tickets()
    {
        return $this->hasMany(\App\Models\Ticket::class);
    }

    public function reviews()
    {
        return $this->hasMany(\App\Models\Review::class);
    }

    // Helper: fasilitas sebagai array
    public function getFasilitasArrayAttribute(): array
    {
        if (!$this->fasilitas) return [];
        return array_map('trim', explode(',', $this->fasilitas));
    }
}