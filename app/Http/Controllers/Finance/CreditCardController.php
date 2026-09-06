<?php

namespace App\Http\Controllers\Finance;

use App\Http\Controllers\Controller;
use App\Http\Requests\Finance\StoreCreditCardRequest;
use App\Models\CreditCard;
use App\Models\Institution;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redirect;
use Inertia\Inertia;
use Inertia\Response;

class CreditCardController extends Controller
{
    public function index(Request $request): Response
    {
        $month = $request->filled('month')
            ? \Illuminate\Support\Carbon::parse($request->string('month').'-01')
            : \Illuminate\Support\Carbon::now()->startOfMonth();

        $month = $month->min(\App\Models\Transaction::recurringCapMonth());

        $cards = CreditCard::with(['institution', 'paymentAccount'])
            ->where('user_id', $request->user()->id)
            ->orderBy('name')
            ->get()
            ->map(function (CreditCard $card) use ($month) {
                $invoice = $card->invoiceForMonth($month);

                return [
                    'id' => $card->id,
                    'name' => $card->name,
                    'institution' => $card->institution,
                    'credit_limit' => $card->credit_limit,
                    'closing_day' => $card->closing_day,
                    'due_day' => $card->due_day,
                    'payment_account_id' => $card->payment_account_id,
                    'open_invoice_total' => $invoice->total(),
                    'available_limit' => $card->availableLimit(),
                    'current_invoice_id' => $invoice->id,
                    'due_date' => $invoice->due_date,
                ];
            });

        return Inertia::render('Finance/CreditCards/Index', [
            'cards' => $cards,
            'institutions' => Institution::orderBy('name')->get(),
            'month' => $month->format('Y-m'),
        ]);
    }

    public function store(StoreCreditCardRequest $request): RedirectResponse
    {
        $request->user()->creditCards()->create($request->validated());

        return Redirect::route('finance.credit-cards.index');
    }

    public function update(StoreCreditCardRequest $request, CreditCard $creditCard): RedirectResponse
    {
        $creditCard->update($request->validated());

        return Redirect::route('finance.credit-cards.index');
    }
}
