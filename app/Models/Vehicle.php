<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Vehicle extends Model
{
    protected $fillable = [
        'name',
        'license_plate',
        'capacity'
    ];

    public function schedules(): HasMany
    {
        return $this->hasMany(Schedule::class);
    }
}
