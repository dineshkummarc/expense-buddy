<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Account;
use App\Models\Contact;
use App\Models\Currency;
use App\Models\PaymentMethod;
use App\Models\Transaction;
use App\Models\TransactionCategory;
use App\Services\TransactionService;
use App\DTOs\TransactionData;
use App\DTOs\TransferData;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

class DummyTransactionSeeder extends Seeder
{
    public function run(TransactionService $transactionService): void
    {
        // 1. Run DemoDataSeeder to make sure base accounts, categories, and payment methods exist
        $this->call(DemoDataSeeder::class);

        // 2. Fetch required currencies
        $usd = Currency::query()->where('code', 'USD')->first()
            ?? Currency::query()->where('is_default', true)->first();
        $bdt = Currency::query()->where('code', 'BDT')->first();

        if ($usd === null || $bdt === null) {
            return;
        }

        // 3. Create additional wallets/accounts
        Account::query()->firstOrCreate(
            ['account_number' => 'BKASH-WALLET'],
            [
                'account_title' => 'bKash Wallet',
                'currency_id' => $bdt->id,
                'initial_balance' => '75000.0000',
                'current_balance' => '75000.0000',
                'note' => 'Mobile banking wallet',
            ],
        );

        Account::query()->firstOrCreate(
            ['account_number' => 'WISE-USD'],
            [
                'account_title' => 'Wise USD Account',
                'currency_id' => $usd->id,
                'initial_balance' => '12000.0000',
                'current_balance' => '12000.0000',
                'note' => 'International multi-currency account',
            ],
        );

        Account::query()->firstOrCreate(
            ['account_number' => 'PAYONEER-USD'],
            [
                'account_title' => 'Payoneer Wallet',
                'currency_id' => $usd->id,
                'initial_balance' => '8500.0000',
                'current_balance' => '8500.0000',
                'note' => 'Freelance payout wallet',
            ],
        );

        // 4. Create additional transaction categories
        foreach (['Freelancing Income', 'Affiliate Commission', 'YouTube AdSense'] as $name) {
            TransactionCategory::query()->firstOrCreate(
                ['name' => $name, 'type' => 'income'],
                ['status' => 'active'],
            );
        }

        foreach (['Software Subscription', 'Server Hosting', 'Internet Bill', 'Food & Dining', 'Travel & Commute'] as $name) {
            TransactionCategory::query()->firstOrCreate(
                ['name' => $name, 'type' => 'expense'],
                ['status' => 'active'],
            );
        }

        // 5. Create additional Contacts for rich lending options
        Contact::query()->firstOrCreate(
            ['email' => 'john@example.com'],
            [
                'type' => 'person',
                'name' => 'John Smith',
                'phone' => '+1-555-0300',
                'current_balance' => '0.0000',
                'status' => 'active',
            ],
        );

        Contact::query()->firstOrCreate(
            ['name' => 'Acme Corp'],
            [
                'type' => 'company',
                'email' => 'finance@acme.example',
                'phone' => '+1-555-0400',
                'company' => 'Acme Corp',
                'current_balance' => '0.0000',
                'status' => 'active',
            ],
        );

        // 6. Clear existing transactions
        Transaction::query()->delete();

        // 7. Reset account balances and contact balances
        $accounts = Account::all();
        foreach ($accounts as $acc) {
            $acc->current_balance = $acc->initial_balance;
            $acc->save();
        }

        $contacts = Contact::all();
        foreach ($contacts as $contact) {
            $contact->current_balance = '0.0000';
            $contact->save();
        }

        $incomeCats = TransactionCategory::query()->where('type', 'income')->get();
        $expenseCats = TransactionCategory::query()->where('type', 'expense')->get();
        $pm = PaymentMethod::all();

        if ($incomeCats->isEmpty() || $expenseCats->isEmpty() || $pm->isEmpty() || $accounts->isEmpty() || $contacts->isEmpty()) {
            return;
        }

        // 8. Create dummy transactions for the last 180 days (6 months)
        $now = Carbon::now();
        
        for ($i = 179; $i >= 0; $i--) {
            $date = $now->copy()->subDays($i)->format('Y-m-d');

            // Generate 1-2 income transactions per day (with higher probability of freelancers/YT income)
            $incomeCount = rand(0, 1) === 1 ? rand(1, 2) : 0;
            for ($j = 0; $j < $incomeCount; $j++) {
                $account = Account::find($accounts->random()->id);
                $cat = $incomeCats->random();
                $method = $pm->random();
                $amount = rand(300, 3500);

                $dto = TransactionData::fromArray([
                    'account_id' => $account->id,
                    'type' => 'income',
                    'category_id' => $cat->id,
                    'payment_method_id' => $method->id,
                    'currency_id' => $account->currency_id,
                    'amount' => (string) $amount,
                    'rate_at_transaction' => (string) $account->currency->exchange_rate,
                    'transaction_date' => $date,
                    'description' => "Received income for {$cat->name}",
                ]);

                $transactionService->createIncome($dto);
            }

            // Generate 1-3 expense transactions per day
            $expenseCount = rand(1, 3);
            for ($j = 0; $j < $expenseCount; $j++) {
                $account = Account::find($accounts->random()->id);
                $cat = $expenseCats->random();
                $method = $pm->random();
                $amount = rand(20, 450);

                // Ensure balance doesn't drop below zero if policy blocks it
                if (bccomp((string) $account->current_balance, (string) $amount, 4) >= 0) {
                    $dto = TransactionData::fromArray([
                        'account_id' => $account->id,
                        'type' => 'expense',
                        'category_id' => $cat->id,
                        'payment_method_id' => $method->id,
                        'currency_id' => $account->currency_id,
                        'amount' => (string) $amount,
                        'rate_at_transaction' => (string) $account->currency->exchange_rate,
                        'transaction_date' => $date,
                        'description' => "Paid expense for {$cat->name}",
                    ]);

                    $transactionService->createExpense($dto);
                }
            }

            // Generate a transfer every 4 days to simulate movement of funds
            if ($i % 4 === 0 && $accounts->count() >= 2) {
                $src = Account::find($accounts->random()->id);
                // Ensure destination is different from source
                $dst = Account::find($accounts->where('id', '!=', $src->id)->random()->id);
                $method = $pm->random();
                
                $amount = rand(100, 1000);
                if (bccomp((string) $src->current_balance, (string) $amount, 4) >= 0) {
                    $srcRate = (float) $src->currency->exchange_rate;
                    $dstRate = (float) $dst->currency->exchange_rate;

                    // Convert to destination amount through base currency
                    $baseAmount = $amount / $srcRate;
                    $dstAmount = $baseAmount * $dstRate;

                    if ($src->currency_id === $dst->currency_id) {
                        // Same currency transfer
                        $feeOption = rand(0, 1) === 1 ? 'charge_fee' : 'no_fees';
                        $destAmountVal = $feeOption === 'charge_fee' ? (string) ($dstAmount * 0.98) : null;
                    } else {
                        // Cross-currency transfer: Always charge fee (with 5% conversion loss to guarantee safety)
                        $feeOption = 'charge_fee';
                        $destAmountVal = (string) ($dstAmount * 0.95);
                    }

                    $dto = TransferData::fromArray([
                        'source_account_id' => $src->id,
                        'destination_account_id' => $dst->id,
                        'payment_method_id' => $method->id,
                        'currency_id' => $src->currency_id,
                        'amount' => (string) $amount,
                        'rate_at_transaction' => (string) $srcRate,
                        'transaction_date' => $date,
                        'fee_option' => $feeOption,
                        'destination_amount' => $destAmountVal,
                        'description' => "Transferred funds from {$src->account_title} to {$dst->account_title}",
                    ]);

                    $transactionService->createTransfer($dto);
                }
            }

            // Generate lending transactions periodically
            if ($i % 12 === 0) {
                // Determine lending direction
                $lendingType = rand(0, 3);
                $contact = Contact::find($contacts->random()->id);
                $account = Account::find($accounts->random()->id);
                $method = $pm->random();

                if ($lendingType === 0) {
                    // 1. We lend to contact (lending_out)
                    $amount = rand(500, 2000);
                    if (bccomp((string) $account->current_balance, (string) $amount, 4) >= 0) {
                        $dto = TransactionData::fromArray([
                            'account_id' => $account->id,
                            'type' => 'lending_out',
                            'payment_method_id' => $method->id,
                            'currency_id' => $account->currency_id,
                            'amount' => (string) $amount,
                            'rate_at_transaction' => (string) $account->currency->exchange_rate,
                            'transaction_date' => $date,
                            'contact_id' => $contact->id,
                            'description' => "Lent money to {$contact->name}",
                        ]);
                        $transactionService->createLending($dto);
                    }
                } elseif ($lendingType === 1) {
                    // 2. We borrow from contact (lending_in)
                    $amount = rand(500, 2500);
                    $dto = TransactionData::fromArray([
                        'account_id' => $account->id,
                        'type' => 'lending_in',
                        'payment_method_id' => $method->id,
                        'currency_id' => $account->currency_id,
                        'amount' => (string) $amount,
                        'rate_at_transaction' => (string) $account->currency->exchange_rate,
                        'transaction_date' => $date,
                        'contact_id' => $contact->id,
                        'description' => "Borrowed money from {$contact->name}",
                    ]);
                    $transactionService->createLending($dto);
                } elseif ($lendingType === 2) {
                    // 3. Contact repays us (lending_repay_in)
                    // Only repay if contact balance is positive (meaning they owe us money)
                    $contactModel = Contact::find($contact->id);
                    if (bccomp((string) $contactModel->current_balance, '0', 4) > 0) {
                        $amount = rand(100, (int) $contactModel->current_balance);
                        $dto = TransactionData::fromArray([
                            'account_id' => $account->id,
                            'type' => 'lending_repay_in',
                            'payment_method_id' => $method->id,
                            'currency_id' => $account->currency_id,
                            'amount' => (string) $amount,
                            'rate_at_transaction' => (string) $account->currency->exchange_rate,
                            'transaction_date' => $date,
                            'contact_id' => $contact->id,
                            'description' => "Received payback from {$contact->name}",
                        ]);
                        $transactionService->createLending($dto);
                    }
                } elseif ($lendingType === 3) {
                    // 4. We repay contact (lending_repay_out)
                    // Only repay if contact balance is negative (meaning we owe them money)
                    $contactModel = Contact::find($contact->id);
                    if (bccomp((string) $contactModel->current_balance, '0', 4) < 0) {
                        $debt = bcsub('0', (string) $contactModel->current_balance, 4);
                        $amount = rand(100, (int) $debt);
                        if (bccomp((string) $account->current_balance, (string) $amount, 4) >= 0) {
                            $dto = TransactionData::fromArray([
                                'account_id' => $account->id,
                                'type' => 'lending_repay_out',
                                'payment_method_id' => $method->id,
                                'currency_id' => $account->currency_id,
                                'amount' => (string) $amount,
                                'rate_at_transaction' => (string) $account->currency->exchange_rate,
                                'transaction_date' => $date,
                                'contact_id' => $contact->id,
                                'description' => "Repaid our debt to {$contact->name}",
                            ]);
                            $transactionService->createLending($dto);
                        }
                    }
                }
            }
        }
    }
}
