@extends('layouts.app')

@section('title', 'System Settings')
@section('heading', 'Administration')
@section('subheading', 'Branding, base currency, and ledger policies')

@section('content')
    @include('admin.partials.nav')

    <div class="grid gap-6 lg:grid-cols-3">
        <x-panel class="lg:col-span-2" title="General Settings" subtitle="Visible branding and ledger rules">
            <form method="POST" action="{{ route('admin.settings.update') }}" enctype="multipart/form-data" class="space-y-4">
                @csrf
                @method('PUT')
                <input type="hidden" name="settings_section" value="general">

                <div>
                    <label class="mb-1.5 block text-sm font-medium">System Name</label>
                    <input type="text" name="system_name" value="{{ old('system_name', $settings->system_name) }}" class="input" required>
                </div>

                <div>
                    <label class="mb-1.5 block text-sm font-medium">System Logo</label>
                    <p class="mb-2 text-xs text-slate-500">Used in the sidebar, browser tab favicon, and mobile PWA install icon.</p>
                    @if (\App\Support\Brand::hasLogo($settings))
                        <div class="mb-3 flex items-center gap-3">
                            <img src="{{ \App\Support\Brand::customLogoUrl($settings) }}" alt="Logo" class="h-12 w-12 rounded-xl border object-contain p-1 dark:border-slate-700">
                            <span class="text-xs text-slate-500">Upload a new file to replace</span>
                        </div>
                    @else
                        <div class="mb-3 flex items-center gap-3">
                            <x-brand-logo size="sm" />
                            <span class="text-xs text-slate-500">Using the default wallet icon until you upload a logo</span>
                        </div>
                    @endif
                    <input type="file" name="system_logo" accept="image/*" class="input">
                </div>

                <div>
                    <label class="mb-1.5 block text-sm font-medium">Default Base Currency</label>
                    <select name="default_currency_id" class="input" data-search-select data-placeholder="Base currency" data-search-placeholder="Search currencies…" required>
                        @foreach ($currencies as $currency)
                            <option value="{{ $currency->id }}" @selected((int) old('default_currency_id', $settings->default_currency_id) === $currency->id)>
                                {{ $currency->name }} ({{ $currency->code }})
                            </option>
                        @endforeach
                    </select>
                    <p class="mt-1 text-xs text-slate-500">Dashboard totals and reports convert other currencies to this base. Account balances stay in their native currency.</p>
                </div>

                <div>
                    <label class="mb-1.5 block text-sm font-medium">Application Timezone</label>
                    <select name="timezone" class="input" data-search-select data-placeholder="Select timezone" data-search-placeholder="Search timezones…" required>
                        @foreach ($timezones as $identifier => $label)
                            <option value="{{ $identifier }}" @selected(old('timezone', $settings->timezone ?? config('app.timezone')) === $identifier)>
                                {{ $label }}
                            </option>
                        @endforeach
                    </select>
                    <p class="mt-1 text-xs text-slate-500">Dates, reports, backups, and scheduled tasks use this timezone.</p>
                </div>

                <div>
                    <label class="mb-1.5 block text-sm font-medium">Amount Number Format</label>
                    <select name="amount_grouping" class="input" data-search-select data-placeholder="Number format" data-search-placeholder="Search formats…" required>
                        @foreach (\App\Enums\AmountGrouping::options() as $value => $label)
                            <option value="{{ $value }}" @selected(old('amount_grouping', $settings->amount_grouping?->value ?? \App\Enums\AmountGrouping::Western->value) === $value)>
                                {{ $label }}
                            </option>
                        @endforeach
                    </select>
                    <p class="mt-1 text-xs text-slate-500">Applies to amounts shown across the app.</p>
                </div>

                <label class="flex items-center gap-2 text-sm">
                    <input type="checkbox" name="allow_negative_balances" value="1" @checked(old('allow_negative_balances', $settings->allow_negative_balances)) class="rounded border-slate-300 text-brand-600">
                    Allow negative account balances
                </label>

                <label class="flex items-center gap-2 text-sm">
                    <input type="checkbox" name="transfer_fees_enabled" value="1" @checked(old('transfer_fees_enabled', $settings->transfer_fees_enabled)) class="rounded border-slate-300 text-brand-600">
                    Enable transfer fees / loss management
                </label>

                <label class="flex items-center gap-2 text-sm">
                    <input type="checkbox" name="error_tracking_enabled" value="1" @checked(old('error_tracking_enabled', $settings->error_tracking_enabled)) class="rounded border-slate-300 text-brand-600">
                    Enable error route tracking
                </label>
                <p class="text-xs text-slate-500">When enabled, an <strong>Error Insights</strong> tab appears here with paginated failed-route reports.</p>

                <button type="submit" class="btn-primary">Save Settings</button>
            </form>
        </x-panel>

        <x-panel title="Preview">
            <div class="flex items-center gap-3 rounded-xl border border-slate-200 p-4 dark:border-slate-800">
                <x-brand-logo size="lg" />
                <div>
                    <div class="text-lg font-bold">{{ $settings->system_name }}</div>
                    <div class="text-sm text-slate-500">Base: {{ $settings->defaultCurrency?->code ?? '—' }}</div>
                    <div class="text-sm text-slate-500">Timezone: {{ $settings->timezone ?? config('app.timezone') }}</div>
                    <div class="text-xs text-slate-400">{{ now()->format('M j, Y g:i A T') }}</div>
                </div>
            </div>
        </x-panel>
    </div>

    <x-panel class="mt-6" title="Email Configuration" subtitle="SMTP or PHP mail for password resets and notifications">
        <form method="POST" action="{{ route('admin.settings.update') }}" class="space-y-4">
            @csrf
            @method('PUT')
            <input type="hidden" name="settings_section" value="email">

            <div>
                <label class="mb-1.5 block text-sm font-medium">Mail Driver</label>
                <select name="mail_driver" id="mail_driver" class="input" data-mail-driver>
                    <option value="smtp" @selected(old('mail_driver', $settings->mail_driver ?? 'smtp') === 'smtp')>SMTP</option>
                    <option value="sendmail" @selected(old('mail_driver', $settings->mail_driver ?? 'smtp') === 'sendmail')>PHP Mail (sendmail)</option>
                </select>
                <p class="mt-1 text-xs text-slate-500">Use SMTP for Gmail, Outlook, or your hosting provider. PHP Mail uses the server’s built-in mail function.</p>
            </div>

            <div id="smtp-fields" class="grid gap-4 sm:grid-cols-2">
                <div class="sm:col-span-2">
                    <label class="mb-1.5 block text-sm font-medium">SMTP Host</label>
                    <input type="text" name="mail_host" value="{{ old('mail_host', $settings->mail_host) }}" class="input" placeholder="smtp.mailtrap.io">
                </div>
                <div>
                    <label class="mb-1.5 block text-sm font-medium">SMTP Port</label>
                    <input type="number" name="mail_port" value="{{ old('mail_port', $settings->mail_port ?? 587) }}" class="input" min="1" max="65535">
                </div>
                <div>
                    <label class="mb-1.5 block text-sm font-medium">Encryption</label>
                    <select name="mail_encryption" class="input">
                        <option value="tls" @selected(old('mail_encryption', $settings->mail_encryption ?? 'tls') === 'tls')>TLS</option>
                        <option value="ssl" @selected(old('mail_encryption', $settings->mail_encryption) === 'ssl')>SSL</option>
                        <option value="none" @selected(old('mail_encryption', $settings->mail_encryption) === null || old('mail_encryption', $settings->mail_encryption) === 'none')>None</option>
                    </select>
                </div>
                <div>
                    <label class="mb-1.5 block text-sm font-medium">Username</label>
                    <input type="text" name="mail_username" value="{{ old('mail_username', $settings->mail_username) }}" class="input" autocomplete="off">
                </div>
                <div>
                    <label class="mb-1.5 block text-sm font-medium">Password</label>
                    <input type="password" name="mail_password" class="input" placeholder="{{ $settings->mail_password ? '•••••••• (leave blank to keep)' : '' }}" autocomplete="new-password">
                </div>
            </div>

            <div class="grid gap-4 sm:grid-cols-2">
                <div>
                    <label class="mb-1.5 block text-sm font-medium">From Email</label>
                    <input type="email" name="mail_from_address" value="{{ old('mail_from_address', $settings->mail_from_address) }}" class="input" placeholder="noreply@example.com">
                </div>
                <div>
                    <label class="mb-1.5 block text-sm font-medium">From Name</label>
                    <input type="text" name="mail_from_name" value="{{ old('mail_from_name', $settings->mail_from_name ?? $settings->system_name) }}" class="input">
                </div>
            </div>

            <button type="submit" class="btn-primary">Save Email Settings</button>
        </form>
    </x-panel>

    @push('scripts')
        <script>
            document.addEventListener('DOMContentLoaded', () => {
                const driver = document.querySelector('[data-mail-driver]');
                const smtpFields = document.getElementById('smtp-fields');

                const toggleSmtp = () => {
                    if (!driver || !smtpFields) return;
                    smtpFields.classList.toggle('hidden', driver.value !== 'smtp');
                };

                driver?.addEventListener('change', toggleSmtp);
                toggleSmtp();
            });
        </script>
    @endpush
@endsection
