<?php

namespace App\Http\Controllers\Finance;

use App\Enums\AccountType;
use App\Enums\TransactionType;
use App\Http\Controllers\Controller;
use App\Models\Account;
use App\Models\Category;
use App\Models\CreditCard;
use App\Models\CreditCardInvoice;
use App\Models\Transaction;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
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

        abort_if($invoice->reference_month->gt(Transaction::recurringCapMonth()), HttpResponse::HTTP_FORBIDDEN);

        $invoice->load(['creditCard.institution', 'creditCard.paymentAccount']);

        $creditCard = $invoice->creditCard;
        $prevInvoice = $creditCard->invoiceForMonth($invoice->reference_month->copy()->subMonthNoOverflow());
        $nextMonth = $invoice->reference_month->copy()->addMonthNoOverflow();
        $nextInvoice = $nextMonth->lte(Transaction::recurringCapMonth()) ? $creditCard->invoiceForMonth($nextMonth) : null;

        return Inertia::render('Finance/CreditCards/Invoice', [
            'invoice' => [
                ...$invoice->toArray(),
                'total' => $invoice->total(),
            ],
            'prevInvoiceId' => $prevInvoice->id,
            'nextInvoiceId' => $nextInvoice?->id,
            'transactions' => $invoice->transactions()
                ->with(['account.institution', 'category', 'person'])
                ->orderBy('date')
                ->orderBy('installment_number')
                ->orderBy('id')
                ->get(),
            'accounts' => Account::with(['institution', 'person'])
                ->whereNotIn('type', [AccountType::CreditCard, AccountType::Investment])
                ->where('institution_id', $creditCard->institution_id)
                ->whereNull('archived_at')
                ->orderBy('name')
                ->get(),
            'categories' => Category::orderBy('name')->get(),
        ]);
    }

    public function storePurchase(Request $request, CreditCard $creditCard): RedirectResponse
    {
        abort_if($creditCard->user_id !== $request->user()->id, HttpResponse::HTTP_FORBIDDEN);

        $data = $request->validate([
            'reference_invoice_id' => ['required', Rule::exists('credit_card_invoices', 'id')],
            'account_id' => ['required', Rule::exists('accounts', 'id')->where('user_id', $request->user()->id)],
            'person_id' => ['required', Rule::exists('people', 'id')->where('user_id', $request->user()->id)],
            'category_id' => ['nullable', Rule::exists('categories', 'id')->where('user_id', $request->user()->id)],
            'is_unknown' => ['nullable', 'boolean'],
            'description' => [Rule::requiredIf(! $request->boolean('is_unknown')), 'nullable', 'string', 'max:255'],
            'date' => ['required', 'date'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'mode' => ['required', Rule::in(['single', 'installments', 'recurring'])],
            'installment_number' => ['required_if:mode,installments', 'integer', 'min:1'],
            'installment_total' => ['required_if:mode,installments', 'integer', 'min:1', 'gte:installment_number'],
        ]);

        $anchorInvoice = CreditCardInvoice::findOrFail($data['reference_invoice_id']);
        abort_if($anchorInvoice->credit_card_id !== $creditCard->id, HttpResponse::HTTP_FORBIDDEN);

        $isUnknown = (bool) ($data['is_unknown'] ?? false);

        $attributes = [
            'user_id' => $request->user()->id,
            'account_id' => $data['account_id'],
            'person_id' => $data['person_id'],
            'category_id' => $data['category_id'] ?? null,
            'type' => TransactionType::Expense,
            'description' => $data['description'] ?: 'Desconhecido',
            'is_unknown' => $isUnknown,
            'amount' => $data['amount'],
            'date' => $data['date'],
        ];

        if ($data['mode'] === 'recurring') {
            Transaction::createRecurringForInvoice($attributes, $anchorInvoice);
        } else {
            [$number, $total] = $data['mode'] === 'installments'
                ? [$data['installment_number'], $data['installment_total']]
                : [1, 1];

            Transaction::createInstallmentsForInvoice($attributes, $anchorInvoice, $number, $total);
        }

        return Redirect::route('finance.invoices.show', $anchorInvoice->id);
    }

    public function updateInstallment(Request $request, Transaction $transaction): RedirectResponse
    {
        abort_if($transaction->user_id !== $request->user()->id, HttpResponse::HTTP_FORBIDDEN);

        $data = $request->validate([
            'account_id' => ['required', Rule::exists('accounts', 'id')->where('user_id', $request->user()->id)],
            'person_id' => ['required', Rule::exists('people', 'id')->where('user_id', $request->user()->id)],
            'is_unknown' => ['nullable', 'boolean'],
            'description' => [Rule::requiredIf(! $request->boolean('is_unknown')), 'nullable', 'string', 'max:255'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'category_id' => ['nullable', Rule::exists('categories', 'id')->where('user_id', $request->user()->id)],
            'date' => ['required', 'date'],
            'mode' => ['required', Rule::in(['single', 'installments', 'recurring'])],
            'installment_number' => ['required_if:mode,installments', 'integer', 'min:1'],
            'installment_total' => ['required_if:mode,installments', 'integer', 'min:1', 'gte:installment_number'],
            'scope' => ['required', Rule::in(['this', 'future', 'all'])],
        ]);

        $isUnknown = (bool) ($data['is_unknown'] ?? false);

        $currentMode = $transaction->installment_total
            ? 'installments'
            : ($transaction->is_recurring ? 'recurring' : 'single');

        $typeChanged = $data['mode'] !== $currentMode
            || ($data['mode'] === 'installments' && (
                (int) $data['installment_number'] !== $transaction->installment_number
                || (int) $data['installment_total'] !== $transaction->installment_total
            ));

        if ($typeChanged) {
            $invoiceId = $transaction->credit_card_invoice_id;

            Transaction::changeType($transaction, $data['mode'], [
                'user_id' => $request->user()->id,
                'account_id' => $data['account_id'],
                'person_id' => $data['person_id'],
                'category_id' => $data['category_id'] ?? null,
                'type' => TransactionType::Expense,
                'description' => $data['description'] ?: 'Desconhecido',
                'is_unknown' => $isUnknown,
                'amount' => $data['amount'],
                'date' => $data['date'],
            ], $data['installment_number'] ?? null, $data['installment_total'] ?? null);

            return Redirect::route('finance.invoices.show', $invoiceId);
        }

        $this->scopedInstallments($transaction, $data['scope'])->toQuery()->update([
            'account_id' => $data['account_id'],
            'description' => $data['description'] ?: 'Desconhecido',
            'is_unknown' => $isUnknown,
            'amount' => $data['amount'],
            'category_id' => $data['category_id'] ?? null,
        ]);

        if ($data['date'] !== $transaction->date->toDateString()) {
            $transaction->update(['date' => $data['date']]);
        }

        return Redirect::back();
    }

    public function toggleReversed(Request $request, Transaction $transaction): RedirectResponse
    {
        $data = $request->validate([
            'scope' => ['required', Rule::in(['this', 'future', 'all'])],
        ]);

        $this->scopedInstallments($transaction, $data['scope'])->toQuery()->update([
            'reversed' => ! $transaction->reversed,
        ]);

        return Redirect::back();
    }

    /**
     * Resolve which installments a "this / this and future / all" edit or
     * reversal scope applies to. A transaction without siblings (a single
     * purchase, not an installment plan) always targets just itself.
     */
    private function scopedInstallments(Transaction $transaction, string $scope): Collection
    {
        if (! $transaction->installment_group_id) {
            return new Collection([$transaction]);
        }

        return match ($scope) {
            'this' => new Collection([$transaction]),
            'future' => Transaction::where('installment_group_id', $transaction->installment_group_id)
                ->where('installment_number', '>=', $transaction->installment_number)
                ->get(),
            'all' => Transaction::where('installment_group_id', $transaction->installment_group_id)->get(),
        };
    }

    private function authorizeInvoice(Request $request, CreditCardInvoice $invoice): void
    {
        abort_if(
            $invoice->creditCard->user_id !== $request->user()->id,
            HttpResponse::HTTP_FORBIDDEN
        );
    }
}
