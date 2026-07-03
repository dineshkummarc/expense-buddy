<?php

declare(strict_types=1);

namespace App\DTOs;

final readonly class TransferData
{
    /**
     * @param array<string, mixed> $data
     */
    public function __construct(
        public int $sourceAccountId,
        public int $destinationAccountId,
        public int $paymentMethodId,
        public int $currencyId,
        public string $amount,
        public string $rateAtTransaction,
        public string $transactionDate,
        public ?string $reference = null,
        public ?string $description = null,
        public ?string $attachment = null,
        public string $feeOption = 'no_fees',
        public ?string $destinationAmount = null,
    ) {
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            sourceAccountId: (int) $data['source_account_id'],
            destinationAccountId: (int) $data['destination_account_id'],
            paymentMethodId: (int) $data['payment_method_id'],
            currencyId: (int) $data['currency_id'],
            amount: self::normalizeAmount((string) $data['amount']),
            rateAtTransaction: self::normalizeAmount((string) $data['rate_at_transaction']),
            transactionDate: (string) $data['transaction_date'],
            reference: isset($data['reference']) ? (string) $data['reference'] : null,
            description: isset($data['description']) ? (string) $data['description'] : null,
            attachment: isset($data['attachment']) ? (string) $data['attachment'] : null,
            feeOption: isset($data['fee_option']) ? (string) $data['fee_option'] : 'no_fees',
            destinationAmount: isset($data['destination_amount']) && $data['destination_amount'] !== '' ? self::normalizeAmount((string) $data['destination_amount']) : null,
        );
    }

    private static function normalizeAmount(string $amount): string
    {
        return bcadd($amount, '0', 4);
    }
}
