@component('mail::message')
# Trip Started - Driver on the Way! 🚌

Hi {{ $booking->user->name }},

Great news! Your trip is now starting. Your driver is on the way to pick you up.

@component('mail::panel')
**Trip Details:**
- From: {{ $schedule->origin }} → {{ $schedule->destination }}
- Date: {{ $schedule->departure_time->format('d F Y') }}
- Time: {{ $schedule->departure_time->format('H:i') }}
- Driver: {{ $schedule->driver->name }}
- Rating: {{ $schedule->driver->rating }}
- Your Seat: Seat {{ $booking->seat->seat_number }}
@endcomponent

**What to do now:**
1. Be ready 5 minutes before the departure time
2. Keep your phone charged for real-time tracking
3. Have your booking reference ready when the driver arrives

**Booking Reference:** {{ $booking->id }}

You can track your driver's real-time location in the app.

@component('mail::button', ['url' => config('app.url') . '/booking/' . $booking->id])
Track Your Trip
@endcomponent

Thank you for choosing KemanapunGo!

Best regards,  
{{ config('app.name') }} Team
@endcomponent
