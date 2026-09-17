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
        Schema::create('categories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('couple_id')->constrained('couples')->cascadeOnDelete();
            $table->string('name', 60);
            $table->string('color', 20)->default('#6366f1');
            $table->string('icon', 20)->default('💳');
            $table->timestamps();

            $table->index('couple_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('categories');
    }
};

