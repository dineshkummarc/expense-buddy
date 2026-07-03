import './bootstrap';
import Chart from 'chart.js/auto';
import { initSearchSelects } from './searchSelect';
import { initConfirmModal } from './confirmModal';
import './copyButton';
import './formModal';
import { initTransactionForm } from './transactionForm';
import { initPwaInstall, registerServiceWorker } from './pwaInstall';
import { initBalanceTrendPanels } from './trendPeriodFilter';

registerServiceWorker();
initPwaInstall();

window.Chart = Chart;
window.initSearchSelects = initSearchSelects;

const root = document.documentElement;
const stored = localStorage.getItem('theme');

if (stored === 'dark' || (!stored && window.matchMedia('(prefers-color-scheme: dark)').matches)) {
    root.classList.add('dark');
}

document.getElementById('theme-toggle')?.addEventListener('click', () => {
    root.classList.toggle('dark');
    localStorage.setItem('theme', root.classList.contains('dark') ? 'dark' : 'light');
    document.dispatchEvent(new CustomEvent('theme-changed'));
});

document.getElementById('mobile-menu-toggle')?.addEventListener('click', () => {
    document.getElementById('mobile-sidebar')?.classList.remove('-translate-x-full');
    document.getElementById('mobile-sidebar-backdrop')?.classList.remove('hidden');
});

document.getElementById('mobile-sidebar-backdrop')?.addEventListener('click', () => {
    document.getElementById('mobile-sidebar')?.classList.add('-translate-x-full');
    document.getElementById('mobile-sidebar-backdrop')?.classList.add('hidden');
});

const profileMenu = document.getElementById('profile-menu');
const profileMenuToggle = document.getElementById('profile-menu-toggle');
const profileMenuPanel = document.getElementById('profile-menu-panel');

function closeProfileMenu() {
    profileMenuPanel?.classList.add('hidden');
    profileMenuToggle?.setAttribute('aria-expanded', 'false');
}

profileMenuToggle?.addEventListener('click', (event) => {
    event.stopPropagation();
    const isOpen = !profileMenuPanel?.classList.contains('hidden');
    if (isOpen) {
        closeProfileMenu();
    } else {
        profileMenuPanel?.classList.remove('hidden');
        profileMenuToggle?.setAttribute('aria-expanded', 'true');
    }
});

document.addEventListener('click', (event) => {
    if (profileMenu && !profileMenu.contains(event.target)) {
        closeProfileMenu();
    }
});

document.addEventListener('keydown', (event) => {
    if (event.key === 'Escape') {
        closeProfileMenu();
    }
});

export function chartColors() {
    const isDark = document.documentElement.classList.contains('dark');

    return {
        grid: isDark ? 'rgba(148, 163, 184, 0.15)' : 'rgba(148, 163, 184, 0.25)',
        text: isDark ? '#94a3b8' : '#64748b',
        income: '#10b981',
        expense: '#f43f5e',
        transfer: '#8b5cf6',
        palette: ['#14b8a6', '#3b82f6', '#f59e0b', '#ec4899', '#8b5cf6', '#06b6d4', '#84cc16', '#f97316'],
    };
}

export function baseChartOptions(extra = {}) {
    const colors = chartColors();

    return {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: {
                labels: { color: colors.text, usePointStyle: true, padding: 16 },
            },
        },
        scales: {
            x: {
                grid: { color: colors.grid },
                ticks: { color: colors.text },
            },
            y: {
                beginAtZero: true,
                grid: { color: colors.grid },
                ticks: { color: colors.text },
            },
        },
        ...extra,
    };
}

window.LedgerCharts = { chartColors, baseChartOptions };
window.flushChartQueue?.();

function bindAccountCurrency(accountSelectId, currencySelectId, rateInputId) {
    const accountSelect = document.getElementById(accountSelectId);
    const currencySelect = document.getElementById(currencySelectId);
    const rateInput = document.getElementById(rateInputId);

    if (!accountSelect || !currencySelect || !rateInput) {
        return;
    }

    const syncFromAccount = () => {
        const option = accountSelect.selectedOptions[0];
        const currencyId = option?.dataset.currencyId;

        if (currencyId) {
            currencySelect.value = currencyId;
            currencySelect.dispatchEvent(new Event('change', { bubbles: true }));
            const rate = currencySelect.selectedOptions[0]?.dataset.rate;
            if (rate) {
                rateInput.value = rate;
            }
        }
    };

    const syncFromCurrency = () => {
        const rate = currencySelect.selectedOptions[0]?.dataset.rate;
        if (rate) {
            rateInput.value = rate;
        }
    };

    accountSelect.addEventListener('change', syncFromAccount);
    currencySelect.addEventListener('change', syncFromCurrency);
}

bindAccountCurrency('txn-account', 'txn-currency', 'txn-rate');
bindAccountCurrency('transfer-source', 'transfer-currency', 'transfer-rate');

initBalanceTrendPanels();
