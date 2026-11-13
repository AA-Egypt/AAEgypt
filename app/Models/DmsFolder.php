<?php

namespace App\Models;

use Database\Factories\DmsFolderFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DmsFolder extends Model
{
    /** @use HasFactory<DmsFolderFactory> */
    use HasFactory;

    protected $fillable = [
        'parent_id',
        'name_ar',
        'name_en',
        'required_role',
        'group_id',
    ];

    public function parent(): BelongsTo
    {
        return $this->belongsTo(DmsFolder::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(DmsFolder::class, 'parent_id');
    }

    public function documents(): HasMany
    {
        return $this->hasMany(DmsDocument::class, 'folder_id');
    }

    public function group(): BelongsTo
    {
        return $this->belongsTo(Group::class, 'group_id');
    }

    /**
     * Determine whether the given user can access this folder for the specified action.
     *
     * @param  string  $action  'view' | 'upload' | 'download' | 'delete'
     */
    public function canUserAccess(?User $user, string $action = 'view'): bool
    {
        if (! $user) {
            return false;
        }

        // Web Admin has full system-wide access
        if ($user->role === 'web_admin') {
            return true;
        }

        // Only web_admin can delete folders
        if ($action === 'delete') {
            return false;
        }

        // Check required role on this folder (e.g. 'treasurer')
        if ($this->required_role !== null && $this->required_role !== '') {
            $allowedRoles = array_map('trim', explode(',', strtolower($this->required_role)));
            $userRole = strtolower($user->role);

            $hasRole = in_array($userRole, $allowedRoles, true);

            if (! $hasRole && in_array('treasurer', $allowedRoles, true)) {
                if (method_exists($user, 'isTreasurer') && $user->isTreasurer()) {
                    $hasRole = true;
                }
            }

            if (! $hasRole) {
                return false;
            }
        }

        // Check group restriction
        if ($this->group_id !== null) {
            if ($user->group_id === null || (int) $user->group_id !== (int) $this->group_id) {
                return false;
            }
        }

        // Check parent folder recursively
        if ($this->parent_id) {
            $parent = $this->parent ?? DmsFolder::find($this->parent_id);
            if ($parent && ! $parent->canUserAccess($user, $action)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Scope query to folders directly accessible by the given user.
     */
    public function scopeAccessibleBy(Builder $query, User $user, string $action = 'view'): Builder
    {
        if ($user->role === 'web_admin') {
            return $query;
        }

        $query->where(function (Builder $q) use ($user) {
            // Group condition: either folder is not group-scoped, or matches user's group
            $q->whereNull('group_id');
            if ($user->group_id) {
                $q->orWhere('group_id', $user->group_id);
            }
        });

        // Role condition
        $isTreasurer = method_exists($user, 'isTreasurer') ? $user->isTreasurer() : ($user->role === 'treasurer');
        if (! $isTreasurer) {
            $query->where(function (Builder $q) {
                $q->whereNull('required_role')
                    ->orWhere('required_role', '!=', 'treasurer');
            });
        }

        return $query;
    }

    /**
     * Seed default root folders if they do not exist.
     */
    public static function seedDefaultFolders(): void
    {
        $defaults = [
            ['name_en' => 'Meeting Minutes', 'name_ar' => 'محاضر الاجتماعات', 'required_role' => null, 'group_id' => null],
            ['name_en' => 'Charters & Guidelines', 'name_ar' => 'اللوائح والإرشادات', 'required_role' => null, 'group_id' => null],
            ['name_en' => 'Treasury Reports', 'name_ar' => 'تقارير الخزانة', 'required_role' => 'treasurer', 'group_id' => null],
            ['name_en' => 'Literature Inventory', 'name_ar' => 'مخزون المطبوعات', 'required_role' => null, 'group_id' => null],
            ['name_en' => 'Group Folders', 'name_ar' => 'ملفات المجموعات', 'required_role' => null, 'group_id' => null],
            ['name_en' => 'Announcements', 'name_ar' => 'الإعلانات', 'required_role' => null, 'group_id' => null],
        ];

        foreach ($defaults as $folder) {
            static::firstOrCreate(
                ['name_en' => $folder['name_en'], 'parent_id' => null],
                $folder
            );
        }
    }
}
