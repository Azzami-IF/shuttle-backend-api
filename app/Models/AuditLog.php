<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Audit Log Model - Tracks all admin actions
 * Provides comprehensive audit trail for compliance
 */
class AuditLog extends Model
{
    protected $table = 'audit_logs';
    
    protected $fillable = [
        'user_id',
        'action',
        'model_type',
        'model_id',
        'old_values',
        'new_values',
        'ip_address',
        'user_agent',
        'description'
    ];

    protected $casts = [
        'old_values' => 'json',
        'new_values' => 'json',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Log an action
     */
    public static function logAction(
        $userId,
        $action,
        $modelType,
        $modelId,
        $oldValues = null,
        $newValues = null,
        $description = null
    ) {
        return self::create([
            'user_id' => $userId,
            'action' => $action,
            'model_type' => $modelType,
            'model_id' => $modelId,
            'old_values' => $oldValues,
            'new_values' => $newValues,
            'ip_address' => request()->ip(),
            'user_agent' => request()->header('User-Agent'),
            'description' => $description,
        ]);
    }
}
