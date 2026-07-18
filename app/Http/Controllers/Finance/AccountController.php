<?php

namespace App\Http\Controllers\Finance;

use App\Enums\AccountType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Finance\StoreAccountRequest;
use App\Models\Account;
use App\Models\Category;
use App\Models\Institution;
use App\Models\Person;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Redirect;
use Inertia\Inertia;
use Inertia\Response;

class AccountController extends Controller
{
    public function index(): Response
    {
        $accounts = Account::with(['person', 'institution', 'creditCard'])
            ->whereNull('archived_at')
            ->orderBy('name')
            ->get()
            ->map(fn (Account $account) => $this->present($account));

        return Inertia::render('Finance/Accounts/Index', [
            'accounts' => $accounts,
            'people' => Person::orderBy('name')->get(),
            'institutions' => Institution::orderBy('name')->get(),
            'accountTypes' => collect(AccountType::cases())->map(fn ($t) => ['value' => $t->value, 'label' => $t->label()]),
        ]);
    }

    public function store(StoreAccountRequest $request): RedirectResponse
    {
        $data = $request->validated();

        $account = $request->user()->accounts()->create([
            'person_id' => $data['person_id'],
            'institution_id' => $data['institution_id'] ?? null,
            'name' => $data['name'],
            'type' => $data['type'],
            'initial_balance' => $data['initial_balance'],
        ]);

        if ($data['type'] === AccountType::CreditCard->value) {
            $account->creditCard()->create([
                'payment_account_id' => $data['payment_account_id'] ?? null,
                'credit_limit' => $data['credit_limit'],
                'closing_day' => $data['closing_day'],
                'due_day' => $data['due_day'],
            ]);
        }

        return Redirect::route('finance.accounts.index');
    }

    public function show(Account $account): Response
    {
        $account->load(['person', 'institution', 'creditCard']);

        $transactions = $account->transactions()
            ->with(['category', 'person'])
            ->orderByDesc('date')
            ->paginate(25);

        $invoices = $account->type === AccountType::CreditCard
            ? $account->creditCard->invoices()->orderByDesc('reference_month')->withCount('transactions')->get()
            : [];

        return Inertia::render('Finance/Accounts/Show', [
            'account' => $this->present($account),
            'transactions' => $transactions,
            'invoices' => $invoices,
            'categories' => Category::orderBy('name')->get(),
            'people' => Person::orderBy('name')->get(),
        ]);
    }

    public function update(StoreAccountRequest $request, Account $account): RedirectResponse
    {
        $data = $request->validated();

        $account->update([
            'person_id' => $data['person_id'],
            'institution_id' => $data['institution_id'] ?? null,
            'name' => $data['name'],
            'type' => $data['type'],
            'initial_balance' => $data['initial_balance'],
        ]);

        if ($data['type'] === AccountType::CreditCard->value) {
            $account->creditCard()->updateOrCreate([], [
                'payment_account_id' => $data['payment_account_id'] ?? null,
                'credit_limit' => $data['credit_limit'],
                'closing_day' => $data['closing_day'],
                'due_day' => $data['due_day'],
            ]);
        }

        return Redirect::back();
    }

    public function destroy(Account $account): RedirectResponse
    {
        $account->update(['archived_at' => now()]);

        return Redirect::route('finance.accounts.index');
    }

    private function present(Account $account): array
    {
        return [
            'id' => $account->id,
            'name' => $account->name,
            'type' => $account->type->value,
            'type_label' => $account->type->label(),
            'person' => $account->person,
            'institution' => $account->institution,
            'initial_balance' => $account->initial_balance,
            'balance' => $account->type !== AccountType::CreditCard ? $account->balance() : null,
            'credit_card' => $account->creditCard,
            'open_invoice_total' => $account->type === AccountType::CreditCard ? $account->openInvoiceTotal() : null,
            'available_limit' => $account->type === AccountType::CreditCard ? $account->availableLimit() : null,
        ];
    }
}
