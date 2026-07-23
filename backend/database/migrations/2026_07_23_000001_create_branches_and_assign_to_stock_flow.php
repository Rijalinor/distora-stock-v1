<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('branches', function (Blueprint $table): void {
            $table->id();
            $table->string('kode')->unique();
            $table->string('nama');
            $table->boolean('status')->default(true);
            $table->timestamps();
        });

        DB::table('branches')->insert([
            'kode' => 'PUSAT',
            'nama' => 'Pusat',
            'status' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        Schema::table('users', function (Blueprint $table): void {
            $table->foreignId('branch_id')
                ->nullable()
                ->after('role')
                ->constrained('branches')
                ->nullOnDelete();
        });

        Schema::table('csv_uploads', function (Blueprint $table): void {
            $table->foreignId('branch_id')
                ->nullable()
                ->after('upload_date')
                ->constrained('branches')
                ->nullOnDelete();
        });

        Schema::table('stock_sessions', function (Blueprint $table): void {
            $table->foreignId('branch_id')
                ->nullable()
                ->after('principal_id')
                ->constrained('branches')
                ->nullOnDelete();

            $table->index(['branch_id', 'session_date']);
        });

        $defaultBranchId = DB::table('branches')->where('kode', 'PUSAT')->value('id');

        DB::table('users')->whereNull('branch_id')->update(['branch_id' => $defaultBranchId]);
        DB::table('csv_uploads')->whereNull('branch_id')->update(['branch_id' => $defaultBranchId]);
        DB::table('stock_sessions')->whereNull('branch_id')->update(['branch_id' => $defaultBranchId]);
    }

    public function down(): void
    {
        Schema::table('stock_sessions', function (Blueprint $table): void {
            $table->dropIndex(['branch_id', 'session_date']);
            $table->dropConstrainedForeignId('branch_id');
        });

        Schema::table('csv_uploads', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('branch_id');
        });

        Schema::table('users', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('branch_id');
        });

        Schema::dropIfExists('branches');
    }
};
