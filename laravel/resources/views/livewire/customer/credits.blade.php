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
                                {{ number_format($creditSummary['usable_balance'] ?? 0) }} usable tokens
                            </p>
                            <p class="mb-1 text-muted">Credits are used when your monthly subscription tokens are low or if you don't have an active subscription.</p>
                            <small class="text-muted d-block">Raw balance: {{ number_format($creditSummary['raw_balance'] ?? auth()->user()->getCreditBalance()) }} tokens</small>
                            <small class="text-muted d-block">Expired credits: {{ number_format($creditSummary['expired_balance'] ?? 0) }} tokens</small>
                            <small class="text-muted d-block">Grace period after expiry: {{ $creditSummary['grace_period_months'] ?? 1 }} month</small>

                            @if(($creditSummary['in_grace_balance'] ?? 0) > 0)
                                <div class="alert alert-warning mt-3 mb-0 py-2">
                                    <i class="fas fa-exclamation-triangle"></i>
                                    {{ number_format($creditSummary['in_grace_balance']) }} tokens are in grace period.
                                    @if(!empty($creditSummary['next_grace_end_at']))
                                        Renew before {{ \Carbon\Carbon::parse($creditSummary['next_grace_end_at'])->format('M d, Y') }} to roll over these credits.
                                    @endif
                                </div>
                            @endif
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

            @if(isset($recentTransactions) && $recentTransactions->count() > 0)
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title"><i class="fas fa-history"></i> Recent Credit Activity</h3>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-sm mb-0">
                                <thead>
                                    <tr>
                                        <th>Date</th>
                                        <th>Type</th>
                                        <th>Amount</th>
                                        <th>Description</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($recentTransactions as $tx)
                                        <tr>
                                            <td>{{ optional($tx->created_at)->format('M d, Y H:i') }}</td>
                                            <td>
                                                <span class="badge badge-{{ $tx->type === 'credit' ? 'success' : 'secondary' }}">
                                                    {{ strtoupper($tx->type) }}
                                                </span>
                                            </td>
                                            <td>{{ number_format((float) $tx->amount, 0) }}</td>
                                            <td>{{ $tx->description ?: '-' }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            @endif
        </div>
    </section>
</div>
