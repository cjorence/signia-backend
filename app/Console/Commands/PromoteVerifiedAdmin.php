<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;

class PromoteVerifiedAdmin extends Command
{
    protected $signature = 'signia:promote-admin {email} {--apply : Promote this reviewed, verified account}';
    protected $description = 'Inspect an existing verified account; explicitly apply administrator access without creating credentials';

    public function handle(): int
    {
        $user = User::where('email', $this->argument('email'))->first();
        if (! $user || ! $user->email_verified_at || ! $user->is_active) {
            $this->error('Account must already exist, be verified, and be active. No changes made.');
            return self::FAILURE;
        }
        $this->table(['ID', 'Email', 'Role'], [[$user->id, $user->email, $user->role]]);
        if (! $this->option('apply')) {
            $this->info('Inspection only. Verify the account owner, then repeat with --apply.');
            return self::SUCCESS;
        }
        $user->forceFill(['role' => 'admin'])->save();
        $this->info('Administrator access applied. Sign out/in and verify the admin page.');
        return self::SUCCESS;
    }
}
