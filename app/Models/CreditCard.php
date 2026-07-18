<?php

namespace App\Models;

use App\Enums\InvoiceStatus;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['account_id', 'payment_account_id', 'credit_limit', 'closing_day', 'due_day'])]
class CreditCard extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'credit_limit' => 'decimal:2',
        ];
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    public function paymentAccount(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'payment_account_id');
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(CreditCardInvoice::class);
    }

    /**
     * Find (or create) the invoice a purchase made on the given date belongs to.
     * Purchases made after the closing day roll over to next month's invoice.
     */
    public function resolveInvoiceFor(CarbonInterface $date): CreditCardInvoice
    {
        $referenceMonth = $date->day > $this->closing_day
            ? $date->copy()->startOfMonth()->addMonthNoOverflow()
            : $date->copy()->startOfMonth();

        return $this->invoices()->firstOrCreate(
            ['reference_month' => $referenceMonth->toDateString()],
            [
                'closing_date' => $this->dayInMonth($referenceMonth, $this->closing_day),
                'due_date' => $this->due_day > $this->closing_day
                    ? $this->dayInMonth($referenceMonth, $this->due_day)
                    : $this->dayInMonth($referenceMonth->copy()->addMonthNoOverflow(), $this->due_day),
                'status' => InvoiceStatus::Open,
            ]
        );
    }

    public function currentInvoice(): ?CreditCardInvoice
    {
        return $this->resolveInvoiceFor(Carbon::now());
    }

    private function dayInMonth(CarbonInterface $month, int $day): string
    {
        return $month->copy()->startOfMonth()->addDays(min($day, $month->daysInMonth) - 1)->toDateString();
    }
}
