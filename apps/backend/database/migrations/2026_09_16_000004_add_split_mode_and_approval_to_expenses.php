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
            $table->string('split_mode', 20)->default('DEFAULT')->after('is_shared');
            $table->decimal('custom_user1_quota', 5, 4)->nullable()->after('split_mode');
            $table->string('status', 20)->default('CONFIRMED')->after('purchase_date');
            $table->foreignId('approved_by_user_id')->nullable()->constrained('users')->nullOnDelete()->after('status');
            $table->timestamp('approved_at')->nullable()->after('approved_by_user_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('expenses', function (Blueprint $table) {
            $table->dropForeign(['approved_by_user_id']);
            $table->dropColumn([
                'split_mode',
                'custom_user1_quota',
                'status',
                'approved_by_user_id',
                'approved_at',
            ]);
        });
    }
};

