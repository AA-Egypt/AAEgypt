<?php

namespace App\Http\Middleware;

use App\Models\Document;
use App\Models\Group;
use App\Models\Meeting;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureGroupAccess
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user) {
            abort(403, 'Forbidden');
        }

        // Web Admins have global access (all_groups)
        if ($user->role === 'web_admin') {
            return $next($request);
        }

        // GSRs and Delegates have strictly group-scoped access (own_group). Default is DENY.
        if (! in_array($user->role, ['gsr', 'delegate'], true)) {
            abort(403, 'Forbidden');
        }

        if (empty($user->group_id)) {
            abort(403, 'Forbidden');
        }

        $group = $request->route('group') ?? $request->route('group_id');
        $groupId = null;

        if ($group instanceof Group) {
            $groupId = $group->id;
        } elseif (is_numeric($group)) {
            $groupId = (int) $group;
        } elseif (is_string($group)) {
            $found = Group::where('slug', $group)->first();
            $groupId = $found?->id ?? (is_numeric($group) ? (int) $group : null);
        } else {
            // Fall back to checking meeting or document bound to route if present
            $meeting = $request->route('meeting') ?? $request->route('meeting_id');
            if ($meeting instanceof Meeting) {
                $groupId = $meeting->group_id;
            } elseif (is_numeric($meeting)) {
                $groupId = Meeting::find($meeting)?->group_id;
            } else {
                $document = $request->route('document') ?? $request->route('document_id');
                if ($document instanceof Document) {
                    $groupId = $document->group_id;
                } elseif (is_numeric($document)) {
                    $groupId = Document::find($document)?->group_id;
                }
            }
        }

        if ($groupId === null || (int) $user->group_id !== (int) $groupId) {
            abort(403, 'Forbidden');
        }

        return $next($request);
    }
}
