<?php

namespace App\Console\Commands;

use App\Models\AdminUser;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;

final class CreateAdminUser extends Command
{
    protected $signature = 'admin:create {email} {--name=Admin} {--role=super_admin}';
    protected $description = 'Create an admin user';

    public function handle(): int
    {
        $email = $this->argument('email');
        $name = $this->option('name');
        $role = $this->option('role');

        $password = $this->secret('Enter password for the admin user');

        if (!$password) {
            $this->error('Password is required');
            return self::FAILURE;
        }

        if (strlen($password) < 8) {
            $this->error('Password must be at least 8 characters');
            return self::FAILURE;
        }

        if (AdminUser::where('email', $email)->exists()) {
            $this->error("Admin with email {$email} already exists");
            return self::FAILURE;
        }

        $admin = AdminUser::create([
            'name' => $name,
            'email' => $email,
            'password' => Hash::make($password),
            'role' => $role,
            'status' => 'active',
        ]);

        $this->info("Admin created successfully!");
        $this->table(
            ['Field', 'Value'],
            [
                ['ID', $admin->id],
                ['Email', $admin->email],
                ['Name', $admin->name],
                ['Role', $admin->role],
            ]
        );

        return self::SUCCESS;
    }
}
