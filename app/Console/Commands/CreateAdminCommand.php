<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;

class CreateAdminCommand extends Command
{
    protected $signature = 'app:create-admin';
    protected $description = 'Create an admin user interactively';

    public function handle(): int
    {
        $name  = $this->ask('Name');
        $email = $this->ask('Email');

        if (User::where('email', $email)->exists()) {
            $this->error("A user with email {$email} already exists.");
            return Command::FAILURE;
        }

        $password = $this->secret('Password (min 8 characters)');

        User::create([
            'name'     => $name,
            'email'    => $email,
            'password' => $password,
            'role'     => 'admin',
        ]);

        $this->info("Admin user {$email} created successfully.");

        return Command::SUCCESS;
    }
}
