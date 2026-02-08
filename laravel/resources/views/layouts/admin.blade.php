<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="csrf-token" content="{{ csrf_token() }}">
  <title>{{ config('app.name', 'AI Chat Support Admin Panel') }}</title>

  <!-- Google Font: Source Sans Pro -->
  <link rel="stylesheet" href="https://fonts.googleapis.com/css?family=Source+Sans+Pro:300,400,400i,700&display=fallback">
  <!-- Font Awesome -->
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
  <!-- AdminLTE -->
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/admin-lte@3.2/dist/css/adminlte.min.css">
  <!-- Livewire Styles -->
  @livewireStyles
</head>
<body class="hold-transition sidebar-mini">
<div class="wrapper">

  <!-- Navbar -->
  <nav class="main-header navbar navbar-expand navbar-white navbar-light">
    <!-- Left navbar links -->
    <ul class="navbar-nav">
      <li class="nav-item">
        <a class="nav-link" data-widget="pushmenu" href="#" role="button"><i class="fas fa-bars"></i></a>
      </li>
    </ul>

    <!-- Right navbar links -->
    <ul class="navbar-nav ml-auto">
      @auth
      <!-- User Dropdown Menu -->
      <li class="nav-item dropdown">
        <a class="nav-link" data-toggle="dropdown" href="#" role="button">
          <i class="fas fa-user"></i> {{ Auth::user()->name }}
        </a>
        <div class="dropdown-menu dropdown-menu-lg dropdown-menu-right">
          <span class="dropdown-item dropdown-header">{{ Auth::user()->name }}</span>
          <div class="dropdown-divider"></div>
          <a href="{{ route('admin.profile.edit') }}" class="dropdown-item">
            <i class="fas fa-user mr-2"></i> Profile
          </a>
          <div class="dropdown-divider"></div>
          <form method="POST" action="{{ route('logout') }}">
            @csrf
            <button type="submit" class="dropdown-item text-left" style="background: none; border: none; width: 100%;">
              <i class="fas fa-sign-out-alt mr-2"></i> Logout
            </button>
          </form>
        </div>
      </li>
      @else
      <li class="nav-item">
        <a class="nav-link" href="{{ route('login') }}">
          <i class="fas fa-sign-in-alt"></i> Login
        </a>
      </li>
      @endauth
      <li class="nav-item">
        <a class="nav-link" data-widget="fullscreen" href="#" role="button">
          <i class="fas fa-expand-arrows-alt"></i>
        </a>
      </li>
    </ul>
  </nav>

  <!-- Main Sidebar Container -->
  <aside class="main-sidebar sidebar-dark-primary elevation-4">
    <!-- Brand Logo -->
    <a href="{{ route('admin.dashboard') }}" class="brand-link">
      <i class="fas fa-robot brand-image"></i>
      <span class="brand-text font-weight-light">AI Chat Support</span>
    </a>

    <!-- Sidebar -->
    <div class="sidebar">
      @auth
      <!-- Sidebar user panel (optional) -->
      <div class="user-panel mt-3 pb-3 mb-3 d-flex">
        <div class="image">
          <i class="fas fa-user-circle fa-2x text-white"></i>
        </div>
        <div class="info">
          <a href="{{ route('admin.profile.edit') }}" class="d-block">{{ Auth::user()->name }}</a>
        </div>
      </div>

      <!-- Sidebar Menu -->
      <nav class="mt-2">
        <ul class="nav nav-pills nav-sidebar flex-column" data-widget="treeview" role="menu" data-accordion="false">
          @if(Auth::user()->isAdmin())
          <li class="nav-item">
            <a href="{{ route('admin.dashboard') }}" class="nav-link {{ request()->routeIs('admin.dashboard') ? 'active' : '' }}">
              <i class="nav-icon fas fa-tachometer-alt"></i>
              <p>Dashboard</p>
            </a>
          </li>
          <li class="nav-item">
            <a href="{{ route('admin.organizations') }}" class="nav-link {{ request()->routeIs('admin.organizations') ? 'active' : '' }}">
              <i class="nav-icon fas fa-building"></i>
              <p>Organizations</p>
            </a>
          </li>
          <li class="nav-item">
            <a href="{{ route('admin.users') }}" class="nav-link {{ request()->routeIs('admin.users') ? 'active' : '' }}">
              <i class="nav-icon fas fa-users"></i>
              <p>Users</p>
            </a>
          </li>
          <li class="nav-item">
            <a href="{{ route('admin.credit-manager') }}" class="nav-link {{ request()->routeIs('admin.credit-manager') ? 'active' : '' }}">
              <i class="nav-icon fas fa-coins"></i>
              <p>Credit Manager</p>
            </a>
          </li>

          <li class="nav-header">DATA MANAGEMENT</li>
          <li class="nav-item has-treeview {{ request()->routeIs('admin.data-entry') || request()->routeIs('admin.data-entry-manager') || request()->routeIs('admin.data-entry-advanced') || request()->routeIs('admin.services') || request()->routeIs('admin.faqs') || request()->routeIs('admin.general-info') || request()->routeIs('admin.documents') ? 'menu-open' : '' }}">
            <a href="#" class="nav-link {{ request()->routeIs('admin.data-entry') || request()->routeIs('admin.data-entry-manager') || request()->routeIs('admin.data-entry-advanced') || request()->routeIs('admin.services') || request()->routeIs('admin.faqs') || request()->routeIs('admin.general-info') || request()->routeIs('admin.documents') ? 'active' : '' }}">
              <i class="nav-icon fas fa-magic"></i>
              <p>Content Hub <i class="right fas fa-angle-left"></i></p>
            </a>
            <ul class="nav nav-treeview">
              <li class="nav-item"><a href="{{ route('admin.data-entry') }}" class="nav-link {{ request()->routeIs('admin.data-entry') ? 'active' : '' }}"><i class="far fa-circle nav-icon"></i><p>Overview</p></a></li>
              <li class="nav-item"><a href="{{ route('admin.data-entry-manager') }}" class="nav-link {{ request()->routeIs('admin.data-entry-manager') ? 'active' : '' }}"><i class="far fa-circle nav-icon"></i><p>Data Manager</p></a></li>
              <li class="nav-item"><a href="{{ route('admin.data-entry-advanced') }}" class="nav-link {{ request()->routeIs('admin.data-entry-advanced') ? 'active' : '' }}"><i class="far fa-circle nav-icon"></i><p>Advanced Entry</p></a></li>
              <li class="nav-item"><a href="{{ route('admin.services') }}" class="nav-link {{ request()->routeIs('admin.services') ? 'active' : '' }}"><i class="far fa-circle nav-icon"></i><p>Services</p></a></li>
              <li class="nav-item"><a href="{{ route('admin.faqs') }}" class="nav-link {{ request()->routeIs('admin.faqs') ? 'active' : '' }}"><i class="far fa-circle nav-icon"></i><p>FAQs</p></a></li>
              <li class="nav-item"><a href="{{ route('admin.general-info') }}" class="nav-link {{ request()->routeIs('admin.general-info') ? 'active' : '' }}"><i class="far fa-circle nav-icon"></i><p>General Info</p></a></li>
              <li class="nav-item"><a href="{{ route('admin.documents') }}" class="nav-link {{ request()->routeIs('admin.documents') ? 'active' : '' }}"><i class="far fa-circle nav-icon"></i><p>Documents</p></a></li>
            </ul>
          </li>
          
          <li class="nav-item">
            <a href="{{ route('admin.action-manager') }}" class="nav-link {{ request()->routeIs('admin.action-manager') ? 'active' : '' }}">
              <i class="nav-icon fas fa-cogs"></i>
              <p>Live Data Actions</p>
            </a>
          </li>
          
          <li class="nav-item">
            <a href="{{ route('admin.ai-chat') }}" class="nav-link {{ request()->routeIs('admin.ai-chat') ? 'active' : '' }}">
              <i class="nav-icon fas fa-comments"></i>
              <p>AI Chat</p>
            </a>
          </li>
          <li class="nav-item">
            <a href="{{ route('admin.live-chats') }}" class="nav-link {{ request()->routeIs('admin.live-chats') ? 'active' : '' }}">
              <i class="nav-icon fas fa-headset"></i>
              <p>Live Chats</p>
            </a>
          </li>
          <li class="nav-item">
            <a href="{{ route('admin.chat-history') }}" class="nav-link {{ request()->routeIs('admin.chat-history') ? 'active' : '' }}">
              <i class="nav-icon fas fa-history"></i>
              <p>Chat History</p>
            </a>
          </li>
          <li class="nav-item">
            <a href="{{ route('admin.leads') }}" class="nav-link {{ request()->routeIs('admin.leads') ? 'active' : '' }}">
              <i class="nav-icon fas fa-address-card"></i>
              <p>Leads</p>
            </a>
          </li>
          <li class="nav-item">
            <a href="{{ route('admin.analytics') }}" class="nav-link {{ request()->routeIs('admin.analytics') ? 'active' : '' }}">
              <i class="nav-icon fas fa-chart-line"></i>
              <p>Analytics</p>
            </a>
          </li>
          <li class="nav-item">
            <a href="{{ route('admin.reviews') }}" class="nav-link {{ request()->routeIs('admin.reviews') ? 'active' : '' }}">
              <i class="nav-icon fas fa-star"></i>
              <p>Customer Reviews</p>
            </a>
          </li>
          <li class="nav-item">
            <a href="{{ route('admin.token-usage-analytics') }}" class="nav-link {{ request()->routeIs('admin.token-usage-analytics') ? 'active' : '' }}">
              <i class="nav-icon fas fa-microchip"></i>
              <p>Token Usage</p>
            </a>
          </li>
          <li class="nav-item">
            <a href="{{ route('admin.widget-manager') }}" class="nav-link {{ request()->routeIs('admin.widget-manager') ? 'active' : '' }}">
              <i class="nav-icon fas fa-code"></i>
              <p>Widget Manager</p>
            </a>
          </li>
          <li class="nav-item">
            <a href="{{ route('admin.widget-script-manager') }}" class="nav-link {{ request()->routeIs('admin.widget-script-manager') ? 'active' : '' }}">
              <i class="nav-icon fas fa-cogs"></i>
              <p>Script Generator</p>
            </a>
          </li>
          <li class="nav-item">
            <a href="{{ route('admin.api-endpoints') }}" class="nav-link {{ request()->routeIs('admin.api-endpoints') ? 'active' : '' }}">
              <i class="nav-icon fas fa-plug"></i>
              <p>API Endpoints</p>
            </a>
          </li>
          
          <li class="nav-header">BILLING</li>
          <li class="nav-item">
            <a href="{{ route('admin.pricing.index') }}" class="nav-link {{ request()->routeIs('admin.pricing.*') ? 'active' : '' }}">
              <i class="nav-icon fas fa-dollar-sign"></i>
              <p>Pricing Management</p>
            </a>
          </li>
          <li class="nav-item">
            <a href="{{ route('admin.payments.manual-capture') }}" class="nav-link {{ request()->routeIs('admin.payments.manual-capture') ? 'active' : '' }}">
              <i class="nav-icon fas fa-credit-card"></i>
              <p>Manual PayPal Capture</p>
            </a>
          </li>
          
          <li class="nav-header">EMAIL MARKETING</li>
          <li class="nav-item">
            <a href="{{ route('admin.email-templates') }}" class="nav-link {{ request()->routeIs('admin.email-templates') ? 'active' : '' }}">
              <i class="nav-icon fas fa-file-alt"></i>
              <p>Email Templates</p>
            </a>
          </li>
          <li class="nav-item">
            <a href="{{ route('admin.email-campaigns') }}" class="nav-link {{ request()->routeIs('admin.email-campaigns') ? 'active' : '' }}">
              <i class="nav-icon fas fa-paper-plane"></i>
              <p>Email Campaigns</p>
            </a>
          </li>
          <li class="nav-item">
            <a href="{{ route('admin.whatsapp-campaigns') }}" class="nav-link {{ request()->routeIs('admin.whatsapp-campaigns') ? 'active' : '' }}">
              <i class="nav-icon fab fa-whatsapp"></i>
              <p>WhatsApp Campaigns</p>
            </a>
          </li>
          <li class="nav-item">
            <a href="{{ route('admin.email-composer') }}" class="nav-link {{ request()->routeIs('admin.email-composer') ? 'active' : '' }}">
              <i class="nav-icon fas fa-edit"></i>
              <p>Compose Email</p>
            </a>
          </li>
          <li class="nav-item">
            <a href="{{ route('admin.demo-manager') }}" class="nav-link {{ request()->routeIs('admin.demo-manager') ? 'active' : '' }}">
              <i class="nav-icon fas fa-desktop"></i>
              <p>Demo Manager</p>
            </a>
          </li>
          @else
          <li class="nav-item">
            <a href="{{ route('customer.dashboard') }}" class="nav-link">
              <i class="nav-icon fas fa-external-link-alt"></i>
              <p>Go to Customer Panel</p>
            </a>
          </li>
          @endif
          
          <li class="nav-header">SYSTEM</li>
          <li class="nav-item">
            <a href="{{ route('admin.settings') }}" class="nav-link {{ request()->routeIs('admin.settings') ? 'active' : '' }}">
              <i class="nav-icon fas fa-cogs"></i>
              <p>Settings</p>
            </a>
          </li>
          <li class="nav-item">
            <a href="{{ route('admin.organization-ai') }}" class="nav-link {{ request()->routeIs('admin.organization-ai') ? 'active' : '' }}">
              <i class="nav-icon fas fa-robot"></i>
              <p>Organization AI Models</p>
            </a>
          </li>
          <li class="nav-item">
            <a href="{{ route('admin.otp-logs') }}" class="nav-link {{ request()->routeIs('admin.otp-logs') ? 'active' : '' }}">
              <i class="nav-icon fas fa-key"></i>
              <p>OTP Logs</p>
            </a>
          </li>
          <li class="nav-item">
            <a href="{{ route('admin.terms-management') }}" class="nav-link {{ request()->routeIs('admin.terms-management') ? 'active' : '' }}">
              <i class="nav-icon fas fa-file-contract"></i>
              <p>Terms & Policies</p>
            </a>
          </li>
          
          <li class="nav-header">ACCOUNT</li>
          <li class="nav-item">
            <a href="{{ route('admin.profile.edit') }}" class="nav-link {{ request()->routeIs('admin.profile.edit') ? 'active' : '' }}">
              <i class="nav-icon fas fa-user"></i>
              <p>Profile</p>
            </a>
          </li>
        </ul>
      </nav>
      @else
      <!-- Guest Menu -->
      <nav class="mt-2">
        <ul class="nav nav-pills nav-sidebar flex-column">
          <li class="nav-item">
            <a href="{{ route('login') }}" class="nav-link">
              <i class="nav-icon fas fa-sign-in-alt"></i>
              <p>Login</p>
            </a>
          </li>
          <li class="nav-item">
            <a href="{{ route('register') }}" class="nav-link">
              <i class="nav-icon fas fa-user-plus"></i>
              <p>Register</p>
            </a>
          </li>
        </ul>
      </nav>
      @endauth
    </div>
  </aside>

  <!-- Content Wrapper -->
  <div class="content-wrapper">
    @if(session('impersonator_id'))
      <div class="alert alert-warning text-center m-0">
        <strong>Impersonating:</strong> {{ Auth::user()->email }}
        <a href="{{ route('admin.impersonate.stop') }}" class="ms-3 btn btn-sm btn-outline-dark">Stop Impersonation</a>
      </div>
    @endif
    <!-- Content Header -->
    <div class="content-header">
      <div class="container-fluid">
        <div class="row mb-2">
          <div class="col-sm-6">
            <h1 class="m-0">{{ $title ?? 'Dashboard' }}</h1>
          </div>
        </div>
      </div>
    </div>

    <!-- Main content -->
    <section class="content">
      <div class="container-fluid">
        @if (session('status'))
            <div class="alert alert-success alert-dismissible">
                <button type="button" class="close" data-dismiss="alert">&times;</button>
                {{ session('status') }}
            </div>
        @endif

        @if ($errors->any())
            <div class="alert alert-danger alert-dismissible">
                <button type="button" class="close" data-dismiss="alert">&times;</button>
                <ul class="mb-0">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

  @isset($slot)
    {{ $slot }}
  @else
    @yield('content')
  @endisset
      </div>
    </section>
  </div>

  <!-- Footer -->
  <footer class="main-footer">
    <strong>Copyright &copy; 2025 AI Chat Support.</strong>
    All rights reserved.
    <div class="float-right d-none d-sm-inline-block">
      <b>Version</b> 1.0.0
    </div>
  </footer>
</div>

<!-- jQuery -->
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<!-- Bootstrap 4 -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.0/dist/js/bootstrap.bundle.min.js"></script>
<!-- AdminLTE App -->
<script src="https://cdn.jsdelivr.net/npm/admin-lte@3.2/dist/js/adminlte.min.js"></script>
<!-- Livewire Scripts -->
@livewireScripts

<script>
  // Lightweight Livewire availability check (avoid noisy console spam)
  document.addEventListener('livewire:init', function() {
    if (!window.Livewire) {
      console.warn('Livewire not found on admin layout');
    }
  });
</script>
</body>
</html>
