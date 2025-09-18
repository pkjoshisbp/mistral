<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ config('app.name', 'Laravel') }} - Affiliate Dashboard</title>

    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />

    <!-- Scripts -->
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
</head>
<body class="font-sans antialiased bg-gray-50">
    <div class="min-h-screen">
        <!-- Navigation -->
        <nav class="bg-white shadow-lg border-b">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="flex justify-between items-center h-16">
                    <!-- Logo -->
                    <div class="flex items-center">
                        <a href="{{ route('affiliate.dashboard') }}" class="flex items-center">
                            <span class="text-xl font-bold text-blue-600">{{ config('app.name') }}</span>
                            <span class="ml-2 text-sm bg-blue-100 text-blue-800 px-2 py-1 rounded">Affiliate</span>
                        </a>
                    </div>

                    <!-- Navigation Links -->
                    <div class="hidden md:block">
                        <div class="ml-10 flex items-baseline space-x-4">
                            <a href="{{ route('affiliate.dashboard') }}" 
                               class="px-3 py-2 rounded-md text-sm font-medium {{ request()->routeIs('affiliate.dashboard') ? 'bg-blue-100 text-blue-700' : 'text-gray-600 hover:text-gray-900' }}">
                                Dashboard
                            </a>
                            <a href="{{ route('affiliate.links') }}" 
                               class="px-3 py-2 rounded-md text-sm font-medium {{ request()->routeIs('affiliate.links') ? 'bg-blue-100 text-blue-700' : 'text-gray-600 hover:text-gray-900' }}">
                                My Links
                            </a>
                            <a href="{{ route('affiliate.commissions') }}" 
                               class="px-3 py-2 rounded-md text-sm font-medium {{ request()->routeIs('affiliate.commissions') ? 'bg-blue-100 text-blue-700' : 'text-gray-600 hover:text-gray-900' }}">
                                Commissions
                            </a>
                            <a href="{{ route('affiliate.reports') }}" 
                               class="px-3 py-2 rounded-md text-sm font-medium {{ request()->routeIs('affiliate.reports') ? 'bg-blue-100 text-blue-700' : 'text-gray-600 hover:text-gray-900' }}">
                                Reports
                            </a>
                            <a href="{{ route('affiliate.profile') }}" 
                               class="px-3 py-2 rounded-md text-sm font-medium {{ request()->routeIs('affiliate.profile') ? 'bg-blue-100 text-blue-700' : 'text-gray-600 hover:text-gray-900' }}">
                                Profile
                            </a>
                        </div>
                    </div>

                    <!-- User Dropdown -->
                    <div class="relative">
                        <div class="flex items-center space-x-4">
                            <span class="text-sm text-gray-600">
                                Welcome, {{ auth()->user()->name }}
                            </span>
                            <div class="relative">
                                <button type="button" class="flex text-sm rounded-full focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500" 
                                        onclick="document.getElementById('user-menu').classList.toggle('hidden')">
                                    <span class="sr-only">Open user menu</span>
                                    <div class="h-8 w-8 rounded-full bg-blue-600 flex items-center justify-center">
                                        <span class="text-sm font-medium text-white">
                                            {{ strtoupper(substr(auth()->user()->name, 0, 1)) }}
                                        </span>
                                    </div>
                                </button>
                                
                                <div id="user-menu" class="hidden absolute right-0 mt-2 w-48 py-1 bg-white rounded-md shadow-lg ring-1 ring-black ring-opacity-5">
                                    <a href="{{ route('profile.edit') }}" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">
                                        Profile Settings
                                    </a>
                                    <form method="POST" action="{{ route('logout') }}">
                                        @csrf
                                        <button type="submit" class="block w-full text-left px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">
                                            Sign out
                                        </button>
                                    </form>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Mobile menu button -->
                    <div class="md:hidden">
                        <button type="button" class="inline-flex items-center justify-center p-2 rounded-md text-gray-400 hover:text-gray-500 hover:bg-gray-100"
                                onclick="document.getElementById('mobile-menu').classList.toggle('hidden')">
                            <span class="sr-only">Open main menu</span>
                            <svg class="h-6 w-6" stroke="currentColor" fill="none" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
                            </svg>
                        </button>
                    </div>
                </div>
            </div>

            <!-- Mobile menu -->
            <div id="mobile-menu" class="hidden md:hidden">
                <div class="px-2 pt-2 pb-3 space-y-1 sm:px-3">
                    <a href="{{ route('affiliate.dashboard') }}" 
                       class="block px-3 py-2 rounded-md text-base font-medium {{ request()->routeIs('affiliate.dashboard') ? 'bg-blue-100 text-blue-700' : 'text-gray-600 hover:text-gray-900' }}">
                        Dashboard
                    </a>
                    <a href="{{ route('affiliate.links') }}" 
                       class="block px-3 py-2 rounded-md text-base font-medium {{ request()->routeIs('affiliate.links') ? 'bg-blue-100 text-blue-700' : 'text-gray-600 hover:text-gray-900' }}">
                        My Links
                    </a>
                    <a href="{{ route('affiliate.commissions') }}" 
                       class="block px-3 py-2 rounded-md text-base font-medium {{ request()->routeIs('affiliate.commissions') ? 'bg-blue-100 text-blue-700' : 'text-gray-600 hover:text-gray-900' }}">
                        Commissions
                    </a>
                    <a href="{{ route('affiliate.reports') }}" 
                       class="block px-3 py-2 rounded-md text-base font-medium {{ request()->routeIs('affiliate.reports') ? 'bg-blue-100 text-blue-700' : 'text-gray-600 hover:text-gray-900' }}">
                        Reports
                    </a>
                    <a href="{{ route('affiliate.profile') }}" 
                       class="block px-3 py-2 rounded-md text-base font-medium {{ request()->routeIs('affiliate.profile') ? 'bg-blue-100 text-blue-700' : 'text-gray-600 hover:text-gray-900' }}">
                        Profile
                    </a>
                </div>
            </div>
        </nav>

        <!-- Page Content -->
        <main class="max-w-7xl mx-auto py-6 px-4 sm:px-6 lg:px-8">
            {{ $slot }}
        </main>
    </div>

    @livewireScripts
    
    <!-- Toast Notifications -->
    <script>
        document.addEventListener('livewire:init', () => {
            Livewire.on('link-created', (event) => {
                showToast(event.message, 'success');
            });
            
            Livewire.on('link-updated', (event) => {
                showToast(event.message, 'success');
            });
            
            Livewire.on('link-deleted', (event) => {
                showToast(event.message, 'success');
            });
        });

        function showToast(message, type = 'info') {
            const toast = document.createElement('div');
            toast.className = `fixed top-4 right-4 z-50 p-4 rounded-lg shadow-lg transition-all duration-300 ${
                type === 'success' ? 'bg-green-100 border border-green-200 text-green-800' :
                type === 'error' ? 'bg-red-100 border border-red-200 text-red-800' :
                'bg-blue-100 border border-blue-200 text-blue-800'
            }`;
            toast.innerHTML = `
                <div class="flex items-center">
                    <span>${message}</span>
                    <button onclick="this.parentElement.parentElement.remove()" class="ml-4 text-gray-500 hover:text-gray-700">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                        </svg>
                    </button>
                </div>
            `;
            
            document.body.appendChild(toast);
            
            setTimeout(() => {
                toast.remove();
            }, 5000);
        }

        // Close dropdowns when clicking outside
        document.addEventListener('click', function(event) {
            const userMenu = document.getElementById('user-menu');
            const userButton = event.target.closest('button');
            
            if (!userButton || !userButton.querySelector('.rounded-full')) {
                userMenu.classList.add('hidden');
            }
        });
    </script>
</body>
</html>