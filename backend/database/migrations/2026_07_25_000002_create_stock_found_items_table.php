<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock_found_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('stock_session_id')->constrained('stock_sessions')->cascadeOnDelete();
            $table->foreignId('item_master_id')->nullable()->constrained('item_masters')->nullOnDelete();
            $table->foreignId('suspected_item_master_id')->nullable()->constrained('item_masters')->nullOnDelete();
            $table->string('kode_barang');
            $table->string('barcode')->nullable();
            $table->string('nama_barang');
            $table->string('satuan')->nullable();
            $table->string('qty_aktual_display');
            $table->integer('qty_aktual_base');
            $table->string('status')->default('unresolved');
            $table->text('note')->nullable();
            $table->foreignId('found_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('found_at')->nullable();
            $table->timestamps();

            $table->index(['stock_session_id', 'kode_barang']);
            $table->index(['stock_session_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_found_items');
    }
};
