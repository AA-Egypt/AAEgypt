<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Set Personal Password - {{ config('app.name', 'AA Egypt') }}</title>
</head>
<body>
    <main>
        <h1>Set Personal Password</h1>
        <p>This service account was initialized with a temporary password. You must set a personal password of at least 12 characters to continue.</p>

        <form method="POST" action="{{ route('first-login.password') }}">
            @csrf

            <div>
                <label for="password">New Password</label>
                <input
                    id="password"
                    type="password"
                    name="password"
                    required
                    minlength="12"
                    autocomplete="new-password"
                    autofocus
                >
                @error('password')
                    <p role="alert">{{ $message }}</p>
                @enderror
            </div>

            <div>
                <label for="password_confirmation">Confirm New Password</label>
                <input
                    id="password_confirmation"
                    type="password"
                    name="password_confirmation"
                    required
                    minlength="12"
                    autocomplete="new-password"
                >
            </div>

            <div>
                <button type="submit">Update Password & Continue</button>
            </div>
        </form>
    </main>
</body>
</html>
