<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock_session_user', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('stock_session_id')->constrained('stock_sessions')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['stock_session_id', 'user_id']);
        });

        DB::table('stock_sessions')
            ->whereNotNull('assigned_to')
            ->orderBy('id')
            ->select(['id', 'assigned_to', 'created_at', 'updated_at'])
            ->chunkById(500, function ($sessions): void {
                DB::table('stock_session_user')->insert($sessions
                    ->map(fn ($session) => [
                        'stock_session_id' => $session->id,
                        'user_id' => $session->assigned_to,
                        'created_at' => $session->created_at ?? now(),
                        'updated_at' => $session->updated_at ?? now(),
                    ])
                    ->all());
            });

        Schema::table('stock_session_items', function (Blueprint $table): void {
            $table->foreignId('locked_by')
                ->nullable()
                ->after('checked_at')
                ->constrained('users')
                ->nullOnDelete();
            $table->timestamp('locked_at')->nullable()->after('locked_by');
            $table->index(['locked_by', 'locked_at'], 'ssi_locked_by_at_idx');
        });
    }

    public function down(): void
    {
        Schema::table('stock_session_items', function (Blueprint $table): void {
            $table->dropIndex('ssi_locked_by_at_idx');
            $table->dropConstrainedForeignId('locked_by');
            $table->dropColumn('locked_at');
        });

        Schema::dropIfExists('stock_session_user');
    }
};
