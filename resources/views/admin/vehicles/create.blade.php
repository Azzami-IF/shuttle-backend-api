@extends('admin.layout')

@section('title', 'Tambah Kendaraan')

@section('content')
<div class="max-w-2xl">
    <div class="flex items-center gap-4 mb-6">
        <a href="{{ route('admin.vehicles') }}" class="material-symbols-outlined text-primary p-2 hover:bg-gray-100 rounded-full">arrow_back</a>
        <h2 class="text-2xl font-bold text-primary">Tambah Kendaraan Baru</h2>
    </div>

    <div class="glass-card rounded-xl p-8 shadow-lg">
        <form action="{{ route('admin.vehicles.store') }}" method="POST" class="space-y-6">
            @csrf
            <div>
                <label class="block text-sm font-bold text-primary mb-2">Nama Kendaraan</label>
                <input type="text" name="name" class="w-full border-outline-variant rounded-lg p-3 focus:ring-primary focus:border-primary" placeholder="Contoh: Toyota Hiace" required>
            </div>

            <div>
                <label class="block text-sm font-bold text-primary mb-2">No. Plat</label>
                <input type="text" name="license_plate" class="w-full border-outline-variant rounded-lg p-3 focus:ring-primary focus:border-primary" placeholder="B 1234 ABC" required>
            </div>

            <div>
                <label class="block text-sm font-bold text-primary mb-2">Kapasitas Kursi</label>
                <input type="number" name="capacity" class="w-full border-outline-variant rounded-lg p-3 focus:ring-primary focus:border-primary" placeholder="12" required>
            </div>

            <div class="pt-4">
                <button type="submit" class="w-full bg-primary text-white py-4 rounded-lg font-bold text-lg shadow-md hover:opacity-90 transition-all">Simpan Kendaraan</button>
            </div>
        </form>
    </div>
</div>
@endsection
