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
                        <h4>Processing One-Time Payment</h4>
                        <p class="text-muted">Please wait while we redirect you to Razorpay for payment...</p>
                        <small class="text-info">This is a one-time payment for your selected plan.</small>
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
    // Auto-trigger one-time payment creation
    setTimeout(function() {
        createOnetimePayment({{ $planId }}, '{{ $cycle }}');
    }, 1000);
    
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
                            // Redirect to subscription page if payment is cancelled
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
