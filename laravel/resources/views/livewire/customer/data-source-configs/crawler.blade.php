<!-- Website Crawler Configuration -->
<div class="row">
    <div class="col-md-6">
        <div class="form-group">
            <label>Website URL</label>
            <input type="url" class="form-control" wire:model="config.url" placeholder="https://example.com">
            @error('config.url') <span class="text-danger">{{ $message }}</span> @enderror
        </div>
    </div>
    <div class="col-md-6">
        <div class="form-group">
            <label>Max Pages</label>
            <input type="number" class="form-control" wire:model="config.max_pages" placeholder="100" min="1" max="1000">
            @error('config.max_pages') <span class="text-danger">{{ $message }}</span> @enderror
        </div>
    </div>
</div>

<div class="row">
    <div class="col-md-6">
        <div class="form-group">
            <label>Crawl Depth</label>
            <select class="form-control" wire:model="config.depth">
                <option value="1">1 level (current page only)</option>
                <option value="2">2 levels</option>
                <option value="3">3 levels</option>
                <option value="4">4 levels</option>
                <option value="5">5 levels (deep crawl)</option>
            </select>
        </div>
    </div>
    <div class="col-md-6">
        <div class="form-group">
            <label>Update Frequency</label>
            <select class="form-control" wire:model="config.frequency">
                <option value="manual">Manual</option>
                <option value="daily">Daily</option>
                <option value="weekly">Weekly</option>
                <option value="monthly">Monthly</option>
            </select>
        </div>
    </div>
</div>

<div class="form-group">
    <label>Include URL Patterns (one per line)</label>
    <textarea class="form-control" wire:model="config.include_patterns" rows="3" 
              placeholder="/products/*
/services/*
/blog/*"></textarea>
    <small class="form-text text-muted">Optional: Specify URL patterns to include. Leave empty to crawl all pages.</small>
</div>

<div class="form-group">
    <label>Exclude URL Patterns (one per line)</label>
    <textarea class="form-control" wire:model="config.exclude_patterns" rows="3" 
              placeholder="/admin/*
/login*
*.pdf"></textarea>
    <small class="form-text text-muted">Optional: Specify URL patterns to exclude from crawling.</small>
</div>

<div class="row">
    <div class="col-md-12">
        <div class="form-group">
            <div class="form-check">
                <input class="form-check-input" type="checkbox" wire:model="config.respect_robots_txt" id="respectRobots">
                <label class="form-check-label" for="respectRobots">
                    Respect robots.txt
                </label>
            </div>
            <div class="form-check">
                <input class="form-check-input" type="checkbox" wire:model="config.extract_images" id="extractImages">
                <label class="form-check-label" for="extractImages">
                    Extract image descriptions
                </label>
            </div>
        </div>
    </div>
</div>
