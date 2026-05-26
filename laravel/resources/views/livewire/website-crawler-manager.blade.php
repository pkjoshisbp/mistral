<div class="card" wire:poll.5s="pollProgress">
    <div class="card-header">
        <h3 class="card-title">Website Crawler</h3>
        <div class="card-tools">
            <small class="text-muted">Create reusable crawl templates from one sample page, then run them against similar pages.</small>
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

        <div class="row mb-4">
            <div class="col-md-6">
                <div class="form-group mb-0">
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
            <div class="mb-4">
                <button wire:click="$toggle('showCreateForm')" class="btn btn-primary">
                    <i class="fas fa-plus"></i> Add Website Crawler
                </button>
            </div>

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
                                        <input type="text" wire:model="name" class="form-control" id="name" placeholder="e.g. IndianArtZone Products">
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

                            <div class="card card-outline card-info mb-4">
                                <div class="card-header">
                                    <h4 class="card-title"><i class="fas fa-flask"></i> Sample Page Analysis</h4>
                                </div>
                                <div class="card-body">
                                    <div class="row align-items-end">
                                        <div class="col-md-8">
                                            <div class="form-group mb-md-0">
                                                <label for="sample_page_url">Sample Page URL</label>
                                                <input type="url" wire:model="sample_page_url" class="form-control" id="sample_page_url" placeholder="https://example.com/products/sample-item">
                                                @error('sample_page_url') <span class="text-danger">{{ $message }}</span> @enderror
                                                <small class="text-muted">Use one representative page first. The system will suggest a crawl template from it.</small>
                                            </div>
                                        </div>
                                        <div class="col-md-4 text-md-right mt-3 mt-md-0">
                                            <button type="button" wire:click="analyzeSamplePage" class="btn btn-info" wire:loading.attr="disabled" wire:target="analyzeSamplePage">
                                                <span wire:loading.remove wire:target="analyzeSamplePage"><i class="fas fa-magic"></i> Analyze Sample</span>
                                                <span wire:loading wire:target="analyzeSamplePage"><i class="fas fa-spinner fa-spin"></i> Analyzing...</span>
                                            </button>
                                        </div>
                                    </div>

                                    @if (!empty($sampleAnalysis))
                                        <div class="row mt-4">
                                            <div class="col-lg-6">
                                                <div class="border rounded p-3 h-100 bg-light">
                                                    <h5 class="mb-3">Parsed Page Signals</h5>
                                                    <p class="mb-2"><strong>Title:</strong> {{ $sampleAnalysis['title'] ?? 'N/A' }}</p>
                                                    @if (!empty($sampleAnalysis['meta_description']))
                                                        <p class="mb-2"><strong>Meta Description:</strong> {{ $sampleAnalysis['meta_description'] }}</p>
                                                    @endif
                                                    <p class="mb-2"><strong>Suggested URL Pattern:</strong> {{ $sampleAnalysis['suggested_template']['url_filter_pattern'] ?? 'N/A' }}</p>
                                                    <p class="mb-2"><strong>Suggested Type:</strong>
                                                        <span class="badge badge-info">{{ $sampleAnalysis['suggested_template']['page_type'] ?? 'generic' }}</span>
                                                        <span class="badge badge-secondary">{{ $sampleAnalysis['suggested_template']['qdrant_data_type'] ?? 'webpage' }}</span>
                                                    </p>

                                                    <h6 class="mt-3">Detected Headings</h6>
                                                    @if (!empty($sampleAnalysis['headings']))
                                                        <div>
                                                            @foreach ($sampleAnalysis['headings'] as $heading)
                                                                <span class="badge badge-light border mr-1 mb-1">{{ $heading }}</span>
                                                            @endforeach
                                                        </div>
                                                    @else
                                                        <p class="text-muted mb-0">No headings detected.</p>
                                                    @endif
                                                </div>
                                            </div>
                                            <div class="col-lg-6 mt-3 mt-lg-0">
                                                <div class="border rounded p-3 h-100">
                                                    <div class="d-flex justify-content-between align-items-center mb-3">
                                                        <h5 class="mb-0">Suggested Template Fields</h5>
                                                        <button type="button" wire:click="applySuggestedTemplate" class="btn btn-sm btn-outline-primary">
                                                            <i class="fas fa-check"></i> Apply Suggested Template
                                                        </button>
                                                    </div>

                                                    @if (!empty($sampleAnalysis['suggested_template']['attribute_schema']))
                                                        <div class="mb-3">
                                                            @foreach ($sampleAnalysis['suggested_template']['attribute_schema'] as $index => $attribute)
                                                                <div class="custom-control custom-checkbox mb-2">
                                                                    <input
                                                                        type="checkbox"
                                                                        class="custom-control-input"
                                                                        id="suggested_attribute_{{ $index }}"
                                                                        value="{{ $attribute }}"
                                                                        wire:model="selectedSuggestedAttributes"
                                                                    >
                                                                    <label class="custom-control-label" for="suggested_attribute_{{ $index }}">{{ $attribute }}</label>
                                                                </div>
                                                            @endforeach
                                                        </div>
                                                    @else
                                                        <p class="text-muted">No fields suggested. You can still configure the crawler manually below.</p>
                                                    @endif

                                                    @if (!empty($sampleAnalysis['suggested_template']['summary']))
                                                        <div class="alert alert-light border mb-0">
                                                            <strong>Suggestion Summary:</strong> {{ $sampleAnalysis['suggested_template']['summary'] }}
                                                        </div>
                                                    @endif
                                                </div>
                                            </div>
                                        </div>
                                    @endif
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-12">
                                    <div class="form-group">
                                        <label>Crawl Method</label>
                                        <div class="form-check">
                                            <input wire:model.live="crawl_type" class="form-check-input" type="radio" value="sitemap" id="crawl_sitemap">
                                            <label class="form-check-label" for="crawl_sitemap">
                                                <strong>Sitemap</strong> - Use the website sitemap
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
                                            <button type="button" wire:click="uploadSitemap" class="btn btn-sm btn-success mt-2">
                                                <i class="fas fa-upload"></i> Process Sitemap
                                            </button>
                                        @endif
                                    </div>
                                </div>
                            @endif

                            @if ($crawl_type === 'specific_pages')
                                <div class="form-group">
                                    <label for="specific_pages">Specific URLs (one per line)</label>
                                    <textarea wire:model="specific_pages" class="form-control" id="specific_pages" rows="6" placeholder="https://example.com/page-1&#10;https://example.com/page-2"></textarea>
                                    <small class="text-muted">Use this when you want to crawl a fixed page list or a manually curated set from the sample stage.</small>
                                </div>
                            @endif

                            @if ($crawl_type === 'full_crawl')
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label for="max_depth">Max Depth</label>
                                            <select wire:model="max_depth" class="form-control" id="max_depth">
                                                <option value="1">1 level</option>
                                                <option value="2">2 levels</option>
                                                <option value="3">3 levels</option>
                                                <option value="4">4 levels</option>
                                                <option value="5">5 levels</option>
                                            </select>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label for="max_pages">Max Pages</label>
                                            <select wire:model="max_pages" class="form-control" id="max_pages">
                                                <option value="25">25 pages</option>
                                                <option value="50">50 pages</option>
                                                <option value="100">100 pages</option>
                                                <option value="250">250 pages</option>
                                                <option value="500">500 pages</option>
                                            </select>
                                        </div>
                                    </div>
                                </div>
                            @endif

                            <div class="card card-outline card-secondary">
                                <div class="card-header">
                                    <h4 class="card-title">Advanced Crawl Options</h4>
                                </div>
                                <div class="card-body">
                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="form-group">
                                                <label for="include_patterns">Include Patterns (one per line)</label>
                                                <textarea wire:model="include_patterns" class="form-control" id="include_patterns" rows="3" placeholder="/products/&#10;/collections/"></textarea>
                                                <small class="text-muted">Only crawl URLs containing these patterns.</small>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="form-group">
                                                <label for="exclude_patterns">Exclude Patterns (one per line)</label>
                                                <textarea wire:model="exclude_patterns" class="form-control" id="exclude_patterns" rows="3" placeholder="/cart/&#10;/login/"></textarea>
                                                <small class="text-muted">Skip URLs matching these patterns.</small>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="form-group mb-0">
                                        <label for="description">Description</label>
                                        <textarea wire:model="description" class="form-control" id="description" rows="2" placeholder="Notes about this crawler template"></textarea>
                                    </div>
                                </div>
                            </div>

                            <div class="card mt-3 border-info">
                                <div class="card-header bg-info text-white">
                                    <strong><i class="fas fa-magic"></i> Structured Extraction Template</strong>
                                    <small class="ml-2 font-weight-light">This is what gets reused across similar pages after you finalize the sample.</small>
                                </div>
                                <div class="card-body">
                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="form-group">
                                                <label for="page_type">Page / Entity Type</label>
                                                <select wire:model="page_type" class="form-control" id="page_type">
                                                    <option value="product">Product</option>
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
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="form-group">
                                                <label for="extraction_method">Extraction Method</label>
                                                <select wire:model="extraction_method" class="form-control" id="extraction_method">
                                                    <option value="llm">AI / LLM</option>
                                                    <option value="structured">Structured only</option>
                                                </select>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="form-group">
                                        <label for="attribute_schema">Attributes to Extract</label>
                                        <input type="text" wire:model="attribute_schema" class="form-control" id="attribute_schema" placeholder="name, price, artist, medium, size, availability, description">
                                        <small class="text-muted">Edit the suggested fields, keep only the fields that should repeat on similar pages, then preview the sample again.</small>
                                    </div>

                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="form-group">
                                                <label for="qdrant_data_type">Save as Data Type</label>
                                                <select wire:model="qdrant_data_type" class="form-control" id="qdrant_data_type">
                                                    <option value="product">product</option>
                                                    <option value="service">service</option>
                                                    <option value="faq">faq</option>
                                                    <option value="info">info</option>
                                                    <option value="webpage">webpage</option>
                                                </select>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="form-group">
                                                <label for="url_filter_pattern">URL Filter Pattern</label>
                                                <input type="text" wire:model="url_filter_pattern" class="form-control" id="url_filter_pattern" placeholder="e.g. /products/ or */paintings/*">
                                                <small class="text-muted">Use the sample analysis suggestion, then adjust it if needed.</small>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="form-group">
                                        <label for="noise_selectors">Additional Noise to Strip</label>
                                        <input type="text" wire:model="noise_selectors_input" class="form-control" id="noise_selectors" placeholder=".related-products, .breadcrumbs, .cta-banner">
                                    </div>

                                    <div class="form-group mb-0">
                                        <label for="extraction_prompt_override">Custom AI Instruction</label>
                                        <textarea wire:model="extraction_prompt_override" class="form-control" id="extraction_prompt_override" rows="2" placeholder="Optional. Example: This is an art marketplace. Prices are in INR."></textarea>
                                    </div>
                                </div>
                            </div>

                            <div class="card card-outline card-primary mt-3">
                                <div class="card-header d-flex justify-content-between align-items-center">
                                    <h4 class="card-title mb-0">Preview Current Template on the Sample Page</h4>
                                    <button type="button" wire:click="previewSampleExtraction" class="btn btn-sm btn-primary" wire:loading.attr="disabled" wire:target="previewSampleExtraction">
                                        <span wire:loading.remove wire:target="previewSampleExtraction"><i class="fas fa-vial"></i> Preview Extraction</span>
                                        <span wire:loading wire:target="previewSampleExtraction"><i class="fas fa-spinner fa-spin"></i> Previewing...</span>
                                    </button>
                                </div>
                                <div class="card-body">
                                    @if (!empty($sampleExtractionPreview))
                                        <div class="row">
                                            <div class="col-lg-6">
                                                <h6>Structured Output Preview</h6>
                                                <div class="table-responsive">
                                                    <table class="table table-sm table-bordered">
                                                        <tbody>
                                                            @forelse (($sampleExtractionPreview['extracted'] ?? []) as $field => $value)
                                                                <tr>
                                                                    <th style="width: 35%;">{{ $field }}</th>
                                                                    <td>{{ is_array($value) ? implode(', ', $value) : ($value ?: 'null') }}</td>
                                                                </tr>
                                                            @empty
                                                                <tr>
                                                                    <td colspan="2" class="text-muted">No structured output returned.</td>
                                                                </tr>
                                                            @endforelse
                                                        </tbody>
                                                    </table>
                                                </div>
                                            </div>
                                            <div class="col-lg-6 mt-3 mt-lg-0">
                                                <h6>Source Content Excerpt</h6>
                                                <div class="border rounded p-3 bg-light" style="max-height: 260px; overflow-y: auto; white-space: pre-wrap;">{{ $sampleExtractionPreview['content_excerpt'] ?? 'No excerpt available.' }}</div>
                                            </div>
                                        </div>
                                    @else
                                        <p class="text-muted mb-0">Run the sample analysis, adjust the template fields, then use preview to verify the extracted structure before saving the crawler.</p>
                                    @endif
                                </div>
                            </div>

                            <div class="form-group mt-3 mb-0">
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

            @if ($crawlers->count() > 0)
                <div class="card card-outline card-info">
                    <div class="card-header">
                        <h3 class="card-title">Website Crawlers</h3>
                        <div class="card-tools">
                            <span class="badge badge-info">{{ $crawlers->count() }} crawlers</span>
                        </div>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-striped mb-0">
                                <thead>
                                    <tr>
                                        <th>Name</th>
                                        <th>Website</th>
                                        <th>Entity Type</th>
                                        <th>Attributes</th>
                                        <th>Last Crawled</th>
                                        <th>Stats</th>
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
                                                @if ($crawler->url_filter_pattern)
                                                    <br><small class="text-muted">Filter: {{ $crawler->url_filter_pattern }}</small>
                                                @endif
                                            </td>
                                            <td>
                                                <span class="badge badge-info">{{ $crawler->page_type ?? 'general' }}</span>
                                                <br><small class="text-muted">{{ $crawler->qdrant_data_type ?? 'webpage' }}</small>
                                            </td>
                                            <td>
                                                @if ($crawler->attribute_schema)
                                                    <small>{{ $crawler->attribute_schema }}</small>
                                                @else
                                                    <span class="text-muted">Full text</span>
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
                                                <button wire:click="startCrawl({{ $crawler->id }})" class="btn btn-primary btn-sm" wire:loading.attr="disabled" title="Start crawl">
                                                    <i class="fas fa-play"></i>
                                                </button>
                                                <button wire:click="deleteCrawler({{ $crawler->id }})" class="btn btn-danger btn-sm ml-1" onclick="return confirm('Delete this crawler template?')">
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
                                        <th style="min-width: 200px;">Progress</th>
                                        <th>Processed</th>
                                        <th>Failed</th>
                                        <th>Current URL</th>
                                        <th>Started</th>
                                        <th></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($activeCrawlJobs as $job)
                                        @php
                                            $pct = $job['total_urls'] > 0
                                                ? min(100, round(($job['processed_count'] + $job['failed_count']) / $job['total_urls'] * 100))
                                                : 0;
                                            $barClass = match($job['status']) {
                                                'completed' => 'bg-success',
                                                'failed' => 'bg-danger',
                                                'running' => 'bg-warning progress-bar-striped progress-bar-animated',
                                                default => 'bg-secondary',
                                            };
                                            $badgeClass = match($job['status']) {
                                                'completed' => 'badge-success',
                                                'failed' => 'badge-danger',
                                                'running' => 'badge-warning',
                                                'pending' => 'badge-secondary',
                                                default => 'badge-light',
                                            };
                                        @endphp
                                        <tr>
                                            <td><small class="text-muted">#{{ $job['id'] }}</small></td>
                                            <td><strong>{{ $job['crawler']['name'] ?? '—' }}</strong></td>
                                            <td><span class="badge {{ $badgeClass }}">{{ ucfirst($job['status']) }}</span></td>
                                            <td>
                                                <div class="progress" style="height: 18px;">
                                                    <div class="progress-bar {{ $barClass }}" role="progressbar" style="width: {{ $pct }}%">{{ $pct }}%</div>
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
                                                    <small class="text-truncate d-block" style="max-width: 220px;" title="{{ $job['current_url'] }}">{{ $job['current_url'] }}</small>
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
                                                    <button wire:click="cancelCrawlJob({{ $job['id'] }})" class="btn btn-xs btn-danger" onclick="return confirm('Cancel this crawl job?')">
                                                        <i class="fas fa-stop"></i>
                                                    </button>
                                                @endif
                                            </td>
                                        </tr>
                                        @if (!empty($job['crawl_log']))
                                            <tr class="table-light">
                                                <td colspan="9">
                                                    <details>
                                                        <summary class="text-muted small">View log</summary>
                                                        <pre class="small p-2 mb-0" style="max-height: 120px; overflow-y: auto; background: #f8f9fa;">{{ implode("\n", array_slice($job['crawl_log'] ?? [], -20)) }}</pre>
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

            <div class="card card-outline card-secondary mt-3">
                <div class="card-header">
                    <h3 class="card-title">Recommended Workflow</h3>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-4">
                            <div class="info-box">
                                <span class="info-box-icon bg-info"><i class="fas fa-vial"></i></span>
                                <div class="info-box-content">
                                    <span class="info-box-text">1. Analyze Sample</span>
                                    <span class="progress-description">Start from one representative product, service, FAQ, or info page.</span>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="info-box">
                                <span class="info-box-icon bg-primary"><i class="fas fa-sliders-h"></i></span>
                                <div class="info-box-content">
                                    <span class="info-box-text">2. Finalize Template</span>
                                    <span class="progress-description">Keep only the fields and URL pattern that should repeat across similar pages.</span>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="info-box">
                                <span class="info-box-icon bg-success"><i class="fas fa-database"></i></span>
                                <div class="info-box-content">
                                    <span class="info-box-text">3. Crawl at Scale</span>
                                    <span class="progress-description">Run the crawler. Each page is stored in the database and synced to Qdrant.</span>
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