@extends('admin.layout')

@section('title', 'Monitoring Perjalanan')

@section('content')
<!-- Include Mapbox GL JS Assets -->
<link href="https://api.mapbox.com/mapbox-gl-js/v3.4.0/mapbox-gl.css" rel="stylesheet" />
<script src="https://api.mapbox.com/mapbox-gl-js/v3.4.0/mapbox-gl.js"></script>

<div class="flex flex-col gap-6">
    <div>
        <h1 class="text-2xl font-bold text-primary">Monitoring Perjalanan</h1>
        <p class="text-sm text-on-surface-variant">Pantau posisi armada bus aktif, rute historis, dan status operasional supir di lapangan.</p>
    </div>

    <!-- Active Trips Map & Passenger Details (2-column layout) -->
    <div class="flex flex-col lg:flex-row gap-6">
        <!-- Peta Kiri -->
        <div class="flex-grow bg-white p-4 rounded-xl shadow-sm border border-gray-100">
            <h2 class="font-bold text-base mb-3 text-primary flex items-center gap-2">
                <span class="material-symbols-outlined text-secondary">map</span>
                Peta Pelacakan Armada Aktif (Real-Time & Historis)
            </h2>
            <div id="admin-map" style="width: 100%; height: 500px; border-radius: 8px; z-index: 1;"></div>
        </div>

        <!-- Passenger Details Kanan -->
        <div class="w-full lg:w-[400px] bg-white p-4 rounded-xl shadow-sm border border-gray-100 hidden flex-col" id="passenger-panel">
            <h2 class="font-bold text-base mb-3 text-primary flex items-center gap-2">
                <span class="material-symbols-outlined text-secondary">groups</span>
                Detail Penumpang
            </h2>
            <div class="flex flex-col gap-2 mb-4 bg-gray-50 p-3 rounded-lg border border-gray-100">
                <div class="text-xs font-semibold text-gray-500">TRIP ID <span id="panel-trip-id" class="text-gray-900 ml-2"></span></div>
                <div class="text-sm font-bold text-gray-800" id="panel-trip-route"></div>
                <div class="text-xs text-gray-600">Sopir: <span id="panel-trip-driver" class="font-medium text-gray-900"></span></div>
                <div class="text-xs text-gray-600">Unit: <span id="panel-trip-vehicle" class="font-medium text-gray-900"></span></div>
                <div class="mt-1"><span id="panel-trip-status"></span></div>
            </div>

            <div class="flex-grow overflow-y-auto">
                <h3 class="text-xs font-bold text-gray-500 mb-2 uppercase tracking-wider">Daftar Penumpang</h3>
                <ul id="passenger-list" class="flex flex-col gap-2">
                    <!-- Dinamis terisi dari JS -->
                </ul>
            </div>
        </div>
    </div>

    <!-- Trips History Table -->
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden mt-6">
        <div class="p-4 border-b border-gray-100 flex flex-col md:flex-row justify-between items-center gap-3">
            <h2 class="font-bold text-base text-primary">Daftar Operasional Perjalanan</h2>
            <form method="GET" action="{{ route('admin.trips') }}" class="w-full md:w-48">
                <select name="status" onchange="this.form.submit()" class="w-full px-3 py-1.5 border border-gray-300 rounded-lg text-sm">
                    <option value="">Semua Status</option>
                    <option value="scheduled" {{ request('status') === 'scheduled' ? 'selected' : '' }}>Scheduled</option>
                    <option value="boarding" {{ request('status') === 'boarding' ? 'selected' : '' }}>Boarding</option>
                    <option value="on-going" {{ request('status') === 'on-going' ? 'selected' : '' }}>On-going</option>
                    <option value="arrived" {{ request('status') === 'arrived' ? 'selected' : '' }}>Arrived</option>
                    <option value="delayed" {{ request('status') === 'delayed' ? 'selected' : '' }}>Delayed</option>
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
                        <tr class="hover:bg-gray-50 cursor-pointer" onclick="focusTripOnMap({{ $trip->id }})">
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
                                @elseif(in_array($trip->status, ['on-going', 'boarding', 'delayed', 'arrived']))
                                    <span class="px-2.5 py-1 text-xs font-semibold rounded-full bg-green-100 text-green-800 animate-pulse">{{ ucfirst($trip->status) }}</span>
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
    let map;
    const tripDataMap = {};
    const tripMarkers = {};
    const tripOriginMarkers = {};
    const tripDestMarkers = {};

    document.addEventListener("DOMContentLoaded", function() {
        mapboxgl.accessToken = {!! json_encode(env('MAPBOX_ACCESS_TOKEN')) !!};

        // Initialize Mapbox map
        map = new mapboxgl.Map({
            container: 'admin-map',
            style: 'mapbox://styles/mapbox/streets-v12',
            center: [107.2, -6.6], // West Java center [lng, lat]
            zoom: 8.5
        });

        map.addControl(new mapboxgl.NavigationControl());

        // Fetch active trips locations generated from backend php
        const activeTrips = [
            @foreach($trips->whereIn('status', ['boarding', 'on-going', 'delayed', 'arrived']) as $t)
                {
                    id: {{ $t->id }},
                    origin: '{{ addslashes($t->schedule?->origin) }}',
                    destination: '{{ addslashes($t->schedule?->destination) }}',
                    driver: '{{ addslashes($t->schedule?->driver?->name) }}',
                    vehicle: '{{ addslashes($t->schedule?->vehicle?->license_plate) }}',
                    status: '{{ $t->status }}',
                    pickup_name: '{{ addslashes($t->schedule?->pickup_name) }}',
                    pickup_lat: {{ $t->schedule?->pickup_lat ?? -6.2088 }},
                    pickup_lng: {{ $t->schedule?->pickup_lng ?? 106.8456 }},
                    drop_off_name: '{{ addslashes($t->schedule?->drop_off_name) }}',
                    drop_off_lat: {{ $t->schedule?->drop_off_lat ?? -6.9175 }},
                    drop_off_lng: {{ $t->schedule?->drop_off_lng ?? 107.6191 }},
                    locations: [
                        @foreach($t->locations as $loc)
                            [{{ $loc->longitude }}, {{ $loc->latitude }}],
                        @endforeach
                    ],
                    passengers: [
                        @foreach($t->schedule->bookings as $booking)
                            {
                                name: '{{ addslashes($booking->user?->name) }}',
                                seat: '{{ $booking->seat?->seat_number ?? $booking->seat_id }}',
                                phone: '{{ addslashes($booking->user?->phone) }}'
                            },
                        @endforeach
                    ]
                },
            @endforeach
        ];

        map.on('load', () => {
            if (activeTrips.length === 0) {
                // Add a default placeholder marker if map is empty
                const elDepot = document.createElement('div');
                elDepot.style.backgroundColor = '#18281e';
                elDepot.style.color = 'white';
                elDepot.style.padding = '6px';
                elDepot.style.borderRadius = '50%';
                elDepot.style.border = '2px solid white';
                elDepot.style.boxShadow = '0 0 8px rgba(0,0,0,0.4)';
                elDepot.style.textAlign = 'center';
                elDepot.innerHTML = '<span class="material-symbols-outlined" style="font-size:16px; display:block;">directions_bus</span>';

                const popup = new mapboxgl.Popup({ offset: 25 })
                    .setHTML('<b>Depot Pusat Bandung</b><br>Tidak ada armada bus aktif saat ini.');

                new mapboxgl.Marker({ element: elDepot })
                    .setLngLat([107.5937, -6.9452])
                    .setPopup(popup)
                    .addTo(map);
            } else {
                activeTrips.forEach(trip => {
                    tripDataMap[trip.id] = trip;
                    plotTrip(trip);
                });

                // Adjust bounds to fit active trips
                fitMapBounds(activeTrips);
            }
        });

        // Real-time location polling every 5 seconds
        setInterval(function() {
            fetch("{{ route('admin.trips.locations') }}")
                .then(res => res.json())
                .then(data => {
                    data.forEach(trip => {
                        // Find matching active trip model
                        const existingTrip = activeTrips.find(t => t.id === trip.id);
                        if (existingTrip) {
                            existingTrip.status = trip.status;
                            existingTrip.locations = trip.locations.map(loc => [loc[1], loc[0]]); // Swap [lat, lng] to [lng, lat]
                            tripDataMap[trip.id] = existingTrip;
                            
                            updateTripMarkerAndPath(existingTrip);
                        } else {
                            // If a new trip just started, load it
                            const newTrip = {
                                id: trip.id,
                                origin: trip.origin,
                                destination: trip.destination,
                                driver: trip.driver,
                                vehicle: trip.vehicle,
                                status: trip.status,
                                pickup_name: trip.pickup_name || `Pool ${trip.origin}`,
                                pickup_lat: trip.pickup_lat || -6.2088,
                                pickup_lng: trip.pickup_lng || 106.8456,
                                drop_off_name: trip.drop_off_name || `Pool ${trip.destination}`,
                                drop_off_lat: trip.drop_off_lat || -6.9175,
                                drop_off_lng: trip.drop_off_lng || 107.6191,
                                locations: trip.locations.map(loc => [loc[1], loc[0]]), // Swap [lat, lng] to [lng, lat]
                                passengers: trip.passengers || []
                            };
                            activeTrips.push(newTrip);
                            tripDataMap[newTrip.id] = newTrip;
                            plotTrip(newTrip);
                        }
                    });

                    // Refresh active passenger panel if it is currently open
                    const activePanelIdElement = document.getElementById('panel-trip-id');
                    if (activePanelIdElement && activePanelIdElement.textContent) {
                        const activePanelId = activePanelIdElement.textContent;
                        if (activePanelId && !document.getElementById('passenger-panel').classList.contains('hidden')) {
                            const tripIdNum = parseInt(activePanelId.replace('#TRP', ''), 10);
                            if (tripIdNum && tripDataMap[tripIdNum]) {
                                showPassengerPanel(tripIdNum);
                            }
                        }
                    }
                })
                .catch(err => console.error("Error polling locations:", err));
        }, 5000);
    });

    function drawRouteLine(tripId, coordinates, color = '#0d9488') {
        const sourceId = `route-source-${tripId}`;
        const layerId = `route-layer-${tripId}`;
        
        if (map.getSource(sourceId)) {
            map.getSource(sourceId).setData({
                type: 'Feature',
                properties: {},
                geometry: {
                    type: 'LineString',
                    coordinates: coordinates
                }
            });
        } else {
            map.addSource(sourceId, {
                type: 'geojson',
                data: {
                    type: 'Feature',
                    properties: {},
                    geometry: {
                        type: 'LineString',
                        coordinates: coordinates
                    }
                }
            });
            
            map.addLayer({
                id: layerId,
                type: 'line',
                source: sourceId,
                layout: {
                    'line-join': 'round',
                    'line-cap': 'round'
                },
                paint: {
                    'line-color': color,
                    'line-width': 4,
                    'line-opacity': 0.8
                }
            });
        }
    }

    function drawPlannedRouteLine(tripId, coordinates) {
        const sourceId = `planned-source-${tripId}`;
        const layerId = `planned-layer-${tripId}`;
        
        const routeColors = [
            '#1a73e8', // Google Maps Blue
            '#10b981', // Emerald Green
            '#8b5cf6', // Violet/Purple
            '#f97316', // Orange
            '#ec4899', // Pink
            '#06b6d4'  // Cyan
        ];
        const color = routeColors[tripId % routeColors.length];
        
        if (map.getSource(sourceId)) {
            map.getSource(sourceId).setData({
                type: 'Feature',
                properties: {},
                geometry: {
                    type: 'LineString',
                    coordinates: coordinates
                }
            });
        } else {
            map.addSource(sourceId, {
                type: 'geojson',
                data: {
                    type: 'Feature',
                    properties: {},
                    geometry: {
                        type: 'LineString',
                        coordinates: coordinates
                    }
                }
            });
            
            map.addLayer({
                id: layerId,
                type: 'line',
                source: sourceId,
                layout: {
                    'line-join': 'round',
                    'line-cap': 'round'
                },
                paint: {
                    'line-color': color,
                    'line-width': 5,
                    'line-opacity': 0.8
                }
            });
        }
    }

    const adminRouteStops = {
        'depok-bandung': [
            { name: 'Pool Karawang', coords: [107.2913, -6.3073] },
            { name: 'Pool Purwakarta', coords: [107.4431, -6.5571] }
        ],
        'bogor-bandung': [
            { name: 'Pool Cianjur', coords: [107.1396, -6.8242] },
            { name: 'Pool Padalarang', coords: [107.4721, -6.8406] }
        ],
        'jakarta-bandung': [
            { name: 'Pool Bekasi', coords: [106.9756, -6.2383] },
            { name: 'Pool Karawang', coords: [107.2913, -6.3073] }
        ]
    };

    const adminCoordinatesMap = {
        'jakarta': [106.8824, -6.3090],
        'terminal kampung rambutan': [106.8824, -6.3090],
        'bandung': [107.5937, -6.9452],
        'terminal leuwi panjang': [107.5937, -6.9452],
        'karawang': [107.2913, -6.3073],
        'sumedang': [107.9234, -6.8524],
        'subang': [107.7587, -6.5715],
        'purwakarta': [107.4431, -6.5571],
        'cikampek': [107.4589, -6.4025],
        'cirebon': [108.5523, -6.7320],
        'bogor': [106.7932, -6.5971],
        'depok': [106.8227, -6.4025],
        'bekasi': [106.9756, -6.2383],
        'tangerang': [106.6403, -6.1702]
    };

    function plotTrip(trip) {
        // Resolve coordinates with fallback
        let originCoords = [trip.pickup_lng, trip.pickup_lat];
        if (!trip.pickup_lng || !trip.pickup_lat || (trip.pickup_lng === 106.8456 && trip.pickup_lat === -6.2088)) {
            const key = trip.origin.toLowerCase().trim();
            const mapped = adminCoordinatesMap[key];
            if (mapped) {
                originCoords = mapped;
            }
        }

        let destCoords = [trip.drop_off_lng, trip.drop_off_lat];
        if (!trip.drop_off_lng || !trip.drop_off_lat || (trip.drop_off_lng === 107.6191 && trip.drop_off_lat === -6.9175)) {
            const key = trip.destination.toLowerCase().trim();
            const mapped = adminCoordinatesMap[key];
            if (mapped) {
                destCoords = mapped;
            }
        }

        // Get intermediate stops
        const routeKey = `${trip.origin.toLowerCase().trim()}-${trip.destination.toLowerCase().trim()}`;
        const stops = adminRouteStops[routeKey] || [];

        // Plot intermediate stops on the map
        stops.forEach((stop, sIdx) => {
            const stopMarkerKey = `stop-${trip.id}-${sIdx}`;
            const elStop = document.createElement('div');
            elStop.className = 'route-marker-icon stop';
            elStop.innerHTML = `<div style="background-color:#f59e0b; color:white; padding:4px 8px; border-radius:10px; font-weight:bold; font-size:9px; border:1px solid white; white-space:nowrap; box-shadow:0 2px 4px rgba(0,0,0,0.2);">
                                    Singgah: ${stop.name}
                                 </div>`;
            new mapboxgl.Marker({ element: elStop })
                .setLngLat(stop.coords)
                .addTo(map);
        });

        // Construct multi-waypoint URL using Mapbox Directions API or OSRM
        let waypointStr = `${originCoords[0]},${originCoords[1]}`;
        stops.forEach(stop => {
            waypointStr += `;${stop.coords[0]},${stop.coords[1]}`;
        });
        waypointStr += `;${destCoords[0]},${destCoords[1]}`;

        // Draw planned route using Mapbox Directions API
        const directionsUrl = `https://api.mapbox.com/directions/v5/mapbox/driving/${waypointStr}?geometries=geojson&access_token=${mapboxgl.accessToken}`;
        fetch(directionsUrl)
            .then(res => res.json())
            .then(data => {
                if (data.routes && data.routes.length > 0) {
                    const coords = data.routes[0].geometry.coordinates;
                    drawPlannedRouteLine(trip.id, coords);
                }
            })
            .catch(err => {
                console.error("Mapbox Directions error, falling back to OSRM", err);
                // Fallback to OSRM
                fetch(`https://router.project-osrm.org/route/v1/driving/${waypointStr}?overview=full&geometries=geojson`)
                    .then(res => res.json())
                    .then(data => {
                        if (data.routes && data.routes.length > 0) {
                            const coords = data.routes[0].geometry.coordinates;
                            drawPlannedRouteLine(trip.id, coords);
                        }
                    });
            });

        // Draw actual path traveled
        if (trip.locations.length > 1) {
            drawRouteLine(trip.id, trip.locations);
        }

        // Add Origin Marker
        if (!tripOriginMarkers[trip.id]) {
            const elOrigin = document.createElement('div');
            elOrigin.className = 'route-marker-icon origin';
            elOrigin.innerHTML = `<div style="background-color:#0d9488; color:white; padding:4px 8px; border-radius:10px; font-weight:bold; font-size:10px; border:1px solid white; white-space:nowrap; box-shadow:0 2px 4px rgba(0,0,0,0.2);">
                                     Mulai (#${trip.id}): ${trip.pickup_name || trip.origin}
                                   </div>`;
            tripOriginMarkers[trip.id] = new mapboxgl.Marker({ element: elOrigin })
                .setLngLat(originCoords)
                .addTo(map);
        }

        // Add Destination Marker
        if (!tripDestMarkers[trip.id]) {
            const elDest = document.createElement('div');
            elDest.className = 'route-marker-icon destination';
            elDest.innerHTML = `<div style="background-color:#b91c1c; color:white; padding:4px 8px; border-radius:10px; font-weight:bold; font-size:10px; border:1px solid white; white-space:nowrap; box-shadow:0 2px 4px rgba(0,0,0,0.2);">
                                     Tujuan (#${trip.id}): ${trip.drop_off_name || trip.destination}
                                   </div>`;
            tripDestMarkers[trip.id] = new mapboxgl.Marker({ element: elDest })
                .setLngLat(destCoords)
                .addTo(map);
        }

        // Add Active Bus Location Marker
        if (trip.locations.length > 0) {
            const latestLoc = trip.locations[trip.locations.length - 1];

            const elBus = document.createElement('div');
            elBus.className = 'custom-bus-icon';
            elBus.innerHTML = `<div style="background-color:#18281e; color:white; padding:6px; border-radius:50%; border:2px solid white; box-shadow:0 0 8px rgba(0,0,0,0.4); text-align:center; cursor:pointer;">
                                 <span class="material-symbols-outlined" style="font-size:16px; display:block;">directions_bus</span>
                                </div>`;

            const popup = new mapboxgl.Popup({ offset: 25 }).setHTML(getBusPopupContent(trip));

            const marker = new mapboxgl.Marker({ element: elBus })
                .setLngLat(latestLoc)
                .setPopup(popup)
                .addTo(map);

            elBus.addEventListener('click', () => {
                showPassengerPanel(trip.id);
            });

            tripMarkers[trip.id] = marker;
        }
    }

    function updateTripMarkerAndPath(trip) {
        if (trip.locations.length > 0) {
            const latestLoc = trip.locations[trip.locations.length - 1];
            
            // Move bus marker
            if (tripMarkers[trip.id]) {
                tripMarkers[trip.id].setLngLat(latestLoc);
                tripMarkers[trip.id].getPopup().setHTML(getBusPopupContent(trip));
            } else {
                plotTrip(trip);
            }

            // Update polyline track path
            if (trip.locations.length > 1) {
                drawRouteLine(trip.id, trip.locations);
            }
        }
    }

    function getBusPopupContent(trip) {
        return `
            <div class="text-xs p-1">
                <b class="text-sm">Armada: ${trip.vehicle}</b><br>
                <b>Rute:</b> ${trip.origin} → ${trip.destination}<br>
                <b>Driver:</b> ${trip.driver}<br>
                <b>Status:</b> ${trip.status.toUpperCase()}<br>
                <button onclick="showPassengerPanel(${trip.id})" class="mt-2 w-full px-2 py-1 bg-primary text-white rounded text-xs">Lihat Penumpang</button>
            </div>
        `;
    }

    function fitMapBounds(trips) {
        if (trips.length === 0) return;
        const bounds = new mapboxgl.LngLatBounds();
        trips.forEach(trip => {
            if (trip.locations.length > 0) {
                trip.locations.forEach(loc => bounds.extend(loc));
            }
            bounds.extend([trip.pickup_lng, trip.pickup_lat]);
            bounds.extend([trip.drop_off_lng, trip.drop_off_lat]);
        });
        map.fitBounds(bounds, { padding: 50, maxZoom: 14 });
    }

    function showPassengerPanel(tripId) {
        const trip = tripDataMap[tripId];
        if (!trip) return;

        document.getElementById('passenger-panel').classList.remove('hidden');
        document.getElementById('passenger-panel').classList.add('flex');

        document.getElementById('panel-trip-id').textContent = `#TRP${trip.id}`;
        document.getElementById('panel-trip-route').textContent = `${trip.origin} → ${trip.destination}`;
        document.getElementById('panel-trip-driver').textContent = trip.driver;
        document.getElementById('panel-trip-vehicle').textContent = trip.vehicle;
        
        let statusBadge = '';
        if(trip.status === 'on-going') statusBadge = '<span class="px-2 py-0.5 bg-green-100 text-green-800 text-[10px] font-bold rounded">ON-GOING</span>';
        else if(trip.status === 'delayed') statusBadge = '<span class="px-2 py-0.5 bg-red-100 text-red-800 text-[10px] font-bold rounded">DELAYED</span>';
        else if(trip.status === 'boarding') statusBadge = '<span class="px-2 py-0.5 bg-yellow-100 text-yellow-800 text-[10px] font-bold rounded">BOARDING</span>';
        else if(trip.status === 'arrived') statusBadge = '<span class="px-2 py-0.5 bg-blue-100 text-blue-800 text-[10px] font-bold rounded">ARRIVED</span>';
        
        document.getElementById('panel-trip-status').innerHTML = statusBadge;

        const list = document.getElementById('passenger-list');
        list.innerHTML = '';

        if (trip.passengers.length === 0) {
            list.innerHTML = '<li class="text-xs text-gray-500 text-center py-4">Tidak ada data penumpang</li>';
            return;
        }

        trip.passengers.forEach(p => {
            const li = document.createElement('li');
            li.className = "flex justify-between items-center bg-white border border-gray-100 p-2 rounded";
            li.innerHTML = `
                <div class="flex flex-col">
                    <span class="text-sm font-semibold text-gray-800">${p.name}</span>
                    <span class="text-[10px] text-gray-500">${p.phone}</span>
                </div>
                <div class="px-2 py-1 bg-primary/10 text-primary text-xs font-bold rounded">
                    Seat ${p.seat}
                </div>
            `;
            list.appendChild(li);
        });
    }

    function focusTripOnMap(tripId) {
        const trip = tripDataMap[tripId];
        if (trip && trip.locations.length > 0) {
            const latestLoc = trip.locations[trip.locations.length - 1];
            map.flyTo({
                center: latestLoc,
                zoom: 12
            });
            if (tripMarkers[tripId]) {
                tripMarkers[tripId].togglePopup();
            }
            showPassengerPanel(tripId);
        }
    }
</script>
@endsection

