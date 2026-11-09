<?php

use App\Http\Controllers\Public\MapTileController;
use App\Http\Controllers\Public\PageController;
use App\Http\Controllers\Public\SearchController;
use App\Http\Controllers\Service\AppPasswordController;
use App\Http\Middleware\EnsureGroupAccess;
use App\Http\Middleware\SetLocale;
use App\Models\Document;
use App\Models\Group;
use App\Models\Meeting;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return redirect('/en');
});

Route::redirect('/who-we-are', '/en/who-we-are');
Route::redirect('/privacy', '/en/privacy');
Route::redirect('/meetings', '/en/meetings');
Route::get('/search', function (Request $request) {
    $queryString = $request->getQueryString();

    return redirect('/en/search'.($queryString ? '?'.$queryString : ''));
});

Route::get('/tiles/{z}/{x}/{y}.png', [MapTileController::class, 'show'])->name('tiles.show');
Route::get('/tiles/{z}/{x}/{y}', [MapTileController::class, 'show']);

Route::prefix('{locale}')
    ->whereIn('locale', ['ar', 'en'])
    ->middleware(['web', SetLocale::class])
    ->group(function () {
        Route::get('/', function () {
            return view('public.home');
        })->name('public.home');

        Route::get('/who-we-are', function () {
            return view('public.who-we-are');
        })->name('public.who-we-are');

        Route::get('/privacy', function () {
            return view('public.privacy');
        })->name('public.privacy');

        Route::get('/meetings', function (Request $request) {
            $query = Meeting::with('group')
                ->where('is_active', true);

            if ($request->filled('city')) {
                $query->byCity($request->query('city'));
            }

            $meetings = $query->get();

            return view('public.meetings.index', ['meetings' => $meetings]);
        })->name('public.meetings');

        Route::get('/search', [SearchController::class, 'index'])
            ->middleware('throttle:30,1')
            ->name('public.search');

        Route::get('/{slug}', [PageController::class, 'show'])
            ->where('slug', '[a-zA-Z0-9_-]+')
            ->name('public.page');
    });

Route::get('/dashboard', function () {
    return response('Dashboard');
})->middleware(['auth'])->name('dashboard');

// Protected service and admin routes requiring MFA
Route::middleware(['auth', 'mfa.enrolled'])->group(function () {
    Route::get('/service/app-passwords', [AppPasswordController::class, 'index'])->name('service.app-passwords.index');
    Route::post('/service/app-passwords', [AppPasswordController::class, 'store'])->name('service.app-passwords.store');
    Route::delete('/service/app-passwords/{appPassword}', [AppPasswordController::class, 'destroy'])->name('service.app-passwords.destroy');

    Route::get('/app-passwords', [AppPasswordController::class, 'index'])->name('app-passwords.index');
    Route::post('/app-passwords', [AppPasswordController::class, 'store'])->name('app-passwords.store');
    Route::delete('/app-passwords/{appPassword}', [AppPasswordController::class, 'destroy'])->name('app-passwords.destroy');

    Route::get('/service', function () {
        return response('Service Dashboard');
    })->name('service.index');

    require __DIR__.'/service.php';

    Route::get('/service/{any}', function () {
        return response('Service Area');
    })->where('any', '.*');
});

// Group-scoped access protected routes
Route::middleware(['auth', EnsureGroupAccess::class])->group(function () {
    Route::get('/groups/{group}', function (Group $group) {
        return response()->json(['group' => $group]);
    })->name('groups.show');

    Route::get('/groups/{group}/meetings', function (Group $group) {
        return response()->json(['meetings' => $group->meetings]);
    })->name('groups.meetings.index');

    Route::match(['put', 'patch'], '/groups/{group}/meetings/{meeting}', function (Request $request, Group $group, Meeting $meeting) {
        Gate::authorize('update', $meeting);

        $validated = $request->validate([
            'title' => 'sometimes|string',
            'day_of_week' => 'sometimes|string',
            'time' => 'sometimes|string',
        ]);

        $before = $meeting->only(array_keys($validated));
        $meeting->update($validated);

        AuditLogger::log('update', 'Meeting', $meeting->id, [
            'before' => $before,
            'changes' => $meeting->getChanges(),
        ]);

        return response()->json([
            'message' => 'Meeting updated successfully',
            'meeting' => $meeting,
        ]);
    })->name('groups.meetings.update');

    Route::get('/groups/{group}/documents', function (Group $group) {
        return response()->json(['documents' => $group->documents]);
    })->name('groups.documents.index');
});

// Meeting and Document direct routes
Route::middleware(['auth'])->group(function () {
    Route::match(['put', 'patch'], '/meetings/{meeting}', function (Request $request, Meeting $meeting) {
        Gate::authorize('update', $meeting);

        $validated = $request->validate([
            'title' => 'sometimes|string',
            'day_of_week' => 'sometimes|string',
            'time' => 'sometimes|string',
        ]);

        $before = $meeting->only(array_keys($validated));
        $meeting->update($validated);

        AuditLogger::log('update', 'Meeting', $meeting->id, [
            'before' => $before,
            'changes' => $meeting->getChanges(),
        ]);

        return response()->json([
            'message' => 'Meeting updated successfully',
            'meeting' => $meeting,
        ]);
    })->name('meetings.update');

    Route::delete('/meetings/{meeting}', function (Meeting $meeting) {
        Gate::authorize('delete', $meeting);

        $id = $meeting->id;
        $meeting->delete();

        AuditLogger::log('delete', 'Meeting', $id, ['deleted' => true]);

        return response()->json(['message' => 'Meeting deleted successfully']);
    })->name('meetings.destroy');

    Route::match(['put', 'patch'], '/documents/{document}', function (Request $request, Document $document) {
        Gate::authorize('update', $document);

        $validated = $request->validate([
            'title' => 'sometimes|string',
        ]);

        $before = $document->only(array_keys($validated));
        $document->update($validated);

        AuditLogger::log('update', 'Document', $document->id, [
            'before' => $before,
            'changes' => $document->getChanges(),
        ]);

        return response()->json([
            'message' => 'Document updated successfully',
            'document' => $document,
        ]);
    })->name('documents.update');

    // Admin access matrix management
    Route::match(['post', 'put'], '/admin/access-matrix', function (Request $request) {
        if ($request->user()?->role !== 'web_admin') {
            abort(403, 'Forbidden');
        }

        $validated = $request->validate([
            'user_id' => 'required|exists:users,id',
            'role' => 'required|in:web_admin,gsr,delegate,service_position',
            'group_id' => 'nullable|exists:groups,id',
        ]);

        $targetUser = User::findOrFail($validated['user_id']);
        $oldData = ['role' => $targetUser->role, 'group_id' => $targetUser->group_id];

        $targetUser->rotateRole($validated['role'], $validated['group_id'] ?? null);

        $log = AuditLogger::log('update_access_matrix', 'User', $targetUser->id, [
            'before' => $oldData,
            'after' => ['role' => $targetUser->role, 'group_id' => $targetUser->group_id],
        ]);

        return response()->json([
            'message' => 'Access matrix updated successfully',
            'user' => $targetUser,
            'audit_log' => $log,
        ]);
    })->name('admin.access-matrix.update');
});

require __DIR__.'/auth.php';
