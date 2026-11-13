<?php

namespace App\Services\Meeting;

use App\Models\Meeting;
use App\Models\MeetingRevision;
use App\Services\AuditLogger;
use Illuminate\Support\Facades\Auth;

class MeetingRevisionService
{
    /**
     * Tracked attributes for meeting revisions and snapshots.
     *
     * @var array<int, string>
     */
    public const TRACKED_FIELDS = [
        'name',
        'title',
        'day_of_week',
        'start_time',
        'end_time',
        'time',
        'venue_address',
        'venue',
        'city',
        'latitude',
        'longitude',
        'type',
        'language',
        'notes',
        'online_link',
        'is_active',
    ];

    /**
     * Human-friendly labels for fields.
     *
     * @var array<string, string>
     */
    public const FIELD_LABELS = [
        'name' => 'Meeting Name',
        'title' => 'Meeting Title',
        'day_of_week' => 'Day of Week',
        'start_time' => 'Start Time',
        'end_time' => 'End Time',
        'time' => 'Meeting Time',
        'venue_address' => 'Venue Address',
        'venue' => 'Venue',
        'city' => 'City',
        'latitude' => 'Latitude',
        'longitude' => 'Longitude',
        'type' => 'Meeting Type',
        'language' => 'Language',
        'notes' => 'Notes',
        'online_link' => 'Online Link',
        'is_active' => 'Status',
    ];

    /**
     * Extract a snapshot of tracked attributes from a Meeting model.
     *
     * @return array<string, mixed>
     */
    public function extractSnapshot(Meeting $meeting): array
    {
        return [
            'name' => $meeting->name ?? $meeting->title ?? '',
            'title' => $meeting->title ?? $meeting->name ?? '',
            'day_of_week' => $meeting->day_of_week !== null ? (int) $meeting->day_of_week : null,
            'start_time' => $meeting->start_time ?? $meeting->time ?? '',
            'end_time' => $meeting->end_time ?? '',
            'time' => $meeting->time ?? $meeting->start_time ?? '',
            'venue_address' => $meeting->venue_address ?? $meeting->venue ?? '',
            'venue' => $meeting->venue ?? $meeting->venue_address ?? '',
            'city' => $meeting->city ?? 'Cairo',
            'latitude' => $meeting->latitude !== null ? (float) $meeting->latitude : null,
            'longitude' => $meeting->longitude !== null ? (float) $meeting->longitude : null,
            'type' => $meeting->type ?? 'in_person',
            'language' => $meeting->language ?? 'ar',
            'notes' => $meeting->notes ?? '',
            'online_link' => $meeting->online_link ?? '',
            'is_active' => (bool) ($meeting->is_active ?? true),
        ];
    }

    /**
     * Record a new revision in the append-only meeting_revisions table.
     *
     * @param  array<string, mixed>  $oldPayload
     * @param  array<string, mixed>  $newPayload
     */
    public function record(
        Meeting $meeting,
        array $oldPayload,
        array $newPayload,
        ?int $userId = null,
        ?int $version = null
    ): MeetingRevision {
        $effectiveUserId = $userId ?? Auth::id();
        $effectiveVersion = $version ?? (int) ($meeting->version ?? 1);

        return MeetingRevision::create([
            'meeting_id' => $meeting->id,
            'user_id' => $effectiveUserId,
            'version' => $effectiveVersion,
            'old_payload' => $oldPayload,
            'new_payload' => $newPayload,
            'created_at' => now(),
        ]);
    }

    /**
     * Restore a meeting back to a revision's snapshot and increment its version counter.
     */
    public function restore(int|MeetingRevision $revision, ?int $userId = null): Meeting
    {
        $revisionModel = $revision instanceof MeetingRevision
            ? $revision
            : MeetingRevision::with(['meeting', 'user'])->findOrFail($revision);

        $meeting = $revisionModel->meeting;
        $effectiveUserId = $userId ?? Auth::id();

        $oldSnapshot = $this->extractSnapshot($meeting);
        $targetPayload = ! empty($revisionModel->new_payload)
            ? $revisionModel->new_payload
            : $revisionModel->old_payload;

        $newVersion = (int) ($meeting->version ?? 1) + 1;

        $allowedAttributes = [
            'name',
            'title',
            'day_of_week',
            'start_time',
            'end_time',
            'time',
            'venue_address',
            'venue',
            'city',
            'latitude',
            'longitude',
            'type',
            'language',
            'notes',
            'online_link',
            'is_active',
        ];

        $updateData = [];
        foreach ($allowedAttributes as $attr) {
            if (array_key_exists($attr, $targetPayload)) {
                $updateData[$attr] = $targetPayload[$attr];
            }
        }

        $updateData['version'] = $newVersion;

        $meeting->update($updateData);

        $newSnapshot = $this->extractSnapshot($meeting);

        $this->record(
            meeting: $meeting,
            oldPayload: $oldSnapshot,
            newPayload: $newSnapshot,
            userId: $effectiveUserId,
            version: $newVersion
        );

        AuditLogger::log('restore', 'Meeting', $meeting->id, [
            'restored_revision_id' => $revisionModel->id,
            'restored_from_version' => $revisionModel->version,
            'new_version' => $newVersion,
        ], $effectiveUserId);

        return $meeting->fresh();
    }

    /**
     * Compute field differences between two payloads.
     *
     * @param  array<string, mixed>  $old
     * @param  array<string, mixed>  $new
     * @return array<string, array<string, mixed>>
     */
    public function diff(array $old, array $new): array
    {
        $diffs = [];
        $keys = array_unique(array_merge(array_keys($old), array_keys($new)));

        foreach ($keys as $key) {
            if (in_array($key, ['title', 'venue', 'time', 'search_vector', 'version', 'updated_at', 'created_at'], true)) {
                // Secondary aliases or metadata handled through primary equivalents
                continue;
            }

            $oldVal = $old[$key] ?? null;
            $newVal = $new[$key] ?? null;

            if (! $this->areValuesEqual($key, $oldVal, $newVal)) {
                $diffs[$key] = [
                    'field' => $key,
                    'label' => self::FIELD_LABELS[$key] ?? ucfirst(str_replace('_', ' ', $key)),
                    'old' => $oldVal,
                    'new' => $newVal,
                ];
            }
        }

        return $diffs;
    }

    /**
     * Get conflict differences showing what the concurrent user changed since form_version.
     *
     * @param  array<string, mixed>  $pendingFormValues
     * @return array<string, array<string, mixed>>
     */
    public function getConflictDiffs(Meeting $meeting, int $formVersion, array $pendingFormValues = []): array
    {
        $meeting->refresh();

        // 1. Find revisions recorded since form_version
        $revisions = MeetingRevision::where('meeting_id', $meeting->id)
            ->where('version', '>', $formVersion)
            ->with('user')
            ->orderBy('id', 'asc')
            ->get();

        $changedBy = 'Another servant';
        $userAChanges = [];

        if ($revisions->isNotEmpty()) {
            $firstRev = $revisions->first();
            $lastRev = $revisions->last();
            $changedBy = $lastRev->user?->name ?? 'Another servant';

            $baseOld = $firstRev->old_payload ?? [];
            $currentNew = $lastRev->new_payload ?? $this->extractSnapshot($meeting);
            $userAChanges = $this->diff($baseOld, $currentNew);
        } else {
            // Fallback to latest revision if version mismatch occurred without newer revision rows
            $latest = MeetingRevision::where('meeting_id', $meeting->id)
                ->with('user')
                ->latest('id')
                ->first();

            if ($latest) {
                $changedBy = $latest->user?->name ?? 'Another servant';
                $userAChanges = $this->diff($latest->old_payload ?? [], $latest->new_payload ?? []);
            }
        }

        // Build comprehensive conflict diff objects
        $diffs = [];
        $currentMeetingSnapshot = $this->extractSnapshot($meeting);

        foreach ($userAChanges as $field => $data) {
            $pendingVal = $pendingFormValues[$field] ?? null;
            $diffs[$field] = [
                'field' => $field,
                'label' => $data['label'],
                'old_value' => $data['old'],
                'new_value' => $data['new'],
                'current_server_value' => $currentMeetingSnapshot[$field] ?? $data['new'],
                'your_value' => $pendingVal,
                'changed_by' => $changedBy,
            ];
        }

        // If userAChanges was empty, compare pendingFormValues with current server values
        if (empty($diffs) && ! empty($pendingFormValues)) {
            $directDiffs = $this->diff($pendingFormValues, $currentMeetingSnapshot);
            foreach ($directDiffs as $field => $data) {
                $diffs[$field] = [
                    'field' => $field,
                    'label' => $data['label'],
                    'old_value' => $data['old'],
                    'new_value' => $data['new'],
                    'current_server_value' => $data['new'],
                    'your_value' => $data['old'],
                    'changed_by' => $changedBy,
                ];
            }
        }

        return $diffs;
    }

    /**
     * Check if two attribute values are considered equal.
     */
    protected function areValuesEqual(string $field, mixed $val1, mixed $val2): bool
    {
        if (is_null($val1) && (is_null($val2) || $val2 === '')) {
            return true;
        }

        if (is_null($val2) && (is_null($val1) || $val1 === '')) {
            return true;
        }

        if ($field === 'day_of_week') {
            return (int) $val1 === (int) $val2;
        }

        if (in_array($field, ['latitude', 'longitude'], true)) {
            if ($val1 === null || $val2 === null) {
                return $val1 === $val2;
            }

            return abs((float) $val1 - (float) $val2) < 0.000001;
        }

        if (is_bool($val1) || is_bool($val2)) {
            return (bool) $val1 === (bool) $val2;
        }

        return (string) $val1 === (string) $val2;
    }
}
