<?php
namespace App\Http\Controllers;

use App\Models\Invoice;
use App\Services\InvoiceService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class InvoiceController extends Controller
{
    /**
     * Get invoice details
     */
    public function show(Request $request, $invoiceId)
    {
        try {
            $invoice = Invoice::with(['booking.user', 'booking.schedule', 'user'])->findOrFail($invoiceId);
            
            // Check authorization
            $user = $request->user();
            if ($invoice->user_id !== $user->id && $user->role !== 'admin') {
                return response()->json(['message' => 'Unauthorized'], 403);
            }

            return response()->json($invoice);
        } catch (\Exception $e) {
            Log::error('Get invoice failed: ' . $e->getMessage());
            return response()->json(['message' => $e->getMessage()], 400);
        }
    }

    /**
     * Get user invoices
     */
    public function getUserInvoices(Request $request)
    {
        try {
            $user = $request->user();
            $invoices = Invoice::where('user_id', $user->id)
                ->with('booking.schedule')
                ->orderBy('issued_at', 'desc')
                ->paginate(15);

            return response()->json($invoices);
        } catch (\Exception $e) {
            Log::error('Get user invoices failed: ' . $e->getMessage());
            return response()->json(['message' => $e->getMessage()], 400);
        }
    }

    /**
     * Send invoice email
     */
    public function sendEmail(Request $request, $invoiceId)
    {
        try {
            $invoice = Invoice::findOrFail($invoiceId);
            
            // Check authorization
            $user = $request->user();
            if ($invoice->user_id !== $user->id && $user->role !== 'admin') {
                return response()->json(['message' => 'Unauthorized'], 403);
            }

            $result = InvoiceService::sendInvoiceEmail($invoiceId);
            
            if ($result) {
                return response()->json(['message' => 'Invoice emailed successfully']);
            }
            
            return response()->json(['message' => 'Failed to send invoice email'], 400);
        } catch (\Exception $e) {
            Log::error('Send invoice email failed: ' . $e->getMessage());
            return response()->json(['message' => $e->getMessage()], 400);
        }
    }

    /**
     * Mark invoice as paid
     */
    public function markAsPaid(Request $request, $invoiceId)
    {
        try {
            $user = $request->user();
            
            // Only admin can mark as paid
            if ($user->role !== 'admin') {
                return response()->json(['message' => 'Unauthorized'], 403);
            }

            $invoice = InvoiceService::markAsPaid($invoiceId);
            
            if ($invoice) {
                return response()->json(['message' => 'Invoice marked as paid', 'invoice' => $invoice]);
            }
            
            return response()->json(['message' => 'Failed to mark invoice as paid'], 400);
        } catch (\Exception $e) {
            Log::error('Mark invoice as paid failed: ' . $e->getMessage());
            return response()->json(['message' => $e->getMessage()], 400);
        }
    }
}
