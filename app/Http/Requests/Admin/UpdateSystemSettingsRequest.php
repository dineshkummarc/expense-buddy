<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class UpdateSystemSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermission('menu.admin.settings') ?? false;
    }

    protected function prepareForValidation(): void
    {
        if ($this->input('settings_section') === 'general') {
            $this->merge([
                'allow_negative_balances' => $this->boolean('allow_negative_balances'),
                'error_tracking_enabled' => $this->boolean('error_tracking_enabled'),
                'transfer_fees_enabled' => $this->boolean('transfer_fees_enabled'),
            ]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        if ($this->input('settings_section') === 'email') {
            return [
                'settings_section' => ['required', 'in:email'],
                'mail_driver' => ['required', 'in:smtp,sendmail'],
                'mail_host' => ['nullable', 'string', 'max:255'],
                'mail_port' => ['nullable', 'integer', 'min:1', 'max:65535'],
                'mail_username' => ['nullable', 'string', 'max:255'],
                'mail_password' => ['nullable', 'string', 'max:500'],
                'mail_encryption' => ['nullable', 'in:tls,ssl,none'],
                'mail_from_address' => ['nullable', 'email', 'max:255'],
                'mail_from_name' => ['nullable', 'string', 'max:120'],
            ];
        }

        return [
            'settings_section' => ['required', 'in:general'],
            'system_name' => ['required', 'string', 'max:120'],
            'default_currency_id' => ['required', 'integer', 'exists:currencies,id'],
            'timezone' => ['required', 'string', 'timezone:all'],
            'allow_negative_balances' => ['sometimes', 'boolean'],
            'error_tracking_enabled' => ['sometimes', 'boolean'],
            'transfer_fees_enabled' => ['sometimes', 'boolean'],
            'system_logo' => ['nullable', 'image', 'max:2048'],
        ];
    }
}
