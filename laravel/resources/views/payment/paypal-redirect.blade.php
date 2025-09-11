@extends('layouts.customer')

@section('title', 'Redirecting to Payment')

@section('content')
<div class="container-fluid">
    <div class="row justify-content-center">
        <div class="col-md-6">
            <div class="card">
                <div class="card-body text-center">
                    <div class="mb-4">
                        <i class="fas fa-spinner fa-spin fa-3x text-primary mb-3"></i>
                        <h4>Processing Payment</h4>
                        <p class="text-muted">Please wait while we redirect you to PayPal for payment...</p>
                    </div>
                    
                    <div class="progress mb-3">
                        <div class="progress-bar progress-bar-striped progress-bar-animated" 
                             role="progressbar" style="width: 100%"></div>
                    </div>
                    
                    <small class="text-muted">This should only take a few seconds</small>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Auto-trigger PayPal subscription creation
    setTimeout(function() {
        createPayPalSubscription({{ $planId }}, '{{ $cycle }}');
    }, 1000);
    
    async function createPayPalSubscription(planId, billingCycle) {
        try {
            const response = await fetch('{{ route("paypal.create-subscription") }}', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': '{{ csrf_token() }}'
                },
                body: JSON.stringify({
                    plan_id: planId,
                    billing_cycle: billingCycle
                })
            });
            
            const data = await response.json();
            
            if (data.success && data.approval_url) {
                // Redirect to PayPal for approval
                window.location.href = data.approval_url;
            } else {
                alert('Error: ' + (data.message || 'Failed to create subscription'));
                window.location.href = '{{ route("customer.subscription") }}';
            }
        } catch (error) {
            console.error('Error:', error);
            alert('An error occurred while processing your request');
            window.location.href = '{{ route("customer.subscription") }}';
        }
    }
});
</script>
@endsection
