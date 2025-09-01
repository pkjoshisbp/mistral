@extends('layouts.customer')

@section('title', 'Google Sheets Integration')

@section('content')
<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h4 class="mb-0">Google Sheets Integration</h4>
                    <p class="text-muted mb-0">Connect your AI agent to Google Sheets for real-time data access</p>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-8">
                            <div class="alert alert-info">
                                <h5 class="alert-heading">Coming Soon!</h5>
                                <p class="mb-0">Google Sheets integration is currently under development. This feature will allow you to:</p>
                                <ul class="mt-2">
                                    <li>Connect your Google Sheets directly to your AI agent</li>
                                    <li>Enable real-time data queries from spreadsheets</li>
                                    <li>Automatically sync data for customer support responses</li>
                                    <li>Configure which sheets and ranges to include</li>
                                </ul>
                            </div>
                            
                            <div class="text-center py-4">
                                <i class="fab fa-google fa-4x text-success mb-3"></i>
                                <h5>Google Sheets Integration</h5>
                                <p class="text-muted">This feature is being developed and will be available soon.</p>
                            </div>
                        </div>
                        
                        <div class="col-md-4">
                            <div class="card border-primary">
                                <div class="card-header bg-primary text-white">
                                    <h6 class="mb-0">What You Can Do Now</h6>
                                </div>
                                <div class="card-body">
                                    <ul class="list-unstyled">
                                        <li class="mb-2">
                                            <i class="fas fa-check text-success me-2"></i>
                                            <a href="{{ route('customer.data-sources') }}" class="text-decoration-none">Upload CSV/Excel files</a>
                                        </li>
                                        <li class="mb-2">
                                            <i class="fas fa-check text-success me-2"></i>
                                            <a href="{{ route('customer.crawler') }}" class="text-decoration-none">Crawl website content</a>
                                        </li>
                                        <li class="mb-2">
                                            <i class="fas fa-clock text-warning me-2"></i>
                                            Google Sheets (Coming Soon)
                                        </li>
                                        <li class="mb-2">
                                            <i class="fas fa-clock text-warning me-2"></i>
                                            Database connections (Coming Soon)
                                        </li>
                                    </ul>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
