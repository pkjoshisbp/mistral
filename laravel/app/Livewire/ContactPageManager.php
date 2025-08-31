<?php

namespace App\Livewire;

use Livewire\Component;
use App\Models\AdminSetting;

class ContactPageManager extends Component
{
    public $editMode = false;
    public $title = '';
    public $subtitle = '';
    public $description = '';
    public $email = '';
    public $phone = '';
    public $address = '';
    public $business_hours = '';
    public $map_embed = '';

    protected $listeners = ['enableEditMode', 'disableEditMode'];

    public function mount()
    {
        $this->loadContactContent();
    }

    public function loadContactContent()
    {
        $this->title = AdminSetting::get('contact_title', 'Get in Touch');
        $this->subtitle = AdminSetting::get('contact_subtitle', 'We\'d love to hear from you');
        $this->description = AdminSetting::get('contact_description', 'Have questions about our AI Chat Support service? Need help with your subscription? Our team is here to assist you.');
        $this->email = AdminSetting::get('contact_email', 'support@ai-chat.support');
        $this->phone = AdminSetting::get('contact_phone', '+1 (555) 123-4567');
        $this->address = AdminSetting::get('contact_address', '123 AI Street, Tech City, TC 12345');
        $this->business_hours = AdminSetting::get('contact_business_hours', 'Monday - Friday: 9:00 AM - 6:00 PM EST');
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
            'address' => 'required|string',
            'business_hours' => 'required|string',
        ]);

        AdminSetting::set('contact_title', $this->title);
        AdminSetting::set('contact_subtitle', $this->subtitle);
        AdminSetting::set('contact_description', $this->description);
        AdminSetting::set('contact_email', $this->email);
        AdminSetting::set('contact_phone', $this->phone);
        AdminSetting::set('contact_address', $this->address);
        AdminSetting::set('contact_business_hours', $this->business_hours);
        AdminSetting::set('contact_map_embed', $this->map_embed);

        $this->editMode = false;
        session()->flash('message', 'Contact content updated successfully!');
    }

    public function render()
    {
        return view('livewire.contact-page-manager');
    }
}
