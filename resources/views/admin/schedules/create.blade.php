@extends('admin.layout')

@section('title', 'Buat Jadwal')

@section('content')
<div class="max-w-3xl">
    <div class="flex items-center gap-4 mb-6">
        <a href="{{ route('admin.schedules') }}" class="material-symbols-outlined text-primary p-2 hover:bg-gray-100 rounded-full">arrow_back</a>
        <h2 class="text-2xl font-bold text-primary">Buat Jadwal Perjalanan Baru</h2>
    </div>

    <div class="glass-card rounded-xl p-8 shadow-lg">
        <form action="{{ route('admin.schedules.store') }}" method="POST" class="space-y-6">
            @csrf
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <label class="block text-sm font-bold text-primary mb-2">Asal</label>
                    <input type="text" name="origin" class="w-full border-outline-variant rounded-lg p-3 focus:ring-primary focus:border-primary" placeholder="Contoh: Jakarta" required>
                </div>
                <div>
                    <label class="block text-sm font-bold text-primary mb-2">Tujuan</label>
                    <input type="text" name="destination" class="w-full border-outline-variant rounded-lg p-3 focus:ring-primary focus:border-primary" placeholder="Contoh: Bandung" required>
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <label class="block text-sm font-bold text-primary mb-2">Pilih Kendaraan</label>
                    <select name="vehicle_id" class="w-full border-outline-variant rounded-lg p-3 focus:ring-primary focus:border-primary" required>
                        <option value="">-- Pilih Kendaraan --</option>
                        @foreach($vehicles as $vehicle)
                            <option value="{{ $vehicle->id }}">{{ $vehicle->name }} ({{ $vehicle->license_plate }}) - Kapasitas: {{ $vehicle->capacity }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-bold text-primary mb-2">Pilih Supir</label>
                    <select name="driver_id" class="w-full border-outline-variant rounded-lg p-3 focus:ring-primary focus:border-primary" required>
                        <option value="">-- Pilih Supir --</option>
                        @foreach($drivers as $driver)
                            <option value="{{ $driver->id }}">{{ $driver->name }}</option>
                        @endforeach
                    </select>
                </div>
            </div>

            <div>
                <label class="block text-sm font-bold text-primary mb-2">Waktu Keberangkatan</label>
                <input type="datetime-local" name="departure_time" class="w-full border-outline-variant rounded-lg p-3 focus:ring-primary focus:border-primary" required>
            </div>

            <div class="pt-4">
                <button type="submit" class="w-full bg-primary text-white py-4 rounded-lg font-bold text-lg shadow-md hover:opacity-90 transition-all">Simpan Jadwal</button>
            </div>
        </form>
    </div>
</div>
@endsection
