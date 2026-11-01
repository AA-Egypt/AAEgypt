<?php

namespace App\Models;

use App\Services\AuditLogger;
use Carbon\Carbon;
use Database\Factories\ServiceTermFactory;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Auth;
use InvalidArgumentException;

#[Fillable([
    'user_id',
    'position_title',
    'start_date',
    'end_date',
    'extended_times',
    'last_extended_by',
])]
class ServiceTerm extends Model
{
    /** @use HasFactory<ServiceTermFactory> */
    use HasFactory;

    /**
     * Standard allowed term durations in months.
     */
    public const STANDARD_TERMS = [3, 6, 9, 12];

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'position_title',
        'start_date',
        'end_date',
        'extended_times',
        'last_extended_by',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'end_date' => 'date',
            'extended_times' => 'integer',
        ];
    }

    /**
     * The user (role owner) holding this service position.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * The administrator or role owner who last extended this position.
     */
    public function lastExtendedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'last_extended_by');
    }

    /**
     * Accessor alias for term_start_date.
     */
    public function getTermStartDateAttribute(): mixed
    {
        return $this->start_date;
    }

    /**
     * Accessor alias for term_end_date.
     */
    public function getTermEndDateAttribute(): mixed
    {
        return $this->end_date;
    }

    /**
     * Determine if a user can extend this service position term.
     */
    public function canBeExtendedBy(?User $user): bool
    {
        if (! $user) {
            return false;
        }

        return $user->id === $this->user_id || $user->role === 'web_admin';
    }

    /**
     * Determine if the term is already expired.
     */
    public function isExpired(): bool
    {
        if (! $this->end_date) {
            return false;
        }

        return Carbon::parse($this->end_date)->isPast() || Carbon::parse($this->end_date)->isToday();
    }

    /**
     * Extend the position by up to 12 months.
     *
     * @throws InvalidArgumentException
     * @throws AuthorizationException
     */
    public function extend(int $months, User|int|null $extendedBy = null): self
    {
        if ($months < 1 || $months > 12) {
            throw new InvalidArgumentException('Position can only be extended by between 1 and 12 months.');
        }

        $actor = null;
        if ($extendedBy instanceof User) {
            $actor = $extendedBy;
        } elseif (is_numeric($extendedBy)) {
            $actor = User::find($extendedBy);
        } else {
            $actor = Auth::user();
        }

        if ($actor && ! $this->canBeExtendedBy($actor)) {
            throw new AuthorizationException('Only the role owner or a Web Admin can extend this position.');
        }

        $previousEndDate = Carbon::parse($this->end_date);
        $newEndDate = (clone $previousEndDate)->addMonths($months);

        $this->end_date = $newEndDate->toDateString();
        $this->extended_times = ($this->extended_times ?? 0) + 1;
        $this->last_extended_by = $actor?->id ?? (is_numeric($extendedBy) ? (int) $extendedBy : null);
        $this->save();

        AuditLogger::log(
            action: 'extend',
            resource: 'ServiceTerm',
            resourceId: $this->id,
            payload: [
                'months' => $months,
                'previous_end_date' => $previousEndDate->toDateString(),
                'new_end_date' => $this->end_date instanceof Carbon ? $this->end_date->toDateString() : (string) $this->end_date,
                'extended_times' => $this->extended_times,
                'last_extended_by' => $this->last_extended_by,
            ],
            userId: $actor?->id,
        );

        return $this;
    }
}
