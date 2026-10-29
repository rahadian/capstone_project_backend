<?php

namespace Database\Seeders;

use App\Models\Contract;
use App\Models\Payment;
use App\Models\Tenant;
use App\Models\Unit;
use App\Models\UnitBlock;
use App\Models\User;
use App\Notifications\PaymentDueReminder;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

class DemoSeeder extends Seeder
{
    public const PASSWORD = 'demo12345';

    /**
     * Foto Unsplash per slug unit. Jangan diganti tanpa memeriksa ulang URL-nya.
     */
    private const PHOTOS = [
        'kos-a' => 'https://images.unsplash.com/photo-1622127922040-13cab637ee78?auto=format&fit=crop&w=1200&q=80',
        'kos-b' => 'https://images.unsplash.com/photo-1634207523205-cf9ef97a31d1?auto=format&fit=crop&w=1200&q=80',
        'kos-c' => 'https://images.unsplash.com/photo-1552321554-5fefe8c9ef14?auto=format&fit=crop&w=1200&q=80',
        'kos-d' => 'https://images.unsplash.com/photo-1734185161012-16f01a8d4339?auto=format&fit=crop&w=1200&q=80',
        'kontrakan-a' => 'https://images.unsplash.com/photo-1586162174803-b70687057df0?auto=format&fit=crop&w=1200&q=80',
        'kontrakan-b' => 'https://images.unsplash.com/photo-1672345158827-7f4aa9467b49?auto=format&fit=crop&w=1200&q=80',
        'kontrakan-c' => 'https://images.unsplash.com/photo-1628797285815-453c1d0d21e3?auto=format&fit=crop&w=1200&q=80',
        'ruko-a' => 'https://images.unsplash.com/photo-1560180277-9a841af42083?auto=format&fit=crop&w=1200&q=80',
        'ruko-b' => 'https://images.unsplash.com/photo-1780037190604-873f4cc59e80?auto=format&fit=crop&w=1200&q=80',
        'ruko-c' => 'https://images.unsplash.com/photo-1598062568618-29c189c7f303?auto=format&fit=crop&w=1200&q=80',
        'apartemen-a' => 'https://images.unsplash.com/photo-1649429710616-dad56ce9a076?auto=format&fit=crop&w=1200&q=80',
        'apartemen-b' => 'https://images.unsplash.com/photo-1629042306558-7d1e15cc02fa?auto=format&fit=crop&w=1200&q=80',
    ];

    private const OWNERS = [
        'wahyu' => ['Wahyu Pratama', 'wahyu@umkm.id'],
        'rina' => ['Rina Kartika', 'rina@umkm.id'],
    ];

    private const UNITS = [
        ['kos-a', 'wahyu', 'Kos Melati A1', 'Kamar Kos', 'Jl. Ketintang Baru No. 12, Surabaya', 850000, 'rented', ['AC', 'WiFi', 'Kamar Mandi Dalam', 'Parkir Motor']],
        ['kos-b', 'wahyu', 'Kos Melati A2', 'Kamar Kos', 'Jl. Ketintang Baru No. 12, Surabaya', 950000, 'rented', ['AC', 'WiFi', 'Kamar Mandi Dalam', 'Listrik Token']],
        ['kos-c', 'wahyu', 'Kos Melati B1', 'Kamar Kos', 'Jl. Ketintang Baru No. 14, Surabaya', 1100000, 'rented', ['AC', 'WiFi', 'Kamar Mandi Dalam', 'Kloset Duduk', 'Parkir Motor']],
        ['kos-d', 'wahyu', 'Kos Melati B2', 'Kamar Kos', 'Jl. Ketintang Baru No. 14, Surabaya', 800000, 'available', ['AC', 'WiFi', 'Kamar Mandi Dalam', 'Dapur']],
        ['kontrakan-a', 'wahyu', 'Kontrakan Wonokromo', 'Kontrakan', 'Jl. Wonokromo Gang 3 No. 8, Surabaya', 1800000, 'rented', ['Dapur', 'Parkir Motor', 'Listrik Token', 'Kamar Mandi Dalam']],
        ['kontrakan-b', 'wahyu', 'Kontrakan Rungkut', 'Kontrakan', 'Jl. Rungkut Asri Timur No. 21, Surabaya', 1650000, 'available', ['Dapur', 'Parkir Motor', 'Listrik Token']],
        ['kontrakan-c', 'rina', 'Kontrakan Lowokwaru', 'Kontrakan', 'Jl. Soekarno Hatta Gang 7 No. 4, Malang', 1500000, 'rented', ['Dapur', 'Kamar Mandi Dalam', 'Parkir Motor']],
        ['ruko-a', 'rina', 'Ruko Soekarno Hatta', 'Ruang Usaha', 'Jl. Soekarno Hatta No. 88, Malang', 4500000, 'rented', ['Etalase', 'Listrik Token', 'Parkir Motor']],
        ['ruko-b', 'rina', 'Ruko Basuki Rahmat', 'Ruang Usaha', 'Jl. Basuki Rahmat No. 45, Malang', 5200000, 'available', ['Etalase', 'Listrik Token', 'Kamar Mandi Dalam']],
        ['ruko-c', 'rina', 'Ruko Ijen', 'Ruang Usaha', 'Jl. Ijen No. 17, Malang', 4800000, 'maintenance', ['Etalase', 'Listrik Token', 'Parkir Motor', 'Keamanan 24 Jam']],
        ['apartemen-a', 'rina', 'Apartemen Araya Studio', 'Apartemen', 'Jl. Raya Araya No. 9, Malang', 2500000, 'rented', ['AC', 'WiFi', 'Lift', 'Keamanan 24 Jam']],
        ['apartemen-b', 'rina', 'Apartemen Soekarno Hatta', 'Apartemen', 'Jl. Soekarno Hatta No. 100, Malang', 2200000, 'available', ['AC', 'WiFi', 'Lift', 'Parkir Motor']],
    ];

    /**
     * [nama, pemilik, email user (null kalau tanpa akun), phone, ktp_status, nik, alamat]
     */
    private const TENANTS = [
        ['Bima Saputra', 'wahyu', 'bima@umkm.id', '081234500011', 'verified', '3578011203940001', 'Jl. Ketintang Baru No. 12, Surabaya'],
        ['Sari Dewi', 'rina', 'sari@umkm.id', '081234500012', 'verified', '3573024506950002', 'Jl. Raya Araya No. 9, Malang'],
        ['Andi Pratama', 'wahyu', null, '081234500013', 'verified', '3578011203940003', 'Jl. Ketintang Baru No. 12, Surabaya'],
        ['Nur Aisyah', 'wahyu', null, '081234500014', 'pending', '3578011203940004', 'Jl. Ketintang Baru No. 14, Surabaya'],
        ['Dedi Kurniawan', 'rina', null, '081234500015', 'unverified', '3573024506950005', 'Jl. Soekarno Hatta No. 88, Malang'],
        ['Fitri Handayani', 'wahyu', null, '081234500016', 'verified', '3578011203940006', 'Jl. Wonokromo Gang 3 No. 8, Surabaya'],
        ['Agus Setiawan', 'rina', null, '081234500017', 'pending', '3573024506950007', 'Jl. Soekarno Hatta Gang 7 No. 4, Malang'],
        ['Maya Lestari', 'rina', null, '081234500018', 'unverified', '3573024506950008', 'Jl. Soekarno Hatta No. 100, Malang'],
    ];

    /**
     * [slug unit, nama penyewa, status, bulan mulai, bulan akhir] relatif bulan berjalan.
     */
    private const CONTRACTS = [
        ['kos-a', 'Bima Saputra', 'active', -8, 3],
        ['kos-b', 'Andi Pratama', 'active', -6, 5],
        ['kos-c', 'Nur Aisyah', 'active', -4, 7],
        ['kontrakan-a', 'Fitri Handayani', 'active', -10, 1],
        ['kontrakan-c', 'Agus Setiawan', 'active', -3, 8],
        ['ruko-a', 'Dedi Kurniawan', 'active', -5, 6],
        ['apartemen-a', 'Sari Dewi', 'active', -2, 9],
        ['kos-d', 'Bima Saputra', 'pending', 1, 12],
        ['ruko-b', 'Maya Lestari', 'pending', 1, 12],
        ['kontrakan-b', 'Dedi Kurniawan', 'completed', -14, -3],
        ['apartemen-b', 'Maya Lestari', 'cancelled', -6, -5],
    ];

    /**
     * Pembayaran dua kontrak ini dicatat pemilik unit sebagai bukti transfer manual.
     */
    private const OWNER_RECORDED_UNITS = ['kos-a', 'ruko-a'];

    public function run(): void
    {
        $images = $this->downloadPhotos();

        $owners = [];
        foreach (self::OWNERS as $key => [$name, $email]) {
            $owners[$key] = User::updateOrCreate(
                ['email' => $email],
                ['name' => $name, 'password' => self::PASSWORD, 'role' => 'owner']
            );
        }

        $units = [];
        foreach (self::UNITS as [$slug, $owner, $name, $type, $address, $price, $status, $facilities]) {
            $units[$slug] = Unit::updateOrCreate(
                ['owner_id' => $owners[$owner]->id, 'name' => $name],
                [
                    'type' => $type,
                    'facilities' => $facilities,
                    'image' => $images[$slug],
                    'address' => $address,
                    'price' => $price,
                    'status' => $status,
                ]
            );
        }

        $tenants = [];
        foreach (self::TENANTS as [$name, $owner, $email, $phone, $ktpStatus, $nik, $address]) {
            $user = $email ? User::updateOrCreate(
                ['email' => $email],
                ['name' => $name, 'password' => self::PASSWORD, 'role' => 'tenant']
            ) : null;

            $tenants[$name] = Tenant::updateOrCreate(
                ['owner_id' => $owners[$owner]->id, 'name' => $name],
                [
                    'user_id' => $user?->id,
                    'phone' => $phone,
                    'ktp_status' => $ktpStatus,
                    'ktp_data_json' => ['nik' => $nik, 'name' => $name, 'address' => $address],
                ]
            );
        }

        $monthStart = Carbon::today()->startOfMonth();

        $contracts = [];
        foreach (self::CONTRACTS as [$slug, $tenantName, $status, $startOffset, $endOffset]) {
            $start = $monthStart->copy()->addMonths($startOffset);
            $end = $monthStart->copy()->addMonths($endOffset);

            $contracts[$slug] = Contract::updateOrCreate(
                [
                    'unit_id' => $units[$slug]->id,
                    'tenant_id' => $tenants[$tenantName]->id,
                    // Carbon, bukan string: kolom date menyimpan 'Y-m-d H:i:s' sehingga kunci string tidak cocok.
                    'start_date' => $start,
                ],
                [
                    'end_date' => $end->toDateString(),
                    'total_price' => $units[$slug]->price * ($endOffset - $startOffset + 1),
                    'status' => $status,
                ]
            );
        }

        // Satu baris pembayaran per bulan berjalan sejak kontrak aktif dimulai.
        foreach (self::CONTRACTS as [$slug, , $status, $startOffset]) {
            if ($status !== 'active') {
                continue;
            }

            $unit = $units[$slug];
            $contract = $contracts[$slug];

            for ($offset = $startOffset; $offset <= 0; $offset++) {
                $month = $monthStart->copy()->addMonths($offset)->format('Y-m');
                $isCurrentMonth = $offset === 0;
                $paymentStatus = $isCurrentMonth ? 'pending' : ($slug === 'kos-c' ? 'rejected' : 'paid');

                Payment::updateOrCreate(
                    ['contract_id' => $contract->id, 'month' => $month],
                    [
                        'amount' => $unit->price,
                        'status' => $paymentStatus,
                        'proof_url' => "bukti-$month-$slug.jpg",
                        'recorded_by' => $offset === $startOffset && in_array($slug, self::OWNER_RECORDED_UNITS, true)
                            ? $unit->owner_id
                            : null,
                    ]
                );
            }
        }

        // Blok sengaja di unit tanpa kontrak berjalan: kos-d dan ruko-b punya pengajuan
        // yang mulai awal bulan depan, jadi blok H+3 sampai H+10 akan menabraknya.
        foreach ([['kontrakan-b', 'Perbaikan atap'], ['apartemen-b', 'Cat ulang']] as [$slug, $note]) {
            UnitBlock::updateOrCreate(
                ['unit_id' => $units[$slug]->id, 'note' => $note],
                [
                    'start_date' => Carbon::today()->addDays(3)->toDateString(),
                    'end_date' => Carbon::today()->addDays(10)->toDateString(),
                    'created_by' => $units[$slug]->owner_id,
                ]
            );
        }

        // Notifikasi hanya untuk pemilik demo pertama, sekali saja (dijaga dari pengulangan seed).
        $wahyu = $owners['wahyu'];
        if ($wahyu->notifications()->count() === 0) {
            $month = Carbon::today()->format('Y-m');
            $due = Carbon::parse($month.'-10');
            $contract = $contracts['kos-a'];

            foreach (['h_minus_7', 'h_minus_1', 'h_plus_1'] as $type) {
                $wahyu->notify(new PaymentDueReminder($type, $month, (int) $units['kos-a']->price, $due, $contract));
            }

            $wahyu->notifications()->first()?->markAsRead();
        }
    }

    /**
     * Unduh foto demo sekali ke disk publik; berkas yang sudah ada dilewati.
     */
    private function downloadPhotos(): array
    {
        $dir = 'demo';
        Storage::disk('public')->makeDirectory($dir);

        $images = [];
        foreach (self::PHOTOS as $slug => $url) {
            $path = "$dir/$slug.jpg";

            if (! Storage::disk('public')->exists($path)) {
                $response = Http::timeout(20)->get($url);

                if (! $response->successful()) {
                    throw new RuntimeException("Gagal mengunduh $url ({$response->status()})");
                }

                Storage::disk('public')->put($path, $response->body());
            }

            $images[$slug] = Storage::disk('public')->url($path);
        }

        return $images;
    }
}
