<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;
use App\Models\Contract;
use App\Models\Payment;
use App\Models\Tenant;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class NusantaraBookingTest extends TestCase
{
    /**
     * A basic feature test example.
     */
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public'); //mock storage for file uploads
    }

    public function test_tenant_can_register_successfully_with_id_card(){
        $idCardFile  = UploadedFile::fake()->image('id_card.jpg', 600, 400);

        $response = $this->postJson('/api/register', [
            'name' => 'Tenant User',
            'email' => 'tenant@example.com',
            'phone' => '081234567890',
            'password' => 'P@ssw0rd123!',
            'password_confirmation' => 'P@ssw0rd123!',
            'role' => 'tenant',
            'id_card' => $idCardFile,
            'ktp_data_json' => json_encode([
                'nik' => '1234567890123456',
                'nama' => 'Tenant User',
                'tempat_lahir' => 'Jakarta',
                'tanggal_lahir' => '1990-01-01',
                'jenis_kelamin' => 'Laki-laki',
                'alamat' => 'Jl. Contoh Alamat No. 123, Jakarta'
            ]),
        ]);

        $response->assertStatus(201)
        ->assertJsonStructure([
            'message',
            'access_token',
                'token_type',
                'expires_in',
                'user' => ['id', 'name', 'email', 'role', 'tenant']
            ]);
       $this->assertDatabaseHas('users', ['email' => 'tenant@example.com', 'role' => 'tenant']);
       $this->assertDatabaseHas('tenants', ['name' => 'Tenant User', 'phone' => '081234567890']);
    }

    public function test_owner_can_create_unit()
    {
        $owner = User::factory()->create(['role' => 'owner']);
        $imageFile = UploadedFile::fake()->image('unit_photo.jpg', 800, 600);

        $response = $this->actingAs($owner, 'api')
            ->postJson('/api/units', [
                'name'        => 'Apartemen Studio 01',
                'type'        => 'Kamar Kos',
                'facilities'  => ['AC', 'WiFi', 'Kamar Mandi Dalam'],
                'image'       => $imageFile,
                'address'     => 'Jl. Contoh Alamat No. 456, Jakarta',
                'price'       => 2000000,
                'status'      => 'available',
            ]);

        $response->assertStatus(201)
            ->assertJsonFragment(['name' => 'Apartemen Studio 01']);

        $this->assertDatabaseHas('units', [
            'owner_id' => $owner->id,
            'name'     => 'Apartemen Studio 01',
        ]);
    }

    /* `image` opsional menurut validasi. Formulir pemilik tidak punya unggahan
       gambar, jadi unit tanpa foto harus tetap tersimpan — dulu baris ini
       berakhir 500 "Call to a member function store() on null". */
    public function test_owner_can_create_unit_without_image()
    {
        $owner = User::factory()->create(['role' => 'owner']);

        $response = $this->actingAs($owner, 'api')
            ->postJson('/api/units', [
                'name'    => 'Ruko Tanpa Foto',
                'type'    => 'Ruang Usaha',
                'address' => 'Jl. Contoh Alamat No. 789, Jakarta',
                'price'   => 3000000,
            ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('units', [
            'owner_id' => $owner->id,
            'name'     => 'Ruko Tanpa Foto',
            'image'    => null,
        ]);
    }

    public function test_tenant_can_propose_contract_and_owner_can_activate_it()
    {
        // 1. Setup Data
        $owner = User::factory()->create(['role' => 'owner']);
        $unit = Unit::create([
            'owner_id'    => $owner->id,
            'name'        => 'Kosan A1',
            'address'     => 'Jl. Contoh Alamat No. 789, Jakarta',
            'price'       => 1000000,
            'status'      => 'available',
        ]);

        $tenantUser = User::factory()->create(['role' => 'tenant']);
        $tenant = Tenant::create([
            'user_id' => $tenantUser->id,
            'name'    => 'Calon Tenant',
            'phone'   => '08999999999',
        ]);

        // 2. Tenant ajukan kontrak
        $contractResponse = $this->actingAs($tenantUser, 'api')
            ->postJson('/api/contracts', [
                'unit_id'     => $unit->id,
                'start_date'  => now()->format('Y-m-d'),
                'end_date'    => now()->addYear()->format('Y-m-d'),
                'total_price' => 24000000,
            ]);

        $contractResponse->assertStatus(201);
        $contractId = $contractResponse->json('contract.id');

        $this->assertDatabaseHas('contracts', [
            'id'        => $contractId,
            'tenant_id' => $tenant->id,
            'status'    => 'pending',
        ]);

        // 3. Owner setujui/aktifkan kontrak
        $updateResponse = $this->actingAs($owner, 'api')
            ->putJson("/api/contracts/{$contractId}", [
                'status' => 'active',
            ]);

        $updateResponse->assertStatus(200);

        // Pastikan status kontrak jadi active dan status unit otomatis jadi rented
        $this->assertDatabaseHas('contracts', ['id' => $contractId, 'status' => 'active']);
        $this->assertDatabaseHas('units', ['id' => $unit->id, 'status' => 'rented']);
    }

    public function test_tenant_can_upload_payment_proof_and_owner_can_verify()
    {
        // 1. Setup Data
        $owner = User::factory()->create(['role' => 'owner']);
        $unit = Unit::create([
            'owner_id'    => $owner->id,
            'name'        => 'Kosan B2',
            'address'     => 'Jl. Contoh Alamat No. 789, Jakarta',
            'price'       => 1500000,
            'status'      => 'rented',
        ]);

        $tenantUser = User::factory()->create(['role' => 'tenant']);
        $tenant = Tenant::create([
            'user_id' => $tenantUser->id,
            'name'    => 'Tenant Bayar',
            'phone'   => '08888888888',
        ]);

        $contract = Contract::create([
            'unit_id'     => $unit->id,
            'tenant_id'   => $tenant->id,
            'start_date'  => now()->format('Y-m-d'),
            'end_date'    => now()->addYear()->format('Y-m-d'),
            'total_price' => 18000000,
            'status'      => 'active',
        ]);

        // 2. Tenant upload bukti bayar
        $proofFile = UploadedFile::fake()->image('bukti_tf.jpg', 800, 600);

        $paymentResponse = $this->actingAs($tenantUser, 'api')
            ->postJson('/api/payments', [
                'contract_id' => $contract->id,
                'month'       => '2026-11',
                'amount'      => 2000000,
                'proof'       => $proofFile,
            ]);

        $paymentResponse->assertStatus(201);
        $paymentId = $paymentResponse->json('payment.id');

        $this->assertDatabaseHas('payments', [
            'id'     => $paymentId,
            'month'  => '2026-11',
            'status' => 'pending',
        ]);

        // 3. Owner verifikasi pembayaran
        $verifyResponse = $this->actingAs($owner, 'api')
            ->putJson("/api/payments/{$paymentId}", [
                'status' => 'paid',
            ]);

        $verifyResponse->assertStatus(200);
        $this->assertDatabaseHas('payments', ['id' => $paymentId, 'status' => 'paid']);
    }

    public function test_public_catalog_only_shows_available_units_by_default()
    {
        // 1. Setup User Owner
        $owner = User::create([
            'name'     => 'Owner Properti Catalog',
            'email'    => 'ownercatalog@example.com',
            'password' => bcrypt('P@ssw0rd123!'),
            'role'     => 'owner',
        ]);

        // 2. Buat 3 unit dengan status berbeda
        $unitAvailable = Unit::create([
            'owner_id'    => $owner->id,
            'name'        => 'Unit Ready',
            'address'     => 'Jl. Contoh Alamat No. 789, Jakarta',
            'price'       => 1500000,
            'status'      => 'available',
        ]);

        $unitRented = Unit::create([
            'owner_id'    => $owner->id,
            'name'        => 'Unit Tersewa',
            'address'     => 'Jl. Contoh Alamat No. 789, Jakarta',
            'price'       => 1500000,
            'status'      => 'rented',
        ]);

        $unitMaintenance = Unit::create([
            'owner_id'    => $owner->id,
            'name'        => 'Unit Perbaikan',
            'address'     => 'Jl. Contoh Alamat No. 789, Jakarta',
            'price'       => 1500000,
            'status'      => 'maintenance',
        ]);

        // 3. Hit endpoint publik TANPA parameter status
        $response = $this->getJson('/api/list/units');

        $response->assertStatus(200)
            ->assertJsonFragment(['name' => 'Unit Ready'])
            ->assertJsonMissing(['name' => 'Unit Tersewa'])
            ->assertJsonMissing(['name' => 'Unit Perbaikan']);

        // 4. Hit endpoint publik DENGAN parameter ?status=all (opsional/opsi 1)
        $responseAll = $this->getJson('/api/list/units?status=all');

        $responseAll->assertStatus(200)
            ->assertJsonFragment(['name' => 'Unit Ready'])
            ->assertJsonFragment(['name' => 'Unit Tersewa'])
            ->assertJsonFragment(['name' => 'Unit Perbaikan']);
    }

    public function test_unit_includes_type_facilities_image_and_booked_dates()
    {
        $owner = User::create([
            'name'     => 'Owner Properti Detail',
            'email'    => 'ownerdetail@example.com',
            'password' => bcrypt('P@ssw0rd123!'),
            'role'     => 'owner',
        ]);

        // Mock fake image upload
        $imageFile = UploadedFile::fake()->image('unit_photo.jpg', 800, 600);

        // 1. Tes Create Unit dengan File Upload Gambar + Field Baru
        $response = $this->actingAs($owner, 'api')
            ->postJson('/api/units', [
                'name'        => 'Kosan Exclusive A1',
                'type'        => 'Kamar Kos',
                'facilities'  => ['AC', 'WiFi', 'Kamar Mandi Dalam'],
                'image'       => $imageFile,
                'address'     => 'Jl. Raya Bromo No. 12, Probolinggo',
                'price'       => 1500000,
                'status'      => 'available',
            ]);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'unit' => ['id', 'type', 'facilities', 'image', 'booked_dates']
            ])
            ->assertJsonFragment([
                'type'       => 'Kamar Kos',
                'facilities' => ['AC', 'WiFi', 'Kamar Mandi Dalam'],
            ]);

        // Ambil ID dari unit yang baru dibuat
        $unitId = $response->json('unit.id');

        // Pastikan file gambar tersimpan di fake storage disk
        $this->assertNotNull($response->json('unit.image'));

        // 2. Simulasikan kontrak aktif
        $tenantUser = User::create([
            'name'     => 'Tenant Booked',
            'email'    => 'tenantbooked@example.com',
            'password' => bcrypt('P@ssw0rd123!'),
            'role'     => 'tenant',
        ]);
        $tenant = Tenant::create(['user_id' => $tenantUser->id, 'name' => 'Tenant Booked', 'phone' => '08123456']);

        Contract::create([
            'unit_id'     => $unitId,
            'tenant_id'   => $tenant->id,
            'start_date'  => '2026-11-01',
            'end_date'    => '2026-11-03',
            'total_price' => 4500000,
            'status'      => 'active',
        ]);

        // 3. Tes Response Detail Publik
        $detailResponse = $this->getJson("/api/detail/unit/{$unitId}");

        $detailResponse->assertStatus(200)
            ->assertJsonStructure(['type', 'facilities', 'image', 'booked_dates'])
            ->assertJsonFragment([
                'booked_dates' => ['2026-11-01', '2026-11-02', '2026-11-03']
            ]);
    }
}
