<?php

namespace App\Models;

use App\Enums\AccountType;
use App\Enums\TransactionType;
use App\Support\BelongsToUser;
use App\Support\Money;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

#[Fillable([
    'user_id', 'account_id', 'person_id', 'category_id', 'credit_card_invoice_id', 'recurring_transaction_id',
    'type', 'description', 'amount', 'date',
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

    /**
     * Create a transaction (or, for a credit-card purchase with more than one
     * installment, one transaction per installment, each attached to the
     * invoice its installment month resolves to).
     *
     * @return Collection<int, Transaction>
     */
    public static function createPurchase(array $attributes, int $installments = 1): Collection
    {
        $account = Account::findOrFail($attributes['account_id']);
        $date = Carbon::parse($attributes['date']);
        $totalAmount = (string) $attributes['amount'];

        if ($account->type !== AccountType::CreditCard || $installments <= 1) {
            $transaction = $account->transactions()->create([
                ...$attributes,
                'credit_card_invoice_id' => $account->type === AccountType::CreditCard
                    ? $account->creditCard->resolveInvoiceFor($date)->id
                    : null,
            ]);

            return new Collection([$transaction]);
        }

        $groupId = (string) Str::ulid();
        $amounts = Money::splitEvenly($totalAmount, $installments);

        return collect(range(1, $installments))->map(function (int $number) use (
            $account, $attributes, $date, $installments, $groupId, $amounts
        ) {
            $installmentDate = $date->copy()->addMonthsNoOverflow($number - 1);
            $invoice = $account->creditCard->resolveInvoiceFor($installmentDate);

            return $account->transactions()->create([
                ...$attributes,
                'amount' => $amounts[$number - 1],
                'credit_card_invoice_id' => $invoice->id,
                'installment_group_id' => $groupId,
                'installment_number' => $number,
                'installment_total' => $installments,
            ]);
        });
    }
}
