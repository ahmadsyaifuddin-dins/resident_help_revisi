<?php

namespace Database\Seeders;

use App\Models\Customer;
use App\Models\MaintenanceOrder;
use App\Models\Ownership;
use App\Models\RepairPrice;
use App\Models\Technician;
use App\Models\Unit;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run()
    {
        // 1. SEEDER UNIT RUMAH (5 Data)
        $units = [];
        for ($i = 1; $i <= 5; $i++) {
            $units[] = Unit::create([
                'block' => $i <= 3 ? 'A' : 'B',
                'number' => '0'.$i,
                'type' => $i <= 3 ? '45' : '36',
                'land_size' => $i <= 3 ? 120 : 90,
                'building_size' => $i <= 3 ? 45 : 36,
                'electricity' => $i <= 3 ? 1300 : 900,
                'water_source' => 'PDAM Intan Banjar',
                'room_details' => '2 Kamar Tidur, 1 Kamar Mandi, Ruang Tamu, Dapur',
                'price' => $i <= 3 ? 450000000 : 300000000,
                'status' => $i <= 3 ? 'Terjual' : 'Tersedia', // 3 Terjual, 2 Tersedia
            ]);
        }

        // 2. SEEDER HARGA PERBAIKAN (5 Data)
        $prices = [
            ['service_name' => 'Perbaikan Atap Bocor Ringan', 'price' => 150000],
            ['service_name' => 'Ganti Kran Air / Pipa Bocor', 'price' => 75000],
            ['service_name' => 'Servis Konslet Listrik (Per Titik)', 'price' => 50000],
            ['service_name' => 'Pengecatan Ulang Tembok (Per Meter)', 'price' => 35000],
            ['service_name' => 'Ganti Keramik Pecah (Per Dus)', 'price' => 120000],
        ];
        foreach ($prices as $p) {
            RepairPrice::create($p);
        }

        // 3. SEEDER USERS (AKUN LOGIN)
        $password = Hash::make('password'); // Password semua sama: password

        $admin = User::create(['name' => 'Administrator', 'email' => 'admin@gmail.com', 'password' => $password, 'role' => 'admin', 'phone' => '081100001111']);
        $nasabah = User::create(['name' => 'Bapak Nasabah', 'email' => 'nasabah@gmail.com', 'password' => $password, 'role' => 'nasabah', 'phone' => '082200002222']);
        $warga = User::create(['name' => 'Anak Warga', 'email' => 'warga@gmail.com', 'password' => $password, 'role' => 'warga', 'phone' => '083300003333']);
        $teknisi1 = User::create(['name' => 'Kang Asep (Teknisi)', 'email' => 'teknisi@gmail.com', 'password' => $password, 'role' => 'teknisi', 'phone' => '084400004444']);
        $teknisi2 = User::create(['name' => 'Mang Peter (Teknisi)', 'email' => 'teknisi2@gmail.com', 'password' => $password, 'role' => 'teknisi', 'phone' => '085500005555']);

        // 4. SEEDER NASABAH / CUSTOMER (5 Data)
        $customers = [];
        $customers[] = Customer::create(['user_id' => $nasabah->id, 'name' => $nasabah->name, 'nik' => '6371012345670001', 'phone' => $nasabah->phone, 'address' => 'Jl. Asal Nasabah 1', 'domicile_address' => 'Blok A-01 Resident']);
        for ($i = 2; $i <= 5; $i++) {
            $customers[] = Customer::create(['user_id' => null, 'name' => 'Customer Dummy '.$i, 'nik' => '637101234567000'.$i, 'phone' => '08123456700'.$i, 'address' => 'Jl. Alamat Dummy '.$i, 'domicile_address' => 'Blok Dummy '.$i]);
        }

        // 5. SEEDER TEKNISI MASTER DATA (5 Data)
        $techs = [];
        $techs[] = Technician::create(['name' => $teknisi1->name, 'phone' => $teknisi1->phone, 'specialty' => 'Atap', 'status' => 'Available', 'user_id' => $teknisi1->id]);
        $techs[] = Technician::create(['name' => $teknisi2->name, 'phone' => $teknisi2->phone, 'specialty' => 'Listrik', 'status' => 'Busy', 'user_id' => $teknisi2->id]);
        $techs[] = Technician::create(['name' => 'Pak Slamet', 'phone' => '081233334444', 'specialty' => 'Air/Pipa', 'status' => 'Available', 'user_id' => null]);
        $techs[] = Technician::create(['name' => 'Mas Joko', 'phone' => '081233335555', 'specialty' => 'Bangunan/Semen', 'status' => 'Available', 'user_id' => null]);
        $techs[] = Technician::create(['name' => 'Pak Budi', 'phone' => '081233336666', 'specialty' => 'Kayu', 'status' => 'Available', 'user_id' => null]);

        // 6. SEEDER OWNERSHIP / KEPEMILIKAN RUMAH (3 Data, karena yg terjual cuma 3)
        $ownerships = [];
        $ownerships[] = Ownership::create(['unit_id' => $units[0]->id, 'customer_id' => $customers[0]->id, 'purchase_method' => 'KREDIT', 'bank_name' => 'BTN Syariah', 'handover_date' => Carbon::now()->subMonths(6), 'warranty_end_date' => Carbon::now()->addMonths(6), 'status' => 'Active']);
        $ownerships[] = Ownership::create(['unit_id' => $units[1]->id, 'customer_id' => $customers[1]->id, 'purchase_method' => 'CASH', 'bank_name' => null, 'handover_date' => Carbon::now()->subYears(2), 'warranty_end_date' => Carbon::now()->subYears(1), 'status' => 'Active']); // Garansi Habis
        $ownerships[] = Ownership::create(['unit_id' => $units[2]->id, 'customer_id' => $customers[2]->id, 'purchase_method' => 'KREDIT', 'bank_name' => 'BSI', 'handover_date' => Carbon::now()->subMonths(2), 'warranty_end_date' => Carbon::now()->addMonths(10), 'status' => 'Active']);

        // Assign Warga ke Rumah Nasabah 1 (Biar si Warga bisa ngetest lapor)
        $warga->update(['ownership_id' => $ownerships[0]->id]);

        // 7. SEEDER MAINTENANCE ORDERS (5 Data Berbagai Status)
        MaintenanceOrder::create([
            'ownership_id' => $ownerships[0]->id, 'reporter_id' => $warga->id, 'technician_id' => null,
            'complaint_title' => 'Tembok Retak Rambut', 'complaint_description' => 'Ada retak rambut di ruang tamu.',
            'complaint_date' => Carbon::now(), 'status' => 'Pending', 'cost' => 0, 'payment_status' => 'Free',
        ]);

        MaintenanceOrder::create([
            'ownership_id' => $ownerships[0]->id, 'reporter_id' => $nasabah->id, 'technician_id' => $techs[1]->id,
            'complaint_title' => 'Lampu Depan Mati', 'complaint_description' => 'Konslet saat hujan.',
            'complaint_date' => Carbon::now()->subDays(2), 'status' => 'In_Progress', 'cost' => 0, 'payment_status' => 'Free',
        ]);

        MaintenanceOrder::create([
            'ownership_id' => $ownerships[1]->id, 'reporter_id' => $nasabah->id, 'technician_id' => $techs[0]->id,
            'complaint_title' => 'Atap Bocor Parah', 'complaint_description' => 'Bocor di kamar utama.', // Garansi Habis
            'complaint_date' => Carbon::now()->subDays(5), 'completion_date' => Carbon::now()->subDays(1),
            'status' => 'Done', 'cost' => 150000, 'payment_status' => 'Unpaid', // Belum dibayar
        ]);

        MaintenanceOrder::create([
            'ownership_id' => $ownerships[2]->id, 'reporter_id' => $admin->id, 'technician_id' => $techs[2]->id,
            'complaint_title' => 'Pipa Dapur Mampet', 'complaint_description' => 'Air tidak mau turun.',
            'complaint_date' => Carbon::now()->subDays(10), 'completion_date' => Carbon::now()->subDays(8),
            'status' => 'Done', 'cost' => 0, 'payment_status' => 'Free', 'rating' => 5, 'review' => 'Cepat sekali, mantap!',
        ]);

        MaintenanceOrder::create([
            'ownership_id' => $ownerships[1]->id, 'reporter_id' => $nasabah->id, 'technician_id' => $techs[4]->id,
            'complaint_title' => 'Pintu Kamar Rusak', 'complaint_description' => 'Engsel lepas.', // Garansi Habis
            'complaint_date' => Carbon::now()->subMonths(1), 'completion_date' => Carbon::now()->subMonths(1)->addDays(2),
            'status' => 'Done', 'cost' => 50000, 'payment_status' => 'Paid', 'rating' => 4, 'review' => 'Bagus tapi agak telat.',
        ]);
    }
}
