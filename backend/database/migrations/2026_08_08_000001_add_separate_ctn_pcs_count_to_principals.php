<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('principals', function (Blueprint $table): void {
            if (! Schema::hasColumn('principals', 'separate_ctn_pcs_count')) {
                $table->boolean('separate_ctn_pcs_count')->default(false)->after('group_principal_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('principals', function (Blueprint $table): void {
            if (Schema::hasColumn('principals', 'separate_ctn_pcs_count')) {
                $table->dropColumn('separate_ctn_pcs_count');
            }
        });
    }
};
