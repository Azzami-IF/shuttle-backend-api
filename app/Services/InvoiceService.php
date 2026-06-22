<?php
namespace App\Services;

use App\Models\Booking;
use App\Models\Invoice;
use App\Mail\InvoiceMail;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;
use Exception;

class InvoiceService
{
    /**
     * Generate invoice for booking
     */
    public static function generateInvoice($bookingId)
    {
        try {
            $booking = Booking::with(['user', 'schedule', 'seat'])->findOrFail($bookingId);
            
            $existingInvoice = Invoice::where('booking_id', $bookingId)->first();
            if ($existingInvoice) {
                return $existingInvoice;
            }
            
            $invoice = Invoice::create([
                'booking_id' => $bookingId,
                'user_id' => $booking->user_id,
                'invoice_number' => 'INV-' . date('Ym') . '-' . str_pad($booking->id, 5, '0', STR_PAD_LEFT),
                'amount' => optional($booking->schedule)->price ?? 50000,
                'status' => 'issued',
                'issued_at' => now(),
            ]);

            Log::info('Invoice generated for booking: ' . $bookingId);
            return $invoice;
        } catch (Exception $e) {
            Log::error('Invoice generation failed: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Send invoice via email
     */
    public static function sendInvoiceEmail($invoiceId)
    {
        try {
            $invoice = Invoice::with(['booking.user', 'booking.schedule', 'user'])->findOrFail($invoiceId);
            
            if ($invoice->user && $invoice->user->email) {
                Mail::to($invoice->user->email)
                    ->send(new InvoiceMail($invoice));
            } else {
                Log::warning('Invoice email skipped: missing user email for invoice '.$invoiceId);
            }
                
            $invoice->update(['emailed_at' => now()]);
            
            Log::info('Invoice emailed for invoice: ' . $invoiceId);
            return true;
        } catch (Exception $e) {
            Log::error('Invoice email failed: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Mark invoice as paid
     */
    public static function markAsPaid($invoiceId)
    {
        try {
            $invoice = Invoice::findOrFail($invoiceId);
            $invoice->update([
                'status' => 'paid',
                'paid_at' => now(),
            ]);
            
            Log::info('Invoice marked as paid: ' . $invoiceId);
            return $invoice;
        } catch (Exception $e) {
            Log::error('Mark invoice as paid failed: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Get invoice details
     */
    public static function getInvoiceDetails($invoiceId)
    {
        try {
            $invoice = Invoice::with(['booking.user', 'booking.schedule', 'booking.seat', 'user'])
                ->findOrFail($invoiceId);
            
            return $invoice;
        } catch (Exception $e) {
            Log::error('Get invoice details failed: ' . $e->getMessage());
            return null;
        }
    }
}
