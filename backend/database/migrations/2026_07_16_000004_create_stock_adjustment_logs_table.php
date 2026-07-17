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
        Schema::create('stock_adjustment_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('stock_session_item_id')
                ->constrained('stock_session_items')
                ->cascadeOnDelete();
            $table->foreignId('adjusted_by')
                ->constrained('users')
                ->cascadeOnUpdate()
                ->restrictOnDelete();
            $table->integer('qty_before_base');
            $table->integer('qty_after_base');
            $table->string('qty_before_display');
            $table->string('qty_after_display');
            $table->string('reason')->nullable();
            $table->timestamp('created_at')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('stock_adjustment_logs');
    }
};
