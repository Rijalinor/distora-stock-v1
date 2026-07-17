<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('stock_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('csv_upload_id')
                ->nullable()
                ->constrained('csv_uploads')
                ->nullOnDelete();
            $table->foreignId('principal_id')
                ->constrained('principals')
                ->cascadeOnUpdate()
                ->restrictOnDelete();
            $table->date('session_date');
            $table->foreignId('assigned_to')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->string('status')->default('open'); // open | in_progress | completed
            $table->integer('total_items')->default(0);
            $table->integer('checked_items')->default(0);
            $table->integer('matched_items')->default(0);
            $table->integer('mismatched_items')->default(0);
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('stock_sessions');
    }
};
