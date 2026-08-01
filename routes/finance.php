<?php

use App\Http\Controllers\Finance\AccountController;
use App\Http\Controllers\Finance\CategoryController;
use App\Http\Controllers\Finance\CreditCardInvoiceController;
use App\Http\Controllers\Finance\InstitutionController;
use App\Http\Controllers\Finance\PersonController;
use App\Http\Controllers\Finance\RecurringTransactionController;
use App\Http\Controllers\Finance\StatementImportController;
use App\Http\Controllers\Finance\SummaryController;
use App\Http\Controllers\Finance\TransactionController;
use App\Http\Controllers\Finance\TransferController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified'])->prefix('finance')->name('finance.')->group(function () {
    Route::resource('people', PersonController::class)->except(['show', 'create', 'edit'])->names('people');
    Route::resource('institutions', InstitutionController::class)->except(['show', 'create', 'edit'])->names('institutions');
    Route::resource('categories', CategoryController::class)->except(['show', 'create', 'edit'])->names('categories');

    Route::resource('accounts', AccountController::class)->except(['create', 'edit'])->names('accounts');

    Route::get('transactions/quick', [TransactionController::class, 'quick'])->name('transactions.quick');
    Route::resource('transactions', TransactionController::class)->except(['show', 'create', 'edit'])->names('transactions');

    Route::get('summary', [SummaryController::class, 'index'])->name('summary.index');

    Route::resource('transfers', TransferController::class)->only(['index', 'store', 'destroy'])->names('transfers');

    Route::resource('recurring', RecurringTransactionController::class)->except(['show', 'create', 'edit'])->names('recurring');

    Route::get('invoices/{invoice}', [CreditCardInvoiceController::class, 'show'])->name('invoices.show');
    Route::post('invoices/{invoice}/pay', [CreditCardInvoiceController::class, 'pay'])->name('invoices.pay');

    Route::get('statement-imports/create', [StatementImportController::class, 'create'])->name('statement-imports.create');
    Route::post('statement-imports', [StatementImportController::class, 'upload'])->name('statement-imports.upload');
    Route::get('statement-imports/{statementImport}', [StatementImportController::class, 'show'])->name('statement-imports.show');
    Route::post('statement-imports/{statementImport}/reextract', [StatementImportController::class, 'reextract'])->name('statement-imports.reextract');
    Route::post('statement-imports/{statementImport}/confirm', [StatementImportController::class, 'confirm'])->name('statement-imports.confirm');
});
