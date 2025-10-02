@extends('layouts.admin')

@section('content')
<div class="row">
  <div class="col-md-8">
    <div class="card card-primary">
      <div class="card-header">
        <h3 class="card-title"><i class="fas fa-credit-card me-2"></i>Manual PayPal Capture</h3>
      </div>
      <div class="card-body">
        <p class="text-muted">Use this tool to capture a PayPal order and allocate credits if the buyer didn't return to our site. This action is idempotent and safe to retry.</p>
        <form id="manualCaptureForm" class="mt-3">
          <div class="form-group mb-3">
            <label for="order_id" class="form-label">PayPal Order ID</label>
            <input type="text" class="form-control" id="order_id" name="order_id" placeholder="e.g. 5J4875592N179473W" required>
            <small class="form-text text-muted">Find this on the PayPal dashboard or from the approval URL token.</small>
          </div>
          <button type="submit" class="btn btn-primary"><i class="fas fa-sync"></i> Capture & Allocate</button>
        </form>
        <div id="captureResult" class="mt-3" style="display:none;"></div>
      </div>
    </div>
  </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
  const form = document.getElementById('manualCaptureForm');
  const result = document.getElementById('captureResult');
  form.addEventListener('submit', async function(e) {
    e.preventDefault();
    result.style.display = 'none';
    result.className = '';
    const orderId = document.getElementById('order_id').value.trim();
    if (!orderId) return;
    try {
      const res = await fetch("{{ route('paypal.admin.capture') }}", {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
        },
        body: JSON.stringify({ order_id: orderId })
      });
      const data = await res.json();
      if (res.ok && data.success) {
        result.className = 'alert alert-success';
        result.innerHTML = `<strong>Success:</strong> ${data.message || 'Captured successfully.'}<br>Order Status: ${data.order_status || 'N/A'}<br>User ID: ${data.user_id || '-'}<br>Tokens: ${data.tokens || '-'}<br>Custom ID: ${data.custom_id || '-'}`;
      } else {
        result.className = 'alert alert-danger';
        const approve = data.approve_url ? `<br><a href="${data.approve_url}" target="_blank" class="btn btn-sm btn-outline-light mt-2"><i class="fas fa-external-link-alt"></i> Open Approval</a>` : '';
        result.innerHTML = `<strong>Error:</strong> ${data.message || 'Operation failed.'}${approve}`;
      }
    } catch (err) {
      result.className = 'alert alert-danger';
      result.innerHTML = `<strong>Error:</strong> ${err.message}`;
    }
    result.style.display = '';
  });
});
</script>
@endsection
