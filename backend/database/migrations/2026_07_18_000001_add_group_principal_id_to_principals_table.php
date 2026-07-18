<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('principals', function (Blueprint $table): void {
            if (! Schema::hasColumn('principals', 'group_principal_id')) {
                $table->foreignId('group_principal_id')
                    ->nullable()
                    ->after('nama')
                    ->constrained('principals')
                    ->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('principals', function (Blueprint $table): void {
            if (Schema::hasColumn('principals', 'group_principal_id')) {
                $table->dropConstrainedForeignId('group_principal_id');
            }
        });
    }
};
