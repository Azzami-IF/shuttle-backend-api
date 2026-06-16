<div style="font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto;">
    <div style="background-color: #f8f9fa; padding: 20px; border-radius: 5px;">
        <h2 style="color: #333;">Trip Update</h2>
        <p style="color: #666;">Dear {{ $booking->user->name }},</p>
        <p style="color: #666;">We have an update regarding your upcoming trip.</p>
        
        <h3 style="color: #333; margin-top: 20px;">Trip Details</h3>
        <ul style="color: #666;">
            <li><strong>Booking ID:</strong> {{ $booking->id }}</li>
            <li><strong>From:</strong> {{ $schedule->origin }}</li>
            <li><strong>To:</strong> {{ $schedule->destination }}</li>
            <li><strong>Departure:</strong> {{ $schedule->departure_time }}</li>
            <li><strong>Seat:</strong> {{ $booking->seat->seat_number ?? 'N/A' }}</li>
        </ul>
        
        <h3 style="color: #333; margin-top: 20px;">Update Message</h3>
        <p style="color: #666;">{{ $message }}</p>
        
        <p style="color: #666; margin-top: 20px;">Please contact us if you need further assistance.</p>
    </div>
</div>