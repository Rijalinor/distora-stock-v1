<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('item_masters', function (Blueprint $table): void {
            $table->json('qty_structure')->nullable()->after('satuan');
        });
    }

    public function down(): void
    {
        Schema::table('item_masters', function (Blueprint $table): void {
            $table->dropColumn('qty_structure');
        });
    }
};
