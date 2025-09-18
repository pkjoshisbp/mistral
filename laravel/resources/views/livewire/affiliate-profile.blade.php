<div class="py-6">
    <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8">
        <!-- Header -->
        <div class="bg-white shadow rounded-lg p-6 mb-6">
            <h1 class="text-2xl font-bold text-gray-900">Affiliate Profile</h1>
            <p class="text-gray-600 mt-1">Manage your affiliate account information and settings</p>
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

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <!-- Profile Form -->
            <div class="lg:col-span-2">
                <div class="bg-white shadow rounded-lg p-6">
                    <h2 class="text-lg font-medium text-gray-900 mb-6">Profile Information</h2>
                    
                    <form wire:submit.prevent="updateProfile">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <!-- Name -->
                            <div>
                                <label for="name" class="block text-sm font-medium text-gray-700">Full Name</label>
                                <input type="text" wire:model="name" id="name" 
                                       class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                                @error('name') <span class="text-red-500 text-sm">{{ $message }}</span> @enderror
                            </div>

                            <!-- Email -->
                            <div>
                                <label for="email" class="block text-sm font-medium text-gray-700">Email Address</label>
                                <input type="email" wire:model="email" id="email" 
                                       class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                                @error('email') <span class="text-red-500 text-sm">{{ $message }}</span> @enderror
                            </div>

                            <!-- Phone -->
                            <div>
                                <label for="phone" class="block text-sm font-medium text-gray-700">Phone Number</label>
                                <input type="tel" wire:model="phone" id="phone" 
                                       class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500"
                                       placeholder="Optional">
                                @error('phone') <span class="text-red-500 text-sm">{{ $message }}</span> @enderror
                            </div>

                            <!-- Website -->
                            <div>
                                <label for="website" class="block text-sm font-medium text-gray-700">Website/Blog</label>
                                <input type="url" wire:model="website" id="website" 
                                       class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500"
                                       placeholder="https://yourwebsite.com">
                                @error('website') <span class="text-red-500 text-sm">{{ $message }}</span> @enderror
                            </div>
                        </div>

                        <!-- Commission Type -->
                        <div class="mt-6">
                            <label for="commission_type" class="block text-sm font-medium text-gray-700">Preferred Commission Type</label>
                            <select wire:model="commission_type" id="commission_type" 
                                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                                <option value="one-time">One-time Commission (Higher rate per sale)</option>
                                <option value="recurring">Recurring Commission (Monthly for 3 years)</option>
                            </select>
                            @error('commission_type') <span class="text-red-500 text-sm">{{ $message }}</span> @enderror
                            <p class="mt-1 text-sm text-gray-500">
                                @if($commission_type === 'one-time')
                                    You'll earn a higher percentage (20-40%) for each sale you generate.
                                @else
                                    You'll earn a lower but recurring percentage (5-15%) monthly for 3 years.
                                @endif
                            </p>
                        </div>

                        <!-- Description -->
                        <div class="mt-6">
                            <label for="description" class="block text-sm font-medium text-gray-700">About You</label>
                            <textarea wire:model="description" id="description" rows="3"
                                      class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500"
                                      placeholder="Tell us about yourself and your marketing approach..."></textarea>
                            @error('description') <span class="text-red-500 text-sm">{{ $message }}</span> @enderror
                        </div>

                        <!-- Marketing Experience -->
                        <div class="mt-6">
                            <label for="marketing_experience" class="block text-sm font-medium text-gray-700">Marketing Experience</label>
                            <textarea wire:model="marketing_experience" id="marketing_experience" rows="3"
                                      class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500"
                                      placeholder="Describe your marketing experience and how you plan to promote our services..."></textarea>
                            @error('marketing_experience') <span class="text-red-500 text-sm">{{ $message }}</span> @enderror
                        </div>

                        <!-- Submit Button -->
                        <div class="mt-6">
                            <button type="submit" 
                                    class="w-full md:w-auto bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-6 rounded">
                                Update Profile
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Account Status & Info -->
            <div class="lg:col-span-1">
                <div class="bg-white shadow rounded-lg p-6 mb-6">
                    <h3 class="text-lg font-medium text-gray-900 mb-4">Account Status</h3>
                    
                    <div class="space-y-4">
                        <!-- Status -->
                        <div>
                            <label class="text-sm font-medium text-gray-700">Status</label>
                            <div class="mt-1">
                                @switch($affiliate->status)
                                    @case('pending')
                                        <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full bg-yellow-100 text-yellow-800">
                                            Pending Approval
                                        </span>
                                        @break
                                    @case('approved')
                                        <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full bg-green-100 text-green-800">
                                            Approved & Active
                                        </span>
                                        @break
                                    @case('rejected')
                                        <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full bg-red-100 text-red-800">
                                            Rejected
                                        </span>
                                        @break
                                    @case('suspended')
                                        <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full bg-gray-100 text-gray-800">
                                            Suspended
                                        </span>
                                        @break
                                @endswitch
                            </div>
                        </div>

                        <!-- Affiliate Code -->
                        <div>
                            <label class="text-sm font-medium text-gray-700">Your Affiliate Code</label>
                            <div class="mt-1 flex">
                                <input type="text" value="{{ $affiliate->affiliate_code }}" readonly
                                       class="block w-full rounded-l-md border-gray-300 bg-gray-50 text-gray-900">
                                <button onclick="copyToClipboard('{{ $affiliate->affiliate_code }}')"
                                        class="inline-flex items-center px-3 py-2 border border-l-0 border-gray-300 rounded-r-md bg-gray-50 text-gray-500 text-sm">
                                    Copy
                                </button>
                            </div>
                        </div>

                        <!-- Join Date -->
                        <div>
                            <label class="text-sm font-medium text-gray-700">Member Since</label>
                            <div class="mt-1 text-sm text-gray-900">
                                {{ $affiliate->created_at->format('F j, Y') }}
                            </div>
                        </div>

                        <!-- Commission Rate -->
                        <div>
                            <label class="text-sm font-medium text-gray-700">Commission Rate</label>
                            <div class="mt-1 text-sm text-gray-900">
                                @if($affiliate->commission_type === 'one-time')
                                    30% per sale
                                @else
                                    10% monthly for 36 months
                                @endif
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Quick Actions -->
                <div class="bg-white shadow rounded-lg p-6">
                    <h3 class="text-lg font-medium text-gray-900 mb-4">Quick Actions</h3>
                    
                    <div class="space-y-3">
                        <a href="{{ route('affiliate.links') }}" 
                           class="block w-full text-center bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded">
                            Create New Link
                        </a>
                        
                        <a href="{{ route('affiliate.reports') }}" 
                           class="block w-full text-center bg-gray-600 hover:bg-gray-700 text-white font-bold py-2 px-4 rounded">
                            View Reports
                        </a>
                        
                        <a href="{{ route('affiliate.commissions') }}" 
                           class="block w-full text-center bg-green-600 hover:bg-green-700 text-white font-bold py-2 px-4 rounded">
                            View Earnings
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    function copyToClipboard(text) {
        navigator.clipboard.writeText(text).then(function() {
            alert('Affiliate code copied to clipboard!');
        }, function(err) {
            console.error('Could not copy text: ', err);
        });
    }
</script>