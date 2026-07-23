<?php

use Illuminate\Foundation\Inspiring;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('audit:prune {--days=90}', function (): void {
    $days = max(1, (int) $this->option('days'));
    $deleted = DB::table('audit_logs')
        ->where('created_at', '<', now()->subDays($days))
        ->delete();

    $this->info("Deleted {$deleted} audit logs older than {$days} days.");
})->purpose('Delete old audit logs');

Artisan::command('user:make-central-admin {email=admin@distora.com}', function (): int {
    $user = User::where('email', $this->argument('email'))->first();

    if (! $user) {
        $this->error('User not found.');

        return self::FAILURE;
    }

    $user->forceFill([
        'role' => \App\Enums\UserRole::Admin,
        'branch_id' => null,
    ])->save();

    $this->info("{$user->email} is now a central admin.");

    return self::SUCCESS;
})->purpose('Make an admin user central admin');
