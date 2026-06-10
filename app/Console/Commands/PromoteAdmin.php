<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;

class PromoteAdmin extends Command
{
    protected $signature = 'user:promote {email}';

    protected $description = 'Grant (or revoke) admin rights for the user with the given email';

    public function handle(): int
    {
        $user = User::where('email', $this->argument('email'))->first();

        if (!$user) {
            $this->error("No user found with email {$this->argument('email')}.");

            return self::FAILURE;
        }

        $user->update(['is_admin' => !$user->is_admin]);

        $this->info("{$user->name} is ".($user->is_admin ? 'now an admin.' : 'no longer an admin.'));

        return self::SUCCESS;
    }
}
