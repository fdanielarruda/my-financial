<?php

namespace App\Models;

use App\Enums\InvoiceStatus;
use App\Support\Money;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

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

}
