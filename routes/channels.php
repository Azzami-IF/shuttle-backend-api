<?php

use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

// Channel privat untuk pelacakan jadwal tertentu
Broadcast::channel('schedules.{scheduleId}', function ($user, $scheduleId) {
    if ($user->role === 'admin') {
        return true;
    }

    if ($user->role === 'driver') {
        $schedule = \App\Models\Schedule::find($scheduleId);
        return $schedule && (int) $schedule->driver_id === (int) $user->id;
    }

    if ($user->role === 'customer') {
        return \App\Models\Booking::where('schedule_id', $scheduleId)
            ->where('user_id', $user->id)
            ->whereIn('status', ['paid', 'booked', 'completed'])
            ->exists();
    }

    return false;
});

// Channel privat untuk pelacakan admin
Broadcast::channel('admin.tracking', function ($user) {
    return $user->role === 'admin';
});
