@extends('layouts.customer')

@section('title', 'Subscription Management')

@section('content')
<div class="content-header">
    <div class="container-fluid">
        <div class="row mb-2">
            <div class="col-sm-6">
                <h1 class="m-0">Subscription Management</h1>
            </div>
            <div class="col-sm-6">
                <ol class="breadcrumb float-sm-right">
                    @if(auth()->user()->organizations->count() > 0)
                        <li class="breadcrumb-item"><a href="{{ route('customer.dashboard') }}">Dashboard</a></li>
                    @else
                        <li class="breadcrumb-item"><a href="{{ route('home') }}">Home</a></li>
                    @endif
                    <li class="breadcrumb-item active">Subscription</li>
                </ol>
            </div>
        </div>
    </div>
</div>

<section class="content">
    <div class="container-fluid">
        @livewire('subscription-manager')
    </div>
</section>
@endsection
