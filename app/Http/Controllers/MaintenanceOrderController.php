<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\MaintenanceOrder;
use App\Models\Ownership;
use App\Models\RepairPrice;
use App\Models\Technician;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class MaintenanceOrderController extends Controller
{
    // --- FITUR ADMIN ---

    public function indexAdmin()
    {
        // Admin lihat semua keluhan (Urut yang terbaru & status pending duluan)
        $orders = MaintenanceOrder::with(['ownership.unit', 'ownership.customer', 'technician'])
            ->orderByRaw("FIELD(status, 'Pending', 'In_Progress', 'Done', 'Cancelled')")
            ->latest()
            ->paginate(10);

        return view('admin.maintenance.index', compact('orders'));
    }

    // --- FITUR TEKNISI ---

    /**
     * Dashboard daftar keluhan untuk teknisi:
     * - Keluhan baru (Pending)
     * - Sedang diproses (In_Progress)
     */
    public function indexTechnician()
    {
        $user = Auth::user();

        if ($user->role !== 'teknisi') {
            abort(403, 'Akses khusus teknisi.');
        }

        // 1. Cari Profil Tukang berdasarkan Akun yang Login
        $technician = \App\Models\Technician::where('user_id', $user->id)->first();

        if (! $technician) {
            // Jika Admin belum menghubungkan akun ini ke Master Tukang
            return abort(403, 'Akun Anda belum dihubungkan dengan Profil Teknisi oleh Admin.');
        }

        // 2. Pending Orders: Tampilkan semua yang belum di-assign teknisi siapapun
        $pendingOrders = MaintenanceOrder::with(['ownership.unit', 'ownership.customer', 'technician'])
            ->where('status', 'Pending')
            ->whereNull('technician_id') // Hanya yang nganggur
            ->orderByDesc('complaint_date')
            ->get();

        // 3. In Progress: HANYA TAMPILKAN TUGAS MILIK TEKNISI INI SAJA
        $inProgressOrders = MaintenanceOrder::with(['ownership.unit', 'ownership.customer', 'technician'])
            ->where('status', 'In_Progress')
            ->where('technician_id', $technician->id) // <--- FILTER KUNCI
            ->orderByDesc('complaint_date')
            ->get();

        // 4. Done: HANYA TAMPILKAN TUGAS YANG DISELESAIKAN TEKNISI INI
        $doneOrders = MaintenanceOrder::with(['ownership.unit', 'ownership.customer', 'technician'])
            ->where('status', 'Done')
            ->where('technician_id', $technician->id) // <--- FILTER KUNCI
            ->orderByDesc('complaint_date')
            ->limit(10)
            ->get();

        return view('technician.maintenance.index', compact(
            'pendingOrders',
            'inProgressOrders',
            'doneOrders'
        ));
    }

    /**
     * Teknisi mengubah status laporan (menerima & menyelesaikan perbaikan).
     * - Bisa ubah: Pending -> In_Progress
     * - Bisa ubah: In_Progress -> Done
     * Tidak mengubah biaya & pembayaran (tetap tugas admin).
     */
    public function technicianUpdateStatus(Request $request, $id)
    {
        $user = Auth::user();

        if ($user->role !== 'teknisi') {
            abort(403, 'Akses khusus teknisi.');
        }

        // Cari profil teknisi
        $technician = \App\Models\Technician::where('user_id', $user->id)->first();

        $request->validate([
            'status' => 'required|in:Pending,In_Progress,Done',
        ]);

        $order = MaintenanceOrder::findOrFail($id);
        $oldStatus = $order->status;
        $newStatus = $request->status;

        // Transisi 1: Teknisi "Mengklaim" tugas Pending menjadi In Progress
        if ($oldStatus === 'Pending' && $newStatus === 'In_Progress') {
            $order->status = 'In_Progress';
            $order->technician_id = $technician->id; // Kunci tugas ini buat dia!
            $technician->update(['status' => 'Busy']); // Status teknisi jadi sibuk
        }
        // Transisi 2: Teknisi menyelesaikan tugas
        elseif ($oldStatus === 'In_Progress' && $newStatus === 'Done') {
            // Pastikan ini benar-benar tugas dia
            if ($order->technician_id !== $technician->id) {
                return back()->with('error', 'Anda tidak berhak menyelesaikan tugas teknisi lain.');
            }

            $order->status = 'Done';
            $order->completion_date = now();
            $technician->update(['status' => 'Available']); // Teknisi kembali nganggur
        } else {
            return back()->with('error', 'Perubahan status tidak diizinkan.');
        }

        $order->save();

        return back()->with('success', 'Status perbaikan berhasil diperbarui.');
    }

    public function showAdmin($id)
    {
        $order = MaintenanceOrder::with(['ownership.unit', 'ownership.customer', 'technician', 'reporter'])->findOrFail($id);
        $technicians = Technician::where('status', 'Available')->get();

        // Ambil list harga buat referensi admin
        $repairPrices = RepairPrice::all();

        // Cek Status Garansi (Logic yang sama kayak di view)
        $isWarrantyExpired = Carbon::now()->greaterThan($order->ownership->warranty_end_date);

        return view('admin.maintenance.show', compact('order', 'technicians', 'repairPrices', 'isWarrantyExpired'));
    }

    public function updateStatus(Request $request, $id)
    {
        $order = MaintenanceOrder::findOrFail($id);

        // 1. SANITASI INPUT BIAYA (Ubah ke Integer murni)
        // Ini memastikan input kosong jadi 0, dan input angka jadi integer
        $costInput = $request->input('cost');
        $cleanCost = (int) $costInput;

        // 2. VALIDASI
        $request->validate([
            'status' => 'required',
        ]);

        // 3. UPDATE STATUS & DATA LAIN
        $order->status = $request->status;

        // Logic Teknisi (Busy/Available)
        if ($request->status == 'In_Progress' && $request->technician_id) {
            $order->technician_id = $request->technician_id;
            Technician::where('id', $request->technician_id)->update(['status' => 'Busy']);
        }

        if ($request->status == 'Done') {
            $order->completion_date = now();
            if ($order->technician_id) {
                Technician::where('id', $order->technician_id)->update(['status' => 'Available']);
            }
        }
        // Logic Biaya dipindah KELUAR dari if(Done).
        // Jadi mau statusnya 'Pending', 'In_Progress', atau 'Done', biaya tetap tersimpan.

        if ($cleanCost > 0) {
            $order->cost = $cleanCost;

            // Set Unpaid hanya jika belum lunas
            if ($order->payment_status != 'Paid') {
                $order->payment_status = 'Unpaid';
            }
        } else {
            // Jika admin input 0 atau kosong
            $order->cost = 0;

            // Kembalikan ke Free jika belum lunas
            if ($order->payment_status != 'Paid') {
                $order->payment_status = 'Free';
            }
        }

        $order->save();

        return redirect()->route('admin.maintenance.index')
            ->with('success', 'Status & Biaya berhasil diperbarui.');
    }
    // --- FITUR WARGA / USER ---

    public function indexUser()
    {
        // User cuma lihat keluhan dia sendiri
        $orders = MaintenanceOrder::with(['ownership.unit', 'technician'])
            ->where('reporter_id', Auth::id())
            ->latest()
            ->paginate(10);

        return view('complaints.index', compact('orders'));
    }

    public function create()
    {
        $user = Auth::user();
        $myHomes = collect([]); // Default kosong

        // SKENARIO 1: ROLE NASABAH (Cek via tabel Customers & Ownerships)
        if ($user->role === 'nasabah') {
            $customer = Customer::where('user_id', $user->id)->first();
            if ($customer) {
                $myHomes = Ownership::where('customer_id', $customer->id)
                    ->where('status', 'Active')
                    ->with('unit')
                    ->get();
            }
        }

        // SKENARIO 2: ROLE WARGA (Cek via kolom ownership_id di tabel Users)
        elseif ($user->role === 'warga') {
            if ($user->ownership_id) {
                // Ambil data ownership berdasarkan ID yang ditempel di user
                $myHomes = Ownership::where('id', $user->ownership_id)
                    ->where('status', 'Active')
                    ->with('unit')
                    ->get();
            }
        }

        // Jika Admin iseng buka, biarkan kosong atau tampilkan semua (opsional)

        return view('complaints.create', compact('myHomes'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'ownership_id' => 'required|exists:ownerships,id',
            'complaint_title' => 'required|string|max:255',
            'complaint_description' => 'required|string',
            'complaint_photo' => 'nullable|image|max:2048', // Max 2MB
        ]);

        $photoPath = null;
        if ($request->hasFile('complaint_photo')) {
            $photoPath = $request->file('complaint_photo')->store('complaints', 'public');
        }

        MaintenanceOrder::create([
            'ownership_id' => $request->ownership_id,
            'reporter_id' => Auth::id(),
            'complaint_title' => $request->complaint_title,
            'complaint_description' => $request->complaint_description,
            'complaint_photo' => $photoPath,
            'complaint_date' => now(),
            'status' => 'Pending',
            'cost' => 0,
            'payment_status' => 'Free',
        ]);

        return redirect()->route('complaints.index')
            ->with('success', 'Keluhan berhasil dikirim. Menunggu respon Admin.');
    }

    // --- FITUR UPDATE PEMBAYARAN ---
    public function markAsPaid($id)
    {
        $order = MaintenanceOrder::findOrFail($id);

        // Cek dulu apakah ada tagihan
        if ($order->cost <= 0) {
            return back()->with('error', 'Tidak ada tagihan untuk pesanan ini.');
        }

        // Update jadi Lunas
        $order->update([
            'payment_status' => 'Paid',
        ]);

        return back()->with('success', 'Tagihan berhasil ditandai LUNAS.');
    }

    public function showUser($id)
    {
        $order = MaintenanceOrder::where('reporter_id', Auth::id())->with('technician')->findOrFail($id);

        return view('complaints.show', compact('order'));
    }

    public function rateService(Request $request, $id)
    {
        $order = MaintenanceOrder::where('reporter_id', Auth::id())->findOrFail($id);

        $request->validate([
            'rating' => 'required|integer|min:1|max:5',
            'review' => 'nullable|string',
        ]);

        $order->update([
            'rating' => $request->rating,
            'review' => $request->review,
        ]);

        return back()->with('success', 'Terima kasih atas penilaian Anda!');
    }

    public function showTechnician($id)
    {
        $user = Auth::user();

        if ($user->role !== 'teknisi') {
            abort(403, 'Akses khusus teknisi.');
        }

        $technician = \App\Models\Technician::where('user_id', $user->id)->first();
        $order = MaintenanceOrder::with(['ownership.unit', 'ownership.customer', 'reporter'])->findOrFail($id);

        // Keamanan: Cegah teknisi melihat detail tugas yang sudah diklaim teknisi lain
        if ($order->technician_id !== null && $order->technician_id !== $technician->id) {
            abort(403, 'Akses ditolak. Ini adalah pekerjaan milik teknisi lain.');
        }

        return view('technician.maintenance.show', compact('order'));
    }

    // CETAK TIKET (BONUS)
    public function printTicket($id)
    {
        $order = MaintenanceOrder::with(['ownership.unit', 'technician'])
            ->where('reporter_id', Auth::id()) // Pastikan punya dia sendiri
            ->findOrFail($id);

        $pdf = Pdf::loadView('complaints.ticket', compact('order'));

        // Set ukuran kertas struk (opsional, kita pakai A4 setengah aja biar rapi)
        $pdf->setPaper('A5', 'landscape');

        return $pdf->stream('Tiket-Laporan-#'.$order->id.'.pdf');
    }
}
