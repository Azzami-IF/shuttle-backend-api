@component('mail::message')
# Trip Completed - Safe Arrival! ✅

Hi {{ $booking->user->name }},

Your trip has been completed successfully. Thank you for traveling with us!

@component('mail::panel')
**Trip Summary:**
- From: {{ $schedule->origin }} → {{ $schedule->destination }}
- Date: {{ $schedule->departure_time->format('d F Y') }}
- Driver: {{ $schedule->driver->name }}
- Vehicle: {{ $schedule->vehicle->name }}
- Amount Paid: Rp {{ number_format($schedule->price, 0, ',', '.') }}
@endcomponent

**What's next?**
1. Rate your driver to help us improve our service
2. You can book again anytime for future trips
3. Check your email for your trip receipt and invoice

@component('mail::button', ['url' => config('app.url') . '/bookings'])
View My Bookings
@endcomponent

If you have any feedback or issues with your trip, please let us know. We'd love to hear from you!

**Have a great day!**

Best regards,  
{{ config('app.name') }} Team
@endcomponent
