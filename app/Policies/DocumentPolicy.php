<?php

namespace App\Policies;

use App\Models\Document;
use App\Models\User;

class DocumentPolicy
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
    public function view(User $user, Document $document): bool
    {
        if ($user->role === 'web_admin') {
            return true;
        }

        if (in_array($user->role, ['gsr', 'delegate'], true)) {
            return ! empty($user->group_id) && (int) $user->group_id === (int) $document->group_id;
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
    public function update(User $user, Document $document): bool
    {
        if ($user->role === 'web_admin') {
            return true;
        }

        if (in_array($user->role, ['gsr', 'delegate'], true)) {
            return ! empty($user->group_id) && (int) $user->group_id === (int) $document->group_id;
        }

        return false;
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Document $document): bool
    {
        return $user->role === 'web_admin';
    }
}
