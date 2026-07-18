<?php

namespace App\Console\Commands;

use App\Enums\InvoiceStatus;
use App\Models\CreditCardInvoice;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

#[Signature('finance:close-invoices')]
#[Description('Flip open credit-card invoices whose closing date has passed to "closed"')]
class CloseCreditCardInvoices extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(): void
    {
        $count = CreditCardInvoice::where('status', InvoiceStatus::Open)
            ->where('closing_date', '<', Carbon::today())
            ->update(['status' => InvoiceStatus::Closed]);

        $this->info("Fechadas {$count} fatura(s).");
    }
}
