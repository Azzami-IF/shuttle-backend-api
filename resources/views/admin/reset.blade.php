@extends('admin.layout')

@section('title','Reset Password')

@section('content')
<div class="max-w-md mx-auto mt-12">
    <div class="glass-card p-6">
        <h2 class="text-2xl font-bold mb-4">Reset Password</h2>
        <form method="POST" action="{{ route('admin.password.update') }}">
            @csrf
            <input type="hidden" name="token" value="{{ $token }}">
            <div class="mb-3">
                <label class="block text-sm text-gray-700">Email</label>
                <input name="email" type="email" required class="w-full border px-3 py-2 rounded" />
            </div>
            <div class="mb-3">
                <label class="block text-sm text-gray-700">Password</label>
                <input name="password" type="password" required class="w-full border px-3 py-2 rounded" />
            </div>
            <div class="mb-3">
                <label class="block text-sm text-gray-700">Confirm Password</label>
                <input name="password_confirmation" type="password" required class="w-full border px-3 py-2 rounded" />
            </div>
            <div class="flex justify-end">
                <button class="bg-primary text-white px-4 py-2 rounded">Reset</button>
            </div>
        </form>
    </div>
</div>
@endsection
