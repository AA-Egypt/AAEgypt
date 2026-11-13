<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Dms\Audit\DmsAuditLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Pagination\LengthAwarePaginator;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

class DmsAuditViewController extends Controller
{
    /**
     * Display or stream immutable DMS audit logs.
     */
    public function index(Request $request): Response|JsonResponse
    {
        $user = $request->user();

        if (! $user || $user->role !== 'web_admin') {
            abort(SymfonyResponse::HTTP_FORBIDDEN, 'Unauthorized. Web Admin access required.');
        }

        $filters = [
            'date_from' => $request->query('date_from'),
            'date_to' => $request->query('date_to'),
            'event' => $request->query('event') ?: $request->query('action'),
            'user' => $request->query('user'),
            'status' => $request->query('status'),
            'doc_id' => $request->query('doc_id'),
        ];

        $activeFilters = array_filter($filters, fn ($val) => $val !== null && $val !== '' && $val !== 'all');

        $entries = DmsAuditLogger::getEntries($activeFilters);

        // JSON response for real-time inspection, polling, or API clients
        if ($request->wantsJson() || $request->expectsJson() || $request->query('format') === 'json' || $request->ajax()) {
            return response()->json([
                'status' => 'success',
                'log_path' => DmsAuditLogger::getLogPath(),
                'total' => $entries->count(),
                'filters' => $filters,
                'data' => $entries->values(),
            ], 200, [
                'Cache-Control' => 'no-cache, no-store, must-revalidate',
                'X-Robots-Tag' => 'noindex, nofollow',
            ]);
        }

        $perPage = (int) $request->query('per_page', 25);
        $page = (int) $request->query('page', 1);

        $paginatedLogs = new LengthAwarePaginator(
            $entries->forPage($page, $perPage)->values(),
            $entries->count(),
            $perPage,
            $page,
            ['path' => $request->url(), 'query' => $request->query()]
        );

        $actions = [
            'upload' => 'Upload',
            'download' => 'Download',
            'delete' => 'Delete',
            'scan_pass' => 'Scan Pass',
            'scan_fail' => 'Scan Fail',
            'version_bump' => 'Version Bump',
            'permission_change' => 'Permission Change',
        ];

        // Gather distinct users present in the logs or user table for filter convenience
        $logUsers = $entries->pluck('user_email')->filter()->unique()->values();

        return response()->view('admin.dms.audit-logs', [
            'logs' => $paginatedLogs,
            'totalCount' => $entries->count(),
            'filters' => $filters,
            'actions' => $actions,
            'logUsers' => $logUsers,
            'logPath' => DmsAuditLogger::getLogPath(),
        ], 200, [
            'Cache-Control' => 'no-cache, no-store, must-revalidate',
            'X-Robots-Tag' => 'noindex, nofollow',
        ]);
    }

    /**
     * Prevent any update action on immutable audit records.
     */
    public function update(): never
    {
        abort(SymfonyResponse::HTTP_METHOD_NOT_ALLOWED, 'Audit logs are append-only and cannot be updated.');
    }

    /**
     * Prevent any delete action on immutable audit records.
     */
    public function destroy(): never
    {
        abort(SymfonyResponse::HTTP_METHOD_NOT_ALLOWED, 'Audit logs are append-only and cannot be deleted.');
    }
}
