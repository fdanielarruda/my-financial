<?php

namespace App\Models;

use App\Enums\TransactionType;
use App\Support\BelongsToUser;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

#[Fillable([
    'user_id', 'account_id', 'person_id', 'category_id', 'credit_card_invoice_id', 'recurring_transaction_id',
    'transfer_id', 'type', 'description', 'is_unknown', 'reversed', 'amount', 'date',
    'installment_group_id', 'installment_number', 'installment_total',
])]
class Transaction extends Model
{
    use BelongsToUser, HasFactory;

    protected function casts(): array
    {
        return [
            'type' => TransactionType::class,
            'amount' => 'decimal:2',
            'date' => 'date',
            'is_unknown' => 'boolean',
            'reversed' => 'boolean',
        ];
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    public function person(): BelongsTo
    {
        return $this->belongsTo(Person::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function creditCardInvoice(): BelongsTo
    {
        return $this->belongsTo(CreditCardInvoice::class);
    }

    public function recurringTransaction(): BelongsTo
    {
        return $this->belongsTo(RecurringTransaction::class);
    }

    public function transfer(): BelongsTo
    {
        return $this->belongsTo(Transfer::class);
    }

    /**
     * Create a plain account transaction (checking/savings/wallet/investment).
     * Credit-card purchases are created separately via
     * createInstallmentsForInvoice(), since cards are no longer accounts.
     *
     * @return Collection<int, Transaction>
     */
    public static function createPurchase(array $attributes): Collection
    {
        $account = Account::findOrFail($attributes['account_id']);

        $transaction = $account->transactions()->create($attributes);

        return new Collection([$transaction]);
    }

    /**
     * Create a credit-card purchase anchored on the installment currently
     * visible on a given invoice (e.g. "I'm looking at 5/10 on March's
     * invoice"): fans out the remaining installments to past/future invoices,
     * creating those invoices as needed. Each installment keeps its own
     * amount (as typed, matching what the bank statement shows).
     *
     * @return Collection<int, Transaction>
     */
    public static function createInstallmentsForInvoice(
        array $attributes,
        CreditCardInvoice $anchorInvoice,
        int $installmentNumber,
        int $installmentTotal
    ): Collection {
        $account = Account::findOrFail($attributes['account_id']);
        $creditCard = $anchorInvoice->creditCard;
        $groupId = $installmentTotal > 1 ? (string) Str::ulid() : null;
        $anchorMonth = $anchorInvoice->reference_month;

        return collect(range(1, $installmentTotal))->map(function (int $number) use (
            $account, $attributes, $creditCard, $anchorMonth, $installmentNumber, $installmentTotal, $groupId
        ) {
            $invoiceMonth = $anchorMonth->copy()->addMonthsNoOverflow($number - $installmentNumber);
            $invoice = $creditCard->invoiceForMonth($invoiceMonth);

            return $account->transactions()->create([
                ...$attributes,
                'credit_card_invoice_id' => $invoice->id,
                'installment_group_id' => $groupId,
                'installment_number' => $installmentTotal > 1 ? $number : null,
                'installment_total' => $installmentTotal > 1 ? $installmentTotal : null,
            ]);
        });
    }
}
