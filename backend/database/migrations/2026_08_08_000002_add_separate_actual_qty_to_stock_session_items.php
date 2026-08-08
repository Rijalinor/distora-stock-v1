<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stock_session_items', function (Blueprint $table): void {
            if (! Schema::hasColumn('stock_session_items', 'qty_aktual_ctn')) {
                $table->integer('qty_aktual_ctn')->nullable()->after('qty_aktual_base');
            }

            if (! Schema::hasColumn('stock_session_items', 'qty_aktual_pcs')) {
                $table->integer('qty_aktual_pcs')->nullable()->after('qty_aktual_ctn');
            }
        });
    }

    public function down(): void
    {
        Schema::table('stock_session_items', function (Blueprint $table): void {
            if (Schema::hasColumn('stock_session_items', 'qty_aktual_pcs')) {
                $table->dropColumn('qty_aktual_pcs');
            }

            if (Schema::hasColumn('stock_session_items', 'qty_aktual_ctn')) {
                $table->dropColumn('qty_aktual_ctn');
            }
        });
    }
};
