@extends('admin.layout')

@section('title','Edit Pengguna')

@section('content')
<div class="max-w-md mx-auto">
    <div class="glass-card p-6">
        <h2 class="text-2xl font-bold mb-4">Edit Pengguna</h2>
        <form method="POST" action="{{ route('admin.users.update', $user) }}">
            @csrf
            @method('PUT')
            <div class="mb-3">
                <label class="block text-sm text-gray-700">Nama</label>
                <input name="name" type="text" value="{{ $user->name }}" required class="w-full border px-3 py-2 rounded" />
            </div>
            <div class="mb-3">
                <label class="block text-sm text-gray-700">Email</label>
                <input name="email" type="email" value="{{ $user->email }}" required class="w-full border px-3 py-2 rounded" />
            </div>
            <div class="mb-3">
                <label class="block text-sm text-gray-700">Role</label>
                <select name="role" class="w-full border px-3 py-2 rounded">
                    <option value="customer" {{ $user->role === 'customer' ? 'selected' : '' }}>Customer</option>
                    <option value="driver" {{ $user->role === 'driver' ? 'selected' : '' }}>Driver</option>
                    <option value="admin" {{ $user->role === 'admin' ? 'selected' : '' }}>Admin</option>
                </select>
            </div>
            <div class="mb-3">
                <label class="block text-sm text-gray-700">Phone</label>
                <input name="phone" type="text" value="{{ $user->phone }}" class="w-full border px-3 py-2 rounded" />
            </div>
            <div class="mb-3">
                <label class="block text-sm text-gray-700">Driver Code (optional)</label>
                <input name="driver_code" type="text" value="{{ $user->driver_code }}" class="w-full border px-3 py-2 rounded" />
            </div>
            <div class="mb-3">
                <label class="block text-sm text-gray-700">Password (kosongkan untuk tetap)</label>
                <input name="password" type="password" class="w-full border px-3 py-2 rounded" />
            </div>
            <div class="flex justify-end">
                <button class="bg-primary text-white px-4 py-2 rounded">Simpan</button>
            </div>
        </form>
    </div>
</div>
@endsection
