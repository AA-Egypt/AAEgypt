<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>App Passwords - {{ config('app.name', 'AA Egypt') }}</title>
    <style>
        body {
            font-family: system-ui, -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
            background-color: #f8fafc;
            color: #1e293b;
            margin: 0;
            padding: 2rem 1rem;
        }
        .container {
            max-width: 900px;
            margin: 0 auto;
            background: #ffffff;
            border-radius: 8px;
            padding: 2rem;
            box-shadow: 0 4px 6px -1px rgb(0 0 0 / 0.1), 0 2px 4px -2px rgb(0 0 0 / 0.1);
        }
        h1 { margin-top: 0; color: #0f172a; }
        .description { color: #64748b; margin-bottom: 2rem; font-size: 0.95rem; line-height: 1.5; }
        .alert {
            padding: 1rem 1.25rem;
            border-radius: 6px;
            margin-bottom: 1.5rem;
        }
        .alert-success {
            background-color: #ecfdf5;
            border: 1px solid #a7f3d0;
            color: #065f46;
        }
        .alert-warning {
            background-color: #fffbeb;
            border: 1px solid #fde68a;
            color: #92400e;
        }
        .token-display {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            margin: 1rem 0;
        }
        .token-input {
            font-family: monospace;
            font-size: 1.15rem;
            letter-spacing: 0.05em;
            padding: 0.5rem 0.75rem;
            background-color: #ffffff;
            border: 2px solid #10b981;
            border-radius: 6px;
            width: 100%;
            max-width: 380px;
            font-weight: 600;
            color: #047857;
        }
        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 0.5rem 1rem;
            font-size: 0.9rem;
            font-weight: 500;
            border-radius: 6px;
            border: 1px solid transparent;
            cursor: pointer;
            text-decoration: none;
            transition: background-color 0.15s ease-in-out;
        }
        .btn-primary { background-color: #2563eb; color: #ffffff; }
        .btn-primary:hover { background-color: #1d4ed8; }
        .btn-copy { background-color: #10b981; color: #ffffff; }
        .btn-copy:hover { background-color: #059669; }
        .btn-danger { background-color: #ef4444; color: #ffffff; }
        .btn-danger:hover { background-color: #dc2626; }
        .form-section {
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 6px;
            padding: 1.5rem;
            margin-bottom: 2rem;
        }
        .form-group { margin-bottom: 1rem; }
        .form-label { display: block; font-weight: 500; margin-bottom: 0.35rem; font-size: 0.9rem; }
        .form-input {
            width: 100%;
            max-width: 400px;
            padding: 0.5rem 0.75rem;
            border: 1px solid #cbd5e1;
            border-radius: 6px;
            font-size: 0.95rem;
            box-sizing: border-box;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 1rem;
            font-size: 0.9rem;
        }
        th, td {
            text-align: left;
            padding: 0.75rem 1rem;
            border-bottom: 1px solid #e2e8f0;
        }
        th {
            background-color: #f8fafc;
            font-weight: 600;
            color: #475569;
        }
        .badge {
            display: inline-block;
            padding: 0.25rem 0.5rem;
            font-size: 0.75rem;
            font-weight: 600;
            border-radius: 9999px;
        }
        .badge-active { background-color: #dcfce7; color: #166534; }
        .badge-revoked { background-color: #fee2e2; color: #991b1b; }
        .empty-state {
            padding: 2rem;
            text-align: center;
            color: #64748b;
        }
        .nav-link {
            display: inline-block;
            margin-bottom: 1rem;
            color: #2563eb;
            text-decoration: none;
            font-size: 0.9rem;
        }
        .nav-link:hover { text-decoration: underline; }
    </style>
</head>
<body>
    <div class="container">
        <a href="{{ route('dashboard') }}" class="nav-link">&larr; Back to Dashboard</a>

        <h1>App Passwords</h1>
        <p class="description">
            Generate and manage application passwords for use with third-party mail clients (Outlook, Thunderbird, Apple Mail) connecting via IMAPS / SMTP without using your personal password or MFA tokens.
        </p>

        @if (session('status'))
            <div class="alert alert-success" role="alert">
                {{ session('status') }}
            </div>
        @endif

        @if (session('raw_token'))
            <div class="alert alert-warning" id="raw-token-banner" role="alert">
                <h3 style="margin: 0 0 0.5rem 0; font-size: 1.05rem;">
                    New App Password Generated{{ session('new_app_password_name') ? ' for "' . session('new_app_password_name') . '"' : '' }}
                </h3>
                <p style="margin: 0 0 0.5rem 0; font-size: 0.9rem;">
                    <strong>Important:</strong> This password is shown <strong>ONLY ONCE</strong> and cannot be retrieved again. Please copy and paste it into your mail client now.
                </p>

                <div class="token-display">
                    <input
                        type="text"
                        id="raw-app-password"
                        class="token-input"
                        value="{{ session('raw_token') }}"
                        readonly
                        aria-label="Generated App Password"
                    >
                    <button type="button" class="btn btn-copy" id="copy-btn" onclick="copyAppPassword()">
                        Copy Password
                    </button>
                </div>
            </div>
        @endif

        <div class="form-section">
            <h2 style="font-size: 1.15rem; margin-top: 0; margin-bottom: 1rem;">Create New App Password</h2>
            <form method="POST" action="{{ route('service.app-passwords.store') }}">
                @csrf

                <div class="form-group">
                    <label for="name" class="form-label">Client or Device Name</label>
                    <input
                        type="text"
                        id="name"
                        name="name"
                        class="form-input"
                        placeholder="e.g. Outlook on Work Laptop, Thunderbird, iPhone"
                        value="{{ old('name') }}"
                        required
                    >
                    @error('name')
                        <p style="color: #dc2626; font-size: 0.85rem; margin-top: 0.25rem;">{{ $message }}</p>
                    @enderror
                </div>

                @if ($isAdmin)
                    <div class="form-group">
                        <label for="user_id" class="form-label">User ID (Optional, Web Admin override)</label>
                        <input
                            type="number"
                            id="user_id"
                            name="user_id"
                            class="form-input"
                            placeholder="Leave empty for yourself"
                            value="{{ old('user_id') }}"
                        >
                        @error('user_id')
                            <p style="color: #dc2626; font-size: 0.85rem; margin-top: 0.25rem;">{{ $message }}</p>
                        @enderror
                    </div>
                @endif

                <button type="submit" class="btn btn-primary" id="generate-btn">
                    Generate App Password
                </button>
            </form>
        </div>

        <h2 style="font-size: 1.15rem; margin-top: 2rem; margin-bottom: 0.5rem;">Existing App Passwords</h2>

        @if ($appPasswords->isEmpty())
            <div class="empty-state">
                <p>No app passwords created yet.</p>
            </div>
        @else
            <table>
                <thead>
                    <tr>
                        <th>Name</th>
                        @if ($isAdmin)
                            <th>User</th>
                        @endif
                        <th>Created</th>
                        <th>Last Used</th>
                        <th>Status</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($appPasswords as $password)
                        <tr>
                            <td>
                                <strong>{{ $password->name }}</strong>
                            </td>
                            @if ($isAdmin)
                                <td>{{ $password->user?->name ?? 'User #' . $password->user_id }} ({{ $password->user?->email }})</td>
                            @endif
                            <td>{{ $password->created_at?->format('M d, Y H:i') ?? 'N/A' }}</td>
                            <td>{{ $password->last_used_at ? $password->last_used_at->diffForHumans() : 'Never' }}</td>
                            <td>
                                @if ($password->isRevoked())
                                    <span class="badge badge-revoked">Revoked</span>
                                @else
                                    <span class="badge badge-active">Active</span>
                                @endif
                            </td>
                            <td>
                                @if (! $password->isRevoked())
                                    <form method="POST" action="{{ route('service.app-passwords.destroy', $password) }}" onsubmit="return confirm('Are you sure you want to revoke this app password? IMAPS/SMTP clients using it will lose access.');">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn btn-danger" style="padding: 0.25rem 0.5rem; font-size: 0.8rem;">
                                            Revoke
                                        </button>
                                    </form>
                                @else
                                    <span style="color: #94a3b8; font-size: 0.8rem;">Revoked</span>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </div>

    <script>
        function copyAppPassword() {
            const input = document.getElementById('raw-app-password');
            if (!input) return;

            input.select();
            input.setSelectionRange(0, 99999);

            if (navigator.clipboard) {
                navigator.clipboard.writeText(input.value).then(function() {
                    const btn = document.getElementById('copy-btn');
                    if (btn) {
                        btn.innerText = 'Copied!';
                        setTimeout(function() {
                            btn.innerText = 'Copy Password';
                        }, 2500);
                    }
                });
            } else {
                document.execCommand('copy');
                const btn = document.getElementById('copy-btn');
                if (btn) {
                    btn.innerText = 'Copied!';
                    setTimeout(function() {
                        btn.innerText = 'Copy Password';
                    }, 2500);
                }
            }
        }
    </script>
</body>
</html>

