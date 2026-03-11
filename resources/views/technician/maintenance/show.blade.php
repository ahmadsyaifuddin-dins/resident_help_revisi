<x-app-layout>
    <x-slot name="header">
        {{ __('Detail Pekerjaan Perbaikan') }}
    </x-slot>

    <div class="max-w-3xl mx-auto bg-white p-6 rounded-lg shadow-md">
        <h3 class="text-lg font-bold mb-4 text-gray-700 border-b pb-2">Informasi Tugas</h3>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-6">
            <div>
                <p class="text-sm text-gray-500">Unit Rumah:</p>
                <p class="font-semibold text-lg text-blue-700">Blok {{ $order->ownership->unit->block }} - No.
                    {{ $order->ownership->unit->number }}</p>
                <p class="text-sm text-gray-600">Pemilik: {{ $order->ownership->customer->name }}
                    ({{ $order->ownership->customer->phone }})</p>
            </div>
            <div>
                <p class="text-sm text-gray-500">Tanggal Lapor:</p>
                <p class="font-semibold">{{ $order->complaint_date->format('d F Y') }}</p>
                <p class="text-sm text-gray-500 mt-2">Status Saat Ini:</p>
                @if ($order->status == 'In_Progress')
                    <span class="px-2 py-1 text-xs font-semibold text-yellow-800 bg-yellow-100 rounded-full">Sedang
                        Diproses</span>
                @elseif ($order->status == 'Pending')
                    <span class="px-2 py-1 text-xs font-semibold text-red-800 bg-red-100 rounded-full">Belum
                        Diambil</span>
                @else
                    <span
                        class="px-2 py-1 text-xs font-semibold text-green-800 bg-green-100 rounded-full">Selesai</span>
                @endif
            </div>
        </div>

        <div class="bg-gray-50 p-4 rounded border mb-6">
            <p class="text-sm text-gray-500">Judul Keluhan:</p>
            <h4 class="font-bold text-gray-800 text-lg">{{ $order->complaint_title }}</h4>
            <p class="text-sm text-gray-500 mt-3">Detail Kerusakan:</p>
            <p class="text-gray-700 mt-1">{{ $order->complaint_description }}</p>
        </div>

        @if ($order->complaint_photo)
            <div class="mb-6">
                <p class="text-sm text-gray-500 mb-2">Foto Kerusakan (Dari Warga):</p>
                <a href="{{ asset('storage/' . $order->complaint_photo) }}" target="_blank">
                    <img src="{{ asset('storage/' . $order->complaint_photo) }}"
                        class="max-w-full h-auto max-h-64 rounded border hover:opacity-75 transition">
                </a>
            </div>
        @else
            <div class="mb-6 p-4 bg-gray-100 rounded text-center text-gray-500 text-sm">
                <i class="fas fa-image mb-2 text-xl"></i><br>
                Tidak ada foto yang dilampirkan oleh pelapor.
            </div>
        @endif

        <div class="flex justify-between border-t pt-4">
            <a href="{{ route('technician.maintenance.index') }}"
                class="bg-gray-500 text-white px-4 py-2 rounded hover:bg-gray-600 transition">
                <i class="fas fa-arrow-left mr-1"></i> Kembali
            </a>

            {{-- Jika status masih In Progress, tampilkan tombol Selesai --}}
            @if ($order->status == 'In_Progress')
                <form action="{{ route('technician.maintenance.update', $order->id) }}" method="POST">
                    @csrf
                    @method('PUT')
                    <input type="hidden" name="status" value="Done">
                    <button type="submit"
                        class="bg-green-600 text-white px-6 py-2 rounded font-bold hover:bg-green-700 transition"
                        onclick="return confirm('Apakah Anda yakin tugas ini sudah selesai dikerjakan?')">
                        <i class="fas fa-check mr-1"></i> Tandai Selesai
                    </button>
                </form>
            @endif
        </div>
    </div>
</x-app-layout>
