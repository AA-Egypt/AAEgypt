<?php

namespace App\Models;

use Database\Factories\AppPasswordFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Hash;

#[Fillable([
    'user_id',
    'name',
    'password_hash',
    'last_used_at',
    'revoked_at',
])]
#[Hidden(['password_hash'])]
class AppPassword extends Model
{
    /** @use HasFactory<AppPasswordFactory> */
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'name',
        'password_hash',
        'last_used_at',
        'revoked_at',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password_hash',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'last_used_at' => 'datetime',
            'revoked_at' => 'datetime',
        ];
    }

    /**
     * The user that owns the app password.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Check if the app password is revoked.
     */
    public function isRevoked(): bool
    {
        return $this->revoked_at !== null;
    }

    /**
     * Check if the app password is active and valid.
     */
    public function isValid(): bool
    {
        return $this->revoked_at === null;
    }

    /**
     * Verify whether a raw token matches this app password without updating last_used_at.
     */
    public function verify(string $plainToken): bool
    {
        if ($this->isRevoked()) {
            return false;
        }

        return Hash::check($plainToken, $this->password_hash);
    }

    /**
     * Authenticate this app password with a raw token.
     * Marks last_used_at on successful match.
     */
    public function authenticate(string $plainToken): bool
    {
        if ($this->isRevoked()) {
            return false;
        }

        if (Hash::check($plainToken, $this->password_hash)) {
            $this->update(['last_used_at' => now()]);

            return true;
        }

        return false;
    }

    /**
     * Authenticate a user with a raw token against active app passwords.
     */
    public static function authenticateForUser(User|int|string $userOrEmail, string $plainToken): ?self
    {
        $query = static::query()->whereNull('revoked_at');

        if ($userOrEmail instanceof User) {
            $query->where('user_id', $userOrEmail->id);
        } elseif (is_numeric($userOrEmail)) {
            $query->where('user_id', (int) $userOrEmail);
        } else {
            $user = User::where('email', $userOrEmail)->first();
            if (! $user) {
                return null;
            }
            $query->where('user_id', $user->id);
        }

        $tokens = $query->get();

        foreach ($tokens as $token) {
            if (Hash::check($plainToken, $token->password_hash)) {
                $token->update(['last_used_at' => now()]);

                return $token;
            }
        }

        return null;
    }
}
