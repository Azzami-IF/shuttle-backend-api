<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Seat extends Model
{
    protected $fillable = [
        'schedule_id',
        'seat_number',
        'status'
    ];

    public function schedule(): BelongsTo
    {
        return $this->belongsTo(Schedule::class);
    }

    public function bookings(): HasMany
    {
        return $this->hasMany(Booking::class);
    }

    // Helper attribute for design labels like 1A, 1B
    public function getLabelAttribute()
    {
        $num = (int)$this->seat_number;
        $row = Math.floor(($num - 1) / 4) + 1;
        $col = ($num - 1) % 4;
        $letters = ['A', 'B', 'C', 'D'];
        return $row . $letters[$col];
    }
}
