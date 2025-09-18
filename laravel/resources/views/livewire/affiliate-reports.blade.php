<div class="py-6">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <!-- Header -->
        <div class="bg-white shadow rounded-lg p-6 mb-6">
            <h1 class="text-2xl font-bold text-gray-900">Performance Reports</h1>
            <p class="text-gray-600 mt-1">Analyze your affiliate marketing performance and optimize your strategy</p>
        </div>

        <!-- Filters -->
        <div class="bg-white shadow rounded-lg p-6 mb-6">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label for="dateRange" class="block text-sm font-medium text-gray-700">Date Range</label>
                    <select wire:model.live="dateRange" id="dateRange" 
                            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                        <option value="7">Last 7 days</option>
                        <option value="30">Last 30 days</option>
                        <option value="90">Last 90 days</option>
                        <option value="365">Last year</option>
                    </select>
                </div>

                <div>
                    <label for="selectedLink" class="block text-sm font-medium text-gray-700">Filter by Link</label>
                    <select wire:model.live="selectedLink" id="selectedLink" 
                            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                        <option value="all">All Links</option>
                        @foreach($allLinks as $link)
                            <option value="{{ $link->id }}">{{ $link->name }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
        </div>

        <!-- Summary Cards -->
        <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-6">
            <div class="bg-white shadow rounded-lg p-6">
                <div class="flex items-center">
                    <div class="p-3 rounded-full bg-blue-100">
                        <svg class="w-6 h-6 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path>
                        </svg>
                    </div>
                    <div class="ml-4">
                        <p class="text-sm font-medium text-gray-500">Total Visits</p>
                        <p class="text-2xl font-semibold text-gray-900">{{ number_format($totalVisits) }}</p>
                    </div>
                </div>
            </div>

            <div class="bg-white shadow rounded-lg p-6">
                <div class="flex items-center">
                    <div class="p-3 rounded-full bg-green-100">
                        <svg class="w-6 h-6 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                        </svg>
                    </div>
                    <div class="ml-4">
                        <p class="text-sm font-medium text-gray-500">Conversions</p>
                        <p class="text-2xl font-semibold text-gray-900">{{ number_format($totalConversions) }}</p>
                    </div>
                </div>
            </div>

            <div class="bg-white shadow rounded-lg p-6">
                <div class="flex items-center">
                    <div class="p-3 rounded-full bg-purple-100">
                        <svg class="w-6 h-6 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path>
                        </svg>
                    </div>
                    <div class="ml-4">
                        <p class="text-sm font-medium text-gray-500">Conversion Rate</p>
                        <p class="text-2xl font-semibold text-gray-900">{{ number_format($conversionRate, 2) }}%</p>
                    </div>
                </div>
            </div>

            <div class="bg-white shadow rounded-lg p-6">
                <div class="flex items-center">
                    <div class="p-3 rounded-full bg-yellow-100">
                        <svg class="w-6 h-6 text-yellow-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1"></path>
                        </svg>
                    </div>
                    <div class="ml-4">
                        <p class="text-sm font-medium text-gray-500">Total Earnings</p>
                        <p class="text-2xl font-semibold text-gray-900">${{ number_format($totalEarnings, 2) }}</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Charts -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
            <!-- Visits & Conversions Chart -->
            <div class="bg-white shadow rounded-lg p-6">
                <h3 class="text-lg font-medium text-gray-900 mb-4">Visits & Conversions Over Time</h3>
                <div class="h-64 flex items-center justify-center bg-gray-50 rounded">
                    <!-- Simple text-based chart - in a real app you'd use Chart.js or similar -->
                    <div class="text-center">
                        <p class="text-gray-600 mb-2">Daily Performance Trend</p>
                        <div class="space-y-1 text-sm">
                            @foreach(array_slice($dailyStats, -7) as $stat)
                                <div class="flex justify-between items-center px-4 py-1 bg-white rounded">
                                    <span>{{ $stat['date'] }}</span>
                                    <span class="text-blue-600">{{ $stat['visits'] }} visits</span>
                                    <span class="text-green-600">{{ $stat['conversions'] }} conv</span>
                                </div>
                            @endforeach
                        </div>
                    </div>
                </div>
            </div>

            <!-- Earnings Chart -->
            <div class="bg-white shadow rounded-lg p-6">
                <h3 class="text-lg font-medium text-gray-900 mb-4">Daily Earnings</h3>
                <div class="h-64 flex items-center justify-center bg-gray-50 rounded">
                    <div class="text-center">
                        <p class="text-gray-600 mb-2">Earnings Trend</p>
                        <div class="space-y-1 text-sm">
                            @foreach(array_slice($dailyStats, -7) as $stat)
                                <div class="flex justify-between items-center px-4 py-1 bg-white rounded">
                                    <span>{{ $stat['date'] }}</span>
                                    <span class="text-green-600 font-medium">${{ number_format($stat['earnings'], 2) }}</span>
                                </div>
                            @endforeach
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Top Performing Links -->
        <div class="bg-white shadow rounded-lg p-6">
            <h3 class="text-lg font-medium text-gray-900 mb-4">Top Performing Links</h3>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Link Name</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Visits</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Conversions</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Conversion Rate</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Performance</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        @forelse($topLinks as $link)
                            <tr>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="font-medium text-gray-900">{{ $link->name }}</div>
                                    <div class="text-sm text-gray-500">{{ Str::limit($link->original_url, 50) }}</div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                    {{ number_format($link->visits_count) }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                    {{ number_format($link->conversions_count) }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                    {{ $link->visits_count > 0 ? number_format(($link->conversions_count / $link->visits_count) * 100, 2) : 0 }}%
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    @php
                                        $rate = $link->visits_count > 0 ? ($link->conversions_count / $link->visits_count) * 100 : 0;
                                    @endphp
                                    @if($rate >= 10)
                                        <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full bg-green-100 text-green-800">
                                            Excellent
                                        </span>
                                    @elseif($rate >= 5)
                                        <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full bg-yellow-100 text-yellow-800">
                                            Good
                                        </span>
                                    @elseif($rate > 0)
                                        <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full bg-orange-100 text-orange-800">
                                            Average
                                        </span>
                                    @else
                                        <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full bg-gray-100 text-gray-800">
                                            No conversions
                                        </span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="px-6 py-4 text-center text-gray-500">
                                    No links found. Create some affiliate links to see performance data.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>