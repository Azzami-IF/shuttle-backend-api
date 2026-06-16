<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use Illuminate\Http\Request;

/**
 * AuditLogController - View and manage audit logs
 */
class AuditLogController extends Controller
{
    /**
     * Get audit logs (admin only)
     */
    public function index(Request $request)
    {
        // Check admin role
        if ($request->user()->role !== 'admin' && $request->user()->role !== 'superadmin') {
            abort(403, 'Unauthorized');
        }

        $query = AuditLog::with('user');

        // Filter by user
        if ($request->has('user_id')) {
            $query->where('user_id', $request->user_id);
        }

        // Filter by action
        if ($request->has('action')) {
            $query->where('action', $request->action);
        }

        // Filter by model type
        if ($request->has('model_type')) {
            $query->where('model_type', $request->model_type);
        }

        // Date range filter
        if ($request->has('from_date')) {
            $query->where('created_at', '>=', $request->from_date);
        }
        if ($request->has('to_date')) {
            $query->where('created_at', '<=', $request->to_date);
        }

        $logs = $query->latest()->paginate($request->get('per_page', 50));

        return response()->json($logs);
    }

    /**
     * Get audit log details
     */
    public function show(AuditLog $auditLog, Request $request)
    {
        if ($request->user()->role !== 'admin' && $request->user()->role !== 'superadmin') {
            abort(403, 'Unauthorized');
        }

        return response()->json($auditLog->load('user'));
    }

    /**
     * Get statistics
     */
    public function statistics(Request $request)
    {
        if ($request->user()->role !== 'admin' && $request->user()->role !== 'superadmin') {
            abort(403, 'Unauthorized');
        }

        $days = $request->get('days', 30);

        return response()->json([
            'total_actions' => AuditLog::where('created_at', '>=', now()->subDays($days))->count(),
            'actions_by_type' => AuditLog::where('created_at', '>=', now()->subDays($days))
                ->groupBy('action')
                ->selectRaw('action, count(*) as count')
                ->pluck('count', 'action'),
            'actions_by_user' => AuditLog::where('created_at', '>=', now()->subDays($days))
                ->with('user')
                ->groupBy('user_id')
                ->selectRaw('user_id, count(*) as count')
                ->get(),
            'actions_by_model' => AuditLog::where('created_at', '>=', now()->subDays($days))
                ->groupBy('model_type')
                ->selectRaw('model_type, count(*) as count')
                ->pluck('count', 'model_type'),
        ]);
    }
}
