@extends('layouts.app')

@section('title', 'Setup Organization')

@section('content')
<div class="container">
    <div class="row justify-content-center">
        <div class="col-md-8">
            <div class="card">
                <div class="card-header">
                    <h4 class="mb-0">
                        <i class="fas fa-building"></i>
                        Setup Your Organization
                    </h4>
                </div>
                <div class="card-body">
                    @if (session('error'))
                        <div class="alert alert-warning">
                            {{ session('error') }}
                        </div>
                    @endif

                    <div class="mb-4">
                        <p class="text-muted">
                            To access the customer portal, you need to be associated with an organization. 
                            You can either create a new organization or request access to an existing one.
                        </p>
                    </div>

                    @livewire('customer.organization-setup')
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
