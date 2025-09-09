<div class="space-y-6">
    <!-- Header -->
    <div class="flex justify-between items-center">
        <div>
            <h2 class="text-2xl font-bold text-gray-900">FAQ Management</h2>
            <p class="text-gray-600">Manage your organization's frequently asked questions</p>
        </div>
        <div class="flex space-x-2">
            <button wire:click="showAddForm" class="bg-blue-500 hover:bg-blue-600 text-white px-4 py-2 rounded-lg">
                Add New FAQ
            </button>
            <button wire:click="showImportForm" class="bg-green-500 hover:bg-green-600 text-white px-4 py-2 rounded-lg">
                Import CSV
            </button>
            <button wire:click="syncAllFaqs" class="bg-purple-500 hover:bg-purple-600 text-white px-4 py-2 rounded-lg">
                Sync to Search
            </button>
        </div>
    </div>

    <!-- Stats -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
        <div class="bg-blue-50 p-4 rounded-lg border border-blue-200">
            <h3 class="text-lg font-semibold text-blue-800">Total FAQs</h3>
            <p class="text-3xl font-bold text-blue-600">{{ $stats['total'] }}</p>
        </div>
        <div class="bg-green-50 p-4 rounded-lg border border-green-200">
            <h3 class="text-lg font-semibold text-green-800">Categories</h3>
            <p class="text-3xl font-bold text-green-600">{{ $stats['categories'] }}</p>
        </div>
        <div class="bg-purple-50 p-4 rounded-lg border border-purple-200">
            <h3 class="text-lg font-semibold text-purple-800">Organization</h3>
            <p class="text-lg font-bold text-purple-600">{{ $organization->name }}</p>
        </div>
    </div>

    <!-- Messages -->
    @if (session()->has('message'))
        <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded">
            {{ session('message') }}
        </div>
    @endif

    @if (session()->has('error'))
        <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded">
            {{ session('error') }}
        </div>
    @endif

    <!-- Add/Edit FAQ Form -->
    @if ($showAddForm)
        <div class="bg-gray-50 p-6 rounded-lg border">
            <h3 class="text-lg font-semibold mb-4">{{ $editingFaq ? 'Edit FAQ' : 'Add New FAQ' }}</h3>
            
            <form wire:submit.prevent="saveFaq" class="space-y-4">
                <div>
                    <label for="question" class="block text-sm font-medium text-gray-700 mb-1">Question</label>
                    <textarea wire:model="question" id="question" rows="2" class="w-full border border-gray-300 rounded-md px-3 py-2 focus:ring-blue-500 focus:border-blue-500" placeholder="Enter the question..."></textarea>
                    @error('question') <span class="text-red-500 text-sm">{{ $message }}</span> @enderror
                </div>

                <div>
                    <label for="answer" class="block text-sm font-medium text-gray-700 mb-1">Answer</label>
                    <textarea wire:model="answer" id="answer" rows="4" class="w-full border border-gray-300 rounded-md px-3 py-2 focus:ring-blue-500 focus:border-blue-500" placeholder="Enter the answer..."></textarea>
                    @error('answer') <span class="text-red-500 text-sm">{{ $message }}</span> @enderror
                </div>

                <div>
                    <label for="category" class="block text-sm font-medium text-gray-700 mb-1">Category</label>
                    <input wire:model="category" type="text" id="category" class="w-full border border-gray-300 rounded-md px-3 py-2 focus:ring-blue-500 focus:border-blue-500" placeholder="e.g., General, Services, Pricing">
                    @error('category') <span class="text-red-500 text-sm">{{ $message }}</span> @enderror
                </div>

                <div class="flex justify-end space-x-2">
                    <button type="button" wire:click="hideAddForm" class="bg-gray-500 hover:bg-gray-600 text-white px-4 py-2 rounded-lg">
                        Cancel
                    </button>
                    <button type="submit" class="bg-blue-500 hover:bg-blue-600 text-white px-4 py-2 rounded-lg">
                        {{ $editingFaq ? 'Update FAQ' : 'Add FAQ' }}
                    </button>
                </div>
            </form>
        </div>
    @endif

    <!-- CSV Import Form -->
    @if ($showImportForm)
        <div class="bg-gray-50 p-6 rounded-lg border">
            <h3 class="text-lg font-semibold mb-4">Import FAQs from CSV</h3>
            
            <div class="mb-4 p-4 bg-yellow-50 border border-yellow-200 rounded">
                <p class="text-sm text-yellow-800">
                    <strong>CSV Format:</strong> Your CSV file should have headers: <code>question</code>, <code>answer</code>, <code>category</code>
                </p>
            </div>

            <form wire:submit.prevent="importCsv" class="space-y-4">
                <div>
                    <label for="csvFile" class="block text-sm font-medium text-gray-700 mb-1">CSV File</label>
                    <input wire:model="csvFile" type="file" id="csvFile" accept=".csv,.txt" class="w-full border border-gray-300 rounded-md px-3 py-2 focus:ring-blue-500 focus:border-blue-500">
                    @error('csvFile') <span class="text-red-500 text-sm">{{ $message }}</span> @enderror
                </div>

                <div class="flex items-center">
                    <input wire:model="replaceExisting" type="checkbox" id="replaceExisting" class="mr-2">
                    <label for="replaceExisting" class="text-sm text-gray-700">Replace all existing FAQs (otherwise, update existing and add new)</label>
                </div>

                <div class="flex justify-end space-x-2">
                    <button type="button" wire:click="hideImportForm" class="bg-gray-500 hover:bg-gray-600 text-white px-4 py-2 rounded-lg">
                        Cancel
                    </button>
                    <button type="submit" class="bg-green-500 hover:bg-green-600 text-white px-4 py-2 rounded-lg">
                        Import FAQs
                    </button>
                </div>
            </form>
        </div>
    @endif

    <!-- Filters -->
    <div class="flex flex-col md:flex-row gap-4">
        <div class="flex-1">
            <label for="search" class="block text-sm font-medium text-gray-700 mb-1">Search FAQs</label>
            <input wire:model.live="search" type="text" id="search" class="w-full border border-gray-300 rounded-md px-3 py-2 focus:ring-blue-500 focus:border-blue-500" placeholder="Search questions or answers...">
        </div>
        <div class="md:w-64">
            <label for="categoryFilter" class="block text-sm font-medium text-gray-700 mb-1">Filter by Category</label>
            <select wire:model.live="categoryFilter" id="categoryFilter" class="w-full border border-gray-300 rounded-md px-3 py-2 focus:ring-blue-500 focus:border-blue-500">
                <option value="">All Categories</option>
                @foreach($categories as $category)
                    <option value="{{ $category }}">{{ $category }}</option>
                @endforeach
            </select>
        </div>
    </div>

    <!-- FAQs Table -->
    <div class="bg-white rounded-lg border border-gray-200">
        @if($faqs->count() > 0)
            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead class="bg-gray-50 border-b border-gray-200">
                        <tr>
                            <th class="text-left px-4 py-3 font-medium text-gray-700">Question</th>
                            <th class="text-left px-4 py-3 font-medium text-gray-700">Category</th>
                            <th class="text-left px-4 py-3 font-medium text-gray-700">Answer Preview</th>
                            <th class="text-right px-4 py-3 font-medium text-gray-700">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200">
                        @foreach($faqs as $faq)
                            <tr class="hover:bg-gray-50">
                                <td class="px-4 py-3">
                                    <div class="font-medium text-gray-900">{{ Str::limit($faq->question, 80) }}</div>
                                    <div class="text-sm text-gray-500">ID: {{ $faq->id }}</div>
                                </td>
                                <td class="px-4 py-3">
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800">
                                        {{ $faq->category }}
                                    </span>
                                </td>
                                <td class="px-4 py-3">
                                    <div class="text-sm text-gray-700">{{ Str::limit($faq->answer, 100) }}</div>
                                </td>
                                <td class="px-4 py-3 text-right">
                                    <button wire:click="editFaq({{ $faq->id }})" class="text-blue-600 hover:text-blue-800 mr-2">
                                        Edit
                                    </button>
                                    <button wire:click="deleteFaq({{ $faq->id }})" onclick="return confirm('Are you sure you want to delete this FAQ?')" class="text-red-600 hover:text-red-800">
                                        Delete
                                    </button>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            <div class="px-4 py-3 border-t border-gray-200">
                {{ $faqs->links() }}
            </div>
        @else
            <div class="text-center py-12">
                <div class="text-gray-500">
                    <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z" />
                    </svg>
                    <h3 class="mt-2 text-sm font-medium text-gray-900">No FAQs found</h3>
                    <p class="mt-1 text-sm text-gray-500">
                        @if($search || $categoryFilter)
                            No FAQs match your current filters.
                        @else
                            Get started by adding your first FAQ or importing from a CSV file.
                        @endif
                    </p>
                </div>
            </div>
        @endif
    </div>
</div>
