<?php

namespace Tests\Feature\Finance;

use App\Enums\TransactionType;
use App\Models\Account;
use App\Models\CreditCard;
use App\Models\Person;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class CreditCardPurchaseTest extends TestCase
{
    use RefreshDatabase;

    public function test_installment_purchase_splits_across_the_correct_future_invoices(): void
    {
        $user = User::factory()->create();
        $person = Person::factory()->for($user)->create();
        $account = Account::factory()->for($user)->for($person)->creditCard()->create();
        $creditCard = CreditCard::factory()->for($account)->create(['closing_day' => 10, 'due_day' => 17]);

        $transactions = Transaction::createPurchase([
            'user_id' => $user->id,
            'account_id' => $account->id,
            'person_id' => $person->id,
            'type' => TransactionType::Expense,
            'description' => 'Notebook',
            'amount' => 300,
            'date' => Carbon::parse('2026-07-15'),
        ], installments: 3);

        $this->assertCount(3, $transactions);
        $this->assertTrue($transactions->every(fn (Transaction $t) => $t->amount === '100.00'));
        $this->assertSame([1, 2, 3], $transactions->pluck('installment_number')->all());
        $this->assertSame(1, $transactions->pluck('installment_group_id')->unique()->count());

        // Purchased on the 15th with a closing day of 10 => rolls to next month's invoice.
        $referenceMonths = $transactions
            ->map(fn (Transaction $t) => $t->creditCardInvoice->reference_month->format('Y-m'))
            ->all();

        $this->assertSame(['2026-08', '2026-09', '2026-10'], $referenceMonths);
    }

    public function test_installment_purchase_allocates_rounding_remainder_to_last_installment(): void
    {
        $user = User::factory()->create();
        $person = Person::factory()->for($user)->create();
        $account = Account::factory()->for($user)->for($person)->creditCard()->create();
        CreditCard::factory()->for($account)->create(['closing_day' => 10, 'due_day' => 17]);

        $transactions = Transaction::createPurchase([
            'user_id' => $user->id,
            'account_id' => $account->id,
            'person_id' => $person->id,
            'type' => TransactionType::Expense,
            'description' => 'Presente',
            'amount' => 100,
            'date' => Carbon::parse('2026-07-05'),
        ], installments: 3);

        $this->assertSame(['33.33', '33.33', '33.34'], $transactions->pluck('amount')->all());
        $this->assertSame('100.00', (string) $transactions->sum('amount'));
    }

    public function test_single_credit_card_purchase_attaches_to_the_open_invoice(): void
    {
        $user = User::factory()->create();
        $person = Person::factory()->for($user)->create();
        $account = Account::factory()->for($user)->for($person)->creditCard()->create();
        CreditCard::factory()->for($account)->create(['closing_day' => 20, 'due_day' => 27]);

        $transactions = Transaction::createPurchase([
            'user_id' => $user->id,
            'account_id' => $account->id,
            'person_id' => $person->id,
            'type' => TransactionType::Expense,
            'description' => 'Cinema',
            'amount' => 45,
            'date' => Carbon::parse('2026-07-05'),
        ]);

        $this->assertCount(1, $transactions);
        $this->assertNull($transactions->first()->installment_group_id);
        $this->assertSame('2026-07', $transactions->first()->creditCardInvoice->reference_month->format('Y-m'));
    }
}
