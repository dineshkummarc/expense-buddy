<?php

declare(strict_types=1);

namespace App\Enums;

enum AmountGrouping: string
{
    case Western = 'western';
    case Asian = 'asian';
    case None = 'none';

    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        return [
            self::Western->value => '1,234,567.89 — groups of 3',
            self::Asian->value => '12,34,567.89 — Asian (groups of 2)',
            self::None->value => '1234567.89 — no commas',
        ];
    }

    public static function tryFromMixed(mixed $value): self
    {
        if ($value instanceof self) {
            return $value;
        }

        return self::tryFrom((string) ($value ?? '')) ?? self::Western;
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
