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
        Schema::create('expenses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('couple_id')->constrained('couples')->cascadeOnDelete();
            $table->foreignId('paid_by_user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('recurring_expense_id')->nullable()->constrained('recurring_expenses')->nullOnDelete();
            $table->string('description', 200);
            $table->decimal('total_amount', 12, 2);
            $table->unsignedInteger('installments_count')->default(1);
            $table->boolean('is_shared')->default(true);
            $table->foreignId('category_id')->nullable()->constrained('categories')->nullOnDelete();
            $table->date('purchase_date');
            $table->timestamps();

            $table->index(['couple_id', 'purchase_date']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('expenses');
    }
};

