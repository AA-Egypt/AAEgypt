<?php

namespace App\Http\Controllers\Service;

use App\Http\Controllers\Controller;
use App\Jobs\ProcessDmsUploadJob;
use App\Models\DmsDocument;
use App\Models\DmsFolder;
use App\Services\Dms\Audit\DmsAuditLogger;
use App\Services\Dms\Sanitizer\DocumentSanitizerService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class DmsUploadController extends Controller
{
    /**
     * Store and queue an uploaded document into the asynchronous intake pipeline.
     */
    public function store(Request $request): JsonResponse|RedirectResponse
    {
        $user = $request->user();

        $allowedExtString = implode(',', DocumentSanitizerService::ALLOWED_EXTENSIONS);

        $validated = $request->validate([
            'folder_id' => ['required', 'exists:dms_folders,id'],
            'file' => [
                'required',
                'file',
                'max:25600', // 25 MB max (25,600 KB)
                "extensions:{$allowedExtString}",
            ],
        ], [
            'file.max' => 'The uploaded file exceeds the maximum allowed size of 25 MB.',
            'file.extensions' => 'The file extension is not permitted. Only PDF, DOCX, XLSX, PPTX, JPG, and PNG are accepted.',
        ]);

        $folder = DmsFolder::findOrFail($validated['folder_id']);

        if ($user && ! $folder->canUserAccess($user, 'upload')) {
            abort(403, 'You do not have permission to upload documents to this folder.');
        }

        $file = $request->file('file');
        $originalName = $file->getClientOriginalName();
        $extension = strtolower((string) ($file->getClientOriginalExtension() ?: pathinfo($originalName, PATHINFO_EXTENSION)));

        $documentId = (string) Str::uuid();
        $tempFileName = "{$documentId}.tmp";
        $quarantineDir = storage_path('app/dms_quarantine');

        if (! file_exists($quarantineDir)) {
            mkdir($quarantineDir, 0755, true);
        }

        $file->move($quarantineDir, $tempFileName);
        $quarantinePath = "{$quarantineDir}/{$tempFileName}";

        $baseName = pathinfo($originalName, PATHINFO_FILENAME);
        $cleanBase = Str::slug($baseName);
        if (empty($cleanBase)) {
            $cleanBase = 'document';
        }
        $sanitizedName = $extension ? "{$cleanBase}.{$extension}" : $cleanBase;

        $latestDoc = DmsDocument::where('folder_id', $folder->id)
            ->where('sanitized_name', $sanitizedName)
            ->orderByDesc('version')
            ->first();
        $version = $latestDoc ? ($latestDoc->version + 1) : 1;

        $fileSize = file_exists($quarantinePath) ? filesize($quarantinePath) : 0;
        $mimeType = $file->getClientMimeType() ?: 'application/octet-stream';
        $initialHash = file_exists($quarantinePath) ? (hash_file('sha256', $quarantinePath) ?: str_repeat('0', 64)) : str_repeat('0', 64);

        $document = DmsDocument::create([
            'id' => $documentId,
            'folder_id' => $folder->id,
            'user_id' => $user?->id,
            'original_name' => $originalName,
            'sanitized_name' => $sanitizedName,
            'file_path' => "dms_quarantine/{$tempFileName}",
            'file_size' => $fileSize,
            'mime_type' => $mimeType,
            'sha256_hash' => $initialHash,
            'version' => $version,
            'status' => 'processing',
        ]);

        // Record in append-only DMS audit log
        DmsAuditLogger::logUpload($document, $user, $request->ip());
        if ($document->version > 1) {
            DmsAuditLogger::logVersionBump($document, $user, $request->ip());
        }

        // Dispatch background processing job
        ProcessDmsUploadJob::dispatch($documentId);

        if ($request->wantsJson() || $request->expectsJson() || $request->ajax() || ! $request->hasHeader('referer')) {
            return response()->json([
                'message' => 'Document accepted for background sanitization and vaulting.',
                'status' => 'processing',
                'document_id' => $document->id,
                'document' => $document,
                'tracking_url' => route('service.documents.uploads.status', $document),
            ], 202)->header('X-Robots-Tag', 'noindex, nofollow');
        }

        return redirect()
            ->route('service.documents.folder', $folder)
            ->with('status', "Document '{$originalName}' queued for background sanitization.")
            ->with('document_id', $document->id)
            ->header('X-Robots-Tag', 'noindex, nofollow');
    }

    /**
     * Check processing status of a queued upload.
     */
    public function status(Request $request, DmsDocument $document): JsonResponse
    {
        $user = $request->user();

        if ($user && ! $document->canUserAccess($user, 'view')) {
            abort(403, 'Unauthorized access to this document status.');
        }

        return response()->json([
            'document_id' => $document->id,
            'status' => $document->status,
            'original_name' => $document->original_name,
            'sanitized_name' => $document->sanitized_name,
            'sha256_hash' => $document->sha256_hash,
            'file_size' => $document->file_size,
            'version' => $document->version,
            'created_at' => $document->created_at,
            'updated_at' => $document->updated_at,
        ])->header('X-Robots-Tag', 'noindex, nofollow');
    }

    /**
     * Show tracking resource.
     */
    public function show(Request $request, DmsDocument $document): JsonResponse
    {
        return $this->status($request, $document);
    }
}
