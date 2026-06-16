<div style="font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto;">
    <div style="background-color: #f8f9fa; padding: 20px; border-radius: 5px;">
        <h2 style="color: #333;">Booking Cancellation</h2>
        <p style="color: #666;">Dear {{ $booking->user->name }},</p>
        <p style="color: #666;">Your booking has been cancelled.</p>
        
        <h3 style="color: #333; margin-top: 20px;">Cancellation Details</h3>
        <ul style="color: #666;">
            <li><strong>Booking ID:</strong> {{ $booking->id }}</li>
            <li><strong>From:</strong> {{ $schedule->origin }}</li>
            <li><strong>To:</strong> {{ $schedule->destination }}</li>
            <li><strong>Original Departure:</strong> {{ $schedule->departure_time }}</li>
            <li><strong>Seat:</strong> {{ $booking->seat->seat_number ?? 'N/A' }}</li>
            <li><strong>Cancellation Reason:</strong> {{ $booking->cancellation_reason ?? 'N/A' }}</li>
        </ul>
        
        <p style="color: #666; margin-top: 20px;">If you have any questions, please contact us.</p>
    </div>
</div>