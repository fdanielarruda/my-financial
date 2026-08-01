<?php

namespace App\Models;

use App\Enums\TransactionType;
use App\Support\BelongsToUser;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['user_id', 'from_account_id', 'to_account_id', 'amount', 'date', 'description'])]
class Transfer extends Model
{
    use BelongsToUser, HasFactory;

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'date' => 'date',
        ];
    }

    public function fromAccount(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'from_account_id');
    }

    public function toAccount(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'to_account_id');
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }

    /**
     * Create a transfer between two accounts, represented as a linked pair
     * of ordinary transactions (an expense out of the source account and an
     * income into the destination account) so it behaves like any other
     * transaction for balances, listings and reports.
     */
    public static function createBetween(array $attributes): self
    {
        $fromAccount = Account::findOrFail($attributes['from_account_id']);
        $toAccount = Account::findOrFail($attributes['to_account_id']);
        $description = $attributes['description'] ?: 'Transferência';

        return tap(
            self::create($attributes),
            function (self $transfer) use ($fromAccount, $toAccount, $attributes, $description) {
                $transfer->transactions()->createMany([
                    [
                        'user_id' => $attributes['user_id'],
                        'account_id' => $fromAccount->id,
                        'person_id' => $fromAccount->person_id,
                        'type' => TransactionType::Expense,
                        'description' => $description,
                        'amount' => $attributes['amount'],
                        'date' => $attributes['date'],
                    ],
                    [
                        'user_id' => $attributes['user_id'],
                        'account_id' => $toAccount->id,
                        'person_id' => $toAccount->person_id,
                        'type' => TransactionType::Income,
                        'description' => $description,
                        'amount' => $attributes['amount'],
                        'date' => $attributes['date'],
                    ],
                ]);
            }
        );
    }
}
