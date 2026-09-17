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
        Schema::create('recurring_expenses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('couple_id')->constrained('couples')->cascadeOnDelete();
            $table->foreignId('paid_by_user_id')->constrained('users')->cascadeOnDelete();
            $table->string('description', 200);
            $table->decimal('total_amount', 12, 2);
            $table->unsignedInteger('installments_count')->default(1);
            $table->boolean('is_shared')->default(true);
            $table->foreignId('category_id')->nullable()->constrained('categories')->nullOnDelete();
            $table->unsignedSmallInteger('day_of_month')->default(1);
            $table->date('start_month');
            $table->date('end_month')->nullable();
            $table->string('frequency', 20)->default('MONTHLY');
            $table->boolean('is_active')->default(true);
            $table->date('last_generated_month')->nullable();
            $table->timestamps();

            $table->index(['couple_id', 'is_active']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('recurring_expenses');
    }
};

