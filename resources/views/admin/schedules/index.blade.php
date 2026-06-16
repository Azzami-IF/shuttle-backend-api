@extends('admin.layout')

@section('title', 'Manajemen Jadwal')

@section('content')
<div class="flex flex-col md:flex-row items-center justify-between mb-6 gap-4">
    <h2 class="text-2xl font-bold text-primary">Daftar Jadwal Shuttle</h2>

    <div class="flex flex-col sm:flex-row items-center gap-4 w-full md:w-auto">
        <form action="{{ route('admin.schedules') }}" method="GET" class="flex gap-2 w-full sm:w-auto">
            <input type="text" name="origin" value="{{ request('origin') }}"
                class="border-outline-variant rounded-lg px-3 py-2 text-sm focus:ring-primary focus:border-primary"
                placeholder="Asal">
            <input type="text" name="destination" value="{{ request('destination') }}"
                class="border-outline-variant rounded-lg px-3 py-2 text-sm focus:ring-primary focus:border-primary"
                placeholder="Tujuan">
            <button type="submit" class="bg-secondary-container text-secondary p-2 rounded-lg">
                <span class="material-symbols-outlined">search</span>
            </button>
        </form>

        <a href="{{ route('admin.schedules.create') }}" class="bg-primary text-white px-4 py-2 rounded-lg font-bold flex items-center gap-2 whitespace-nowrap w-full sm:w-auto justify-center">
            <span class="material-symbols-outlined">add</span>
            Tambah Jadwal
        </a>
    </div>
</div>

<div class="bg-white border border-outline-variant rounded-xl overflow-hidden shadow-sm">
    <table class="w-full text-left">
        <thead class="bg-gray-50 border-b border-outline-variant">
            <tr>
                <th class="px-6 py-3 text-sm font-bold text-primary uppercase">Rute</th>
                <th class="px-6 py-3 text-sm font-bold text-primary uppercase">Waktu</th>
                <th class="px-6 py-3 text-sm font-bold text-primary uppercase">Kendaraan / Supir</th>
                <th class="px-6 py-3 text-sm font-bold text-primary uppercase text-right">Aksi</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-outline-variant">
            @foreach($schedules as $schedule)
            <tr class="hover:bg-gray-50">
                <td class="px-6 py-4">
                    <p class="font-bold text-primary">{{ $schedule->origin }} → {{ $schedule->destination }}</p>
                </td>
                <td class="px-6 py-4 text-on-surface-variant">
                    {{ \Carbon\Carbon::parse($schedule->departure_time)->format('d M Y, H:i') }}
                </td>
                <td class="px-6 py-4">
                    <p class="text-sm font-medium text-primary">{{ $schedule->vehicle->name }}</p>
                    <p class="text-xs text-on-surface-variant">Supir: {{ $schedule->driver->name }}</p>
                </td>
                <td class="px-6 py-4 text-right">
                    <form action="{{ route('admin.schedules.delete', $schedule->id) }}" method="POST" class="inline">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="text-red-500 hover:text-red-700" onclick="return confirm('Hapus jadwal ini?')">
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
