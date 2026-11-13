<?php

namespace App\Jobs;

use App\Models\DmsDocument;
use App\Models\User;
use App\Notifications\DocumentInfectedNotification;
use App\Services\AuditLogger;
use App\Services\Dms\Sanitizer\DocumentSanitizerService;
use App\Services\Dms\Sanitizer\SanitizerResult;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ProcessDmsUploadJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public string $documentId;

    /**
     * Create a new job instance.
     */
    public function __construct(string|DmsDocument $documentId)
    {
        $this->documentId = $documentId instanceof DmsDocument ? $documentId->id : $documentId;
    }

    /**
     * Execute the job to sanitize and transition the uploaded document.
     */
    public function handle(DocumentSanitizerService $sanitizer): void
    {
        $document = DmsDocument::find($this->documentId);

        if (! $document) {
            Log::warning("ProcessDmsUploadJob: DmsDocument [{$this->documentId}] not found.");

            return;
        }

        $tempFilePath = $this->resolveQuarantineFilePath($document);

        if (! $tempFilePath || ! file_exists($tempFilePath)) {
            Log::error("ProcessDmsUploadJob: Quarantine temp file not found for document [{$document->id}].");
            $document->update(['status' => 'rejected']);

            return;
        }

        $extension = strtolower((string) (
            pathinfo($document->original_name, PATHINFO_EXTENSION) ?:
            pathinfo($document->sanitized_name, PATHINFO_EXTENSION)
        ));

        $result = $sanitizer->sanitize($tempFilePath, $extension);

        if ($result->isClean()) {
            $this->handleCleanDocument($document, $tempFilePath, $result);
        } else {
            $this->handleInfectedDocument($document, $tempFilePath, $result);
        }
    }

    /**
     * Resolve the location of the quarantine staging file on disk.
     */
    protected function resolveQuarantineFilePath(DmsDocument $document): ?string
    {
        $candidates = [
            storage_path("app/dms_quarantine/{$document->id}.tmp"),
            storage_path('app/'.ltrim((string) $document->file_path, '/')),
            storage_path("app/dms_quarantine/{$document->file_path}"),
        ];

        foreach ($candidates as $path) {
            if (file_exists($path) && is_file($path)) {
                return $path;
            }
        }

        return null;
    }

    /**
     * Move sanitized document into permanent vault storage and update attributes.
     */
    protected function handleCleanDocument(DmsDocument $document, string $tempFilePath, SanitizerResult $result): void
    {
        $folderId = $document->folder_id;
        $vaultDir = storage_path("app/dms_vault/{$folderId}");

        if (! file_exists($vaultDir)) {
            mkdir($vaultDir, 0775, true);
        }

        $storedFileName = "{$document->id}_{$document->sanitized_name}";
        $vaultFilePath = "{$vaultDir}/{$storedFileName}";
        $vaultRelativePath = "{$folderId}/{$storedFileName}";

        // Move sanitized file to permanent storage
        if (@copy($tempFilePath, $vaultFilePath)) {
            @unlink($tempFilePath);
        } else {
            @rename($tempFilePath, $vaultFilePath);
        }

        // Ensure temp staging file is destroyed
        if (file_exists($tempFilePath)) {
            @unlink($tempFilePath);
        }

        $sha256 = hash_file('sha256', $vaultFilePath) ?: str_repeat('0', 64);
        $fileSize = file_exists($vaultFilePath) ? filesize($vaultFilePath) : $document->file_size;
        $mimeType = $result->mimeType ?? ($this->detectMimeType($vaultFilePath) ?: $document->mime_type);

        $document->update([
            'status' => 'clean',
            'file_path' => $vaultRelativePath,
            'sha256_hash' => $sha256,
            'file_size' => $fileSize,
            'mime_type' => $mimeType,
        ]);

        AuditLogger::log(
            action: 'document_sanitized_clean',
            resource: 'dms_document',
            resourceId: $document->id,
            payload: [
                'original_name' => $document->original_name,
                'sha256_hash' => $sha256,
                'file_size' => $fileSize,
                'sanitizer_details' => $result->details,
            ],
            userId: $document->user_id,
        );
    }

    /**
     * Handle quarantine and alerts for rejected or infected documents.
     */
    protected function handleInfectedDocument(DmsDocument $document, string $tempFilePath, SanitizerResult $result): void
    {
        $infectedDir = storage_path('app/dms_quarantine/infected');

        if (! file_exists($infectedDir)) {
            mkdir($infectedDir, 0755, true);
        }

        $infectedFileName = "{$document->id}.tmp";
        $infectedFilePath = "{$infectedDir}/{$infectedFileName}";
        $infectedRelativePath = "dms_quarantine/infected/{$infectedFileName}";

        // Move file to infected quarantine staging
        if (file_exists($tempFilePath) && $tempFilePath !== $infectedFilePath) {
            if (@rename($tempFilePath, $infectedFilePath) === false) {
                @copy($tempFilePath, $infectedFilePath);
                @unlink($tempFilePath);
            }
        }

        $document->update([
            'status' => 'rejected',
            'file_path' => $infectedRelativePath,
        ]);

        $reason = $result->error ?: 'Malware or security policy violation detected';

        // Log audit record
        AuditLogger::log(
            action: 'document_infected',
            resource: 'dms_document',
            resourceId: $document->id,
            payload: [
                'original_name' => $document->original_name,
                'reason' => $reason,
                'sanitizer_status' => $result->status,
                'details' => $result->details,
                'infected_path' => $infectedRelativePath,
            ],
            userId: $document->user_id,
        );

        // Notify uploader and Web Admins
        $this->notifyParties($document, $reason, $result->details);

        // Delete infected files after quarantine retention period
        $this->processQuarantineRetention($infectedFilePath);
    }

    /**
     * Notify uploader and web administrators.
     *
     * @param  array<string, mixed>  $details
     */
    protected function notifyParties(DmsDocument $document, string $reason, array $details = []): void
    {
        $notification = new DocumentInfectedNotification($document, $reason, $details);

        if ($document->user) {
            $document->user->notify($notification);
        }

        $webAdmins = User::where('role', 'web_admin')->get();
        foreach ($webAdmins as $admin) {
            if (! $document->user || $admin->id !== $document->user->id) {
                $admin->notify($notification);
            }
        }
    }

    /**
     * Clean up quarantined files that exceed retention threshold.
     */
    public function processQuarantineRetention(?string $currentInfectedFilePath = null): void
    {
        $retentionDays = config('dms.quarantine_retention_days', 30);

        // If retention is configured as 0, delete infected file immediately
        if ($retentionDays === 0 || $retentionDays === '0') {
            if ($currentInfectedFilePath && file_exists($currentInfectedFilePath)) {
                @unlink($currentInfectedFilePath);
            }

            return;
        }

        $this->purgeExpiredQuarantine((int) $retentionDays);
    }

    /**
     * Purge infected quarantine files older than given retention days.
     */
    public function purgeExpiredQuarantine(int $retentionDays): int
    {
        $infectedDir = storage_path('app/dms_quarantine/infected');
        if (! is_dir($infectedDir)) {
            return 0;
        }

        $deletedCount = 0;
        $threshold = now()->subDays($retentionDays)->timestamp;

        $files = glob($infectedDir.'/*');
        if (! is_array($files)) {
            return 0;
        }

        foreach ($files as $file) {
            if (is_file($file) && filemtime($file) < $threshold) {
                if (@unlink($file)) {
                    $deletedCount++;
                }
            }
        }

        return $deletedCount;
    }

    /**
     * Detect MIME type safely.
     */
    protected function detectMimeType(string $path): ?string
    {
        if (! file_exists($path)) {
            return null;
        }

        $finfo = new \finfo(FILEINFO_MIME_TYPE);

        return $finfo->file($path) ?: null;
    }
}
