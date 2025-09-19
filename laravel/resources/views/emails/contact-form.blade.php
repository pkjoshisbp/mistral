<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Contact Form Submission</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            line-height: 1.6;
            color: #333;
            max-width: 600px;
            margin: 0 auto;
            padding: 20px;
        }
        .header {
            background-color: #f8f9fa;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 20px;
        }
        .content {
            background-color: #ffffff;
            padding: 20px;
            border: 1px solid #dee2e6;
            border-radius: 8px;
        }
        .field {
            margin-bottom: 15px;
        }
        .field-label {
            font-weight: bold;
            color: #495057;
            display: block;
            margin-bottom: 5px;
        }
        .field-value {
            color: #212529;
            background-color: #f8f9fa;
            padding: 8px 12px;
            border-radius: 4px;
            word-wrap: break-word;
        }
        .footer {
            margin-top: 20px;
            padding-top: 20px;
            border-top: 1px solid #dee2e6;
            font-size: 12px;
            color: #6c757d;
        }
    </style>
</head>
<body>
    <div class="header">
        <h2>New Contact Form Submission</h2>
        <p>A new message has been received through the contact form.</p>
    </div>

    <div class="content">
        <div class="field">
            <span class="field-label">Name:</span>
            <div class="field-value">{{ $contactData['name'] }}</div>
        </div>

        <div class="field">
            <span class="field-label">Email:</span>
            <div class="field-value">{{ $contactData['email'] }}</div>
        </div>

        @if(!empty($contactData['phone']))
        <div class="field">
            <span class="field-label">Phone:</span>
            <div class="field-value">{{ $contactData['phone'] }}</div>
        </div>
        @endif

        <div class="field">
            <span class="field-label">Subject:</span>
            <div class="field-value">{{ $contactData['subject'] }}</div>
        </div>

        <div class="field">
            <span class="field-label">Message:</span>
            <div class="field-value">{!! nl2br(e($contactData['message'])) !!}</div>
        </div>

        @if(!empty($contactData['organization']))
        <div class="field">
            <span class="field-label">Organization:</span>
            <div class="field-value">{{ $contactData['organization'] }}</div>
        </div>
        @endif
    </div>

    <div class="footer">
        <p>This email was sent automatically from the contact form on {{ config('app.name') }}.</p>
        <p>Submitted on: {{ now()->format('F j, Y \a\t g:i A') }}</p>
    </div>
</body>
</html>