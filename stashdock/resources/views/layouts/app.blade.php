<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="referrer" content="no-referrer">

    <title>StashDock</title>

    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />

    <!-- Chart.js CDN -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

    <!-- Vite Assets (Tailwind CSS + Alpine.js) -->
    @vite(['resources/css/app.css', 'resources/js/app.js'])

    <!-- Prevent Alpine.js x-cloak flash -->
    <style>
        [x-cloak] {
            display: none !important;
        }
    </style>
</head>
<body class="font-sans antialiased bg-gray-50 text-gray-800">

    <!-- Fixed Top Navbar -->
    <nav class="fixed top-0 left-0 right-0 z-50 bg-white border-b border-gray-200 shadow-sm">
        <div class="max-w-screen-xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex items-center justify-between h-14">

                <!-- Left: Logo + Brand -->
                <div class="flex items-center gap-2 shrink-0">
                    <img src="/logo.png" alt="StashDock Logo" class="h-7 w-7 object-contain">
                    <span class="font-semibold text-gray-900 text-base tracking-tight">StashDock</span>
                </div>

                <!-- Center: Nav Links -->
                <div class="flex items-center gap-1">
                    <a href="{{ route('dashboard') }}"
                       class="px-3 py-1.5 rounded-md text-sm font-medium transition-colors
                              {{ request()->routeIs('dashboard') ? 'bg-gray-100 text-gray-900' : 'text-gray-600 hover:bg-gray-100 hover:text-gray-900' }}">
                        Dashboard
                    </a>
                    <a href="{{ route('projects.index') }}"
                       class="px-3 py-1.5 rounded-md text-sm font-medium transition-colors
                              {{ request()->routeIs('projects.*') ? 'bg-gray-100 text-gray-900' : 'text-gray-600 hover:bg-gray-100 hover:text-gray-900' }}">
                        Projects
                    </a>
                    <a href="{{ route('settings.show') }}"
                       class="px-3 py-1.5 rounded-md text-sm font-medium transition-colors
                              {{ request()->routeIs('settings.*') ? 'bg-gray-100 text-gray-900' : 'text-gray-600 hover:bg-gray-100 hover:text-gray-900' }}">
                        Settings
                    </a>
                </div>

                <!-- Right: User Name + Logout -->
                <div class="flex items-center gap-3 shrink-0">
                    <span class="text-sm text-gray-600">{{ Auth::user()->name }}</span>
                    <form method="POST" action="/logout">
                        @csrf
                        <button type="submit"
                                class="px-3 py-1.5 text-sm font-medium text-gray-600 rounded-md border border-gray-200 hover:bg-gray-100 hover:text-gray-900 transition-colors">
                            Logout
                        </button>
                    </form>
                </div>

            </div>
        </div>
    </nav>

    <!-- Page Content (offset for fixed navbar) -->
    <main class="pt-14 min-h-screen">
        {{ $slot }}
    </main>

</body>
</html>
