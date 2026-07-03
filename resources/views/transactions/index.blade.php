@extends('layouts.app')

@section('title', 'Transactions')
@section('heading', 'Transaction Ledger')
@section('subheading', 'Browse and filter all income, expense, and transfer records')

@section('actions')
    <a href="{{ route('transfers.create') }}" class="btn-secondary">
        <x-ming-icon name="arrow.transfer" class="h-4 w-4" />
        Transfer Funds
    </a>
@endsection

@section('content')
    @php
        use App\Support\MoneyFormatter;
        use App\Support\TransactionType;
    @endphp

    <x-section-nav :items="[
        ['route' => 'transactions.index', 'label' => 'All Entries', 'icon' => 'business.bank-card', 'active' => 'transactions.index'],
        ['route' => 'transactions.create', 'label' => 'Record', 'icon' => 'system.add', 'active' => 'transactions.create'],
        ['route' => 'transfers.create', 'label' => 'Transfer', 'icon' => 'arrow.transfer', 'active' => 'transfers.*'],
    ]" />

    <x-panel title="Filters" subtitle="Narrow down ledger results">
        <form method="GET" action="{{ route('transactions.index') }}" class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
            <select name="type" class="input" data-search-select="off">
                <option value="">All Types</option>
                <option value="income" @selected(($filters['type'] ?? '') === 'income')>Income</option>
                <option value="expense" @selected(($filters['type'] ?? '') === 'expense')>Expense</option>
                <option value="lending_out" @selected(($filters['type'] ?? '') === 'lending_out')>Loan out</option>
                <option value="lending_in" @selected(($filters['type'] ?? '') === 'lending_in')>Loan in</option>
                <option value="lending_repay_in" @selected(($filters['type'] ?? '') === 'lending_repay_in')>Repayment in</option>
                <option value="lending_repay_out" @selected(($filters['type'] ?? '') === 'lending_repay_out')>Repayment out</option>
                <option value="transfer" @selected(($filters['type'] ?? '') === 'transfer')>Transfer</option>
            </select>
            <select name="account_id" class="input" data-search-select data-placeholder="All accounts" data-search-placeholder="Search accounts…">
                <option value="">All Accounts</option>
                @foreach ($accounts as $account)
                    <option value="{{ $account->id }}" @selected((string) ($filters['account_id'] ?? '') === (string) $account->id)>
                        {{ $account->account_title }} ({{ $account->currency?->code }})
                    </option>
                @endforeach
            </select>
            <select name="category_id" class="input" data-search-select data-placeholder="All categories" data-search-placeholder="Search categories…">
                <option value="">All Categories</option>
                @foreach ($categories as $category)
                    <option value="{{ $category->id }}" @selected((string) ($filters['category_id'] ?? '') === (string) $category->id)>{{ $category->name }}</option>
                @endforeach
            </select>
            <select name="contact_id" class="input" data-search-select data-placeholder="All people" data-search-placeholder="Search people…">
                <option value="">All People</option>
                @foreach ($contacts as $contact)
                    <option value="{{ $contact->id }}" @selected((string) ($filters['contact_id'] ?? '') === (string) $contact->id)>
                        {{ $contact->name }}
                    </option>
                @endforeach
            </select>
            <input type="date" name="date_from" value="{{ $filters['date_from'] ?? '' }}" class="input" placeholder="From">
            <input type="date" name="date_to" value="{{ $filters['date_to'] ?? '' }}" class="input" placeholder="To">
            <div class="flex gap-2 sm:col-span-2">
                <button type="submit" class="btn-primary">Apply</button>
                <a href="{{ route('transactions.index') }}" class="btn-secondary">Reset</a>
            </div>
        </form>
    </x-panel>

    <x-panel class="mt-6" title="Ledger Entries" :subtitle="$transactions->total().' records'">
        <div class="overflow-x-auto -mx-5 px-5">
            <table class="min-w-full">
                <thead>
                    <tr class="border-b border-slate-200 dark:border-slate-800">
                        <th class="th">Date</th>
                        <th class="th">Type</th>
                        <th class="th hidden sm:table-cell">Account</th>
                        <th class="th hidden md:table-cell">Category</th>
                        <th class="th hidden lg:table-cell">Person</th>
                        <th class="th text-right">Amount</th>
                        <th class="th">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-200 dark:divide-slate-800">
                    @forelse ($transactions as $transaction)
                        <tr class="hover:bg-slate-50/80 dark:hover:bg-slate-800/30">
                            <td class="td whitespace-nowrap">{{ $transaction->transaction_date->format('M d, Y') }}</td>
                            <td class="td"><x-transaction-type-badge :type="$transaction->type" /></td>
                            <td class="td hidden sm:table-cell">
                                @if ($transaction->type === 'transfer')
                                    <div class="text-sm">
                                        <span class="text-slate-500">{{ $transaction->account?->account_title }}</span>
                                        <span class="mx-1 text-slate-400">→</span>
                                        <span class="font-medium">{{ $transaction->transferReference?->account?->account_title ?? '—' }}</span>
                                    </div>
                                    <div class="flex flex-wrap items-center gap-1.5 mt-1">
                                        <x-currency-badge :currency="$transaction->currency" />
                                        @if ($feeTxn = $transaction->linkedTransfers->firstWhere('type', 'expense'))
                                            <span class="inline-flex items-center gap-1 px-1.5 py-0.5 rounded text-[10px] font-semibold bg-rose-50 text-rose-600 border border-rose-100 dark:bg-rose-950/20 dark:text-rose-400 dark:border-rose-900/30">
                                                Fee: {{ MoneyFormatter::format((string) $feeTxn->amount, $feeTxn->currency) }}
                                            </span>
                                        @endif
                                    </div>
                                @else
                                    <div>{{ $transaction->account?->account_title }}</div>
                                    <x-currency-badge :currency="$transaction->currency" class="mt-1" />
                                @endif
                            </td>
                            <td class="td hidden md:table-cell">{{ $transaction->category?->name ?? '—' }}</td>
                            <td class="td hidden lg:table-cell">{{ $transaction->contact?->name ?? '—' }}</td>
                            <td class="td text-right">
                                @php
                                    $amountClass = match ($transaction->type) {
                                        'income' => 'amount-income',
                                        'expense' => 'amount-expense',
                                        'transfer' => 'amount-transfer',
                                        default => TransactionType::isLending($transaction->type) ? 'amount-lending' : 'amount-neutral',
                                    };
                                @endphp
                                <span class="amount {{ $amountClass }}">
                                    {{ MoneyFormatter::format((string) $transaction->amount, $transaction->currency) }}
                                </span>
                            </td>
                            <td class="td">
                                 <div class="flex flex-wrap gap-2">
                                     @if ($transaction->type !== 'transfer')
                                         <a href="{{ route('transactions.invoice.show', $transaction->id) }}" class="text-sm font-medium text-brand-600 hover:underline">Invoice</a>
                                     @endif
                                     <form id="delete-txn-{{ $transaction->id }}" method="POST" action="{{ route('transactions.destroy', $transaction->id) }}" class="inline">
                                         @csrf
                                         @method('DELETE')
                                     </form>
                                     <button type="button" data-confirm="Delete this transaction?" form="delete-txn-{{ $transaction->id }}" class="text-sm font-medium text-rose-600 hover:underline">Delete</button>
                                 </div>
                             </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="px-4 py-12 text-center">
                                <p class="text-sm text-slate-500">No transactions yet.</p>
                                <a href="{{ route('transactions.create') }}" class="mt-3 inline-flex btn-primary">Record your first transaction</a>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="mt-4 border-t border-slate-200 pt-4 dark:border-slate-800">
            {{ $transactions->withQueryString()->links() }}
        </div>
    </x-panel>
@endsection
