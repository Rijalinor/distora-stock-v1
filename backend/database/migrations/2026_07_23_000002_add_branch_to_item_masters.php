<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('item_masters', function (Blueprint $table): void {
            $table->foreignId('branch_id')
                ->nullable()
                ->after('id')
                ->constrained('branches')
                ->nullOnDelete();
        });

        $defaultBranchId = DB::table('branches')->where('kode', 'PUSAT')->value('id');
        DB::table('item_masters')->whereNull('branch_id')->update(['branch_id' => $defaultBranchId]);

        Schema::table('item_masters', function (Blueprint $table): void {
            $table->dropUnique(['kode_barang']);
            $table->unique(['branch_id', 'kode_barang']);
            $table->index(['branch_id', 'barcode']);
        });
    }

    public function down(): void
    {
        Schema::table('item_masters', function (Blueprint $table): void {
            $table->dropIndex(['branch_id', 'barcode']);
            $table->dropUnique(['branch_id', 'kode_barang']);
            $table->unique('kode_barang');
            $table->dropConstrainedForeignId('branch_id');
        });
    }
};
