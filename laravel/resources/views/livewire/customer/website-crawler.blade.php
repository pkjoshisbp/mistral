<div class="content-header">
    <div class="container-fluid">
        <div class="row mb-2">
            <div class="col-sm-6">
                <h1 class="m-0">Website Crawler</h1>
            </div>
            <div class="col-sm-6">
                <ol class="breadcrumb float-sm-right">
                    <li class="breadcrumb-item"><a href="{{ route('customer.dashboard') }}">Dashboard</a></li>
                    <li class="breadcrumb-item active">Website Crawler</li>
                </ol>
            </div>
        </div>
    </div>
</div>

<section class="content">
    <div class="container-fluid">
        @if (session()->has('message'))
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                {{ session('message') }}
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        @endif

        @if (session()->has('error'))
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                {{ session('error') }}
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        @endif

        <!-- Crawler Configuration -->
        <div class="card mb-4">
            <div class="card-header">
                <h3 class="card-title">
                    <i class="fas fa-spider mr-2"></i>
                    Configure Website Crawl
                </h3>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-8">
                        <div class="form-group">
                            <label for="url">Website URL</label>
                            <input type="url" 
                                   class="form-control" 
                                   id="url"
                                   wire:model="url" 
                                   placeholder="https://example.com"
                                   {{ $isCrawling ? 'disabled' : '' }}>
                            @error('url') 
                                <span class="text-danger">{{ $message }}</span> 
                            @enderror
                        </div>

                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="maxPages">Maximum Pages</label>
                                    <input type="number" 
                                           class="form-control" 
                                           id="maxPages"
                                           wire:model="maxPages" 
                                           min="1" 
                                           max="100"
                                           {{ $isCrawling ? 'disabled' : '' }}>
                                    @error('maxPages') 
                                        <span class="text-danger">{{ $message }}</span> 
                                    @enderror
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="crawlDepth">Crawl Depth</label>
                                    <select class="form-control" 
                                            id="crawlDepth"
                                            wire:model="crawlDepth"
                                            {{ $isCrawling ? 'disabled' : '' }}>
                                        <option value="1">1 Level (Homepage only)</option>
                                        <option value="2">2 Levels (Homepage + linked pages)</option>
                                        <option value="3">3 Levels (Deep crawl)</option>
                                        <option value="4">4 Levels (Deeper crawl)</option>
                                        <option value="5">5 Levels (Maximum depth)</option>
                                    </select>
                                    @error('crawlDepth') 
                                        <span class="text-danger">{{ $message }}</span> 
                                    @enderror
                                </div>
                            </div>
                        </div>

                        <div class="form-group">
                            @if($isCrawling)
                                <button type="button" class="btn btn-secondary" disabled>
                                    <i class="fas fa-spinner fa-spin"></i> Crawling...
                                </button>
                            @else
                                <button type="button" 
                                        class="btn btn-primary mr-2" 
                                        wire:click="startCrawl">
                                    <i class="fas fa-play"></i> Start Crawl
                                </button>
                                <button type="button" 
                                        class="btn btn-outline-secondary" 
                                        wire:click="resetCrawl">
                                    <i class="fas fa-redo"></i> Reset
                                </button>
                            @endif
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="alert alert-info">
                            <h6><i class="fas fa-info-circle"></i> How it works:</h6>
                            <ul class="mb-0">
                                <li>Enter your website URL</li>
                                <li>Set maximum pages to crawl</li>
                                <li>Choose crawl depth</li>
                                <li>Our AI will extract and learn from your content</li>
                            </ul>
                        </div>
                    </div>
                </div>

                @if($crawlStatus)
                    <div class="mt-3">
                        <div class="alert alert-info">
                            <strong>Status:</strong> {{ $crawlStatus }}
                        </div>
                    </div>
                @endif
            </div>
        </div>

        <!-- Crawl Results -->
        @if(count($crawledPages) > 0)
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">
                        <i class="fas fa-list mr-2"></i>
                        Crawl Results ({{ count($crawledPages) }} pages)
                    </h3>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-striped">
                            <thead>
                                <tr>
                                    <th>URL</th>
                                    <th>Page Title</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($crawledPages as $page)
                                    <tr>
                                        <td>
                                            <a href="{{ $page['url'] }}" 
                                               target="_blank" 
                                               class="text-primary">
                                                {{ $page['url'] }}
                                                <i class="fas fa-external-link-alt fa-sm ml-1"></i>
                                            </a>
                                        </td>
                                        <td>{{ $page['title'] }}</td>
                                        <td>
                                            @if($page['status'] === 'success')
                                                <span class="badge badge-success">
                                                    <i class="fas fa-check"></i> Success
                                                </span>
                                            @else
                                                <span class="badge badge-danger">
                                                    <i class="fas fa-times"></i> Failed
                                                </span>
                                            @endif
                                        </td>
                                        <td>
                                            <button type="button" 
                                                    class="btn btn-sm btn-outline-primary">
                                                <i class="fas fa-eye"></i> View Content
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
    </div>
</section>
