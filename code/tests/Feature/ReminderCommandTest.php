<?php

namespace Tests\Feature;

use App\Models\Contract;
use App\Models\Payment;
use App\Models\Tenant;
use App\Models\Unit;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ReminderCommandTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow(Carbon::parse('2026-11-03 07:00:00', 'Asia/Jakarta'));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function contract(array $overrides = []): Contract
    {
        $owner = User::factory()->create(['role' => 'owner']);
        $unit = Unit::create([
            'owner_id' => $owner->id,
            'name' => 'Kos A',
            'address' => 'Bandung',
            'price' => 1_200_000,
            'status' => 'rented',
        ]);
        $tenantUserId = array_key_exists('tenant_user_id', $overrides)
            ? $overrides['tenant_user_id']
            : User::factory()->create(['role' => 'tenant'])->id;
        $tenant = Tenant::create([
            'name' => 'Penyewa A',
            'user_id' => $tenantUserId,
        ]);

        return Contract::create(array_merge([
            'unit_id' => $unit->id,
            'tenant_id' => $tenant->id,
            'start_date' => '2026-10-01',
            'end_date' => '2026-12-31',
            'total_price' => 3_600_000,
            'status' => 'active',
        ], collect($overrides)->except('tenant_user_id')->all()));
    }

    private function runCommand(): void
    {
        $this->artisan('reminders:dispatch')->assertSuccessful();
    }

    public function test_sends_h_minus_seven_reminder()
    {
        $contract = $this->contract();

        $this->runCommand();

        $this->assertDatabaseHas('reminders', [
            'contract_id' => $contract->id,
            'month' => '2026-11',
            'type' => 'h_minus_7',
        ]);
        $this->assertDatabaseCount('reminders', 1);

        $notification = DB::table('notifications')->first();
        $this->assertNotNull($notification);
        $this->assertSame($contract->tenant->user_id, $notification->notifiable_id);
        $this->assertSame($contract->id, json_decode($notification->data, true)['contract_id']);
    }

    public function test_sends_h_minus_one_reminder()
    {
        Carbon::setTestNow(Carbon::parse('2026-11-09 07:00:00', 'Asia/Jakarta'));
        $contract = $this->contract();

        $this->runCommand();

        $this->assertDatabaseHas('reminders', [
            'contract_id' => $contract->id,
            'month' => '2026-11',
            'type' => 'h_minus_1',
        ]);
    }

    public function test_sends_h_plus_one_reminder()
    {
        Carbon::setTestNow(Carbon::parse('2026-11-11 07:00:00', 'Asia/Jakarta'));
        $contract = $this->contract();

        $this->runCommand();

        $this->assertDatabaseHas('reminders', [
            'contract_id' => $contract->id,
            'month' => '2026-11',
            'type' => 'h_plus_1',
        ]);
    }

    public function test_previous_month_h_plus_thirty_reminder()
    {
        Carbon::setTestNow(Carbon::parse('2026-11-09 07:00:00', 'Asia/Jakarta'));
        $contract = $this->contract();

        $this->runCommand();

        $this->assertDatabaseHas('reminders', [
            'contract_id' => $contract->id,
            'month' => '2026-10',
            'type' => 'h_plus_30',
        ]);
    }

    public function test_reminder_is_not_duplicated()
    {
        $contract = $this->contract();

        $this->runCommand();
        $this->runCommand();

        $this->assertSame(1, DB::table('reminders')
            ->where('contract_id', $contract->id)
            ->where('type', 'h_minus_7')
            ->count());
        $this->assertSame(1, DB::table('notifications')->count());
    }

    public function test_paid_month_is_skipped()
    {
        $contract = $this->contract();
        Payment::create([
            'contract_id' => $contract->id,
            'month' => '2026-11',
            'amount' => 1_200_000,
            'status' => 'paid',
        ]);

        $this->runCommand();

        $this->assertDatabaseCount('reminders', 0);
        $this->assertDatabaseCount('notifications', 0);
    }

    public function test_contract_starting_after_due_date_gets_no_reminder()
    {
        $contract = $this->contract(['start_date' => '2026-11-15']);

        foreach (['2026-11-03', '2026-11-09', '2026-11-11'] as $day) {
            Carbon::setTestNow(Carbon::parse("{$day} 07:00:00", 'Asia/Jakarta'));
            $this->runCommand();
        }

        $this->assertSame(0, DB::table('reminders')
            ->where('contract_id', $contract->id)
            ->where('month', '2026-11')
            ->count());
    }

    public function test_tenant_without_account_records_reminder_without_notification()
    {
        $contract = $this->contract(['tenant_user_id' => null]);

        $this->runCommand();

        $this->assertDatabaseHas('reminders', [
            'contract_id' => $contract->id,
            'type' => 'h_minus_7',
        ]);
        $this->assertDatabaseCount('notifications', 0);
    }

    public function test_inactive_contract_is_skipped()
    {
        $this->contract(['status' => 'pending']);

        $this->runCommand();

        $this->assertDatabaseCount('reminders', 0);
    }
}
