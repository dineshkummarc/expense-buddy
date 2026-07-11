@extends('layouts.app')

@section('title', 'Dashboard')
@section('heading', 'Analytics Dashboard')
@section('subheading', 'Multi-currency cash flow, balances, and trends')

@section('content')
    @php
        use App\Support\MoneyFormatter;
        $base = $summary['base_currency'];
    @endphp

    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <x-stat-card label="Total Income" :value="MoneyFormatter::format($summary['total_income'], $base)" color="emerald" icon="arrow.arrow-up" trend="All-time recognized income" />
        <x-stat-card label="Total Expense" :value="MoneyFormatter::format($summary['total_expense'], $base)" color="rose" icon="arrow.arrow-down" trend="Including outbound transfers" />
        <x-stat-card label="Net P&L" :value="MoneyFormatter::format($summary['net_balance'], $base)" color="violet" icon="business.chart" trend="Income minus expense" />
        <x-stat-card label="Cash (Base)" :value="MoneyFormatter::format($summary['net_cash_assets'], $base)" color="brand" icon="business.wallet" trend="Converted across all currencies" />
    </div>

    <div class="mt-6 grid gap-6 xl:grid-cols-3">
        <x-panel class="xl:col-span-2" title="Cash Flow — Last 6 Months" subtitle="Income, expense, and net margin trend">
            <div class="h-72">
                <canvas id="cashFlowChart"></canvas>
            </div>
        </x-panel>

        <x-panel title="Transaction Mix" subtitle="Volume by transaction type">
            <div class="h-72">
                <canvas id="typeMixChart"></canvas>
            </div>
        </x-panel>
    </div>

    <div class="mt-6 grid gap-6 lg:grid-cols-2">
        <x-panel title="Currency Exposure" subtitle="Native balances with base-currency equivalent">
            <div class="space-y-4">
                @foreach ($summary['currency_breakdown'] as $row)
                    <div class="rounded-xl border border-slate-200/80 p-4 dark:border-slate-800">
                        <div class="flex items-center justify-between gap-3">
                            <div class="flex items-center gap-2">
                                <x-currency-badge :currency="$row['currency']" />
                                <span class="text-sm text-slate-500">{{ $row['account_count'] }} account(s)</span>
                            </div>
                            <div class="text-right">
                                <div class="font-mono text-sm font-semibold">{{ MoneyFormatter::format($row['balance_native'], $row['currency']) }}</div>
                                <div class="text-xs text-slate-500">≈ {{ MoneyFormatter::format($row['balance_base'], $base) }}</div>
                            </div>
                        </div>
                        @php
                            $share = $summary['net_cash_assets'] !== '0.0000'
                                ? (float) bcmul(bcdiv($row['balance_base'], $summary['net_cash_assets'], 6), '100', 2)
                                : 0;
                        @endphp
                        <div class="mt-3 h-2 overflow-hidden rounded-full bg-slate-100 dark:bg-slate-800">
                            <div class="h-full rounded-full bg-brand-500" style="width: {{ min($share, 100) }}%"></div>
                        </div>
                    </div>
                @endforeach
            </div>
        </x-panel>

        <x-panel title="Account Balances" subtitle="Converted to base currency for fair comparison">
            <div class="h-80">
                <canvas id="accountBalanceChart"></canvas>
            </div>
        </x-panel>
    </div>

    <x-panel class="mt-6" title="Recent Activity" subtitle="Latest ledger movements">
        <div class="overflow-x-auto">
            <table class="min-w-full">
                <thead>
                    <tr class="border-b border-slate-200 dark:border-slate-800">
                        <th class="th">Date</th>
                        <th class="th">Type</th>
                        <th class="th">Account</th>
                        <th class="th">Amount</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-200 dark:divide-slate-800">
                    @forelse ($summary['recent_transactions'] as $txn)
                        <tr class="hover:bg-slate-50/80 dark:hover:bg-slate-800/30">
                            <td class="td">{{ $txn->transaction_date->format('M d, Y') }}</td>
                            <td class="td"><x-transaction-type-badge :type="$txn->type" /></td>
                            <td class="td">
                                <div>{{ $txn->account?->account_title }}</div>
                                <x-currency-badge :currency="$txn->currency" class="mt-1" />
                            </td>
                            <td class="td font-mono font-semibold">{{ MoneyFormatter::format((string) $txn->amount, $txn->currency) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="px-4 py-8 text-center text-sm text-slate-500">No transactions yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </x-panel>
@endsection

@push('scripts')
<x-chart-init>
    const colors = window.LedgerCharts.chartColors();

    new Chart(document.getElementById('cashFlowChart'), {
        type: 'bar',
        data: {
            labels: @json($summary['chart_labels']),
            datasets: [
                { label: 'Income', data: @json($summary['chart_income']), backgroundColor: colors.income + '99', borderRadius: 8 },
                { label: 'Expense', data: @json($summary['chart_expense']), backgroundColor: colors.expense + '99', borderRadius: 8 },
                { label: 'Net', data: @json($summary['chart_net']), type: 'line', borderColor: colors.transfer, backgroundColor: 'transparent', tension: 0.35 },
            ],
        },
        options: window.LedgerCharts.baseChartOptions(),
    });

    new Chart(document.getElementById('typeMixChart'), {
        type: 'doughnut',
        data: {
            labels: @json($summary['type_distribution']['labels']),
            datasets: [{ data: @json($summary['type_distribution']['values']), backgroundColor: [colors.income, colors.expense, colors.transfer], borderWidth: 0 }],
        },
        options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { position: 'bottom', labels: { color: colors.text } } } },
    });

    new Chart(document.getElementById('accountBalanceChart'), {
        type: 'bar',
        data: {
            labels: @json($summary['account_balances']['labels']),
            datasets: [{ label: 'Balance', data: @json($summary['account_balances']['values']), backgroundColor: colors.palette, borderRadius: 8 }],
        },
        options: (() => {
            const opts = window.LedgerCharts.baseChartOptions();
            const tips = @json($summary['account_balances']['tooltips']);

            return {
                ...opts,
                indexAxis: 'y',
                plugins: {
                    ...opts.plugins,
                    tooltip: {
                        callbacks: {
                            label(ctx) {
                                const tip = tips[ctx.dataIndex];

                                if (! tip) {
                                    return `Balance: ${ctx.formattedValue}`;
                                }

                                if (tip.is_base) {
                                    return tip.native;
                                }

                                return [tip.native, `≈ ${tip.base}`];
                            },
                        },
                    },
                },
            };
        })(),
    });
</x-chart-init>
@endpush
