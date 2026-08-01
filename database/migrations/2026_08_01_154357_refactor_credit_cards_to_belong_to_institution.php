<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('credit_cards', function (Blueprint $table) {
            $table->foreignId('user_id')->nullable()->after('id')->constrained()->cascadeOnDelete();
            $table->foreignId('institution_id')->nullable()->after('user_id')->constrained()->restrictOnDelete();
            $table->string('name')->nullable()->after('institution_id');
        });

        // Backfill from the account each credit card used to belong to.
        DB::statement('
            update credit_cards
            set user_id = accounts.user_id,
                institution_id = accounts.institution_id,
                name = accounts.name
            from accounts
            where accounts.id = credit_cards.account_id
        ');

        Schema::table('credit_cards', function (Blueprint $table) {
            $table->dropConstrainedForeignId('account_id');
            $table->unsignedBigInteger('user_id')->nullable(false)->change();
            $table->unsignedBigInteger('institution_id')->nullable(false)->change();
            $table->string('name')->nullable(false)->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('credit_cards', function (Blueprint $table) {
            $table->foreignId('account_id')->nullable()->constrained()->cascadeOnDelete();
            $table->dropConstrainedForeignId('institution_id');
            $table->dropConstrainedForeignId('user_id');
            $table->dropColumn('name');
        });
    }
};
