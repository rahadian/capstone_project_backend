<?php

namespace Tests\Feature;

use App\Models\Contract;
use App\Models\Payment;
use App\Models\Tenant;
use App\Models\Unit;
use App\Models\UnitBlock;
use App\Models\User;
use Carbon\Carbon;
use Database\Seeders\DemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Factory;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Facade;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
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
            'https://images.unsplash.com/*' => Http::response("\xFF\xD8\xFF", 200, ['Content-Type' => 'image/jpeg']),
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

        // Dua pemilik tambahan hanya untuk variasi katalog, tidak dibagikan ke penguji.
        $this->assertSame(4, User::where('role', 'owner')->count());
        $this->assertDatabaseHas('users', ['email' => 'dimas@umkm.id', 'role' => 'owner']);
        $this->assertDatabaseHas('users', ['email' => 'putri@umkm.id', 'role' => 'owner']);

        $this->assertCount(24, Tenant::all());
        $this->assertNotNull(Tenant::where('name', 'Bima Saputra')->first()->user_id);
        $this->assertNotNull(Tenant::where('name', 'Sari Dewi')->first()->user_id);
        $this->assertSame(2, Tenant::whereNotNull('user_id')->count());
    }

    public function test_seed_creates_sixty_four_units_with_absolute_unsplash_images(): void
    {
        $this->seed(DemoSeeder::class);

        $units = Unit::all();
        $types = ['Kamar Kos', 'Kontrakan', 'Ruang Usaha', 'Apartemen'];
        $statuses = ['available', 'rented', 'maintenance'];

        $this->assertCount(64, $units);
        $this->assertSame(32, Unit::where('status', 'available')->count());
        $this->assertSame(28, Unit::where('status', 'rented')->count());
        $this->assertSame(4, Unit::where('status', 'maintenance')->count());
        $this->assertCount(27, Storage::disk('public')->allFiles('demo'));
        $this->assertCount(4, Unit::pluck('owner_id')->unique());
        $this->assertSame(16, Unit::where('owner_id', User::where('email', 'dimas@umkm.id')->value('id'))->count());

        // Sebaran foto per tipe: setiap foto milik tipe itu terpakai, tidak menumpuk di satu unit.
        foreach (['Kamar Kos' => 8, 'Kontrakan' => 6, 'Ruang Usaha' => 7, 'Apartemen' => 6] as $type => $distinct) {
            $this->assertSame($distinct, Unit::where('type', $type)->pluck('image')->unique()->count());
        }

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

        // Katalog publik hanya menampilkan unit available; 32 baris adalah isi halaman depan.
        $res = $this->getJson('/api/list/units?limit=50')->assertOk()->json();
        $this->assertCount(32, $res['data']);
    }

    public function test_seed_creates_contracts_and_payments_inside_valid_enums(): void
    {
        $this->seed(DemoSeeder::class);

        $contracts = Contract::all();

        // 11 kontrak per pemilik × 4 pemilik: 7 active, 2 pending, 1 completed, 1 cancelled.
        $this->assertCount(44, $contracts);
        $this->assertSame(28, Contract::where('status', 'active')->count());
        $this->assertSame(8, Contract::where('status', 'pending')->count());
        $this->assertSame(4, Contract::where('status', 'completed')->count());
        $this->assertSame(4, Contract::where('status', 'cancelled')->count());

        // Pengajuan sewa hanya boleh di unit available, kalau tidak hilang dari daftar pemilik.
        foreach (Contract::where('status', 'pending')->with('unit')->get() as $contract) {
            $this->assertSame('available', $contract->unit->status);
        }
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

        $this->assertSame(8, Payment::whereNotNull('recorded_by')->count());
        $this->assertCount(4, UnitBlock::all());
    }

    /**
     * Blok unit tidak boleh beririsan dengan kontrak apa pun pada unit yang sama.
     * Regresi: blok H+3..H+10 pernah menabrak pengajuan sewa yang mulai awal bulan depan,
     * sehingga aktivasi kontrak ditolak hasOverlap().
     */
    public function test_unit_blocks_do_not_overlap_contracts(): void
    {
        $this->seed(DemoSeeder::class);

        foreach (UnitBlock::with('unit.contracts')->get() as $block) {
            foreach ($block->unit->contracts as $contract) {
                // Blok inklusif [start, end], kontrak setengah terbuka [start, end).
                $overlaps = $block->start_date->lt($contract->end_date)
                    && $contract->start_date->lte($block->end_date);

                $this->assertFalse(
                    $overlaps,
                    "Blok {$block->start_date}..{$block->end_date} menabrak kontrak {$contract->status} unit {$block->unit->name}"
                );
            }
        }

        // Demo harus punya keempat tipe unit, kalau tidak filter katalog terlihat kosong.
        $this->assertEqualsCanonicalizing(
            ['Kamar Kos', 'Kontrakan', 'Ruang Usaha', 'Apartemen'],
            Unit::pluck('type')->unique()->values()->all()
        );
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
        $this->assertSame(44, $before['contracts']);
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

    /**
     * Unduhan gagal harus menghentikan seed, bukan menyimpan URL rusak.
     */
    public function test_seed_aborts_when_photo_download_fails(): void
    {
        // Http::fake(array) menambah stub, tidak mengganti, dan facade menyimpan
        // instance hasil resolve. Buang keduanya supaya stub 200 dari setUp() tidak menang.
        $this->app->forgetInstance(Factory::class);
        Facade::clearResolvedInstance(Factory::class);

        Http::fake([
            'https://images.unsplash.com/*' => Http::response('', 404),
        ]);

        try {
            // Panggil langsung: lewat $this->seed() exception ditelan lapisan konsol artisan.
            (new DemoSeeder)->run();
            $this->fail('Seeder seharusnya melempar RuntimeException saat foto gagal diunduh.');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('images.unsplash.com', $e->getMessage());
            $this->assertStringContainsString('404', $e->getMessage());
        }

        $this->assertSame(0, Unit::count());
    }

    /**
     * Dashboard tagihan menghitung dari rentang kontrak, jadi setiap bulan aktif
     * wajib punya baris pembayaran dan nominalnya harus sama dengan harga unit.
     */
    public function test_active_contracts_have_complete_monthly_payments(): void
    {
        $this->seed(DemoSeeder::class);

        $currentMonth = Carbon::today()->format('Y-m');
        $rejectedUnits = ['Kos Melati B1', 'Ruko Soekarno Hatta', 'Kos Dago Asri A1', 'Kos Kaliurang A1'];

        foreach (Contract::where('status', 'active')->with('unit')->get() as $contract) {
            $expected = [];
            for ($month = $contract->start_date->copy()->startOfMonth(); $month->lte(Carbon::today()); $month->addMonth()) {
                $expected[] = $month->format('Y-m');
            }

            $this->assertSame($expected, $contract->payments()->orderBy('month')->pluck('month')->all());

            foreach ($contract->payments as $payment) {
                if ($payment->month === $currentMonth) {
                    $this->assertSame('pending', $payment->status);
                } elseif (in_array($contract->unit->name, $rejectedUnits, true)) {
                    $this->assertSame('rejected', $payment->status);
                } else {
                    $this->assertSame('paid', $payment->status);
                }

                $this->assertSame($contract->unit->price, (float) $payment->amount);
            }

            /* `end_date` adalah hari pertama bulan setelah masa sewa berakhir,
               dan frontend menghitung bulan sewa dari selisih itu (`hitungBulanSewa`).
               Nilai kontrak wajib sama dengan harga bulanan unit dikali jumlah bulan
               tersebut — kalau tidak, layar menampilkan harga per bulan yang bukan
               harga unit (mis. Rp 927.273 untuk unit Rp 850.000). */
            $months = (int) $contract->start_date->diffInMonths($contract->end_date);
            $this->assertSame((float) ($contract->unit->price * $months), (float) $contract->total_price);
            $this->assertSame((float) $contract->unit->price, round($contract->total_price / $months));
        }
    }

    /**
     * `POST /api/register` publik: email demo bisa didaftarkan orang lain lebih dulu.
     * Seed tidak boleh mengambil alih akun itu dengan mengganti kata sandinya.
     */
    public function test_seed_does_not_overwrite_an_existing_demo_email(): void
    {
        $existing = User::factory()->create([
            'email' => 'wahyu@umkm.id',
            'password' => 'rahasia-lain-123',
            'role' => 'owner',
        ]);

        $this->seed(DemoSeeder::class);

        $existing->refresh();

        $this->assertTrue(Hash::check('rahasia-lain-123', $existing->password));
        $this->assertFalse(Hash::check(DemoSeeder::PASSWORD, $existing->password));
    }

    /**
     * Seed boleh dijalankan di database berisi data lain tanpa menghapusnya.
     */
    public function test_seed_leaves_non_demo_rows_alone(): void
    {
        $other = User::factory()->create(['email' => 'lain@example.com', 'role' => 'owner']);
        $unit = Unit::create([
            'owner_id' => $other->id,
            'name' => 'Unit Milik Orang Lain',
            'address' => 'Jl. Contoh No. 1',
            'price' => 1000000,
            'status' => 'available',
        ]);

        $this->seed(DemoSeeder::class);

        $this->assertDatabaseHas('users', ['email' => 'lain@example.com']);
        $this->assertDatabaseHas('units', ['id' => $unit->id, 'name' => 'Unit Milik Orang Lain']);
        $this->assertSame(65, Unit::count());
    }
}
