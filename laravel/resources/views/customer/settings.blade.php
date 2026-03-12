@extends('layouts.customer')

@section('title', 'Settings')

@section('content')
<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <h4 class="mb-3"><i class="fas fa-cog mr-2"></i> Settings</h4>
        </div>
    </div>

    <div class="row">

        <!-- Organization -->
        <div class="col-md-6 col-lg-4 mb-4">
            <div class="card h-100">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0"><i class="fas fa-building mr-2"></i> Organization</h5>
                </div>
                <div class="card-body">
                    <p class="text-muted">Manage your organization profile, name, website and branding details.</p>
                </div>
                <div class="card-footer">
                    <a href="{{ route('customer.organization') }}" class="btn btn-primary btn-block">
                        <i class="fas fa-arrow-right mr-1"></i> Manage Organization
                    </a>
                </div>
            </div>
        </div>

        <!-- Widget Settings -->
        <div class="col-md-6 col-lg-4 mb-4">
            <div class="card h-100">
                <div class="card-header bg-info text-white">
                    <h5 class="mb-0"><i class="fas fa-code mr-2"></i> Widget Settings</h5>
                </div>
                <div class="card-body">
                    <p class="text-muted">Customize your chat widget appearance, colors, and embed code for your website.</p>
                </div>
                <div class="card-footer">
                    <a href="{{ route('customer.widget') }}" class="btn btn-info btn-block">
                        <i class="fas fa-arrow-right mr-1"></i> Widget Settings
                    </a>
                </div>
            </div>
        </div>

        <!-- Integration Settings -->
        <div class="col-md-6 col-lg-4 mb-4">
            <div class="card h-100">
                <div class="card-header bg-warning text-white">
                    <h5 class="mb-0"><i class="fas fa-sliders-h mr-2"></i> Integration Settings</h5>
                </div>
                <div class="card-body">
                    <p class="text-muted">Configure AI model preferences, response style, and data source integration options.</p>
                </div>
                <div class="card-footer">
                    <a href="{{ route('customer.integration-settings') }}" class="btn btn-warning btn-block">
                        <i class="fas fa-arrow-right mr-1"></i> Integration Settings
                    </a>
                </div>
            </div>
        </div>

        <!-- WhatsApp Integration -->
        <div class="col-md-6 col-lg-4 mb-4">
            <div class="card h-100">
                <div class="card-header bg-success text-white">
                    <h5 class="mb-0"><i class="fab fa-whatsapp mr-2"></i> WhatsApp Integration</h5>
                </div>
                <div class="card-body">
                    <p class="text-muted">Connect your WhatsApp Business account to handle customer queries via WhatsApp.</p>
                </div>
                <div class="card-footer">
                    <a href="{{ route('customer.whatsapp') }}" class="btn btn-success btn-block">
                        <i class="fas fa-arrow-right mr-1"></i> WhatsApp Settings
                    </a>
                </div>
            </div>
        </div>

        <!-- API Integration -->
        <div class="col-md-6 col-lg-4 mb-4">
            <div class="card h-100">
                <div class="card-header bg-secondary text-white">
                    <h5 class="mb-0"><i class="fas fa-plug mr-2"></i> API Integration</h5>
                </div>
                <div class="card-body">
                    <p class="text-muted">Get your API keys and configure programmatic access to the AI Chat platform.</p>
                </div>
                <div class="card-footer">
                    <a href="{{ route('customer.api-integration') }}" class="btn btn-secondary btn-block">
                        <i class="fas fa-arrow-right mr-1"></i> API Settings
                    </a>
                </div>
            </div>
        </div>

        <!-- Profile -->
        <div class="col-md-6 col-lg-4 mb-4">
            <div class="card h-100">
                <div class="card-header bg-dark text-white">
                    <h5 class="mb-0"><i class="fas fa-user mr-2"></i> My Profile</h5>
                </div>
                <div class="card-body">
                    <p class="text-muted">Update your name, email address, and change your account password.</p>
                </div>
                <div class="card-footer">
                    <a href="{{ route('customer.profile.edit') }}" class="btn btn-dark btn-block">
                        <i class="fas fa-arrow-right mr-1"></i> Edit Profile
                    </a>
                </div>
            </div>
        </div>

    </div>
</div>
@endsection
