<?php

namespace App\Http\Controllers\Finance;

use App\Http\Controllers\Controller;
use App\Http\Requests\Finance\StoreRecurringTransactionRequest;
use App\Models\Account;
use App\Models\Category;
use App\Models\Person;
use App\Models\RecurringTransaction;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Redirect;
use Inertia\Inertia;
use Inertia\Response;

class RecurringTransactionController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Finance/Recurring/Index', [
            'recurringTransactions' => RecurringTransaction::with(['account', 'person', 'category'])
                ->orderBy('next_run_date')
                ->get(),
            'accounts' => Account::whereNull('archived_at')->orderBy('name')->get(),
            'people' => Person::orderBy('name')->get(),
            'categories' => Category::orderBy('name')->get(),
        ]);
    }

    public function store(StoreRecurringTransactionRequest $request): RedirectResponse
    {
        $data = $request->validated();

        $request->user()->recurringTransactions()->create([
            ...$data,
            'next_run_date' => $data['start_date'],
            'is_active' => true,
        ]);

        return Redirect::route('finance.recurring.index');
    }

    public function update(StoreRecurringTransactionRequest $request, RecurringTransaction $recurring): RedirectResponse
    {
        $recurring->update($request->validated());

        return Redirect::route('finance.recurring.index');
    }

    public function destroy(RecurringTransaction $recurring): RedirectResponse
    {
        $recurring->delete();

        return Redirect::route('finance.recurring.index');
    }
}
