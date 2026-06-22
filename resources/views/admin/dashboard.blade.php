@extends('admin.layout')

@section('title', 'Dashboard')

@section('content')
<!-- Stats Grid -->
<div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-10">
    <div class="glass-card rounded-xl p-6 flex items-start justify-between">
        <div>
            <p class="text-on-surface-variant text-sm mb-1">Total Booking</p>
            <h3 class="text-2xl font-bold text-primary">{{ $stats['bookings'] }}</h3>
        </div>
        <div class="bg-secondary-container text-secondary p-3 rounded-xl">
            <span class="material-symbols-outlined">confirmation_number</span>
        </div>
    </div>
    <div class="glass-card rounded-xl p-6 flex items-start justify-between">
        <div>
            <p class="text-on-surface-variant text-sm mb-1">Armada Aktif</p>
            <h3 class="text-2xl font-bold text-primary">{{ $stats['active_trips'] }} / {{ $stats['vehicles'] }}</h3>
        </div>
        <div class="bg-secondary-container text-secondary p-3 rounded-xl">
            <span class="material-symbols-outlined">directions_bus</span>
        </div>
    </div>
    <div class="glass-card rounded-xl p-6 flex items-start justify-between">
        <div>
            <p class="text-on-surface-variant text-sm mb-1">Jadwal Hari Ini</p>
            <h3 class="text-2xl font-bold text-primary">{{ $stats['schedules'] }}</h3>
        </div>
        <div class="bg-secondary-container text-secondary p-3 rounded-xl">
            <span class="material-symbols-outlined">event_note</span>
        </div>
    </div>
    <div class="glass-card rounded-xl p-6 flex items-start justify-between">
        <div>
            <p class="text-on-surface-variant text-sm mb-1">Pendapatan Estimasi</p>
            <h3 class="text-2xl font-bold text-primary">Rp {{ number_format($stats['bookings'] * 85000, 0, ',', '.') }}</h3>
        </div>
        <div class="bg-secondary-container text-secondary p-3 rounded-xl">
            <span class="material-symbols-outlined">payments</span>
        </div>
    </div>
</div>

<!-- Charts Section -->
<div class="grid grid-cols-1 mb-10">
    <div class="glass-card rounded-xl p-6">
        <h2 class="text-xl font-bold text-primary mb-4">Tren Booking (7 Hari Terakhir)</h2>
        <div class="h-[300px]">
            <canvas id="bookingChart"></canvas>
        </div>
    </div>
</div>

<div class="grid grid-cols-1 xl:grid-cols-3 gap-8">
    <!-- Active Trips -->
    <div class="xl:col-span-2 space-y-8">
        <section>
            <div class="flex items-center justify-between mb-4">
                <h2 class="text-xl font-bold text-primary">Monitoring Perjalanan Aktif</h2>
            </div>
            <div class="bg-white border border-outline-variant rounded-xl overflow-hidden">
                <table class="w-full text-left">
                    <thead class="bg-gray-50 border-b border-outline-variant">
                        <tr>
                            <th class="px-4 py-3 text-sm font-bold text-primary">Rute</th>
                            <th class="px-4 py-3 text-sm font-bold text-primary">Driver</th>
                            <th class="px-4 py-3 text-sm font-bold text-primary">Status</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-outline-variant">
                        @foreach($active_trips as $trip)
                        <tr class="hover:bg-gray-50">
                            <td class="px-4 py-4">
                                <p class="font-bold text-sm text-primary">{{ optional($trip->schedule)->origin ?? '-' }} → {{ optional($trip->schedule)->destination ?? '-' }}</p>
                                <p class="text-xs text-on-surface-variant">{{ optional(optional($trip->schedule)->vehicle)->name ?? '-' }}</p>
                            </td>
                            <td class="px-4 py-4 text-sm text-on-surface-variant">{{ optional(optional($trip->schedule)->driver)->name ?? '-' }}</td>
                            <td class="px-4 py-4">
                                <span class="text-xs px-2 py-1 bg-green-100 text-green-800 rounded-full font-bold uppercase">{{ $trip->status }}</span>
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </section>
    </div>

    <!-- Recent Bookings -->
    <div class="xl:col-span-1">
        <section class="glass-card rounded-2xl p-6 h-full border border-secondary/20">
            <h2 class="text-xl font-bold text-primary mb-6">Booking Terbaru</h2>
            <div class="space-y-6">
                @foreach($recent_bookings as $booking)
                        <div class="flex gap-4">
                    <div class="w-8 h-8 rounded-full bg-gray-200 flex items-center justify-center">
                        <span class="material-symbols-outlined text-sm">person</span>
                    </div>
                        <div class="flex-1">
                        <p class="font-bold text-primary text-sm">{{ optional($booking->user)->name ?? '-' }}</p>
                        <p class="text-xs text-on-surface-variant">{{ optional($booking->schedule)->origin ?? '-' }} → {{ optional($booking->schedule)->destination ?? '-' }}</p>
                        <div class="mt-1 text-[10px] text-secondary font-bold uppercase">Berhasil</div>
                    </div>
                </div>
                @endforeach
            </div>
        </section>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        const ctx = document.getElementById('bookingChart').getContext('2d');
        new Chart(ctx, {
            type: 'line',
            data: {
                labels: {!! json_encode($chart_data['labels']) !!},
                datasets: [{
                    label: 'Jumlah Booking',
                    data: {!! json_encode($chart_data['values']) !!},
                    borderColor: '#18281e',
                    backgroundColor: 'rgba(24, 40, 30, 0.1)',
                    fill: true,
                    tension: 0.4
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: false }
                },
                scales: {
                    y: { beginAtZero: true, ticks: { stepSize: 1 } }
                }
            }
        });
    });
</script>
@endsection
