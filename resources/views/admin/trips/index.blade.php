@extends('admin.layout')

@section('title', 'Monitoring Perjalanan')

@section('content')
<!-- Include Leaflet Assets -->
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>

<div class="flex flex-col gap-6">
    <div>
        <h1 class="text-2xl font-bold text-primary">Monitoring Perjalanan</h1>
        <p class="text-sm text-on-surface-variant">Pantau posisi armada bus aktif dan status operasional supir di lapangan.</p>
    </div>

    <!-- Active Trips Map -->
    <div class="bg-white p-4 rounded-xl shadow-sm border border-gray-100">
        <h2 class="font-bold text-base mb-3 text-primary flex items-center gap-2">
            <span class="material-symbols-outlined text-secondary">map</span>
            Peta Pelacakan Armada Aktif (Real-Time)
        </h2>
        <div id="admin-map" style="width: 100%; height: 400px; border-radius: 8px; z-index: 1;"></div>
    </div>

    <!-- Trips History Table -->
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
        <div class="p-4 border-b border-gray-100 flex flex-col md:flex-row justify-between items-center gap-3">
            <h2 class="font-bold text-base text-primary">Daftar Operasional Perjalanan</h2>
            <form method="GET" action="{{ route('admin.trips') }}" class="w-full md:w-48">
                <select name="status" onchange="this.form.submit()" class="w-full px-3 py-1.5 border border-gray-300 rounded-lg text-sm">
                    <option value="">Semua Status</option>
                    <option value="scheduled" {{ request('status') === 'scheduled' ? 'selected' : '' }}>Scheduled</option>
                    <option value="on-going" {{ request('status') === 'on-going' ? 'selected' : '' }}>On-going</option>
                    <option value="completed" {{ request('status') === 'completed' ? 'selected' : '' }}>Completed</option>
                </select>
            </form>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse">
                <thead>
                    <tr class="bg-gray-50 border-b border-gray-100 text-sm font-semibold text-on-surface-variant">
                        <th class="px-6 py-4">ID Perjalanan</th>
                        <th class="px-6 py-4">Pengemudi & Unit</th>
                        <th class="px-6 py-4">Rute</th>
                        <th class="px-6 py-4">Keberangkatan</th>
                        <th class="px-6 py-4">Lokasi Terkini</th>
                        <th class="px-6 py-4">Status</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 text-sm">
                    @forelse($trips as $trip)
                        @php
                            $latestLoc = $trip->locations->last();
                        @endphp
                        <tr>
                            <td class="px-6 py-4 font-semibold">#TRP{{ $trip->id }}</td>
                            <td class="px-6 py-4">
                                <div class="font-medium text-gray-900">{{ $trip->schedule?->driver?->name }}</div>
                                <div class="text-xs text-gray-500">{{ $trip->schedule?->vehicle?->name }} ({{ $trip->schedule?->vehicle?->license_plate }})</div>
                            </td>
                            <td class="px-6 py-4">
                                <div class="font-medium text-gray-900">{{ $trip->schedule?->origin }} → {{ $trip->schedule?->destination }}</div>
                            </td>
                            <td class="px-6 py-4">
                                <div class="text-gray-900">{{ \Carbon\Carbon::parse($trip->schedule?->departure_time)->format('H:mm') }}</div>
                                <div class="text-xs text-gray-500">{{ \Carbon\Carbon::parse($trip->schedule?->departure_time)->format('d M Y') }}</div>
                            </td>
                            <td class="px-6 py-4 font-mono text-xs">
                                @if($latestLoc)
                                    <span class="text-secondary">{{ $latestLoc->latitude }}, {{ $latestLoc->longitude }}</span>
                                    <div class="text-[10px] text-gray-400">Update: {{ $latestLoc->created_at->format('H:mm:s') }}</div>
                                @else
                                    <span class="text-gray-400">Belum ada sinyal GPS</span>
                                @endif
                            </td>
                            <td class="px-6 py-4">
                                @if($trip->status === 'scheduled')
                                    <span class="px-2.5 py-1 text-xs font-semibold rounded-full bg-blue-100 text-blue-800">Scheduled</span>
                                @elseif($trip->status === 'on-going')
                                    <span class="px-2.5 py-1 text-xs font-semibold rounded-full bg-green-100 text-green-800 animate-pulse">On-going / Active</span>
                                @elseif($trip->status === 'completed')
                                    <span class="px-2.5 py-1 text-xs font-semibold rounded-full bg-gray-100 text-gray-800">Completed</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-6 py-12 text-center text-gray-500">
                                <span class="material-symbols-outlined text-4xl block mb-2">route</span>
                                Tidak ada data perjalanan ditemukan.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
    document.addEventListener("DOMContentLoaded", function() {
        // Initialize Map centered on West Java (between Jakarta and Bandung)
        const map = L.map('admin-map').setView([-6.6, 107.2], 9);

        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            attribution: '© OpenStreetMap contributors'
        }).addTo(map);

        // Fetch active trips locations generated from backend php
        const activeTrips = [
            @foreach($trips->where('status', 'on-going') as $t)
                @php
                    $lLoc = $t->locations->last();
                @endphp
                @if($lLoc)
                    {
                        id: {{ $t->id }},
                        origin: '{{ $t->schedule?->origin }}',
                        destination: '{{ $t->schedule?->destination }}',
                        driver: '{{ $t->schedule?->driver?->name }}',
                        vehicle: '{{ $t->schedule?->vehicle?->license_plate }}',
                        lat: {{ $lLoc->latitude }},
                        lng: {{ $lLoc->longitude }}
                    },
                @endif
            @endforeach
        ];

        const markers = [];

        if (activeTrips.length === 0) {
            // Add a default placeholder marker if map is empty
            const placeholderMarker = L.marker([-6.9452, 107.5937])
                .addTo(map)
                .bindPopup("<b>Depot Pusat Bandung</b><br>Tidak ada armada bus aktif saat ini.")
                .openPopup();
        } else {
            activeTrips.forEach(trip => {
                const busIcon = L.divIcon({
                    className: 'custom-bus-icon',
                    html: `<div style="background-color:#18281e; color:white; padding:6px; border-radius:50%; border:2px solid white; box-shadow:0 0 8px rgba(0,0,0,0.4); text-align:center;">
                             <span class="material-symbols-outlined" style="font-size:16px; display:block;">directions_bus</span>
                           </div>`,
                    iconSize: [28, 28],
                    iconAnchor: [14, 14]
                });

                const marker = L.marker([trip.lat, trip.lng], { icon: busIcon })
                    .addTo(map)
                    .bindPopup(`
                        <div class="text-xs p-1">
                            <b class="text-sm">Armada: ${trip.vehicle}</b><br>
                            <b>Rute:</b> ${trip.origin} → ${trip.destination}<br>
                            <b>Driver:</b> ${trip.driver}<br>
                            <b>Posisi:</b> ${trip.lat}, ${trip.lng}
                        </div>
                    `);
                
                markers.push(marker);
            });

            // Adjust bounds if multiple active trips
            if (markers.length > 0) {
                const group = new L.featureGroup(markers);
                map.fitBounds(group.getBounds().pad(0.1));
            }
        }
    });
</script>
@endsection
