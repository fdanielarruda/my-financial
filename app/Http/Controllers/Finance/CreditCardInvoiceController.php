<?php

namespace App\Http\Controllers\Finance;

use App\Http\Controllers\Controller;
use App\Models\Account;
use App\Models\CreditCardInvoice;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

class CreditCardInvoiceController extends Controller
{
    public function show(Request $request, CreditCardInvoice $invoice): Response
    {
        $this->authorizeInvoice($request, $invoice);

        $invoice->load(['creditCard.account', 'creditCard.paymentAccount']);

        return Inertia::render('Finance/CreditCards/Invoice', [
            'invoice' => [
                ...$invoice->toArray(),
                'total' => $invoice->total(),
            ],
            'transactions' => $invoice->transactions()->with(['category', 'person'])->orderBy('date')->get(),
            'accounts' => Account::whereNull('archived_at')->orderBy('name')->get(),
        ]);
    }

    public function pay(Request $request, CreditCardInvoice $invoice): RedirectResponse
    {
        $this->authorizeInvoice($request, $invoice);

        $data = $request->validate([
            'payment_account_id' => ['required', Rule::exists('accounts', 'id')->where('user_id', $request->user()->id)],
            'date' => ['nullable', 'date'],
        ]);

        $invoice->pay(
            Account::findOrFail($data['payment_account_id']),
            isset($data['date']) ? Carbon::parse($data['date']) : null
        );

        return Redirect::back();
    }

    private function authorizeInvoice(Request $request, CreditCardInvoice $invoice): void
    {
        abort_if(
            $invoice->creditCard->account->user_id !== $request->user()->id,
            HttpResponse::HTTP_FORBIDDEN
        );
    }
}
