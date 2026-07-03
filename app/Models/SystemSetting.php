<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SystemSetting extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'system_name',
        'system_logo',
        'default_currency_id',
        'timezone',
        'allow_negative_balances',
        'mail_driver',
        'mail_host',
        'mail_port',
        'mail_username',
        'mail_password',
        'mail_encryption',
        'mail_from_address',
        'mail_from_name',
        'backup_enabled',
        'backup_frequency',
        'backup_day',
        'backup_email',
        'backup_last_run_at',
        'backup_last_success_at',
        'error_tracking_enabled',
        'transfer_fees_enabled',
    ];

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'allow_negative_balances' => 'boolean',
        'mail_port' => 'integer',
        'backup_enabled' => 'boolean',
        'backup_day' => 'integer',
        'backup_last_run_at' => 'datetime',
        'backup_last_success_at' => 'datetime',
        'error_tracking_enabled' => 'boolean',
        'transfer_fees_enabled' => 'boolean',
    ];

    public function defaultCurrency(): BelongsTo
    {
        return $this->belongsTo(Currency::class, 'default_currency_id');
    }
}
