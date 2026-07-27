<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;

class CreateAdminCommand extends Command
{
    protected $signature = 'app:create-admin {name} {email} {password}';
    protected $description = 'Create an admin user';

    public function handle(): int
    {
        $email = $this->argument('email');

        if (User::where('email', $email)->exists()) {
            $this->error("A user with email {$email} already exists.");
            return Command::FAILURE;
        }

        User::create([
            'name'     => $this->argument('name'),
            'email'    => $email,
            'password' => $this->argument('password'),
            'role'     => 'admin',
        ]);

        $this->info("Admin user {$email} created successfully.");

        return Command::SUCCESS;
    }
}
