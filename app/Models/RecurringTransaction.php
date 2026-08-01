<?php

namespace App\Models;

use App\Enums\RecurringFrequency;
use App\Enums\TransactionType;
use App\Support\BelongsToUser;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

#[Fillable([
    'user_id', 'account_id', 'person_id', 'category_id', 'type', 'description', 'amount',
    'frequency', 'interval', 'day_of_month', 'weekday', 'start_date', 'end_date',
    'next_run_date', 'is_active',
])]
class RecurringTransaction extends Model
{
    use BelongsToUser, HasFactory;

    protected function casts(): array
    {
        return [
            'type' => TransactionType::class,
            'frequency' => RecurringFrequency::class,
            'amount' => 'decimal:2',
            'start_date' => 'date',
            'end_date' => 'date',
            'next_run_date' => 'date',
            'is_active' => 'boolean',
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

    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }

    /**
     * Materialize the transaction for the current due date, then advance
     * next_run_date to the next occurrence (deactivating past end_date).
     */
    public function generateDueTransaction(): Transaction
    {
        $account = $this->account;
        $dueDate = $this->next_run_date->copy();

        $attributes = [
            'user_id' => $this->user_id,
            'account_id' => $account->id,
            'person_id' => $this->person_id,
            'category_id' => $this->category_id,
            'recurring_transaction_id' => $this->id,
            'type' => $this->type,
            'description' => $this->description,
            'amount' => $this->amount,
            'date' => $dueDate,
        ];

        $transaction = Transaction::create($attributes);

        $this->advanceNextRunDate();

        return $transaction;
    }

    private function advanceNextRunDate(): void
    {
        $next = match ($this->frequency) {
            RecurringFrequency::Weekly => $this->next_run_date->copy()->addWeeks($this->interval),
            RecurringFrequency::Monthly => $this->next_run_date->copy()->addMonthsNoOverflow($this->interval),
            RecurringFrequency::Yearly => $this->next_run_date->copy()->addYearsNoOverflow($this->interval),
        };

        $isActive = ! $this->end_date || $next->lte($this->end_date);

        $this->update([
            'next_run_date' => $next,
            'is_active' => $isActive,
        ]);
    }
}
