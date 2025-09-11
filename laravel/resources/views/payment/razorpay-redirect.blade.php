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
                        <p class="text-muted">Please wait while we redirect you to Razorpay for payment...</p>
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

<script src="https://checkout.razorpay.com/v1/checkout.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Auto-trigger Razorpay subscription creation
    setTimeout(function() {
        createRazorpaySubscription({{ $planId }}, '{{ $cycle }}');
    }, 1000);
    
    async function createRazorpaySubscription(planId, billingCycle) {
        try {
            const response = await fetch('{{ route("razorpay.create-subscription") }}', {
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
            
            if (data.success) {
                // Initialize Razorpay
                const options = {
                    key: data.razorpay_key,
                    subscription_id: data.subscription_id,
                    name: data.name,
                    description: data.description,
                    handler: function (response) {
                        // Handle successful payment
                        const form = document.createElement('form');
                        form.method = 'POST';
                        form.action = '{{ route("razorpay.success") }}';
                        
                        const csrfInput = document.createElement('input');
                        csrfInput.type = 'hidden';
                        csrfInput.name = '_token';
                        csrfInput.value = '{{ csrf_token() }}';
                        form.appendChild(csrfInput);
                        
                        const subscriptionInput = document.createElement('input');
                        subscriptionInput.type = 'hidden';
                        subscriptionInput.name = 'razorpay_subscription_id';
                        subscriptionInput.value = response.razorpay_subscription_id;
                        form.appendChild(subscriptionInput);
                        
                        const paymentInput = document.createElement('input');
                        paymentInput.type = 'hidden';
                        paymentInput.name = 'razorpay_payment_id';
                        paymentInput.value = response.razorpay_payment_id;
                        form.appendChild(paymentInput);
                        
                        const signatureInput = document.createElement('input');
                        signatureInput.type = 'hidden';
                        signatureInput.name = 'razorpay_signature';
                        signatureInput.value = response.razorpay_signature;
                        form.appendChild(signatureInput);
                        
                        document.body.appendChild(form);
                        form.submit();
                    },
                    prefill: data.prefill,
                    theme: {
                        color: '#007bff'
                    },
                    modal: {
                        ondismiss: function() {
                            // Offer one-time payment option if recurring payment is dismissed
                            if (confirm('Recurring payment was cancelled. Would you like to try a one-time payment instead? (You can set up recurring payments later from your dashboard)')) {
                                createOnetimePayment({{ $planId }}, '{{ $cycle }}');
                            } else {
                                window.location.href = '{{ route("customer.subscription") }}';
                            }
                        }
                    }
                };
                
                const rzp = new Razorpay(options);
                rzp.open();
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
    
    async function createOnetimePayment(planId, billingCycle) {
        try {
            const response = await fetch('{{ route("razorpay.create-onetime-payment") }}', {
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
            
            if (data.success) {
                // Initialize Razorpay for one-time payment
                const options = {
                    key: data.razorpay_key,
                    amount: data.amount,
                    currency: data.currency,
                    order_id: data.order_id,
                    name: data.name,
                    description: data.description,
                    handler: function (response) {
                        // Handle successful payment
                        const form = document.createElement('form');
                        form.method = 'POST';
                        form.action = '{{ route("razorpay.onetime-success") }}';
                        
                        const csrfInput = document.createElement('input');
                        csrfInput.type = 'hidden';
                        csrfInput.name = '_token';
                        csrfInput.value = '{{ csrf_token() }}';
                        form.appendChild(csrfInput);
                        
                        const orderInput = document.createElement('input');
                        orderInput.type = 'hidden';
                        orderInput.name = 'razorpay_order_id';
                        orderInput.value = response.razorpay_order_id;
                        form.appendChild(orderInput);
                        
                        const paymentInput = document.createElement('input');
                        paymentInput.type = 'hidden';
                        paymentInput.name = 'razorpay_payment_id';
                        paymentInput.value = response.razorpay_payment_id;
                        form.appendChild(paymentInput);
                        
                        const signatureInput = document.createElement('input');
                        signatureInput.type = 'hidden';
                        signatureInput.name = 'razorpay_signature';
                        signatureInput.value = response.razorpay_signature;
                        form.appendChild(signatureInput);
                        
                        document.body.appendChild(form);
                        form.submit();
                    },
                    prefill: data.prefill,
                    theme: {
                        color: '#007bff'
                    },
                    modal: {
                        ondismiss: function() {
                            window.location.href = '{{ route("customer.subscription") }}';
                        }
                    }
                };
                
                const rzp = new Razorpay(options);
                rzp.open();
            } else {
                alert('Error: ' + (data.message || 'Failed to create payment'));
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
