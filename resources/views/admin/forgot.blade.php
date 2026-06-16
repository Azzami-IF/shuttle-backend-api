@extends('admin.layout')

@section('title','Lupa Password')

@section('content')
<div class="max-w-md mx-auto mt-12">
    <div class="glass-card p-6">
        <h2 class="text-2xl font-bold mb-4">Lupa Password</h2>
        @if(session('status'))<div class="bg-green-50 p-3 mb-3">{{ session('status') }}</div>@endif
        <form method="POST" action="{{ route('admin.password.email') }}">
            @csrf
            <div class="mb-3">
                <label class="block text-sm text-gray-700">Email</label>
                <input name="email" type="email" required class="w-full border px-3 py-2 rounded" />
            </div>
            <div class="flex justify-end">
                <button class="bg-primary text-white px-4 py-2 rounded">Kirim Link Reset</button>
            </div>
        </form>
    </div>
</div>
@endsection
