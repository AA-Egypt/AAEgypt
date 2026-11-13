<?php

namespace App\Models;

use Database\Factories\DmsDocumentFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DmsDocument extends Model
{
    /** @use HasFactory<DmsDocumentFactory> */
    use HasFactory, HasUuids;

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'folder_id',
        'user_id',
        'original_name',
        'sanitized_name',
        'file_path',
        'file_size',
        'mime_type',
        'sha256_hash',
        'version',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'file_size' => 'integer',
            'version' => 'integer',
        ];
    }

    public function folder(): BelongsTo
    {
        return $this->belongsTo(DmsFolder::class, 'folder_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * Determine whether the given user can access this document.
     */
    public function canUserAccess(?User $user, string $action = 'view'): bool
    {
        if (! $user) {
            return false;
        }

        if ($user->role === 'web_admin') {
            return true;
        }

        if ($action === 'delete') {
            return false;
        }

        if (! $this->folder) {
            $folder = DmsFolder::find($this->folder_id);

            return $folder ? $folder->canUserAccess($user, $action) : false;
        }

        return $this->folder->canUserAccess($user, $action);
    }

    /**
     * Human readable file size string.
     */
    public function getFormattedFileSizeAttribute(): string
    {
        $bytes = $this->file_size;

        if ($bytes >= 1073741824) {
            return number_format($bytes / 1073741824, 2).' GB';
        }

        if ($bytes >= 1048576) {
            return number_format($bytes / 1048576, 2).' MB';
        }

        if ($bytes >= 1024) {
            return number_format($bytes / 1024, 1).' KB';
        }

        return $bytes.' B';
    }

    /**
     * Get the absolute path in dms_vault.
     */
    public function getVaultPath(): string
    {
        return storage_path('app/dms_vault/'.ltrim($this->file_path, '/'));
    }
}
