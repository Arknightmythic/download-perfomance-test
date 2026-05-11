<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('bank_data', function (Blueprint $table) {
            $table->id();
            $table->date('transaction_date')->index();
            $table->string('account_number', 30)->index();
            $table->string('transaction_type', 20);
            $table->decimal('amount', 18, 2);
            $table->decimal('balance', 18, 2);
            $table->text('description')->nullable();
            $table->string('branch_code', 10);
            $table->char('currency', 3)->default('IDR');
            $table->timestamps();

            // Composite index untuk query date range
            $table->index(['transaction_date', 'branch_code']);
        });

        // BRIN index untuk performa data sequential tanggal
        DB::statement('CREATE INDEX bank_data_transaction_date_brin ON bank_data USING BRIN (transaction_date)');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('bank_data');
    }
};