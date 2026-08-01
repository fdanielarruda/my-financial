<?php

namespace App\Models;

use App\Enums\InvoiceStatus;
use App\Enums\TransactionType;
use App\Support\Money;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

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

    public function paymentTransactions(): HasMany
    {
        return $this->hasMany(Transaction::class, 'invoice_payment_id');
    }

    /**
     * Invoice total: charges minus reversed (credited) items, so a
     * reversal reduces the total exactly like it does on the real statement.
     */
    public function total(): string
    {
        $charges = (string) $this->transactions()->where('reversed', false)->sum('amount');
        $credits = (string) $this->transactions()->where('reversed', true)->sum('amount');

        return Money::sub($charges, $credits);
    }

    /**
     * Settle the invoice: each account that funded a purchase on it gets
     * debited its own share as a regular "Pagamento de Fatura" transaction
     * (excluded from the card itself — cards aren't real accounts). This
     * mirrors how the card was actually used, since a shared card can have
     * purchases split across several people's accounts.
     *
     * @return Collection<int, Transaction>
     */
    public function pay(?CarbonInterface $date = null): Collection
    {
        if ($this->status === InvoiceStatus::Paid) {
            throw new \RuntimeException('Invoice already paid.');
        }

        $date ??= Carbon::now();

        $totalsByAccount = [];

        foreach ($this->transactions()->get() as $transaction) {
            $signedAmount = $transaction->reversed
                ? Money::sub('0.00', $transaction->amount)
                : $transaction->amount;

            $totalsByAccount[$transaction->account_id] = Money::add(
                $totalsByAccount[$transaction->account_id] ?? '0.00',
                $signedAmount
            );
        }

        $payments = collect($totalsByAccount)
            ->filter(fn (string $total) => Money::compare($total, '0.00') > 0)
            ->map(function (string $total, int $accountId) use ($date) {
                $account = Account::findOrFail($accountId);

                return $account->transactions()->create([
                    'user_id' => $account->user_id,
                    'person_id' => $account->person_id,
                    'invoice_payment_id' => $this->id,
                    'type' => TransactionType::Expense,
                    'description' => 'Pagamento de Fatura',
                    'amount' => $total,
                    'date' => $date->toDateString(),
                ]);
            })
            ->values();

        $this->update([
            'status' => InvoiceStatus::Paid,
            'paid_at' => $date,
            'paid_transaction_id' => $payments->first()?->id,
        ]);

        return $payments;
    }
}
