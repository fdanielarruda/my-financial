<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('credit_card_invoices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('credit_card_id')->constrained()->cascadeOnDelete();
            $table->date('reference_month');
            $table->date('closing_date');
            $table->date('due_date');
            $table->string('status')->default('open');
            $table->timestamp('paid_at')->nullable();
            // paid_transaction_id foreign key is added in a later migration, once the transactions table exists.
            $table->unsignedBigInteger('paid_transaction_id')->nullable();
            $table->timestamps();

            $table->unique(['credit_card_id', 'reference_month']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('credit_card_invoices');
    }
};
