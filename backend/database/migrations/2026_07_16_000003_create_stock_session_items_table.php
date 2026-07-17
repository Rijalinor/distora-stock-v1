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
        Schema::create('stock_session_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('stock_session_id')
                ->constrained('stock_sessions')
                ->cascadeOnDelete();
            $table->foreignId('item_master_id')
                ->nullable()
                ->constrained('item_masters')
                ->nullOnDelete();
            $table->string('kode_barang');
            $table->string('nama_barang');
            $table->string('satuan')->nullable();
            
            // Quantity fields
            $table->string('qty_sistem_display');
            $table->integer('qty_sistem_base');
            $table->string('qty_aktual_display')->nullable();
            $table->integer('qty_aktual_base')->nullable();
            $table->integer('selisih')->nullable(); // qty_aktual_base - qty_sistem_base
            
            $table->string('status')->default('pending'); // pending | matched | mismatched
            $table->foreignId('checked_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->timestamp('checked_at')->nullable();
            $table->timestamps();

            // Composite index for fast lookups
            $table->index(['stock_session_id', 'kode_barang']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('stock_session_items');
    }
};
