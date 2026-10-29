<?php

namespace Tests\Feature;

use App\Models\Contract;
use App\Models\Tenant;
use App\Models\Unit;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ContractAvailabilityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-10-29 08:00:00');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function owner(): User
    {
        return User::factory()->create(['role' => 'owner']);
    }

    private function tenant(?string $name = 'Penyewa A'): Tenant
    {
        return Tenant::create(['name' => $name, 'phone' => '628111']);
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

    private function contract(Unit $unit, Tenant $tenant, string $start, string $end, string $status = 'active'): Contract
    {
        return Contract::create([
            'unit_id' => $unit->id,
            'tenant_id' => $tenant->id,
            'start_date' => $start,
            'end_date' => $end,
            'total_price' => 3_600_000,
            'status' => $status,
        ]);
    }

    private function tenantUser(Tenant $tenant): User
    {
        $user = User::factory()->create(['role' => 'tenant']);
        $tenant->update(['user_id' => $user->id]);

        return $user;
    }

    public function test_tenant_booking_overlapping_active_contract_is_rejected()
    {
        $owner = $this->owner();
        $unit = $this->unit($owner);
        $this->contract($unit, $this->tenant('Penghuni Lama'), '2026-11-01', '2026-11-30');

        $user = $this->tenantUser($this->tenant('Penyewa Baru'));

        $this->actingAs($user, 'api')
            ->postJson('/api/contracts', [
                'unit_id' => $unit->id,
                'start_date' => '2026-11-15',
                'end_date' => '2026-12-15',
                'total_price' => 3_600_000,
            ])
            ->assertStatus(422)
            ->assertJsonPath('message', 'Tanggal yang dipilih sudah terisi atau diblokir.');
    }

    public function test_tenant_booking_overlapping_block_is_rejected()
    {
        $owner = $this->owner();
        $unit = $this->unit($owner);
        $unit->blocks()->create(['start_date' => '2026-11-20', 'end_date' => '2026-11-22']);

        $user = $this->tenantUser($this->tenant('Penyewa Baru'));

        $this->actingAs($user, 'api')
            ->postJson('/api/contracts', [
                'unit_id' => $unit->id,
                'start_date' => '2026-11-21',
                'end_date' => '2026-12-21',
                'total_price' => 3_600_000,
            ])
            ->assertStatus(422)
            ->assertJsonPath('message', 'Tanggal yang dipilih sudah terisi atau diblokir.');
    }

    public function test_tenant_booking_adjacent_to_active_contract_is_accepted()
    {
        $owner = $this->owner();
        $unit = $this->unit($owner);
        $this->contract($unit, $this->tenant('Penghuni Lama'), '2026-11-01', '2026-11-30');

        $user = $this->tenantUser($this->tenant('Penyewa Baru'));

        $this->actingAs($user, 'api')
            ->postJson('/api/contracts', [
                'unit_id' => $unit->id,
                'start_date' => '2026-12-01',
                'end_date' => '2026-12-31',
                'total_price' => 3_600_000,
            ])
            ->assertStatus(201);
    }

    public function test_owner_creates_contract_for_own_tenant()
    {
        $owner = $this->owner();
        $unit = $this->unit($owner);
        $tenant = $this->tenant('Penyewa Buatan Pemilik');
        $tenant->update(['owner_id' => $owner->id]);

        $response = $this->actingAs($owner, 'api')->postJson('/api/contracts', [
            'tenant_id' => $tenant->id,
            'unit_id' => $unit->id,
            'start_date' => '2026-11-01',
            'end_date' => '2026-12-31',
            'total_price' => 2_400_000,
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('contract.status', 'pending')
            ->assertJsonPath('contract.tenant_id', $tenant->id);
        $this->assertDatabaseHas('contracts', [
            'tenant_id' => $tenant->id,
            'unit_id' => $unit->id,
            'status' => 'pending',
        ]);
    }

    public function test_owner_cannot_create_contract_for_other_owners_tenant()
    {
        $owner = $this->owner();
        $otherOwner = $this->owner();
        $unit = $this->unit($owner);
        $otherTenant = $this->tenant('Penyewa Pemilik Lain');
        $otherTenant->update(['owner_id' => $otherOwner->id]);

        $this->actingAs($owner, 'api')
            ->postJson('/api/contracts', [
                'tenant_id' => $otherTenant->id,
                'unit_id' => $unit->id,
                'start_date' => '2026-11-01',
                'end_date' => '2026-12-31',
                'total_price' => 2_400_000,
            ])
            ->assertStatus(403);
        $this->assertDatabaseCount('contracts', 0);
    }

    public function test_owner_cannot_create_contract_for_other_owners_unit()
    {
        $owner = $this->owner();
        $otherOwner = $this->owner();
        $otherUnit = $this->unit($otherOwner);
        $tenant = $this->tenant('Penyewa Sendiri');
        $tenant->update(['owner_id' => $owner->id]);

        $this->actingAs($owner, 'api')
            ->postJson('/api/contracts', [
                'tenant_id' => $tenant->id,
                'unit_id' => $otherUnit->id,
                'start_date' => '2026-11-01',
                'end_date' => '2026-12-31',
                'total_price' => 2_400_000,
            ])
            ->assertStatus(403);
        $this->assertDatabaseCount('contracts', 0);
    }

    public function test_two_pending_contracts_can_coexist_but_only_one_activates()
    {
        $owner = $this->owner();
        $unit = $this->unit($owner);
        $firstTenant = $this->tenant('Penyewa Satu');
        $secondTenant = $this->tenant('Penyewa Dua');
        $firstTenant->update(['owner_id' => $owner->id]);
        $secondTenant->update(['owner_id' => $owner->id]);

        $first = $this->actingAs($owner, 'api')->postJson('/api/contracts', [
            'tenant_id' => $firstTenant->id,
            'unit_id' => $unit->id,
            'start_date' => '2026-11-15',
            'end_date' => '2026-12-15',
            'total_price' => 3_000_000,
        ])->assertStatus(201)->json('contract.id');

        $second = $this->actingAs($owner, 'api')->postJson('/api/contracts', [
            'tenant_id' => $secondTenant->id,
            'unit_id' => $unit->id,
            'start_date' => '2026-12-10',
            'end_date' => '2027-01-10',
            'total_price' => 3_000_000,
        ])->assertStatus(201)->json('contract.id');

        $this->actingAs($owner, 'api')
            ->putJson("/api/contracts/{$first}", ['status' => 'active'])
            ->assertStatus(200);

        $this->actingAs($owner, 'api')
            ->putJson("/api/contracts/{$second}", ['status' => 'active'])
            ->assertStatus(422)
            ->assertJsonPath('message', 'Tanggal yang dipilih sudah terisi atau diblokir.');
        $this->assertDatabaseHas('contracts', ['id' => $second, 'status' => 'pending']);
    }

    public function test_activating_future_contract_leaves_unit_available()
    {
        $owner = $this->owner();
        $unit = $this->unit($owner);
        $contract = $this->contract($unit, $this->tenant(), '2026-12-01', '2027-01-31', 'pending');

        $this->actingAs($owner, 'api')
            ->putJson("/api/contracts/{$contract->id}", ['status' => 'active'])
            ->assertStatus(200);
        $this->assertDatabaseHas('units', ['id' => $unit->id, 'status' => 'available']);
    }

    public function test_activating_started_contract_marks_unit_rented()
    {
        $owner = $this->owner();
        $unit = $this->unit($owner);
        $contract = $this->contract($unit, $this->tenant(), '2026-10-01', '2026-12-31', 'pending');

        $this->actingAs($owner, 'api')
            ->putJson("/api/contracts/{$contract->id}", ['status' => 'active'])
            ->assertStatus(200);
        $this->assertDatabaseHas('units', ['id' => $unit->id, 'status' => 'rented']);
    }

    public function test_contract_index_includes_nested_tenant_ktp_status()
    {
        $owner = $this->owner();
        $unit = $this->unit($owner);
        $tenant = $this->tenant('Penyewa KTP');
        $tenant->update(['ktp_status' => 'verified']);
        $this->contract($unit, $tenant, '2026-11-01', '2026-12-31');

        $this->actingAs($owner, 'api')
            ->getJson('/api/contracts?status=all')
            ->assertStatus(200)
            ->assertJsonPath('data.0.tenant.ktp_status', 'verified');
    }
}
