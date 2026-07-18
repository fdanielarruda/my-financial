<?php

namespace Tests\Feature\Finance;

use App\Enums\AccountType;
use App\Enums\InvoiceStatus;
use App\Enums\TransactionType;
use App\Models\Account;
use App\Models\CreditCard;
use App\Models\Person;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class CreditCardInvoicePaymentTest extends TestCase
{
    use RefreshDatabase;

    public function test_paying_an_invoice_debits_the_payment_account_and_marks_it_paid(): void
    {
        $user = User::factory()->create();
        $person = Person::factory()->for($user)->create();
        $checking = Account::factory()->for($user)->for($person)->create([
            'type' => AccountType::Checking,
            'initial_balance' => 1000,
        ]);
        $cardAccount = Account::factory()->for($user)->for($person)->creditCard()->create();
        $creditCard = CreditCard::factory()->for($cardAccount)->create([
            'closing_day' => 20,
            'due_day' => 27,
            'payment_account_id' => $checking->id,
        ]);

        Transaction::createPurchase([
            'user_id' => $user->id,
            'account_id' => $cardAccount->id,
            'person_id' => $person->id,
            'type' => TransactionType::Expense,
            'description' => 'Supermercado',
            'amount' => 250,
            'date' => Carbon::parse('2026-07-05'),
        ]);

        $invoice = $creditCard->resolveInvoiceFor(Carbon::parse('2026-07-05'));
        $this->assertSame('250.00', $invoice->total());

        $paymentTransaction = $invoice->pay($checking, Carbon::parse('2026-07-21'));

        $invoice->refresh();
        $this->assertSame(InvoiceStatus::Paid, $invoice->status);
        $this->assertSame($paymentTransaction->id, $invoice->paid_transaction_id);
        $this->assertSame('750.00', $checking->balance());
    }

    public function test_paying_an_already_paid_invoice_throws(): void
    {
        $user = User::factory()->create();
        $person = Person::factory()->for($user)->create();
        $checking = Account::factory()->for($user)->for($person)->create(['initial_balance' => 500]);
        $cardAccount = Account::factory()->for($user)->for($person)->creditCard()->create();
        $creditCard = CreditCard::factory()->for($cardAccount)->create(['closing_day' => 20, 'due_day' => 27]);
        $invoice = $creditCard->resolveInvoiceFor(Carbon::parse('2026-07-05'));

        $invoice->pay($checking);

        $this->expectException(\RuntimeException::class);
        $invoice->pay($checking);
    }
}
