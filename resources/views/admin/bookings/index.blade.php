@extends('admin.layout')

@section('title', 'Monitoring Pemesanan')

@section('content')
<div class="flex flex-col gap-6">
    <div class="flex justify-between items-center">
        <div>
            <h1 class="text-2xl font-bold text-primary">Monitoring Pemesanan</h1>
            <p class="text-sm text-on-surface-variant">Daftar semua transaksi tiket dan status pembayaran penumpang.</p>
        </div>
    </div>

    <!-- Filters & Search -->
    <div class="bg-white p-4 rounded-xl shadow-sm border border-gray-100 flex flex-col md:flex-row gap-4 justify-between items-center">
        <form method="GET" action="{{ route('admin.bookings') }}" class="flex flex-col md:flex-row gap-3 w-full">
            <div class="flex-1">
                <input type="text" name="search" value="{{ request('search') }}" placeholder="Cari nama penumpang, asal, atau tujuan..." class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:border-secondary text-sm"/>
            </div>
            <div class="w-full md:w-48">
                <select name="status" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:border-secondary text-sm">
                    <option value="">Semua Status</option>
                    <option value="pending_payment" {{ request('status') === 'pending_payment' ? 'selected' : '' }}>Pending Payment</option>
                    <option value="pending_verification" {{ request('status') === 'pending_verification' ? 'selected' : '' }}>Menunggu Konfirmasi</option>
                    <option value="booked" {{ request('status') === 'booked' ? 'selected' : '' }}>Booked / Paid</option>
                    <option value="cancelled" {{ request('status') === 'cancelled' ? 'selected' : '' }}>Cancelled</option>
                    <option value="completed" {{ request('status') === 'completed' ? 'selected' : '' }}>Completed</option>
                </select>
            </div>
            <button type="submit" class="bg-secondary text-white px-6 py-2 rounded-lg font-semibold text-sm hover:bg-opacity-90">
                Filter
            </button>
            @if(request()->has('search') || request()->has('status'))
                <a href="{{ route('admin.bookings') }}" class="border border-gray-300 text-gray-700 px-4 py-2 rounded-lg text-sm hover:bg-gray-50 flex items-center justify-center">
                    Reset
                </a>
            @endif
        </form>
    </div>

    <!-- Table -->
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse">
                <thead>
                    <tr class="bg-gray-50 border-b border-gray-100 text-sm font-semibold text-on-surface-variant">
                        <th class="px-6 py-4">ID</th>
                        <th class="px-6 py-4">Penumpang</th>
                        <th class="px-6 py-4">Rute & Jadwal</th>
                        <th class="px-6 py-4">Total</th>
                        <th class="px-6 py-4">Bukti Bayar</th>
                        <th class="px-6 py-4">Status</th>
                        <th class="px-6 py-4 text-center">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 text-sm">
                    @forelse($bookings as $booking)
                        <tr class="hover:bg-gray-50 transition-colors">
                            <td class="px-6 py-4 font-semibold uppercase text-xs">#TCK{{ $booking->id }}</td>
                            <td class="px-6 py-4">
                                <div class="font-medium text-gray-900">{{ $booking->user?->name }}</div>
                                <div class="text-[10px] text-gray-500 uppercase">{{ $booking->booking_code }}</div>
                            </td>
                            <td class="px-6 py-4">
                                <div class="font-medium text-gray-900">{{ $booking->schedule?->origin }} → {{ $booking->schedule?->destination }}</div>
                                <div class="text-[10px] text-gray-500">{{ \Carbon\Carbon::parse($booking->schedule?->departure_time)->format('d M Y H:mm') }}</div>
                            </td>
                             <td class="px-6 py-4 font-bold text-primary">
                                Rp {{ number_format($booking->aggregated_total ?? 0, 0, ',', '.') }}
                                @if($booking->group_count > 1)
                                    <div class="text-[9px] text-gray-400">({{ $booking->group_count }} Kursi)</div>
                                @endif
                            </td>
                            <td class="px-6 py-4">
                                @if($booking->payment_proof)
                                    <div class="relative group cursor-pointer w-10 h-10 rounded-lg overflow-hidden border border-gray-200 shadow-sm" 
                                         onclick="openProofModal('{{ asset('storage/' . $booking->payment_proof) }}')">
                                        <img src="{{ asset('storage/' . $booking->payment_proof) }}" class="w-full h-full object-cover">
                                        <div class="absolute inset-0 bg-black bg-opacity-0 group-hover:bg-opacity-20 flex items-center justify-center transition-all">
                                            <span class="material-symbols-outlined text-white text-xs opacity-0 group-hover:opacity-100">zoom_in</span>
                                        </div>
                                    </div>
                                @else
                                    <span class="text-[10px] text-gray-400 italic">Belum Upload</span>
                                @endif
                            </td>
                            <td class="px-6 py-4">
                                @if($booking->status === 'pending_payment')
                                    <span class="px-2 py-1 text-[10px] font-bold rounded-full bg-yellow-100 text-yellow-800 uppercase">Pending</span>
                                @elseif($booking->status === 'pending_verification')
                                    <span class="px-2 py-1 text-[10px] font-bold rounded-full bg-blue-100 text-blue-800 uppercase">Menunggu Konfirmasi</span>
                                @elseif($booking->status === 'booked')
                                    <span class="px-2 py-1 text-[10px] font-bold rounded-full bg-green-100 text-green-800 uppercase">Lunas</span>
                                @elseif($booking->status === 'cancelled')
                                    <span class="px-2 py-1 text-[10px] font-bold rounded-full bg-red-100 text-red-800 uppercase">Batal</span>
                                @elseif($booking->status === 'completed')
                                    <span class="px-2 py-1 text-[10px] font-bold rounded-full bg-blue-100 text-blue-800 uppercase">Selesai</span>
                                @endif
                            </td>
                            <td class="px-6 py-4">
                                <div class="flex items-center justify-center gap-2">
                                    @if($booking->status === 'pending_payment' || $booking->status === 'pending_verification')
                                        <form action="{{ route('admin.bookings.confirm', $booking->id) }}" method="POST">
                                            @csrf
                                            <button type="submit" class="bg-green-600 hover:bg-green-700 text-white p-2 rounded-lg transition-all shadow-sm" title="Approve">
                                                <span class="material-symbols-outlined text-sm block">check</span>
                                            </button>
                                        </form>
                                        <form action="{{ route('admin.bookings.reject', $booking->id) }}" method="POST">
                                            @csrf
                                            <button type="submit" class="bg-red-600 hover:bg-red-700 text-white p-2 rounded-lg transition-all shadow-sm" title="Reject"
                                                    onclick="return confirm('Tolak pembayaran ini?')">
                                                <span class="material-symbols-outlined text-sm block">close</span>
                                            </button>
                                        </form>
                                    @endif

                                    <button type="button" class="bg-gray-100 hover:bg-gray-200 text-gray-700 p-2 rounded-lg transition-all shadow-sm" title="Detail"
                                            onclick="openDetailModal({{ json_encode($booking->load(['user', 'schedule.vehicle'])) }}, '{{ $booking->aggregated_seats }}', {{ $booking->aggregated_total }})">
                                        <span class="material-symbols-outlined text-sm block">visibility</span>
                                    </button>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="px-6 py-12 text-center text-gray-500">
                                <span class="material-symbols-outlined text-4xl block mb-2">inbox</span>
                                Tidak ada data pemesanan ditemukan.
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
    <div class="relative max-w-2xl w-full flex flex-col items-center animate-in zoom-in duration-200">
        <button class="absolute -top-12 right-0 bg-white text-black p-2 rounded-full shadow-lg">
            <span class="material-symbols-outlined block">close</span>
        </button>
        <img id="modalImg" src="" class="max-h-[80vh] w-auto shadow-2xl rounded-xl border-4 border-white">
    </div>
</div>

<!-- BOOKING DETAIL MODAL -->
<div id="detailModal" class="fixed inset-0 z-[100] hidden overflow-auto bg-black bg-opacity-75 flex items-center justify-center p-4">
    <div class="bg-white rounded-2xl w-full max-w-lg shadow-2xl animate-in zoom-in duration-300 overflow-hidden">
        <div class="px-6 py-4 bg-[#18281e] text-white flex justify-between items-center">
            <h3 class="font-bold text-lg">Rincian Pemesanan</h3>
            <button onclick="closeDetailModal()" class="text-white hover:text-gray-200">
                <span class="material-symbols-outlined block">close</span>
            </button>
        </div>
        <div class="p-6 space-y-4 text-left">
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <p class="text-[10px] text-gray-400 uppercase font-bold">Penumpang</p>
                    <p id="detName" class="font-semibold text-gray-900">-</p>
                    <p id="detEmail" class="text-xs text-gray-500">-</p>
                </div>
                <div>
                    <p class="text-[10px] text-gray-400 uppercase font-bold">Status Tiket</p>
                    <span id="detStatusBadge" class="px-2 py-0.5 text-[10px] font-bold rounded-full">-</span>
                </div>
            </div>

            <div class="border-t border-dashed pt-4">
                <p class="text-[10px] text-gray-400 uppercase font-bold mb-2">Informasi Perjalanan</p>
                <div class="bg-gray-50 p-3 rounded-lg flex justify-between items-center text-left">
                    <div>
                        <p id="detRoute" class="font-bold text-gray-900">-</p>
                        <p id="detTime" class="text-xs text-gray-500">-</p>
                    </div>
                </div>
            </div>

            <div class="grid grid-cols-2 gap-4 py-2">
                <div>
                    <p class="text-[10px] text-gray-400 uppercase font-bold">Kendaraan</p>
                    <p id="detVehicle" class="font-semibold text-gray-900">-</p>
                </div>
                <div>
                    <p class="text-[10px] text-gray-400 uppercase font-bold">Nomor Kursi</p>
                    <p id="detSeat" class="font-bold text-secondary text-lg">-</p>
                </div>
            </div>

            <div class="border-t pt-4 flex justify-between items-end">
                <div>
                    <p class="text-[10px] text-gray-400 uppercase font-bold">Total Bayar</p>
                    <p id="detTotal" class="text-xl font-black text-primary">-</p>
                </div>
                <div class="text-right">
                    <p class="text-[10px] text-gray-400 uppercase font-bold">Kode Booking</p>
                    <p id="detCode" class="font-mono text-gray-600">-</p>
                </div>
            </div>
        </div>
        <div class="px-6 py-4 bg-gray-50 flex justify-end">
            <button onclick="closeDetailModal()" class="px-6 py-2 bg-gray-200 text-gray-700 rounded-lg text-sm font-bold">Tutup</button>
        </div>
    </div>
</div>

<script>
    function openProofModal(src) {
        document.getElementById('modalImg').src = src;
        document.getElementById('proofModal').classList.remove('hidden');
        document.getElementById('proofModal').classList.add('flex');
    }

    function openDetailModal(booking, seatLabels, totalVal) {
        console.log('Opening detail for:', booking);
        document.getElementById('detName').innerText = booking.user?.name || '-';
        document.getElementById('detEmail').innerText = booking.user?.email || '-';
        document.getElementById('detRoute').innerText = (booking.schedule?.origin + ' → ' + booking.schedule?.destination) || '-';
        
        if (booking.schedule?.departure_time) {
            const date = new Date(booking.schedule.departure_time);
            document.getElementById('detTime').innerText = date.toLocaleString('id-ID', { dateStyle: 'medium', timeStyle: 'short' });
        }

        document.getElementById('detVehicle').innerText = booking.schedule?.vehicle?.name || '-';
        document.getElementById('detSeat').innerText = seatLabels || '-';
        document.getElementById('detCode').innerText = booking.booking_code || '-';
        document.getElementById('detTotal').innerText = 'Rp ' + totalVal.toLocaleString('id-ID');

        const badge = document.getElementById('detStatusBadge');
        badge.innerText = booking.status.toUpperCase();
        badge.className = 'px-2 py-0.5 text-[10px] font-bold rounded-full ' + (
            booking.status === 'booked' ? 'bg-green-100 text-green-800' : 
            (booking.status === 'cancelled' ? 'bg-red-100 text-red-800' : 'bg-yellow-100 text-yellow-800')
        );

        document.getElementById('detailModal').classList.remove('hidden');
        document.getElementById('detailModal').classList.add('flex');
    }

    function closeDetailModal() {
        document.getElementById('detailModal').classList.add('hidden');
        document.getElementById('detailModal').classList.remove('flex');
    }
</script>
@endsection
