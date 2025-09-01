@extends('layouts.public')

@section('title', ($terms->title ?? 'Terms and Conditions') . ' - AI Chat Support')

@section('content')
<!-- Content -->
<div class="container my-5">
    <div class="row justify-content-center">
        <div class="col-lg-8">
            @if($terms)
                <h1>{{ $terms->title }}</h1>
                <hr>
                <div class="content">
                    {!! nl2br(e($terms->content)) !!}
                </div>
                <hr>
                <p class="text-muted">
                    <small>Last updated: {{ $terms->updated_at->format('F j, Y') }}</small>
                </p>
            @else
                <div class="text-center">
                    <h1>Terms and Conditions</h1>
                    <p class="lead">Terms and conditions have not been configured yet.</p>
                    <a href="{{ route('home') }}" class="btn btn-primary">Go Back Home</a>
                </div>
            @endif
        </div>
    </div>
</div>
@endsection
