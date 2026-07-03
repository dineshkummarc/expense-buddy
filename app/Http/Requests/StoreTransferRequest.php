<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreTransferRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'source_account_id' => ['required', 'integer', 'exists:accounts,id', 'different:destination_account_id'],
            'destination_account_id' => ['required', 'integer', 'exists:accounts,id'],
            'payment_method_id' => ['required', 'integer', 'exists:payment_methods,id'],
            'currency_id' => ['required', 'integer', 'exists:currencies,id'],
            'amount' => ['required', 'numeric', 'gt:0', 'regex:/^\d+(\.\d{1,4})?$/'],
            'rate_at_transaction' => ['required', 'numeric', 'gt:0', 'regex:/^\d+(\.\d{1,4})?$/'],
            'transaction_date' => ['required', 'date'],
            'reference' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'attachment' => ['nullable', 'string', 'max:255'],
            'fee_option' => ['required', 'string', 'in:no_fees,charge_fee'],
            'destination_amount' => [
                'required_if:fee_option,charge_fee',
                'nullable',
                'numeric',
                'gt:0',
                'regex:/^\d+(\.\d{1,4})?$/',
            ],
        ];
    }
}
