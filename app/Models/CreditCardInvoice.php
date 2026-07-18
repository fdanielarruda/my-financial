<?php

namespace App\Models;

use App\Enums\InvoiceStatus;
use App\Enums\TransactionType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use RuntimeException;

#[Fillable(['credit_card_id', 'reference_month', 'closing_date', 'due_date', 'status', 'paid_at', 'paid_transaction_id'])]
class CreditCardInvoice extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'reference_month' => 'date',
            'closing_date' => 'date',
            'due_date' => 'date',
            'status' => InvoiceStatus::class,
            'paid_at' => 'datetime',
        ];
    }

    public function creditCard(): BelongsTo
    {
        return $this->belongsTo(CreditCard::class);
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }

    public function paidTransaction(): BelongsTo
    {
        return $this->belongsTo(Transaction::class, 'paid_transaction_id');
    }

    public function total(): string
    {
        return (string) $this->transactions()->sum('amount');
    }

    /**
     * Pay this invoice from the given account: creates the expense transaction
     * that debits the payment account and marks the invoice as paid.
     */
    public function pay(Account $paymentAccount, ?Carbon $date = null, ?Person $person = null): Transaction
    {
        if ($this->status === InvoiceStatus::Paid) {
            throw new RuntimeException('Esta fatura já foi paga.');
        }

        $date ??= Carbon::now();

        $transaction = $paymentAccount->transactions()->create([
            'user_id' => $this->creditCard->account->user_id,
            'person_id' => $person?->id ?? $this->creditCard->account->person_id,
            'type' => TransactionType::Expense,
            'description' => 'Pagamento fatura '.$this->creditCard->account->name.' - '.$this->reference_month->translatedFormat('M/Y'),
            'amount' => $this->total(),
            'date' => $date,
        ]);

        $this->update([
            'status' => InvoiceStatus::Paid,
            'paid_at' => $date,
            'paid_transaction_id' => $transaction->id,
        ]);

        return $transaction;
    }
}
