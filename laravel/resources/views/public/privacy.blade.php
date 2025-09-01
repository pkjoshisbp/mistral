@extends('layouts.public')

@section('title', ($privacy->title ?? 'Privacy Policy') . ' - AI Chat Support')

@section('content')
<!-- Content -->
<div class="container my-5">
    <div class="row justify-content-center">
        <div class="col-lg-8">
            @if($privacy)
                <h1>{{ $privacy->title }}</h1>
                <hr>
                <div class="content">
                    {!! nl2br(e($privacy->content)) !!}
                </div>
                <hr>
                <p class="text-muted">
                    <small>Last updated: {{ $privacy->updated_at->format('F j, Y') }}</small>
                </p>
            @else
                <div class="text-center">
                    <h1>Privacy Policy</h1>
                    <p class="lead">Privacy policy has not been configured yet.</p>
                    <a href="{{ route('home') }}" class="btn btn-primary">Go Back Home</a>
                </div>
            @endif
        </div>
    </div>
</div>
@endsection
