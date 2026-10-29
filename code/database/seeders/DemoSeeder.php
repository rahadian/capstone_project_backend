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
        'kos-e' => 'https://images.unsplash.com/photo-1615874959474-d609969a20ed?auto=format&fit=crop&w=1200&q=80',
        'kos-f' => 'https://images.unsplash.com/photo-1631049307264-da0ec9d70304?auto=format&fit=crop&w=1200&q=80',
        'kos-g' => 'https://images.unsplash.com/photo-1586023492125-27b2c045efd7?auto=format&fit=crop&w=1200&q=80',
        'kos-h' => 'https://images.unsplash.com/photo-1502672260266-1c1ef2d93688?auto=format&fit=crop&w=1200&q=80',
        'kontrakan-d' => 'https://images.unsplash.com/photo-1480074568708-e7b720bb3f09?auto=format&fit=crop&w=1200&q=80',
        'kontrakan-e' => 'https://images.unsplash.com/photo-1449844908441-8829872d2607?auto=format&fit=crop&w=1200&q=80',
        'kontrakan-f' => 'https://images.unsplash.com/photo-1570129477492-45c003edd2be?auto=format&fit=crop&w=1200&q=80',
        'ruko-d' => 'https://images.unsplash.com/photo-1528698827591-e19ccd7bc23d?auto=format&fit=crop&w=1200&q=80',
        'ruko-e' => 'https://images.unsplash.com/photo-1472851294608-062f824d29cc?auto=format&fit=crop&w=1200&q=80',
        'ruko-f' => 'https://images.unsplash.com/photo-1556742049-0cfed4f6a45d?auto=format&fit=crop&w=1200&q=80',
        'ruko-g' => 'https://images.unsplash.com/photo-1441986300917-64674bd600d8?auto=format&fit=crop&w=1200&q=80',
        'apartemen-c' => 'https://images.unsplash.com/photo-1560448204-e02f11c3d0e2?auto=format&fit=crop&w=1200&q=80',
        'apartemen-d' => 'https://images.unsplash.com/photo-1600566753086-00f18fb6b3ea?auto=format&fit=crop&w=1200&q=80',
        'apartemen-e' => 'https://images.unsplash.com/photo-1493809842364-78817add7ffb?auto=format&fit=crop&w=1200&q=80',
        'apartemen-f' => 'https://images.unsplash.com/photo-1600607687939-ce8a6c25118c?auto=format&fit=crop&w=1200&q=80',
    ];

    private const OWNERS = [
        'wahyu' => ['Wahyu Pratama', 'wahyu@umkm.id'],
        'rina' => ['Rina Kartika', 'rina@umkm.id'],
        // Dua akun berikut hanya untuk variasi katalog; tidak dibagikan ke penguji.
        'dimas' => ['Dimas Anggara', 'dimas@umkm.id'],
        'putri' => ['Putri Maharani', 'putri@umkm.id'],
    ];

    /**
     * [slug unik, slug foto, pemilik, nama, tipe, alamat, harga, status, fasilitas]
     * Slug unik memakai awalan pemilik karena nama unit hanya unik per pemilik.
     */
    private const UNITS = [
        ['wahyu-kos-a', 'kos-a', 'wahyu', 'Kos Melati A1', 'Kamar Kos', 'Jl. Ketintang Baru No. 12, Surabaya', 850000, 'rented', ['AC', 'WiFi', 'Kamar Mandi Dalam', 'Parkir Motor']],
        ['wahyu-kos-b', 'kos-b', 'wahyu', 'Kos Melati A2', 'Kamar Kos', 'Jl. Ketintang Baru No. 12, Surabaya', 950000, 'rented', ['AC', 'WiFi', 'Kamar Mandi Dalam', 'Listrik Token']],
        ['wahyu-kos-c', 'kos-c', 'wahyu', 'Kos Melati B1', 'Kamar Kos', 'Jl. Ketintang Baru No. 14, Surabaya', 1100000, 'rented', ['AC', 'WiFi', 'Kamar Mandi Dalam', 'Kloset Duduk', 'Parkir Motor']],
        ['wahyu-kos-d', 'kos-d', 'wahyu', 'Kos Melati B2', 'Kamar Kos', 'Jl. Ketintang Baru No. 14, Surabaya', 800000, 'available', ['AC', 'WiFi', 'Kamar Mandi Dalam', 'Dapur']],
        ['wahyu-kontrakan-a', 'kontrakan-a', 'wahyu', 'Kontrakan Wonokromo', 'Kontrakan', 'Jl. Wonokromo Gang 3 No. 8, Surabaya', 1800000, 'rented', ['Dapur', 'Parkir Motor', 'Listrik Token', 'Kamar Mandi Dalam']],
        ['wahyu-kontrakan-b', 'kontrakan-b', 'wahyu', 'Kontrakan Rungkut', 'Kontrakan', 'Jl. Rungkut Asri Timur No. 21, Surabaya', 1650000, 'available', ['Dapur', 'Parkir Motor', 'Listrik Token']],
        ['wahyu-kontrakan-d', 'kontrakan-d', 'wahyu', 'Kontrakan Bratang', 'Kontrakan', 'Jl. Bratang Gede No. 15, Surabaya', 1900000, 'rented', ['Dapur', 'Kamar Mandi Dalam', 'Parkir Motor']],
        ['wahyu-kontrakan-e', 'kontrakan-e', 'wahyu', 'Kontrakan Ketintang', 'Kontrakan', 'Jl. Ketintang Baru No. 30, Surabaya', 1750000, 'available', ['Dapur', 'Listrik Token', 'Parkir Motor']],
        ['wahyu-ruko-d', 'ruko-d', 'wahyu', 'Ruko Rungkut', 'Ruang Usaha', 'Jl. Rungkut Asri Timur No. 5, Surabaya', 4200000, 'rented', ['Etalase', 'Listrik Token', 'Parkir Motor']],
        ['wahyu-ruko-e', 'ruko-e', 'wahyu', 'Ruko Bratang', 'Ruang Usaha', 'Jl. Bratang Gede No. 44, Surabaya', 4800000, 'available', ['Etalase', 'Kamar Mandi Dalam', 'Listrik Token']],
        ['wahyu-ruko-f', 'ruko-f', 'wahyu', 'Ruko Wonokromo', 'Ruang Usaha', 'Jl. Wonokromo Gang 3 No. 21, Surabaya', 5000000, 'available', ['Etalase', 'Parkir Motor', 'Keamanan 24 Jam']],
        ['wahyu-ruko-g', 'ruko-g', 'wahyu', 'Ruko Ketintang', 'Ruang Usaha', 'Jl. Ketintang Baru No. 88, Surabaya', 4400000, 'maintenance', ['Etalase', 'Listrik Token', 'Kamar Mandi Dalam']],
        ['wahyu-apartemen-c', 'apartemen-c', 'wahyu', 'Apartemen Ketintang Studio', 'Apartemen', 'Jl. Ketintang Baru No. 60, Surabaya', 2300000, 'rented', ['AC', 'WiFi', 'Lift', 'Keamanan 24 Jam']],
        ['wahyu-apartemen-d', 'apartemen-d', 'wahyu', 'Apartemen Bratang 1 BR', 'Apartemen', 'Jl. Bratang Gede No. 8, Surabaya', 2700000, 'available', ['AC', 'WiFi', 'Lift', 'Parkir Motor']],
        ['wahyu-apartemen-e', 'apartemen-e', 'wahyu', 'Apartemen Rungkut Studio', 'Apartemen', 'Jl. Rungkut Asri Timur No. 33, Surabaya', 2100000, 'available', ['AC', 'WiFi', 'Lift', 'Keamanan 24 Jam']],
        ['wahyu-apartemen-f', 'apartemen-f', 'wahyu', 'Apartemen Wonokromo 1 BR', 'Apartemen', 'Jl. Wonokromo Gang 3 No. 12, Surabaya', 2600000, 'available', ['AC', 'WiFi', 'Kamar Mandi Dalam', 'Parkir Motor']],

        ['rina-kontrakan-c', 'kontrakan-c', 'rina', 'Kontrakan Lowokwaru', 'Kontrakan', 'Jl. Soekarno Hatta Gang 7 No. 4, Malang', 1500000, 'rented', ['Dapur', 'Kamar Mandi Dalam', 'Parkir Motor']],
        ['rina-ruko-a', 'ruko-a', 'rina', 'Ruko Soekarno Hatta', 'Ruang Usaha', 'Jl. Soekarno Hatta No. 88, Malang', 4500000, 'rented', ['Etalase', 'Listrik Token', 'Parkir Motor']],
        ['rina-ruko-b', 'ruko-b', 'rina', 'Ruko Basuki Rahmat', 'Ruang Usaha', 'Jl. Basuki Rahmat No. 45, Malang', 5200000, 'available', ['Etalase', 'Listrik Token', 'Kamar Mandi Dalam']],
        ['rina-ruko-c', 'ruko-c', 'rina', 'Ruko Ijen', 'Ruang Usaha', 'Jl. Ijen No. 17, Malang', 4800000, 'maintenance', ['Etalase', 'Listrik Token', 'Parkir Motor', 'Keamanan 24 Jam']],
        ['rina-apartemen-a', 'apartemen-a', 'rina', 'Apartemen Araya Studio', 'Apartemen', 'Jl. Raya Araya No. 9, Malang', 2500000, 'rented', ['AC', 'WiFi', 'Lift', 'Keamanan 24 Jam']],
        ['rina-apartemen-b', 'apartemen-b', 'rina', 'Apartemen Soekarno Hatta', 'Apartemen', 'Jl. Soekarno Hatta No. 100, Malang', 2200000, 'available', ['AC', 'WiFi', 'Lift', 'Parkir Motor']],
        ['rina-kos-e', 'kos-e', 'rina', 'Kos Ijen C1', 'Kamar Kos', 'Jl. Ijen No. 22, Malang', 900000, 'rented', ['AC', 'WiFi', 'Kamar Mandi Dalam', 'Parkir Motor']],
        ['rina-kos-f', 'kos-f', 'rina', 'Kos Ijen C2', 'Kamar Kos', 'Jl. Ijen No. 22, Malang', 1000000, 'rented', ['AC', 'WiFi', 'Kamar Mandi Dalam', 'Listrik Token']],
        ['rina-kos-g', 'kos-g', 'rina', 'Kos Soekarno Hatta D1', 'Kamar Kos', 'Jl. Soekarno Hatta Gang 7 No. 9, Malang', 850000, 'available', ['AC', 'WiFi', 'Kamar Mandi Dalam', 'Dapur']],
        ['rina-kos-h', 'kos-h', 'rina', 'Kos Soekarno Hatta D2', 'Kamar Kos', 'Jl. Soekarno Hatta Gang 7 No. 9, Malang', 800000, 'available', ['AC', 'WiFi', 'Kloset Duduk', 'Parkir Motor']],
        ['rina-kontrakan-d', 'kontrakan-d', 'rina', 'Kontrakan Ijen', 'Kontrakan', 'Jl. Ijen No. 45, Malang', 1700000, 'rented', ['Dapur', 'Kamar Mandi Dalam', 'Listrik Token']],
        ['rina-kontrakan-e', 'kontrakan-e', 'rina', 'Kontrakan Basuki Rahmat', 'Kontrakan', 'Jl. Basuki Rahmat No. 12, Malang', 1600000, 'rented', ['Dapur', 'Parkir Motor', 'Kamar Mandi Dalam']],
        ['rina-kontrakan-f', 'kontrakan-f', 'rina', 'Kontrakan Araya', 'Kontrakan', 'Jl. Raya Araya No. 21, Malang', 1550000, 'available', ['Dapur', 'Listrik Token', 'Parkir Motor']],
        ['rina-ruko-d', 'ruko-d', 'rina', 'Ruko Araya', 'Ruang Usaha', 'Jl. Raya Araya No. 3, Malang', 4600000, 'available', ['Etalase', 'Parkir Motor', 'Kamar Mandi Dalam']],
        ['rina-apartemen-c', 'apartemen-c', 'rina', 'Apartemen Basuki Rahmat Studio', 'Apartemen', 'Jl. Basuki Rahmat No. 60, Malang', 2400000, 'available', ['AC', 'WiFi', 'Lift', 'Keamanan 24 Jam']],
        ['rina-apartemen-d', 'apartemen-d', 'rina', 'Apartemen Ijen 1 BR', 'Apartemen', 'Jl. Ijen No. 30, Malang', 2600000, 'available', ['AC', 'WiFi', 'Lift', 'Parkir Motor']],

        ['dimas-kos-a', 'kos-a', 'dimas', 'Kos Dago Asri A1', 'Kamar Kos', 'Jl. Dago Asri No. 4, Bandung', 950000, 'rented', ['AC', 'WiFi', 'Kamar Mandi Dalam', 'Parkir Motor']],
        ['dimas-kos-b', 'kos-b', 'dimas', 'Kos Dago Asri A2', 'Kamar Kos', 'Jl. Dago Asri No. 4, Bandung', 1000000, 'rented', ['AC', 'WiFi', 'Kamar Mandi Dalam', 'Listrik Token']],
        ['dimas-kos-c', 'kos-c', 'dimas', 'Kos Cihampelas B1', 'Kamar Kos', 'Jl. Cihampelas No. 118, Bandung', 850000, 'available', ['AC', 'WiFi', 'Kamar Mandi Dalam', 'Dapur']],
        ['dimas-kos-d', 'kos-d', 'dimas', 'Kos Cihampelas B2', 'Kamar Kos', 'Jl. Cihampelas No. 118, Bandung', 800000, 'available', ['AC', 'WiFi', 'Kloset Duduk', 'Parkir Motor']],
        ['dimas-kontrakan-a', 'kontrakan-a', 'dimas', 'Kontrakan Sukajadi', 'Kontrakan', 'Jl. Sukajadi No. 27, Bandung', 1900000, 'rented', ['Dapur', 'Kamar Mandi Dalam', 'Parkir Motor']],
        ['dimas-kontrakan-b', 'kontrakan-b', 'dimas', 'Kontrakan Buah Batu', 'Kontrakan', 'Jl. Buah Batu No. 90, Bandung', 1750000, 'rented', ['Dapur', 'Listrik Token', 'Parkir Motor']],
        ['dimas-kontrakan-c', 'kontrakan-c', 'dimas', 'Kontrakan Dago', 'Kontrakan', 'Jl. Dago Asri No. 11, Bandung', 1850000, 'available', ['Dapur', 'Kamar Mandi Dalam', 'Listrik Token']],
        ['dimas-kontrakan-d', 'kontrakan-d', 'dimas', 'Kontrakan Cihampelas', 'Kontrakan', 'Jl. Cihampelas No. 45, Bandung', 1650000, 'available', ['Dapur', 'Parkir Motor', 'Kamar Mandi Dalam']],
        ['dimas-ruko-a', 'ruko-a', 'dimas', 'Ruko Sukajadi', 'Ruang Usaha', 'Jl. Sukajadi No. 66, Bandung', 5200000, 'rented', ['Etalase', 'Listrik Token', 'Parkir Motor']],
        ['dimas-ruko-b', 'ruko-b', 'dimas', 'Ruko Buah Batu', 'Ruang Usaha', 'Jl. Buah Batu No. 14, Bandung', 4800000, 'rented', ['Etalase', 'Kamar Mandi Dalam', 'Keamanan 24 Jam']],
        ['dimas-ruko-c', 'ruko-c', 'dimas', 'Ruko Dago', 'Ruang Usaha', 'Jl. Dago Asri No. 77, Bandung', 5000000, 'available', ['Etalase', 'Listrik Token', 'Parkir Motor']],
        ['dimas-ruko-d', 'ruko-d', 'dimas', 'Ruko Cihampelas', 'Ruang Usaha', 'Jl. Cihampelas No. 200, Bandung', 4500000, 'maintenance', ['Etalase', 'Listrik Token', 'Kamar Mandi Dalam']],
        ['dimas-apartemen-a', 'apartemen-a', 'dimas', 'Apartemen Dago Studio', 'Apartemen', 'Jl. Dago Asri No. 100, Bandung', 2500000, 'rented', ['AC', 'WiFi', 'Lift', 'Keamanan 24 Jam']],
        ['dimas-apartemen-b', 'apartemen-b', 'dimas', 'Apartemen Sukajadi 1 BR', 'Apartemen', 'Jl. Sukajadi No. 35, Bandung', 2800000, 'available', ['AC', 'WiFi', 'Lift', 'Parkir Motor']],
        ['dimas-apartemen-c', 'apartemen-c', 'dimas', 'Apartemen Buah Batu Studio', 'Apartemen', 'Jl. Buah Batu No. 55, Bandung', 2300000, 'available', ['AC', 'WiFi', 'Keamanan 24 Jam', 'Parkir Motor']],
        ['dimas-apartemen-d', 'apartemen-d', 'dimas', 'Apartemen Cihampelas 1 BR', 'Apartemen', 'Jl. Cihampelas No. 150, Bandung', 2700000, 'available', ['AC', 'WiFi', 'Lift', 'Kamar Mandi Dalam']],

        ['putri-kos-a', 'kos-a', 'putri', 'Kos Kaliurang A1', 'Kamar Kos', 'Jl. Kaliurang No. 8, Yogyakarta', 800000, 'rented', ['AC', 'WiFi', 'Kamar Mandi Dalam', 'Parkir Motor']],
        ['putri-kos-b', 'kos-b', 'putri', 'Kos Kaliurang A2', 'Kamar Kos', 'Jl. Kaliurang No. 8, Yogyakarta', 850000, 'rented', ['AC', 'WiFi', 'Kamar Mandi Dalam', 'Listrik Token']],
        ['putri-kos-c', 'kos-c', 'putri', 'Kos Gejayan B1', 'Kamar Kos', 'Jl. Gejayan No. 21, Yogyakarta', 900000, 'available', ['AC', 'WiFi', 'Kamar Mandi Dalam', 'Dapur']],
        ['putri-kos-d', 'kos-d', 'putri', 'Kos Gejayan B2', 'Kamar Kos', 'Jl. Gejayan No. 21, Yogyakarta', 750000, 'available', ['AC', 'WiFi', 'Kloset Duduk', 'Parkir Motor']],
        ['putri-kontrakan-a', 'kontrakan-a', 'putri', 'Kontrakan Gejayan', 'Kontrakan', 'Jl. Gejayan No. 40, Yogyakarta', 1600000, 'rented', ['Dapur', 'Kamar Mandi Dalam', 'Parkir Motor']],
        ['putri-kontrakan-b', 'kontrakan-b', 'putri', 'Kontrakan Magelang', 'Kontrakan', 'Jl. Magelang No. 12, Yogyakarta', 1700000, 'rented', ['Dapur', 'Listrik Token', 'Kamar Mandi Dalam']],
        ['putri-kontrakan-c', 'kontrakan-c', 'putri', 'Kontrakan Kaliurang', 'Kontrakan', 'Jl. Kaliurang No. 55, Yogyakarta', 1500000, 'available', ['Dapur', 'Parkir Motor', 'Listrik Token']],
        ['putri-kontrakan-d', 'kontrakan-d', 'putri', 'Kontrakan Malioboro', 'Kontrakan', 'Jl. Malioboro No. 30, Yogyakarta', 1800000, 'available', ['Dapur', 'Kamar Mandi Dalam', 'Parkir Motor']],
        ['putri-ruko-a', 'ruko-a', 'putri', 'Ruko Malioboro', 'Ruang Usaha', 'Jl. Malioboro No. 88, Yogyakarta', 4600000, 'rented', ['Etalase', 'Listrik Token', 'Parkir Motor']],
        ['putri-ruko-b', 'ruko-b', 'putri', 'Ruko Gejayan', 'Ruang Usaha', 'Jl. Gejayan No. 9, Yogyakarta', 4000000, 'rented', ['Etalase', 'Kamar Mandi Dalam', 'Listrik Token']],
        ['putri-ruko-c', 'ruko-c', 'putri', 'Ruko Magelang', 'Ruang Usaha', 'Jl. Magelang No. 77, Yogyakarta', 3800000, 'available', ['Etalase', 'Parkir Motor', 'Keamanan 24 Jam']],
        ['putri-ruko-d', 'ruko-d', 'putri', 'Ruko Kaliurang', 'Ruang Usaha', 'Jl. Kaliurang No. 120, Yogyakarta', 3600000, 'maintenance', ['Etalase', 'Listrik Token', 'Kamar Mandi Dalam']],
        ['putri-apartemen-a', 'apartemen-a', 'putri', 'Apartemen Malioboro Studio', 'Apartemen', 'Jl. Malioboro No. 150, Yogyakarta', 2200000, 'rented', ['AC', 'WiFi', 'Lift', 'Keamanan 24 Jam']],
        ['putri-apartemen-b', 'apartemen-b', 'putri', 'Apartemen Gejayan 1 BR', 'Apartemen', 'Jl. Gejayan No. 60, Yogyakarta', 2500000, 'available', ['AC', 'WiFi', 'Lift', 'Parkir Motor']],
        ['putri-apartemen-c', 'apartemen-c', 'putri', 'Apartemen Magelang Studio', 'Apartemen', 'Jl. Magelang No. 44, Yogyakarta', 2000000, 'available', ['AC', 'WiFi', 'Keamanan 24 Jam', 'Parkir Motor']],
        ['putri-apartemen-d', 'apartemen-d', 'putri', 'Apartemen Kaliurang 1 BR', 'Apartemen', 'Jl. Kaliurang No. 210, Yogyakarta', 2400000, 'available', ['AC', 'WiFi', 'Lift', 'Kamar Mandi Dalam']],
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
        ['Rizky Ramadhan', 'wahyu', null, '081234500019', 'verified', '3578011203940019', 'Jl. Bratang Gede No. 15, Surabaya'],
        ['Lestari Wulandari', 'wahyu', null, '081234500020', 'pending', '3578011203940020', 'Jl. Ketintang Baru No. 60, Surabaya'],
        ['Bayu Nugroho', 'rina', null, '081234500021', 'verified', '3573024506950021', 'Jl. Ijen No. 22, Malang'],
        ['Intan Permata', 'rina', null, '081234500022', 'unverified', '3573024506950022', 'Jl. Raya Araya No. 3, Malang'],
        ['Fajar Hidayat', 'dimas', null, '081234500023', 'verified', '3273011203940023', 'Jl. Dago Asri No. 4, Bandung'],
        ['Ayu Kusuma', 'dimas', null, '081234500024', 'pending', '3273011203940024', 'Jl. Cihampelas No. 118, Bandung'],
        ['Hendra Wijaya', 'dimas', null, '081234500025', 'verified', '3273011203940025', 'Jl. Sukajadi No. 27, Bandung'],
        ['Mega Puspita', 'dimas', null, '081234500026', 'unverified', '3273011203940026', 'Jl. Buah Batu No. 90, Bandung'],
        ['Yoga Pratama', 'dimas', null, '081234500027', 'verified', '3273011203940027', 'Jl. Sukajadi No. 66, Bandung'],
        ['Sinta Amelia', 'dimas', null, '081234500028', 'pending', '3273011203940028', 'Jl. Dago Asri No. 100, Bandung'],
        ['Galih Susanto', 'putri', null, '081234500029', 'verified', '3471011203940029', 'Jl. Kaliurang No. 8, Yogyakarta'],
        ['Nadia Rahma', 'putri', null, '081234500030', 'pending', '3471011203940030', 'Jl. Gejayan No. 21, Yogyakarta'],
        ['Arif Budiman', 'putri', null, '081234500031', 'verified', '3471011203940031', 'Jl. Gejayan No. 40, Yogyakarta'],
        ['Dewi Anggraini', 'putri', null, '081234500032', 'unverified', '3471011203940032', 'Jl. Magelang No. 12, Yogyakarta'],
        ['Rizal Fauzi', 'putri', null, '081234500033', 'verified', '3471011203940033', 'Jl. Malioboro No. 88, Yogyakarta'],
        ['Tari Wulandari', 'putri', null, '081234500034', 'pending', '3471011203940034', 'Jl. Malioboro No. 150, Yogyakarta'],
    ];

    /**
     * [slug unit, nama penyewa, status, bulan mulai, bulan akhir] relatif bulan berjalan.
     */
    private const CONTRACTS = [
        // wahyu — 7 active, 2 pending, 1 completed, 1 cancelled
        ['wahyu-kos-a', 'Bima Saputra', 'active', -8, 3],
        ['wahyu-kos-b', 'Andi Pratama', 'active', -6, 5],
        ['wahyu-kos-c', 'Nur Aisyah', 'active', -4, 7],
        ['wahyu-kontrakan-a', 'Fitri Handayani', 'active', -10, 1],
        ['wahyu-kontrakan-d', 'Rizky Ramadhan', 'active', -7, 4],
        ['wahyu-ruko-d', 'Lestari Wulandari', 'active', -5, 6],
        ['wahyu-apartemen-c', 'Bima Saputra', 'active', -2, 9],
        ['wahyu-kos-d', 'Bima Saputra', 'pending', 1, 12],
        ['wahyu-ruko-e', 'Rizky Ramadhan', 'pending', 1, 12],
        ['wahyu-kontrakan-b', 'Dedi Kurniawan', 'completed', -14, -3],
        ['wahyu-apartemen-d', 'Nur Aisyah', 'cancelled', -6, -5],

        // rina
        ['rina-kontrakan-c', 'Agus Setiawan', 'active', -3, 8],
        ['rina-ruko-a', 'Dedi Kurniawan', 'active', -5, 6],
        ['rina-apartemen-a', 'Sari Dewi', 'active', -2, 9],
        ['rina-kos-e', 'Bayu Nugroho', 'active', -9, 2],
        ['rina-kos-f', 'Intan Permata', 'active', -6, 5],
        ['rina-kontrakan-d', 'Bayu Nugroho', 'active', -4, 7],
        ['rina-kontrakan-e', 'Intan Permata', 'active', -2, 9],
        ['rina-ruko-b', 'Maya Lestari', 'pending', 1, 12],
        ['rina-kos-g', 'Sari Dewi', 'pending', 1, 12],
        ['rina-kontrakan-f', 'Maya Lestari', 'completed', -13, -2],
        ['rina-apartemen-b', 'Maya Lestari', 'cancelled', -6, -5],

        // dimas
        ['dimas-kos-a', 'Fajar Hidayat', 'active', -8, 3],
        ['dimas-kos-b', 'Ayu Kusuma', 'active', -6, 5],
        ['dimas-kontrakan-a', 'Hendra Wijaya', 'active', -10, 1],
        ['dimas-kontrakan-b', 'Mega Puspita', 'active', -4, 7],
        ['dimas-ruko-a', 'Yoga Pratama', 'active', -5, 6],
        ['dimas-ruko-b', 'Sinta Amelia', 'active', -3, 8],
        ['dimas-apartemen-a', 'Fajar Hidayat', 'active', -2, 9],
        ['dimas-kos-c', 'Ayu Kusuma', 'pending', 1, 12],
        ['dimas-ruko-c', 'Hendra Wijaya', 'pending', 1, 12],
        ['dimas-kontrakan-c', 'Mega Puspita', 'completed', -14, -3],
        ['dimas-apartemen-b', 'Sinta Amelia', 'cancelled', -6, -5],

        // putri
        ['putri-kos-a', 'Galih Susanto', 'active', -9, 2],
        ['putri-kos-b', 'Nadia Rahma', 'active', -7, 4],
        ['putri-kontrakan-a', 'Arif Budiman', 'active', -10, 1],
        ['putri-kontrakan-b', 'Dewi Anggraini', 'active', -4, 7],
        ['putri-ruko-a', 'Rizal Fauzi', 'active', -6, 5],
        ['putri-ruko-b', 'Tari Wulandari', 'active', -3, 8],
        ['putri-apartemen-a', 'Galih Susanto', 'active', -2, 9],
        ['putri-kos-c', 'Nadia Rahma', 'pending', 1, 12],
        ['putri-ruko-c', 'Arif Budiman', 'pending', 1, 12],
        ['putri-kontrakan-c', 'Dewi Anggraini', 'completed', -13, -2],
        ['putri-apartemen-b', 'Tari Wulandari', 'cancelled', -5, -4],
    ];

    /**
     * Satu unit per pemilik yang bulan lampaunya ditandai `rejected`, supaya UI punya contohnya.
     */
    private const REJECTED_UNITS = ['Kos Melati B1', 'Ruko Soekarno Hatta', 'Kos Dago Asri A1', 'Kos Kaliurang A1'];

    /**
     * Dua kontrak per pemilik ini pembayaran bulan pertamanya dicatat pemilik unit
     * sebagai bukti transfer manual.
     */
    private const OWNER_RECORDED_UNITS = [
        'wahyu-kos-a', 'wahyu-ruko-d',
        'rina-ruko-a', 'rina-kos-e',
        'dimas-ruko-a', 'dimas-apartemen-a',
        'putri-ruko-a', 'putri-apartemen-a',
    ];

    public function run(): void
    {
        $images = $this->downloadPhotos();

        $owners = [];
        foreach (self::OWNERS as $key => [$name, $email]) {
            // firstOrCreate, bukan updateOrCreate: /api/register publik, jadi email demo
            // bisa saja sudah didaftarkan orang lain. Jangan pernah menimpa kata sandinya.
            $owners[$key] = User::firstOrCreate(
                ['email' => $email],
                ['name' => $name, 'password' => self::PASSWORD, 'role' => 'owner']
            );
        }

        $units = [];
        foreach (self::UNITS as [$slug, $photo, $owner, $name, $type, $address, $price, $status, $facilities]) {
            $units[$slug] = Unit::updateOrCreate(
                ['owner_id' => $owners[$owner]->id, 'name' => $name],
                [
                    'type' => $type,
                    'facilities' => $facilities,
                    'image' => $images[$photo],
                    'address' => $address,
                    'price' => $price,
                    'status' => $status,
                ]
            );
        }

        $tenants = [];
        foreach (self::TENANTS as [$name, $owner, $email, $phone, $ktpStatus, $nik, $address]) {
            $user = $email ? User::firstOrCreate(
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
                    /* `end_date` jatuh di hari pertama bulan sesudah masa sewa, jadi
                       jumlah bulannya = selisih offset. Menambah 1 membuat harga
                       bulanan hasil bagi (total / bulan) tidak sama dengan harga
                       unit — frontend menampilkan angka itu apa adanya. */
                    'total_price' => $units[$slug]->price * ($endOffset - $startOffset),
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
                $paymentStatus = $isCurrentMonth
                    ? 'pending'
                    : (in_array($unit->name, self::REJECTED_UNITS, true) ? 'rejected' : 'paid');

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

        // Blok sengaja di unit tanpa kontrak sama sekali: pengajuan sewa yang mulai awal
        // bulan depan akan ditolak hasOverlap() kalau blok H+3..H+10 menabraknya.
        foreach ([
            ['wahyu-kontrakan-b', 'Perbaikan atap'],
            ['rina-apartemen-b', 'Cat ulang'],
            ['dimas-kontrakan-d', 'Perbaikan atap'],
            ['putri-apartemen-c', 'Cat ulang'],
        ] as [$slug, $note]) {
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
            $contract = $contracts['wahyu-kos-a'];

            foreach (['h_minus_7', 'h_minus_1', 'h_plus_1'] as $type) {
                $wahyu->notify(new PaymentDueReminder($type, $month, (int) $units['wahyu-kos-a']->price, $due, $contract));
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
