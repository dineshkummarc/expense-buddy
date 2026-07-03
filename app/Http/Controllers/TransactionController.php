<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\DTOs\TransactionData;
use App\DTOs\TransferData;
use App\Exceptions\InsufficientBalanceException;
use App\Http\Requests\StoreTransactionRequest;
use App\Http\Requests\StoreTransferRequest;
use App\Http\Requests\UpdateTransactionRequest;
use App\Models\Currency;
use App\Models\PaymentMethod;
use App\Support\TransactionType;
use App\Models\TransactionCategory;
use App\Repositories\Contracts\AccountRepositoryInterface;
use App\Repositories\Contracts\ContactRepositoryInterface;
use App\Repositories\Contracts\TransactionRepositoryInterface;
use App\Services\TransactionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use InvalidArgumentException;
use Throwable;

class TransactionController extends Controller
{
    public function __construct(
        private readonly TransactionService $transactionService,
        private readonly TransactionRepositoryInterface $transactionRepository,
        private readonly AccountRepositoryInterface $accountRepository,
        private readonly ContactRepositoryInterface $contactRepository,
    ) {
    }

    public function index(Request $request): View
    {
        $transactions = $this->transactionRepository->paginateFiltered(
            $request->only(['type', 'category_id', 'account_id', 'contact_id', 'date_from', 'date_to'])
        );

        return view('transactions.index', [
            'transactions' => $transactions,
            'accounts' => $this->accountRepository->active(),
            'categories' => TransactionCategory::query()->where('status', 'active')->orderBy('name')->get(),
            'contacts' => $this->contactRepository->allActive(),
            'filters' => $request->only(['type', 'category_id', 'account_id', 'contact_id', 'date_from', 'date_to']),
        ]);
    }

    public function create(): View
    {
        return view('transactions.create', $this->formData());
    }

    public function createTransfer(): View
    {
        return view('transfers.create', $this->formData());
    }

    public function store(StoreTransactionRequest $request): RedirectResponse
    {
        try {
            $data = $request->validated();

            if (TransactionType::isLending($data['type'])) {
                $paymentMethodId = PaymentMethod::query()->where('status', 'active')->orderBy('id')->value('id');

                if ($paymentMethodId === null) {
                    return back()->withInput()->withErrors(['form' => 'No active payment method is available for lending entries.']);
                }

                $data['payment_method_id'] = $paymentMethodId;
            }

            $dto = TransactionData::fromArray($data);

            if ($dto->type === 'income') {
                $this->transactionService->createIncome($dto);
            } elseif (TransactionType::isLending($dto->type)) {
                $this->transactionService->createLending($dto);
            } else {
                $this->transactionService->createExpense($dto);
            }

            return redirect()
                ->route('transactions.index')
                ->with('success', 'Transaction recorded successfully.');
        } catch (InsufficientBalanceException $exception) {
            return back()->withInput()->withErrors(['amount' => $exception->getMessage()]);
        } catch (InvalidArgumentException $exception) {
            return back()->withInput()->withErrors(['form' => $exception->getMessage()]);
        }
    }

    public function storeTransfer(StoreTransferRequest $request): RedirectResponse
    {
        try {
            $this->transactionService->createTransfer(
                TransferData::fromArray($request->validated())
            );

            return redirect()
                ->route('transactions.index')
                ->with('success', 'Transfer completed successfully.');
        } catch (InsufficientBalanceException $exception) {
            return back()->withInput()->withErrors(['amount' => $exception->getMessage()]);
        } catch (InvalidArgumentException $exception) {
            return back()->withInput()->withErrors(['form' => $exception->getMessage()]);
        }
    }

    public function update(UpdateTransactionRequest $request, int $id): RedirectResponse
    {
        try {
            $data = $request->validated();

            if (TransactionType::isLending($data['type'])) {
                $paymentMethodId = PaymentMethod::query()->where('status', 'active')->orderBy('id')->value('id');

                if ($paymentMethodId === null) {
                    return back()->withInput()->withErrors(['form' => 'No active payment method is available for lending entries.']);
                }

                $data['payment_method_id'] = $paymentMethodId;
            }

            $this->transactionService->update($id, TransactionData::fromArray($data));

            return redirect()
                ->route('transactions.index')
                ->with('success', 'Transaction updated successfully.');
        } catch (InsufficientBalanceException $exception) {
            return back()->withInput()->withErrors(['amount' => $exception->getMessage()]);
        } catch (InvalidArgumentException $exception) {
            return back()->withInput()->withErrors(['form' => $exception->getMessage()]);
        }
    }

    public function destroy(int $id): RedirectResponse
    {
        try {
            $deleted = $this->transactionService->delete($id);

            if (! $deleted) {
                return back()->withErrors(['form' => 'Transaction not found.']);
            }

            return redirect()
                ->route('transactions.index')
                ->with('success', 'Transaction deleted successfully.');
        } catch (Throwable $exception) {
            return back()->withErrors(['form' => $exception->getMessage()]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function formData(): array
    {
        return [
            'accounts' => $this->accountRepository->active(),
            'currencies' => Currency::query()->orderBy('name')->get(),
            'categories' => TransactionCategory::active()->orderBy('name')->get(),
            'paymentMethods' => PaymentMethod::query()->where('status', 'active')->orderBy('name')->get(),
            'contacts' => $this->contactRepository->allActive(),
        ];
    }
}
