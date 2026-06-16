@extends('admin.layout')

@section('title', 'Manajemen Kendaraan')

@section('content')
<div class="flex flex-col md:flex-row items-center justify-between mb-6 gap-4">
    <h2 class="text-2xl font-bold text-primary">Daftar Kendaraan</h2>

    <div class="flex items-center gap-4 w-full md:w-auto">
        <form action="{{ route('admin.vehicles') }}" method="GET" class="flex-1 md:w-64">
            <div class="relative">
                <input type="text" name="search" value="{{ request('search') }}"
                    class="w-full border-outline-variant rounded-lg pl-10 pr-4 py-2 text-sm focus:ring-primary focus:border-primary"
                    placeholder="Cari nama atau plat...">
                <span class="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-outline text-sm">search</span>
            </div>
        </form>

        <a href="{{ route('admin.vehicles.create') }}" class="bg-primary text-white px-4 py-2 rounded-lg font-bold flex items-center gap-2 whitespace-nowrap">
            <span class="material-symbols-outlined">add</span>
            Tambah Kendaraan
        </a>
    </div>
</div>

<div class="bg-white border border-outline-variant rounded-xl overflow-hidden shadow-sm">
    <table class="w-full text-left">
        <thead class="bg-gray-50 border-b border-outline-variant">
            <tr>
                <th class="px-6 py-3 text-sm font-bold text-primary uppercase">Nama Kendaraan</th>
                <th class="px-6 py-3 text-sm font-bold text-primary uppercase">No. Plat</th>
                <th class="px-6 py-3 text-sm font-bold text-primary uppercase text-center">Kapasitas</th>
                <th class="px-6 py-3 text-sm font-bold text-primary uppercase text-right">Aksi</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-outline-variant">
            @foreach($vehicles as $vehicle)
            <tr class="hover:bg-gray-50">
                <td class="px-6 py-4">
                    <p class="font-bold text-primary">{{ $vehicle->name }}</p>
                </td>
                <td class="px-6 py-4 text-on-surface-variant">
                    {{ $vehicle->license_plate }}
                </td>
                <td class="px-6 py-4 text-center">
                    <span class="bg-secondary-container text-secondary px-3 py-1 rounded-full text-xs font-bold">{{ $vehicle->capacity }} Kursi</span>
                </td>
                <td class="px-6 py-4 text-right">
                    <a href="{{ route('admin.vehicles.edit', $vehicle->id) }}" class="text-on-surface-variant hover:text-primary mr-3">
                        <span class="material-symbols-outlined">edit</span>
                    </a>
                    <form action="{{ route('admin.vehicles.delete', $vehicle->id) }}" method="POST" class="inline">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="text-red-500 hover:text-red-700" onclick="return confirm('Hapus kendaraan ini?')">
                            <span class="material-symbols-outlined">delete</span>
                        </button>
                    </form>
                </td>
            </tr>
            @endforeach
        </tbody>
    </table>
</div>
@endsection
