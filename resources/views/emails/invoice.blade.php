<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        body {
            font-family: Arial, sans-serif;
            color: #333;
            line-height: 1.6;
        }
        .invoice-container {
            max-width: 800px;
            margin: 0 auto;
            padding: 20px;
        }
        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-bottom: 2px solid #007bff;
            margin-bottom: 30px;
            padding-bottom: 20px;
        }
        .company-info {
            color: #007bff;
        }
        .company-info h1 {
            margin: 0;
            font-size: 24px;
        }
        .invoice-details {
            text-align: right;
        }
        .invoice-number {
            font-size: 14px;
            font-weight: bold;
        }
        .customer-info {
            margin-bottom: 30px;
        }
        .customer-info h3 {
            margin-top: 0;
            color: #007bff;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin: 20px 0;
        }
        th {
            background-color: #f0f0f0;
            padding: 10px;
            text-align: left;
            border-bottom: 2px solid #007bff;
        }
        td {
            padding: 10px;
            border-bottom: 1px solid #ddd;
        }
        .summary {
            margin-top: 20px;
            text-align: right;
        }
        .summary-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 10px;
            max-width: 300px;
            margin-left: auto;
        }
        .total {
            font-weight: bold;
            font-size: 18px;
            border-top: 2px solid #007bff;
            padding-top: 10px;
        }
        .footer {
            margin-top: 40px;
            padding-top: 20px;
            border-top: 1px solid #ddd;
            text-align: center;
            color: #666;
            font-size: 12px;
        }
    </style>
</head>
<body>
    <div class="invoice-container">
        <div class="header">
            <div class="company-info">
                <h1>Shuttle</h1>
                <p>Your Transportation Solution</p>
            </div>
            <div class="invoice-details">
                <div class="invoice-number">{{ $invoice->invoice_number }}</div>
                <div>Issue Date: {{ $invoice->issued_at->format('F d, Y') }}</div>
                <div>Status: <strong>{{ ucfirst($invoice->status) }}</strong></div>
            </div>
        </div>

        <div class="customer-info">
            <h3>Bill To:</h3>
            <p>
                <strong>{{ $invoice->user->name }}</strong><br>
                {{ $invoice->user->email }}<br>
                @if($invoice->user->phone)
                    {{ $invoice->user->phone }}<br>
                @endif
            </p>
        </div>

        <table>
            <thead>
                <tr>
                    <th>Description</th>
                    <th>Date</th>
                    <th>Amount</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td>
                        <strong>Shuttle Booking Payment</strong><br>
                        <small>Booking ID: #{{ $invoice->booking_id }}</small>
                        @if($invoice->booking->schedule)
                            <br><small>Route: {{ $invoice->booking->schedule->route }}</small>
                        @endif
                    </td>
                    <td>{{ $invoice->issued_at->format('F d, Y') }}</td>
                    <td style="text-align: right;">
                        ${{ number_format($invoice->amount / 100, 2) }}
                    </td>
                </tr>
            </tbody>
        </table>

        <div class="summary">
            <div class="summary-row">
                <span>Subtotal:</span>
                <span>${{ number_format($invoice->amount / 100, 2) }}</span>
            </div>
            <div class="summary-row">
                <span>Tax:</span>
                <span>$0.00</span>
            </div>
            <div class="summary-row total">
                <span>Total Due:</span>
                <span>${{ number_format($invoice->amount / 100, 2) }}</span>
            </div>
        </div>

        @if($invoice->status !== 'paid')
            <div style="margin-top: 30px; padding: 15px; background-color: #f9f9f9; border-left: 4px solid #007bff;">
                <h3 style="margin-top: 0; color: #007bff;">Payment Instructions</h3>
                <p>Please complete payment for this invoice. Visit your account dashboard to make a payment or contact support if you have any questions.</p>
            </div>
        @else
            <div style="margin-top: 30px; padding: 15px; background-color: #d4edda; border-left: 4px solid #28a745;">
                <p style="margin: 0; color: #155724;"><strong>✓ This invoice has been paid.</strong> Paid on {{ $invoice->paid_at->format('F d, Y') }}</p>
            </div>
        @endif

        <div class="footer">
            <p>Thank you for using Shuttle!</p>
            <p>If you have any questions about this invoice, please contact support@shuttle.local</p>
            <p>&copy; {{ date('Y') }} Shuttle. All rights reserved.</p>
        </div>
    </div>
</body>
</html>
