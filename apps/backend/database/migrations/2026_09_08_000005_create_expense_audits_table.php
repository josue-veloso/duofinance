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
        Schema::create('expense_audits', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('expense_id')->index();
            $table->foreignId('couple_id')->constrained('couples')->cascadeOnDelete();
            $table->foreignId('actor_user_id')->constrained('users')->cascadeOnDelete();
            $table->string('action', 50);
            $table->json('before')->nullable();
            $table->json('after')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['expense_id', 'created_at']);
        });

    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('expense_audits');
    }
};
