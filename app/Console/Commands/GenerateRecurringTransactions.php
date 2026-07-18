<?php

namespace App\Console\Commands;

use App\Models\RecurringTransaction;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

#[Signature('finance:generate-recurring')]
#[Description('Materialize transactions for every recurring rule that is due today or earlier')]
class GenerateRecurringTransactions extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(): void
    {
        $today = Carbon::today();
        $generated = 0;

        RecurringTransaction::where('is_active', true)
            ->where('next_run_date', '<=', $today)
            ->each(function (RecurringTransaction $recurring) use ($today, &$generated) {
                // A rule can be several cycles overdue (e.g. app was offline); catch it up to today.
                while ($recurring->is_active && $recurring->next_run_date->lte($today)) {
                    $recurring->generateDueTransaction();
                    $generated++;
                }
            });

        $this->info("Geradas {$generated} transação(ões) recorrente(s).");
    }
}
