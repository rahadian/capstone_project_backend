<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        User::factory()->create([
            'name' => 'Admin Nusantara Booking',
            'email' => 'admin@admin.com',
            'password' => bcrypt('admin123'),
            'role' => 'admin',
        ]);

        User::factory()->create([
            'name' => 'Owner Nusantara Booking',
            'email' => 'owner@owner.com',
            'password' => bcrypt('indomiee123'),
            'role' => 'owner',
        ]);

        User::factory()->create([
            'name' => 'Tenant Nusantara Booking',
            'email' => 'tenant@tenant.com',
            'password' => bcrypt('tenant123'),
            'role' => 'tenant',
        ]);
    }
}
