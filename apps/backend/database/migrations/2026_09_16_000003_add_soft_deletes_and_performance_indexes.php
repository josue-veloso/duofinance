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
        Schema::table('expenses', function (Blueprint $table) {
            $table->softDeletes();
        });

        Schema::table('installments', function (Blueprint $table) {
            $table->index(['due_month', 'expense_id'], 'installments_due_month_expense_id_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('installments', function (Blueprint $table) {
            $table->dropIndex('installments_due_month_expense_id_idx');
        });

        Schema::table('expenses', function (Blueprint $table) {
            $table->dropSoftDeletes();
        });
    }
};

