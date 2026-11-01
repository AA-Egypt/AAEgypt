<?php

namespace App\Http\Controllers\Service;

use App\Http\Controllers\Controller;
use App\Models\AppPassword;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\View\View;

class AppPasswordController extends Controller
{
    /**
     * Display a listing of app passwords.
     */
    public function index(Request $request): View|JsonResponse
    {
        $user = $request->user();

        if ($user->role === 'web_admin') {
            $query = AppPassword::with('user')->latest();
            if ($request->filled('user_id')) {
                $query->where('user_id', $request->integer('user_id'));
            }
            $appPasswords = $query->get();
        } else {
            $appPasswords = $user->appPasswords()->latest()->get();
        }

        if ($request->wantsJson()) {
            return response()->json([
                'app_passwords' => $appPasswords,
            ]);
        }

        return view('service.app-passwords.index', [
            'appPasswords' => $appPasswords,
            'isAdmin' => $user->role === 'web_admin',
        ]);
    }

    /**
     * Store a newly created app password in storage.
     */
    public function store(Request $request): RedirectResponse|JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'user_id' => ['nullable', 'exists:users,id'],
        ]);

        $userId = $request->user()->id;
        if ($request->user()->role === 'web_admin' && ! empty($validated['user_id'])) {
            $userId = (int) $validated['user_id'];
        }

        $rawToken = Str::random(24);
        $passwordHash = Hash::make($rawToken);

        $appPassword = AppPassword::create([
            'user_id' => $userId,
            'name' => $validated['name'],
            'password_hash' => $passwordHash,
        ]);

        if ($request->wantsJson()) {
            return response()->json([
                'message' => 'App password created successfully',
                'app_password' => $appPassword,
                'raw_token' => $rawToken,
            ], 201);
        }

        return redirect()->route('service.app-passwords.index')
            ->with('raw_token', $rawToken)
            ->with('new_app_password_name', $appPassword->name)
            ->with('status', 'App password generated successfully.');
    }

    /**
     * Revoke the specified app password.
     */
    public function destroy(Request $request, AppPassword $appPassword): RedirectResponse|JsonResponse
    {
        $user = $request->user();

        if ($user->id !== $appPassword->user_id && $user->role !== 'web_admin') {
            abort(403, 'Unauthorized.');
        }

        $appPassword->update([
            'revoked_at' => now(),
        ]);

        if ($request->wantsJson()) {
            return response()->json([
                'message' => 'App password revoked successfully',
                'app_password' => $appPassword,
            ]);
        }

        return redirect()->route('service.app-passwords.index')
            ->with('status', 'App password revoked successfully.');
    }
}
