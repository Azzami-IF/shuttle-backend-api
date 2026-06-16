<!DOCTYPE html>
<html class="light" lang="id">
<head>
    <meta charset="utf-8"/>
    <meta content="width=device-width, initial-scale=1.0" name="viewport"/>
    <title>@yield('title') | KemanapunGo Admin</title>
    <script src="https://cdn.tailwindcss.com?plugins=forms,container-queries"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <link href="https://fonts.googleapis.com/css2?family=Hanken+Grotesk:wght@400;500;600;700&display=swap" rel="stylesheet"/>
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght@100..700&display=swap" rel="stylesheet"/>
    <style>
        body {
            font-family: 'Hanken Grotesk', sans-serif;
            background-color: #f8faf5;
        }
        .material-symbols-outlined {
            font-variation-settings: 'FILL' 0, 'wght' 400, 'GRAD' 0, 'opsz' 24;
        }
        .glass-card {
            background: rgba(255, 255, 255, 0.7);
            backdrop-filter: blur(12px);
            border: 1px solid rgba(225, 227, 223, 0.5);
        }
    </style>
    <script>
        tailwind.config = {
            darkMode: "class",
            theme: {
                extend: {
                    colors: {
                        "primary": "#18281e",
                        "secondary": "#536349",
                        "primary-container": "#2d3e33",
                        "secondary-container": "#d3e5c5",
                        "surface": "#f8faf5",
                        "outline": "#737873",
                        "outline-variant": "#c3c8c2",
                        "on-surface-variant": "#434844",
                    }
                }
            }
        }
    </script>
</head>
<body class="bg-[#f8faf5] text-[#191c1a]">
    <header class="bg-white shadow-sm flex justify-between items-center px-4 md:px-16 w-full h-16 fixed top-0 z-50">
        <div class="flex items-center gap-4">
            <button class="material-symbols-outlined text-on-surface-variant hover:bg-gray-100 p-2 rounded-full">menu</button>
            <a href="{{ route('admin.dashboard') }}" class="font-bold text-xl text-primary">KemanapunGo</a>
        </div>
        <div class="flex items-center gap-4">
            <select onchange="window.location.href='?lang='+this.value" class="text-sm border-none bg-transparent focus:ring-0 cursor-pointer text-on-surface-variant">
                <option value="id" {{ app()->getLocale() == 'id' ? 'selected' : '' }}>ID</option>
                <option value="en" {{ app()->getLocale() == 'en' ? 'selected' : '' }}>EN</option>
            </select>
            @auth
                <form method="POST" action="{{ route('admin.logout') }}">@csrf
                    <button type="submit" class="material-symbols-outlined text-primary p-2 hover:bg-gray-100 rounded-full">logout</button>
                </form>
            @else
                <a href="{{ route('admin.login') }}" class="text-primary p-2 hover:bg-gray-100 rounded">Masuk</a>
            @endauth
        </div>
    </header>

    <script>
        // Toggle side menu on mobile
        document.addEventListener('DOMContentLoaded', function () {
            const btn = document.querySelector('header button');
            const aside = document.querySelector('aside');
            if (!btn || !aside) return;
            btn.addEventListener('click', function () {
                aside.classList.toggle('open');
            });
        });
    </script>
    <style>
        aside.open { transform: translateX(0); }
        aside { transition: transform .18s ease; }
        @media (max-width: 1024px) {
            aside { transform: translateX(-110%); position: fixed; z-index:60; }
        }
        @media (min-width: 1025px) {
            aside { transform: none; }
        }
    </style>

    <aside class="h-full w-72 fixed left-0 top-0 z-40 bg-white shadow-xl flex flex-col py-6 px-2 pt-20">
        <nav class="flex flex-col gap-1">
            <a class="flex items-center gap-3 px-4 py-3 {{ request()->routeIs('admin.dashboard') ? 'bg-secondary-container text-secondary font-bold' : 'text-on-surface-variant' }} rounded-r-full" href="{{ route('admin.dashboard') }}">
                <span class="material-symbols-outlined">dashboard</span>
                <span class="text-sm">Dashboard Admin</span>
            </a>
            <a class="flex items-center gap-3 px-4 py-3 {{ request()->routeIs('admin.vehicles*') ? 'bg-secondary-container text-secondary font-bold' : 'text-on-surface-variant' }} rounded-r-full" href="{{ route('admin.vehicles') }}">
                <span class="material-symbols-outlined">directions_bus</span>
                <span class="text-sm">Manajemen Kendaraan</span>
            </a>
            <a class="flex items-center gap-3 px-4 py-3 {{ request()->routeIs('admin.route-templates*') || request()->routeIs('admin.schedules*') ? 'bg-secondary-container text-secondary font-bold' : 'text-on-surface-variant' }} rounded-r-full" href="{{ route('admin.route-templates.index') }}">
                <span class="material-symbols-outlined">event_note</span>
                <span class="text-sm">Manajemen Jadwal</span>
            </a>
            <a class="flex items-center gap-3 px-4 py-3 {{ request()->routeIs('admin.users*') ? 'bg-secondary-container text-secondary font-bold' : 'text-on-surface-variant' }} rounded-r-full" href="{{ route('admin.users') }}">
                <span class="material-symbols-outlined">group</span>
                <span class="text-sm">Manajemen Pengguna</span>
            </a>
            <a class="flex items-center gap-3 px-4 py-3 {{ request()->routeIs('admin.bookings*') ? 'bg-secondary-container text-secondary font-bold' : 'text-on-surface-variant' }} rounded-r-full" href="{{ route('admin.bookings') }}">
                <span class="material-symbols-outlined">confirmation_number</span>
                <span class="text-sm">Monitoring Booking</span>
            </a>
            <a class="flex items-center gap-3 px-4 py-3 {{ request()->routeIs('admin.trips*') ? 'bg-secondary-container text-secondary font-bold' : 'text-on-surface-variant' }} rounded-r-full" href="{{ route('admin.trips') }}">
                <span class="material-symbols-outlined">route</span>
                <span class="text-sm">Monitoring Perjalanan</span>
            </a>
        </nav>
    </aside>

    <main class="pt-24 pb-12 px-4 md:px-16 lg:ml-72">
        @if(session('success'))
            <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative mb-4">
                {{ session('success') }}
            </div>
        @endif
        @yield('content')
    </main>
</body>
</html>
