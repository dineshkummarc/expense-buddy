@extends('layouts.app')

@section('title', 'Record Transaction')
@section('heading', 'Record Transaction')
@section('subheading', 'Income, expense, or lending with optional person/company link')

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
        <x-panel class="lg:col-span-2" title="Transaction Details">
            <form method="POST" action="{{ route('transactions.store') }}" class="space-y-4">
                @csrf

                <div class="grid gap-4 sm:grid-cols-2">
                    <div>
                        <label class="label">Type</label>
                        <select name="type" id="txn-type" class="input" data-search-select required>
                            <option value="income" @selected(old('type') === 'income')>Income</option>
                            <option value="expense" @selected(old('type', 'expense') === 'expense')>Expense</option>
                            <optgroup label="Lending">
                                <option value="lending_out" @selected(old('type') === 'lending_out' || old('type') === 'lending')>Loan out (you lend)</option>
                                <option value="lending_in" @selected(old('type') === 'lending_in')>Loan in (you borrow)</option>
                                <option value="lending_repay_in" @selected(old('type') === 'lending_repay_in')>Repayment received</option>
                                <option value="lending_repay_out" @selected(old('type') === 'lending_repay_out')>Repayment sent</option>
                            </optgroup>
                        </select>
                    </div>
                    <div>
                        <label class="label">Date</label>
                        <input type="date" name="transaction_date" value="{{ old('transaction_date', now()->toDateString()) }}" class="input" required>
                    </div>
                </div>

                <div>
                    <label class="label">Account</label>
                    <select name="account_id" id="txn-account" class="input" data-search-select data-placeholder="Select account" data-search-placeholder="Search accounts…" required>
                        <option value="">Select account</option>
                        @foreach ($accounts as $account)
                            <option value="{{ $account->id }}" data-currency-id="{{ $account->currency_id }}" @selected((string) old('account_id') === (string) $account->id)>
                                {{ $account->account_title }} ({{ $account->currency?->code }})
                            </option>
                        @endforeach
                    </select>
                </div>

                <div id="category-field-wrap" class="grid gap-4 sm:grid-cols-2">
                    <div>
                        <label class="label">Category</label>
                        <select name="category_id" class="input" data-search-select data-placeholder="No category" data-search-placeholder="Search categories…">
                            <option value="">No category</option>
                            @foreach ($categories as $category)
                                <option value="{{ $category->id }}" @selected((string) old('category_id') === (string) $category->id)>
                                    {{ $category->name }} ({{ $category->type }})
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div id="payment-field-wrap">
                        <label class="label">Payment Method</label>
                        <select name="payment_method_id" class="input" data-search-select data-placeholder="Select method" data-search-placeholder="Search methods…" required>
                            <option value="">Select method</option>
                            @foreach ($paymentMethods as $method)
                                <option value="{{ $method->id }}" @selected((string) old('payment_method_id') === (string) $method->id)>{{ $method->name }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>

                <div id="contact-field-wrap">
                    <label class="label">Person / Company <span id="contact-hint" class="font-normal text-slate-400">(optional — for tracking)</span></label>
                    <select name="contact_id" class="input" data-search-select data-placeholder="None selected" data-search-placeholder="Search contacts…">
                        <option value="">None selected</option>
                        @foreach ($contacts as $contact)
                            <option value="{{ $contact->id }}" @selected((string) old('contact_id') === (string) $contact->id)>
                                {{ $contact->name }} ({{ ucfirst($contact->type) }})
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="grid gap-4 sm:grid-cols-3">
                    <div>
                        <label class="label">Currency</label>
                        <select name="currency_id" id="txn-currency" class="input" data-search-select data-placeholder="Currency" data-search-placeholder="Search currencies…" required>
                            @foreach ($currencies as $currency)
                                <option value="{{ $currency->id }}" data-rate="{{ $currency->exchange_rate }}" @selected((string) old('currency_id', $currencies->first()?->id) === (string) $currency->id)>
                                    {{ $currency->code }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="label">Amount</label>
                        <input type="number" name="amount" step="0.0001" min="0.0001" value="{{ old('amount') }}" placeholder="0.00" class="input amount-input" required>
                    </div>
                    <div>
                        <label class="label">Exchange Rate</label>
                        <input type="number" name="rate_at_transaction" id="txn-rate" step="0.0001" min="0.0001" value="{{ old('rate_at_transaction', '1.0000') }}" class="input" required>
                    </div>
                </div>

                <div>
                    <label class="label">Reference</label>
                    <input type="text" name="reference" value="{{ old('reference') }}" placeholder="Invoice #, receipt, etc." class="input">
                </div>

                <div>
                    <label class="label">Description</label>
                    <textarea name="description" rows="3" placeholder="What was this for?" class="input">{{ old('description') }}</textarea>
                </div>

                <div class="flex flex-wrap gap-3 pt-2">
                    <button type="submit" class="btn-primary">Save Transaction</button>
                    <a href="{{ route('transactions.index') }}" class="btn-secondary">Cancel</a>
                </div>
            </form>
        </x-panel>

        <div class="space-y-4">
            <x-panel title="Type Guide">
                <ul class="space-y-3 text-sm text-slate-600 dark:text-slate-400">
                    <li><span class="badge-income">Income</span> Money in from sales, salary, freelance, etc.</li>
                    <li><span class="badge-expense">Expense</span> Money out for purchases and bills.</li>
                    <li><span class="badge-lending">Loan out</span> You lend money — tracked per contact, not expense.</li>
                    <li><span class="badge-lending">Loan in</span> You borrow money — not counted as income.</li>
                    <li><span class="badge-lending">Repayments</span> Track money paid back without affecting income/expense totals.</li>
                </ul>
            </x-panel>
        </div>
    </div>
@endsection
