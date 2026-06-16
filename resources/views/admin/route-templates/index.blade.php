@extends('admin.layout')

@section('content')
<div class="space-y-6">
    <!-- Header Section -->
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold text-primary">Manajemen Jadwal</h1>
            <p class="text-on-surface-variant text-sm">Kelola jadwal yang akan di-generate otomatis oleh sistem.</p>
        </div>
        <div class="flex gap-2">
            <form action="{{ route('admin.route-templates.generate') }}" method="POST">
                @csrf
                <button type="submit" class="flex items-center gap-2 bg-secondary text-white px-4 py-2 rounded-lg hover:bg-opacity-90 transition shadow-sm">
                    <span class="material-symbols-outlined text-sm">magic_button</span>
                    <span class="text-sm font-medium">Generate Jadwal Sekarang</span>
                </button>
            </form>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <!-- Kolom Kiri: Form Input -->
        <div class="lg:col-span-1">
            <div class="bg-white rounded-2xl p-6 shadow-sm border border-outline-variant">
                <h3 class="text-lg font-bold text-primary mb-4">Tambah Template Baru</h3>
                <form action="{{ route('admin.route-templates.store') }}" method="POST" class="space-y-4">
                    @csrf
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-xs font-bold text-outline mb-1">ASAL</label>
                            <input type="text" name="origin" class="w-full rounded-xl border-outline-variant text-sm focus:ring-secondary focus:border-secondary" placeholder="Jakarta" required>
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-outline mb-1">TUJUAN</label>
                            <input type="text" name="destination" class="w-full rounded-xl border-outline-variant text-sm focus:ring-secondary focus:border-secondary" placeholder="Bandung" required>
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-xs font-bold text-outline mb-1">JAM BERANGKAT</label>
                            <input type="time" name="departure_time" class="w-full rounded-xl border-outline-variant text-sm focus:ring-secondary focus:border-secondary" required>
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-outline mb-1">HARGA (RP)</label>
                            <input type="number" name="price" value="85000" class="w-full rounded-xl border-outline-variant text-sm focus:ring-secondary focus:border-secondary" required>
                        </div>
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-outline mb-1">ARMADA / UNIT</label>
                        <select name="vehicle_id" class="w-full rounded-xl border-outline-variant text-sm focus:ring-secondary focus:border-secondary" required>
                            <option value="">Pilih Armada...</option>
                            @foreach($vehicles as $vehicle)
                                <option value="{{ $vehicle->id }}">{{ $vehicle->name }} ({{ $vehicle->license_plate }})</option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-outline mb-1">DRIVER</label>
                        <select name="driver_id" class="w-full rounded-xl border-outline-variant text-sm focus:ring-secondary focus:border-secondary" required>
                            <option value="">Pilih Driver...</option>
                            @foreach($drivers as $driver)
                                <option value="{{ $driver->id }}">{{ $driver->name }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-outline mb-2">HARI AKTIF</label>
                        <div class="flex flex-wrap gap-2">
                            @foreach(['Min', 'Sen', 'Sel', 'Rab', 'Kam', 'Jum', 'Sab'] as $idx => $day)
                                <label class="flex items-center gap-1 bg-surface px-2 py-1 rounded-lg border border-outline-variant cursor-pointer hover:bg-secondary-container transition">
                                    <input type="checkbox" name="active_days[]" value="{{ $idx }}" checked class="rounded text-secondary focus:ring-secondary">
                                    <span class="text-xs font-medium">{{ $day }}</span>
                                </label>
                            @endforeach
                        </div>
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-outline mb-1">AUTO-GENERATE (HARI)</label>
                        <input type="number" name="generate_days_ahead" value="30" max="90" class="w-full rounded-xl border-outline-variant text-sm focus:ring-secondary focus:border-secondary">
                        <p class="text-[10px] text-outline mt-1">*Sistem akan selalu menjaga jadwal tersedia hingga X hari kedepan.</p>
                    </div>

                    <button type="submit" class="w-full bg-primary text-white font-bold py-3 rounded-xl hover:brightness-125 transition shadow-md mt-2">
                        Simpan Template Rute
                    </button>
                </form>
            </div>
        </div>

        <!-- Kolom Kanan: Tabel Data -->
        <div class="lg:col-span-2">
            <div class="bg-white rounded-2xl shadow-sm border border-outline-variant overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="w-full text-left border-collapse">
                        <thead class="bg-surface border-b border-outline-variant">
                            <tr>
                                <th class="px-6 py-4 text-xs font-bold text-outline uppercase">Rute & Jam</th>
                                <th class="px-6 py-4 text-xs font-bold text-outline uppercase">Unit & Driver</th>
                                <th class="px-6 py-4 text-xs font-bold text-outline uppercase text-center">Status</th>
                                <th class="px-6 py-4 text-xs font-bold text-outline uppercase text-right">Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-outline-variant">
                            @foreach($templates as $template)
                            <tr class="hover:bg-gray-50 transition">
                                <td class="px-6 py-4">
                                    <div class="font-bold text-primary">{{ $template->origin }} → {{ $template->destination }}</div>
                                    <div class="flex items-center gap-2 mt-1">
                                        <span class="bg-secondary-container text-secondary text-[10px] font-bold px-2 py-0.5 rounded-full">
                                            {{ \Carbon\Carbon::parse($template->departure_time)->format('H:i') }}
                                        </span>
                                        <span class="text-outline text-[10px]">Rp {{ number_format($template->price, 0, ',', '.') }}</span>
                                    </div>
                                </td>
                                <td class="px-6 py-4 text-sm">
                                    <div class="font-medium text-primary">{{ $template->vehicle->name }}</div>
                                    <div class="text-outline text-xs mt-0.5">{{ $template->driver->name }}</div>
                                </td>
                                <td class="px-6 py-4 text-center">
                                    <form action="{{ route('admin.route-templates.toggle', $template->id) }}" method="POST">
                                        @csrf
                                        <button type="submit" class="inline-flex">
                                            <span class="px-3 py-1 rounded-full text-[10px] font-bold {{ $template->is_active ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-500' }}">
                                                {{ $template->is_active ? 'AKTIF' : 'NONAKTIF' }}
                                            </span>
                                        </button>
                                    </form>
                                </td>
                                <td class="px-6 py-4 text-right">
                                    <form action="{{ route('admin.route-templates.destroy', $template->id) }}" method="POST" onsubmit="return confirm('Hapus template ini?')">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="text-red-500 hover:bg-red-50 p-2 rounded-full transition">
                                            <span class="material-symbols-outlined">delete</span>
                                        </button>
                                    </form>
                                </td>
                            </tr>
                            @endforeach
                            @if($templates->isEmpty())
                            <tr>
                                <td colspan="4" class="px-6 py-12 text-center text-outline text-sm italic">
                                    Belum ada template rute. Silakan tambah di kolom kiri.
                                </td>
                            </tr>
                            @endif
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
