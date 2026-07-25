<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stock_session_items', function (Blueprint $table): void {
            $table->index(['stock_session_id', 'item_master_id'], 'ssi_session_item_master_idx');
            $table->index(['stock_session_id', 'status'], 'ssi_session_status_idx');
        });

        Schema::table('stock_sessions', function (Blueprint $table): void {
            $table->index(['branch_id', 'status'], 'stock_sessions_branch_status_idx');
            $table->index(['assigned_to', 'status'], 'stock_sessions_assigned_status_idx');
            $table->index(['principal_id', 'session_date'], 'stock_sessions_principal_date_idx');
        });

        Schema::table('csv_uploads', function (Blueprint $table): void {
            $table->index(['branch_id', 'status'], 'csv_uploads_branch_status_idx');
        });
    }

    public function down(): void
    {
        Schema::table('csv_uploads', function (Blueprint $table): void {
            $table->dropIndex('csv_uploads_branch_status_idx');
        });

        Schema::table('stock_sessions', function (Blueprint $table): void {
            $table->dropIndex('stock_sessions_branch_status_idx');
            $table->dropIndex('stock_sessions_assigned_status_idx');
            $table->dropIndex('stock_sessions_principal_date_idx');
        });

        Schema::table('stock_session_items', function (Blueprint $table): void {
            $table->dropIndex('ssi_session_item_master_idx');
            $table->dropIndex('ssi_session_status_idx');
        });
    }
};
