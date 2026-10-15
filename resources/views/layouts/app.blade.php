<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $title ?? 'One-Click Exam Datesheet Generator' }}</title>
    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        brand: {
                            50: '#eff6ff',
                            100: '#dbeafe',
                            500: '#3b82f6',
                            600: '#2563eb',
                            700: '#1d4ed8',
                            800: '#1e40af',
                            900: '#1e3a8a',
                        }
                    }
                }
            }
        }
    </script>
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    @livewireStyles
</head>
<body class="bg-slate-50 text-slate-800 min-h-screen flex flex-col font-sans antialiased">

    <!-- Top Navigation Bar -->
    <header class="bg-brand-900 text-white shadow-md sticky top-0 z-50">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex items-center justify-between h-16">
                <!-- Brand / Logo -->
                <div class="flex items-center space-x-3">
                    <div class="w-10 h-10 rounded-lg bg-blue-600 flex items-center justify-center text-white font-bold text-xl shadow">
                        <i class="fa-solid fa-calendar-check"></i>
                    </div>
                    <div>
                        <a href="{{ url('/') }}" class="text-lg font-bold tracking-tight text-white hover:text-blue-200">
                            Exam Datesheet Generator
                        </a>
                        <p class="text-xs text-blue-200">Zero-Hardcoding Constraint Optimization</p>
                    </div>
                </div>

                <!-- Nav Links -->
                <nav class="hidden md:flex items-center space-x-1 text-sm font-medium">
                    <a href="{{ url('/') }}" class="px-3 py-2 rounded-md {{ request()->is('/') ? 'bg-blue-800 text-white' : 'text-blue-100 hover:bg-blue-800/60' }}">
                        <i class="fa-solid fa-table-cells mr-1.5"></i> Timetable Grid
                    </a>
                    <a href="{{ url('/settings') }}" class="px-3 py-2 rounded-md {{ request()->is('settings') ? 'bg-blue-800 text-white' : 'text-blue-100 hover:bg-blue-800/60' }}">
                        <i class="fa-solid fa-sliders mr-1.5"></i> Settings
                    </a>
                    <a href="{{ url('/what-if') }}" class="px-3 py-2 rounded-md {{ request()->is('what-if') ? 'bg-blue-800 text-white' : 'text-blue-100 hover:bg-blue-800/60' }}">
                        <i class="fa-solid fa-flask mr-1.5"></i> What-If Mode
                    </a>
                    <a href="{{ url('/versions') }}" class="px-3 py-2 rounded-md {{ request()->is('versions') ? 'bg-blue-800 text-white' : 'text-blue-100 hover:bg-blue-800/60' }}">
                        <i class="fa-solid fa-clock-rotate-left mr-1.5"></i> Versions & Audit
                    </a>
                    <a href="{{ url('/import-data') }}" class="px-3 py-2 rounded-md {{ request()->is('import-data') ? 'bg-blue-800 text-white' : 'text-blue-100 hover:bg-blue-800/60' }}">
                        <i class="fa-solid fa-file-excel mr-1.5"></i> Data & Templates
                    </a>
                </nav>

                <!-- Status & Actions -->
                <div class="flex items-center space-x-3">
                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold bg-emerald-100 text-emerald-800">
                        <span class="w-1.5 h-1.5 mr-1.5 rounded-full bg-emerald-500"></span> Admin Mode
                    </span>
                    <a href="{{ url('/load-demo') }}" class="hidden sm:inline-flex items-center px-3 py-1.5 border border-blue-400/40 text-xs font-semibold rounded-md text-white bg-blue-800/70 hover:bg-blue-700 focus:outline-none shadow-sm transition">
                        <i class="fa-solid fa-database mr-1.5 text-blue-300"></i> Load Demo
                    </a>
                </div>
            </div>
        </div>
    </header>

    <!-- Main Content Area -->
    <main class="flex-grow max-w-7xl w-full mx-auto px-4 sm:px-6 lg:px-8 py-6">
        @if(session('success'))
            <div class="mb-4 p-4 rounded-lg bg-emerald-50 border border-emerald-200 text-emerald-800 flex items-center justify-between shadow-sm">
                <div class="flex items-center space-x-3">
                    <i class="fa-solid fa-circle-check text-emerald-500 text-lg"></i>
                    <span>{{ session('success') }}</span>
                </div>
            </div>
        @endif

        @if(session('error'))
            <div class="mb-4 p-4 rounded-lg bg-rose-50 border border-rose-200 text-rose-800 flex items-center justify-between shadow-sm">
                <div class="flex items-center space-x-3">
                    <i class="fa-solid fa-triangle-exclamation text-rose-500 text-lg"></i>
                    <span>{{ session('error') }}</span>
                </div>
            </div>
        @endif

        {{ $slot }}
    </main>

    <!-- Footer -->
    <footer class="bg-white border-t border-slate-200 text-slate-500 text-xs py-4">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 flex flex-col sm:flex-row justify-between items-center space-y-2 sm:space-y-0">
            <div>
                <strong>One-Click Exam Datesheet Generator</strong> • Production-Ready Constraint Optimization
            </div>
            <div>
                Laravel 12 • Livewire 4 • MySQL 8 • Zero Hardcoding
            </div>
        </div>
    </footer>

    @livewireScripts
</body>
</html>
