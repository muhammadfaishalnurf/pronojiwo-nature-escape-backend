<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Ticket extends Model
{
    protected $fillable = [
        'user_id',
        'destination_id',
        'ticket_code',
        'nama_ketua',
        'jenis_kelamin',
        'no_hp',
        'kebangsaan',
        'visit_date',
        'quantity',
        'total_price',
        'status',
        'order_id',
    ];

    protected $casts = [
        'visit_date'  => 'date',
        'total_price' => 'float',
        'quantity'    => 'integer',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function destination()
    {
        return $this->belongsTo(Destination::class);
    }

    public function payment()
    {
        return $this->hasOne(Payment::class);
    }
}
