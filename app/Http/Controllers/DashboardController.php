<?php

namespace App\Http\Controllers;

use App\Models\Group;
use App\Models\Meeting;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    /**
     * Display the authenticated service dashboard.
     */
    public function index(Request $request): View
    {
        /** @var User $user */
        $user = $request->user();

        $group = $user->group;
        $activeServiceTerm = $user->latestServiceTerm;

        $meetingsCount = $user->role === 'web_admin'
            ? Meeting::where('is_active', true)->count()
            : ($group ? $group->meetings()->where('is_active', true)->count() : 0);

        $totalGroupsCount = Group::count();
        $appPasswordsCount = $user->appPasswords()->whereNull('revoked_at')->count();

        return view('dashboard', [
            'user' => $user,
            'group' => $group,
            'activeServiceTerm' => $activeServiceTerm,
            'meetingsCount' => $meetingsCount,
            'totalGroupsCount' => $totalGroupsCount,
            'appPasswordsCount' => $appPasswordsCount,
        ]);
    }
}
