<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

class CreateHospitalWhatsAppTemplate extends Command
{
    protected $signature = 'whatsapp:create-hospital-template';
    protected $description = 'Create WhatsApp template for hospital prospects with demo link';

    public function handle()
    {
        // Hospital/healthcare image from Pexels
        $imageUrl = 'https://images.pexels.com/photos/236380/pexels-photo-236380.jpeg?auto=compress&cs=tinysrgb&w=1260&h=750&dpr=1';

        // Get credentials from admin settings
        $wabaId = \App\Models\AdminSetting::get('whatsapp_business_account_id');
        $accessToken = \App\Models\AdminSetting::get('whatsapp_access_token');

        if (!$wabaId || !$accessToken) {
            $this->error('WhatsApp credentials not configured in Admin Settings');
            $this->info('Please configure: whatsapp_business_account_id and whatsapp_access_token');
            return 1;
        }

        $this->info('Step 1: Uploading hospital image to Facebook...');
        $uploadUrl = "https://graph.facebook.com/v21.0/{$wabaId}/media";

        $response = Http::asForm()
            ->withToken($accessToken)
            ->post($uploadUrl, [
                'messaging_product' => 'whatsapp',
                'file_url' => $imageUrl,
                'type' => 'image/jpeg'
            ]);

        if (!$response->successful()) {
            $this->error("Failed to upload image: " . $response->body());
            return 1;
        }

        $mediaId = $response->json()['id'] ?? null;
        if (!$mediaId) {
            $this->error('No media ID returned from upload');
            return 1;
        }

        $this->info("✓ Image uploaded successfully! Media ID: {$mediaId}");

        $this->info('Step 2: Creating WhatsApp template...');

        $components = [
            [
                'type' => 'HEADER',
                'format' => 'IMAGE',
                'example' => [
                    'header_handle' => [$mediaId]
                ]
            ],
            [
                'type' => 'BODY',
                'text' => "Transform your hospital's patient experience with AI-powered support! 🏥\n\nOur intelligent chat system provides:\n✅ 24/7 instant responses to patient inquiries\n✅ Appointment scheduling automation\n✅ Medical FAQs and general information\n✅ Multi-language support for diverse patients\n✅ Seamless handoff to human staff when needed\n\nSee it in action with our healthcare demo!"
            ],
            [
                'type' => 'BUTTONS',
                'buttons' => [[
                    'type' => 'URL',
                    'text' => 'Try Healthcare Demo',
                    'url' => 'https://ai-chat.support/demo/healthcare'
                ]]
            ]
        ];

        $payload = [
            'name' => 'hospital_ai_demo_v1',
            'category' => 'MARKETING',
            'language' => 'en',
            'components' => $components
        ];

        $templateUrl = "https://graph.facebook.com/v21.0/{$wabaId}/message_templates";

        $templateResponse = Http::withToken($accessToken)
            ->post($templateUrl, $payload);

        $this->info("Response status: {$templateResponse->status()}");
        
        if ($templateResponse->successful()) {
            $this->info("\n✅ Template created successfully!");
            $this->info("Template name: hospital_ai_demo_v1");
            $this->info("Status: Pending approval (usually takes a few minutes)");
            $this->info("\nTemplate details:");
            $this->line(json_encode($templateResponse->json(), JSON_PRETTY_PRINT));
            return 0;
        } else {
            $this->error("\n❌ Template creation failed");
            $this->error($templateResponse->body());
            return 1;
        }
    }
}
