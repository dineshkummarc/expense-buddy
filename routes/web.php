<?php

declare(strict_types=1);

use App\Http\Controllers\AdminController;
use App\Http\Controllers\AccountController;
use App\Http\Controllers\AccountSecurityController;
use App\Http\Controllers\Auth\ForgotPasswordController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\ResetPasswordController;
use App\Http\Controllers\Auth\TwoFactorChallengeController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\ContactController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\InvoiceController;
use App\Http\Controllers\PaymentMethodController;
use App\Http\Controllers\PwaController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\TransactionController;
use Illuminate\Support\Facades\Route;

Route::get('/manifest.webmanifest', [PwaController::class, 'manifest'])->name('pwa.manifest');
Route::get('/favicon.ico', [PwaController::class, 'favicon'])->name('pwa.favicon');

Route::middleware('guest')->group(function (): void {
    Route::get('/login', [LoginController::class, 'create'])->name('login');
    Route::post('/login', [LoginController::class, 'store']);

    Route::get('/forgot-password', [ForgotPasswordController::class, 'create'])->name('password.request');
    Route::post('/forgot-password', [ForgotPasswordController::class, 'store'])->name('password.email');

    Route::get('/reset-password/{token}', [ResetPasswordController::class, 'create'])->name('password.reset');
    Route::post('/reset-password', [ResetPasswordController::class, 'store'])->name('password.update');

    Route::get('/two-factor-challenge', [TwoFactorChallengeController::class, 'create'])->name('two-factor.login');
    Route::post('/two-factor-challenge', [TwoFactorChallengeController::class, 'store'])->name('two-factor.login.store');
});

Route::post('/logout', [LoginController::class, 'destroy'])
    ->middleware('auth')
    ->name('logout');

Route::middleware(['auth', 'menu.permission'])->group(function (): void {
    Route::get('/', [DashboardController::class, 'index'])->name('dashboard');

    Route::get('/account/security', [AccountSecurityController::class, 'index'])->name('account.security');
    Route::post('/account/security/enable', [AccountSecurityController::class, 'enable'])->name('account.security.enable');
    Route::post('/account/security/confirm', [AccountSecurityController::class, 'confirm'])->name('account.security.confirm');
    Route::post('/account/security/disable', [AccountSecurityController::class, 'disable'])->name('account.security.disable');
    Route::delete('/account/security/sessions/{session}', [AccountSecurityController::class, 'destroySession'])->name('account.security.sessions.destroy');
    Route::post('/account/security/sessions/logout-others', [AccountSecurityController::class, 'logoutOtherSessions'])->name('account.security.sessions.logout-others');

    Route::get('/transactions', [TransactionController::class, 'index'])->name('transactions.index');
    Route::get('/transactions/create', [TransactionController::class, 'create'])->name('transactions.create');
    Route::post('/transactions', [TransactionController::class, 'store'])->name('transactions.store');
    Route::get('/transfers/create', [TransactionController::class, 'createTransfer'])->name('transfers.create');
    Route::post('/transfers', [TransactionController::class, 'storeTransfer'])->name('transfers.store');
    Route::put('/transactions/{id}', [TransactionController::class, 'update'])->name('transactions.update');
    Route::delete('/transactions/{id}', [TransactionController::class, 'destroy'])->name('transactions.destroy');

    Route::prefix('transactions/{transactionId}/invoice')->name('transactions.invoice.')->group(function (): void {
        Route::get('/', [InvoiceController::class, 'show'])->name('show');
        Route::get('/pdf', [InvoiceController::class, 'download'])->name('pdf');
        Route::post('/share', [InvoiceController::class, 'share'])->name('share');
        Route::post('/revoke', [InvoiceController::class, 'revoke'])->name('revoke');
    });

    Route::get('/accounts', [AccountController::class, 'index'])->name('accounts.index');
    Route::post('/accounts', [AccountController::class, 'store'])->name('accounts.store');
    Route::put('/accounts/{id}', [AccountController::class, 'update'])->name('accounts.update');
    Route::post('/accounts/{id}/archive', [AccountController::class, 'archive'])->name('accounts.archive');
    Route::post('/accounts/{id}/restore', [AccountController::class, 'restore'])->name('accounts.restore');

    Route::get('/categories', [CategoryController::class, 'index'])->name('categories.index');
    Route::post('/categories', [CategoryController::class, 'store'])->name('categories.store');
    Route::put('/categories/{id}', [CategoryController::class, 'update'])->name('categories.update');
    Route::post('/categories/{id}/archive', [CategoryController::class, 'archive'])->name('categories.archive');
    Route::post('/categories/{id}/restore', [CategoryController::class, 'restore'])->name('categories.restore');

    Route::get('/payment-methods', [PaymentMethodController::class, 'index'])->name('payment-methods.index');
    Route::post('/payment-methods', [PaymentMethodController::class, 'store'])->name('payment-methods.store');
    Route::put('/payment-methods/{id}', [PaymentMethodController::class, 'update'])->name('payment-methods.update');
    Route::post('/payment-methods/{id}/archive', [PaymentMethodController::class, 'archive'])->name('payment-methods.archive');
    Route::post('/payment-methods/{id}/restore', [PaymentMethodController::class, 'restore'])->name('payment-methods.restore');

    Route::prefix('contacts')->name('contacts.')->group(function (): void {
        Route::get('/', [ContactController::class, 'index'])->name('index');
        Route::get('/create', [ContactController::class, 'create'])->name('create');
        Route::post('/', [ContactController::class, 'store'])->name('store');
        Route::get('/{id}', [ContactController::class, 'show'])->name('show');
        Route::get('/{id}/edit', [ContactController::class, 'edit'])->name('edit');
        Route::put('/{id}', [ContactController::class, 'update'])->name('update');
    });

    Route::prefix('lending')->name('lending.')->group(function (): void {
        Route::get('/', [ContactController::class, 'overview'])->name('overview');
        Route::get('/ledger', [ContactController::class, 'ledger'])->name('ledger');
        Route::get('/trend-chart', [ContactController::class, 'trendChart'])->name('trend-chart');
        Route::redirect('/people', '/contacts');
        Route::redirect('/people/create', '/contacts/create');
        Route::get('/people/{id}/edit', fn (int $id) => redirect()->route('contacts.edit', $id));
    });

    Route::prefix('reports')->name('reports.')->group(function (): void {
        Route::get('/income-vs-expense', [ReportController::class, 'incomeVsExpense'])->name('income-vs-expense');
        Route::get('/categorized', [ReportController::class, 'categorized'])->name('categorized');
        Route::get('/detailed', [ReportController::class, 'detailed'])->name('detailed');
        Route::redirect('/contact-ledger', '/lending/ledger');
    });

    Route::prefix('admin')->name('admin.')->group(function (): void {
        Route::get('/', function () {
            $route = \App\Support\MenuPermissionRegistry::firstAdminRouteFor(auth()->user());

            return redirect()->route($route ?? 'dashboard');
        });
        Route::get('/settings', [AdminController::class, 'settings'])->name('settings');
        Route::put('/settings', [AdminController::class, 'updateSettings'])->name('settings.update');
        Route::get('/updater', [\App\Http\Controllers\UpdaterController::class, 'index'])->name('updater');
        Route::post('/updater/run', [\App\Http\Controllers\UpdaterController::class, 'update'])->name('updater.run');
        Route::get('/error-insights', [AdminController::class, 'errorInsights'])->name('error-insights');
        Route::post('/error-insights/clear', [AdminController::class, 'clearErrorTracking'])->name('error-insights.clear');
        Route::get('/currencies', [AdminController::class, 'currencies'])->name('currencies');
        Route::post('/currencies', [AdminController::class, 'storeCurrency'])->name('currencies.store');
        Route::put('/currencies/{id}', [AdminController::class, 'updateCurrency'])->name('currencies.update');
        Route::delete('/currencies/{id}', [AdminController::class, 'destroyCurrency'])->name('currencies.destroy');
        Route::get('/users', [AdminController::class, 'users'])->name('users');
        Route::post('/users', [AdminController::class, 'storeUser'])->name('users.store');
        Route::put('/users/{id}', [AdminController::class, 'updateUser'])->name('users.update');
        Route::delete('/users/{id}', [AdminController::class, 'destroyUser'])->name('users.destroy');
        Route::get('/roles', [AdminController::class, 'roles'])->name('roles');
        Route::post('/roles', [AdminController::class, 'storeRole'])->name('roles.store');
        Route::put('/roles/{id}', [AdminController::class, 'updateRole'])->name('roles.update');
        Route::delete('/roles/{id}', [AdminController::class, 'destroyRole'])->name('roles.destroy');
        Route::get('/backup', [AdminController::class, 'backup'])->name('backup');
        Route::put('/backup', [AdminController::class, 'updateBackup'])->name('backup.update');
        Route::get('/backup/download', [AdminController::class, 'backupDatabase'])->name('backup.download');
        Route::post('/backup/run', [AdminController::class, 'runBackupEmail'])->name('backup.run');
    });
});

Route::get('/i/{token}', [InvoiceController::class, 'publicShow'])->name('invoices.public');
Route::get('/i/{token}/pdf', [InvoiceController::class, 'publicDownload'])->name('invoices.public.pdf');
