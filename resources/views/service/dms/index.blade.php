<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" dir="{{ app()->getLocale() === 'ar' ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>{{ $currentFolder ? $currentFolder->name_en . ' / ' . $currentFolder->name_ar : 'Document Management System' }} - {{ config('app.name', 'AA Egypt') }}</title>
    <style>
        body {
            font-family: system-ui, -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
            background-color: #f8fafc;
            color: #1e293b;
            margin: 0;
            padding: 2rem 1rem;
        }
        .container {
            max-width: 1050px;
            margin: 0 auto;
            background: #ffffff;
            border-radius: 8px;
            padding: 2rem;
            box-shadow: 0 4px 6px -1px rgb(0 0 0 / 0.1), 0 2px 4px -2px rgb(0 0 0 / 0.1);
        }
        .header-section {
            border-bottom: 1px solid #e2e8f0;
            padding-bottom: 1.5rem;
            margin-bottom: 1.5rem;
        }
        .title-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 1rem;
        }
        h1 { margin: 0; color: #0f172a; font-size: 1.6rem; }
        .subtitle { color: #64748b; font-size: 0.95rem; margin-top: 0.35rem; }
        .breadcrumbs {
            display: flex;
            align-items: center;
            flex-wrap: wrap;
            gap: 0.5rem;
            margin-bottom: 1.5rem;
            font-size: 0.9rem;
            color: #64748b;
        }
        .breadcrumbs a {
            color: #2563eb;
            text-decoration: none;
        }
        .breadcrumbs a:hover { text-decoration: underline; }
        .alert {
            padding: 1rem 1.25rem;
            border-radius: 6px;
            margin-bottom: 1.5rem;
        }
        .alert-success {
            background-color: #ecfdf5;
            border: 1px solid #a7f3d0;
            color: #065f46;
        }
        .alert-error {
            background-color: #fef2f2;
            border: 1px solid #fecaca;
            color: #991b1b;
        }
        .folder-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
            gap: 1.25rem;
            margin-bottom: 2rem;
        }
        .folder-card {
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            padding: 1.25rem;
            background: #f8fafc;
            text-decoration: none;
            color: inherit;
            display: flex;
            align-items: flex-start;
            gap: 1rem;
            transition: all 0.2s ease-in-out;
        }
        .folder-card:hover {
            background: #f1f5f9;
            border-color: #cbd5e1;
            transform: translateY(-2px);
            box-shadow: 0 4px 6px -1px rgb(0 0 0 / 0.05);
        }
        .folder-icon {
            flex-shrink: 0;
            width: 40px;
            height: 40px;
            background-color: #e0e7ff;
            color: #4338ca;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.3rem;
        }
        .folder-info { flex-grow: 1; }
        .folder-title-en { font-weight: 600; font-size: 1rem; color: #0f172a; margin-bottom: 0.2rem; }
        .folder-title-ar { font-size: 0.85rem; color: #64748b; direction: rtl; }
        .folder-meta { font-size: 0.75rem; color: #94a3b8; margin-top: 0.5rem; }
        .badge {
            display: inline-block;
            padding: 0.2rem 0.5rem;
            font-size: 0.75rem;
            font-weight: 600;
            border-radius: 9999px;
        }
        .badge-clean { background-color: #dcfce7; color: #166534; }
        .badge-quarantined { background-color: #fef3c7; color: #92400e; }
        .badge-rejected { background-color: #fee2e2; color: #991b1b; }
        .badge-processing { background-color: #e0e7ff; color: #3730a3; }
        .badge-role { background-color: #e2e8f0; color: #475569; font-size: 0.7rem; }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 1rem;
            font-size: 0.9rem;
        }
        th, td {
            text-align: left;
            padding: 0.75rem 1rem;
            border-bottom: 1px solid #e2e8f0;
        }
        [dir="rtl"] th, [dir="rtl"] td {
            text-align: right;
        }
        th {
            background-color: #f8fafc;
            font-weight: 600;
            color: #475569;
        }
        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 0.45rem 0.85rem;
            font-size: 0.85rem;
            font-weight: 500;
            border-radius: 6px;
            border: 1px solid transparent;
            cursor: pointer;
            text-decoration: none;
            transition: background-color 0.15s ease-in-out;
        }
        .btn-primary { background-color: #2563eb; color: #ffffff; }
        .btn-primary:hover { background-color: #1d4ed8; }
        .btn-download { background-color: #0284c7; color: #ffffff; }
        .btn-download:hover { background-color: #0369a1; }
        .btn-danger { background-color: #ef4444; color: #ffffff; }
        .btn-danger:hover { background-color: #dc2626; }
        .upload-section {
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 6px;
            padding: 1.5rem;
            margin-bottom: 2rem;
        }
        .upload-form {
            display: flex;
            align-items: center;
            flex-wrap: wrap;
            gap: 1rem;
        }
        .form-file-input {
            font-size: 0.9rem;
            color: #475569;
        }
        .empty-state {
            padding: 2.5rem;
            text-align: center;
            color: #64748b;
            background: #f8fafc;
            border-radius: 6px;
            border: 1px dashed #cbd5e1;
        }
        .nav-back {
            display: inline-block;
            margin-bottom: 1rem;
            color: #2563eb;
            text-decoration: none;
            font-size: 0.9rem;
        }
        .nav-back:hover { text-decoration: underline; }
    </style>
</head>
<body>
    <div class="container">
        <a href="{{ route('dashboard') }}" class="nav-back">&larr; Back to Service Dashboard</a>

        <div class="header-section">
            <div class="title-row">
                <div>
                    <h1>
                        @if ($currentFolder)
                            {{ $currentFolder->name_en }}
                            <span style="font-weight: normal; color: #64748b;"> / {{ $currentFolder->name_ar }}</span>
                        @else
                            Document Management System (DMS)
                        @endif
                    </h1>
                    <p class="subtitle">
                        @if ($currentFolder)
                            Secure storage repository &bull; Non-web-accessible vault
                        @else
                            AA Egypt Private Service Repository &bull; Secure Document Vault
                        @endif
                    </p>
                </div>
            </div>
        </div>

        <nav class="breadcrumbs" aria-label="Breadcrumb">
            @foreach ($breadcrumbs as $index => $crumb)
                @if ($index > 0)
                    <span>&rsaquo;</span>
                @endif
                @if ($loop->last && $currentFolder)
                    <span style="font-weight: 600; color: #0f172a;">{{ $crumb['name_en'] }}</span>
                @else
                    <a href="{{ $crumb['url'] }}">{{ $crumb['name_en'] }} ({{ $crumb['name_ar'] }})</a>
                @endif
            @endforeach
        </nav>

        @if (session('status'))
            <div class="alert alert-success" role="alert">
                {{ session('status') }}
            </div>
        @endif

        @if ($errors->any())
            <div class="alert alert-error" role="alert">
                <ul style="margin: 0; padding-left: 1.25rem;">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        @if ($folders->isNotEmpty())
            <h2 style="font-size: 1.15rem; margin-bottom: 1rem; color: #0f172a;">
                {{ $currentFolder ? 'Subfolders' : 'Document Folders (المجلدات)' }}
            </h2>
            <div class="folder-grid">
                @foreach ($folders as $folder)
                    <a href="{{ route('service.documents.folder', $folder) }}" class="folder-card">
                        <div class="folder-icon">&square;</div>
                        <div class="folder-info">
                            <div class="folder-title-en">{{ $folder->name_en }}</div>
                            <div class="folder-title-ar">{{ $folder->name_ar }}</div>
                            <div class="folder-meta">
                                <span>{{ $folder->documents()->count() }} files</span>
                                @if ($folder->required_role)
                                    &bull; <span class="badge badge-role">{{ $folder->required_role }}</span>
                                @endif
                                @if ($folder->group)
                                    &bull; <span class="badge badge-role">{{ $folder->group->name_en }}</span>
                                @endif
                            </div>
                        </div>
                    </a>
                @endforeach
            </div>
        @endif

        @if ($currentFolder)
            @if ($canUpload)
                <div class="upload-section">
                    <h3 style="margin-top: 0; font-size: 1.05rem; margin-bottom: 0.75rem;">Upload Document to this Folder</h3>
                    <form method="POST" action="{{ route('service.documents.upload') }}" enctype="multipart/form-data" class="upload-form">
                        @csrf
                        <input type="hidden" name="folder_id" value="{{ $currentFolder->id }}">
                        <input type="file" name="file" class="form-file-input" required>
                        <button type="submit" class="btn btn-primary">
                            Upload to Vault
                        </button>
                    </form>
                </div>
            @endif

            <h2 style="font-size: 1.15rem; margin-top: 2rem; margin-bottom: 0.5rem; color: #0f172a;">Documents (المستندات)</h2>

            @if ($documents->isEmpty())
                <div class="empty-state">
                    <p style="margin: 0;">No documents in this folder yet.</p>
                </div>
            @else
                <table>
                    <thead>
                        <tr>
                            <th>Document Name</th>
                            <th>Version</th>
                            <th>Size</th>
                            <th>Status</th>
                            <th>Uploaded</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($documents as $doc)
                            <tr>
                                <td>
                                    <strong>{{ $doc->original_name }}</strong>
                                    <div style="font-size: 0.75rem; color: #64748b;">SHA256: {{ substr($doc->sha256_hash, 0, 12) }}...</div>
                                </td>
                                <td>v{{ $doc->version }}</td>
                                <td>{{ $doc->formatted_file_size }}</td>
                                <td>
                                    <span class="badge badge-{{ $doc->status }}">
                                        {{ ucfirst($doc->status) }}
                                    </span>
                                </td>
                                <td>
                                    {{ $doc->created_at?->format('M d, Y') }}
                                    @if ($doc->user)
                                        <div style="font-size: 0.75rem; color: #64748b;">by {{ $doc->user->name }}</div>
                                    @endif
                                </td>
                                <td>
                                    <div style="display: flex; gap: 0.5rem; align-items: center;">
                                        <a href="{{ route('service.documents.download', $doc) }}" class="btn btn-download" style="padding: 0.25rem 0.5rem; font-size: 0.8rem;">
                                            Download
                                        </a>

                                        @if ($isAdmin)
                                            <form method="POST" action="{{ route('service.documents.destroy', $doc) }}" onsubmit="return confirm('Are you sure you want to delete this document from the vault?');">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="btn btn-danger" style="padding: 0.25rem 0.5rem; font-size: 0.8rem;">
                                                    Delete
                                                </button>
                                            </form>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
        @endif
    </div>
</body>
</html>
