<div>
    <section class="content-header">
        <div class="container-fluid">
            <div class="row mb-2">
                <div class="col-sm-6">
                    <h1><i class="fas fa-coins"></i> Credits</h1>
                </div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-right">
                        <li class="breadcrumb-item"><a href="{{ route('customer.dashboard') }}">Dashboard</a></li>
                        <li class="breadcrumb-item active">Credits</li>
                    </ol>
                </div>
            </div>
        </div>
    </section>
    <section class="content">
        <div class="container-fluid">
            <div class="card">
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-8">
                            <h5>Your Credit Balance</h5>
                            <p class="lead mb-1">
                                {{ number_format(auth()->user()->getCreditBalance()) }} tokens
                            </p>
                            <p class="text-muted">Credits are used when your monthly subscription tokens are low or if you don't have an active subscription.</p>
                        </div>
                        <div class="col-md-4 text-md-right">
                            <a href="{{ route('paypal.create-credit-payment') }}" class="btn btn-primary mb-2"><i class="fas fa-plus-circle"></i> Buy Credits (PayPal)</a>
                            <a href="{{ route('razorpay.create-onetime-payment') }}" class="btn btn-outline-primary mb-2"><i class="fas fa-bolt"></i> Buy Credits (Razorpay)</a>
                        </div>
                    </div>
                </div>
            </div>

            <div class="alert alert-info">
                <i class="fas fa-info-circle"></i> Tip: You can also manage subscriptions on the <a href="{{ route('customer.subscription') }}">Subscription</a> page.
            </div>
        </div>
    </section>
</div>
