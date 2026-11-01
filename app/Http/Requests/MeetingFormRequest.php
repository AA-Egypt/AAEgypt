<?php

namespace App\Http\Requests;

use Carbon\Carbon;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;

class MeetingFormRequest extends FormRequest
{
    public const EGYPT_LAT_MIN = 21.5;

    public const EGYPT_LAT_MAX = 31.8;

    public const EGYPT_LON_MIN = 24.5;

    public const EGYPT_LON_MAX = 37.0;

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'group_id' => ['required', 'integer', 'exists:groups,id'],
            'name' => ['required', 'string', 'max:255'],
            'day_of_week' => ['required', 'integer', 'between:0,6'],
            'start_time' => ['required', 'date_format:H:i'],
            'end_time' => [
                'required',
                'date_format:H:i',
                'after:start_time',
                fn (string $attribute, mixed $value, Closure $fail) => $this->validateTimeSequencing($attribute, $value, $fail),
            ],
            'venue_address' => ['required', 'string', 'max:255'],
            'city' => ['required', 'string', 'max:255'],
            'latitude' => [
                'required',
                'numeric',
                'between:'.self::EGYPT_LAT_MIN.','.self::EGYPT_LAT_MAX,
                fn (string $attribute, mixed $value, Closure $fail) => $this->validateEgyptLatitude($attribute, $value, $fail),
            ],
            'longitude' => [
                'required',
                'numeric',
                'between:'.self::EGYPT_LON_MIN.','.self::EGYPT_LON_MAX,
                fn (string $attribute, mixed $value, Closure $fail) => $this->validateEgyptLongitude($attribute, $value, $fail),
            ],
            'type' => ['required', 'string', 'in:in_person,online,hybrid'],
            'online_link' => ['nullable', 'string', 'url', 'max:255'],
            'language' => ['required', 'string', 'in:ar,en,bilingual'],
            'notes' => ['nullable', 'string'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * Custom validation rule for Egypt latitude bounds (21.5 to 31.8).
     */
    public function validateEgyptLatitude(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_numeric($value) || (float) $value < self::EGYPT_LAT_MIN || (float) $value > self::EGYPT_LAT_MAX) {
            $fail('The latitude must be within Egypt bounding box (21.5 to 31.8).');
        }
    }

    /**
     * Custom validation rule for Egypt longitude bounds (24.5 to 37.0).
     */
    public function validateEgyptLongitude(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_numeric($value) || (float) $value < self::EGYPT_LON_MIN || (float) $value > self::EGYPT_LON_MAX) {
            $fail('The longitude must be within Egypt bounding box (24.5 to 37.0).');
        }
    }

    /**
     * Custom validation rule ensuring end_time is strictly after start_time in Africa/Cairo.
     */
    public function validateTimeSequencing(string $attribute, mixed $value, Closure $fail): void
    {
        $startTime = $this->input('start_time');
        if (! $startTime || ! $value) {
            return;
        }

        try {
            $start = Carbon::createFromFormat('H:i', $startTime, 'Africa/Cairo');
            $end = Carbon::createFromFormat('H:i', $value, 'Africa/Cairo');

            if ($end->lte($start)) {
                $fail('The end time must be after the start time.');
            }
        } catch (\Throwable) {
            // Handled by date_format validation rule
        }
    }

    /**
     * Configure the validator instance with custom cross-field assertions.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $data = $validator->getData();

            $lat = $data['latitude'] ?? $this->input('latitude');
            if (is_numeric($lat) && ((float) $lat < self::EGYPT_LAT_MIN || (float) $lat > self::EGYPT_LAT_MAX)) {
                $validator->errors()->add('latitude', 'Coordinates must be within Egypt bounding box (Latitude 21.5 to 31.8).');
            }

            $lon = $data['longitude'] ?? $this->input('longitude');
            if (is_numeric($lon) && ((float) $lon < self::EGYPT_LON_MIN || (float) $lon > self::EGYPT_LON_MAX)) {
                $validator->errors()->add('longitude', 'Coordinates must be within Egypt bounding box (Longitude 24.5 to 37.0).');
            }

            $start = $data['start_time'] ?? $this->input('start_time');
            $end = $data['end_time'] ?? $this->input('end_time');
            if ($start && $end) {
                try {
                    $startTime = Carbon::createFromFormat('H:i', $start, 'Africa/Cairo');
                    $endTime = Carbon::createFromFormat('H:i', $end, 'Africa/Cairo');
                    if ($endTime->lte($startTime)) {
                        $validator->errors()->add('end_time', 'End time must be after start time.');
                    }
                } catch (\Throwable) {
                    // Handled by date_format validation rule
                }
            }
        });
    }
}
