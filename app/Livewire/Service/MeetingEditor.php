<?php

namespace App\Livewire\Service;

use App\Models\Group;
use App\Models\Meeting;
use App\Services\AuditLogger;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.public')]
#[Title('Meeting Editor - Service')]
class MeetingEditor extends Component
{
    /**
     * @var Collection<int, Meeting>
     */
    public Collection $meetings;

    public ?int $selectedMeetingId = null;

    public ?int $meetingId = null;

    public ?Group $group = null;

    public string $name = '';

    public string $title = '';

    public mixed $day = null;

    public mixed $day_of_week = null;

    public string $start_time = '';

    public string $end_time = '';

    public string $address = '';

    public string $venue_address = '';

    public ?string $city = 'Cairo';

    public ?float $latitude = null;

    public ?float $longitude = null;

    public ?string $coordinates = '';

    public string $type = 'in_person';

    public string $language = 'ar';

    public ?string $notes = '';

    public ?string $online_link = '';

    /**
     * Mount the component and load meetings for the authenticated user's group.
     */
    public function mount(?int $meetingId = null): void
    {
        $user = Auth::user();

        if (! $user) {
            abort(401);
        }

        if (! in_array($user->role, ['gsr', 'delegate', 'web_admin'], true)) {
            abort(403, 'Forbidden. Only Group Service Representatives (GSRs) and Delegates can access this page.');
        }

        if (empty($user->group_id) && $user->role !== 'web_admin') {
            abort(403, 'Forbidden. No group is assigned to your account.');
        }

        if ($user->group_id) {
            $this->group = Group::find($user->group_id);
        }

        $this->loadMeetings();

        if ($meetingId) {
            $this->selectMeeting($meetingId);
        } elseif ($this->meetings->isNotEmpty()) {
            $this->selectMeeting($this->meetings->first()->id);
        }
    }

    /**
     * Load meetings belonging to the authenticated user's group.
     */
    public function loadMeetings(): void
    {
        $user = Auth::user();

        if (! $user) {
            $this->meetings = collect();

            return;
        }

        if ($user->group_id) {
            $this->meetings = Meeting::where('group_id', $user->group_id)
                ->orderBy('day_of_week')
                ->orderBy('start_time')
                ->get();
        } elseif ($user->role === 'web_admin') {
            $this->meetings = Meeting::orderBy('name')->get();
        } else {
            $this->meetings = collect();
        }
    }

    /**
     * Select a meeting to edit after verifying authorization.
     */
    public function selectMeeting(int $id): void
    {
        $user = Auth::user();
        $meeting = Meeting::findOrFail($id);

        if ($user->role !== 'web_admin' && (int) $meeting->group_id !== (int) $user->group_id) {
            abort(403, 'Forbidden. GSR cannot view or edit another group\'s meetings.');
        }

        $this->selectedMeetingId = $meeting->id;
        $this->meetingId = $meeting->id;
        $this->fillForm($meeting);
    }

    /**
     * Fill form fields from a meeting model.
     */
    protected function fillForm(Meeting $meeting): void
    {
        $this->name = $meeting->name ?? $meeting->title ?? '';
        $this->title = $this->name;
        $this->day_of_week = $meeting->day_of_week;
        $this->day = $this->day_of_week;
        $this->start_time = $meeting->start_time ?? $meeting->time ?? '';
        $this->end_time = $meeting->end_time ?? '';
        $this->venue_address = $meeting->venue_address ?? $meeting->venue ?? '';
        $this->address = $this->venue_address;
        $this->city = $meeting->city ?? 'Cairo';
        $this->latitude = $meeting->latitude !== null ? (float) $meeting->latitude : null;
        $this->longitude = $meeting->longitude !== null ? (float) $meeting->longitude : null;
        $this->syncCoordinates();
        $this->type = $meeting->type ?? 'in_person';
        $this->language = $meeting->language ?? 'ar';
        $this->notes = $meeting->notes ?? '';
        $this->online_link = $meeting->online_link ?? '';

        $this->resetValidation();
    }

    /**
     * Sync coordinate string with latitude and longitude.
     */
    protected function syncCoordinates(): void
    {
        if ($this->latitude !== null && $this->longitude !== null) {
            $this->coordinates = "{$this->latitude}, {$this->longitude}";
        }
    }

    public function updatedName(string $value): void
    {
        $this->title = $value;
    }

    public function updatedTitle(string $value): void
    {
        $this->name = $value;
    }

    public function updatedDay(mixed $value): void
    {
        $this->day_of_week = is_numeric($value) ? (int) $value : $value;
    }

    public function updatedDayOfWeek(mixed $value): void
    {
        $this->day = is_numeric($value) ? (int) $value : $value;
    }

    public function updatedAddress(string $value): void
    {
        $this->venue_address = $value;
    }

    public function updatedVenueAddress(string $value): void
    {
        $this->address = $value;
    }

    public function updatedLatitude(mixed $value): void
    {
        $this->latitude = is_numeric($value) ? (float) $value : null;
        $this->syncCoordinates();
    }

    public function updatedLongitude(mixed $value): void
    {
        $this->longitude = is_numeric($value) ? (float) $value : null;
        $this->syncCoordinates();
    }

    public function updatedCoordinates(mixed $value): void
    {
        if (is_string($value) && str_contains($value, ',')) {
            [$lat, $lon] = explode(',', $value, 2);
            if (is_numeric(trim($lat)) && is_numeric(trim($lon))) {
                $this->latitude = (float) trim($lat);
                $this->longitude = (float) trim($lon);
            }
        }
    }

    public function updatedSelectedMeetingId(mixed $value): void
    {
        if ($value) {
            $this->selectMeeting((int) $value);
        }
    }

    /**
     * Update coordinates when pin confirmation is triggered.
     */
    public function updateCoordinates(float|string $lat, float|string $lng): void
    {
        $this->latitude = (float) $lat;
        $this->longitude = (float) $lng;
        $this->syncCoordinates();
    }

    /**
     * Alias for updateCoordinates.
     */
    public function confirmPin(float|string $lat, float|string $lng): void
    {
        $this->updateCoordinates($lat, $lng);
    }

    /**
     * Validation rules for the meeting form.
     *
     * @return array<string, array<int, mixed>>
     */
    protected function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'day_of_week' => ['required', 'integer', 'between:0,6'],
            'start_time' => ['required', 'string'],
            'end_time' => ['required', 'string'],
            'address' => ['required', 'string', 'max:255'],
            'latitude' => ['required', 'numeric'],
            'longitude' => ['required', 'numeric'],
            'type' => ['required', 'string', 'in:in_person,online,hybrid'],
            'language' => ['required', 'string', 'in:ar,en,bilingual'],
            'notes' => ['nullable', 'string'],
            'city' => ['nullable', 'string', 'max:255'],
            'online_link' => ['nullable', 'string', 'max:255'],
        ];
    }

    /**
     * Save the meeting changes.
     */
    public function save(): void
    {
        $this->updateMeeting();
    }

    /**
     * Update the meeting changes (alias for save).
     */
    public function update(): void
    {
        $this->updateMeeting();
    }

    /**
     * Perform the actual meeting update with authorization check.
     */
    protected function updateMeeting(): void
    {
        $user = Auth::user();

        if (! $user) {
            abort(401);
        }

        $targetId = $this->meetingId ?? $this->selectedMeetingId;

        if (! $targetId) {
            abort(404, 'No meeting selected.');
        }

        $meeting = Meeting::findOrFail($targetId);

        // Authorization: before updating, assert $meeting->group_id === auth()->user()->group_id
        if ($user->role !== 'web_admin' && (int) $meeting->group_id !== (int) $user->group_id) {
            abort(403, 'Forbidden. You can only edit your assigned group\'s meetings.');
        }

        assert($user->role === 'web_admin' || (int) $meeting->group_id === (int) $user->group_id, 'Unauthorized: meeting does not belong to your group.');

        Gate::authorize('update', $meeting);

        // Pre-normalize synchronized properties before validation
        if (empty($this->name) && ! empty($this->title)) {
            $this->name = $this->title;
        }
        if (empty($this->address) && ! empty($this->venue_address)) {
            $this->address = $this->venue_address;
        }
        if ($this->day_of_week === null && $this->day !== null) {
            $this->day_of_week = is_numeric($this->day) ? (int) $this->day : $this->day;
        }
        if (($this->latitude === null || $this->longitude === null) && ! empty($this->coordinates)) {
            if (str_contains($this->coordinates, ',')) {
                [$lat, $lon] = explode(',', $this->coordinates, 2);
                if (is_numeric(trim($lat)) && is_numeric(trim($lon))) {
                    $this->latitude = (float) trim($lat);
                    $this->longitude = (float) trim($lon);
                }
            }
        }

        $this->validate();

        $before = $meeting->only([
            'name', 'title', 'day_of_week', 'start_time', 'end_time', 'time',
            'venue_address', 'venue', 'city', 'latitude', 'longitude', 'type',
            'online_link', 'language', 'notes',
        ]);

        $meeting->update([
            'name' => $this->name,
            'title' => $this->name,
            'day_of_week' => (int) $this->day_of_week,
            'start_time' => $this->start_time,
            'end_time' => $this->end_time,
            'time' => $this->start_time,
            'venue_address' => $this->address,
            'venue' => $this->address,
            'city' => $this->city ?: ($meeting->city ?: 'Cairo'),
            'latitude' => $this->latitude,
            'longitude' => $this->longitude,
            'type' => $this->type,
            'language' => $this->language,
            'notes' => $this->notes,
            'online_link' => $this->online_link,
        ]);

        AuditLogger::log('update', 'Meeting', $meeting->id, [
            'before' => $before,
            'changes' => $meeting->getChanges(),
        ]);

        $this->loadMeetings();
        $this->fillForm($meeting);

        $flashMessage = 'Meeting updated and live on website.';
        session()->flash('message', $flashMessage);
        session()->flash('success', $flashMessage);
        session()->flash('status', $flashMessage);

        $this->dispatch('meeting-updated', message: $flashMessage);
        $this->dispatch('notify', message: $flashMessage);
        $this->dispatch('flash', message: $flashMessage);
    }

    /**
     * Render the Livewire component.
     */
    public function render(): View
    {
        return view('livewire.service.meeting-editor')
            ->layout('layouts.public', ['title' => 'Meeting Editor - Service']);
    }
}

