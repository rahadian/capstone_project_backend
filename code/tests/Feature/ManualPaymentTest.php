<?php

namespace Tests\Feature;

use App\Models\Contract;
use App\Models\Payment;
use App\Models\Tenant;
use App\Models\Unit;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ManualPaymentTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
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

    private function unit(User $owner): Unit
    {
        return Unit::create([
            'owner_id' => $owner->id,
            'name' => 'Kos A',
            'address' => 'Bandung',
            'price' => 1_200_000,
            'status' => 'rented',
        ]);
    }

    private function contract(Unit $unit, string $start = '2026-10-01', string $end = '2026-12-31'): Contract
    {
        $tenant = Tenant::create(['name' => 'Penyewa A']);

        return Contract::create([
            'unit_id' => $unit->id,
            'tenant_id' => $tenant->id,
            'start_date' => $start,
            'end_date' => $end,
            'total_price' => 3_600_000,
            'status' => 'active',
        ]);
    }

    private function payment(Contract $contract, string $month, string $status): Payment
    {
        return Payment::create([
            'contract_id' => $contract->id,
            'month' => $month,
            'amount' => 1_200_000,
            'status' => $status,
            'proof_url' => $status === 'paid' ? null : '/storage/proofs/bukti.jpg',
        ]);
    }

    public function test_owner_records_cash_payment()
    {
        $owner = $this->owner();
        $contract = $this->contract($this->unit($owner));

        $response = $this->actingAs($owner, 'api')->postJson('/api/payments', [
            'contract_id' => $contract->id,
            'month' => '2026-10',
            'amount' => 1_200_000,
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('payment.status', 'paid')
            ->assertJsonPath('payment.proof_url', null);
        $this->assertDatabaseHas('payments', [
            'contract_id' => $contract->id,
            'month' => '2026-10',
            'status' => 'paid',
            'recorded_by' => $owner->id,
            'proof_url' => null,
        ]);
    }

    public function test_owner_cannot_record_payment_for_other_owners_contract()
    {
        $owner = $this->owner();
        $otherOwner = $this->owner();
        $contract = $this->contract($this->unit($otherOwner));

        $this->actingAs($owner, 'api')
            ->postJson('/api/payments', [
                'contract_id' => $contract->id,
                'month' => '2026-10',
                'amount' => 1_200_000,
            ])
            ->assertStatus(403);
        $this->assertDatabaseCount('payments', 0);
    }

    public function test_owner_cannot_record_duplicate_month()
    {
        $owner = $this->owner();
        $contract = $this->contract($this->unit($owner));
        $this->payment($contract, '2026-10', 'paid');

        $this->actingAs($owner, 'api')
            ->postJson('/api/payments', [
                'contract_id' => $contract->id,
                'month' => '2026-10',
                'amount' => 1_200_000,
            ])
            ->assertStatus(409)
            ->assertJsonPath('message', 'Bulan ini sudah memiliki pembayaran.');
    }

    public function test_owner_cannot_record_pending_month()
    {
        $owner = $this->owner();
        $contract = $this->contract($this->unit($owner));
        $this->payment($contract, '2026-10', 'pending');

        $this->actingAs($owner, 'api')
            ->postJson('/api/payments', [
                'contract_id' => $contract->id,
                'month' => '2026-10',
                'amount' => 1_200_000,
            ])
            ->assertStatus(409);
    }

    public function test_owner_can_re_record_rejected_month()
    {
        $owner = $this->owner();
        $contract = $this->contract($this->unit($owner));
        $this->payment($contract, '2026-10', 'rejected');

        $this->actingAs($owner, 'api')
            ->postJson('/api/payments', [
                'contract_id' => $contract->id,
                'month' => '2026-10',
                'amount' => 1_200_000,
            ])
            ->assertStatus(201);
        $this->assertDatabaseCount('payments', 2);
    }

    public function test_month_outside_billing_period_is_rejected()
    {
        $owner = $this->owner();
        $contract = $this->contract($this->unit($owner), '2026-11-15', '2027-02-14');

        $this->actingAs($owner, 'api')
            ->postJson('/api/payments', [
                'contract_id' => $contract->id,
                'month' => '2027-02',
                'amount' => 1_200_000,
            ])
            ->assertStatus(422)
            ->assertJsonPath('message', 'Bulan tidak termasuk masa tagihan kontrak.');
        $this->assertDatabaseCount('payments', 0);
    }

    public function test_future_month_is_rejected()
    {
        $owner = $this->owner();
        $contract = $this->contract($this->unit($owner), '2026-10-01', '2027-01-31');

        $this->actingAs($owner, 'api')
            ->postJson('/api/payments', [
                'contract_id' => $contract->id,
                'month' => '2026-11',
                'amount' => 1_200_000,
            ])
            ->assertStatus(422)
            ->assertJsonPath('message', 'Bulan tagihan belum berjalan.');
        $this->assertDatabaseCount('payments', 0);
    }

    public function test_amount_must_be_positive()
    {
        $owner = $this->owner();
        $contract = $this->contract($this->unit($owner));

        $this->actingAs($owner, 'api')
            ->postJson('/api/payments', [
                'contract_id' => $contract->id,
                'month' => '2026-10',
                'amount' => 0,
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('amount');
    }

    public function test_tenant_flow_still_requires_proof()
    {
        $owner = $this->owner();
        $contract = $this->contract($this->unit($owner));
        $tenantUser = User::factory()->create(['role' => 'tenant']);
        $contract->tenant->update(['user_id' => $tenantUser->id]);

        $this->actingAs($tenantUser, 'api')
            ->postJson('/api/payments', [
                'contract_id' => $contract->id,
                'month' => '2026-10',
                'amount' => 1_200_000,
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('proof');
    }
}
