@extends('admin.layout')

@section('title','Buat Pengguna')

@section('content')
<div class="max-w-md mx-auto">
    <div class="glass-card p-6">
        <h2 class="text-2xl font-bold mb-4">Buat Pengguna Baru</h2>
        <form method="POST" action="{{ route('admin.users.store') }}">
            @csrf
            <div class="mb-3">
                <label class="block text-sm text-gray-700">Nama</label>
                <input name="name" type="text" required class="w-full border px-3 py-2 rounded" />
            </div>
            <div class="mb-3">
                <label class="block text-sm text-gray-700">Email</label>
                <input name="email" type="email" required class="w-full border px-3 py-2 rounded" />
            </div>
            <div class="mb-3">
                <label class="block text-sm text-gray-700">Role</label>
                <select name="role" class="w-full border px-3 py-2 rounded">
                    <option value="customer">Customer</option>
                    <option value="driver">Driver</option>
                    <option value="admin">Admin</option>
                </select>
            </div>
            <div class="mb-3">
                <label class="block text-sm text-gray-700">Phone</label>
                <input name="phone" type="text" class="w-full border px-3 py-2 rounded" />
            </div>
            <div class="mb-3">
                <label class="block text-sm text-gray-700">Driver Code (optional)</label>
                <input name="driver_code" type="text" class="w-full border px-3 py-2 rounded" />
            </div>
            <div class="mb-3">
                <label class="block text-sm text-gray-700">Password</label>
                <input name="password" type="password" required class="w-full border px-3 py-2 rounded" />
            </div>
            <div class="flex justify-end">
                <button class="bg-primary text-white px-4 py-2 rounded">Buat</button>
            </div>
        </form>
    </div>
</div>
@endsection
