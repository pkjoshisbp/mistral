<div wire:poll.5s="pollProgress">
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

        @if (!$organization)
            <div class="alert alert-warning">No organization linked to your account. Contact admin.</div>
        @else

        {{-- ── Configured Crawlers ──────────────────────────────────────────────── --}}
        <div class="card card-outline card-primary">
            <div class="card-header">
                <h3 class="card-title"><i class="fas fa-spider"></i> Website Crawlers</h3>
                <div class="card-tools">
                    <small class="text-muted">Contact admin to add or modify crawlers</small>
                </div>
            </div>
            <div class="card-body p-0">
                @if ($crawlers->isEmpty())
                    <div class="text-center text-muted py-4">No crawlers configured yet. Contact your admin.</div>
                @else
                    <div class="table-responsive">
                    <table class="table table-sm mb-0">
                        <thead class="thead-light">
                            <tr>
                                <th>Name</th>
                                <th>Website</th>
                                <th>Type</th>
                                <th>Attributes</th>
                                <th>Last Crawled</th>
                                <th>Stats</th>
                                <th></th>
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
                                        <span class="badge badge-info">{{ $crawler->page_type ?? 'generic' }}</span>
                                    </td>
                                    <td>
                                        @if ($crawler->attribute_schema)
                                            <small class="text-muted">{{ $crawler->attribute_schema }}</small>
                                        @else
                                            <small class="text-muted">Full text</small>
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
                                                <span class="text-success">✓ {{ $crawler->crawl_stats['pages_processed'] ?? 0 }}</span>
                                                / {{ $crawler->crawl_stats['total_urls'] ?? '?' }}
                                            </small>
                                        @else
                                            <small class="text-muted">—</small>
                                        @endif
                                    </td>
                                    <td>
                                        <button wire:click="startCrawl({{ $crawler->id }})"
                                                class="btn btn-sm btn-primary"
                                                wire:loading.attr="disabled">
                                            <span wire:loading.remove wire:target="startCrawl({{ $crawler->id }})">
                                                <i class="fas fa-play"></i> Start Crawl
                                            </span>
                                            <span wire:loading wire:target="startCrawl({{ $crawler->id }})">
                                                <i class="fas fa-spinner fa-spin"></i> Queuing...
                                            </span>
                                        </button>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                    </div>
                @endif
            </div>
        </div>

        {{-- ── Recent Crawl Jobs — live progress via wire:poll.5s ───────────────── --}}
        @if ($crawlJobs->isNotEmpty())
            <div class="card card-outline card-info mt-3">
                <div class="card-header">
                    <h3 class="card-title"><i class="fas fa-tasks"></i> Recent Crawl Jobs</h3>
                    <div class="card-tools">
                        <span class="badge badge-info"><i class="fas fa-sync fa-spin fa-xs"></i> Live — updates every 5s</span>
                    </div>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                    <table class="table table-sm mb-0">
                                <th>Crawler</th>
                                <th>Status</th>
                                <th style="min-width:180px">Progress</th>
                                <th>✓ Done</th>
                                <th>✗ Failed</th>
                                <th>Currently at</th>
                                <th>Started</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($crawlJobs as $job)
                                @php
                                    $pct = $job->total_urls > 0
                                        ? min(100, round(($job->processed_count + $job->failed_count) / $job->total_urls * 100))
                                        : 0;
                                    $barClass = match($job->status) {
                                        'completed' => 'bg-success',
                                        'failed'    => 'bg-danger',
                                        'running'   => 'bg-warning progress-bar-striped progress-bar-animated',
                                        default     => 'bg-secondary',
                                    };
                                    $badgeClass = match($job->status) {
                                        'completed' => 'badge-success',
                                        'failed'    => 'badge-danger',
                                        'running'   => 'badge-warning',
                                        default     => 'badge-secondary',
                                    };
                                @endphp
                                <tr>
                                    <td><small class="text-muted">#{{ $job->id }}</small></td>
                                    <td>{{ $job->crawler->name ?? '—' }}</td>
                                    <td><span class="badge {{ $badgeClass }}">{{ ucfirst($job->status) }}</span></td>
                                    <td>
                                        <div class="progress mb-1" style="height:16px;">
                                            <div class="progress-bar {{ $barClass }}"
                                                 style="width:{{ $pct }}%"
                                                 role="progressbar">{{ $pct }}%</div>
                                        </div>
                                        <small class="text-muted">{{ $job->processed_count + $job->failed_count }} / {{ $job->total_urls }}</small>
                                    </td>
                                    <td><span class="text-success">{{ $job->processed_count }}</span></td>
                                    <td>
                                        @if ($job->failed_count > 0)
                                            <span class="text-danger">{{ $job->failed_count }}</span>
                                        @else <span class="text-muted">0</span> @endif
                                    </td>
                                    <td>
                                        @if ($job->current_url)
                                            <small class="d-block text-truncate text-muted" style="max-width:180px;" title="{{ $job->current_url }}">
                                                {{ $job->current_url }}
                                            </small>
                                        @else <span class="text-muted">—</span> @endif
                                    </td>
                                    <td>
                                        <small class="text-muted">{{ $job->started_at ? $job->started_at->diffForHumans() : 'pending' }}</small>
                                    </td>
                                    <td>
                                        @if ($job->isRunning())
                                            <button wire:click="cancelCrawlJob({{ $job->id }})"
                                                    class="btn btn-xs btn-danger"
                                                    onclick="return confirm('Cancel this crawl?')">
                                                <i class="fas fa-stop"></i>
                                            </button>
                                        @endif
                                    </td>
                                </tr>
                                @if ($job->crawl_log && $job->isRunning())
                                    <tr class="table-light">
                                        <td colspan="9">
                                            <details open>
                                                <summary class="small text-muted">Activity log</summary>
                                                <pre class="small p-2 mb-0" style="max-height:100px;overflow-y:auto;background:#f8f9fa;">{{ implode("\n", array_slice($job->crawl_log ?? [], -15)) }}</pre>
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

        @endif {{-- /organization --}}
    </div>
</section>
</div>
