<?php

namespace App\Http\Controllers\Service;

use App\Http\Controllers\Controller;
use App\Models\DmsDocument;
use App\Models\DmsFolder;
use App\Services\Dms\Audit\DmsAuditLogger;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class DmsController extends Controller
{
    /**
     * Display root folders or folder specified via query parameter.
     */
    public function index(Request $request): Response
    {
        $user = $request->user();

        // If folder parameter provided in query string, route to that folder view
        if ($request->has('folder') && is_numeric($request->query('folder'))) {
            $folder = DmsFolder::findOrFail($request->query('folder'));

            return $this->showFolder($request, $folder);
        }

        // Ensure default root folders exist
        if (DmsFolder::whereNull('parent_id')->count() === 0) {
            DmsFolder::seedDefaultFolders();
        }

        // Fetch top-level root folders that the user is authorized to view
        $rootFolders = DmsFolder::whereNull('parent_id')
            ->get()
            ->filter(fn (DmsFolder $folder) => $folder->canUserAccess($user, 'view'))
            ->values();

        $breadcrumbs = $this->buildBreadcrumbs(null);

        return response()
            ->view('service.dms.index', [
                'currentFolder' => null,
                'folders' => $rootFolders,
                'documents' => collect(),
                'breadcrumbs' => $breadcrumbs,
                'canUpload' => false,
                'isAdmin' => $user ? $user->role === 'web_admin' : false,
            ])
            ->header('X-Robots-Tag', 'noindex, nofollow');
    }

    /**
     * Display contents of a specific folder.
     */
    public function showFolder(Request $request, DmsFolder $folder): Response
    {
        $user = $request->user();

        if (! $folder->canUserAccess($user, 'view')) {
            abort(403, 'Unauthorized access to this folder.');
        }

        $subfolders = $folder->children()
            ->get()
            ->filter(fn (DmsFolder $child) => $child->canUserAccess($user, 'view'))
            ->values();

        $documents = $folder->documents()->latest()->get();
        $breadcrumbs = $this->buildBreadcrumbs($folder);

        return response()
            ->view('service.dms.index', [
                'currentFolder' => $folder,
                'folders' => $subfolders,
                'documents' => $documents,
                'breadcrumbs' => $breadcrumbs,
                'canUpload' => $folder->canUserAccess($user, 'upload'),
                'isAdmin' => $user ? $user->role === 'web_admin' : false,
            ])
            ->header('X-Robots-Tag', 'noindex, nofollow');
    }

    /**
     * Upload a new document into a folder.
     */
    public function upload(Request $request)
    {
        $user = $request->user();

        $validated = $request->validate([
            'folder_id' => 'required|exists:dms_folders,id',
            'file' => 'required|file|max:51200', // max 50 MB
        ]);

        $folder = DmsFolder::findOrFail($validated['folder_id']);

        if (! $folder->canUserAccess($user, 'upload')) {
            abort(403, 'You do not have permission to upload documents to this folder.');
        }

        $file = $request->file('file');
        $originalName = $file->getClientOriginalName();
        $extension = $file->getClientOriginalExtension();
        $baseName = pathinfo($originalName, PATHINFO_FILENAME);
        $cleanBase = Str::slug($baseName);
        if (empty($cleanBase)) {
            $cleanBase = 'document';
        }
        $sanitizedName = $extension ? "{$cleanBase}.{$extension}" : $cleanBase;

        $sha256Hash = hash_file('sha256', $file->getRealPath());
        $fileSize = $file->getSize();
        $mimeType = $file->getMimeType() ?: 'application/octet-stream';

        // Compute incremental version
        $latestDoc = DmsDocument::where('folder_id', $folder->id)
            ->where('sanitized_name', $sanitizedName)
            ->orderByDesc('version')
            ->first();
        $version = $latestDoc ? ($latestDoc->version + 1) : 1;

        $documentId = (string) Str::uuid();
        $storedFileName = "{$documentId}_{$sanitizedName}";
        $vaultRelativePath = "{$folder->id}/{$storedFileName}";
        $vaultDirectory = storage_path("app/dms_vault/{$folder->id}");

        if (! file_exists($vaultDirectory)) {
            mkdir($vaultDirectory, 0775, true);
        }

        $file->move($vaultDirectory, $storedFileName);

        $document = DmsDocument::create([
            'id' => $documentId,
            'folder_id' => $folder->id,
            'user_id' => $user->id,
            'original_name' => $originalName,
            'sanitized_name' => $sanitizedName,
            'file_path' => $vaultRelativePath,
            'file_size' => $fileSize,
            'mime_type' => $mimeType,
            'sha256_hash' => $sha256Hash,
            'version' => $version,
            'status' => 'clean',
        ]);

        // Record in append-only DMS audit log
        DmsAuditLogger::logUpload($document, $user, $request->ip());
        if ($document->version > 1) {
            DmsAuditLogger::logVersionBump($document, $user, $request->ip());
        }

        if ($request->wantsJson()) {
            return response()->json([
                'message' => 'Document uploaded successfully',
                'document' => $document,
            ], 201)->header('X-Robots-Tag', 'noindex, nofollow');
        }

        return redirect()
            ->route('service.documents.folder', $folder)
            ->with('status', "Document '{$originalName}' uploaded successfully.")
            ->header('X-Robots-Tag', 'noindex, nofollow');
    }

    /**
     * Download a document securely from the vault.
     */
    public function download(Request $request, DmsDocument $document): BinaryFileResponse
    {
        return app(DmsDownloadController::class)->download($document);
    }

    /**
     * Delete a document.
     */
    public function destroy(Request $request, DmsDocument $document)
    {
        $user = $request->user();

        if (! $document->canUserAccess($user, 'delete')) {
            abort(403, 'Only administrators can delete documents.');
        }

        $folder = $document->folder;
        $fullPath = storage_path('app/dms_vault/'.ltrim($document->file_path, '/'));

        if (file_exists($fullPath)) {
            @unlink($fullPath);
        }

        // Record in append-only DMS audit log
        DmsAuditLogger::logDelete($document, $user, $request->ip());

        $document->delete();

        if ($request->wantsJson()) {
            return response()->json([
                'message' => 'Document deleted successfully',
            ])->header('X-Robots-Tag', 'noindex, nofollow');
        }

        $redirectRoute = $folder ? route('service.documents.folder', $folder) : route('service.documents.index');

        return redirect($redirectRoute)
            ->with('status', 'Document deleted successfully.')
            ->header('X-Robots-Tag', 'noindex, nofollow');
    }

    /**
     * Build breadcrumbs trail for current folder.
     *
     * @return array<int, array{name_en: string, name_ar: string, url: string}>
     */
    protected function buildBreadcrumbs(?DmsFolder $folder): array
    {
        $breadcrumbs = [
            [
                'name_en' => 'DMS Root',
                'name_ar' => 'رئيسية المستندات',
                'url' => route('service.documents.index'),
            ],
        ];

        if (! $folder) {
            return $breadcrumbs;
        }

        $chain = [];
        $current = $folder;
        while ($current) {
            array_unshift($chain, [
                'name_en' => $current->name_en,
                'name_ar' => $current->name_ar,
                'url' => route('service.documents.folder', $current),
            ]);
            $current = $current->parent;
        }

        return array_merge($breadcrumbs, $chain);
    }
}
