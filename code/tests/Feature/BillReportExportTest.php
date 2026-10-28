<?php

namespace Tests\Feature;

use App\Models\Contract;
use App\Models\Payment;
use App\Models\Tenant;
use App\Models\Unit;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class BillReportExportTest extends TestCase
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

    private function payment(Contract $contract, string $month, string $status, string $proof = ''): Payment
    {
        $payment = new Payment([
            'contract_id' => $contract->id,
            'month' => $month,
            'amount' => 2500000,
            'status' => $status,
            'proof_url' => $proof,
        ]);
        $payment->created_at = Carbon::parse('2026-09-05 09:00:00');
        $payment->save();

        return $payment;
    }

    private function download(User $user, string $path = '/api/payments/export'): TestResponse
    {
        return $this->actingAs($user, 'api')->get($path);
    }

    /** @return list<string> */
    private function csvRows(TestResponse $response): array
    {
        return explode("\r\n", rtrim(substr($response->streamedContent(), 3), "\r\n"));
    }

    public function test_tenant_receives_paid_and_unpaid_months(): void
    {
        $owner = $this->owner();
        $unit = $this->unit($owner, 'Kos Uji');
        [$user, $tenant] = $this->tenant();
        $contract = $this->contract($unit, $tenant, '2026-09-01', '2027-02-28', 15000000);
        $this->payment($contract, '2026-09', 'paid', '/storage/proofs/bukti-sep.jpg');

        $response = $this->actingAs($user, 'api')
            ->withHeaders(['Origin' => 'http://127.0.0.1:5173'])
            ->get('/api/payments/export');

        $response->assertOk()
            ->assertHeader('content-type', 'text/csv; charset=UTF-8');

        $this->assertStringContainsString('laporan-tagihan-20261028.csv', $response->headers->get('content-disposition'));
        $this->assertStringContainsString('Content-Disposition', $response->headers->get('access-control-expose-headers'));

        $this->assertSame([
            'Bulan;Unit;Pemilik;Jumlah;Status;Bukti;Diunggah',
            '2026-09;Kos Uji;Pak Wahyu;2500000;Lunas;bukti-sep.jpg;2026-09-05 09:00',
            '2026-10;Kos Uji;Pak Wahyu;2500000;Belum Bayar;;',
        ], $this->csvRows($response));
    }

    public function test_reupload_yields_single_row(): void
    {
        $owner = $this->owner();
        $unit = $this->unit($owner);
        [$user, $tenant] = $this->tenant();
        $contract = $this->contract($unit, $tenant, '2026-09-01', '2027-02-28', 15000000);
        $this->payment($contract, '2026-09', 'paid', '/storage/proofs/bukti-lama.jpg');
        $this->payment($contract, '2026-09', 'rejected');

        $rows = $this->csvRows($this->download($user));

        $this->assertCount(3, $rows);
        $this->assertStringContainsString('2026-09;Kos Uji;Pak Wahyu;2500000;Ditolak;;', $rows[1]);
        $this->assertStringNotContainsString('Lunas', implode("\n", $rows));
    }

    public function test_owner_only_sees_contracts_of_owned_units(): void
    {
        $owner = $this->owner('Pak Wahyu');
        $unit = $this->unit($owner, 'Kos Sendiri');
        [$tenantUser, $tenant] = $this->tenant('Siti Sarah');
        $this->contract($unit, $tenant, '2026-09-01', '2027-02-28', 15000000);

        $other = $this->owner('Bu Rina');
        $otherUnit = $this->unit($other, 'Kos Orang');
        [$otherUser, $otherTenant] = $this->tenant('Budi Lain');
        $this->contract($otherUnit, $otherTenant, '2026-09-01', '2027-02-28', 12000000);

        $rows = $this->csvRows($this->download($owner));
        $content = implode("\n", $rows);

        $this->assertSame('Bulan;Unit;Penyewa;Jumlah;Status;Bukti;Diunggah', $rows[0]);
        $this->assertStringContainsString('Kos Sendiri', $content);
        $this->assertStringNotContainsString('Kos Orang', $content);
        $this->assertStringNotContainsString('Budi Lain', $content);
    }

    public function test_months_after_current_month_are_not_billed(): void
    {
        $owner = $this->owner();
        $unit = $this->unit($owner);
        [$user, $tenant] = $this->tenant();
        $this->contract($unit, $tenant, '2026-09-01', '2027-02-28', 15000000);

        $rows = $this->csvRows($this->download($user));

        $this->assertCount(3, $rows);
        $this->assertStringNotContainsString('2026-11', implode("\n", $rows));
    }

    public function test_tenant_without_profile_gets_404_json(): void
    {
        $user = User::factory()->create(['role' => 'tenant']);

        $response = $this->download($user);

        $response->assertStatus(404)
            ->assertHeader('content-type', 'application/json');
    }

    public function test_other_roles_are_rejected_403(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->download($admin)->assertStatus(403);
    }

    public function test_without_token_401(): void
    {
        $this->getJson('/api/payments/export')->assertStatus(401);
    }

    public function test_contract_starting_next_month_yields_header_only(): void
    {
        $owner = $this->owner();
        $unit = $this->unit($owner);
        [$user, $tenant] = $this->tenant();
        $this->contract($unit, $tenant, '2026-11-01', '2027-01-31', 9000000);

        $response = $this->download($user);

        $this->assertSame(
            "\xEF\xBB\xBFBulan;Unit;Pemilik;Jumlah;Status;Bukti;Diunggah\r\n",
            $response->streamedContent(),
        );
    }

    public function test_inactive_contracts_are_not_billed(): void
    {
        $owner = $this->owner();
        $activeUnit = $this->unit($owner, 'Kos Aktif');
        $completedUnit = $this->unit($owner, 'Kos Selesai');
        [$user, $tenant] = $this->tenant();
        $this->contract($activeUnit, $tenant, '2026-09-01', '2027-02-28', 15000000);
        $this->contract($completedUnit, $tenant, '2026-09-01', '2027-02-28', 12000000, 'completed');

        $content = implode("\n", $this->csvRows($this->download($owner)));

        $this->assertStringContainsString('Kos Aktif', $content);
        $this->assertStringNotContainsString('Kos Selesai', $content);
    }

    public function test_formula_like_names_are_prefixed_with_quote(): void
    {
        $owner = $this->owner();
        $unit = $this->unit($owner, '=1+1');
        [, $tenant] = $this->tenant('@SUM(A1)');
        $this->contract($unit, $tenant, '2026-09-01', '2027-02-28', 15000000);

        $rows = $this->csvRows($this->download($owner));

        $this->assertStringContainsString("'=1+1;'@SUM(A1);2500000;Belum Bayar;;", $rows[1]);
    }

    public function test_owner_receives_all_rows_without_pagination(): void
    {
        $owner = $this->owner();
        $unit = $this->unit($owner);
        [, $tenant] = $this->tenant();

        for ($i = 1; $i <= 12; $i++) {
            $this->contract($unit, $tenant, '2026-10-01', '2026-10-01', 2500000);
        }

        $this->assertCount(13, $this->csvRows($this->download($owner)));
    }
}