@extends('layouts.public')

@section('title', 'Customer Reviews')

@section('content')
<div class="py-12 bg-gray-50">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        @livewire('public.reviews-display')
    </div>
</div>
@endsection