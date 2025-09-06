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
    
    public function mount()
    {
        $this->loadSettings();
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

    public function render()
    {
        // Use the admin layout explicitly to avoid MissingLayoutException
        return view('livewire.admin.settings-manager')
            ->layout('layouts.admin');
    }
}
