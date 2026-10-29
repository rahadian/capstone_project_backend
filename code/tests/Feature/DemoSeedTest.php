<?php

namespace Tests\Feature;

use App\Models\Contract;
use App\Models\Payment;
use App\Models\Tenant;
use App\Models\Unit;
use App\Models\UnitBlock;
use App\Models\User;
use Database\Seeders\DemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class DemoSeedTest extends TestCase
{
    use RefreshDatabase;

    private const DEMO_EMAILS = [
        'wahyu@umkm.id',
        'rina@umkm.id',
        'bima@umkm.id',
        'sari@umkm.id',
    ];

    protected function setUp(): void
    {
        parent::setUp();

        // Tes tidak boleh menyentuh jaringan: semua foto Unsplash dijawab JPEG minimal.
        Http::fake([
            'images.unsplash.com/*' => Http::response("\xFF\xD8\xFF", 200, ['Content-Type' => 'image/jpeg']),
        ]);

        // url eksplisit supaya Storage::url() tetap menghasilkan URL http seperti disk asli,
        // bukan path /storage bawaan fake disk.
        Storage::fake('public', ['url' => 'http://localhost:8555/uploads']);
    }

    public function test_seed_creates_demo_accounts_with_shared_password(): void
    {
        $this->seed(DemoSeeder::class);

        $users = User::whereIn('email', self::DEMO_EMAILS)->get();

        $this->assertCount(4, $users);

        foreach ($users as $user) {
            $this->assertTrue(Hash::check(DemoSeeder::PASSWORD, $user->password), "Kata sandi {$user->email} tidak cocok");
        }

        $this->assertSame('owner', $users->firstWhere('email', 'wahyu@umkm.id')->role);
        $this->assertSame('owner', $users->firstWhere('email', 'rina@umkm.id')->role);
        $this->assertSame('tenant', $users->firstWhere('email', 'bima@umkm.id')->role);
        $this->assertSame('tenant', $users->firstWhere('email', 'sari@umkm.id')->role);

        $this->assertCount(8, Tenant::all());
        $this->assertNotNull(Tenant::where('name', 'Bima Saputra')->first()->user_id);
        $this->assertNotNull(Tenant::where('name', 'Sari Dewi')->first()->user_id);
        $this->assertSame(2, Tenant::whereNotNull('user_id')->count());
    }

    public function test_seed_creates_twelve_units_with_absolute_unsplash_images(): void
    {
        $this->seed(DemoSeeder::class);

        $units = Unit::all();
        $types = ['Kamar Kos', 'Kontrakan', 'Ruang Usaha', 'Apartemen'];
        $statuses = ['available', 'rented', 'maintenance'];

        $this->assertCount(12, $units);
        $this->assertSame(7, Unit::where('status', 'rented')->count());
        $this->assertCount(12, Storage::disk('public')->allFiles('demo'));

        foreach ($units as $unit) {
            $this->assertStringStartsWith('http', $unit->image);
            $this->assertStringContainsString('/uploads/demo/', $unit->image);
            $this->assertContains($unit->type, $types);
            $this->assertContains($unit->status, $statuses);
            $this->assertIsArray($unit->facilities);
            $this->assertGreaterThanOrEqual(3, count($unit->facilities));
        }

        // Kamar kos wajib AC + WiFi, ruko wajib Etalase.
        foreach ($units->where('type', 'Kamar Kos') as $kos) {
            $this->assertContains('AC', $kos->facilities);
            $this->assertContains('WiFi', $kos->facilities);
        }

        foreach ($units->where('type', 'Ruang Usaha') as $ruko) {
            $this->assertContains('Etalase', $ruko->facilities);
        }
    }

    public function test_seed_creates_contracts_and_payments_inside_valid_enums(): void
    {
        $this->seed(DemoSeeder::class);

        $contracts = Contract::all();

        // Plan menyebut 10 baris, tetapi rinciannya 7 active + 2 pending + 1 completed + 1 cancelled.
        $this->assertCount(11, $contracts);
        $this->assertEqualsCanonicalizing(
            ['pending', 'active', 'completed', 'cancelled'],
            $contracts->pluck('status')->unique()->values()->all()
        );

        $this->assertGreaterThan(0, Payment::count());

        foreach (Payment::with('contract')->get() as $payment) {
            $this->assertMatchesRegularExpression('/^\d{4}-\d{2}$/', $payment->month);
            $this->assertContains($payment->status, ['pending', 'paid', 'rejected']);

            $contract = $payment->contract;
            $this->assertGreaterThanOrEqual($contract->start_date->format('Y-m'), $payment->month);
            $this->assertLessThanOrEqual($contract->end_date->format('Y-m'), $payment->month);
        }

        $this->assertSame(2, Payment::whereNotNull('recorded_by')->count());
        $this->assertSame(2, UnitBlock::count());
    }

    public function test_seed_is_idempotent(): void
    {
        $this->seed(DemoSeeder::class);

        $before = [
            'users' => User::count(),
            'units' => Unit::count(),
            'tenants' => Tenant::count(),
            'contracts' => Contract::count(),
            'payments' => Payment::count(),
            'blocks' => UnitBlock::count(),
            'notifications' => DB::table('notifications')->count(),
        ];

        $this->seed(DemoSeeder::class);

        $this->assertSame($before, [
            'users' => User::count(),
            'units' => Unit::count(),
            'tenants' => Tenant::count(),
            'contracts' => Contract::count(),
            'payments' => Payment::count(),
            'blocks' => UnitBlock::count(),
            'notifications' => DB::table('notifications')->count(),
        ]);

        $this->assertSame(3, $before['notifications']);
        $this->assertSame(1, DB::table('notifications')->whereNotNull('read_at')->count());
    }

    public function test_demo_owner_can_log_in(): void
    {
        $this->seed(DemoSeeder::class);

        $this->postJson('/api/login', [
            'email' => 'wahyu@umkm.id',
            'password' => DemoSeeder::PASSWORD,
        ])
            ->assertOk()
            ->assertJsonStructure(['access_token', 'token_type']);
    }
}
