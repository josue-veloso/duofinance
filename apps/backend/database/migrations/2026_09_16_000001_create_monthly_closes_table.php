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
        Schema::create('monthly_closes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('couple_id')->constrained('couples')->cascadeOnDelete();
            $table->date('month');
            $table->decimal('total_shared', 12, 2);
            $table->decimal('user1_paid', 12, 2);
            $table->decimal('user2_paid', 12, 2);
            $table->decimal('verdict_amount', 12, 2);
            $table->foreignId('verdict_payer_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('verdict_receiver_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('closed_at')->nullable();
            $table->timestamps();

            $table->unique(['couple_id', 'month']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('monthly_closes');
    }
};

