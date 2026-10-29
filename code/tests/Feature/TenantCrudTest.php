<?php

namespace Tests\Feature;

use App\Models\Contract;
use App\Models\Tenant;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class TenantCrudTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
    }

    private function owner(): User
    {
        return User::factory()->create(['role' => 'owner']);
    }

    private function tenant(array $overrides = []): Tenant
    {
        return Tenant::create(array_merge(['name' => 'Penyewa A', 'phone' => '628111'], $overrides));
    }

    private function unit(User $owner): Unit
    {
        return Unit::create([
            'owner_id' => $owner->id,
            'name' => 'Kos A',
            'address' => 'Bandung',
            'price' => 1_200_000,
            'status' => 'available',
        ]);
    }

    private function contract(Unit $unit, Tenant $tenant): Contract
    {
        return Contract::create([
            'unit_id' => $unit->id,
            'tenant_id' => $tenant->id,
            'start_date' => '2026-11-01',
            'end_date' => '2027-01-31',
            'total_price' => 3_600_000,
            'status' => 'active',
        ]);
    }

    public function test_owner_lists_own_and_contract_linked_tenants()
    {
        $owner = $this->owner();
        $otherOwner = $this->owner();

        $this->tenant(['name' => 'Penyewa Sendiri', 'owner_id' => $owner->id]);
        $linkedTenant = $this->tenant(['name' => 'Penyewa Kontrak']);
        $this->contract($this->unit($owner), $linkedTenant);
        $this->tenant(['name' => 'Penyewa Asing', 'owner_id' => $otherOwner->id]);

        $response = $this->actingAs($owner, 'api')->getJson('/api/tenants');

        $response->assertStatus(200)
            ->assertJsonFragment(['name' => 'Penyewa Sendiri', 'contracts_count' => 0])
            ->assertJsonFragment(['name' => 'Penyewa Kontrak', 'contracts_count' => 1])
            ->assertJsonMissing(['name' => 'Penyewa Asing']);
    }

    public function test_owner_does_not_see_unrelated_tenants()
    {
        $owner = $this->owner();
        $otherOwner = $this->owner();
        $this->tenant(['name' => 'Penyewa Asing', 'owner_id' => $otherOwner->id]);

        $this->actingAs($owner, 'api')
            ->getJson('/api/tenants')
            ->assertStatus(200)
            ->assertJsonPath('data', []);
    }

    public function test_owner_creates_tenant_without_account()
    {
        $owner = $this->owner();

        $response = $this->actingAs($owner, 'api')->postJson('/api/tenants', [
            'name' => 'Budi Tanpa Akun',
            'phone' => '628123456789',
            'ktp_status' => 'pending',
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('tenant.user_id', null)
            ->assertJsonPath('tenant.owner_id', $owner->id)
            ->assertJsonPath('tenant.ktp_status', 'pending');
        $this->assertDatabaseHas('tenants', [
            'name' => 'Budi Tanpa Akun',
            'owner_id' => $owner->id,
            'user_id' => null,
            'ktp_status' => 'pending',
        ]);
    }

    public function test_owner_updates_contract_linked_tenant_ktp_status()
    {
        $owner = $this->owner();
        $tenant = $this->tenant(['name' => 'Penyewa Kontrak', 'ktp_status' => 'pending']);
        $this->contract($this->unit($owner), $tenant);

        $this->actingAs($owner, 'api')
            ->putJson("/api/tenants/{$tenant->id}", ['ktp_status' => 'verified'])
            ->assertStatus(200)
            ->assertJsonPath('tenant.ktp_status', 'verified');
        $this->assertDatabaseHas('tenants', ['id' => $tenant->id, 'ktp_status' => 'verified']);
    }

    public function test_owner_cannot_edit_tenant_of_other_owner()
    {
        $otherOwner = $this->owner();
        $tenant = $this->tenant(['name' => 'Penyewa Asing', 'owner_id' => $otherOwner->id]);

        $this->actingAs($this->owner(), 'api')
            ->putJson("/api/tenants/{$tenant->id}", ['name' => 'Diubah'])
            ->assertStatus(403);
        $this->assertDatabaseHas('tenants', ['id' => $tenant->id, 'name' => 'Penyewa Asing']);
    }

    public function test_owner_cannot_delete_tenant_with_contract()
    {
        $owner = $this->owner();
        $tenant = $this->tenant(['name' => 'Penyewa Kontrak', 'owner_id' => $owner->id]);
        $this->contract($this->unit($owner), $tenant);

        $this->actingAs($owner, 'api')
            ->deleteJson("/api/tenants/{$tenant->id}")
            ->assertStatus(409)
            ->assertJsonPath('message', 'Penyewa yang memiliki kontrak tidak dapat dihapus.');
        $this->assertDatabaseHas('tenants', ['id' => $tenant->id]);
    }

    public function test_owner_deletes_own_tenant_without_contract()
    {
        $owner = $this->owner();
        $tenant = $this->tenant(['name' => 'Penyewa Tanpa Kontrak', 'owner_id' => $owner->id]);

        $this->actingAs($owner, 'api')
            ->deleteJson("/api/tenants/{$tenant->id}")
            ->assertStatus(204);
        $this->assertDatabaseMissing('tenants', ['id' => $tenant->id]);
    }

    public function test_tenant_cannot_access_tenant_crud()
    {
        $tenantUser = User::factory()->create(['role' => 'tenant']);

        $this->actingAs($tenantUser, 'api')
            ->getJson('/api/tenants')
            ->assertStatus(403);
        $this->actingAs($tenantUser, 'api')
            ->postJson('/api/tenants', ['name' => 'Tidak Boleh'])
            ->assertStatus(403);
    }

    public function test_registration_sets_ktp_status_from_ktp_data()
    {
        $this->postJson('/api/register', [
            'name' => 'Penyewa Dengan KTP',
            'email' => 'ktp@example.com',
            'phone' => '081234567890',
            'password' => 'P@ssw0rd123!',
            'password_confirmation' => 'P@ssw0rd123!',
            'role' => 'tenant',
            'id_card' => UploadedFile::fake()->image('ktp.jpg', 600, 400),
            'ktp_data_json' => json_encode([
                'nik' => '1234567890123456',
                'nama' => 'Penyewa Dengan KTP',
            ]),
        ])->assertStatus(201);

        $this->postJson('/api/register', [
            'name' => 'Penyewa Tanpa Data KTP',
            'email' => 'ktp-kosong@example.com',
            'phone' => '081234567891',
            'password' => 'P@ssw0rd123!',
            'password_confirmation' => 'P@ssw0rd123!',
            'role' => 'tenant',
            'id_card' => UploadedFile::fake()->image('ktp2.jpg', 600, 400),
        ])->assertStatus(201);

        $this->assertDatabaseHas('tenants', [
            'name' => 'Penyewa Dengan KTP',
            'ktp_status' => 'pending',
        ]);
        $this->assertDatabaseHas('tenants', [
            'name' => 'Penyewa Tanpa Data KTP',
            'ktp_status' => 'unverified',
        ]);
    }
}
