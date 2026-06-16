<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RouteTemplate extends Model
{
    protected $fillable = [
        'vehicle_id', 'driver_id', 'origin', 'destination',
        'departure_time', 'price', 'active_days', 'generate_days_ahead', 'is_active'
    ];

    protected $casts = [
        'active_days' => 'array',
        'is_active' => 'boolean',
        'price' => 'decimal:2',
    ];

    public function vehicle()
    {
        return $this->belongsTo(Vehicle::class);
    }

    public function driver()
    {
        return $this->belongsTo(User::class, 'driver_id');
    }
}
