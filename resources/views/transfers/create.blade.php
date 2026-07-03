@extends('layouts.app')

@section('title', 'Transfer Funds')
@section('heading', 'Transfer Funds')
@section('subheading', 'Move money between your accounts — supports cross-currency')

@section('actions')
    <a href="{{ route('transactions.index') }}" class="btn-secondary">Back to Ledger</a>
@endsection

@section('content')
    <x-section-nav :items="[
        ['route' => 'transactions.index', 'label' => 'All Entries', 'icon' => 'business.bank-card', 'active' => 'transactions.index'],
        ['route' => 'transactions.create', 'label' => 'Record', 'icon' => 'system.add', 'active' => 'transactions.create'],
        ['route' => 'transfers.create', 'label' => 'Transfer', 'icon' => 'arrow.transfer', 'active' => 'transfers.*'],
    ]" />

    <div class="grid gap-6 lg:grid-cols-3">
        <x-panel class="lg:col-span-2" title="Transfer Details" subtitle="Creates a paired debit and credit entry">
            <form method="POST" action="{{ route('transfers.store') }}" class="space-y-4">
                @csrf

                <div class="grid gap-4 sm:grid-cols-2">
                    <div>
                        <label class="label">From Account</label>
                        <select name="source_account_id" id="transfer-source" class="input" data-search-select data-placeholder="Select source" data-search-placeholder="Search accounts…" required>
                            <option value="">Select source</option>
                            @foreach ($accounts as $account)
                                <option value="{{ $account->id }}" data-currency-id="{{ $account->currency_id }}" data-currency-code="{{ $account->currency?->code }}" data-currency-rate="{{ $account->currency?->exchange_rate }}" @selected((string) old('source_account_id') === (string) $account->id)>
                                    {{ $account->account_title }} ({{ $account->currency?->code }})
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="label">To Account</label>
                        <select name="destination_account_id" id="transfer-destination" class="input" data-search-select data-placeholder="Select destination" data-search-placeholder="Search accounts…" required>
                            <option value="">Select destination</option>
                            @foreach ($accounts as $account)
                                <option value="{{ $account->id }}" data-currency-id="{{ $account->currency_id }}" data-currency-code="{{ $account->currency?->code }}" data-currency-rate="{{ $account->currency?->exchange_rate }}" @selected((string) old('destination_account_id') === (string) $account->id)>
                                    {{ $account->account_title }} ({{ $account->currency?->code }})
                                </option>
                            @endforeach
                        </select>
                    </div>
                </div>

                <div class="grid gap-4 sm:grid-cols-2">
                    <div>
                        <label class="label">Payment Method</label>
                        <select name="payment_method_id" class="input" data-search-select data-placeholder="Payment method" data-search-placeholder="Search methods…" required>
                            @foreach ($paymentMethods as $method)
                                <option value="{{ $method->id }}" @selected((string) old('payment_method_id') === (string) $method->id)>{{ $method->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="label">Date</label>
                        <input type="date" name="transaction_date" value="{{ old('transaction_date', now()->toDateString()) }}" class="input" required>
                    </div>
                </div>

                <div class="grid gap-4 sm:grid-cols-3">
                    <div>
                        <label class="label">Currency</label>
                        <select name="currency_id" id="transfer-currency" class="input" data-search-select data-placeholder="Currency" data-search-placeholder="Search currencies…" required>
                            @foreach ($currencies as $currency)
                                <option value="{{ $currency->id }}" data-rate="{{ $currency->exchange_rate }}" @selected((string) old('currency_id', $currencies->first()?->id) === (string) $currency->id)>
                                    {{ $currency->code }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="label">Amount</label>
                        <input type="number" name="amount" id="transfer-amount" step="0.0001" min="0.0001" value="{{ old('amount') }}" placeholder="0.00" class="input amount-input" required>
                    </div>
                    <div>
                        <label class="label">Exchange Rate</label>
                        <input type="number" name="rate_at_transaction" id="transfer-rate" step="0.0001" min="0.0001" value="{{ old('rate_at_transaction', '1.0000') }}" class="input" required>
                    </div>
                </div>

                @if ($systemSettings && $systemSettings->transfer_fees_enabled)
                    <div class="border-t border-slate-200 dark:border-slate-800 pt-4 mt-4 space-y-4">
                        <div>
                            <label class="label">Fee Options</label>
                            <select name="fee_option" id="transfer-fee-option" class="input" data-search-select>
                                <option value="no_fees" @selected(old('fee_option', 'no_fees') === 'no_fees')>No Fees</option>
                                <option value="charge_fee" @selected(old('fee_option') === 'charge_fee')>Charge Transfer Fee / Cashout Fee</option>
                            </select>
                        </div>

                        <div id="fee-fields" class="grid gap-4 sm:grid-cols-2 {{ old('fee_option') === 'charge_fee' ? '' : 'hidden' }}">
                            <div class="sm:col-span-2">
                                <label class="label" id="destination-amount-label">Destination Amount</label>
                                <input type="number" name="destination_amount" id="transfer-destination-amount" step="0.0001" min="0.0001" value="{{ old('destination_amount') }}" placeholder="0.00" class="input">
                            </div>
                            <div class="sm:col-span-2">
                                <p id="fee-calculation-preview" class="text-sm font-medium text-slate-600 dark:text-slate-400 bg-slate-50 dark:bg-slate-900/40 border border-slate-200 dark:border-slate-800 rounded-xl p-3 hidden">
                                    <!-- Dynamic text populated by JS -->
                                </p>
                            </div>
                        </div>
                    </div>
                @else
                    <input type="hidden" name="fee_option" value="no_fees">
                @endif

                <div class="flex flex-wrap gap-3 pt-2">
                    <button type="submit" class="btn-primary">Execute Transfer</button>
                    <a href="{{ route('transactions.index') }}" class="btn-secondary">Cancel</a>
                </div>

                <script>
                    document.addEventListener('DOMContentLoaded', () => {
                        const feeOption = document.getElementById('transfer-fee-option');
                        const feeFields = document.getElementById('fee-fields');
                        const sourceSelect = document.getElementById('transfer-source');
                        const destSelect = document.getElementById('transfer-destination');
                        const amountInput = document.getElementById('transfer-amount');
                        const rateInput = document.getElementById('transfer-rate');
                        const destAmountInput = document.getElementById('transfer-destination-amount');
                        const destLabel = document.getElementById('destination-amount-label');
                        const previewText = document.getElementById('fee-calculation-preview');

                        if (!feeOption) return;

                        const updateLabelsAndCalculation = () => {
                            const sourceOpt = sourceSelect.selectedOptions[0];
                            const destOpt = destSelect.selectedOptions[0];

                            const sourceCode = sourceOpt?.dataset.currencyCode || '';
                            const destCode = destOpt?.dataset.currencyCode || '';

                            // Update label
                            if (destCode) {
                                destLabel.textContent = `Destination Amount (${destCode})`;
                                destAmountInput.placeholder = `0.00 ${destCode}`;
                            } else {
                                destLabel.textContent = 'Destination Amount';
                                destAmountInput.placeholder = '0.00';
                            }

                            // Calculate fee if Charge Fee is active
                            if (feeOption.value === 'charge_fee') {
                                const amount = parseFloat(amountInput.value) || 0;
                                const rate = parseFloat(rateInput.value) || 1;
                                const destAmount = parseFloat(destAmountInput.value) || 0;

                                if (amount > 0 && destAmount > 0) {
                                    const sourceRate = parseFloat(sourceOpt?.dataset.currencyRate) || 1.0;
                                    const destRate = parseFloat(destOpt?.dataset.currencyRate) || 1.0;

                                    if (sourceOpt?.dataset.currencyId === destOpt?.dataset.currencyId) {
                                        // Same currency
                                        const loss = amount - destAmount;
                                        if (loss >= 0) {
                                            previewText.textContent = `Loss of ${loss.toFixed(2)} ${sourceCode} will be charged as a fee.`;
                                            previewText.classList.remove('hidden');
                                        } else {
                                            previewText.textContent = `Destination amount cannot exceed transfer amount.`;
                                            previewText.classList.remove('hidden');
                                        }
                                    } else {
                                        // Cross currency
                                        // Expected = (Amount / Rate) * Destination Rate
                                        const expectedDest = (amount / rate) * destRate;
                                        const lossDest = expectedDest - destAmount;
                                        const lossSource = amount - (destAmount / destRate) * rate;

                                        if (lossSource >= 0) {
                                            previewText.textContent = `Loss: ${lossDest.toFixed(2)} ${destCode} (${lossSource.toFixed(2)} ${sourceCode}) will be charged as a fee.`;
                                            previewText.classList.remove('hidden');
                                        } else {
                                            previewText.textContent = `Destination amount cannot exceed expected converted transfer amount (${expectedDest.toFixed(2)} ${destCode}).`;
                                            previewText.classList.remove('hidden');
                                        }
                                    }
                                } else {
                                    previewText.classList.add('hidden');
                                }
                            } else {
                                previewText.classList.add('hidden');
                            }
                        };

                        feeOption.addEventListener('change', () => {
                            if (feeOption.value === 'charge_fee') {
                                feeFields.classList.remove('hidden');
                            } else {
                                feeFields.classList.add('hidden');
                            }
                            updateLabelsAndCalculation();
                        });

                        [sourceSelect, destSelect, amountInput, rateInput, destAmountInput].forEach(el => {
                            if (el) el.addEventListener('change', updateLabelsAndCalculation);
                            if (el) el.addEventListener('input', updateLabelsAndCalculation);
                        });

                        // Initial check
                        updateLabelsAndCalculation();
                    });
                </script>
            </form>
        </x-panel>

        <div class="space-y-4">
            <x-panel title="How transfers work">
                <ul class="space-y-3 text-sm text-slate-600 dark:text-slate-400">
                    <li>Transfers move money between <strong>your own accounts</strong> only.</li>
                    <li>Cross-currency transfers use the exchange rate you enter.</li>
                    <li>Two linked entries are created — one debit, one credit.</li>
                    <li>Transfers do not affect lending balances.</li>
                </ul>
            </x-panel>
            <x-panel title="Record income or expense?">
                <p class="text-sm text-slate-600 dark:text-slate-400">External money in/out uses the transaction recorder.</p>
                <a href="{{ route('transactions.create') }}" class="btn-secondary mt-4 w-full">Record Transaction</a>
            </x-panel>
        </div>
    </div>
@endsection
