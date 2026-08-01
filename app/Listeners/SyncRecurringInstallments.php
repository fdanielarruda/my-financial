<?php

namespace App\Listeners;

use App\Models\Transaction;
use Illuminate\Auth\Events\Login;
use Illuminate\Support\Carbon;

/**
 * Keeps every recurring card purchase topped up to the rolling 2-years-ahead
 * window. Runs inline on login (not queued): it's cheap — at most one batch
 * of inserts per active recurring purchase, once per calendar month per user.
 */
class SyncRecurringInstallments
{
    public function handle(Login $event): void
    {
        $user = $event->user;
        $currentMonth = Carbon::today()->startOfMonth();

        if ($user->recurring_synced_month && $user->recurring_synced_month->gte($currentMonth)) {
            return;
        }

        Transaction::where('user_id', $user->id)
            ->where('is_recurring', true)
            ->distinct()
            ->pluck('installment_group_id')
            ->each(fn (string $groupId) => Transaction::extendRecurringGroup($groupId));

        $user->forceFill(['recurring_synced_month' => $currentMonth])->save();
    }
}
