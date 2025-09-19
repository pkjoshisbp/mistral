// Payment Gateway JavaScript
// This file handles PayPal and Razorpay integrations

// PayPal SDK configuration
window.initializePayPal = function() {
    if (typeof paypal !== 'undefined') {
        return; // Already initialized
    }
    
    // Load PayPal SDK dynamically
    const paypalScript = document.createElement('script');
    paypalScript.src = `https://www.paypal.com/sdk/js?client-id=${window.paypalClientId}&vault=true&intent=subscription`;
    paypalScript.onload = function() {
        console.log('PayPal SDK loaded successfully');
    };
    document.head.appendChild(paypalScript);
};

// Razorpay SDK configuration
window.initializeRazorpay = function() {
    if (typeof Razorpay !== 'undefined') {
        return; // Already initialized
    }
    
    // Load Razorpay SDK dynamically
    const razorpayScript = document.createElement('script');
    razorpayScript.src = 'https://checkout.razorpay.com/v1/checkout.js';
    razorpayScript.onload = function() {
        console.log('Razorpay SDK loaded successfully');
    };
    document.head.appendChild(razorpayScript);
};

// PayPal Subscription Creation
window.createPayPalSubscription = async function(planId) {
    if (typeof paypal === 'undefined') {
        console.error('PayPal SDK not loaded');
        return;
    }
    
    try {
        const response = await fetch('/api/paypal/create-subscription', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
            },
            body: JSON.stringify({ plan_id: planId })
        });
        
        const data = await response.json();
        return data.subscription_id;
    } catch (error) {
        console.error('Error creating PayPal subscription:', error);
        throw error;
    }
};

// Razorpay Payment Creation
window.createRazorpayPayment = async function(planId, billingCycle) {
    if (typeof Razorpay === 'undefined') {
        console.error('Razorpay SDK not loaded');
        return;
    }
    
    try {
        const response = await fetch('/api/razorpay/create-payment', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
            },
            body: JSON.stringify({ 
                plan_id: planId, 
                billing_cycle: billingCycle 
            })
        });
        
        const data = await response.json();
        
        const options = {
            key: data.razorpay_key,
            subscription_id: data.subscription_id,
            name: 'AI Chat Support',
            description: data.description,
            handler: function (response) {
                // Handle successful payment
                window.location.href = `/payment/success?payment_id=${response.razorpay_payment_id}&subscription_id=${response.razorpay_subscription_id}`;
            },
            prefill: data.prefill,
            theme: {
                color: '#6366f1'
            }
        };
        
        const rzp = new Razorpay(options);
        rzp.open();
    } catch (error) {
        console.error('Error creating Razorpay payment:', error);
        throw error;
    }
};

// Generic subscription handler
window.createSubscription = async function(planId) {
    const button = document.getElementById(`subscribe-btn-${planId}`);
    const originalText = button.innerHTML;
    const billingCycle = document.querySelector('input[name="billingCycle"]:checked')?.value || 'monthly';
    
    button.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Processing...';
    button.disabled = true;
    
    try {
        const paymentMethod = document.querySelector('input[name="paymentMethod"]:checked')?.value || 'paypal';
        
        if (paymentMethod === 'paypal') {
            await window.createPayPalSubscription(planId);
        } else if (paymentMethod === 'razorpay') {
            await window.createRazorpayPayment(planId, billingCycle);
        }
    } catch (error) {
        console.error('Subscription creation failed:', error);
        alert('Payment processing failed. Please try again.');
    } finally {
        button.innerHTML = originalText;
        button.disabled = false;
    }
};