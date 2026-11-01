<?php

namespace App\Policies;

use App\Models\Meeting;
use App\Models\User;

class MeetingPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return in_array($user->role, ['web_admin', 'gsr', 'delegate', 'service_position'], true);
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, Meeting $meeting): bool
    {
        if ($user->role === 'web_admin') {
            return true;
        }

        if (in_array($user->role, ['gsr', 'delegate'], true)) {
            return ! empty($user->group_id) && (int) $user->group_id === (int) $meeting->group_id;
        }

        return false;
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        if ($user->role === 'web_admin') {
            return true;
        }

        if (in_array($user->role, ['gsr', 'delegate'], true)) {
            return ! empty($user->group_id);
        }

        return false;
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, Meeting $meeting): bool
    {
        if ($user->role === 'web_admin') {
            return true;
        }

        if (in_array($user->role, ['gsr', 'delegate'], true)) {
            return ! empty($user->group_id) && (int) $user->group_id === (int) $meeting->group_id;
        }

        return false;
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Meeting $meeting): bool
    {
        return $user->role === 'web_admin';
    }
}
