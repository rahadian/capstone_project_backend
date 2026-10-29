<?php

namespace Tests\Feature;

use App\Models\Contract;
use App\Models\Tenant;
use App\Models\Unit;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class ContractReportExportTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(Carbon::parse('2026-10-28 10:00:00'));
    }

    protected function tearDown(): void
    {
        $this->travelBack();
        parent::tearDown();
    }

    private function owner(string $name = 'Pak Wahyu'): User
    {
        return User::factory()->create(['role' => 'owner', 'name' => $name]);
    }

    /** @return array{0: User, 1: Tenant} */
    private function tenant(string $name = 'Siti Sarah'): array
    {
        $user = User::factory()->create(['role' => 'tenant', 'name' => $name]);
        $tenant = Tenant::create(['user_id' => $user->id, 'name' => $name, 'phone' => '081200000000']);

        return [$user, $tenant];
    }

    private function unit(User $owner, string $name = 'Kos Uji'): Unit
    {
        return Unit::create([
            'owner_id' => $owner->id,
            'name' => $name,
            'address' => 'Jl. Uji 1',
            'price' => 2500000,
            'status' => 'rented',
        ]);
    }

    private function contract(Unit $unit, Tenant $tenant, string $from, string $until, float $total, string $status = 'active'): Contract
    {
        return Contract::create([
            'unit_id' => $unit->id,
            'tenant_id' => $tenant->id,
            'start_date' => $from,
            'end_date' => $until,
            'total_price' => $total,
            'status' => $status,
        ]);
    }

    private function download(User $user): TestResponse
    {
        return $this->actingAs($user, 'api')->get('/api/contracts/export');
    }

    /** @return list<string> */
    private function csvRows(TestResponse $response): array
    {
        return explode("\r\n", rtrim(substr($response->streamedContent(), 3), "\r\n"));
    }

    public function test_owner_receives_all_statuses_and_contract_columns(): void
    {
        $owner = $this->owner();
        $unit = $this->unit($owner);
        [, $tenant] = $this->tenant();
        $contract = $this->contract($unit, $tenant, '2026-09-01', '2027-02-28', 15000000);

        $response = $this->download($owner);

        $response->assertOk()
            ->assertHeader('content-type', 'text/csv; charset=UTF-8');

        $this->assertStringContainsString('laporan-kontrak-20261028.csv', $response->headers->get('content-disposition'));

        $rows = $this->csvRows($response);

        $this->assertSame([
            'ID;Penyewa;Unit;Pemilik;Mulai;Selesai;Durasi;Harga per Bulan;Nilai Kontrak;Status',
            "{$contract->id};Siti Sarah;Kos Uji;Pak Wahyu;2026-09-01;2027-02-28;6 bulan;2500000;15000000;Aktif",
        ], $rows);
        $this->assertStringNotContainsString('KTP', implode("\n", $rows));
    }

    public function test_all_contract_statuses_included(): void
    {
        $owner = $this->owner();
        $unit = $this->unit($owner);
        [, $tenant] = $this->tenant();
        $this->contract($unit, $tenant, '2026-09-01', '2027-02-28', 15000000, 'active');
        $this->contract($unit, $tenant, '2026-09-01', '2027-02-28', 12000000, 'pending');
        $this->contract($unit, $tenant, '2026-09-01', '2027-02-28', 12000000, 'completed');
        $this->contract($unit, $tenant, '2026-09-01', '2027-02-28', 12000000, 'cancelled');

        $content = implode("\n", $this->csvRows($this->download($owner)));

        foreach (['Menunggu', 'Aktif', 'Selesai', 'Dibatalkan'] as $label) {
            $this->assertStringContainsString($label, $content);
        }
    }

    public function test_contracts_of_other_owners_excluded(): void
    {
        $owner = $this->owner('Pak Wahyu');
        $unit = $this->unit($owner, 'Kos Sendiri');
        [, $tenant] = $this->tenant();
        $this->contract($unit, $tenant, '2026-09-01', '2027-02-28', 15000000);

        $other = $this->owner('Bu Rina');
        $otherUnit = $this->unit($other, 'Kos Orang');
        [, $otherTenant] = $this->tenant('Budi Lain');
        $this->contract($otherUnit, $otherTenant, '2026-09-01', '2027-02-28', 12000000);

        $content = implode("\n", $this->csvRows($this->download($owner)));

        $this->assertStringContainsString('Kos Sendiri', $content);
        $this->assertStringNotContainsString('Kos Orang', $content);
        $this->assertStringNotContainsString('Budi Lain', $content);
    }

    public function test_all_rows_sent_without_pagination(): void
    {
        $owner = $this->owner();
        $unit = $this->unit($owner);
        [, $tenant] = $this->tenant();

        for ($i = 1; $i <= 12; $i++) {
            $this->contract($unit, $tenant, '2026-09-01', '2027-02-28', 15000000);
        }

        $this->assertCount(13, $this->csvRows($this->download($owner)));
    }

    public function test_tenant_rejected_403(): void
    {
        [$user] = $this->tenant();

        $this->download($user)->assertStatus(403);
    }

    public function test_without_token_401(): void
    {
        $this->getJson('/api/contracts/export')->assertStatus(401);
    }
}