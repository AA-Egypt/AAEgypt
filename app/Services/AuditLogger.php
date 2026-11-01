<?php

namespace App\Services;

use App\Models\AuditLog;
use Illuminate\Support\Facades\Auth;

class AuditLogger
{
    /**
     * Log an action directly to the audit_logs table.
     */
    public static function log(
        string $action,
        string $resource,
        mixed $resourceId = null,
        mixed $payload = null,
        ?int $userId = null,
        ?string $ipAddress = null,
    ): AuditLog {
        $effectiveUserId = $userId ?? Auth::id() ?? request()?->user()?->id;
        $effectiveIpAddress = $ipAddress ?? request()?->ip() ?? '127.0.0.1';

        $payloadData = is_array($payload)
            ? $payload
            : ($payload !== null ? ['data' => $payload] : null);

        return AuditLog::create([
            'user_id' => $effectiveUserId,
            'action' => $action,
            'resource' => $resource,
            'resource_id' => $resourceId !== null ? (string) $resourceId : null,
            'payload' => $payloadData,
            'ip_address' => $effectiveIpAddress,
            'created_at' => now(),
        ]);
    }
}
