<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Schedule extends Model
{
    protected $fillable = [
        'vehicle_id',
        'driver_id',
        'origin',
        'destination',
        'departure_time',
        'price',
        'pickup_name',
        'pickup_lat',
        'pickup_lng',
        'drop_off_name',
        'drop_off_lat',
        'drop_off_lng'
    ];

    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class);
    }

    public function driver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'driver_id');
    }

    public function seats(): HasMany
    {
        return $this->hasMany(Seat::class);
    }

    public function bookings(): HasMany
    {
        return $this->hasMany(Booking::class);
    }

    public function trip(): HasOne
    {
        return $this->hasOne(Trip::class);
    }
}
