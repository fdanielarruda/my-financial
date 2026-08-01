<?php

namespace App\Http\Controllers\Finance;

use App\Enums\TransactionType;
use App\Http\Controllers\Controller;
use App\Models\Account;
use App\Models\Person;
use App\Models\Transaction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class SummaryController extends Controller
{
    public function index(Request $request): Response
    {
        $from = $request->filled('from') ? $request->date('from') : now()->startOfMonth();
        $to = $request->filled('to') ? $request->date('to') : now()->endOfMonth();

        $baseQuery = fn () => Transaction::query()
            ->where('user_id', Auth::id())
            ->whereBetween('date', [$from, $to])
            ->when($request->filled('account_id'), fn ($q) => $q->where('account_id', $request->integer('account_id')))
            ->when($request->filled('person_id'), fn ($q) => $q->where('person_id', $request->integer('person_id')));

        $totalIncome = (clone $baseQuery())->where('type', TransactionType::Income)->sum('amount');
        $totalExpense = (clone $baseQuery())->where('type', TransactionType::Expense)->sum('amount');

        $groups = (clone $baseQuery())
            ->with(['account.institution', 'account.person'])
            ->get()
            ->groupBy('account_id')
            ->map(function ($transactions) {
                $account = $transactions->first()->account;

                return [
                    'account' => $account,
                    'count' => $transactions->count(),
                    'income' => $transactions->where('type', TransactionType::Income)->sum('amount'),
                    'expense' => $transactions->where('type', TransactionType::Expense)->sum('amount'),
                    'balance' => $account->balance(),
                ];
            })
            ->sortBy(fn ($group) => $group['account']->name)
            ->values();

        return Inertia::render('Finance/Summary/Index', [
            'totals' => [
                'income' => (string) $totalIncome,
                'expense' => (string) $totalExpense,
            ],
            'groups' => $groups,
            'accounts' => Account::whereNull('archived_at')->orderBy('name')->get(),
            'people' => Person::orderBy('name')->get(),
            'filters' => [
                'account_id' => $request->input('account_id', ''),
                'person_id' => $request->input('person_id', ''),
                'from' => $from->toDateString(),
                'to' => $to->toDateString(),
            ],
        ]);
    }
}
