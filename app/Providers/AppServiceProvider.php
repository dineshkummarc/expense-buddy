<?php

declare(strict_types=1);

namespace App\Providers;

use App\Models\SystemSetting;
use App\Services\SystemSettingService;
use App\Support\MoneyFormatter;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
		Schema::defaultStringLength(191);
        Paginator::defaultView('vendor.pagination.tailwind');

        $settings = null;
        $baseCurrency = null;

        try {
            if (Schema::hasTable('system_settings')) {
                $settings = SystemSetting::query()->with('defaultCurrency')->first();

                if ($settings !== null) {
                    app(SystemSettingService::class)->applyRuntimeConfig($settings);
                }
            }

            $baseCurrency = $settings?->defaultCurrency ?? MoneyFormatter::baseCurrency();
        } catch (\Throwable) {
            $settings = null;
            $baseCurrency = null;
        }

        View::share([
            'systemSettings' => $settings,
            'baseCurrency' => $baseCurrency,
        ]);
    }
}
