<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Account;
use App\Models\Currency;
use App\Models\PaymentMethod;
use App\Models\SystemSetting;
use App\Models\Transaction;
use App\Models\TransactionCategory;
use App\Support\ExpenseBuddyTestHarness;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class TransferFundFeesTest extends TestCase
{
    #[Test]
    public function it_can_create_a_transfer_without_a_fee(): void
    {
        $admin = ExpenseBuddyTestHarness::install();

        $usd = Currency::query()->where('code', 'USD')->firstOrFail();

        $source = Account::query()->create([
            'account_title' => 'Wise USD',
            'account_number' => 'WISE-100',
            'currency_id' => $usd->id,
            'initial_balance' => '500.0000',
            'current_balance' => '500.0000',
        ]);

        $destination = Account::query()->create([
            'account_title' => 'Bank USD',
            'account_number' => 'BANK-200',
            'currency_id' => $usd->id,
            'initial_balance' => '100.0000',
            'current_balance' => '100.0000',
        ]);

        $paymentMethod = PaymentMethod::query()->firstOrCreate(['name' => 'Bank Transfer'], ['status' => 'active']);

        $response = $this->actingAs($admin)->post('/transfers', [
            'source_account_id' => $source->id,
            'destination_account_id' => $destination->id,
            'payment_method_id' => $paymentMethod->id,
            'currency_id' => $usd->id,
            'amount' => '100.0000',
            'rate_at_transaction' => '1.0000',
            'transaction_date' => now()->toDateString(),
            'fee_option' => 'no_fees',
        ]);

        $response->assertRedirect(route('transactions.index'));
        $this->assertEquals('400.0000', $source->fresh()->current_balance);
        $this->assertEquals('200.0000', $destination->fresh()->current_balance);
    }

    #[Test]
    public function it_can_create_a_transfer_with_a_fee_same_currency(): void
    {
        $admin = ExpenseBuddyTestHarness::install();

        $usd = Currency::query()->where('code', 'USD')->firstOrFail();

        $source = Account::query()->create([
            'account_title' => 'Wise USD',
            'account_number' => 'WISE-100',
            'currency_id' => $usd->id,
            'initial_balance' => '500.0000',
            'current_balance' => '500.0000',
        ]);

        $destination = Account::query()->create([
            'account_title' => 'Bank USD',
            'account_number' => 'BANK-200',
            'currency_id' => $usd->id,
            'initial_balance' => '100.0000',
            'current_balance' => '100.0000',
        ]);

        $paymentMethod = PaymentMethod::query()->firstOrCreate(['name' => 'Bank Transfer'], ['status' => 'active']);
        $feeCategory = TransactionCategory::query()->where('is_system', true)->firstOrFail();

        // Transfer 100 USD, destination receives 90 USD (10 USD fee)
        $response = $this->actingAs($admin)->post('/transfers', [
            'source_account_id' => $source->id,
            'destination_account_id' => $destination->id,
            'payment_method_id' => $paymentMethod->id,
            'currency_id' => $usd->id,
            'amount' => '100.0000',
            'rate_at_transaction' => '1.0000',
            'transaction_date' => now()->toDateString(),
            'fee_option' => 'charge_fee',
            'destination_amount' => '90.0000',
        ]);

        $response->assertRedirect(route('transactions.index'));
        // Wise should decrease by 100 (90 net transfer + 10 fee)
        $this->assertEquals('400.0000', $source->fresh()->current_balance);
        // Bank should increase by 90
        $this->assertEquals('190.0000', $destination->fresh()->current_balance);

        // Verify fee transaction exists
        $feeTxn = Transaction::query()->where('type', 'expense')->where('category_id', $feeCategory->id)->firstOrFail();
        $this->assertEquals('10.0000', $feeTxn->amount);

        // Delete the transfer and check if balances are refunded correctly
        $primaryTransfer = Transaction::query()->where('type', 'transfer')->where('account_id', $source->id)->firstOrFail();
        $responseDelete = $this->actingAs($admin)->delete("/transactions/{$primaryTransfer->id}");
        $responseDelete->assertRedirect(route('transactions.index'));

        $this->assertEquals('500.0000', $source->fresh()->current_balance);
        $this->assertEquals('100.0000', $destination->fresh()->current_balance);
        $this->assertDatabaseMissing('transactions', ['id' => $feeTxn->id]);
    }

    #[Test]
    public function it_can_create_cross_currency_transfer_with_fee(): void
    {
        $admin = ExpenseBuddyTestHarness::install();

        $usd = Currency::query()->where('code', 'USD')->firstOrFail();
        
        $bdt = Currency::query()->create([
            'name' => 'Bangladeshi Taka',
            'code' => 'BDT',
            'symbol' => '৳',
            'exchange_rate' => '120.0000',
            'is_default' => false,
        ]);

        $source = Account::query()->create([
            'account_title' => 'Wise USD',
            'account_number' => 'WISE-100',
            'currency_id' => $usd->id,
            'initial_balance' => '500.0000',
            'current_balance' => '500.0000',
        ]);

        $destination = Account::query()->create([
            'account_title' => 'bKash BDT',
            'account_number' => 'BKASH-200',
            'currency_id' => $bdt->id,
            'initial_balance' => '10000.0000',
            'current_balance' => '10000.0000',
        ]);

        $paymentMethod = PaymentMethod::query()->firstOrCreate(['name' => 'Mobile Banking'], ['status' => 'active']);
        $feeCategory = TransactionCategory::query()->where('is_system', true)->firstOrFail();

        // Wise has 100 USD transfer, rate at transaction is 1.0 (since USD is the base currency).
        // BDT exchange rate is 120.0.
        // Expecting destination amount 12000 BDT, but user got 10500 BDT.
        // Loss is 1500 BDT = 12.50 USD. Net transfer is 87.50 USD.
        $response = $this->actingAs($admin)->post('/transfers', [
            'source_account_id' => $source->id,
            'destination_account_id' => $destination->id,
            'payment_method_id' => $paymentMethod->id,
            'currency_id' => $usd->id,
            'amount' => '100.0000',
            'rate_at_transaction' => '1.0000',
            'transaction_date' => now()->toDateString(),
            'fee_option' => 'charge_fee',
            'destination_amount' => '10500.0000',
        ]);

        $response->assertRedirect(route('transactions.index'));
        // Wise should decrease by 100.00 USD (87.50 net + 12.50 fee)
        $this->assertEquals('400.0000', $source->fresh()->current_balance);
        // bKash should increase by exactly 10,500.00 BDT (10000 initial + 10500 transfer)
        $this->assertEquals('20500.0000', $destination->fresh()->current_balance);

        // Verify fee transaction exists
        $feeTxn = Transaction::query()->where('type', 'expense')->where('category_id', $feeCategory->id)->firstOrFail();
        // Fee amount should be exactly 12.50 USD
        $this->assertEquals('12.5000', $feeTxn->amount);

        // Delete the transfer and check if balances are refunded correctly
        $primaryTransfer = Transaction::query()->where('type', 'transfer')->where('account_id', $source->id)->firstOrFail();
        $responseDelete = $this->actingAs($admin)->delete("/transactions/{$primaryTransfer->id}");
        $responseDelete->assertRedirect(route('transactions.index'));

        $this->assertEquals('500.0000', $source->fresh()->current_balance);
        $this->assertEquals('10000.0000', $destination->fresh()->current_balance);
        $this->assertDatabaseMissing('transactions', ['id' => $feeTxn->id]);
    }

    #[Test]
    public function it_hides_the_system_category_when_disabled(): void
    {
        $admin = ExpenseBuddyTestHarness::install();
        $settings = SystemSetting::firstOrFail();

        // Initially enabled, category is active and visible
        $this->assertTrue(TransactionCategory::active()->where('is_system', true)->exists());

        // Disable settings
        $settings->update(['transfer_fees_enabled' => false]);
        $this->assertFalse(TransactionCategory::active()->where('is_system', true)->exists());

        // Enable again
        $settings->update(['transfer_fees_enabled' => true]);
        $this->assertTrue(TransactionCategory::active()->where('is_system', true)->exists());
    }

    #[Test]
    public function it_prevents_modifying_or_archiving_system_categories(): void
    {
        $admin = ExpenseBuddyTestHarness::install();
        $systemCategory = TransactionCategory::query()->where('is_system', true)->firstOrFail();

        // Try to update name
        $responseUpdate = $this->actingAs($admin)->put("/categories/{$systemCategory->id}", [
            'name' => 'Hacked Category',
            'type' => 'expense',
            'status' => 'active', // status is required by validation
        ]);
        $responseUpdate->assertSessionHasErrors('form');
        $this->assertEquals('Transfer Fees', $systemCategory->fresh()->name);

        // Try to archive
        $responseArchive = $this->actingAs($admin)->put("/categories/{$systemCategory->id}/archive");
        $responseArchive->assertSessionHasErrors('form');
        $this->assertEquals('active', $systemCategory->fresh()->status);
    }
}
