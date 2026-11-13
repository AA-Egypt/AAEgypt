<?php

namespace App\Livewire\Service;

use App\Models\Meeting;
use App\Models\MeetingRevision;
use App\Services\Meeting\MeetingRevisionService;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.public')]
#[Title('Meeting Revision History - Service')]
class MeetingRevisionHistory extends Component
{
    public ?int $meetingId = null;

    public ?Meeting $meeting = null;

    public ?int $selectedRevisionId = null;

    /**
     * @var array<string, array<string, mixed>>|null
     */
    public ?array $diffPreview = null;

    /**
     * Mount the revision history component.
     */
    public function mount(Meeting|int|null $meeting = null, ?int $meetingId = null): void
    {
        $user = Auth::user();

        if (! $user) {
            abort(401);
        }

        if (! in_array($user->role, ['gsr', 'delegate', 'web_admin'], true)) {
            abort(403, 'Forbidden. Only Group Service Representatives, Delegates, and Web Admins can view revision history.');
        }

        $id = $meeting instanceof Meeting ? $meeting->id : ($meeting ?? $meetingId);

        if ($id) {
            $this->meetingId = (int) $id;
            $this->loadMeeting();
        }
    }

    /**
     * Load the meeting and verify authorization.
     */
    public function loadMeeting(): void
    {
        if (! $this->meetingId) {
            $this->meeting = null;

            return;
        }

        $user = Auth::user();
        $meeting = Meeting::findOrFail($this->meetingId);

        if ($user->role !== 'web_admin' && (int) $meeting->group_id !== (int) $user->group_id) {
            abort(403, 'Forbidden. GSR cannot view another group\'s meeting revision history.');
        }

        Gate::authorize('view', $meeting);

        $this->meeting = $meeting;
    }

    /**
     * Get revisions for the current meeting.
     *
     * @return Collection<int, MeetingRevision>
     */
    public function getRevisionsProperty(): Collection
    {
        if (! $this->meetingId) {
            return new Collection;
        }

        return MeetingRevision::where('meeting_id', $this->meetingId)
            ->with('user')
            ->orderBy('id', 'desc')
            ->get();
    }

    /**
     * Preview diff for a specific revision.
     */
    public function previewDiff(int $revisionId): void
    {
        $revision = MeetingRevision::findOrFail($revisionId);

        if ($this->meetingId && (int) $revision->meeting_id !== (int) $this->meetingId) {
            abort(403);
        }

        $service = app(MeetingRevisionService::class);
        $this->selectedRevisionId = $revision->id;
        $this->diffPreview = $service->diff($revision->old_payload ?? [], $revision->new_payload ?? []);
    }

    /**
     * Clear the diff preview modal/panel.
     */
    public function clearPreview(): void
    {
        $this->selectedRevisionId = null;
        $this->diffPreview = null;
    }

    /**
     * 1-Click restore of a meeting revision.
     */
    public function restore(int $revisionId): void
    {
        $user = Auth::user();

        if (! $user) {
            abort(401);
        }

        if (! in_array($user->role, ['gsr', 'delegate', 'web_admin'], true)) {
            abort(403, 'Forbidden. Unauthorized to restore meeting revisions.');
        }

        $revision = MeetingRevision::with('meeting')->findOrFail($revisionId);
        $meeting = $revision->meeting;

        if ($user->role !== 'web_admin' && (int) $meeting->group_id !== (int) $user->group_id) {
            abort(403, 'Forbidden. You can only restore your assigned group\'s meetings.');
        }

        Gate::authorize('update', $meeting);

        $service = app(MeetingRevisionService::class);
        $restoredMeeting = $service->restore($revision, $user->id);

        $this->meeting = $restoredMeeting;
        $this->meetingId = $restoredMeeting->id;
        $this->clearPreview();

        $message = "Version {$revision->version} restored successfully. New live version is {$restoredMeeting->version}.";
        session()->flash('message', $message);
        session()->flash('success', $message);

        $this->dispatch('meeting-restored', revisionId: $revisionId, version: $restoredMeeting->version);
        $this->dispatch('meeting-updated', message: $message);
    }

    /**
     * Render the component view.
     */
    public function render(): View
    {
        return view('livewire.service.meeting-revision-history', [
            'revisions' => $this->revisions,
        ])->layout('layouts.public', ['title' => 'Meeting Revision History - Service']);
    }
}
