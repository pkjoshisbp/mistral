@extends('layouts.public')

@section('title', 'Terms and Conditions - AI Chat Support')

@section('content')
<!-- Content -->
<div class="container my-5">
    <div class="row justify-content-center">
        <div class="col-lg-8">
            <div class="text-center">
                <h1>{{ $terms?->title ?? 'Terms and Conditions' }}</h1>
                @if($terms && $terms->content)
                    <div class="text-start">
                        {!! nl2br(e($terms->content)) !!}
                    </div>
                @else
                    <p class="lead">Terms and conditions have not been configured yet.</p>
                @endif
                <a href="{{ route('home') }}" class="btn btn-primary">Go Back Home</a>
            </div>
        </div>
    </div>
</div>
@endsection
