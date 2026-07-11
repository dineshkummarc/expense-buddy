@extends('layouts.app')

@section('title', $contact->name)
@section('heading', $contact->name)
@section('subheading', ucfirst($contact->type).' · income, expenses, and lending activity')

@section('actions')
    <a href="{{ route('contacts.index') }}" class="btn-secondary">All Contacts</a>
    <a href="{{ route('contacts.edit', $contact->id) }}" class="btn-secondary">Edit</a>
@endsection

@section('content')
    @php
        use App\Support\MoneyFormatter;
        use App\Support\TransactionType;
    @endphp

    <div class="rounded-2xl border border-brand-200/80 bg-brand-50/50 px-5 py-4 dark:border-brand-900/40 dark:bg-brand-900/30">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <div class="text-sm text-slate-500">{{ $contact->email ?? $contact->phone ?? 'No contact info' }}</div>
                <div class="mt-1 text-sm text-slate-500">
                    Lending outstanding: {{ MoneyFormatter::format($summary['current_balance'], $baseCurrency) }}
                </div>
            </div>
            <span class="badge {{ $contact->status === 'active' ? 'badge-income' : 'badge-expense' }}">{{ ucfirst($contact->status) }}</span>
        </div>
    </div>

    <div class="mt-6 grid gap-4 sm:grid-cols-4">
        <x-stat-card label="Income" :value="MoneyFormatter::format($summary['income_base'], $baseCurrency)" color="emerald" icon="business.chart" />
        <x-stat-card label="Expenses" :value="MoneyFormatter::format($summary['expense_base'], $baseCurrency)" color="rose" icon="business.bank-card" />
        <x-stat-card label="Lending Outstanding" :value="MoneyFormatter::format($summary['current_balance'], $baseCurrency)" color="brand" icon="business.safe-box" />
        <x-stat-card label="Transactions" :value="$summary['transaction_count']" icon="editor.hashtag" />
    </div>

    <x-panel class="mt-6" title="Lending Balance Trend">
        <x-balance-trend-panel
            :chart="$chart"
            :period="$period"
            :contact-id="$contact->id"
            label="Outstanding"
            metric="lending"
            color="transfer"
        />
    </x-panel>

    <div class="mt-6 grid gap-6 lg:grid-cols-2">
        <x-panel title="Income Trend">
            <x-balance-trend-panel
                :chart="$incomeChart"
                :period="$period"
                :contact-id="$contact->id"
                label="Income"
                metric="income"
                color="income"
                empty-message="No linked income for this period."
            />
        </x-panel>

        <x-panel title="Expense Trend">
            <x-balance-trend-panel
                :chart="$expenseChart"
                :period="$period"
                :contact-id="$contact->id"
                label="Expenses"
                metric="expense"
                color="expense"
                empty-message="No linked expenses for this period."
            />
        </x-panel>
    </div>

    <x-panel class="mt-6" title="All Linked Transactions">
        <div class="overflow-x-auto -mx-5 px-5">
            <table class="min-w-full">
                <thead>
                    <tr class="border-b border-slate-200 dark:border-slate-800">
                        <th class="th">Date</th>
                        <th class="th">Type</th>
                        <th class="th">Account</th>
                        <th class="th text-right">Amount</th>
                        <th class="th">Note</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-200 dark:divide-slate-800">
                    @forelse ($transactions as $transaction)
                        <tr class="hover:bg-slate-50/80 dark:hover:bg-slate-800/30">
                            <td class="td whitespace-nowrap">{{ $transaction->transaction_date->format('M d, Y') }}</td>
                            <td class="td"><x-transaction-type-badge :type="$transaction->type" /></td>
                            <td class="td">{{ $transaction->account?->account_title }}</td>
                            <td class="td text-right">
                                <span class="amount {{ TransactionType::isLending($transaction->type) ? 'amount-lending' : ($transaction->type === 'income' ? 'amount-income' : 'amount-expense') }}">
                                    {{ MoneyFormatter::format((string) $transaction->amount, $transaction->currency) }}
                                </span>
                            </td>
                            <td class="td text-slate-500">{{ $transaction->description ?? $transaction->reference ?? '—' }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="px-4 py-12 text-center text-sm text-slate-500">No linked transactions yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </x-panel>
@endsection
