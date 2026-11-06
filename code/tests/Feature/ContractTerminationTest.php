<?php

namespace Tests\Feature;

use App\Models\Contract;
use App\Models\Tenant;
use App\Models\Unit;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/* Penghentian sewa: kontrak aktif yang ditutup sebelum tanggal selesainya wajib
   beralasan, sedangkan kontrak yang ditutup pada atau setelah tanggal selesainya
   dicatat sebagai selesai normal tanpa meminta apa pun. */
class ContractTerminationTest extends TestCase
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

    private function contract(string $status = 'active', string $start = '2026-09-01', string $end = '2027-02-28'): array
    {
        $owner = $this->owner();
        $unit = Unit::create([
            'owner_id' => $owner->id,
            'name' => 'Kos A',
            'address' => 'Bandung',
            'price' => 1_200_000,
            'status' => 'rented',
        ]);
        $tenant = Tenant::create(['name' => 'Penyewa A', 'phone' => '628111']);
        $contract = Contract::create([
            'unit_id' => $unit->id,
            'tenant_id' => $tenant->id,
            'start_date' => $start,
            'end_date' => $end,
            'total_price' => 3_600_000,
            'status' => $status,
        ]);

        return [$owner, $contract];
    }

    public function test_early_termination_requires_a_reason(): void
    {
        [$owner, $contract] = $this->contract();

        $this->actingAs($owner, 'api')
            ->putJson("/api/contracts/{$contract->id}", ['status' => 'completed'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['end_reason']);

        $this->assertDatabaseHas('contracts', ['id' => $contract->id, 'status' => 'active']);
    }

    /* Layar pemilik menampilkan `message` dari 422 apa adanya. Bawaan Laravel
       berbahasa Inggris, jadi pesannya diterjemahkan di controller dan dikunci
       di sini supaya tidak diam-diam kembali ke bahasa Inggris. */
    public function test_validation_messages_are_indonesian(): void
    {
        [$owner, $contract] = $this->contract();

        $this->actingAs($owner, 'api')
            ->putJson("/api/contracts/{$contract->id}", ['status' => 'completed', 'ended_at' => '2026-11-15'])
            ->assertStatus(422)
            ->assertJsonPath('message', 'Alasan penghentian wajib dipilih.');

        $this->actingAs($owner, 'api')
            ->putJson("/api/contracts/{$contract->id}", [
                'status' => 'completed',
                'ended_at' => '2026-11-15',
                'end_reason' => 'lainnya',
            ])
            ->assertStatus(422)
            ->assertJsonPath('message', 'Catatan wajib diisi bila alasannya Lainnya.');

        $this->actingAs($owner, 'api')
            ->putJson("/api/contracts/{$contract->id}", [
                'status' => 'completed',
                'ended_at' => '2026-08-01',
                'end_reason' => 'pelanggaran',
            ])
            ->assertStatus(422)
            ->assertJsonPath('message', 'Tanggal penghentian tidak boleh sebelum masa sewa.');
    }

    public function test_early_termination_stores_date_reason_and_note(): void
    {
        [$owner, $contract] = $this->contract();

        $this->actingAs($owner, 'api')
            ->putJson("/api/contracts/{$contract->id}", [
                'status' => 'completed',
                'ended_at' => '2026-11-15',
                'end_reason' => 'berhenti_penyewa',
                'end_note' => 'Pindah kerja ke luar kota.',
            ])
            ->assertStatus(200)
            ->assertJsonPath('contract.status', 'completed')
            ->assertJsonPath('contract.end_reason', 'berhenti_penyewa');

        $this->assertDatabaseHas('contracts', [
            'id' => $contract->id,
            'status' => 'completed',
            'end_reason' => 'berhenti_penyewa',
        ]);
        $this->assertEquals('2026-11-15', $contract->fresh()->ended_at->toDateString());
    }

    public function test_other_reason_requires_a_note(): void
    {
        [$owner, $contract] = $this->contract();

        $this->actingAs($owner, 'api')
            ->putJson("/api/contracts/{$contract->id}", [
                'status' => 'completed',
                'ended_at' => '2026-11-15',
                'end_reason' => 'lainnya',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['end_note']);
    }

    public function test_ended_at_must_be_inside_the_lease(): void
    {
        [$owner, $contract] = $this->contract();

        foreach (['2026-08-31', '2027-03-01'] as $tanggal) {
            $this->actingAs($owner, 'api')
                ->putJson("/api/contracts/{$contract->id}", [
                    'status' => 'completed',
                    'ended_at' => $tanggal,
                    'end_reason' => 'pelanggaran',
                ])
                ->assertStatus(422)
                ->assertJsonValidationErrors(['ended_at']);
        }
    }

    public function test_closing_on_or_after_end_date_counts_as_normal_completion(): void
    {
        [$owner, $contract] = $this->contract('active', '2026-08-01', '2026-10-01');

        $this->actingAs($owner, 'api')
            ->putJson("/api/contracts/{$contract->id}", ['status' => 'completed'])
            ->assertStatus(200)
            ->assertJsonPath('contract.end_reason', 'selesai_normal');

        $this->assertEquals('2026-10-01', $contract->fresh()->ended_at->toDateString());
    }

    public function test_rejecting_a_proposal_needs_no_reason(): void
    {
        [$owner, $contract] = $this->contract('pending');

        $this->actingAs($owner, 'api')
            ->putJson("/api/contracts/{$contract->id}", ['status' => 'cancelled'])
            ->assertStatus(200);

        $contract->refresh();
        $this->assertSame('cancelled', $contract->status);
        $this->assertNull($contract->ended_at);
        $this->assertNull($contract->end_reason);
    }

    public function test_reactivating_clears_the_termination(): void
    {
        [$owner, $contract] = $this->contract();
        $contract->update([
            'ended_at' => '2026-11-15',
            'end_reason' => 'berhenti_penyewa',
            'end_note' => 'Pindah.',
        ]);

        $this->actingAs($owner, 'api')
            ->putJson("/api/contracts/{$contract->id}", ['status' => 'active'])
            ->assertStatus(200);

        $contract->refresh();
        $this->assertNull($contract->ended_at);
        $this->assertNull($contract->end_reason);
        $this->assertNull($contract->end_note);
    }
}
