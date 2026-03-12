@extends('layouts.customer')

@section('title', 'Manage Content')

@section('content')
<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <h4 class="mb-1"><i class="fas fa-magic mr-2"></i> Manage Content</h4>
            <p class="text-muted mb-4">Add and manage the information your AI assistant uses to answer visitor questions.</p>
        </div>
    </div>

    {{-- AI Knowledge Base --}}
    <div class="row">
        <div class="col-12"><h6 class="text-uppercase text-muted mb-2 font-weight-bold">AI Knowledge Base</h6></div>

        <div class="col-md-6 col-lg-3 mb-4">
            <div class="card h-100 shadow-sm">
                <div class="card-body text-center py-4">
                    <i class="fas fa-concierge-bell fa-2x text-primary mb-3"></i>
                    <h5 class="card-title">Services</h5>
                    <p class="card-text text-muted small">Define your products, services, pricing and availability.</p>
                </div>
                <div class="card-footer bg-white border-top-0 text-center">
                    <a href="{{ route('customer.services') }}" class="btn btn-primary btn-sm btn-block">
                        <i class="fas fa-arrow-right mr-1"></i> Manage Services
                    </a>
                </div>
            </div>
        </div>

        <div class="col-md-6 col-lg-3 mb-4">
            <div class="card h-100 shadow-sm">
                <div class="card-body text-center py-4">
                    <i class="fas fa-question-circle fa-2x text-success mb-3"></i>
                    <h5 class="card-title">FAQs</h5>
                    <p class="card-text text-muted small">Common questions and answers your visitors frequently ask.</p>
                </div>
                <div class="card-footer bg-white border-top-0 text-center">
                    <a href="{{ route('customer.faqs') }}" class="btn btn-success btn-sm btn-block">
                        <i class="fas fa-arrow-right mr-1"></i> Manage FAQs
                    </a>
                </div>
            </div>
        </div>

        <div class="col-md-6 col-lg-3 mb-4">
            <div class="card h-100 shadow-sm">
                <div class="card-body text-center py-4">
                    <i class="fas fa-info-circle fa-2x text-info mb-3"></i>
                    <h5 class="card-title">General Info</h5>
                    <p class="card-text text-muted small">About your business, contact details, and general information.</p>
                </div>
                <div class="card-footer bg-white border-top-0 text-center">
                    <a href="{{ route('customer.general-info') }}" class="btn btn-info btn-sm btn-block">
                        <i class="fas fa-arrow-right mr-1"></i> Manage Info
                    </a>
                </div>
            </div>
        </div>

        <div class="col-md-6 col-lg-3 mb-4">
            <div class="card h-100 shadow-sm">
                <div class="card-body text-center py-4">
                    <i class="fas fa-tags fa-2x text-warning mb-3"></i>
                    <h5 class="card-title">Catalog &amp; Prices</h5>
                    <p class="card-text text-muted small">Product catalog with pricing for AI-assisted price queries.</p>
                </div>
                <div class="card-footer bg-white border-top-0 text-center">
                    <a href="{{ route('customer.catalog-prices') }}" class="btn btn-warning btn-sm btn-block">
                        <i class="fas fa-arrow-right mr-1"></i> Manage Catalog
                    </a>
                </div>
            </div>
        </div>
    </div>

    {{-- Data Import --}}
    <div class="row mt-2">
        <div class="col-12"><h6 class="text-uppercase text-muted mb-2 font-weight-bold">Data Import</h6></div>

        <div class="col-md-6 col-lg-3 mb-4">
            <div class="card h-100 shadow-sm">
                <div class="card-body text-center py-4">
                    <i class="fas fa-file-alt fa-2x text-secondary mb-3"></i>
                    <h5 class="card-title">Documents</h5>
                    <p class="card-text text-muted small">Upload PDFs, Word docs and text files for AI to learn from.</p>
                </div>
                <div class="card-footer bg-white border-top-0 text-center">
                    <a href="{{ route('customer.documents') }}" class="btn btn-secondary btn-sm btn-block">
                        <i class="fas fa-arrow-right mr-1"></i> Manage Documents
                    </a>
                </div>
            </div>
        </div>

        <div class="col-md-6 col-lg-3 mb-4">
            <div class="card h-100 shadow-sm">
                <div class="card-body text-center py-4">
                    <i class="fas fa-spider fa-2x text-danger mb-3"></i>
                    <h5 class="card-title">Website Crawler</h5>
                    <p class="card-text text-muted small">Automatically extract content from your website pages.</p>
                </div>
                <div class="card-footer bg-white border-top-0 text-center">
                    <a href="{{ route('customer.crawler') }}" class="btn btn-danger btn-sm btn-block">
                        <i class="fas fa-arrow-right mr-1"></i> Crawl Website
                    </a>
                </div>
            </div>
        </div>

        <div class="col-md-6 col-lg-3 mb-4">
            <div class="card h-100 shadow-sm">
                <div class="card-body text-center py-4">
                    <i class="fas fa-file-csv fa-2x text-success mb-3"></i>
                    <h5 class="card-title">CSV Import</h5>
                    <p class="card-text text-muted small">Bulk-import products, services or FAQ data from a CSV file.</p>
                </div>
                <div class="card-footer bg-white border-top-0 text-center">
                    <a href="{{ route('customer.csv-import') }}" class="btn btn-success btn-sm btn-block">
                        <i class="fas fa-arrow-right mr-1"></i> Import CSV
                    </a>
                </div>
            </div>
        </div>

        <div class="col-md-6 col-lg-3 mb-4">
            <div class="card h-100 shadow-sm">
                <div class="card-body text-center py-4">
                    <i class="fas fa-database fa-2x text-primary mb-3"></i>
                    <h5 class="card-title">Data Sources</h5>
                    <p class="card-text text-muted small">Connect external data sources for real-time AI responses.</p>
                </div>
                <div class="card-footer bg-white border-top-0 text-center">
                    <a href="{{ route('customer.data-sources') }}" class="btn btn-primary btn-sm btn-block">
                        <i class="fas fa-arrow-right mr-1"></i> Data Sources
                    </a>
                </div>
            </div>
        </div>
    </div>

    {{-- Automation --}}
    <div class="row mt-2">
        <div class="col-12"><h6 class="text-uppercase text-muted mb-2 font-weight-bold">Automation</h6></div>

        <div class="col-md-6 col-lg-3 mb-4">
            <div class="card h-100 shadow-sm">
                <div class="card-body text-center py-4">
                    <i class="fas fa-cogs fa-2x text-dark mb-3"></i>
                    <h5 class="card-title">Live Data Actions</h5>
                    <p class="card-text text-muted small">Configure automated actions triggered by visitor interactions.</p>
                </div>
                <div class="card-footer bg-white border-top-0 text-center">
                    <a href="{{ route('customer.action-manager') }}" class="btn btn-dark btn-sm btn-block">
                        <i class="fas fa-arrow-right mr-1"></i> Manage Actions
                    </a>
                </div>
            </div>
        </div>
    </div>

</div>
@endsection
