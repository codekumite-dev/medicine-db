<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;

class RoleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $adminEmail = env('ADMIN_EMAIL', 'admin@example.com');
        $adminPassword = env('ADMIN_PASSWORD', 'changeme');

        $roles = [
            'super-admin',
            'admin',
            'clinical-editor',
            'content-admin',
            'data-operator',
            'api-manager',
            'viewer',
        ];

        foreach ($roles as $role) {
            Role::firstOrCreate(['name' => $role, 'guard_name' => 'web']);
        }

        $admin = User::firstOrCreate(
            ['email' => $adminEmail],
            [
                'name' => 'Super Admin',
                'password' => bcrypt($adminPassword),
                'is_active' => true,
            ]
        );

        $admin->assignRole('super-admin');

        if ($this->command) {
            $this->command->info('Admin Login Credentials:');
            $this->command->info('Email: '.$adminEmail);
            $this->command->info('Password: '.$adminPassword);
        }
    }
}
