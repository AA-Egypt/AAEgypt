<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable([
    'name',
    'email',
    'password',
    'role',
    'group_id',
    'must_change_password',
    'is_suspended',
    'totp_secret',
    'first_login_at',
    'password_changed_at',
])]
#[Hidden(['password', 'remember_token', 'totp_secret'])]
class User extends Authenticatable implements FilamentUser
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
        'group_id',
        'must_change_password',
        'is_suspended',
        'totp_secret',
        'first_login_at',
        'password_changed_at',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'must_change_password' => 'boolean',
            'is_suspended' => 'boolean',
            'first_login_at' => 'datetime',
            'password_changed_at' => 'datetime',
        ];
    }

    public function group(): BelongsTo
    {
        return $this->belongsTo(Group::class);
    }

    public function serviceTerms(): HasMany
    {
        return $this->hasMany(ServiceTerm::class);
    }

    public function serviceTerm(): HasOne
    {
        return $this->hasOne(ServiceTerm::class)->latestOfMany();
    }

    public function latestServiceTerm(): HasOne
    {
        return $this->hasOne(ServiceTerm::class)->latestOfMany();
    }

    public function getTermStartDateAttribute(): mixed
    {
        return $this->latestServiceTerm?->start_date;
    }

    public function getTermEndDateAttribute(): mixed
    {
        return $this->latestServiceTerm?->end_date;
    }

    public function appPasswords(): HasMany
    {
        return $this->hasMany(AppPassword::class);
    }

    /**
     * Rotate the user's role and revoke all existing app passwords.
     */
    public function rotateRole(?string $newRole = null, ?int $groupId = null): self
    {
        if ($newRole !== null) {
            $this->role = $newRole;
        }

        if ($groupId !== null || func_num_args() >= 2) {
            $this->group_id = $groupId;
        }

        if ($this->isDirty()) {
            $this->save();
        }

        $this->appPasswords()->update(['revoked_at' => now()]);

        return $this;
    }

    /**
     * Authenticate an app password for this user.
     */
    public function authenticateAppPassword(string $plainToken): ?AppPassword
    {
        return AppPassword::authenticateForUser($this, $plainToken);
    }

    /**
     * Determine whether the user can access the Filament panel.
     */
    public function canAccessPanel(Panel $panel): bool
    {
        return $this->role === 'web_admin';
    }

    public function isWebAdmin(): bool
    {
        return $this->role === 'web_admin';
    }

    public function isTreasurer(): bool
    {
        if ($this->role === 'treasurer' || $this->role === 'web_admin') {
            return true;
        }

        return $this->serviceTerms()
            ->where(function ($q) {
                $q->where('position_title', 'like', '%treasurer%')
                    ->orWhere('position_title', 'like', '%خازن%')
                    ->orWhere('position_title', 'like', '%أم%صندوق%');
            })
            ->exists();
    }

    public function dmsDocuments(): HasMany
    {
        return $this->hasMany(DmsDocument::class);
    }
}
