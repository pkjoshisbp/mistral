@extends('layouts.public')

@section('title', ($refund->title ?? 'Refund Policy') . ' - AI Chat Support')

@section('content')
<!-- Content -->
<div class="container my-5">
    <div class="row justify-content-center">
        <div class="col-lg-8">
            @if($refund)
                <h1>{{ $refund->title }}</h1>
                <hr>
                <div class="content">
                    {!! nl2br(e($refund->content)) !!}
                </div>
                <hr>
                <p class="text-muted">
                    <small>Last updated: {{ $refund->updated_at->format('F j, Y') }}</small>
                </p>
            @else
                <div class="text-center">
                    <h1>Refund Policy</h1>
                    <p class="lead">Refund policy has not been configured yet.</p>
                    <a href="{{ route('home') }}" class="btn btn-primary">Go Back Home</a>
                </div>
            @endif
        </div>
    </div>
</div>
@endsection
