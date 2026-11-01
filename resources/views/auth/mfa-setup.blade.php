<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Two-Factor Authentication Setup - {{ config('app.name', 'AA Egypt') }}</title>
</head>
<body>
    <main>
        <h1>Set Up Two-Factor Authentication</h1>
        <p>Two-factor authentication (TOTP) is mandatory for this account. Scan the QR code with your authenticator app (e.g., Google Authenticator, Authy) or enter the manual key below.</p>

        <div id="qr-code">
            {!! $qrCodeSvg !!}
        </div>

        <div id="manual-key">
            <p><strong>Manual Setup Key:</strong> <code>{{ $secret }}</code></p>
        </div>

        <form method="POST" action="{{ route('mfa.setup') }}">
            @csrf

            <div>
                <label for="code">6-digit Authenticator Code</label>
                <input
                    id="code"
                    type="text"
                    name="code"
                    required
                    inputmode="numeric"
                    pattern="[0-9]{6}"
                    maxlength="6"
                    placeholder="123456"
                    autocomplete="one-time-code"
                    autofocus
                >
                @error('code')
                    <p role="alert">{{ $message }}</p>
                @enderror
            </div>

            <div>
                <button type="submit">Confirm & Enable 2FA</button>
            </div>
        </form>
    </main>
</body>
</html>
