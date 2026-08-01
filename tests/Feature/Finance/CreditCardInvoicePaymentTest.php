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

    public function test_paying_an_invoice_debits_each_account_that_funded_a_purchase(): void
    {
        $user = User::factory()->create();
        $person = Person::factory()->for($user)->create();
        $checking = Account::factory()->for($user)->for($person)->create([
            'type' => AccountType::Checking,
            'initial_balance' => 1000,
        ]);
        $savings = Account::factory()->for($user)->for($person)->create([
            'type' => AccountType::Savings,
            'initial_balance' => 500,
        ]);
        $cardAccount = Account::factory()->for($user)->for($person)->creditCard()->create();
        $creditCard = CreditCard::factory()->for($cardAccount)->create(['closing_day' => 20, 'due_day' => 27]);

        $invoice = $creditCard->resolveInvoiceFor(Carbon::parse('2026-07-05'));

        Transaction::createInstallmentsForInvoice([
            'user_id' => $user->id,
            'account_id' => $checking->id,
            'person_id' => $person->id,
            'type' => TransactionType::Expense,
            'description' => 'Supermercado',
            'amount' => 250,
            'date' => Carbon::parse('2026-07-05'),
        ], $invoice, 1, 1);

        Transaction::createInstallmentsForInvoice([
            'user_id' => $user->id,
            'account_id' => $savings->id,
            'person_id' => $person->id,
            'type' => TransactionType::Expense,
            'description' => 'Presente',
            'amount' => 100,
            'date' => Carbon::parse('2026-07-06'),
        ], $invoice, 1, 1);

        $this->assertSame('1000.00', $checking->balance());
        $this->assertSame('500.00', $savings->balance());

        $payments = $invoice->pay(Carbon::parse('2026-07-21'));

        $invoice->refresh();
        $this->assertSame(InvoiceStatus::Paid, $invoice->status);
        $this->assertCount(2, $payments);
        $this->assertTrue($payments->every(fn (Transaction $t) => $t->description === 'Pagamento de Fatura'));

        $this->assertSame('750.00', $checking->balance());
        $this->assertSame('400.00', $savings->balance());
    }

    public function test_paying_an_already_paid_invoice_throws(): void
    {
        $user = User::factory()->create();
        $person = Person::factory()->for($user)->create();
        $account = Account::factory()->for($user)->for($person)->create(['initial_balance' => 500]);
        $cardAccount = Account::factory()->for($user)->for($person)->creditCard()->create();
        $creditCard = CreditCard::factory()->for($cardAccount)->create(['closing_day' => 20, 'due_day' => 27]);
        $invoice = $creditCard->resolveInvoiceFor(Carbon::parse('2026-07-05'));

        Transaction::createInstallmentsForInvoice([
            'user_id' => $user->id,
            'account_id' => $account->id,
            'person_id' => $person->id,
            'type' => TransactionType::Expense,
            'description' => 'Cinema',
            'amount' => 45,
            'date' => Carbon::parse('2026-07-05'),
        ], $invoice, 1, 1);

        $invoice->pay();

        $this->expectException(\RuntimeException::class);
        $invoice->pay();
    }
}
