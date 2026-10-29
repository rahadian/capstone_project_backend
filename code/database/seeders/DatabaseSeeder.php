<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     *
     * Hanya DemoSeeder: UserSeeder dan UjiDeploySeeder ditinggalkan sebagai
     * catatan sejarah, gambarnya menunjuk berkas yang tidak ada.
     */
    public function run(): void
    {
        $this->call(DemoSeeder::class);
    }
}
