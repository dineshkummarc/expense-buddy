<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\AmountGrouping;
use App\Models\Currency;
use App\Models\SystemSetting;
use App\Support\Brand;
use App\Support\MoneyFormatter;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;
use RuntimeException;

class SystemSettingService
{
    public function __construct(
        private readonly MailConfigService $mailConfigService,
    ) {
    }

    public function get(): SystemSetting
    {
        $settings = SystemSetting::query()->with('defaultCurrency')->first();

        if ($settings === null) {
            $defaultCurrency = Currency::query()->where('is_default', true)->first()
                ?? Currency::query()->orderBy('id')->first();

            $settings = SystemSetting::query()->create([
                'system_name' => Brand::name(),
                'default_currency_id' => $defaultCurrency?->id,
                'timezone' => (string) config('app.timezone', 'UTC'),
                'allow_negative_balances' => (bool) config('ledger.allow_negative_balances', false),
            ]);
        }

        return $settings->loadMissing('defaultCurrency');
    }

    /**
     * @param array<string, mixed> $data
     */
    public function update(array $data, ?UploadedFile $logo = null): SystemSetting
    {
        $settings = $this->get();

        if ($logo !== null) {
            if ($settings->system_logo !== null) {
                Storage::disk('public')->delete($settings->system_logo);
            }

            $settings->system_logo = $logo->store('branding', 'public');
        }

        if (isset($data['system_name'])) {
            $settings->system_name = (string) $data['system_name'];
        }

        if (array_key_exists('allow_negative_balances', $data)) {
            $settings->allow_negative_balances = (bool) $data['allow_negative_balances'];
        }

        if (! empty($data['default_currency_id'])) {
            $this->setDefaultCurrency($settings, (int) $data['default_currency_id']);
        }

        if (isset($data['timezone'])) {
            $settings->timezone = (string) $data['timezone'];
        }

        if (isset($data['amount_grouping'])) {
            $settings->amount_grouping = AmountGrouping::tryFromMixed($data['amount_grouping']);
        }

        if (isset($data['mail_driver'])) {
            $settings->mail_driver = (string) $data['mail_driver'];
        }

        if (array_key_exists('mail_host', $data)) {
            $settings->mail_host = $data['mail_host'] !== null && $data['mail_host'] !== ''
                ? (string) $data['mail_host']
                : null;
        }

        if (array_key_exists('mail_port', $data)) {
            $settings->mail_port = $data['mail_port'] !== null && $data['mail_port'] !== ''
                ? (int) $data['mail_port']
                : null;
        }

        if (array_key_exists('mail_username', $data)) {
            $settings->mail_username = $data['mail_username'] !== null && $data['mail_username'] !== ''
                ? (string) $data['mail_username']
                : null;
        }

        if (! empty($data['mail_password'])) {
            $settings->mail_password = $this->mailConfigService->encryptPassword((string) $data['mail_password']);
        }

        if (array_key_exists('mail_encryption', $data)) {
            $encryption = $data['mail_encryption'];
            $settings->mail_encryption = $encryption !== null && $encryption !== '' && $encryption !== 'none'
                ? (string) $encryption
                : null;
        }

        if (array_key_exists('mail_from_address', $data)) {
            $settings->mail_from_address = $data['mail_from_address'] !== null && $data['mail_from_address'] !== ''
                ? (string) $data['mail_from_address']
                : null;
        }

        if (array_key_exists('mail_from_name', $data)) {
            $settings->mail_from_name = $data['mail_from_name'] !== null && $data['mail_from_name'] !== ''
                ? (string) $data['mail_from_name']
                : null;
        }

        if (array_key_exists('backup_enabled', $data)) {
            $settings->backup_enabled = (bool) $data['backup_enabled'];
        }

        if (isset($data['backup_frequency'])) {
            $settings->backup_frequency = (string) $data['backup_frequency'];
        }

        if (array_key_exists('backup_day', $data)) {
            $settings->backup_day = (int) $data['backup_day'];
        }

        if (array_key_exists('backup_email', $data)) {
            $settings->backup_email = $data['backup_email'] !== null && $data['backup_email'] !== ''
                ? (string) $data['backup_email']
                : null;
        }

        if (array_key_exists('error_tracking_enabled', $data)) {
            $settings->error_tracking_enabled = (bool) $data['error_tracking_enabled'];
        }

        if (array_key_exists('transfer_fees_enabled', $data)) {
            $settings->transfer_fees_enabled = (bool) $data['transfer_fees_enabled'];
        }

        $settings->save();

        MoneyFormatter::resetGroupingCache();
        $this->applyRuntimeConfig($settings);

        return $settings->fresh(['defaultCurrency']);
    }

    public function applyRuntimeConfig(SystemSetting $settings): void
    {
        config(['ledger.allow_negative_balances' => $settings->allow_negative_balances]);

        $timezone = $settings->timezone ?: (string) config('app.timezone', 'UTC');

        if (in_array($timezone, timezone_identifiers_list(), true)) {
            config(['app.timezone' => $timezone]);
            date_default_timezone_set($timezone);
        }

        $this->mailConfigService->applyFromSettings($settings);
    }

    private function setDefaultCurrency(SystemSetting $settings, int $currencyId): void
    {
        $currency = Currency::query()->find($currencyId);

        if ($currency === null) {
            throw new InvalidArgumentException("Currency [{$currencyId}] not found.");
        }

        DB::transaction(function () use ($settings, $currency): void {
            Currency::query()->update(['is_default' => false]);
            $currency->update(['is_default' => true, 'exchange_rate' => '1.0000']);
            $settings->default_currency_id = $currency->id;
        });
    }
}
