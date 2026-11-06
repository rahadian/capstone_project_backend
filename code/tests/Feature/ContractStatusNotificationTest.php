<?php

namespace Tests\Feature;

use App\Models\Contract;
use App\Models\Tenant;
use App\Models\Unit;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/* Notifikasi siklus kontrak: pengajuan penyewa diteruskan ke pemilik, dan
   keputusan pemilik (setuju/tolak) diteruskan ke penyewa. Kanalnya `database`;
   pola dan pesan dikunci di sini seperti pada PaymentDueReminder. */
class ContractStatusNotificationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-11-06 08:00:00');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function pihak(): array
    {
        $owner = User::factory()->create(['role' => 'owner']);
        $tenantUser = User::factory()->create(['role' => 'tenant']);
        $unit = Unit::create([
            'owner_id' => $owner->id,
            'name' => 'Kos A',
            'address' => 'Bandung',
            'price' => 1_200_000,
            'status' => 'available',
        ]);
        $tenant = Tenant::create([
            'user_id' => $tenantUser->id,
            'owner_id' => $owner->id,
            'name' => 'Penyewa A',
            'phone' => '628111',
        ]);

        return [$owner, $tenantUser, $unit, $tenant];
    }

    private function contract(Unit $unit, Tenant $tenant, string $status): Contract
    {
        return Contract::create([
            'unit_id' => $unit->id,
            'tenant_id' => $tenant->id,
            'start_date' => '2026-12-01',
            'end_date' => '2027-05-31',
            'total_price' => 6_000_000,
            'status' => $status,
        ]);
    }

    public function test_pengajuan_dari_penyewa_memberi_notifikasi_ke_pemilik(): void
    {
        [$owner, $tenantUser, $unit] = $this->pihak();

        $this->actingAs($tenantUser, 'api')
            ->postJson('/api/contracts', [
                'unit_id' => $unit->id,
                'start_date' => '2026-12-01',
                'end_date' => '2027-05-31',
                'total_price' => 6_000_000,
            ])
            ->assertStatus(201);

        $this->assertDatabaseCount('notifications', 1);
        $notif = $owner->notifications()->first();
        $this->assertSame('contract_status', $notif->data['type']);
        $this->assertSame('Penyewa Penyewa A mengajukan sewa unit Kos A.', $notif->data['message']);
    }

    public function test_kontrak_buatan_pemilik_tidak_dinotifikasi(): void
    {
        [$owner, , $unit, $tenant] = $this->pihak();

        $this->actingAs($owner, 'api')
            ->postJson('/api/contracts', [
                'unit_id' => $unit->id,
                'tenant_id' => $tenant->id,
                'start_date' => '2026-12-01',
                'end_date' => '2027-05-31',
                'total_price' => 6_000_000,
            ])
            ->assertStatus(201);

        $this->assertDatabaseCount('notifications', 0);
    }

    public function test_persetujuan_memberi_notifikasi_ke_penyewa(): void
    {
        [$owner, $tenantUser, $unit, $tenant] = $this->pihak();
        $contract = $this->contract($unit, $tenant, 'pending');

        $this->actingAs($owner, 'api')
            ->putJson("/api/contracts/{$contract->id}", ['status' => 'active'])
            ->assertStatus(200);

        $this->assertDatabaseCount('notifications', 1);
        $notif = $tenantUser->notifications()->first();
        $this->assertSame('contract_status', $notif->data['type']);
        $this->assertSame('Pengajuan sewa unit Kos A disetujui.', $notif->data['message']);
        $this->assertSame($contract->id, $notif->data['contract_id']);
    }

    public function test_penolakan_memberi_notifikasi_ke_penyewa(): void
    {
        [$owner, $tenantUser, $unit, $tenant] = $this->pihak();
        $contract = $this->contract($unit, $tenant, 'pending');

        $this->actingAs($owner, 'api')
            ->putJson("/api/contracts/{$contract->id}", ['status' => 'cancelled'])
            ->assertStatus(200);

        $this->assertDatabaseCount('notifications', 1);
        $notif = $tenantUser->notifications()->first();
        $this->assertSame('Pengajuan sewa unit Kos A ditolak.', $notif->data['message']);
    }

    /* Penghentian dini bukan penolakan pengajuan; tidak dinotifikasi. */
    public function test_penghentian_dini_tidak_memberi_notifikasi(): void
    {
        [$owner, $tenantUser, $unit, $tenant] = $this->pihak();
        $contract = $this->contract($unit, $tenant, 'active');

        $this->actingAs($owner, 'api')
            ->putJson("/api/contracts/{$contract->id}", [
                'status' => 'cancelled',
                'ended_at' => '2026-12-15',
                'end_reason' => 'berhenti_penyewa',
            ])
            ->assertStatus(200);

        $this->assertDatabaseCount('notifications', 0);
    }
}
