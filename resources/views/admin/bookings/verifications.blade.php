@extends('admin.layout')

@section('title', 'Verifikasi Pembayaran')

@section('content')
<div class="flex flex-col gap-6">
    <div class="flex justify-between items-center">
        <div>
            <h1 class="text-2xl font-bold text-primary">Verifikasi Pembayaran</h1>
            <p class="text-sm text-on-surface-variant">Validasi bukti transfer dari penumpang untuk mengaktifkan tiket.</p>
        </div>
    </div>

    @if(session('success'))
        <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded-xl relative" role="alert">
            <span class="block sm:inline">{{ session('success') }}</span>
        </div>
    @endif

    @if(session('error'))
        <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded-xl relative" role="alert">
            <span class="block sm:inline">{{ session('error') }}</span>
        </div>
    @endif

    <!-- Table Component -->
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse">
                <thead>
                    <tr class="bg-gray-50 border-b border-gray-100 text-sm font-semibold text-on-surface-variant">
                        <th class="px-6 py-4">ID Pesanan</th>
                        <th class="px-6 py-4">Nama Buyer</th>
                        <th class="px-6 py-4">Total Harga</th>
                        <th class="px-6 py-4">Bukti Bayar</th>
                        <th class="px-6 py-4 text-center">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 text-sm text-gray-700">
                    @forelse($bookings as $booking)
                        <tr class="hover:bg-gray-50 transition-colors">
                            <td class="px-6 py-4">
                                <span class="font-bold text-primary">#TCK{{ $booking->id }}</span>
                                <div class="text-[10px] text-gray-400 uppercase mt-1">{{ $booking->booking_code }}</div>
                            </td>
                            <td class="px-6 py-4">
                                <div class="font-medium text-gray-900">{{ $booking->user?->name }}</div>
                                <div class="text-xs text-gray-500">{{ $booking->user?->email }}</div>
                            </td>
                            <td class="px-6 py-4 font-bold text-primary">
                                Rp {{ number_format(($booking->total_price ?? $booking->schedule?->price ?? 0) + ($booking->unique_code ?? 0), 0, ',', '.') }}
                            </td>
                            <td class="px-6 py-4">
                                @if($booking->payment_proof)
                                    <div class="relative group cursor-pointer w-16 h-16 rounded-lg overflow-hidden border border-gray-200 shadow-sm" 
                                         onclick="openProofModal('{{ asset('storage/' . $booking.payment_proof) }}')">
                                        <img src="{{ asset('storage/' . $booking->payment_proof) }}" 
                                             alt="Bukti Transfer" 
                                             class="w-full h-full object-cover group-hover:scale-110 transition-transform duration-200">
                                        <div class="absolute inset-0 bg-black bg-opacity-0 group-hover:bg-opacity-20 flex items-center justify-center transition-all">
                                            <span class="material-symbols-outlined text-white opacity-0 group-hover:opacity-100 scale-75">zoom_in</span>
                                        </div>
                                    </div>
                                @else
                                    <div class="w-16 h-16 rounded-lg bg-gray-100 flex flex-col items-center justify-center text-gray-400 border border-dashed border-gray-300">
                                        <span class="material-symbols-outlined text-sm">image_not_supported</span>
                                        <span class="text-[8px] uppercase mt-1">No File</span>
                                    </div>
                                @endif
                            </td>
                            <td class="px-6 py-4">
                                <div class="flex items-center justify-center gap-2">
                                    <form action="{{ route('admin.bookings.confirm', $booking->id) }}" method="POST">
                                        @csrf
                                        <button type="submit" class="bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded-lg text-xs font-bold flex items-center gap-1 shadow-sm transition-all active:scale-95">
                                            <span class="material-symbols-outlined text-sm">check_circle</span>
                                            Approve
                                        </button>
                                    </form>
                                    <form action="{{ route('admin.bookings.reject', $booking->id) }}" method="POST">
                                        @csrf
                                        <button type="submit" class="bg-red-600 hover:bg-red-700 text-white px-4 py-2 rounded-lg text-xs font-bold flex items-center gap-1 shadow-sm transition-all active:scale-95"
                                                onclick="return confirm('Yakin ingin menolak pembayaran ini? Pesanan akan dibatalkan.')">
                                            <span class="material-symbols-outlined text-sm">cancel</span>
                                            Reject
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="px-6 py-20 text-center text-gray-500">
                                <span class="material-symbols-outlined text-5xl mb-4 block text-gray-300">verified_user</span>
                                <div class="text-lg font-medium text-gray-400">Tidak ada pembayaran yang butuh verifikasi</div>
                                <p class="text-sm">Semua pembayaran saat ini sudah diproses.</p>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- IMAGE MODAL (ZOOM) -->
<div id="proofModal" class="fixed inset-0 z-[100] hidden overflow-auto bg-black bg-opacity-90 flex items-center justify-center p-4" onclick="this.classList.add('hidden')">
    <div class="relative max-w-4xl w-full flex flex-col items-center animate-in zoom-in duration-200">
        <div class="absolute -top-12 right-0 flex gap-4">
            <button class="bg-white bg-opacity-20 hover:bg-opacity-40 text-white p-2 rounded-full transition-all" onclick="event.stopPropagation(); window.open(document.getElementById('modalImg').src)">
                <span class="material-symbols-outlined">open_in_new</span>
            </button>
            <button class="bg-white text-black p-2 rounded-full hover:bg-gray-200 transition-all">
                <span class="material-symbols-outlined">close</span>
            </button>
        </div>
        <img id="modalImg" src="" class="max-h-[85vh] w-auto shadow-2xl rounded-lg border-4 border-white border-opacity-10" onclick="event.stopPropagation()">
        <div class="mt-6 text-white text-center">
            <h3 class="text-xl font-bold">Detail Bukti Transfer</h3>
            <p class="text-sm text-gray-400 mt-1 italic">Klik area luar untuk menutup</p>
        </div>
    </div>
</div>

<script>
    function openProofModal(src) {
        const modal = document.getElementById('proofModal');
        const img = document.getElementById('modalImg');
        img.src = src;
        modal.classList.remove('hidden');
        modal.classList.add('flex');
    }
</script>
@endsection
