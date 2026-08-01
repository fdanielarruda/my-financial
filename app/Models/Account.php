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
use Illuminate\Database\Eloquent\Relations\HasMany;

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
     * Current balance: initial balance plus every income/expense
     * transaction. Transfers are represented as a linked expense/income
     * transaction pair, so they're already included. Credit-card purchases
     * attributed to this account are excluded — they only hit the balance
     * when the invoice is actually paid (a separate real transaction).
     */
    public function balance(): string
    {
        $income = $this->transactions()->where('type', TransactionType::Income)->whereNull('credit_card_invoice_id')->sum('amount');
        $expense = $this->transactions()->where('type', TransactionType::Expense)->whereNull('credit_card_invoice_id')->sum('amount');

        return Money::add($this->initial_balance, Money::sub($income, $expense));
    }
}
