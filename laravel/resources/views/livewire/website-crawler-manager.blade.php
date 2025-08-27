<div class="card">
    <div class="card-header">
        <h3 class="card-title">Website Crawler</h3>
        <div class="card-tools">
            <small class="text-muted">Automatically extract content from client websites</small>
        </div>
    </div>

    <div class="card-body">
        @if (session()->has('message'))
            <div class="alert alert-success alert-dismissible">
                <button type="button" class="close" data-dismiss="alert">&times;</button>
                {{ session('message') }}
            </div>
        @endif

        @if (session()->has('error'))
            <div class="alert alert-danger alert-dismissible">
                <button type="button" class="close" data-dismiss="alert">&times;</button>
                {{ session('error') }}
            </div>
        @endif

        <!-- Organization Selection -->
        <div class="row mb-4">
            <div class="col-md-6">
                <div class="form-group">
                    <label for="selectedOrgId">Select Organization</label>
                    <select wire:model.live="selectedOrgId" class="form-control" id="selectedOrgId">
                        <option value="">Choose an organization...</option>
                        @foreach ($organizations as $org)
                            <option value="{{ $org->id }}">{{ $org->name }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
        </div>

        @if ($selectedOrgId)
            <!-- Add New Crawler Button -->
            <div class="mb-4">
                <button wire:click="$toggle('showCreateForm')" class="btn btn-primary">
                    <i class="fas fa-plus"></i> Add Website Crawler
                </button>
            </div>

            <!-- Create Form -->
            @if ($showCreateForm)
                <div class="card card-outline card-success mb-4">
                    <div class="card-header">
                        <h3 class="card-title">Create Website Crawler</h3>
                    </div>
                    <div class="card-body">
                        <form wire:submit.prevent="createCrawler">
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="name">Crawler Name</label>
                                        <input type="text" wire:model="name" class="form-control" id="name" placeholder="e.g., Main Website">
                                        @error('name') <span class="text-danger">{{ $message }}</span> @enderror
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="website_url">Website URL</label>
                                        <div class="input-group">
                                            <input type="url" wire:model="website_url" class="form-control" id="website_url" placeholder="https://example.com">
                                            <div class="input-group-append">
                                                <button type="button" wire:click="testCrawl" class="btn btn-outline-secondary">
                                                    <i class="fas fa-globe"></i> Test
                                                </button>
                                                <button type="button" wire:click="detectSitemap" class="btn btn-outline-info">
                                                    <i class="fas fa-search"></i> Find Sitemap
                                                </button>
                                            </div>
                                        </div>
                                        @error('website_url') <span class="text-danger">{{ $message }}</span> @enderror
                                    </div>
                                </div>
                            </div>

                            <!-- Crawl Type Selection -->
                            <div class="row">
                                <div class="col-12">
                                    <div class="form-group">
                                        <label>Crawl Method</label>
                                        <div class="form-check">
                                            <input wire:model.live="crawl_type" class="form-check-input" type="radio" value="sitemap" id="crawl_sitemap">
                                            <label class="form-check-label" for="crawl_sitemap">
                                                <strong>Sitemap</strong> - Use website's sitemap.xml (Recommended)
                                            </label>
                                        </div>
                                        <div class="form-check">
                                            <input wire:model.live="crawl_type" class="form-check-input" type="radio" value="specific_pages" id="crawl_specific">
                                            <label class="form-check-label" for="crawl_specific">
                                                <strong>Specific Pages</strong> - Define exact URLs to crawl
                                            </label>
                                        </div>
                                        <div class="form-check">
                                            <input wire:model.live="crawl_type" class="form-check-input" type="radio" value="full_crawl" id="crawl_full">
                                            <label class="form-check-label" for="crawl_full">
                                                <strong>Full Crawl</strong> - Start from homepage and follow links
                                            </label>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Sitemap Configuration -->
                            @if ($crawl_type === 'sitemap')
                                <div class="row">
                                    <div class="col-md-8">
                                        <div class="form-group">
                                            <label for="sitemap_url">Sitemap URL</label>
                                            <input type="url" wire:model="sitemap_url" class="form-control" id="sitemap_url" placeholder="https://example.com/sitemap.xml">
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <label>Or Upload Sitemap</label>
                                        <div class="custom-file">
                                            <input type="file" wire:model="sitemap_file" class="custom-file-input" accept=".xml,.txt">
                                            <label class="custom-file-label">Choose file</label>
                                        </div>
                                        @if ($sitemap_file)
                                            <button type="button" wire:click="uploadSitemap" class="btn btn-sm btn-success mt-1">
                                                <i class="fas fa-upload"></i> Process
                                            </button>
                                        @endif
                                    </div>
                                </div>
                            @endif

                            <!-- Specific Pages Configuration -->
                            @if ($crawl_type === 'specific_pages')
                                <div class="form-group">
                                    <label for="specific_pages">Specific URLs (one per line)</label>
                                    <textarea wire:model="specific_pages" class="form-control" id="specific_pages" rows="6" placeholder="https://example.com/services&#10;https://example.com/about&#10;https://example.com/contact"></textarea>
                                    <small class="text-muted">Enter one URL per line. Maximum 100 URLs.</small>
                                </div>
                            @endif

                            <!-- Full Crawl Configuration -->
                            @if ($crawl_type === 'full_crawl')
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label for="max_depth">Max Depth</label>
                                            <select wire:model="max_depth" class="form-control" id="max_depth">
                                                <option value="1">1 level (homepage only)</option>
                                                <option value="2">2 levels</option>
                                                <option value="3">3 levels (recommended)</option>
                                                <option value="4">4 levels</option>
                                                <option value="5">5 levels</option>
                                            </select>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label for="max_pages">Max Pages</label>
                                            <select wire:model="max_pages" class="form-control" id="max_pages">
                                                <option value="10">10 pages</option>
                                                <option value="25">25 pages</option>
                                                <option value="50">50 pages (recommended)</option>
                                                <option value="100">100 pages</option>
                                                <option value="200">200 pages</option>
                                            </select>
                                        </div>
                                    </div>
                                </div>
                            @endif

                            <!-- Advanced Options -->
                            <div class="card card-outline card-secondary">
                                <div class="card-header">
                                    <h4 class="card-title">Advanced Options (Optional)</h4>
                                </div>
                                <div class="card-body">
                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="form-group">
                                                <label for="include_patterns">Include Patterns (one per line)</label>
                                                <textarea wire:model="include_patterns" class="form-control" id="include_patterns" rows="3" placeholder="/services/&#10;/products/&#10;/about"></textarea>
                                                <small class="text-muted">Only crawl URLs containing these patterns</small>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="form-group">
                                                <label for="exclude_patterns">Exclude Patterns (one per line)</label>
                                                <textarea wire:model="exclude_patterns" class="form-control" id="exclude_patterns" rows="3" placeholder="/admin/&#10;/login/&#10;/cart/"></textarea>
                                                <small class="text-muted">Skip URLs containing these patterns</small>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="form-group">
                                        <label for="description">Description</label>
                                        <textarea wire:model="description" class="form-control" id="description" rows="2" placeholder="Brief description of this crawler configuration"></textarea>
                                    </div>
                                </div>
                            </div>

                            <div class="form-group mt-3">
                                <button type="submit" class="btn btn-success">
                                    <i class="fas fa-save"></i> Create Crawler
                                </button>
                                <button type="button" wire:click="$set('showCreateForm', false)" class="btn btn-secondary">
                                    <i class="fas fa-times"></i> Cancel
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            @endif

            <!-- Existing Crawlers -->
            @if ($crawlers->count() > 0)
                <div class="card card-outline card-info">
                    <div class="card-header">
                        <h3 class="card-title">Website Crawlers</h3>
                        <div class="card-tools">
                            <span class="badge badge-info">{{ $crawlers->count() }} crawlers</span>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-striped">
                                <thead>
                                    <tr>
                                        <th>Name</th>
                                        <th>Website</th>
                                        <th>Method</th>
                                        <th>Last Crawled</th>
                                        <th>Stats</th>
                                        <th>Status</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($crawlers as $crawler)
                                        <tr>
                                            <td>
                                                <strong>{{ $crawler->name }}</strong>
                                                @if ($crawler->description)
                                                    <br><small class="text-muted">{{ $crawler->description }}</small>
                                                @endif
                                            </td>
                                            <td>
                                                <a href="{{ $crawler->website_url }}" target="_blank" class="text-primary">
                                                    {{ parse_url($crawler->website_url, PHP_URL_HOST) }}
                                                    <i class="fas fa-external-link-alt fa-xs"></i>
                                                </a>
                                            </td>
                                            <td>
                                                @if ($crawler->sitemap_url)
                                                    <span class="badge badge-success">Sitemap</span>
                                                @elseif ($crawler->specific_pages)
                                                    <span class="badge badge-info">{{ count($crawler->specific_pages) }} Pages</span>
                                                @else
                                                    <span class="badge badge-warning">Full Crawl</span>
                                                @endif
                                            </td>
                                            <td>
                                                @if ($crawler->last_crawled_at)
                                                    {{ $crawler->last_crawled_at->diffForHumans() }}
                                                @else
                                                    <span class="text-muted">Never</span>
                                                @endif
                                            </td>
                                            <td>
                                                @if ($crawler->crawl_stats)
                                                    <small>
                                                        {{ $crawler->crawl_stats['pages_processed'] ?? 0 }} processed<br>
                                                        {{ $crawler->crawl_stats['pages_failed'] ?? 0 }} failed
                                                    </small>
                                                @else
                                                    <span class="text-muted">-</span>
                                                @endif
                                            </td>
                                            <td>
                                                <span class="badge badge-{{ $crawler->is_active ? 'success' : 'secondary' }}">
                                                    {{ $crawler->is_active ? 'Active' : 'Inactive' }}
                                                </span>
                                            </td>
                                            <td>
                                                <button wire:click="executeCrawl({{ $crawler->id }})" 
                                                        class="btn btn-primary btn-sm"
                                                        {{ $isCrawling ? 'disabled' : '' }}
                                                        wire:loading.attr="disabled">
                                                    @if ($isCrawling && $currentCrawlId == $crawler->id)
                                                        <i class="fas fa-spinner fa-spin"></i>
                                                        <span wire:loading.remove>Crawling...</span>
                                                        <span wire:loading>Starting...</span>
                                                    @elseif ($isCrawling)
                                                        <i class="fas fa-clock"></i> Wait
                                                    @else
                                                        <i class="fas fa-play"></i>
                                                        <span wire:loading.remove>Crawl</span>
                                                        <span wire:loading>Starting...</span>
                                                    @endif
                                                </button>
                                                <button wire:click="deleteCrawler({{ $crawler->id }})" 
                                                        class="btn btn-danger btn-sm"
                                                        {{ $isCrawling ? 'disabled' : '' }}
                                                        onclick="return confirm('Are you sure?')">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            @endif

            <!-- Crawl Progress Indicator -->
            @if ($isCrawling)
                <div class="card card-outline card-warning">
                    <div class="card-header">
                        <h3 class="card-title">
                            <i class="fas fa-spider fa-spin"></i> Website Crawl in Progress
                        </h3>
                        <div class="card-tools">
                            <span class="badge badge-warning">Processing</span>
                        </div>
                    </div>
                    <div class="card-body">
                        <!-- Progress Bar -->
                        <div class="progress mb-3" style="height: 25px;">
                            <div class="progress-bar progress-bar-striped progress-bar-animated bg-warning" 
                                 role="progressbar" 
                                 style="width: {{ $progressPercent }}%"
                                 aria-valuenow="{{ $progressPercent }}" 
                                 aria-valuemin="0" 
                                 aria-valuemax="100">
                                {{ $progressPercent }}%
                            </div>
                        </div>

                        <!-- Status Information -->
                        <div class="row">
                            <div class="col-md-4">
                                <div class="info-box bg-info">
                                    <span class="info-box-icon"><i class="fas fa-globe"></i></span>
                                    <div class="info-box-content">
                                        <span class="info-box-text">Total Pages</span>
                                        <span class="info-box-number">{{ $totalPages > 0 ? $totalPages : '...' }}</span>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="info-box bg-success">
                                    <span class="info-box-icon"><i class="fas fa-check"></i></span>
                                    <div class="info-box-content">
                                        <span class="info-box-text">Processed</span>
                                        <span class="info-box-number">{{ $pagesProcessed }}</span>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="info-box bg-{{ $pagesFailed > 0 ? 'danger' : 'secondary' }}">
                                    <span class="info-box-icon">
                                        <i class="fas fa-{{ $pagesFailed > 0 ? 'exclamation-triangle' : 'clock' }}"></i>
                                    </span>
                                    <div class="info-box-content">
                                        <span class="info-box-text">{{ $pagesFailed > 0 ? 'Failed' : 'Remaining' }}</span>
                                        <span class="info-box-number">
                                            {{ $pagesFailed > 0 ? $pagesFailed : ($totalPages > 0 ? max(0, $totalPages - $pagesProcessed) : '...') }}
                                        </span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Current Status -->
                        @if($currentUrl)
                            <div class="alert alert-info mt-3">
                                <i class="fas fa-info-circle"></i> <strong>Status:</strong> {{ $currentUrl }}
                            </div>
                        @endif

                        <!-- Important Notice -->
                        <div class="callout callout-warning">
                            <h5><i class="fas fa-clock"></i> Please Wait</h5>
                            <p>Website crawling is in progress. This process can take several minutes depending on the number of pages. 
                               The page will automatically update when crawling is complete.</p>
                        </div>
                    </div>
                </div>
            @endif

            <!-- Info Section -->
            <div class="card card-outline card-secondary">
                <div class="card-header">
                    <h3 class="card-title">How Website Crawling Works</h3>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-4">
                            <div class="info-box">
                                <span class="info-box-icon bg-primary"><i class="fas fa-sitemap"></i></span>
                                <div class="info-box-content">
                                    <span class="info-box-text">Sitemap Method</span>
                                    <span class="info-box-number">Best</span>
                                    <div class="progress">
                                        <div class="progress-bar" style="width: 100%"></div>
                                    </div>
                                    <span class="progress-description">Fast & comprehensive</span>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="info-box">
                                <span class="info-box-icon bg-success"><i class="fas fa-list"></i></span>
                                <div class="info-box-content">
                                    <span class="info-box-text">Specific Pages</span>
                                    <span class="info-box-number">Precise</span>
                                    <div class="progress">
                                        <div class="progress-bar bg-success" style="width: 80%"></div>
                                    </div>
                                    <span class="progress-description">Controlled content</span>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="info-box">
                                <span class="info-box-icon bg-warning"><i class="fas fa-spider"></i></span>
                                <div class="info-box-content">
                                    <span class="info-box-text">Full Crawl</span>
                                    <span class="info-box-number">Complete</span>
                                    <div class="progress">
                                        <div class="progress-bar bg-warning" style="width: 60%"></div>
                                    </div>
                                    <span class="progress-description">Slower but thorough</span>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <h5 class="mt-3">What Gets Extracted:</h5>
                    <ul>
                        <li><strong>Page Content:</strong> Main text content from each page</li>
                        <li><strong>Services/Products:</strong> Automatically detects service and product listings</li>
                        <li><strong>Contact Info:</strong> Hours, location, contact details</li>
                        <li><strong>FAQs:</strong> Question-answer sections</li>
                        <li><strong>Pricing:</strong> Cost information when available</li>
                    </ul>
                </div>
            </div>

        @else
            <div class="text-center text-muted py-5">
                <i class="fas fa-building fa-3x mb-3"></i>
                <p>Please select an organization to manage website crawlers.</p>
            </div>
        @endif
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    let crawlProgressInterval;
    
    // Listen for crawl progress updates
    window.addEventListener('crawl-progress-updated', function(event) {
        console.log('Crawl progress:', event.detail);
    });
    
    // Auto-refresh when crawling is active
    function startProgressPolling() {
        if (crawlProgressInterval) {
            clearInterval(crawlProgressInterval);
        }
        
        crawlProgressInterval = setInterval(function() {
            if (@this.isCrawling) {
                @this.$refresh();
            } else {
                clearInterval(crawlProgressInterval);
            }
        }, 3000); // Refresh every 3 seconds
    }
    
    // Start polling when crawl begins
    document.addEventListener('livewire:initialized', function() {
        if (@this.isCrawling) {
            startProgressPolling();
        }
        
        // Listen for when crawling starts
        @this.on('crawl-started', function() {
            startProgressPolling();
        });
    });
});
</script>
