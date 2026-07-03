<?php

declare(strict_types=1);

namespace App\Services;

use App\DTOs\TransactionData;
use App\DTOs\TransferData;
use App\Enums\SystemCategoryKey;
use App\Exceptions\InsufficientBalanceException;
use App\Models\Currency;
use App\Models\Transaction;
use App\Models\TransactionCategory;
use App\Repositories\Contracts\AccountRepositoryInterface;
use App\Repositories\Contracts\ContactRepositoryInterface;
use App\Repositories\Contracts\TransactionRepositoryInterface;
use App\Support\MoneyFormatter;
use App\Support\TransactionType;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use RuntimeException;

class TransactionService
{
    public function __construct(
        private readonly AccountRepositoryInterface $accountRepository,
        private readonly ContactRepositoryInterface $contactRepository,
        private readonly TransactionRepositoryInterface $transactionRepository,
    ) {
    }

    public function createIncome(TransactionData $dto): Transaction
    {
        if ($dto->type !== 'income') {
            throw new InvalidArgumentException('Transaction type must be income.');
        }

        return DB::transaction(function () use ($dto): Transaction {
            $this->assertPositiveAmount($dto->amount);
            $this->assertAccountExists($dto->accountId);

            $transaction = $this->transactionRepository->create($dto->toAttributes());

            $this->accountRepository->updateBalance($dto->accountId, $dto->amount, 'increment');

            return $this->transactionRepository->findWithRelations($transaction->id)
                ?? $transaction;
        });
    }

    public function createExpense(TransactionData $dto): Transaction
    {
        if ($dto->type !== 'expense') {
            throw new InvalidArgumentException('Transaction type must be expense.');
        }

        return DB::transaction(function () use ($dto): Transaction {
            $this->assertPositiveAmount($dto->amount);
            $this->assertSufficientBalance($dto->accountId, $dto->amount);

            $transaction = $this->transactionRepository->create($dto->toAttributes());

            $this->accountRepository->updateBalance($dto->accountId, $dto->amount, 'decrement');

            return $this->transactionRepository->findWithRelations($transaction->id)
                ?? $transaction;
        });
    }

    public function createLending(TransactionData $dto): Transaction
    {
        if (! TransactionType::isLending($dto->type)) {
            throw new InvalidArgumentException('Transaction type must be a lending type.');
        }

        if ($dto->contactId === null) {
            throw new InvalidArgumentException('Lending transactions require a linked person or company.');
        }

        return DB::transaction(function () use ($dto): Transaction {
            $this->assertPositiveAmount($dto->amount);
            $this->assertAccountExists($dto->accountId);

            if (in_array($dto->type, [TransactionType::LENDING_OUT, TransactionType::LENDING_REPAY_OUT], true)) {
                $this->assertSufficientBalance($dto->accountId, $dto->amount);
            }

            $attributes = $dto->toAttributes();
            $attributes['category_id'] = null;

            $transaction = $this->transactionRepository->create($attributes);

            match ($dto->type) {
                TransactionType::LENDING_OUT, TransactionType::LENDING_REPAY_OUT => $this->accountRepository->updateBalance($dto->accountId, $dto->amount, 'decrement'),
                TransactionType::LENDING_IN, TransactionType::LENDING_REPAY_IN => $this->accountRepository->updateBalance($dto->accountId, $dto->amount, 'increment'),
                default => throw new InvalidArgumentException("Unsupported lending type: {$dto->type}"),
            };

            match ($dto->type) {
                TransactionType::LENDING_OUT, TransactionType::LENDING_REPAY_OUT => $this->contactRepository->updateBalance($dto->contactId, $dto->amount, 'increment'),
                TransactionType::LENDING_IN, TransactionType::LENDING_REPAY_IN => $this->contactRepository->updateBalance($dto->contactId, $dto->amount, 'decrement'),
                default => throw new InvalidArgumentException("Unsupported lending type: {$dto->type}"),
            };

            return $this->transactionRepository->findWithRelations($transaction->id)
                ?? $transaction;
        });
    }

    /**
     * @return array{expense: Transaction, income: Transaction}
     */
    public function createTransfer(TransferData $dto): array
    {
        if ($dto->sourceAccountId === $dto->destinationAccountId) {
            throw new InvalidArgumentException('Source and destination accounts must differ.');
        }

        return DB::transaction(function () use ($dto): array {
            $this->assertPositiveAmount($dto->amount);
            $this->assertAccountExists($dto->sourceAccountId);
            $this->assertAccountExists($dto->destinationAccountId);

            $sourceAccount = $this->accountRepository->find($dto->sourceAccountId);
            $destAccount = $this->accountRepository->find($dto->destinationAccountId);

            if ($sourceAccount === null || $destAccount === null) {
                throw new InvalidArgumentException('Source or destination account not found.');
            }

            $feeAmount = '0.0000';
            $netAmount = $dto->amount;

            if ($dto->feeOption === 'charge_fee' && $dto->destinationAmount !== null) {
                $sourceCurrencyId = $sourceAccount->currency_id;
                $destCurrencyId = $destAccount->currency_id;

                if ($sourceCurrencyId === $destCurrencyId) {
                    $feeAmount = bcsub($dto->amount, $dto->destinationAmount, 4);
                } else {
                    $destCurrency = Currency::find($destCurrencyId);
                    if ($destCurrency === null) {
                        throw new InvalidArgumentException('Destination account currency not found.');
                    }
                    $destRate = (string) $destCurrency->exchange_rate;

                    $baseOfEntered = bcdiv($dto->destinationAmount, $destRate, 4);
                    $transferEquivalentOfEntered = bcmul($baseOfEntered, $dto->rateAtTransaction, 4);
                    $feeAmount = bcsub($dto->amount, $transferEquivalentOfEntered, 4);
                }

                if (bccomp($feeAmount, '0', 4) < 0) {
                    throw new InvalidArgumentException('Destination amount cannot exceed the expected converted transfer amount.');
                }

                $netAmount = bcsub($dto->amount, $feeAmount, 4);
            }

            // Assert sufficient balance for total deduction ($dto->amount) on the source account
            $this->assertSufficientBalance($dto->sourceAccountId, $dto->amount);

            $expenseTransaction = $this->transactionRepository->create([
                'account_id' => $dto->sourceAccountId,
                'type' => 'transfer',
                'category_id' => null,
                'payment_method_id' => $dto->paymentMethodId,
                'currency_id' => $dto->currencyId,
                'amount' => $netAmount,
                'rate_at_transaction' => $dto->rateAtTransaction,
                'contact_id' => null,
                'transaction_date' => $dto->transactionDate,
                'reference' => $dto->reference,
                'description' => $dto->description,
                'attachment' => $dto->attachment,
            ]);

            $incomeTransaction = $this->transactionRepository->create([
                'account_id' => $dto->destinationAccountId,
                'type' => 'transfer',
                'category_id' => null,
                'payment_method_id' => $dto->paymentMethodId,
                'currency_id' => $dto->currencyId,
                'amount' => $netAmount,
                'rate_at_transaction' => $dto->rateAtTransaction,
                'contact_id' => null,
                'transaction_date' => $dto->transactionDate,
                'reference' => $dto->reference,
                'description' => $dto->description,
                'attachment' => $dto->attachment,
            ]);

            $this->transactionRepository->linkTransferPair(
                $expenseTransaction->id,
                $incomeTransaction->id
            );

            if (bccomp($feeAmount, '0', 4) > 0) {
                $feeCategory = TransactionCategory::query()->where('key', SystemCategoryKey::TRANSFER_FEES->value)->first();
                if ($feeCategory === null) {
                    throw new InvalidArgumentException('System transfer fee category not found.');
                }

                $this->transactionRepository->create([
                    'account_id' => $dto->sourceAccountId,
                    'type' => 'expense',
                    'category_id' => $feeCategory->id,
                    'payment_method_id' => $dto->paymentMethodId,
                    'currency_id' => $dto->currencyId,
                    'amount' => $feeAmount,
                    'rate_at_transaction' => $dto->rateAtTransaction,
                    'contact_id' => null,
                    'transaction_date' => $dto->transactionDate,
                    'reference' => $dto->reference,
                    'description' => "Transfer fee: {$sourceAccount->account_title} to {$destAccount->account_title}",
                    'attachment' => $dto->attachment,
                    'transfer_reference_id' => $expenseTransaction->id,
                ]);
            }

            // Decrement source by the full amount (net transfer + fee)
            $this->accountRepository->updateBalance($dto->sourceAccountId, $dto->amount, 'decrement');

            // Increment destination by net transfer converted to destination currency
            $destCurrencyId = $destAccount->currency_id;
            if ($destCurrencyId === $dto->currencyId) {
                $destAmount = $netAmount;
            } else {
                $baseAmount = MoneyFormatter::convertToBase($netAmount, $dto->rateAtTransaction);
                $destCurrency = Currency::find($destCurrencyId);
                $destRate = (string) ($destCurrency?->exchange_rate ?? '1.0000');
                $destAmount = bcmul($baseAmount, $destRate, 4);
            }
            $this->accountRepository->updateBalance($dto->destinationAccountId, $destAmount, 'increment');

            $expense = $this->transactionRepository->findWithRelations($expenseTransaction->id);
            $income = $this->transactionRepository->findWithRelations($incomeTransaction->id);

            if ($expense === null || $income === null) {
                throw new RuntimeException('Failed to load transfer transactions after creation.');
            }

            return [
                'expense' => $expense,
                'income' => $income,
            ];
        });
    }

    public function update(int $id, TransactionData $dto): Transaction
    {
        return DB::transaction(function () use ($id, $dto): Transaction {
            $existing = $this->transactionRepository->findWithLock($id);

            if ($existing === null) {
                throw new InvalidArgumentException("Transaction [{$id}] not found.");
            }

            if ($existing->type === 'transfer') {
                throw new InvalidArgumentException('Transfer transactions must be updated as a paired operation.');
            }

            $this->reverseTransactionEffects($existing);
            $this->assertPositiveAmount($dto->amount);

            if (in_array($dto->type, ['expense', TransactionType::LENDING_OUT, TransactionType::LENDING_REPAY_OUT], true)) {
                $this->assertSufficientBalance($dto->accountId, $dto->amount);
            }

            if (TransactionType::isLending($dto->type) && $dto->contactId === null) {
                throw new InvalidArgumentException('Lending transactions require a linked person or company.');
            }

            $attributes = $dto->toAttributes();

            if (TransactionType::isLending($dto->type)) {
                $attributes['category_id'] = null;
            }

            $existing->update($attributes);
            $this->applyTransactionEffects($dto);

            $updated = $this->transactionRepository->findWithRelations($id);

            if ($updated === null) {
                throw new RuntimeException("Failed to reload transaction [{$id}] after update.");
            }

            return $updated;
        });
    }

    public function delete(int $id): bool
    {
        return DB::transaction(function () use ($id): bool {
            $transaction = $this->transactionRepository->findWithLock($id);

            if ($transaction === null) {
                return false;
            }

            if ($transaction->type === 'transfer') {
                return $this->deleteTransferPair($transaction);
            }

            $this->reverseTransactionEffects($transaction);

            return $this->transactionRepository->delete($id);
        });
    }

    private function deleteTransferPair(Transaction $transaction): bool
    {
        $pairedId = $transaction->transfer_reference_id;

        if ($pairedId === null) {
            throw new RuntimeException("Transfer transaction [{$transaction->id}] is missing its paired reference.");
        }

        $paired = $this->transactionRepository->findWithLock((int) $pairedId);

        if ($paired === null) {
            throw new RuntimeException("Paired transfer transaction [{$pairedId}] not found.");
        }

        // Find and delete the associated fee transaction if exists.
        // A fee transaction is an expense transaction where transfer_reference_id is $transaction->id or $paired->id.
        $feeTransaction = Transaction::query()
            ->where('type', 'expense')
            ->whereIn('transfer_reference_id', [$transaction->id, $paired->id])
            ->first();

        if ($feeTransaction !== null) {
            $this->reverseTransactionEffects($feeTransaction);
            $this->transactionRepository->delete($feeTransaction->id);
        }

        $this->reverseTransactionEffects($transaction);
        $this->reverseTransactionEffects($paired);

        $deletedPrimary = $this->transactionRepository->delete($transaction->id);
        $deletedPaired = $this->transactionRepository->delete($paired->id);

        return $deletedPrimary && $deletedPaired;
    }

    private function reverseTransactionEffects(Transaction $transaction): void
    {
        $amount = (string) $transaction->amount;
        $accountId = (int) $transaction->account_id;

        match ($transaction->type) {
            'income' => $this->reverseIncomeEffects($transaction, $amount, $accountId),
            'expense' => $this->reverseExpenseEffects($transaction, $amount, $accountId),
            'transfer' => $this->reverseTransferEffects($transaction, $amount, $accountId),
            default => TransactionType::isLending($transaction->type)
                ? $this->reverseLendingEffects($transaction, $amount, $accountId)
                : throw new InvalidArgumentException("Unknown transaction type: {$transaction->type}"),
        };
    }

    private function reverseIncomeEffects(Transaction $transaction, string $amount, int $accountId): void
    {
        $this->accountRepository->updateBalance($accountId, $amount, 'decrement');
    }

    private function reverseExpenseEffects(Transaction $transaction, string $amount, int $accountId): void
    {
        $this->accountRepository->updateBalance($accountId, $amount, 'increment');
    }

    private function reverseLendingEffects(Transaction $transaction, string $amount, int $accountId): void
    {
        match ($transaction->type) {
            TransactionType::LENDING_OUT, TransactionType::LENDING_REPAY_OUT => $this->accountRepository->updateBalance($accountId, $amount, 'increment'),
            TransactionType::LENDING_IN, TransactionType::LENDING_REPAY_IN => $this->accountRepository->updateBalance($accountId, $amount, 'decrement'),
            default => null,
        };

        if ($transaction->contact_id === null) {
            return;
        }

        match ($transaction->type) {
            TransactionType::LENDING_OUT, TransactionType::LENDING_REPAY_OUT => $this->contactRepository->updateBalance((int) $transaction->contact_id, $amount, 'decrement'),
            TransactionType::LENDING_IN, TransactionType::LENDING_REPAY_IN => $this->contactRepository->updateBalance((int) $transaction->contact_id, $amount, 'increment'),
            default => null,
        };
    }

    private function reverseTransferEffects(Transaction $transaction, string $amount, int $accountId): void
    {
        if ($transaction->transfer_reference_id === null) {
            throw new RuntimeException("Transfer [{$transaction->id}] is missing its paired reference.");
        }

        if ($transaction->id < (int) $transaction->transfer_reference_id) {
            // Source side: increment back by original transfer amount.
            // If a fee existed, it was reversed separately in deleteTransferPair.
            $this->accountRepository->updateBalance($accountId, $amount, 'increment');

            return;
        }

        // Destination side: decrement by converted amount.
        $destAccount = $this->accountRepository->find($accountId);
        $destCurrencyId = $destAccount?->currency_id;

        if ($destCurrencyId === $transaction->currency_id) {
            $destAmount = $amount;
        } else {
            $baseAmount = MoneyFormatter::convertToBase($amount, (string) $transaction->rate_at_transaction);
            $destCurrency = Currency::find($destCurrencyId);
            $destRate = (string) ($destCurrency?->exchange_rate ?? '1.0000');
            $destAmount = bcmul($baseAmount, $destRate, 4);
        }

        $this->accountRepository->updateBalance($accountId, $destAmount, 'decrement');
    }

    private function applyTransactionEffects(TransactionData $dto): void
    {
        match ($dto->type) {
            'income' => $this->applyIncomeEffects($dto),
            'expense' => $this->applyExpenseEffects($dto),
            default => TransactionType::isLending($dto->type) ? $this->applyLendingEffects($dto) : throw new InvalidArgumentException("Unsupported transaction type: {$dto->type}"),
        };
    }

    private function applyIncomeEffects(TransactionData $dto): void
    {
        $this->accountRepository->updateBalance($dto->accountId, $dto->amount, 'increment');
    }

    private function applyExpenseEffects(TransactionData $dto): void
    {
        $this->accountRepository->updateBalance($dto->accountId, $dto->amount, 'decrement');
    }

    private function applyLendingEffects(TransactionData $dto): void
    {
        if ($dto->contactId === null) {
            throw new InvalidArgumentException('Lending transactions require a linked person or company.');
        }

        match ($dto->type) {
            TransactionType::LENDING_OUT, TransactionType::LENDING_REPAY_OUT => $this->accountRepository->updateBalance($dto->accountId, $dto->amount, 'decrement'),
            TransactionType::LENDING_IN, TransactionType::LENDING_REPAY_IN => $this->accountRepository->updateBalance($dto->accountId, $dto->amount, 'increment'),
            default => throw new InvalidArgumentException("Unsupported lending type: {$dto->type}"),
        };

        match ($dto->type) {
            TransactionType::LENDING_OUT, TransactionType::LENDING_REPAY_OUT => $this->contactRepository->updateBalance($dto->contactId, $dto->amount, 'increment'),
            TransactionType::LENDING_IN, TransactionType::LENDING_REPAY_IN => $this->contactRepository->updateBalance($dto->contactId, $dto->amount, 'decrement'),
            default => throw new InvalidArgumentException("Unsupported lending type: {$dto->type}"),
        };
    }

    private function assertPositiveAmount(string $amount): void
    {
        if (bccomp($amount, '0', 4) <= 0) {
            throw new InvalidArgumentException('Transaction amount must be greater than zero.');
        }
    }

    private function assertAccountExists(int $accountId): void
    {
        if ($this->accountRepository->find($accountId) === null) {
            throw new InvalidArgumentException("Account [{$accountId}] not found.");
        }
    }

    private function assertSufficientBalance(int $accountId, string $amount): void
    {
        if (config('ledger.allow_negative_balances', false)) {
            return;
        }

        $account = $this->accountRepository->findWithLock($accountId);

        if ($account === null) {
            throw new InvalidArgumentException("Account [{$accountId}] not found.");
        }

        $currentBalance = (string) $account->current_balance;

        if (bccomp($currentBalance, $amount, 4) < 0) {
            throw InsufficientBalanceException::forAccount($accountId, $currentBalance, $amount);
        }
    }
}
