@php
    use App\Support\MenuPermissionRegistry;

    $user = auth()->user();
    $adminItems = array_values(array_filter(
        MenuPermissionRegistry::items(),
        fn (array $item): bool => $item['group'] === 'Administration' && ($user?->hasPermission($item['slug']) ?? false),
    ));

    $showErrorInsights = ($systemSettings?->error_tracking_enabled ?? false)
        && ($user?->hasPermission('menu.admin.settings') ?? false);
@endphp

@if ($adminItems !== [] || auth()->check())
    <div class="mb-6 flex flex-wrap gap-2 border-b border-slate-200 pb-4 dark:border-slate-800">
        <a href="{{ route('account.security') }}"
           class="inline-flex items-center gap-2 rounded-xl px-4 py-2 text-sm font-medium transition {{ request()->routeIs('account.*') ? 'bg-brand-50 text-brand-700 dark:bg-brand-900/30 dark:text-brand-300' : 'text-slate-600 hover:bg-slate-100 dark:text-slate-400 dark:hover:bg-slate-800' }}">
            <x-ming-icon name="user.user-security" class="h-4 w-4" />
            Security
        </a>

        @foreach ($adminItems as $tab)
            <a href="{{ route($tab['route']) }}"
               class="inline-flex items-center gap-2 rounded-xl px-4 py-2 text-sm font-medium transition {{ request()->routeIs($tab['active']) ? 'bg-brand-50 text-brand-700 dark:bg-brand-900/30 dark:text-brand-300' : 'text-slate-600 hover:bg-slate-100 dark:text-slate-400 dark:hover:bg-slate-800' }}">
                <x-ming-icon :name="$tab['icon']" class="h-4 w-4" />
                {{ $tab['name'] }}
            </a>
        @endforeach

        @if ($showErrorInsights)
            <a href="{{ route('admin.error-insights') }}"
               class="inline-flex items-center gap-2 rounded-xl px-4 py-2 text-sm font-medium transition {{ request()->routeIs('admin.error-insights*') ? 'bg-brand-50 text-brand-700 dark:bg-brand-900/30 dark:text-brand-300' : 'text-slate-600 hover:bg-slate-100 dark:text-slate-400 dark:hover:bg-slate-800' }}">
                <x-ming-icon name="system.warning" class="h-4 w-4" />
                Error Insights
            </a>
        @endif

        @if ($user?->hasPermission('menu.admin.settings'))
            <a href="{{ route('admin.updater') }}"
               class="inline-flex items-center gap-2 rounded-xl px-4 py-2 text-sm font-medium transition {{ request()->routeIs('admin.updater*') ? 'bg-brand-50 text-brand-700 dark:bg-brand-900/30 dark:text-brand-300' : 'text-slate-600 hover:bg-slate-100 dark:text-slate-400 dark:hover:bg-slate-800' }}">
                <x-ming-icon name="system.refresh-2" class="h-4 w-4" />
                Updater
            </a>
        @endif
    </div>
@endif
