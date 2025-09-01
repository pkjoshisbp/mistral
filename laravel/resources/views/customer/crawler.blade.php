@extends('layouts.customer')

@section('title', 'Website Crawler')

@section('content')
<div class="content-wrapper">
    <section class="content-header">
        <div class="container-fluid">
            <div class="row mb-2">
                <div class="col-sm-6">
                    <h1>Website Crawler</h1>
                </div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-right">
                        <li class="breadcrumb-item"><a href="{{ route('customer.dashboard') }}">Dashboard</a></li>
                        <li class="breadcrumb-item active">Website Crawler</li>
                    </ol>
                </div>
            </div>
        </div>
    </section>

    <section class="content">
        <div class="container-fluid">
            <div class="row">
                <div class="col-12">
                    <div class="card">
                        <div class="card-header">
                            <h3 class="card-title">Crawl Your Website</h3>
                        </div>
                        <div class="card-body">
                            <p>Use this tool to automatically crawl and index content from your website for the AI knowledge base.</p>
                            
                            <form class="row g-3">
                                <div class="col-md-8">
                                    <label for="websiteUrl" class="form-label">Website URL</label>
                                    <input type="url" class="form-control" id="websiteUrl" placeholder="https://example.com">
                                </div>
                                <div class="col-md-4">
                                    <label for="maxPages" class="form-label">Max Pages</label>
                                    <input type="number" class="form-control" id="maxPages" value="10" min="1" max="100">
                                </div>
                                <div class="col-12">
                                    <button type="submit" class="btn btn-primary">Start Crawling</button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="row mt-4">
                <div class="col-12">
                    <div class="card">
                        <div class="card-header">
                            <h3 class="card-title">Crawl History</h3>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-striped">
                                    <thead>
                                        <tr>
                                            <th>URL</th>
                                            <th>Pages Found</th>
                                            <th>Status</th>
                                            <th>Date</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr>
                                            <td colspan="5" class="text-center">No crawl history yet</td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>
</div>
@endsection
