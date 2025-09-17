<?php

namespace App\Livewire\Admin;

use Livewire\Component;
use App\Models\AdminSetting;
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
    
    // AI Settings
    public $ai_model_provider = 'llama';
    public $ai_backend_type = 'ollama'; // ollama or llamacpp
    public $openai_api_key = '';
    public $openai_default_model = 'gpt-5-mini'; // Only allowed model
    public $llama_default_model = 'llama3.2:3b';
    public $llamacpp_model_path = '';
    public $llamacpp_model_repo = 'custom/Llama-3.2-3B-Instruct-Q8_0-Custom';
    public $llamacpp_threads = 4;
    public $llamacpp_context_length = 4096;
    
    public function mount()
    {
        $this->loadSettings();
    }
    
    public function getAvailableLlamaModels()
    {
        return [
            'llama3.2:1b' => 'Llama 3.2:1B (Fast, lightweight)',
            'llama3.2:3b' => 'Llama 3.2:3B (Balanced quality/speed)',
            'llama3.2:3b-instruct-gguf' => 'Llama 3.2:3B Instruct GGUF (llama.cpp optimized)',
            'mistral:7b' => 'Mistral 7B (High quality, slower)',
            'gemma:2b' => 'Gemma 2B (Google, fast)',
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
        
        try {
            $startTime = microtime(true);
            
            // Test with Ollama API
            $response = \Http::timeout(30)->post('http://localhost:11434/api/generate', [
                'model' => $testModel,
                'prompt' => $testPrompt,
                'stream' => false
            ]);
            
            $endTime = microtime(true);
            $responseTime = round(($endTime - $startTime), 2);
            
            if ($response->successful()) {
                $data = $response->json();
                $responseText = $data['response'] ?? 'No response received';
                
                session()->flash('success', "✅ Model '{$testModel}' test successful! Response time: {$responseTime}s. Response: " . substr($responseText, 0, 100) . "...");
            } else {
                session()->flash('error', "❌ Model test failed. HTTP status: " . $response->status());
            }
            
        } catch (\Exception $e) {
            session()->flash('error', "❌ Model test error: " . $e->getMessage());
        }
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
        
        // AI Settings
        $this->ai_model_provider = AdminSetting::get('ai_model_provider', config('app.ai_model_provider', 'llama'));
        $this->ai_backend_type = AdminSetting::get('ai_backend_type', 'ollama');
        $this->openai_api_key = AdminSetting::get('openai_api_key', '');
        $this->openai_default_model = AdminSetting::get('openai_default_model', 'gpt-5-mini');
        $this->llama_default_model = AdminSetting::get('llama_default_model', 'llama3.2:1b');
        $this->llamacpp_model_path = AdminSetting::get('llamacpp_model_path', '');
        $this->llamacpp_model_repo = AdminSetting::get('llamacpp_model_repo', 'custom/Llama-3.2-3B-Instruct-Q8_0-Custom');
        $this->llamacpp_threads = AdminSetting::get('llamacpp_threads', 4);
        $this->llamacpp_context_length = AdminSetting::get('llamacpp_context_length', 4096);
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
        ]);

        AdminSetting::set('app_name', $this->app_name, 'text', 'app', 'Application Name');
        AdminSetting::set('app_url', $this->app_url, 'url', 'app', 'Application URL');
        AdminSetting::set('app_timezone', $this->app_timezone, 'select', 'app', 'Timezone');

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
            'llamacpp_model_path' => 'nullable|string',
            'llamacpp_model_repo' => 'nullable|string',
            'llamacpp_threads' => 'nullable|integer|min:1|max:32',
            'llamacpp_context_length' => 'nullable|integer|min:512|max:8192',
        ]);

        // Save to admin settings
        AdminSetting::set('ai_model_provider', $this->ai_model_provider, 'select', 'ai', 'AI Model Provider');
        AdminSetting::set('ai_backend_type', $this->ai_backend_type, 'select', 'ai', 'AI Backend Type');
        AdminSetting::set('openai_api_key', $this->openai_api_key, 'password', 'ai', 'OpenAI API Key', null, true);
        AdminSetting::set('openai_default_model', $this->openai_default_model, 'text', 'ai', 'OpenAI Default Model');
        AdminSetting::set('llama_default_model', $this->llama_default_model, 'select', 'ai', 'Llama Default Model');
        AdminSetting::set('llamacpp_model_path', $this->llamacpp_model_path, 'text', 'ai', 'llama.cpp Model Path');
        AdminSetting::set('llamacpp_model_repo', $this->llamacpp_model_repo, 'select', 'ai', 'llama.cpp Model Repository');
        AdminSetting::set('llamacpp_threads', $this->llamacpp_threads, 'number', 'ai', 'llama.cpp Threads');
        AdminSetting::set('llamacpp_context_length', $this->llamacpp_context_length, 'number', 'ai', 'llama.cpp Context Length');

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
            'openai_model' => $this->openai_default_model
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
