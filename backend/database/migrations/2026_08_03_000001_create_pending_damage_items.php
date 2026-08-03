<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pending_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->string('barcode');
            $table->string('temporary_code')->nullable();
            $table->string('item_name');
            $table->foreignId('principal_id')->nullable()->constrained()->nullOnDelete();
            $table->string('unit_label')->default('PCS');
            $table->unsignedInteger('qty_per_scan')->default(1);
            $table->string('status')->default('pending');
            $table->foreignId('matched_item_master_id')->nullable()->constrained('item_masters')->nullOnDelete();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['branch_id', 'barcode']);
            $table->index(['branch_id', 'status']);
        });

        Schema::create('damage_check_pending_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('damage_check_id')->constrained()->cascadeOnDelete();
            $table->foreignId('pending_item_id')->constrained()->restrictOnDelete();
            $table->unsignedInteger('qty_rusak_base')->default(1);
            $table->string('qty_rusak_display')->default('1 PCS');
            $table->timestamp('last_scanned_at');
            $table->foreignId('last_scanned_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['damage_check_id', 'pending_item_id'], 'damage_pending_check_item_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('damage_check_pending_items');
        Schema::dropIfExists('pending_items');
    }
};
