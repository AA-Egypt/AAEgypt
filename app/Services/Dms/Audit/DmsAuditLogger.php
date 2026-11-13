<?php

namespace App\Services\Dms\Audit;

use App\Models\DmsDocument;
use App\Models\DmsFolder;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Monolog\Formatter\LineFormatter;
use Monolog\Handler\StreamHandler;
use Monolog\Level;
use Monolog\Logger;
use Psr\Log\LoggerInterface;

class DmsAuditLogger
{
    protected static ?LoggerInterface $logger = null;

    protected static ?string $customLogPath = null;

    /**
     * Resolve the storage location for DMS audit logs.
     */
    public static function getLogPath(): string
    {
        if (static::$customLogPath !== null) {
            return static::$customLogPath;
        }

        $configured = config('logging.channels.dms_audit.handler_with.stream')
            ?? config('logging.channels.dms_audit.path')
            ?? env('DMS_AUDIT_LOG_PATH');

        if ($configured) {
            return $configured;
        }

        if (file_exists('/var/log/aaegypt/dms-audit.log') && is_writable('/var/log/aaegypt/dms-audit.log')) {
            return '/var/log/aaegypt/dms-audit.log';
        }

        if (is_dir('/var/log/aaegypt') && is_writable('/var/log/aaegypt')) {
            return '/var/log/aaegypt/dms-audit.log';
        }

        return storage_path('logs/dms-audit.log');
    }

    /**
     * Set a custom log path (primarily for testing and overrides).
     */
    public static function setLogPath(?string $path): void
    {
        static::$customLogPath = $path;
        static::$logger = null;
    }

    /**
     * Reset logger state and custom path.
     */
    public static function reset(): void
    {
        static::$customLogPath = null;
        static::$logger = null;
    }

    /**
     * Ensure the audit log directory and file exist with file permissions 0640.
     */
    public static function ensureLogFileExists(): string
    {
        $logPath = static::getLogPath();
        $dir = dirname($logPath);

        if (! is_dir($dir)) {
            @mkdir($dir, 0750, true);
        }

        if (! file_exists($logPath)) {
            @touch($logPath);
        }

        @chmod($logPath, 0640);

        return $logPath;
    }

    /**
     * Get or build the dedicated Monolog logger instance with StreamHandler.
     */
    public static function getLogger(): LoggerInterface
    {
        if (static::$logger !== null) {
            return static::$logger;
        }

        $logPath = static::ensureLogFileExists();

        $handler = new StreamHandler($logPath, Level::Info, true, 0640);
        $formatter = new LineFormatter("%message%\n", null, true, true);
        $handler->setFormatter($formatter);

        static::$logger = new Logger('dms_audit', [$handler]);

        return static::$logger;
    }

    /**
     * Append a structured JSONL audit record to the log stream.
     *
     * Record Schema:
     * {"timestamp": "ISO8601", "event": "upload|download|delete", "user_id": 42,
     *  "user_email": "gsr.maadi@aaegypt.org", "group_id": 3, "doc_id": "uuid",
     *  "doc_name": "minutes.pdf", "sha256": "hash...", "ip": "1.2.3.4", "status": "clean|infected"}
     *
     * @param  array<string, mixed>  $extra
     * @return array<string, mixed>
     */
    public static function log(
        string $event,
        DmsDocument|array|null $document = null,
        ?User $user = null,
        ?string $ip = null,
        ?string $status = null,
        array $extra = []
    ): array {
        $user = $user ?? Auth::user() ?? request()?->user();

        $userId = $user?->id;
        $userEmail = $user?->email;
        $groupId = $user?->group_id;

        $docId = null;
        $docName = null;
        $sha256 = null;
        $docStatus = $status;

        if ($document instanceof DmsDocument) {
            $docId = (string) $document->id;
            $docName = $document->original_name ?: $document->sanitized_name ?: 'document';
            $sha256 = $document->sha256_hash ?: '';
            $docStatus = $docStatus ?? ($document->status === 'rejected' ? 'infected' : ($document->status ?: 'clean'));
            $groupId = $groupId ?? $document->folder?->group_id;
        } elseif (is_array($document)) {
            $docId = $document['doc_id'] ?? $document['id'] ?? null;
            $docName = $document['doc_name'] ?? $document['original_name'] ?? $document['sanitized_name'] ?? null;
            $sha256 = $document['sha256'] ?? $document['sha256_hash'] ?? '';
            $docStatus = $docStatus ?? $document['status'] ?? null;
            $groupId = $groupId ?? $document['group_id'] ?? null;
            $userId = $userId ?? $document['user_id'] ?? null;
            $userEmail = $userEmail ?? $document['user_email'] ?? null;
        }

        $effectiveIp = $ip ?? request()?->ip() ?? '127.0.0.1';

        $record = array_merge([
            'timestamp' => now()->toIso8601String(),
            'event' => $event,
            'user_id' => $userId,
            'user_email' => $userEmail,
            'group_id' => $groupId,
            'doc_id' => $docId,
            'doc_name' => $docName,
            'sha256' => $sha256 ?? '',
            'ip' => $effectiveIp,
            'status' => $docStatus ?? 'clean',
        ], $extra);

        $jsonLine = json_encode($record, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        static::getLogger()->info($jsonLine);

        $logPath = static::getLogPath();
        if (file_exists($logPath)) {
            @chmod($logPath, 0640);
        }

        return $record;
    }

    /**
     * Log a document upload action.
     *
     * @param  array<string, mixed>  $extra
     * @return array<string, mixed>
     */
    public static function logUpload(DmsDocument|array $doc, ?User $user = null, ?string $ip = null, array $extra = []): array
    {
        return static::log('upload', $doc, $user, $ip, null, $extra);
    }

    /**
     * Log a document download action.
     *
     * @param  array<string, mixed>  $extra
     * @return array<string, mixed>
     */
    public static function logDownload(DmsDocument|array $doc, ?User $user = null, ?string $ip = null, array $extra = []): array
    {
        return static::log('download', $doc, $user, $ip, null, $extra);
    }

    /**
     * Log a document delete action.
     *
     * @param  array<string, mixed>  $extra
     * @return array<string, mixed>
     */
    public static function logDelete(DmsDocument|array $doc, ?User $user = null, ?string $ip = null, array $extra = []): array
    {
        return static::log('delete', $doc, $user, $ip, null, $extra);
    }

    /**
     * Log a malware / sanitization scan pass event.
     *
     * @param  array<string, mixed>  $extra
     * @return array<string, mixed>
     */
    public static function logScanPass(DmsDocument|array $doc, ?User $user = null, ?string $ip = null, array $extra = []): array
    {
        return static::log('scan_pass', $doc, $user, $ip, 'clean', $extra);
    }

    /**
     * Log a malware / sanitization scan fail event.
     *
     * @param  array<string, mixed>  $extra
     * @return array<string, mixed>
     */
    public static function logScanFail(DmsDocument|array $doc, ?User $user = null, ?string $ip = null, array $extra = []): array
    {
        return static::log('scan_fail', $doc, $user, $ip, 'infected', $extra);
    }

    /**
     * Log a document version bump.
     *
     * @param  array<string, mixed>  $extra
     * @return array<string, mixed>
     */
    public static function logVersionBump(DmsDocument|array $doc, ?User $user = null, ?string $ip = null, array $extra = []): array
    {
        return static::log('version_bump', $doc, $user, $ip, null, $extra);
    }

    /**
     * Log a permission or access matrix change on documents or folders.
     *
     * @param  array<string, mixed>  $extra
     * @return array<string, mixed>
     */
    public static function logPermissionChange(
        DmsDocument|DmsFolder|array $entity,
        ?User $user = null,
        ?string $ip = null,
        array $extra = []
    ): array {
        if ($entity instanceof DmsFolder) {
            $docData = [
                'doc_id' => 'folder_'.$entity->id,
                'doc_name' => $entity->name_en ?: $entity->name_ar ?: 'Folder '.$entity->id,
                'group_id' => $entity->group_id,
                'sha256' => '',
                'status' => 'clean',
            ];

            return static::log('permission_change', $docData, $user, $ip, 'clean', $extra);
        }

        return static::log('permission_change', $entity, $user, $ip, null, $extra);
    }

    /**
     * Read and filter log entries from the audit log file.
     *
     * @param  array<string, mixed>  $filters
     * @return Collection<int, array<string, mixed>>
     */
    public static function getEntries(array $filters = []): Collection
    {
        $logPath = static::getLogPath();

        if (! file_exists($logPath) || ! is_readable($logPath)) {
            return collect();
        }

        $lines = file($logPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if ($lines === false) {
            return collect();
        }

        $entries = collect();

        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '' || $line[0] !== '{') {
                continue;
            }

            $decoded = json_decode($line, true);
            if (! is_array($decoded)) {
                continue;
            }

            // 1. Date Range Filter
            $dateFrom = $filters['date_from'] ?? $filters['from'] ?? $filters['start_date'] ?? null;
            $dateTo = $filters['date_to'] ?? $filters['to'] ?? $filters['end_date'] ?? null;

            if ($dateFrom && isset($decoded['timestamp'])) {
                $entryTime = strtotime($decoded['timestamp']);
                $fromTime = strtotime($dateFrom);
                if ($entryTime < $fromTime) {
                    continue;
                }
            }

            if ($dateTo && isset($decoded['timestamp'])) {
                $entryTime = strtotime($decoded['timestamp']);
                $toTime = strlen($dateTo) === 10 ? strtotime($dateTo.' 23:59:59') : strtotime($dateTo);
                if ($entryTime > $toTime) {
                    continue;
                }
            }

            // 2. Action / Event filter
            $eventFilter = $filters['event'] ?? $filters['action'] ?? null;
            if ($eventFilter && ! empty($eventFilter) && $eventFilter !== 'all') {
                if (strcasecmp((string) ($decoded['event'] ?? ''), (string) $eventFilter) !== 0) {
                    continue;
                }
            }

            // 3. User filter
            $userFilter = $filters['user'] ?? $filters['user_id'] ?? $filters['user_email'] ?? null;
            if ($userFilter && ! empty($userFilter) && $userFilter !== 'all') {
                $match = false;
                if (isset($decoded['user_id']) && (string) $decoded['user_id'] === (string) $userFilter) {
                    $match = true;
                }
                if (isset($decoded['user_email']) && stripos((string) $decoded['user_email'], (string) $userFilter) !== false) {
                    $match = true;
                }
                if (! $match) {
                    continue;
                }
            }

            // 4. Status filter
            $statusFilter = $filters['status'] ?? null;
            if ($statusFilter && ! empty($statusFilter) && $statusFilter !== 'all') {
                if (strcasecmp((string) ($decoded['status'] ?? ''), (string) $statusFilter) !== 0) {
                    continue;
                }
            }

            // 5. Document filter
            $docFilter = $filters['doc_id'] ?? $filters['document'] ?? null;
            if ($docFilter && ! empty($docFilter)) {
                $match = false;
                if (isset($decoded['doc_id']) && (string) $decoded['doc_id'] === (string) $docFilter) {
                    $match = true;
                }
                if (isset($decoded['doc_name']) && stripos((string) $decoded['doc_name'], (string) $docFilter) !== false) {
                    $match = true;
                }
                if (! $match) {
                    continue;
                }
            }

            $entries->push($decoded);
        }

        // Return latest entries first (descending timestamp)
        return $entries->sortByDesc(function ($entry) {
            return $entry['timestamp'] ?? '';
        })->values();
    }

    /**
     * Delegate instance calls to static methods.
     */
    public function __call(string $method, array $arguments): mixed
    {
        return static::$method(...$arguments);
    }
}
