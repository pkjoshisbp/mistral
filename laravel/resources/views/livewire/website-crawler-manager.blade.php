<div class="card" wire:poll.5s="pollProgress">
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

                            {{-- ── Structured Extraction Configuration ───────────────── --}}
                            <div class="card mt-3 border-info">
                                <div class="card-header bg-info text-white">
                                    <strong><i class="fas fa-magic"></i> Smart Attribute Extraction (AI-Powered)</strong>
                                    <small class="ml-2 font-weight-light">Define what data to extract from each page</small>
                                </div>
                                <div class="card-body">
                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="form-group">
                                                <label for="page_type">Page / Entity Type</label>
                                                <select wire:model="page_type" class="form-control" id="page_type">
                                                    <option value="product">Product (e-commerce)</option>
                                                    <option value="service">Service</option>
                                                    <option value="doctor">Doctor / Staff profile</option>
                                                    <option value="property">Property / Real estate</option>
                                                    <option value="menu-item">Restaurant menu item</option>
                                                    <option value="medical-test">Medical test / Diagnostic</option>
                                                    <option value="faq">FAQ / Q&A</option>
                                                    <option value="article">Blog / Article</option>
                                                    <option value="event">Event</option>
                                                    <option value="course">Course / Training</option>
                                                    <option value="general">General / Other</option>
                                                </select>
                                                <small class="text-muted">Tells the AI what kind of entity it is reading</small>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="form-group">
                                                <label for="extraction_method">Extraction Method</label>
                                                <select wire:model="extraction_method" class="form-control" id="extraction_method">
                                                    <option value="llm">AI / LLM (recommended – works on any site)</option>
                                                    <option value="structured">Structured only (faster, no AI)</option>
                                                </select>
                                                <small class="text-muted">LLM method reads the page and understands context</small>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="form-group">
                                        <label for="attribute_schema">
                                            Attributes to Extract
                                            <span class="badge badge-info ml-1">comma-separated</span>
                                        </label>
                                        <input type="text" wire:model="attribute_schema" class="form-control" id="attribute_schema"
                                            placeholder="e.g. name, price, artist, medium, size, color, style, availability, description">
                                        <small class="text-muted">
                                            The AI will extract exactly these fields from every crawled page and store them as structured data.
                                            <br>Examples by type:
                                            <em>Product</em> → name, price, color, size, material, availability |
                                            <em>Doctor</em> → name, specialty, fee, timings, languages, qualifications |
                                            <em>Test</em> → test name, price, preparation, duration, report time
                                        </small>
                                    </div>

                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="form-group">
                                                <label for="qdrant_data_type">
                                                    Save as Data Type
                                                </label>
                                                <select wire:model="qdrant_data_type" class="form-control" id="qdrant_data_type">
                                                    <option value="product">product</option>
                                                    <option value="service">service</option>
                                                    <option value="faq">faq</option>
                                                    <option value="info">info</option>
                                                    <option value="webpage">webpage (generic)</option>
                                                </select>
                                                <small class="text-muted">How this data appears in the vector DB and AI context</small>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="form-group">
                                                <label for="url_filter_pattern">URL Filter Pattern (optional)</label>
                                                <input type="text" wire:model="url_filter_pattern" class="form-control" id="url_filter_pattern"
                                                    placeholder="e.g. /products/ or */catalog/*">
                                                <small class="text-muted">Only crawl URLs matching this pattern. Use * as wildcard.</small>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="form-group">
                                        <label for="noise_selectors">
                                            Additional Noise to Strip
                                            <span class="badge badge-secondary ml-1">optional</span>
                                        </label>
                                        <input type="text" wire:model="noise_selectors_input" class="form-control" id="noise_selectors"
                                            placeholder="e.g. .related-products, #cart-sidebar, .review-section, .upsell">
                                        <small class="text-muted">
                                            CSS selectors to remove before AI reads the page. Already auto-stripped: nav, header, footer, scripts, ads, breadcrumbs, sidebars.
                                        </small>
                                    </div>

                                    <div class="form-group">
                                        <label for="extraction_prompt_override">Custom AI Instruction (advanced / optional)</label>
                                        <textarea wire:model="extraction_prompt_override" class="form-control" id="extraction_prompt_override" rows="2"
                                            placeholder="Leave blank for default. E.g.: This is an art marketplace – prices are in INR. If no price is listed, mark as 'on request'."></textarea>
                                        <small class="text-muted">Additional instructions given to the AI for extraction. Not required for most sites.</small>
                                    </div>
                                </div>
                            </div>

                            <div class="form-group mt-3">
                                <button type="submit" class="btn btn-success">
                                    <i class="fas fa-save"></i> Create Crawler
                                </button>
                                <button type="button" wire:click="$set('showCreateForm', false)" class="btn btn-secondary ml-2">
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
                                                <button wire:click="startCrawl({{ $crawler->id }})" 
                                                        class="btn btn-primary btn-sm"
                                                        wire:loading.attr="disabled"
                                                        title="Start batch crawl">
                                                    <i class="fas fa-play"></i>
                                                    <span wire:loading.remove wire:target="startCrawl({{ $crawler->id }})">Start Crawl</span>
                                                    <span wire:loading wire:target="startCrawl({{ $crawler->id }})">Queuing...</span>
                                                </button>
                                                <button wire:click="deleteCrawler({{ $crawler->id }})" 
                                                        class="btn btn-danger btn-sm ml-1"
                                                        onclick="return confirm('Delete this crawler config?')">
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

            {{-- ── Active & Recent Crawl Jobs (polled every 5s via wire:poll on root div) ─── --}}
            @if (count($activeCrawlJobs) > 0)
                <div class="card card-outline card-primary mt-3">
                    <div class="card-header">
                        <h3 class="card-title"><i class="fas fa-tasks"></i> Crawl Jobs</h3>
                        <div class="card-tools">
                            <span class="badge badge-primary">Auto-refreshes every 5s</span>
                        </div>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-sm mb-0">
                                <thead class="thead-light">
                                    <tr>
                                        <th>#</th>
                                        <th>Crawler</th>
                                        <th>Status</th>
                                        <th style="min-width:200px">Progress</th>
                                        <th>Processed</th>
                                        <th>Failed</th>
                                        <th>Currently Crawling</th>
                                        <th>Started</th>
                                        <th></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($activeCrawlJobs as $job)
                                        @php
                                            $pct    = $job['total_urls'] > 0
                                                        ? min(100, round(($job['processed_count'] + $job['failed_count']) / $job['total_urls'] * 100))
                                                        : 0;
                                            $barClass = match($job['status']) {
                                                'completed' => 'bg-success',
                                                'failed'    => 'bg-danger',
                                                'running'   => 'bg-warning progress-bar-striped progress-bar-animated',
                                                default     => 'bg-secondary',
                                            };
                                            $badgeClass = match($job['status']) {
                                                'completed' => 'badge-success',
                                                'failed'    => 'badge-danger',
                                                'running'   => 'badge-warning',
                                                'pending'   => 'badge-secondary',
                                                default     => 'badge-light',
                                            };
                                        @endphp
                                        <tr>
                                            <td><small class="text-muted">#{{ $job['id'] }}</small></td>
                                            <td><strong>{{ $job['crawler']['name'] ?? '—' }}</strong></td>
                                            <td>
                                                <span class="badge {{ $badgeClass }}">{{ ucfirst($job['status']) }}</span>
                                            </td>
                                            <td>
                                                <div class="progress" style="height:18px;">
                                                    <div class="progress-bar {{ $barClass }}"
                                                         role="progressbar"
                                                         style="width: {{ $pct }}%"
                                                         aria-valuenow="{{ $pct }}" aria-valuemin="0" aria-valuemax="100">
                                                        {{ $pct }}%
                                                    </div>
                                                </div>
                                                <small class="text-muted">{{ $job['processed_count'] + $job['failed_count'] }} / {{ $job['total_urls'] }} URLs</small>
                                            </td>
                                            <td><span class="text-success">{{ $job['processed_count'] }}</span></td>
                                            <td>
                                                @if ($job['failed_count'] > 0)
                                                    <span class="text-danger">{{ $job['failed_count'] }}</span>
                                                @else
                                                    <span class="text-muted">0</span>
                                                @endif
                                            </td>
                                            <td>
                                                @if (!empty($job['current_url']))
                                                    <small class="text-truncate d-block" style="max-width:220px;" title="{{ $job['current_url'] }}">
                                                        {{ $job['current_url'] }}
                                                    </small>
                                                @else
                                                    <span class="text-muted">—</span>
                                                @endif
                                            </td>
                                            <td>
                                                <small class="text-muted">
                                                    {{ $job['started_at'] ? \Carbon\Carbon::parse($job['started_at'])->diffForHumans() : 'pending' }}
                                                </small>
                                            </td>
                                            <td>
                                                @if (in_array($job['status'], ['pending', 'running']))
                                                    <button wire:click="cancelCrawlJob({{ $job['id'] }})"
                                                            class="btn btn-xs btn-danger"
                                                            onclick="return confirm('Cancel this crawl job?')">
                                                        <i class="fas fa-stop"></i> Cancel
                                                    </button>
                                                @endif
                                            </td>
                                        </tr>
                                        @if (!empty($job['crawl_log']))
                                            <tr class="table-light">
                                                <td colspan="9">
                                                    <details>
                                                        <summary class="text-muted small">View log</summary>
                                                        <pre class="small p-2 mb-0" style="max-height:120px;overflow-y:auto;background:#f8f9fa;">{{ implode("\n", array_slice($job['crawl_log'] ?? [], -20)) }}</pre>
                                                    </details>
                                                </td>
                                            </tr>
                                        @endif
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            @endif

            <!-- Info Section -->
            <div class="card card-outline card-secondary mt-3">
                <div class="card-header">
                    <h3 class="card-title">How Website Crawling Works</h3>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-4">
                            <div class="info-box">
                                <span class="info-box-icon bg-primary"><i class="fas fa-magic"></i></span>
                                <div class="info-box-content">
                                    <span class="info-box-text">AI Extraction</span>
                                    <span class="info-box-number">Smart</span>
                                    <span class="progress-description">Extracts defined attributes per page using LLM</span>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="info-box">
                                <span class="info-box-icon bg-success"><i class="fas fa-layer-group"></i></span>
                                <div class="info-box-content">
                                    <span class="info-box-text">Batch Processing</span>
                                    <span class="info-box-number">20/batch</span>
                                    <span class="progress-description">Runs in background — no timeouts</span>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="info-box">
                                <span class="info-box-icon bg-info"><i class="fas fa-database"></i></span>
                                <div class="info-box-content">
                                    <span class="info-box-text">Qdrant Sync</span>
                                    <span class="info-box-number">Live</span>
                                    <span class="progress-description">Each page stored immediately in vector DB</span>
                                </div>
                            </div>
                        </div>
                    </div>
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
