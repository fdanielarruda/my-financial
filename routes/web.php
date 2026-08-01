<?php

use App\Enums\AccountType;
use App\Http\Controllers\ProfileController;
use App\Models\Account;
use App\Models\CreditCard;
use App\Models\RecurringTransaction;
use App\Support\Money;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Carbon;
use Inertia\Inertia;

Route::get('/', function () {
    return Inertia::render('Welcome', [
        'canLogin' => Route::has('login'),
        'canRegister' => Route::has('register'),
        'laravelVersion' => Application::VERSION,
        'phpVersion' => PHP_VERSION,
    ]);
});

Route::get('/dashboard', function () {
    $accounts = Account::with('person')->whereNull('archived_at')->orderBy('name')->get();

    $balancesByPerson = $accounts
        ->filter(fn (Account $account) => $account->type !== AccountType::CreditCard)
        ->groupBy(fn (Account $account) => $account->person->name)
        ->map(fn ($group) => $group->reduce(fn ($carry, Account $account) => Money::add($carry, $account->balance()), '0.00'));

    $openInvoices = CreditCard::where('user_id', auth()->id())
        ->get()
        ->map(function (CreditCard $card) {
            $invoice = $card->currentInvoice();

            return [
                'card' => ['id' => $card->id, 'name' => $card->name],
                'invoice_id' => $invoice?->id,
                'total' => $card->openInvoiceTotal(),
            ];
        })
        ->filter(fn ($invoice) => Money::compare($invoice['total'], '0.00') === 1)
        ->values();

    $upcomingRecurring = RecurringTransaction::with(['account', 'person'])
        ->where('is_active', true)
        ->whereBetween('next_run_date', [Carbon::today(), Carbon::today()->addDays(14)])
        ->orderBy('next_run_date')
        ->get();

    return Inertia::render('Dashboard', [
        'balancesByPerson' => $balancesByPerson,
        'netWorth' => $balancesByPerson->reduce(fn ($carry, $balance) => Money::add($carry, $balance), '0.00'),
        'openInvoices' => $openInvoices,
        'upcomingRecurring' => $upcomingRecurring,
    ]);
})->middleware(['auth', 'verified'])->name('dashboard');

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__.'/auth.php';
require __DIR__.'/finance.php';
