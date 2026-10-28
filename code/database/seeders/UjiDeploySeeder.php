<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use App\Models\Unit;
use App\Models\Tenant;
use App\Models\Contract;
use App\Models\Payment;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\File;

class UjiDeploySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // 1. Buat Dummy Image File di Storage Disk Publik (Opsional biar ada file fisik lokal)
        Storage::disk('public')->makeDirectory('unit_image');
        Storage::disk('public')->makeDirectory('payments');

        // 2. Owner Uji
        $owner = User::firstOrCreate(
            ['email' => 'uji.deploy.20261028@example.com'],
            [
                'name'     => 'Owner Uji',
                'password' => bcrypt('P@ssw0rd123!'),
                'role'     => 'owner',
            ]
        );

        // 3. Unit Uji
        $unit = Unit::firstOrCreate(
            ['owner_id' => $owner->id, 'name' => 'Unit Uji Surabaya A1'],
            [
                'type'        => 'Kamar Kos',
                'facilities'  => ['AC', 'WiFi', 'Kamar Mandi Dalam'],
                'image'       => Storage::disk('public')->url('unit_image/sample_unit.jpg'),
                'address'     => 'Jl. Raya Bromo No. 123, Surabaya',
                'price'       => 1500000,
                'status'      => 'rented',
            ]
        );

        // 4. Tenant Uji
        $tenantUser = User::firstOrCreate(
            ['email' => 'tenant.uji@example.com'],
            [
                'name'     => 'Tenant Uji',
                'password' => bcrypt('P@ssw0rd123!'),
                'role'     => 'tenant',
            ]
        );

        $tenant = Tenant::firstOrCreate(
            ['user_id' => $tenantUser->id],
            [
                'name'  => 'Tenant Uji',
                'phone' => '081234567890',
            ]
        );

        // 5. Kontrak 1: Active
        $contractActive = Contract::firstOrCreate(
            [
                'unit_id'    => $unit->id,
                'tenant_id'  => $tenant->id,
                'start_date' => '2026-10-01',
            ],
            [
                'end_date'    => '2027-09-30',
                'total_price' => 18000000,
                'status'      => 'active',
            ]
        );

        // 6. Kontrak 2: Completed
        $contractCompleted = Contract::firstOrCreate(
            [
                'unit_id'    => $unit->id,
                'tenant_id'  => $tenant->id,
                'start_date' => '2026-01-11',
            ],
            [
                'end_date'    => '2026-03-30',
                'total_price' => 1000000,
                'status'      => 'completed',
            ]
        );

        // 7. Kontrak 3: Terminated
        $contractTerminated = Contract::firstOrCreate(
            [
                'unit_id'    => $unit->id,
                'tenant_id'  => $tenant->id,
                'start_date' => '2026-05-01',
            ],
            [
                'end_date'    => '2026-06-30',
                'total_price' => 1000000,
                'status'      => 'terminated',
            ]
        );

        // 8. Payment untuk Kontrak Active (Paid & Pending)
        Payment::firstOrCreate(
            [
                'contract_id' => $contractActive->id,
                'month'       => '2026-10',
            ],
            [
                'amount' => 1500000,
                'proof_url'  => Storage::disk('public')->url('payments/bukti_lunas.jpg'),
                'status' => 'paid',
            ]
        );

        Payment::firstOrCreate(
            [
                'contract_id' => $contractActive->id,
                'month'       => '2026-11',
            ],
            [
                'amount' => 1500000,
                'proof_url'  => Storage::disk('public')->url('payments/bukti_pending.jpg'),
                'status' => 'pending',
            ]
        );

        // 9. Payment untuk Kontrak Terminated (Failed)
        Payment::firstOrCreate(
            [
                'contract_id' => $contractTerminated->id,
                'month'       => '2026-05',
            ],
            [
                'amount' => 1500000,
                'proof_url'  => Storage::disk('public')->url('payments/bukti_failed.jpg'),
                'status' => 'failed',
            ]
        );
    }
}
