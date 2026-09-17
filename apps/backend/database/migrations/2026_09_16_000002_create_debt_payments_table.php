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
        Schema::create('debt_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('couple_id')->constrained('couples')->cascadeOnDelete();
            $table->foreignId('payer_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('receiver_id')->constrained('users')->cascadeOnDelete();
            $table->decimal('amount', 12, 2);
            $table->date('month')->nullable();
            $table->string('note', 140)->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['couple_id', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('debt_payments');
    }
};

