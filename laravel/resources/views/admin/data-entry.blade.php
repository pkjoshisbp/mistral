@extends('layouts.admin')
@section('title', 'Data Entry')
@section('content')
<div class="container-fluid py-4">
  <div class="card">
    <div class="card-header bg-primary text-white">
      <h3 class="mb-0"><i class="fas fa-keyboard me-2"></i>Data Entry</h3>
    </div>
    <div class="card-body">
      <p class="lead">Manage all organization data, services, FAQs, general info, and documents from one place.</p>
      <ul class="list-group list-group-flush">
        <li class="list-group-item"><a href="{{ route('admin.services') }}"><i class="far fa-circle me-2"></i>Services</a></li>
        <li class="list-group-item"><a href="{{ route('admin.faqs') }}"><i class="far fa-circle me-2"></i>FAQs</a></li>
        <li class="list-group-item"><a href="{{ route('admin.general-info') }}"><i class="far fa-circle me-2"></i>General Info</a></li>
        <li class="list-group-item"><a href="{{ route('admin.documents') }}"><i class="fas fa-file-alt me-2"></i>Documents</a></li>
      </ul>
    </div>
  </div>
</div>
@endsection
