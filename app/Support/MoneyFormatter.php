<?php

declare(strict_types=1);

namespace App\Support;

use App\Enums\AmountGrouping;
use App\Models\Currency;
use App\Models\SystemSetting;

final class MoneyFormatter
{
    private static ?AmountGrouping $grouping = null;

    public static function format(string $amount, ?Currency $currency = null, int $decimals = 2): string
    {
        $normalized = bcadd($amount, '0', 4);
        $formatted = self::number((float) $normalized, $decimals);

        if ($currency === null) {
            return $formatted;
        }

        return $currency->symbol.' '.$formatted.' '.$currency->code;
    }

    public static function compact(string $amount, ?Currency $currency = null): string
    {
        $normalized = bcadd($amount, '0', 2);
        $value = (float) $normalized;

        if ($currency === null) {
            if ($value >= 1000000) {
                return self::number($value / 1000000, 1).'M';
            }

            if ($value >= 1000) {
                return self::number($value / 1000, 1).'K';
            }

            return self::number($value, 2);
        }

        $prefix = $currency->symbol.' ';

        if ($value >= 1000000) {
            return $prefix.self::number($value / 1000000, 1).'M';
        }

        if ($value >= 1000) {
            return $prefix.self::number($value / 1000, 1).'K';
        }

        return self::format($amount, $currency, 2);
    }

    public static function convertToBase(string $amount, string $exchangeRate): string
    {
        if (bccomp($exchangeRate, '0', 4) <= 0) {
            return bcadd($amount, '0', 4);
        }

        return bcdiv($amount, $exchangeRate, 4);
    }

    public static function baseAmountExpression(string $amountColumn = 'amount', string $rateColumn = 'rate_at_transaction'): string
    {
        return "({$amountColumn} / {$rateColumn})";
    }

    public static function baseCurrency(): ?Currency
    {
        try {
            $settings = SystemSetting::query()->with('defaultCurrency')->first();

            if ($settings?->defaultCurrency !== null) {
                return $settings->defaultCurrency;
            }

            return Currency::query()->where('is_default', true)->first()
                ?? Currency::query()->orderBy('id')->first();
        } catch (\Throwable) {
            return null;
        }
    }

    public static function resetGroupingCache(): void
    {
        self::$grouping = null;
    }

    private static function number(float $value, int $decimals): string
    {
        return match (self::grouping()) {
            AmountGrouping::None => number_format($value, $decimals, '.', ''),
            AmountGrouping::Asian => self::asianNumber($value, $decimals),
            AmountGrouping::Western => number_format($value, $decimals, '.', ','),
        };
    }

    private static function asianNumber(float $value, int $decimals): string
    {
        $negative = $value < 0;
        $absolute = abs($value);
        $raw = number_format($absolute, $decimals, '.', '');
        [$integer, $fraction] = array_pad(explode('.', $raw, 2), 2, null);

        if (strlen($integer) > 3) {
            $lastThree = substr($integer, -3);
            $rest = substr($integer, 0, -3);
            $rest = preg_replace('/\B(?=(\d{2})+(?!\d))/', ',', $rest) ?? $rest;
            $integer = $rest.','.$lastThree;
        }

        $formatted = $fraction === null ? $integer : $integer.'.'.$fraction;

        return $negative ? '-'.$formatted : $formatted;
    }

    private static function grouping(): AmountGrouping
    {
        if (self::$grouping !== null) {
            return self::$grouping;
        }

        try {
            $value = SystemSetting::query()->value('amount_grouping');
        } catch (\Throwable) {
            $value = null;
        }

        self::$grouping = AmountGrouping::tryFromMixed($value);

        return self::$grouping;
    }
}
