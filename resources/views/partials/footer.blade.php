<footer class="bg-slate-900 text-slate-300 mt-auto border-t border-slate-800">
    <div class="container mx-auto px-4 sm:px-6 lg:px-8 py-10">
        <div class="grid grid-cols-1 md:grid-cols-2 gap-8 mb-8">
            <!-- AA Declaration Section -->
            <div class="text-start">
                <h3 class="text-emerald-400 font-semibold text-lg mb-3">
                    {{ app()->getLocale() === 'ar' ? 'إعلان المسؤولية' : 'Declaration of Responsibility' }}
                </h3>
                <blockquote class="text-slate-300 text-sm leading-relaxed italic border-s-4 border-emerald-500 ps-4">
                    {{ __('AA Declaration') }}
                </blockquote>
            </div>

            <!-- AA Disclaimer Section -->
            <div class="text-start">
                <h3 class="text-slate-200 font-semibold text-lg mb-3">
                    {{ app()->getLocale() === 'ar' ? 'إخلاء المسؤولية' : 'Disclaimer' }}
                </h3>
                <p class="text-slate-400 text-sm leading-relaxed border-s-4 border-slate-700 ps-4">
                    {{ __('AA Disclaimer') }}
                </p>
            </div>
        </div>

        <div class="border-t border-slate-800 pt-6 flex flex-col sm:flex-row items-center justify-between gap-4 text-xs text-slate-400">
            <div class="text-start">
                &copy; {{ date('Y') }} {{ config('app.name', 'A.A. Egypt') }}. {{ __('All rights reserved.') }}
            </div>
            <div class="flex items-center gap-6">
                <a href="{{ url(app()->getLocale() . '/privacy') }}" class="hover:text-emerald-400 transition underline underline-offset-4">
                    {{ __('Privacy Policy') }}
                </a>
            </div>
        </div>
    </div>
</footer>

