<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Account;
use App\Models\Currency;
use App\Models\Transaction;
use App\Support\MoneyFormatter;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class DashboardService
{
    /**
     * @return array<string, mixed>
     */
    public function getSummary(): array
    {
        $baseCurrency = MoneyFormatter::baseCurrency();
        $baseRate = (string) ($baseCurrency?->exchange_rate ?? '1.0000');

        $totalIncome = $this->sumInBase('income');
        $totalExpense = $this->sumInBase('expense');
        $chartData = $this->buildSixMonthChart();
        $currencyBreakdown = $this->buildCurrencyBreakdown($baseRate);
        $recentTransactions = $this->recentTransactions();

        $netCashBase = '0.0000';
        foreach ($currencyBreakdown as $row) {
            $netCashBase = bcadd($netCashBase, (string) $row['balance_base'], 4);
        }

        return [
            'base_currency' => $baseCurrency,
            'total_income' => $totalIncome,
            'total_expense' => $totalExpense,
            'net_balance' => bcsub($totalIncome, $totalExpense, 4),
            'net_cash_assets' => $netCashBase,
            'currency_breakdown' => $currencyBreakdown,
            'recent_transactions' => $recentTransactions,
            'chart_labels' => $chartData['labels'],
            'chart_income' => $chartData['income'],
            'chart_expense' => $chartData['expense'],
            'chart_net' => $chartData['net'],
            'type_distribution' => $this->typeDistribution(),
            'account_balances' => $this->accountBalanceChart(),
        ];
    }

    private function sumInBase(string $type): string
    {
        $expression = MoneyFormatter::baseAmountExpression();

        return bcadd((string) (Transaction::query()
            ->where('type', $type)
            ->selectRaw("COALESCE(SUM({$expression}), 0) as total_base")
            ->value('total_base') ?? '0'), '0', 4);
    }

    /**
     * @return array{labels: list<string>, income: list<string>, expense: list<string>, net: list<string>}
     */
    private function buildSixMonthChart(): array
    {
        $labels = [];
        $income = [];
        $expense = [];
        $net = [];

        for ($i = 5; $i >= 0; $i--) {
            $month = Carbon::now()->subMonths($i);
            $start = $month->copy()->startOfMonth()->toDateString();
            $end = $month->copy()->endOfMonth()->toDateString();

            $labels[] = $month->format('M Y');

            $baseExpression = MoneyFormatter::baseAmountExpression();

            $monthIncome = bcadd((string) (Transaction::query()
                ->where('type', 'income')
                ->whereBetween('transaction_date', [$start, $end])
                ->selectRaw("COALESCE(SUM({$baseExpression}), 0) as total_base")
                ->value('total_base') ?? '0'), '0', 4);

            $monthExpense = bcadd((string) (Transaction::query()
                ->where('type', 'expense')
                ->whereBetween('transaction_date', [$start, $end])
                ->selectRaw("COALESCE(SUM({$baseExpression}), 0) as total_base")
                ->value('total_base') ?? '0'), '0', 4);

            $income[] = $monthIncome;
            $expense[] = $monthExpense;
            $net[] = bcsub($monthIncome, $monthExpense, 4);
        }

        return compact('labels', 'income', 'expense', 'net');
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function buildCurrencyBreakdown(string $baseRate): array
    {
        return Account::query()
            ->with('currency')
            ->get()
            ->groupBy('currency_id')
            ->map(function (Collection $accounts) use ($baseRate): array {
                $currency = $accounts->first()?->currency;
                $nativeTotal = $accounts->reduce(
                    fn (string $carry, Account $account): string => bcadd($carry, (string) $account->current_balance, 4),
                    '0.0000'
                );
                $rate = (string) ($currency?->exchange_rate ?? '1.0000');
                $balanceBase = MoneyFormatter::convertToBase($nativeTotal, $rate);

                return [
                    'currency' => $currency,
                    'account_count' => $accounts->count(),
                    'balance_native' => $nativeTotal,
                    'balance_base' => $balanceBase,
                    'accounts' => $accounts,
                ];
            })
            ->values()
            ->all();
    }

    /**
     * @return Collection<int, Transaction>
     */
    private function recentTransactions(): Collection
    {
        return Transaction::query()
            ->with(['account.currency', 'category', 'currency', 'contact'])
            ->orderByDesc('transaction_date')
            ->orderByDesc('id')
            ->limit(8)
            ->get();
    }

    /**
     * @return array{labels: list<string>, values: list<string>}
     */
    private function typeDistribution(): array
    {
        $baseExpression = MoneyFormatter::baseAmountExpression();
        $income = bcadd((string) (Transaction::query()->where('type', 'income')->selectRaw("COALESCE(SUM({$baseExpression}), 0) as total_base")->value('total_base') ?? '0'), '0', 4);
        $expense = bcadd((string) (Transaction::query()->where('type', 'expense')->selectRaw("COALESCE(SUM({$baseExpression}), 0) as total_base")->value('total_base') ?? '0'), '0', 4);
        $transfer = bcadd((string) (Transaction::query()->where('type', 'transfer')->selectRaw("COALESCE(SUM({$baseExpression}), 0) as total_base")->value('total_base') ?? '0'), '0', 4);

        return [
            'labels' => ['Income', 'Expense', 'Transfers'],
            'values' => [$income, $expense, $transfer],
        ];
    }

    /**
     * @return array{
     *     labels: list<string>,
     *     values: list<string>,
     *     tooltips: list<array{native: string, base: string, is_base: bool}>
     * }
     */
    private function accountBalanceChart(): array
    {
        $baseCurrency = MoneyFormatter::baseCurrency();
        $baseCurrencyId = $baseCurrency?->id;

        $rows = Account::query()
            ->with('currency')
            ->get()
            ->map(function (Account $account) use ($baseCurrency, $baseCurrencyId): array {
                $native = (string) $account->current_balance;
                $base = MoneyFormatter::convertToBase(
                    $native,
                    (string) ($account->currency?->exchange_rate ?? '1.0000'),
                );
                $baseRounded = bcadd($base, '0', 2);
                $isBase = $baseCurrencyId !== null && (int) $account->currency_id === (int) $baseCurrencyId;

                return [
                    'label' => $account->account_title,
                    'value' => $baseRounded,
                    'tooltip' => [
                        'native' => MoneyFormatter::format($native, $account->currency),
                        'base' => MoneyFormatter::format($baseRounded, $baseCurrency),
                        'is_base' => $isBase,
                    ],
                ];
            })
            ->sortByDesc(fn (array $row): float => (float) $row['value'])
            ->values();

        return [
            'labels' => $rows->pluck('label')->all(),
            'values' => $rows->pluck('value')->all(),
            'tooltips' => $rows->pluck('tooltip')->all(),
        ];
    }
}
