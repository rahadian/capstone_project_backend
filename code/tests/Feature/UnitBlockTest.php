<?php

namespace Tests\Feature;

use App\Models\Contract;
use App\Models\Tenant;
use App\Models\Unit;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UnitBlockTest extends TestCase
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

    private function unit(User $owner, array $overrides = []): Unit
    {
        return Unit::create(array_merge([
            'owner_id' => $owner->id,
            'name' => 'Kos A',
            'address' => 'Bandung',
            'price' => 1_200_000,
            'status' => 'available',
        ], $overrides));
    }

    private function activeContract(Unit $unit, string $start, string $end): Contract
    {
        $tenant = Tenant::create(['name' => 'Penyewa Kontrak']);

        return Contract::create([
            'unit_id' => $unit->id,
            'tenant_id' => $tenant->id,
            'start_date' => $start,
            'end_date' => $end,
            'total_price' => 3_600_000,
            'status' => 'active',
        ]);
    }

    public function test_owner_sees_blocks_of_owned_unit()
    {
        $owner = $this->owner();
        $unit = $this->unit($owner);
        $unit->blocks()->create([
            'start_date' => '2026-11-03',
            'end_date' => '2026-11-04',
            'note' => 'Renovasi',
            'created_by' => $owner->id,
        ]);

        $response = $this->actingAs($owner, 'api')->getJson("/api/units/{$unit->id}/blocks");

        $response->assertStatus(200)
            ->assertJsonPath('data.0.note', 'Renovasi')
            ->assertJsonPath('data.0.start_date', '2026-11-03')
            ->assertJsonPath('data.0.end_date', '2026-11-04');
    }

    public function test_other_owner_cannot_see_blocks()
    {
        $owner = $this->owner();
        $other = $this->owner();
        $unit = $this->unit($owner);

        $response = $this->actingAs($other, 'api')->getJson("/api/units/{$unit->id}/blocks");

        $response->assertStatus(403)
            ->assertJsonPath('message', 'Unauthorized access');
    }

    public function test_block_dates_must_be_ordered_and_not_in_the_past()
    {
        $owner = $this->owner();
        $unit = $this->unit($owner);

        $this->actingAs($owner, 'api')
            ->postJson("/api/units/{$unit->id}/blocks", [
                'start_date' => '2026-11-05',
                'end_date' => '2026-11-01',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('end_date');

        $this->actingAs($owner, 'api')
            ->postJson("/api/units/{$unit->id}/blocks", [
                'start_date' => '2026-10-29',
                'end_date' => '2026-10-28',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('end_date');
    }

    public function test_block_overlapping_active_contract_is_rejected()
    {
        $owner = $this->owner();
        $unit = $this->unit($owner);
        $this->activeContract($unit, '2026-11-01', '2026-11-10');

        $response = $this->actingAs($owner, 'api')
            ->postJson("/api/units/{$unit->id}/blocks", [
                'start_date' => '2026-11-05',
                'end_date' => '2026-11-07',
            ]);

        $response->assertStatus(422)
            ->assertJsonPath('message', 'Tanggal yang dipilih sudah terisi atau diblokir.');
        $this->assertDatabaseCount('unit_blocks', 0);
    }

    public function test_block_boundary_is_inclusive()
    {
        $owner = $this->owner();
        $unit = $this->unit($owner);
        $this->activeContract($unit, '2026-11-01', '2026-11-10');

        $this->actingAs($owner, 'api')
            ->postJson("/api/units/{$unit->id}/blocks", [
                'start_date' => '2026-11-10',
                'end_date' => '2026-11-12',
            ])
            ->assertStatus(422)
            ->assertJsonPath('message', 'Tanggal yang dipilih sudah terisi atau diblokir.');
    }

    public function test_block_starting_day_after_active_contract_is_accepted()
    {
        $owner = $this->owner();
        $unit = $this->unit($owner);
        $this->activeContract($unit, '2026-11-01', '2026-11-10');

        $this->actingAs($owner, 'api')
            ->postJson("/api/units/{$unit->id}/blocks", [
                'start_date' => '2026-11-11',
                'end_date' => '2026-11-12',
            ])
            ->assertStatus(201)
            ->assertJsonPath('message', 'Tanggal berhasil diblokir.');
    }

    public function test_blocked_dates_appear_in_booked_and_blocked_dates()
    {
        $owner = $this->owner();
        $unit = $this->unit($owner);

        $this->actingAs($owner, 'api')
            ->postJson("/api/units/{$unit->id}/blocks", [
                'start_date' => '2026-11-03',
                'end_date' => '2026-11-04',
            ])
            ->assertStatus(201);

        $response = $this->getJson("/api/detail/unit/{$unit->id}");

        $response->assertStatus(200)
            ->assertJsonPath('booked_dates', ['2026-11-03', '2026-11-04'])
            ->assertJsonPath('blocked_dates', ['2026-11-03', '2026-11-04']);
    }

    public function test_owner_can_delete_block_and_other_owner_cannot()
    {
        $owner = $this->owner();
        $other = $this->owner();
        $unit = $this->unit($owner);
        $block = $unit->blocks()->create([
            'start_date' => '2026-11-03',
            'end_date' => '2026-11-04',
            'created_by' => $owner->id,
        ]);

        $this->actingAs($other, 'api')
            ->deleteJson("/api/units/{$unit->id}/blocks/{$block->id}")
            ->assertStatus(403);
        $this->assertDatabaseHas('unit_blocks', ['id' => $block->id]);

        $this->actingAs($owner, 'api')
            ->deleteJson("/api/units/{$unit->id}/blocks/{$block->id}")
            ->assertStatus(204);
        $this->assertDatabaseMissing('unit_blocks', ['id' => $block->id]);
    }

    public function test_owner_can_patch_unit_status()
    {
        $owner = $this->owner();
        $unit = $this->unit($owner);

        $response = $this->actingAs($owner, 'api')
            ->patchJson("/api/units/{$unit->id}/status", ['status' => 'maintenance']);

        $response->assertStatus(200)
            ->assertJsonPath('message', 'Status unit berhasil diperbarui.')
            ->assertJsonPath('unit.status', 'maintenance');
        $this->assertDatabaseHas('units', ['id' => $unit->id, 'status' => 'maintenance']);
    }

    public function test_tenant_cannot_patch_unit_status()
    {
        $owner = $this->owner();
        $tenantUser = User::factory()->create(['role' => 'tenant']);
        $unit = $this->unit($owner);

        $this->actingAs($tenantUser, 'api')
            ->patchJson("/api/units/{$unit->id}/status", ['status' => 'maintenance'])
            ->assertStatus(403);
    }
}
