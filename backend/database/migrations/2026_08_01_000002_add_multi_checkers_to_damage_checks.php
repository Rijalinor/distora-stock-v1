<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('damage_check_user', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('damage_check_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['damage_check_id', 'user_id']);
        });

        DB::table('damage_checks')->orderBy('id')->chunkById(500, function ($checks): void {
            DB::table('damage_check_user')->insert($checks->map(fn ($check): array => [
                'damage_check_id' => $check->id,
                'user_id' => $check->officer_id,
                'created_at' => now(),
                'updated_at' => now(),
            ])->all());
        });

        Schema::table('damage_check_items', function (Blueprint $table): void {
            $table->foreignId('last_scanned_by')->nullable()->after('last_scanned_at')->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('damage_check_items', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('last_scanned_by');
        });
        Schema::dropIfExists('damage_check_user');
    }
};
