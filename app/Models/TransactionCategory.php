<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TransactionCategory extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'type',
        'status',
        'is_system',
        'key',
    ];

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'is_system' => 'boolean',
    ];

    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class, 'category_id');
    }

    /**
     * @param \Illuminate\Database\Eloquent\Builder<self> $query
     */
    public function scopeActive($query)
    {
        $query->where('status', 'active');

        try {
            $settings = \App\Models\SystemSetting::first();
            if ($settings && !$settings->transfer_fees_enabled) {
                $query->where(function ($q) {
                    $q->whereNull('key')
                      ->orWhere('key', '!=', \App\Enums\SystemCategoryKey::TRANSFER_FEES->value);
                });
            }
        } catch (\Throwable $e) {
            // Safe fallback
        }

        return $query;
    }
}
