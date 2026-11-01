<?php

namespace App\Models;

use App\Services\Search\ArabicNormalizer;
use Carbon\CarbonInterface;
use Database\Factories\MeetingFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

class Meeting extends Model
{
    /** @use HasFactory<MeetingFactory> */
    use HasFactory;

    public const TIMEZONE = 'Africa/Cairo';

    protected $fillable = [
        'group_id',
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
        'online_link',
        'language',
        'notes',
        'search_vector',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'latitude' => 'float',
        'longitude' => 'float',
    ];

    protected static function booted(): void
    {
        static::saving(function (Meeting $meeting) {
            if (empty($meeting->attributes['name']) && ! empty($meeting->attributes['title'])) {
                $meeting->attributes['name'] = $meeting->attributes['title'];
            } elseif (empty($meeting->attributes['title']) && ! empty($meeting->attributes['name'])) {
                $meeting->attributes['title'] = $meeting->attributes['name'];
            }

            if (empty($meeting->attributes['venue_address']) && ! empty($meeting->attributes['venue'])) {
                $meeting->attributes['venue_address'] = $meeting->attributes['venue'];
            } elseif (empty($meeting->attributes['venue']) && ! empty($meeting->attributes['venue_address'])) {
                $meeting->attributes['venue'] = $meeting->attributes['venue_address'];
            }

            if (empty($meeting->attributes['start_time']) && ! empty($meeting->attributes['time'])) {
                $meeting->attributes['start_time'] = $meeting->attributes['time'];
            } elseif (empty($meeting->attributes['time']) && ! empty($meeting->attributes['start_time'])) {
                $meeting->attributes['time'] = $meeting->attributes['start_time'];
            }

            $groupName = '';
            if ($meeting->relationLoaded('group')) {
                $groupName = ($meeting->group?->name_en ?? '').' '.($meeting->group?->name_ar ?? '');
            } elseif ($meeting->group_id) {
                $group = Group::find($meeting->group_id);
                if ($group) {
                    $groupName = ($group->name_en ?? '').' '.($group->name_ar ?? '');
                }
            }

            $text = implode(' ', array_filter([
                $meeting->name,
                $meeting->title,
                $groupName,
                $meeting->venue_address,
                $meeting->venue,
                $meeting->city,
                $meeting->notes,
            ]));

            $meeting->search_vector = ArabicNormalizer::normalize($text);
        });
    }

    public function group(): BelongsTo
    {
        return $this->belongsTo(Group::class);
    }

    public function getNameAttribute($value): ?string
    {
        return $value ?? $this->attributes['title'] ?? null;
    }

    public function getTitleAttribute($value): ?string
    {
        return $value ?? $this->attributes['name'] ?? null;
    }

    public function getVenueAddressAttribute($value): ?string
    {
        return $value ?? $this->attributes['venue'] ?? null;
    }

    public function getVenueAttribute($value): ?string
    {
        return $value ?? $this->attributes['venue_address'] ?? null;
    }

    public function getDayOfWeekAttribute($value): mixed
    {
        if (is_null($value)) {
            return null;
        }

        return is_numeric($value) ? (int) $value : $value;
    }

    /**
     * Get the formatted start time in Africa/Cairo timezone (e.g. "7:00 PM").
     */
    public function getStartTimeFormattedAttribute(): ?string
    {
        $time = $this->start_time ?: $this->time;
        if (empty($time)) {
            return null;
        }

        try {
            return Carbon::createFromFormat('H:i', $time, self::TIMEZONE)->format('g:i A');
        } catch (\Throwable) {
            try {
                return Carbon::parse($time, self::TIMEZONE)->format('g:i A');
            } catch (\Throwable) {
                return $time;
            }
        }
    }

    /**
     * Get the formatted end time in Africa/Cairo timezone (e.g. "8:30 PM").
     */
    public function getEndTimeFormattedAttribute(): ?string
    {
        if (empty($this->end_time)) {
            return null;
        }

        try {
            return Carbon::createFromFormat('H:i', $this->end_time, self::TIMEZONE)->format('g:i A');
        } catch (\Throwable) {
            try {
                return Carbon::parse($this->end_time, self::TIMEZONE)->format('g:i A');
            } catch (\Throwable) {
                return $this->end_time;
            }
        }
    }

    /**
     * Check if the meeting occurs today strictly in Africa/Cairo timezone.
     */
    public function getIsTodayAttribute(): bool
    {
        if (is_null($this->day_of_week)) {
            return false;
        }

        $nowCairo = Carbon::now(self::TIMEZONE);
        $todayDayOfWeek = $nowCairo->dayOfWeek; // 0 (Sunday) to 6 (Saturday)

        if (is_numeric($this->day_of_week)) {
            return (int) $this->day_of_week === $todayDayOfWeek;
        }

        return strcasecmp((string) $this->day_of_week, $nowCairo->format('l')) === 0;
    }

    /**
     * Check if the meeting is currently live now in Africa/Cairo timezone.
     */
    public function getIsLiveNowAttribute(): bool
    {
        if (! ($this->is_active ?? true) || ! $this->is_today) {
            return false;
        }

        $startTime = $this->start_time ?: $this->time;
        $endTime = $this->end_time;

        if (empty($startTime) || empty($endTime)) {
            return false;
        }

        try {
            $nowCairo = Carbon::now(self::TIMEZONE);

            $start = Carbon::createFromFormat('H:i', $startTime, self::TIMEZONE)
                ->setDate($nowCairo->year, $nowCairo->month, $nowCairo->day);
            $end = Carbon::createFromFormat('H:i', $endTime, self::TIMEZONE)
                ->setDate($nowCairo->year, $nowCairo->month, $nowCairo->day);

            return $nowCairo->betweenIncluded($start, $end);
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * Get the timezone used for meeting calculations.
     */
    public static function getTimezone(): string
    {
        return self::TIMEZONE;
    }

    /**
     * Get the current offset in hours for Africa/Cairo for a given date or right now.
     */
    public function getCairoOffsetHours(CarbonInterface|\DateTimeInterface|null $date = null): int
    {
        $target = $date ? Carbon::parse($date)->setTimezone(self::TIMEZONE) : Carbon::now(self::TIMEZONE);

        return (int) $target->offsetHours;
    }

    /**
     * Determine if Africa/Cairo is currently observing Daylight Saving Time.
     */
    public function isCairoDaylightSaving(CarbonInterface|\DateTimeInterface|null $date = null): bool
    {
        $target = $date ? Carbon::parse($date)->setTimezone(self::TIMEZONE) : Carbon::now(self::TIMEZONE);

        return $target->isDST();
    }

    /**
     * Scope a query to only include active meetings.
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope a query to only include meetings scheduled for today in Africa/Cairo.
     */
    public function scopeToday(Builder $query): Builder
    {
        $nowCairo = Carbon::now(self::TIMEZONE);
        $todayDayOfWeek = $nowCairo->dayOfWeek; // 0 (Sunday) to 6 (Saturday)
        $todayDayName = $nowCairo->format('l');

        return $query->where(function (Builder $q) use ($todayDayOfWeek, $todayDayName) {
            $q->where('day_of_week', $todayDayOfWeek)
                ->orWhere('day_of_week', (string) $todayDayOfWeek)
                ->orWhere('day_of_week', $todayDayName);
        });
    }

    /**
     * Scope a query to only include meetings in a specific city.
     */
    public function scopeByCity(Builder $query, string $city): Builder
    {
        return $query->where('city', $city);
    }
}
