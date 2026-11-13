<?php

namespace App\Http\Controllers\Service;

use App\Http\Controllers\Controller;
use App\Models\DmsDocument;
use App\Policies\DocumentPolicy;
use App\Services\AuditLogger;
use App\Services\Dms\Audit\DmsAuditLogger;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class DmsDownloadController extends Controller
{
    /**
     * Download a sanitized document securely from the DMS vault.
     */
    public function download(string|DmsDocument $uuid): BinaryFileResponse
    {
        $document = $uuid instanceof DmsDocument ? $uuid : DmsDocument::find($uuid);

        if (! $document || $document->status !== 'clean') {
            abort(404, 'Document not found or unavailable for download.');
        }

        $user = auth()->user() ?? request()?->user();

        if (! $user) {
            abort(403, 'Unauthorized.');
        }

        if (! DocumentPolicy::download($user, $document)) {
            abort(403, 'You do not have permission to download this document.');
        }

        $vaultPath = $document->getVaultPath();

        if (! file_exists($vaultPath)) {
            abort(404, 'Document file not found on storage server.');
        }

        $cleanFileName = $document->sanitized_name ?: basename($vaultPath);

        // Security headers applied to every download response
        $headers = [
            'Content-Disposition' => 'attachment; filename="'.$cleanFileName.'"',
            'X-Content-Type-Options' => 'nosniff',
            'Content-Security-Policy' => "default-src 'none'; sandbox",
            'Cache-Control' => 'private, no-cache, no-store, must-revalidate',
            'X-Robots-Tag' => 'noindex, nofollow',
        ];

        // Trigger download audit log event
        AuditLogger::log(
            action: 'document_download',
            resource: 'dms_document',
            resourceId: $document->id,
            payload: [
                'original_name' => $document->original_name,
                'sanitized_name' => $cleanFileName,
                'folder_id' => $document->folder_id,
                'file_size' => $document->file_size,
            ],
            userId: $user->id,
        );

        // Record in append-only DMS audit log
        DmsAuditLogger::logDownload($document, $user, request()?->ip());

        $response = response()->download($vaultPath, $cleanFileName, $headers);
        $response->setPrivate();
        $response->headers->set('Content-Disposition', 'attachment; filename="'.$cleanFileName.'"');
        $response->headers->set('Cache-Control', 'private, no-cache, no-store, must-revalidate');
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('Content-Security-Policy', "default-src 'none'; sandbox");
        $response->headers->set('X-Robots-Tag', 'noindex, nofollow');

        return $response;
    }
}
