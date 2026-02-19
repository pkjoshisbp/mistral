<!DOCTYPE html>
<html>
<head>
    <title>Test Email Tracking</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
    <div class="container mt-5">
        <div class="row justify-content-center">
            <div class="col-md-8">
                <div class="card">
                    <div class="card-header">
                        <h3>Test Email Tracking</h3>
                    </div>
                    <div class="card-body">
                        @if(session('success'))
                            <div class="alert alert-success">{{ session('success') }}</div>
                        @endif
                        
                        @if(session('error'))
                            <div class="alert alert-danger">{{ session('error') }}</div>
                        @endif
                        
                        <form method="POST" action="{{ route('test.email.send') }}">
                            @csrf
                            <div class="mb-3">
                                <label class="form-label">Your Email</label>
                                <input type="email" name="email" class="form-control" value="{{ auth()->user()->email ?? 'info@mywebsolutions.co.in' }}" required>
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label">Subject</label>
                                <input type="text" name="subject" class="form-control" value="Testing Email Tracking" required>
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label">Content (HTML allowed)</label>
                                <textarea name="content" class="form-control" rows="8" required><!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
</head>
<body style="font-family: Arial, sans-serif; padding: 20px;">
    <h1>Email Tracking Test</h1>
    <p>This is a test email to verify tracking functionality.</p>
    <p>Please click the link below:</p>
    <p><a href="https://ai-chat.support" style="color: #007bff;">Visit AI Chat Support</a></p>
    <p>If tracking works:</p>
    <ul>
        <li>Opening this email will increment "Opened" count</li>
        <li>Clicking the link will increment "Clicked" count</li>
    </ul>
</body>
</html></textarea>
                            </div>
                            
                            <button type="submit" class="btn btn-primary">Send Test Email</button>
                        </form>
                        
                        @if(isset($recipient))
                        <hr>
                        <div class="mt-4">
                            <h5>Tracking Status</h5>
                            <table class="table table-sm">
                                <tr>
                                    <td><strong>Email:</strong></td>
                                    <td>{{ $recipient->recipient_email }}</td>
                                </tr>
                                <tr>
                                    <td><strong>Sent At:</strong></td>
                                    <td>{{ $recipient->sent_at }}</td>
                                </tr>
                                <tr>
                                    <td><strong>Delivered:</strong></td>
                                    <td>{{ $recipient->delivered_at ? '✓ Yes' : '✗ No' }}</td>
                                </tr>
                                <tr>
                                    <td><strong>Opened:</strong></td>
                                    <td>{{ $recipient->opened_at ? '✓ Yes at ' . $recipient->opened_at : '✗ Not yet' }}</td>
                                </tr>
                                <tr>
                                    <td><strong>Clicked:</strong></td>
                                    <td>{{ $recipient->clicked_at ? '✓ Yes at ' . $recipient->clicked_at : '✗ Not yet' }}</td>
                                </tr>
                                <tr>
                                    <td><strong>Tracking Token:</strong></td>
                                    <td><code>{{ $recipient->tracking_token }}</code></td>
                                </tr>
                            </table>
                            
                            <div class="mt-3">
                                <strong>Test Tracking Pixel:</strong><br>
                                <a href="https://ai-chat.support/email/open/{{ $recipient->tracking_token }}.png" target="_blank" class="btn btn-sm btn-secondary">
                                    Load Tracking Pixel
                                </a>
                                <small class="text-muted d-block mt-2">Click this to simulate opening the email</small>
                            </div>
                            
                            <div class="mt-3">
                                <a href="{{ route('test.email.check', $recipient->id) }}" class="btn btn-success">
                                    Refresh Tracking Status
                                </a>
                            </div>
                        </div>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
