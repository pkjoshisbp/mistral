<?php

namespace App\Livewire\Admin;

use Livewire\Component;
use App\Models\AdminSetting;
use App\Support\VastAiConfig;
use Illuminate\Support\Facades\Auth;

class SettingsManager extends Component
{
    public $activeTab = 'payment';
    
    // Payment Settings
    public $paypal_mode = 'sandbox';
    public $paypal_client_id = '';
    public $paypal_client_secret = '';
    public $paypal_webhook_url = '';
    public $razorpay_key_id = '';
    public $razorpay_key_secret = '';
    public $razorpay_webhook_url = '';
    
    // Email Settings
    public $mail_mailer = 'smtp';
    public $mail_host = '';
    public $mail_port = '587';
    public $mail_username = '';
    public $mail_password = '';
    public $mail_encryption = 'tls';
    public $mail_from_address = '';
    public $mail_from_name = '';
    
    // App Settings
    public $app_name = '';
    public $app_url = '';
    public $app_timezone = 'UTC';
    public $homepage_client_logo_gap = 24;
    public $homepage_client_logo_height = 100;
    
    // AI Settings
    public $ai_model_provider = 'llama';
    public $ai_backend_type = 'ollama'; // ollama or llamacpp
    public $openai_api_key = '';
    public $openai_default_model = 'gpt-5-mini'; // Only allowed model
    public $llama_default_model = 'llama3.1:8b';
    public $ai_context_relevance_model = 'deepseek-r1:8b';
    public $ai_context_relevance_min_confidence = 0.4;
    public $llamacpp_model_path = '';
    public $llamacpp_model_repo = 'custom/Llama-3.2-3B-Instruct-Q8_0-Custom';
    public $llamacpp_threads = 4;
    public $llamacpp_context_length = 4096;
    public $ai_use_intent_rewrite = true;
    public $global_query_translation_map = '';
    public $global_query_alias_map = '';
    public $vastai_ssh_host = '';
    public $vastai_ssh_port = 51734;
    public $vastai_ssh_user = 'root';

    // WhatsApp Cloud Settings
    public $whatsapp_api_version = 'v20.0';
    public $whatsapp_business_account_id = '';
    public $whatsapp_phone_number_id = '';
    public $whatsapp_access_token = '';
    public $whatsapp_verify_token = '';
    public $whatsapp_default_seed_question = 'Would you like to know more about our services, products, pricing, or latest offers?';
    
    public function mount()
    {
        $this->loadSettings();
    }
    
    public function getAvailableLlamaModels()
    {
        return [
            'deepseek-r1:8b'           => 'DeepSeek R1 Distill Llama 8B (Vast.ai, trial)',
            'llama3.1:8b'              => 'Llama 3.1 8B (Vast.ai, recommended)',
            'mistral-nemo:latest'       => 'Mistral Nemo (Vast.ai)',
            'llama3.2:1b'              => 'Llama 3.2:1B (Fast, lightweight)',
            'llama3.2:3b'              => 'Llama 3.2:3B (Balanced quality/speed)',
            'llama3.2:3b-instruct-gguf' => 'Llama 3.2:3B Instruct GGUF (llama.cpp optimized)',
            'mistral:7b'               => 'Mistral 7B (High quality, slower)',
            'gemma:2b'                 => 'Gemma 2B (Google, fast)',
        ];
    }

    public function getAvailableLlamaCppModels()
    {
        return [
            'bartowski/Llama-3.2-3B-Instruct-GGUF:Llama-3.2-3B-Instruct-Q4_K_M.gguf' => 'Llama 3.2 3B Instruct Q4_K_M (1.87GB)',
            'bartowski/Llama-3.2-1B-Instruct-GGUF:Llama-3.2-1B-Instruct-Q4_K_M.gguf' => 'Llama 3.2 1B Instruct Q4_K_M (Fast)',
            'custom/Llama-3.2-3B-Instruct-Q8_0-Custom' => 'Llama 3.2 3B Instruct Q8_0 Custom (3.2GB, High Quality)',
        ];
    }

    public function getAvailableBackends()
    {
        return [
            'ollama' => 'Ollama (Easy management, auto-downloads)',
            'llamacpp' => 'llama.cpp (Optimized performance, manual setup)'
        ];
    }

    public function checkOllamaModels()
    {
        try {
            $output = shell_exec('ollama list 2>&1');
            if ($output && str_contains($output, 'llama3.2')) {
                return true;
            }
        } catch (\Exception $e) {
            \Log::error('Failed to check Ollama models: ' . $e->getMessage());
        }
        return false;
    }

    public function testModel($model = null)
    {
        $testModel = $model ?: $this->llama_default_model;
        $testPrompt = "Respond with 'AI system test successful' and the current model name.";
        $ollamaUrl = $this->resolveOllamaUrlForModel($testModel);
        
        try {
            $startTime = microtime(true);
            
            // Test with Ollama API
            $response = \Http::timeout(45)->post("{$ollamaUrl}/api/generate", [
                'model' => $testModel,
                'prompt' => $testPrompt,
                'stream' => false
            ]);
            
            $endTime = microtime(true);
            $responseTime = round(($endTime - $startTime), 2);
            
            if ($response->successful()) {
                $data = $response->json();
                $responseText = $data['response'] ?? 'No response received';
                
                session()->flash('success', "✅ Model '{$testModel}' test successful via {$ollamaUrl}! Response time: {$responseTime}s. Response: " . substr($responseText, 0, 100) . "...");
            } else {
                session()->flash('error', "❌ Model test failed. HTTP status: " . $response->status());
            }
            
        } catch (\Exception $e) {
            session()->flash('error', "❌ Model test error: " . $e->getMessage());
        }
    }

    private function resolveOllamaUrlForModel(string $model): string
    {
        $vastModels = [
            'deepseek-r1:8b',
            'llama3.1:8b',
            'mistral-nemo',
            'mistral-nemo:latest',
        ];

        return in_array($model, $vastModels, true)
            ? 'http://127.0.0.1:11435'
            : 'http://localhost:11434';
    }

    public function loadSettings()
    {
                // Load payment settings
    $this->paypal_mode = AdminSetting::get('paypal_mode', 'sandbox');
    $this->paypal_client_id = AdminSetting::get('paypal_client_id', '');
    $this->paypal_client_secret = AdminSetting::get('paypal_client_secret', '');
    $this->paypal_webhook_url = url('/paypal/webhook');
    $this->razorpay_key_id = AdminSetting::get('razorpay_key_id', '');
    $this->razorpay_key_secret = AdminSetting::get('razorpay_key_secret', '');
    $this->razorpay_webhook_url = url('/razorpay/webhook');
        
        // Email Settings
        $this->mail_mailer = AdminSetting::get('mail_mailer', 'smtp');
        $this->mail_host = AdminSetting::get('mail_host', '');
        $this->mail_port = AdminSetting::get('mail_port', '587');
        $this->mail_username = AdminSetting::get('mail_username', '');
        $this->mail_password = AdminSetting::get('mail_password', '');
        $this->mail_encryption = AdminSetting::get('mail_encryption', 'tls');
        $this->mail_from_address = AdminSetting::get('mail_from_address', '');
        $this->mail_from_name = AdminSetting::get('mail_from_name', '');
        
        // App Settings
        $this->app_name = AdminSetting::get('app_name', config('app.name'));
        $this->app_url = AdminSetting::get('app_url', config('app.url'));
        $this->app_timezone = AdminSetting::get('app_timezone', 'UTC');
        $this->homepage_client_logo_gap = (int) AdminSetting::get('homepage_client_logo_gap', 24);
        $this->homepage_client_logo_height = (int) AdminSetting::get('homepage_client_logo_height', 100);
        
        // AI Settings
        $this->ai_model_provider = AdminSetting::get('ai_model_provider', config('app.ai_model_provider', 'llama'));
        $this->ai_backend_type = AdminSetting::get('ai_backend_type', 'ollama');
        $this->openai_api_key = AdminSetting::get('openai_api_key', '');
        $this->openai_default_model = AdminSetting::get('openai_default_model', 'gpt-5-mini');
        $this->llama_default_model = AdminSetting::get('llama_default_model', 'llama3.1:8b');
        $this->ai_context_relevance_model = AdminSetting::get('ai_context_relevance_model', 'deepseek-r1:8b');
        $this->ai_context_relevance_min_confidence = (float) AdminSetting::get('ai_context_relevance_min_confidence', 0.4);
        if (!array_key_exists($this->llama_default_model, $this->getAvailableLlamaModels())) {
            $this->llama_default_model = 'llama3.1:8b';
        }
        if (!array_key_exists($this->ai_context_relevance_model, $this->getAvailableLlamaModels())) {
            $this->ai_context_relevance_model = 'deepseek-r1:8b';
        }
        $this->llamacpp_model_path = AdminSetting::get('llamacpp_model_path', '');
        $this->llamacpp_model_repo = AdminSetting::get('llamacpp_model_repo', 'custom/Llama-3.2-3B-Instruct-Q8_0-Custom');
        $this->llamacpp_threads = AdminSetting::get('llamacpp_threads', 4);
        $this->llamacpp_context_length = AdminSetting::get('llamacpp_context_length', 4096);
        $this->ai_use_intent_rewrite = (bool) AdminSetting::get('ai_use_intent_rewrite', true);
        $this->global_query_translation_map = (string) AdminSetting::get('global_query_translation_map', '');
        $this->global_query_alias_map = (string) AdminSetting::get('global_query_alias_map', '');
        $this->vastai_ssh_host = (string) AdminSetting::get('vastai_ssh_host', env('VAST_HOST', '123.21.80.170'));
        $this->vastai_ssh_port = (int) AdminSetting::get('vastai_ssh_port', env('VAST_PORT', 51734));
        $this->vastai_ssh_user = (string) AdminSetting::get('vastai_ssh_user', env('VAST_USER', 'root'));

        // WhatsApp Settings
        $this->whatsapp_api_version = AdminSetting::get('whatsapp_api_version', 'v20.0');
        $this->whatsapp_business_account_id = AdminSetting::get('whatsapp_business_account_id', '');
        $this->whatsapp_phone_number_id = AdminSetting::get('whatsapp_phone_number_id', '');
        $this->whatsapp_access_token = AdminSetting::get('whatsapp_access_token', '');
        $this->whatsapp_verify_token = AdminSetting::get('whatsapp_verify_token', '');
        $this->whatsapp_default_seed_question = AdminSetting::get('whatsapp_default_seed_question', 'Would you like to know more about our services, products, pricing, or latest offers?');
    }

    public function saveWhatsappSettings()
    {
        $this->validate([
            'whatsapp_api_version' => 'required|string',
            'whatsapp_business_account_id' => 'nullable|string',
            'whatsapp_phone_number_id' => 'required|string',
            'whatsapp_access_token' => 'required|string',
            'whatsapp_verify_token' => 'required|string',
            'whatsapp_default_seed_question' => 'nullable|string|max:500',
        ]);

        AdminSetting::set('whatsapp_api_version', $this->whatsapp_api_version, 'text', 'whatsapp', 'WhatsApp API Version');
        AdminSetting::set('whatsapp_business_account_id', $this->whatsapp_business_account_id, 'text', 'whatsapp', 'Business Account ID');
        AdminSetting::set('whatsapp_phone_number_id', $this->whatsapp_phone_number_id, 'text', 'whatsapp', 'Phone Number ID');
        AdminSetting::set('whatsapp_access_token', $this->whatsapp_access_token, 'password', 'whatsapp', 'Access Token', null, true);
        AdminSetting::set('whatsapp_verify_token', $this->whatsapp_verify_token, 'password', 'whatsapp', 'Webhook Verify Token', null, true);
        AdminSetting::set('whatsapp_default_seed_question', trim((string) $this->whatsapp_default_seed_question), 'textarea', 'whatsapp', 'Default Seed Question For Yes Replies');

        session()->flash('success', 'WhatsApp settings saved successfully!');
    }

    public function savePaymentSettings()
    {
        $this->validate([
            'paypal_client_id' => 'nullable|string',
            'paypal_client_secret' => 'nullable|string',
            'razorpay_key_id' => 'nullable|string',
            'razorpay_key_secret' => 'nullable|string',
        ]);

        AdminSetting::set('paypal_mode', $this->paypal_mode, 'select', 'payment', 'PayPal Mode');
        AdminSetting::set('paypal_client_id', $this->paypal_client_id, 'text', 'payment', 'PayPal Client ID');
        AdminSetting::set('paypal_client_secret', $this->paypal_client_secret, 'password', 'payment', 'PayPal Client Secret', null, true);
        AdminSetting::set('razorpay_key_id', $this->razorpay_key_id, 'text', 'payment', 'Razorpay Key ID');
        AdminSetting::set('razorpay_key_secret', $this->razorpay_key_secret, 'password', 'payment', 'Razorpay Key Secret', null, true);

        // Update environment file
        $this->updateEnvFile([
            'PAYPAL_MODE' => $this->paypal_mode,
            'PAYPAL_CLIENT_ID' => $this->paypal_client_id,
            'PAYPAL_CLIENT_SECRET' => $this->paypal_client_secret,
            'RAZORPAY_KEY_ID' => $this->razorpay_key_id,
            'RAZORPAY_KEY_SECRET' => $this->razorpay_key_secret,
        ]);

        session()->flash('success', 'Payment settings saved successfully!');
    }

    public function saveEmailSettings()
    {
        $this->validate([
            'mail_host' => 'required|string',
            'mail_port' => 'required|numeric',
            'mail_username' => 'required|string',
            'mail_password' => 'required|string',
            'mail_from_address' => 'required|email',
            'mail_from_name' => 'required|string',
        ]);

        AdminSetting::set('mail_mailer', $this->mail_mailer, 'select', 'email', 'Mail Driver');
        AdminSetting::set('mail_host', $this->mail_host, 'text', 'email', 'SMTP Host');
        AdminSetting::set('mail_port', $this->mail_port, 'number', 'email', 'SMTP Port');
        AdminSetting::set('mail_username', $this->mail_username, 'text', 'email', 'SMTP Username');
        AdminSetting::set('mail_password', $this->mail_password, 'password', 'email', 'SMTP Password', null, true);
        AdminSetting::set('mail_encryption', $this->mail_encryption, 'select', 'email', 'Encryption');
        AdminSetting::set('mail_from_address', $this->mail_from_address, 'email', 'email', 'From Address');
        AdminSetting::set('mail_from_name', $this->mail_from_name, 'text', 'email', 'From Name');

        // Update environment file
        $this->updateEnvFile([
            'MAIL_MAILER' => $this->mail_mailer,
            'MAIL_HOST' => $this->mail_host,
            'MAIL_PORT' => $this->mail_port,
            'MAIL_USERNAME' => $this->mail_username,
            'MAIL_PASSWORD' => $this->mail_password,
            'MAIL_ENCRYPTION' => $this->mail_encryption,
            'MAIL_FROM_ADDRESS' => $this->mail_from_address,
            'MAIL_FROM_NAME' => '"' . $this->mail_from_name . '"',
        ]);

        session()->flash('success', 'Email settings saved successfully!');
    }

    public function saveAppSettings()
    {
        $this->validate([
            'app_name' => 'required|string|max:255',
            'app_url' => 'required|url',
            'app_timezone' => 'required|string',
            'homepage_client_logo_gap' => 'required|integer|min:8|max:80',
            'homepage_client_logo_height' => 'required|integer|min:40|max:140',
        ]);

        AdminSetting::set('app_name', $this->app_name, 'text', 'app', 'Application Name');
        AdminSetting::set('app_url', $this->app_url, 'url', 'app', 'Application URL');
        AdminSetting::set('app_timezone', $this->app_timezone, 'select', 'app', 'Timezone');
        AdminSetting::set('homepage_client_logo_gap', (string) $this->homepage_client_logo_gap, 'number', 'app', 'Homepage Client Logo Gap');
        AdminSetting::set('homepage_client_logo_height', (string) $this->homepage_client_logo_height, 'number', 'app', 'Homepage Client Logo Height');

        // Update environment file
        $this->updateEnvFile([
            'APP_NAME' => '"' . $this->app_name . '"',
            'APP_URL' => $this->app_url,
            'APP_TIMEZONE' => $this->app_timezone,
        ]);

        session()->flash('success', 'Application settings saved successfully!');
    }

    private function updateEnvFile($data)
    {
        $envFile = app()->environmentFilePath();
        $str = file_get_contents($envFile);

        foreach ($data as $key => $value) {
            $str = preg_replace("/^{$key}=.*$/m", "{$key}={$value}", $str);
            
            // If key doesn't exist, add it
            if (strpos($str, $key) === false) {
                $str .= "\n{$key}={$value}";
            }
        }

        file_put_contents($envFile, $str);
    }

    public function testEmailSettings()
    {
        try {
            // Test email configuration
            $user = Auth::user();
            \Mail::raw('This is a test email from AI Agent System admin panel.', function ($message) use ($user) {
                $message->to($user->email)
                        ->subject('Test Email from AI Agent System');
            });

            session()->flash('success', 'Test email sent successfully!');
        } catch (\Exception $e) {
            session()->flash('error', 'Failed to send test email: ' . $e->getMessage());
        }
    }

    public function saveAiSettings()
    {
        $this->validate([
            'ai_model_provider' => 'required|in:openai,llama',
            'ai_backend_type' => 'required|in:ollama,llamacpp',
            'openai_api_key' => 'nullable|string',
            'openai_default_model' => 'nullable|string',
            'llama_default_model' => 'nullable|string',
            'ai_context_relevance_model' => 'nullable|string',
            'ai_context_relevance_min_confidence' => 'nullable|numeric|min:0|max:1',
            'llamacpp_model_path' => 'nullable|string',
            'llamacpp_model_repo' => 'nullable|string',
            'llamacpp_threads' => 'nullable|integer|min:1|max:32',
            'llamacpp_context_length' => 'nullable|integer|min:512|max:8192',
            'ai_use_intent_rewrite' => 'boolean',
            'global_query_translation_map' => 'nullable|string|max:12000',
            'global_query_alias_map' => 'nullable|string|max:12000',
            'vastai_ssh_host' => 'required|string|max:255',
            'vastai_ssh_port' => 'required|integer|min:1|max:65535',
            'vastai_ssh_user' => 'required|string|max:64',
        ]);

        // Save to admin settings
        AdminSetting::set('ai_model_provider', $this->ai_model_provider, 'select', 'ai', 'AI Model Provider');
        AdminSetting::set('ai_backend_type', $this->ai_backend_type, 'select', 'ai', 'AI Backend Type');
        AdminSetting::set('openai_api_key', $this->openai_api_key, 'password', 'ai', 'OpenAI API Key', null, true);
        AdminSetting::set('openai_default_model', $this->openai_default_model, 'text', 'ai', 'OpenAI Default Model');
        AdminSetting::set('llama_default_model', $this->llama_default_model, 'select', 'ai', 'Llama Default Model');
        AdminSetting::set('ai_context_relevance_model', $this->ai_context_relevance_model, 'select', 'ai', 'Context Relevance Judge Model');
        AdminSetting::set('ai_context_relevance_min_confidence', (string) $this->ai_context_relevance_min_confidence, 'number', 'ai', 'Context Relevance Minimum Confidence');
        AdminSetting::set('llamacpp_model_path', $this->llamacpp_model_path, 'text', 'ai', 'llama.cpp Model Path');
        AdminSetting::set('llamacpp_model_repo', $this->llamacpp_model_repo, 'select', 'ai', 'llama.cpp Model Repository');
        AdminSetting::set('llamacpp_threads', $this->llamacpp_threads, 'number', 'ai', 'llama.cpp Threads');
        AdminSetting::set('llamacpp_context_length', $this->llamacpp_context_length, 'number', 'ai', 'llama.cpp Context Length');
        AdminSetting::set('ai_use_intent_rewrite', $this->ai_use_intent_rewrite ? '1' : '0', 'boolean', 'ai', 'Use intent + query rewrite');
        AdminSetting::set('global_query_translation_map', trim((string) $this->global_query_translation_map), 'textarea', 'ai', 'Global Query Translation Map');
        AdminSetting::set('global_query_alias_map', trim((string) $this->global_query_alias_map), 'textarea', 'ai', 'Global Query Alias Map');
        AdminSetting::set('vastai_ssh_host', trim((string) $this->vastai_ssh_host), 'text', 'ai', 'Vast.ai SSH Host');
        AdminSetting::set('vastai_ssh_port', (string) $this->vastai_ssh_port, 'number', 'ai', 'Vast.ai SSH Port');
        AdminSetting::set('vastai_ssh_user', trim((string) $this->vastai_ssh_user), 'text', 'ai', 'Vast.ai SSH User');

        VastAiConfig::writeShellEnvFile();

        // Update environment file
        $envUpdates = [
            'AI_MODEL_PROVIDER' => $this->ai_model_provider,
            'AI_BACKEND_TYPE' => $this->ai_backend_type,
        ];
        
        if ($this->openai_api_key) {
            $envUpdates['OPENAI_API_KEY'] = $this->openai_api_key;
        }

        $this->updateEnvFile($envUpdates);

        \Log::info('AI Settings saved successfully', [
            'provider' => $this->ai_model_provider,
            'backend_type' => $this->ai_backend_type,
            'llama_model' => $this->llama_default_model,
            'context_relevance_model' => $this->ai_context_relevance_model,
            'context_relevance_min_confidence' => (float) $this->ai_context_relevance_min_confidence,
            'openai_model' => $this->openai_default_model,
            'has_global_translation_map' => trim((string) $this->global_query_translation_map) !== '',
            'has_global_alias_map' => trim((string) $this->global_query_alias_map) !== '',
            'vastai_host' => $this->vastai_ssh_host,
            'vastai_port' => (int) $this->vastai_ssh_port,
            'vastai_user' => $this->vastai_ssh_user,
        ]);
        
        session()->flash('success', 'AI settings saved successfully!');
    }

    public function render()
    {
        // Use the admin layout explicitly to avoid MissingLayoutException
        return view('livewire.admin.settings-manager')
            ->layout('layouts.admin');
    }
}
