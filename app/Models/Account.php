<?php

namespace App\Models;

use App\Enums\AccountType;
use App\Enums\InvoiceStatus;
use App\Enums\TransactionType;
use App\Support\BelongsToUser;
use App\Support\Money;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Fillable(['user_id', 'person_id', 'institution_id', 'name', 'type', 'initial_balance', 'archived_at'])]
class Account extends Model
{
    use BelongsToUser, HasFactory;

    protected function casts(): array
    {
        return [
            'type' => AccountType::class,
            'initial_balance' => 'decimal:2',
            'archived_at' => 'datetime',
        ];
    }

    public function person(): BelongsTo
    {
        return $this->belongsTo(Person::class);
    }

    public function institution(): BelongsTo
    {
        return $this->belongsTo(Institution::class);
    }

    public function creditCard(): HasOne
    {
        return $this->hasOne(CreditCard::class);
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }

    public function outgoingTransfers(): HasMany
    {
        return $this->hasMany(Transfer::class, 'from_account_id');
    }

    public function incomingTransfers(): HasMany
    {
        return $this->hasMany(Transfer::class, 'to_account_id');
    }

    /**
     * Current balance for non credit-card accounts: initial balance plus
     * every income/expense transaction. Transfers are represented as a
     * linked expense/income transaction pair, so they're already included.
     */
    public function balance(): string
    {
        $income = $this->transactions()->where('type', TransactionType::Income)->sum('amount');
        $expense = $this->transactions()->where('type', TransactionType::Expense)->sum('amount');

        return Money::add($this->initial_balance, Money::sub($income, $expense));
    }

    /**
     * Total of the currently open invoice for a credit-card account.
     */
    public function openInvoiceTotal(): string
    {
        $invoice = $this->creditCard?->currentInvoice();

        return $invoice ? $invoice->total() : '0.00';
    }

    public function availableLimit(): string
    {
        $creditCard = $this->creditCard;

        if (! $creditCard) {
            return '0.00';
        }

        $unpaidTotal = $creditCard->invoices()
            ->where('status', '!=', InvoiceStatus::Paid)
            ->get()
            ->reduce(fn ($carry, $invoice) => Money::add($carry, $invoice->total()), '0.00');

        return Money::sub($creditCard->credit_limit, $unpaidTotal);
    }
}
