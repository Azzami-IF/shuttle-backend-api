@extends('admin.layout')

@section('title', 'Manajemen Pengguna')

@section('content')
<div class="flex flex-col md:flex-row items-center justify-between mb-6 gap-4">
    <h2 class="text-2xl font-bold text-primary">Manajemen Pengguna & Supir</h2>

    <div class="flex items-center gap-3">
        <a href="{{ route('admin.users.create') }}" class="bg-primary text-white px-4 py-2 rounded">Buat Pengguna</a>
        <form action="{{ route('admin.users') }}" method="GET" class="w-full md:w-64">
        <div class="relative">
            <input type="text" name="search" value="{{ request('search') }}"
                class="w-full border-outline-variant rounded-lg pl-10 pr-4 py-2 text-sm focus:ring-primary focus:border-primary"
                placeholder="Cari nama, email, telp...">
            <span class="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-outline text-sm">search</span>
        </div>
        </form>
    </div>
</div>

<div class="bg-white border border-outline-variant rounded-xl overflow-hidden shadow-sm">
    <table class="w-full text-left">
        <thead class="bg-gray-50 border-b border-outline-variant">
            <tr>
                <th class="px-6 py-3 text-sm font-bold text-primary uppercase">Nama</th>
                <th class="px-6 py-3 text-sm font-bold text-primary uppercase">Email / No. Telp</th>
                <th class="px-6 py-3 text-sm font-bold text-primary uppercase">Peran</th>
                <th class="px-6 py-3 text-sm font-bold text-primary uppercase text-right">Aksi</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-outline-variant">
            @foreach($users as $user)
            <tr class="hover:bg-gray-50">
                <td class="px-6 py-4">
                    <p class="font-bold text-primary">{{ $user->name }}</p>
                </td>
                <td class="px-6 py-4 text-on-surface-variant">
                    <p>{{ $user->email }}</p>
                    <p class="text-xs">{{ $user->phone }}</p>
                </td>
                <td class="px-6 py-4">
                    <span class="px-3 py-1 rounded-full text-xs font-bold {{ $user->role === 'admin' ? 'bg-red-100 text-red-700' : ($user->role === 'driver' ? 'bg-blue-100 text-blue-700' : 'bg-gray-100 text-gray-700') }}">
                        {{ strtoupper($user->role) }}
                    </span>
                </td>
                <td class="px-6 py-4 text-right">
                    <a href="{{ route('admin.users.edit', $user) }}" class="px-3 py-1 bg-secondary-container rounded text-secondary">Edit</a>
                    <form action="{{ route('admin.users.delete', $user) }}" method="POST" style="display:inline">@csrf @method('DELETE')
                        <button class="px-3 py-1 bg-red-100 text-red-700 rounded">Hapus</button>
                    </form>
                </td>
            </tr>
            @endforeach
        </tbody>
    </table>
</div>
@endsection
