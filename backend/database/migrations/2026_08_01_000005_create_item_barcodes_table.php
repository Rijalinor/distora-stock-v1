<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('item_barcodes', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('item_master_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('barcode');
            $table->string('unit_label')->default('PCS');
            $table->unsignedInteger('qty_base')->default(1);
            $table->boolean('is_primary')->default(false);
            $table->timestamps();

            $table->unique(['item_master_id', 'barcode']);
            $table->index(['branch_id', 'barcode']);
        });

        DB::table('item_masters')
            ->whereNotNull('barcode')
            ->where('barcode', '!=', '')
            ->orderBy('id')
            ->chunkById(500, function ($items): void {
                DB::table('item_barcodes')->insert($items->map(fn ($item): array => [
                    'item_master_id' => $item->id,
                    'branch_id' => $item->branch_id,
                    'barcode' => $item->barcode,
                    'unit_label' => 'PCS',
                    'qty_base' => 1,
                    'is_primary' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ])->all());
            });
    }

    public function down(): void
    {
        Schema::dropIfExists('item_barcodes');
    }
};
