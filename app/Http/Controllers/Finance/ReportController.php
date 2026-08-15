<?php

namespace App\Http\Controllers\Finance;

use App\Enums\AccountType;
use App\Enums\TransactionType;
use App\Http\Controllers\Controller;
use App\Models\Account;
use App\Models\Category;
use App\Models\Person;
use App\Models\Transaction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class ReportController extends Controller
{
    public function index(Request $request): Response
    {
        $from = $request->filled('from') ? $request->date('from') : now()->startOfMonth()->subMonths(5);
        $to = $request->filled('to') ? $request->date('to') : now()->endOfMonth();
        $view = in_array($request->input('view'), ['overview', 'credit_card', 'investments'], true)
            ? $request->input('view')
            : 'overview';

        // A card installment keeps its original purchase date on every row, but
        // it belongs to whichever invoice (reference_month) it was billed on —
        // that's what should drive month filtering/grouping in the card view,
        // not the shared purchase date.
        $baseQuery = fn () => Transaction::query()
            ->where('user_id', Auth::id())
            ->when(
                $view === 'credit_card',
                fn ($q) => $q->whereHas(
                    'creditCardInvoice',
                    fn ($invoiceQuery) => $invoiceQuery->whereBetween('reference_month', [$from->copy()->startOfMonth(), $to->copy()->endOfMonth()])
                ),
                fn ($q) => $q->whereBetween('date', [$from, $to])
            )
            ->when(
                $request->filled('person_id'),
                fn ($q) => $q->whereHas('account', fn ($accountQuery) => $accountQuery->where('person_id', $request->integer('person_id')))
            )
            ->when($request->filled('category_id'), fn ($q) => $q->where('category_id', $request->integer('category_id')))
            ->when($view === 'credit_card', fn ($q) => $q->whereNotNull('credit_card_invoice_id'))
            ->when(
                $view === 'investments',
                fn ($q) => $q->whereHas('account', fn ($accountQuery) => $accountQuery->where('type', AccountType::Investment))
            )
            ->when(
                $view === 'overview',
                fn ($q) => $q->whereHas(
                    'account',
                    fn ($accountQuery) => $accountQuery->whereNotIn('type', [AccountType::CreditCard, AccountType::Investment])
                )->whereNull('transfer_id')->whereNull('credit_card_invoice_id')
            );

        $monthKey = fn (Transaction $t) => $view === 'credit_card'
            ? $t->creditCardInvoice->reference_month->format('Y-m')
            : $t->date->format('Y-m');

        $totalIncome = (clone $baseQuery())->where('type', TransactionType::Income)->sum('amount');
        $totalExpense = (clone $baseQuery())->where('type', TransactionType::Expense)->sum('amount');

        $monthly = (clone $baseQuery())
            ->with('creditCardInvoice')
            ->get(['id', 'date', 'type', 'amount', 'credit_card_invoice_id'])
            ->groupBy($monthKey)
            ->map(fn ($transactions, $month) => [
                'month' => $month,
                'income' => (string) $transactions->where('type', TransactionType::Income)->sum('amount'),
                'expense' => (string) $transactions->where('type', TransactionType::Expense)->sum('amount'),
            ])
            ->sortBy('month')
            ->values();

        $transactionsByMonth = (clone $baseQuery())
            ->with(['account', 'category', 'creditCardInvoice'])
            ->orderByDesc('date')
            ->orderByDesc('id')
            ->get()
            ->groupBy($monthKey)
            ->map(fn ($transactions) => $transactions->map(fn (Transaction $t) => [
                'id' => $t->id,
                'date' => $t->date->toDateString(),
                'description' => $t->description,
                'type' => $t->type->value,
                'amount' => (string) $t->amount,
                'account' => $t->account->name,
                'category' => $t->category?->name,
            ]))
            ->toArray();

        $categoryBreakdown = (clone $baseQuery())
            ->where('type', TransactionType::Expense)
            ->with('category')
            ->get()
            ->groupBy('category_id')
            ->map(function ($transactions) {
                $category = $transactions->first()->category;

                return [
                    'category' => $category ? ['id' => $category->id, 'name' => $category->name, 'color' => $category->color] : null,
                    'amount' => (string) $transactions->sum('amount'),
                ];
            })
            ->sortByDesc(fn ($row) => (float) $row['amount'])
            ->values();

        $accountsSummary = $view === 'overview' ? [] : (clone $baseQuery())
            ->with('account.institution')
            ->get()
            ->groupBy('account_id')
            ->map(function ($transactions) use ($view) {
                $account = $transactions->first()->account;

                return [
                    'account' => $account,
                    'income' => (string) $transactions->where('type', TransactionType::Income)->sum('amount'),
                    'expense' => (string) $transactions->where('type', TransactionType::Expense)->sum('amount'),
                    'balance' => $view === 'investments' ? $account->balance() : null,
                ];
            })
            ->sortBy(fn ($group) => $group['account']->name)
            ->values();

        return Inertia::render('Finance/Reports/Index', [
            'totals' => [
                'income' => (string) $totalIncome,
                'expense' => (string) $totalExpense,
            ],
            'monthly' => $monthly,
            'transactionsByMonth' => $transactionsByMonth,
            'categoryBreakdown' => $categoryBreakdown,
            'accountsSummary' => $accountsSummary,
            'people' => Person::orderBy('name')->get(),
            'categories' => Category::orderBy('name')->get(),
            'filters' => [
                'person_id' => $request->input('person_id', ''),
                'category_id' => $request->input('category_id', ''),
                'from' => $from->toDateString(),
                'to' => $to->toDateString(),
                'view' => $view,
            ],
        ]);
    }
}
