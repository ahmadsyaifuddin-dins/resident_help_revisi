<?php

namespace App\Http\Controllers;

use App\Models\Ownership;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class UserController extends Controller
{
    public function index()
    {
        $users = User::latest()->paginate(10);

        return view('admin.users.index', compact('users'));
    }

    // Method CREATE (Menampilkan Form Tambah)
    public function create()
    {
        $ownerships = Ownership::with(['unit', 'customer'])
            ->where('status', 'Active')
            ->get();

        // Ambil data teknisi yang BELUM dihubungkan ke akun manapun
        $technicians = \App\Models\Technician::whereNull('user_id')->get();

        return view('admin.users.create', [
            'user' => new User,
            'ownerships' => $ownerships,
            'technicians' => $technicians, // Kirim ke view
        ]);
    }

    // Method STORE
    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users',
            'password' => 'required|string|min:8|confirmed',
            'role' => 'required',
            'ownership_id' => 'nullable|exists:ownerships,id',
            'technician_id' => 'nullable|exists:technicians,id', // Validasi baru
        ]);

        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'phone' => $request->phone,
            'role' => $request->role,
            'ownership_id' => $request->ownership_id,
        ]);

        // LOGIC KHUSUS TEKNISI: Hubungkan ke master data tukang
        if ($user->role === 'teknisi' && $request->technician_id) {
            \App\Models\Technician::where('id', $request->technician_id)
                ->update(['user_id' => $user->id]);
        }

        return redirect()->route('admin.users.index')
            ->with('success', 'Pengguna baru berhasil ditambahkan.');
    }

    // Method EDIT
    public function edit(User $user)
    {
        $ownerships = Ownership::with(['unit', 'customer'])
            ->where('status', 'Active')
            ->get();

        // Ambil teknisi yang BELUM terikat, ATAU yang terikat dengan user ini
        $technicians = \App\Models\Technician::whereNull('user_id')
            ->orWhere('user_id', $user->id)
            ->get();

        return view('admin.users.edit', compact('user', 'ownerships', 'technicians'));
    }

    // Method UPDATE
    public function update(Request $request, User $user)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email,'.$user->id,
            'role' => 'required',
            'ownership_id' => 'nullable|exists:ownerships,id',
            'technician_id' => 'nullable|exists:technicians,id', // Validasi baru
        ]);

        $data = $request->only(['name', 'email', 'role', 'ownership_id']);

        if ($request->filled('password')) {
            $data['password'] = Hash::make($request->password);
        }

        $user->update($data);

        // LOGIC KHUSUS TEKNISI (Update Relasi)
        if ($request->role === 'teknisi') {
            if ($request->technician_id) {
                // 1. Lepas dulu tukang lama yang mungkin nyangkut di user ini
                \App\Models\Technician::where('user_id', $user->id)->update(['user_id' => null]);

                // 2. Pasang tukang yang baru dipilih
                \App\Models\Technician::where('id', $request->technician_id)
                    ->update(['user_id' => $user->id]);
            }
        } else {
            // Jika role diubah (bukan teknisi lagi), lepas ikatannya
            \App\Models\Technician::where('user_id', $user->id)->update(['user_id' => null]);
        }

        return redirect()->route('admin.users.index')->with('success', 'Data pengguna diperbarui');
    }

    public function show(User $user)
    {
        return view('admin.users.show', compact('user'));
    }

    public function destroy(User $user)
    {
        // Cegah admin menghapus dirinya sendiri
        if (auth()->id() == $user->id) {
            return back()->with('error', 'Anda tidak bisa menghapus akun sendiri!');
        }

        $user->delete();

        return redirect()->route('admin.users.index')->with('success', 'Pengguna berhasil dihapus!');
    }
}
