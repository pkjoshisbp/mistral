<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Widget Test Page - {{ $organization->name }}</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 0;
            padding: 40px;
            background: #f5f5f5;
        }
        .container {
            max-width: 800px;
            margin: 0 auto;
            background: white;
            padding: 40px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        h1 {
            color: #333;
            margin-bottom: 20px;
        }
        .info {
            background: #e3f2fd;
            padding: 20px;
            border-radius: 5px;
            margin-bottom: 30px;
            border-left: 4px solid #2196f3;
        }
        .sample-content {
            line-height: 1.6;
            color: #666;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>{{ $organization->name }} - Widget Test Page</h1>
        
        <div class="info">
            <strong>This is a test page for the AI chat widget.</strong><br>
            The chat widget should appear in the {{ $organization->settings['widget_position'] ?? 'bottom-right' }} corner of this page.
            Click on it to start a conversation!
        </div>

        <div class="sample-content">
            <h2>About Our Services</h2>
            <p>Welcome to {{ $organization->name }}! This is a sample page to demonstrate how our AI chat widget integrates seamlessly into any website.</p>
            
            <h3>How it Works</h3>
            <ul>
                <li>The widget loads dynamically from our servers</li>
                <li>It connects to our AI system powered by Mistral 7B</li>
                <li>Responses are generated based on your organization's data stored in our vector database</li>
                <li>The widget is fully customizable to match your brand</li>
            </ul>

            <h3>Features</h3>
            <ul>
                <li>Real-time AI-powered responses</li>
                <li>Customizable appearance and positioning</li>
                <li>Mobile-responsive design</li>
                <li>Easy integration with just one script tag</li>
                <li>Session management and conversation history</li>
            </ul>

            <p>Try asking the chat widget about our services, pricing, or any questions related to {{ $organization->name }}!</p>
        </div>
    </div>

    <!-- Widget Script -->
    <script>
        (function() {
            var script = document.createElement('script');
            script.src = '{{ route('widget.script', $organization->id) }}';
            script.async = true;
            document.head.appendChild(script);
        })();
    </script>
</body>
</html>
