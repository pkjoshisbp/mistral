<?php

namespace App\Livewire;

use Livewire\Component;
use App\Models\AdminSetting;
use Illuminate\Support\Facades\Mail;
use App\Mail\ContactFormMail;

class ContactPageManager extends Component
{
    public $editMode = false;
    public $title = '';
    public $subtitle = '';
    public $description = '';
    public $email = '';
    public $phone = '';
    public $primary_address = '';
    public $secondary_address = '';
    public $address = '';
    public $business_hours = '';
    public $map_embed = '';

    // Contact form fields
    public $contactName = '';
    public $contactEmail = '';
    public $contactPhone = '';
    public $contactSubject = '';
    public $contactMessage = '';
    public $mathAnswer = '';
    public $honeypot = ''; // Honeypot field - should stay empty
    public $contactSubmitted = false;

    // Math captcha
    public $mathQuestion = '';
    public $mathSolution = 0;

    protected $listeners = ['enableEditMode', 'disableEditMode'];

    public function mount()
    {
        $this->loadContactContent();
        $this->generateMathCaptcha();
    }

    public function loadContactContent()
    {
        $this->title = AdminSetting::get('contact_title', 'Get in Touch');
        $this->subtitle = AdminSetting::get('contact_subtitle', 'We\'d love to hear from you');
        $this->description = AdminSetting::get('contact_description', 'Have questions about our AI Chat Support service? Need help with your subscription? Our team is here to assist you.');
        $this->email = AdminSetting::get('contact_email', 'support@ai-chat.support');
        $this->phone = AdminSetting::get('contact_phone', '+1 (555) 123-4567');
        $this->primary_address = AdminSetting::get('contact_primary_address', 'Road No. 16, Bhagirathi House, Plot No. 195, opposite Park, near VLR Residency, Journalists Colony Phase 3, Gachibowli, Hyderabad, Telangana 500032');
        $legacyAddress = AdminSetting::get('contact_address', '123 AI Street, Tech City, TC 12345');
        $this->secondary_address = AdminSetting::get('contact_secondary_address', $legacyAddress);
        $this->address = $this->primary_address;
        $this->business_hours = AdminSetting::get('contact_business_hours', 'Monday - Friday: 11:00 AM - 11:30 PM IST');
        $this->map_embed = AdminSetting::get('contact_map_embed', '');
    }

    public function enableEditMode()
    {
        $this->editMode = true;
    }

    public function disableEditMode()
    {
        $this->editMode = false;
    }

    public function saveContent()
    {
        $this->validate([
            'title' => 'required|string|max:255',
            'subtitle' => 'required|string|max:255',
            'description' => 'required|string',
            'email' => 'required|email',
            'phone' => 'required|string|max:50',
            'primary_address' => 'required|string',
            'secondary_address' => 'nullable|string',
            'business_hours' => 'required|string',
        ]);

        AdminSetting::set('contact_title', $this->title);
        AdminSetting::set('contact_subtitle', $this->subtitle);
        AdminSetting::set('contact_description', $this->description);
        AdminSetting::set('contact_email', $this->email);
        AdminSetting::set('contact_phone', $this->phone);
        AdminSetting::set('contact_primary_address', $this->primary_address);
        AdminSetting::set('contact_secondary_address', $this->secondary_address);
        AdminSetting::set('contact_address', $this->primary_address);
        AdminSetting::set('contact_business_hours', $this->business_hours);
        AdminSetting::set('contact_map_embed', $this->map_embed);

        $this->editMode = false;
        session()->flash('message', 'Contact content updated successfully!');
    }

    public function generateMathCaptcha()
    {
        $num1 = rand(1, 20);
        $num2 = rand(1, 20);
        $operators = ['+', '-'];
        $operator = $operators[array_rand($operators)];
        
        if ($operator === '+') {
            $this->mathSolution = $num1 + $num2;
            $this->mathQuestion = "$num1 + $num2";
        } else {
            // Ensure positive result for subtraction
            if ($num1 < $num2) {
                $temp = $num1;
                $num1 = $num2;
                $num2 = $temp;
            }
            $this->mathSolution = $num1 - $num2;
            $this->mathQuestion = "$num1 - $num2";
        }
    }

    public function submitContactForm()
    {
        // Honeypot check - if filled, it's likely a bot
        if (!empty($this->honeypot)) {
            session()->flash('error', 'Form submission failed. Please try again.');
            return;
        }

        // Validate form
        $this->validate([
            'contactName' => 'required|string|max:255',
            'contactEmail' => 'required|email|max:255',
            'contactSubject' => 'required|string|max:255',
            'contactMessage' => 'required|string|max:2000',
            'mathAnswer' => 'required|numeric',
        ], [
            'contactName.required' => 'Name is required.',
            'contactEmail.required' => 'Email is required.',
            'contactEmail.email' => 'Please enter a valid email address.',
            'contactSubject.required' => 'Subject is required.',
            'contactMessage.required' => 'Message is required.',
            'mathAnswer.required' => 'Please solve the math problem.',
            'mathAnswer.numeric' => 'Please enter a number for the math answer.',
        ]);

        // Check math captcha
        if ((int)$this->mathAnswer !== $this->mathSolution) {
            $this->addError('mathAnswer', 'Incorrect answer to the math problem. Please try again.');
            $this->generateMathCaptcha(); // Generate new question
            $this->mathAnswer = '';
            return;
        }

        try {
            // Send email
            $contactData = [
                'name' => $this->contactName,
                'email' => $this->contactEmail,
                'phone' => $this->contactPhone,
                'subject' => $this->contactSubject,
                'message' => $this->contactMessage,
                'ip_address' => request()->ip(),
                'user_agent' => request()->userAgent(),
                'submitted_at' => now()->format('Y-m-d H:i:s'),
            ];

            // Send to admin email
            $adminEmail = AdminSetting::get('contact_email', 'info@ai-chat.support');
            Mail::to($adminEmail)->send(new ContactFormMail($contactData));

            // Reset form
            $this->contactName = '';
            $this->contactEmail = '';
            $this->contactPhone = '';
            $this->contactSubject = '';
            $this->contactMessage = '';
            $this->mathAnswer = '';
            $this->honeypot = '';
            $this->contactSubmitted = true;
            $this->generateMathCaptcha();

        } catch (\Exception $e) {
            \Log::error('Contact form error: ' . $e->getMessage());
            session()->flash('error', 'Sorry, there was an error sending your message. Please try again or email us directly.');
        }
    }

    public function resetContactForm()
    {
        $this->contactSubmitted = false;
        $this->generateMathCaptcha();
    }

    public function render()
    {
        return view('livewire.contact-page-manager');
    }
}
