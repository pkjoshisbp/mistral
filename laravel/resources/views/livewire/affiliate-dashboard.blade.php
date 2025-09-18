<div class="space-y-6">
    <!-- Header -->
    <div class="bg-white shadow-sm rounded-lg p-6">
        <div class="flex flex-col md:flex-row md:items-center md:justify-between">
            <div>
                <h1 class="text-2xl font-bold text-gray-900">Affiliate Dashboard</h1>
                <p class="mt-1 text-gray-500">
                    Welcome back, {{ auth()->user()->name }}! Your affiliate code: 
                    <span class="font-mono bg-blue-50 px-2 py-1 rounded text-blue-700">{{ $affiliate->affiliate_code }}</span>
                </p>
            </div>
            <div class="mt-4 md:mt-0">
                <select wire:model.live="selectedPeriod" class="border border-gray-300 rounded-md px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500">
                    <option value="7">Last 7 days</option>
                    <option value="30">Last 30 days</option>
                    <option value="90">Last 90 days</option>
                    <option value="365">Last year</option>
                </select>
            </div>
        </div>
    </div>

    <!-- Stats Grid -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
        <!-- Total Links -->
        <div class="bg-white rounded-lg shadow-sm p-6">
            <div class="flex items-center">
                <div class="p-3 rounded-full bg-blue-100">
                    <svg class="w-6 h-6 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1"></path>
                    </svg>
                </div>
                <div class="ml-4">
                    <h3 class="text-sm font-medium text-gray-500">Total Links</h3>
                    <p class="text-2xl font-bold text-gray-900">{{ $stats['total_links'] }}</p>
                </div>
            </div>
        </div>

        <!-- Total Visits -->
        <div class="bg-white rounded-lg shadow-sm p-6">
            <div class="flex items-center">
                <div class="p-3 rounded-full bg-green-100">
                    <svg class="w-6 h-6 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path>
                    </svg>
                </div>
                <div class="ml-4">
                    <h3 class="text-sm font-medium text-gray-500">Visits</h3>
                    <p class="text-2xl font-bold text-gray-900">{{ number_format($stats['total_visits']) }}</p>
                </div>
            </div>
        </div>

        <!-- Conversions -->
        <div class="bg-white rounded-lg shadow-sm p-6">
            <div class="flex items-center">
                <div class="p-3 rounded-full bg-yellow-100">
                    <svg class="w-6 h-6 text-yellow-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                    </svg>
                </div>
                <div class="ml-4">
                    <h3 class="text-sm font-medium text-gray-500">Conversions</h3>
                    <p class="text-2xl font-bold text-gray-900">{{ number_format($stats['total_conversions']) }}</p>
                    <p class="text-sm text-gray-500">{{ $stats['conversion_rate'] }}% rate</p>
                </div>
            </div>
        </div>

        <!-- Commissions -->
        <div class="bg-white rounded-lg shadow-sm p-6">
            <div class="flex items-center">
                <div class="p-3 rounded-full bg-purple-100">
                    <svg class="w-6 h-6 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1"></path>
                    </svg>
                </div>
                <div class="ml-4">
                    <h3 class="text-sm font-medium text-gray-500">Earned Commissions</h3>
                    <p class="text-2xl font-bold text-gray-900">${{ number_format($stats['total_commissions'], 2) }}</p>
                    @if($stats['pending_commissions'] > 0)
                        <p class="text-sm text-yellow-600">${{ number_format($stats['pending_commissions'], 2) }} pending</p>
                    @endif
                </div>
            </div>
        </div>
    </div>

    <!-- Quick Actions & Links -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <!-- Link Management -->
        <div class="lg:col-span-2 bg-white rounded-lg shadow-sm">
            <div class="p-6 border-b">
                <div class="flex items-center justify-between">
                    <h2 class="text-lg font-medium text-gray-900">My Affiliate Links</h2>
                    <button wire:click="$set('showCreateLink', true)" class="bg-blue-600 text-white px-4 py-2 rounded-md hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500">
                        Create Link
                    </button>
                </div>
            </div>

            @if($showCreateLink)
                <div class="p-6 border-b bg-gray-50">
                    <form wire:submit.prevent="createLink">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Link Name</label>
                                <input type="text" wire:model="newLinkName" placeholder="Homepage Link" class="w-full border border-gray-300 rounded-md px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500 @error('newLinkName') border-red-500 @enderror">
                                @error('newLinkName') <div class="text-red-500 text-sm mt-1">{{ $message }}</div> @enderror
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Target URL</label>
                                <input type="url" wire:model="newLinkUrl" placeholder="https://example.com" class="w-full border border-gray-300 rounded-md px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500 @error('newLinkUrl') border-red-500 @enderror">
                                @error('newLinkUrl') <div class="text-red-500 text-sm mt-1">{{ $message }}</div> @enderror
                            </div>
                        </div>
                        <div class="flex items-center space-x-3 mt-4">
                            <button type="submit" class="bg-green-600 text-white px-4 py-2 rounded-md hover:bg-green-700">
                                Create Link
                            </button>
                            <button type="button" wire:click="$set('showCreateLink', false)" class="bg-gray-300 text-gray-700 px-4 py-2 rounded-md hover:bg-gray-400">
                                Cancel
                            </button>
                        </div>
                    </form>
                </div>
            @endif

            <div class="divide-y">
                @forelse($links as $link)
                    <div class="p-6">
                        <div class="flex items-center justify-between">
                            <div class="flex-1">
                                <div class="flex items-center space-x-3">
                                    <h3 class="text-sm font-medium text-gray-900">{{ $link->name }}</h3>
                                    <span class="inline-flex items-center px-2 py-1 text-xs font-medium rounded-full
                                        {{ $link->is_active ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-800' }}">
                                        {{ $link->is_active ? 'Active' : 'Inactive' }}
                                    </span>
                                </div>
                                <div class="mt-1">
                                    <p class="text-sm text-gray-600 font-mono bg-gray-50 px-2 py-1 rounded">
                                        {{ url('/') }}/ref/{{ $link->tracking_code }}
                                    </p>
                                </div>
                                <div class="flex items-center space-x-4 mt-2 text-sm text-gray-500">
                                    <span>{{ $link->visits_count }} visits</span>
                                    <span>{{ $link->conversions_count }} conversions</span>
                                    <span>Created {{ $link->created_at->diffForHumans() }}</span>
                                </div>
                            </div>
                            <div class="flex items-center space-x-2">
                                <button onclick="navigator.clipboard.writeText('{{ url('/') }}/ref/{{ $link->tracking_code }}')" 
                                        class="text-blue-600 hover:text-blue-900 text-sm font-medium">
                                    Copy
                                </button>
                                <button wire:click="toggleLinkStatus({{ $link->id }})" 
                                        class="text-yellow-600 hover:text-yellow-900 text-sm font-medium">
                                    {{ $link->is_active ? 'Deactivate' : 'Activate' }}
                                </button>
                                <button wire:click="deleteLink({{ $link->id }})" 
                                        onclick="return confirm('Are you sure you want to delete this link?')"
                                        class="text-red-600 hover:text-red-900 text-sm font-medium">
                                    Delete
                                </button>
                            </div>
                        </div>
                    </div>
                @empty
                    <div class="p-6 text-center text-gray-500">
                        <p>No affiliate links created yet.</p>
                        <button wire:click="$set('showCreateLink', true)" class="mt-2 text-blue-600 hover:text-blue-900 font-medium">
                            Create your first link
                        </button>
                    </div>
                @endforelse
            </div>

            @if($links->hasPages())
                <div class="px-6 py-3 border-t">
                    {{ $links->links() }}
                </div>
            @endif
        </div>

        <!-- Recent Activity -->
        <div class="bg-white rounded-lg shadow-sm">
            <div class="p-6 border-b">
                <h2 class="text-lg font-medium text-gray-900">Recent Activity</h2>
            </div>
            <div class="divide-y max-h-96 overflow-y-auto">
                @forelse($recentVisits as $visit)
                    <div class="p-4">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-sm font-medium text-gray-900">
                                    {{ $visit->link->name }}
                                </p>
                                <p class="text-xs text-gray-500">
                                    {{ $visit->visited_at->diffForHumans() }}
                                </p>
                            </div>
                            @if($visit->conversion_date)
                                <span class="inline-flex items-center px-2 py-1 text-xs font-medium bg-green-100 text-green-800 rounded-full">
                                    Converted
                                </span>
                            @else
                                <span class="inline-flex items-center px-2 py-1 text-xs font-medium bg-gray-100 text-gray-800 rounded-full">
                                    Visit
                                </span>
                            @endif
                        </div>
                    </div>
                @empty
                    <div class="p-4 text-center text-gray-500">
                        <p>No recent activity</p>
                    </div>
                @endforelse
            </div>
        </div>
    </div>

    <!-- Commission History -->
    <div class="bg-white rounded-lg shadow-sm">
        <div class="p-6 border-b">
            <h2 class="text-lg font-medium text-gray-900">Commission History</h2>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Type</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Amount</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Link</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    @forelse($commissions as $commission)
                        <tr>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                {{ $commission->commission_start_date->format('M j, Y') }}
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                {{ ucfirst(str_replace('_', ' ', $commission->commission_type)) }}
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                ${{ number_format($commission->commission_amount, 2) }}
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full
                                    {{ $commission->status === 'approved' ? 'bg-green-100 text-green-800' : 
                                       ($commission->status === 'pending' ? 'bg-yellow-100 text-yellow-800' : 'bg-red-100 text-red-800') }}">
                                    {{ ucfirst($commission->status) }}
                                </span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                {{ $commission->visit->link->name ?? 'N/A' }}
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="px-6 py-8 text-center text-gray-500">
                                No commissions earned yet. Start promoting to earn your first commission!
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        
        @if($commissions->hasPages())
            <div class="px-6 py-3 border-t">
                {{ $commissions->links() }}
            </div>
        @endif
    </div>
</div>
