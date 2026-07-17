<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->string('mobile_api_token_hash', 64)->nullable()->unique()->after('role');
            $table->timestamp('mobile_api_token_created_at')->nullable()->after('mobile_api_token_hash');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropUnique(['mobile_api_token_hash']);
            $table->dropColumn(['mobile_api_token_hash', 'mobile_api_token_created_at']);
        });
    }
};
