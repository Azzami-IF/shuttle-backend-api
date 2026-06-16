@extends('admin.layout')

@section('title', 'Admin Login')

@section('content')
<div class="max-w-md mx-auto mt-12">
    <div class="glass-card p-6">
        <h2 class="text-2xl font-bold mb-4">Masuk Admin</h2>
        @if($errors->any())
            <div class="bg-red-50 border border-red-200 text-red-700 px-3 py-2 rounded mb-3">{{ $errors->first() }}</div>
        @endif
        <form method="POST" action="{{ route('admin.login.post') }}">
            @csrf
            <div class="mb-3">
                <label class="block text-sm text-gray-700">Email</label>
                <input name="email" type="email" required class="w-full border px-3 py-2 rounded" />
            </div>
            <div class="mb-3">
                <label class="block text-sm text-gray-700">Password</label>
                <input name="password" type="password" required class="w-full border px-3 py-2 rounded" />
            </div>
            <div class="flex justify-end">
                <button class="bg-primary text-white px-4 py-2 rounded">Masuk</button>
            </div>
        </form>
    </div>
</div>
@endsection
