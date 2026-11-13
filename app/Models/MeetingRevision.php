<?php

namespace App\Models;

use Database\Factories\MeetingRevisionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use RuntimeException;

class MeetingRevision extends Model
{
    /** @use HasFactory<MeetingRevisionFactory> */
    use HasFactory;

    public const UPDATED_AT = null;

    protected $fillable = [
        'meeting_id',
        'user_id',
        'version',
        'old_payload',
        'new_payload',
        'created_at',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'meeting_id' => 'integer',
            'user_id' => 'integer',
            'version' => 'integer',
            'old_payload' => 'array',
            'new_payload' => 'array',
            'created_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::updating(function () {
            throw new RuntimeException('Meeting revisions are append-only and cannot be updated.');
        });
    }

    public function meeting(): BelongsTo
    {
        return $this->belongsTo(Meeting::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
