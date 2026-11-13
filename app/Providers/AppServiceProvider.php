<?php

namespace App\Providers;

use App\Models\DmsDocument;
use App\Models\Document;
use App\Models\Meeting;
use App\Models\User;
use App\Policies\DocumentPolicy;
use App\Policies\MeetingPolicy;
use App\Services\AuditLogger;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        if (! class_exists('AuditLogger')) {
            class_alias(AuditLogger::class, 'AuditLogger');
        }
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Password::defaults(function () {
            return Password::min(12)->uncompromised();
        });

        Gate::policy(Meeting::class, MeetingPolicy::class);
        Gate::policy(Document::class, DocumentPolicy::class);
        Gate::policy(DmsDocument::class, DocumentPolicy::class);

        Gate::define('use-translation-manager', function (?User $user = null): bool {
            return $user?->role === 'web_admin';
        });
    }
}
