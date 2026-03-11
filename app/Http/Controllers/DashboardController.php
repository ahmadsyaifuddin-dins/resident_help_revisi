<?php

namespace App\Http\Controllers;

use App\Models\MaintenanceOrder;
use App\Models\Technician;
use App\Models\Unit;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function index()
    {
        $user = Auth::user();

        // JIKA ADMIN: Tampilkan Statistik Global
        if ($user->role === 'admin') {
            // 1. Data Kartu Atas
            $totalUnits = Unit::count();
            $soldUnits = Unit::where('status', 'Terjual')->count();
            $availableUnits = Unit::where('status', 'Tersedia')->count();

            $technicians = Technician::count();
            $techAvailable = Technician::where('status', 'Available')->count();

            $pendingComplaints = MaintenanceOrder::where('status', 'Pending')->count();
            $processComplaints = MaintenanceOrder::where('status', 'In_Progress')->count();

            // 3. Data Chart 2: Keluhan per Bulan (Bar Chart) - 6 Bulan Terakhir
            $complaintsPerMonth = MaintenanceOrder::select(
                DB::raw('count(id) as total'),
                DB::raw("DATE_FORMAT(complaint_date, '%M') as month_name"),
                DB::raw('MONTH(complaint_date) as month')
            )
                ->whereYear('complaint_date', date('Y'))
                ->groupBy('month_name', 'month')
                ->orderBy('month')
                ->limit(6)
                ->pluck('total', 'month_name'); // Hasil: ['January' => 5, 'February' => 2]

            return view('dashboard', compact(
                'totalUnits',
                'soldUnits',
                'availableUnits',
                'technicians',
                'techAvailable',
                'pendingComplaints',
                'processComplaints',
                'complaintsPerMonth'
            ));
        }

        if ($user->role === 'teknisi') {
            // Cari profil teknisi yang terhubung dengan user ini
            $technician = Technician::where('user_id', $user->id)->first();

            // Jika belum terhubung, set semua statistik ke 0 untuk mencegah error
            if (! $technician) {
                $pendingComplaints = 0;
                $processComplaints = 0;
                $doneComplaints = 0;
            } else {
                // Keluhan Baru: Tampilkan semua yang statusnya 'Pending' dan belum ada teknisinya
                $pendingComplaints = MaintenanceOrder::where('status', 'Pending')
                    ->whereNull('technician_id')
                    ->count();

                // Sedang Diproses: HANYA yang dikerjakan oleh teknisi ini
                $processComplaints = MaintenanceOrder::where('status', 'In_Progress')
                    ->where('technician_id', $technician->id)
                    ->count();

                // Selesai Dikerjakan: HANYA yang diselesaikan oleh teknisi ini
                $doneComplaints = MaintenanceOrder::where('status', 'Done')
                    ->where('technician_id', $technician->id)
                    ->count();
            }

            return view('dashboard', compact(
                'pendingComplaints',
                'processComplaints',
                'doneComplaints'
            ));
        }

        // JIKA WARGA/NASABAH: Tampilkan Statistik Pribadi
        $myComplaintsTotal = MaintenanceOrder::where('reporter_id', $user->id)->count();
        $myComplaintsPending = MaintenanceOrder::where('reporter_id', $user->id)
            ->where('status', 'Pending')
            ->count();
        $myComplaintsDone = MaintenanceOrder::where('reporter_id', $user->id)
            ->where('status', 'Done')
            ->count();

        return view('dashboard', compact('myComplaintsTotal', 'myComplaintsPending', 'myComplaintsDone'));
    }
}
