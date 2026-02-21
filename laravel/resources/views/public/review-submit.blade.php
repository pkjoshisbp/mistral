@extends('layouts.public')

@section('title', 'Submit Review')

@section('content')
<div class="py-12 bg-gray-50">
    <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8">
        @livewire('customer.review-form', ['organizationId' => $organizationId ?? null])
    </div>
</div>
@endsection