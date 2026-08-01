<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('damage_checks', function (Blueprint $table): void {
            $table->text('join_pin')->nullable()->after('notes');
        });
    }

    public function down(): void
    {
        Schema::table('damage_checks', function (Blueprint $table): void {
            $table->dropColumn('join_pin');
        });
    }
};
