<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A transaction's person was always meant to be whoever owns its account
 * (accounts belong to exactly one person), but the column let the two
 * diverge — e.g. a purchase billed to someone's account could be tagged
 * with a different person, silently breaking per-person reports like
 * "quanto me devem". Removing it makes the account the single source of
 * truth: Transaction::person is now derived from account->person.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->dropForeign(['person_id']);
            $table->dropColumn('person_id');
        });
    }

    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->foreignId('person_id')->nullable()->after('account_id')->constrained()->cascadeOnDelete();
        });
    }
};
