<?php

namespace App\Http\Controllers\Finance;

use App\Http\Controllers\Controller;
use App\Http\Requests\Finance\StoreTransactionRequest;
use App\Models\Account;
use App\Models\Category;
use App\Models\Person;
use App\Models\Transaction;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Redirect;
use Inertia\Inertia;
use Inertia\Response;

class TransactionController extends Controller
{
    public function quick(): Response
    {
        return Inertia::render('Finance/Transactions/Quick', [
            'accounts' => Account::with(['person', 'institution'])
                ->whereNull('archived_at')
                ->where('type', '!=', \App\Enums\AccountType::CreditCard)
                ->orderBy('name')
                ->get()
                ->map(fn (Account $account) => [
                    'id' => $account->id,
                    'name' => $account->name,
                    'type' => $account->type->value,
                    'person' => $account->person,
                    'institution' => $account->institution,
                    'balance' => $account->type !== \App\Enums\AccountType::CreditCard ? $account->balance() : null,
                ]),
            'people' => Person::orderBy('name')->get(),
            'categories' => Category::orderBy('name')->get(),
        ]);
    }

    public function recentByBank(Request $request): \Illuminate\Http\JsonResponse
    {
        $institutionId = $request->integer('institution_id') ?: null;

        $transactions = Transaction::query()
            ->where('user_id', $request->user()->id)
            ->whereHas('account', fn ($q) => $institutionId
                ? $q->where('institution_id', $institutionId)
                : $q->whereNull('institution_id'))
            ->with(['account', 'category'])
            ->orderByDesc('date')
            ->orderByDesc('id')
            ->limit(10)
            ->get();

        return response()->json(['transactions' => $transactions]);
    }

    public function index(Request $request): Response
    {
        $transactions = Transaction::query()
            ->with(['account.institution', 'category', 'person'])
            ->when($request->filled('account_id'), fn ($q) => $q->where('account_id', $request->integer('account_id')))
            ->when($request->filled('person_id'), fn ($q) => $q->where('person_id', $request->integer('person_id')))
            ->when($request->filled('category_id'), fn ($q) => $q->where('category_id', $request->integer('category_id')))
            ->when($request->filled('from'), fn ($q) => $q->whereDate('date', '>=', $request->date('from')))
            ->when($request->filled('to'), fn ($q) => $q->whereDate('date', '<=', $request->date('to')))
            ->orderByDesc('date')
            ->orderByDesc('id')
            ->paginate(25)
            ->withQueryString();

        return Inertia::render('Finance/Transactions/Index', [
            'transactions' => $transactions,
            'accounts' => Account::whereNull('archived_at')->orderBy('name')->get(),
            'people' => Person::orderBy('name')->get(),
            'categories' => Category::orderBy('name')->get(),
            'filters' => $request->only(['account_id', 'person_id', 'category_id', 'from', 'to']),
        ]);
    }

    public function store(StoreTransactionRequest $request): RedirectResponse
    {
        $data = $request->validated();

        Transaction::createPurchase([
            'user_id' => $request->user()->id,
            'account_id' => $data['account_id'],
            'person_id' => $data['person_id'],
            'category_id' => $data['category_id'] ?? null,
            'type' => $data['type'],
            'description' => $data['description'],
            'amount' => $data['amount'],
            'date' => $data['date'],
        ], installments: $data['installments'] ?? 1);

        return Redirect::back();
    }

    public function update(StoreTransactionRequest $request, Transaction $transaction): RedirectResponse
    {
        $data = $request->validated();

        $transaction->update([
            'account_id' => $data['account_id'],
            'person_id' => $data['person_id'],
            'category_id' => $data['category_id'] ?? null,
            'type' => $data['type'],
            'description' => $data['description'],
            'amount' => $data['amount'],
            'date' => $data['date'],
        ]);

        return Redirect::back();
    }

    public function destroy(Transaction $transaction): RedirectResponse
    {
        $transaction->delete();

        return Redirect::back();
    }
}
