<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('damage_checks', function (Blueprint $table): void {
            $table->id();
            $table->string('reference_number')->nullable()->unique();
            $table->date('check_date');
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();
            $table->foreignId('principal_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('officer_id')->constrained('users')->restrictOnDelete();
            $table->string('location');
            $table->text('notes')->nullable();
            $table->string('status')->default('open');
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index(['branch_id', 'check_date']);
        });

        Schema::create('damage_check_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('damage_check_id')->constrained()->cascadeOnDelete();
            $table->foreignId('item_master_id')->constrained()->restrictOnDelete();
            $table->unsignedInteger('qty_rusak_base')->default(1);
            $table->string('qty_rusak_display')->default('1 PCS');
            $table->timestamp('last_scanned_at');
            $table->timestamps();

            $table->unique(['damage_check_id', 'item_master_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('damage_check_items');
        Schema::dropIfExists('damage_checks');
    }
};
