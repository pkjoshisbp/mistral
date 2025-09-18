<div class="py-6">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <!-- Header -->
        <div class="bg-white shadow rounded-lg p-6 mb-6">
            <div class="flex justify-between items-center">
                <div>
                    <h1 class="text-2xl font-bold text-gray-900">My Affiliate Links</h1>
                    <p class="text-gray-600 mt-1">Create and manage your affiliate tracking links</p>
                </div>
                <button wire:click="$set('showCreateForm', true)" 
                        class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded">
                    Create New Link
                </button>
            </div>
        </div>

        <!-- Flash Messages -->
        @if (session()->has('message'))
            <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-4">
                {{ session('message') }}
            </div>
        @endif

        @if (session()->has('error'))
            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">
                {{ session('error') }}
            </div>
        @endif

        <!-- Create Link Form -->
        @if ($showCreateForm)
            <div class="bg-white shadow rounded-lg p-6 mb-6">
                <form wire:submit.prevent="createLink">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <label for="name" class="block text-sm font-medium text-gray-700">Link Name</label>
                            <input type="text" wire:model="name" id="name" 
                                   class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500"
                                   placeholder="e.g., Homepage Link">
                            @error('name') <span class="text-red-500 text-sm">{{ $message }}</span> @enderror
                        </div>

                        <div>
                            <label for="originalUrl" class="block text-sm font-medium text-gray-700">Target URL</label>
                            <input type="url" wire:model="originalUrl" id="originalUrl" 
                                   class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500"
                                   placeholder="https://example.com/page">
                            @error('originalUrl') <span class="text-red-500 text-sm">{{ $message }}</span> @enderror
                        </div>
                    </div>

                    <div class="mt-6 flex justify-end space-x-3">
                        <button type="button" wire:click="$set('showCreateForm', false)"
                                class="bg-gray-300 hover:bg-gray-400 text-gray-800 font-bold py-2 px-4 rounded">
                            Cancel
                        </button>
                        <button type="submit" 
                                class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded">
                            Create Link
                        </button>
                    </div>
                </form>
            </div>
        @endif

        <!-- Links Table -->
        <div class="bg-white shadow rounded-lg overflow-hidden">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Name</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Tracking URL</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Original URL</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Stats</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    @forelse ($links as $link)
                        <tr>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="font-medium text-gray-900">{{ $link->name }}</div>
                                <div class="text-sm text-gray-500">Created {{ $link->created_at->diffForHumans() }}</div>
                            </td>
                            <td class="px-6 py-4">
                                <div class="text-sm text-gray-900 break-all">
                                    {{ route('affiliate.redirect', $link->tracking_code) }}
                                </div>
                                <button onclick="copyToClipboard('{{ route('affiliate.redirect', $link->tracking_code) }}')"
                                        class="text-blue-600 hover:text-blue-800 text-sm mt-1">
                                    Copy Link
                                </button>
                            </td>
                            <td class="px-6 py-4">
                                <div class="text-sm text-gray-900 break-all">{{ $link->original_url }}</div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="text-sm text-gray-900">{{ $link->clicks }} clicks</div>
                                <div class="text-sm text-gray-500">{{ $link->conversions }} conversions</div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                @if ($link->is_active)
                                    <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full bg-green-100 text-green-800">
                                        Active
                                    </span>
                                @else
                                    <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full bg-red-100 text-red-800">
                                        Inactive
                                    </span>
                                @endif
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                <button wire:click="toggleLinkStatus({{ $link->id }})"
                                        class="text-blue-600 hover:text-blue-800 mr-3">
                                    {{ $link->is_active ? 'Deactivate' : 'Activate' }}
                                </button>
                                <button wire:click="deleteLink({{ $link->id }})"
                                        onclick="return confirm('Are you sure you want to delete this link?')"
                                        class="text-red-600 hover:text-red-800">
                                    Delete
                                </button>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-6 py-4 text-center text-gray-500">
                                No affiliate links created yet. 
                                <button wire:click="$set('showCreateForm', true)" class="text-blue-600 hover:text-blue-800">
                                    Create your first link
                                </button>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>

            <!-- Pagination -->
            @if ($links->hasPages())
                <div class="px-6 py-4 bg-gray-50 border-t">
                    {{ $links->links() }}
                </div>
            @endif
        </div>
    </div>
</div>

<script>
    function copyToClipboard(text) {
        navigator.clipboard.writeText(text).then(function() {
            alert('Link copied to clipboard!');
        }, function(err) {
            console.error('Could not copy text: ', err);
        });
    }
</script>