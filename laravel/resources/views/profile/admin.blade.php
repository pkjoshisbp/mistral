@extends('layouts.admin')

@section('title', 'Profile')
@section('content')
    
<div class="content-header">
  <div class="container-fluid">
    <div class="row mb-2">
      <div class="col-sm-6">
        <h1 class="m-0">Profile</h1>
      </div>
      <div class="col-sm-6">
        <ol class="breadcrumb float-sm-right">
          <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}">Dashboard</a></li>
          <li class="breadcrumb-item active">Profile</li>
        </ol>
      </div>
    </div>
  </div>
</div>

<section class="content">
  <div class="container-fluid">
    <div class="row">
      <div class="col-md-8 mx-auto">
        <div class="card">
          <div class="card-header">
            <h3 class="card-title">Profile Information</h3>
          </div>
          <div class="card-body">
            @include('profile.partials.update-profile-information-form')
          </div>
        </div>
      </div>
    </div>
    
    <div class="row mt-4">
      <div class="col-md-8 mx-auto">
        <div class="card">
          <div class="card-header">
            <h3 class="card-title">Update Password</h3>
          </div>
          <div class="card-body">
            @include('profile.partials.update-password-form')
          </div>
        </div>
      </div>
    </div>
    
    <div class="row mt-4">
      <div class="col-md-8 mx-auto">
        <div class="card border-danger">
          <div class="card-header bg-danger">
            <h3 class="card-title text-white">Delete Account</h3>
          </div>
          <div class="card-body">
            @include('profile.partials.delete-user-form')
          </div>
        </div>
      </div>
    </div>
  </div>
</section>

@endsection
