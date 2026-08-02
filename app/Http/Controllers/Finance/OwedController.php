<?php

namespace App\Http\Controllers\Finance;

use App\Enums\AccountType;
use App\Http\Controllers\Controller;
use App\Models\Account;
use App\Models\CreditCard;
use App\Models\Person;
use App\Support\Money;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;

class OwedController extends Controller
{
    public function index(Request $request): Response
    {
        $month = $request->filled('month') ? $request->input('month') : now()->format('Y-m');
        $monthStart = Carbon::parse($month.'-01')->startOfMonth();
        $personId = $request->integer('person_id') ?: null;

        $accounts = Account::with(['institution', 'person'])
            ->where('type', '!=', AccountType::CreditCard)
            ->whereNull('archived_at')
            ->when($personId, fn ($q) => $q->where('person_id', $personId))
            ->orderBy('name')
            ->get()
            ->map(fn (Account $account) => [
                'id' => $account->id,
                'name' => $account->name,
                'institution' => $account->institution,
                'person' => $account->person,
                'balance' => $account->balance(),
            ]);

        $cards = CreditCard::with('institution')
            ->where('user_id', $request->user()->id)
            ->orderBy('name')
            ->get()
            ->map(function (CreditCard $card) use ($monthStart, $personId) {
                $invoice = $card->invoices()->where('reference_month', $monthStart->toDateString())->first();

                $total = '0.00';
                if ($invoice) {
                    $items = $invoice->transactions()->when(
                        $personId,
                        fn ($q) => $q->whereHas('account', fn ($accountQuery) => $accountQuery->where('person_id', $personId))
                    );
                    $charges = (string) (clone $items)->where('reversed', false)->sum('amount');
                    $credits = (string) (clone $items)->where('reversed', true)->sum('amount');
                    $total = Money::sub($charges, $credits);
                }

                return [
                    'id' => $card->id,
                    'name' => $card->name,
                    'institution' => $card->institution,
                    'invoice_id' => $invoice?->id,
                    'due_date' => $invoice?->due_date,
                    'total' => $total,
                ];
            });

        return Inertia::render('Finance/Owed/Index', [
            'accounts' => $accounts,
            'cards' => $cards,
            'people' => Person::orderBy('name')->get(),
            'filters' => [
                'person_id' => $personId,
                'month' => $monthStart->format('Y-m'),
            ],
        ]);
    }
}
