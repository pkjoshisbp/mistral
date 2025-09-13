<?php
/**
 * Fix Healthcare Demo Script
 * Restores proper healthcare FAQs and sample questions
 */

require_once __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\DemoOrganization;
use App\Models\Organization;

echo "\n=== Healthcare Demo Restoration ===\n";

try {
    // Check current healthcare demo
    $healthcareDemo = DemoOrganization::where('industry', 'healthcare')->first();
    
    if ($healthcareDemo) {
        echo "Found current healthcare demo: {$healthcareDemo->name}\n";
        echo "Current sample questions:\n";
        foreach ($healthcareDemo->sample_questions ?? [] as $q) {
            echo "  - $q\n";
        }
        
        // Restore proper healthcare questions
        $properHealthcareQuestions = [
            'What are your visiting hours?',
            'Do you accept my insurance?',
            'How do I schedule an appointment?',
            'What emergency services do you provide?',
            'Do you have a cardiac care unit?',
            'What are the costs for a routine checkup?',
            'Do you offer preventive care services?',
            'What specialists do you have available?',
            'How do I access my medical records?',
            'Do you provide maternity services?'
        ];
        
        echo "\nRestoring proper healthcare questions...\n";
        
        $healthcareDemo->update([
            'name' => 'Healthcare Demo',
            'description' => 'Leading healthcare provider offering comprehensive medical services including emergency care, surgery, and specialized treatments.',
            'features' => [
                'Emergency Services 24/7',
                'Specialized Surgery Units', 
                'Advanced Diagnostic Imaging',
                'Cardiology Department',
                'Oncology Treatment Center',
                'Maternity and Pediatric Care'
            ],
            'sample_questions' => $properHealthcareQuestions
        ]);
        
        echo "✅ Healthcare demo restored successfully!\n";
        echo "\nNew sample questions:\n";
        foreach ($properHealthcareQuestions as $q) {
            echo "  - $q\n";
        }
        
    } else {
        echo "No healthcare demo found. Creating new one...\n";
        
        // Find a healthcare organization to link to
        $organization = Organization::where('industry', 'healthcare')->first() 
                       ?? Organization::first();
        
        DemoOrganization::create([
            'industry' => 'healthcare',
            'name' => 'Healthcare Demo',
            'organization_id' => $organization->id,
            'description' => 'Leading healthcare provider offering comprehensive medical services including emergency care, surgery, and specialized treatments.',
            'features' => [
                'Emergency Services 24/7',
                'Specialized Surgery Units', 
                'Advanced Diagnostic Imaging',
                'Cardiology Department',
                'Oncology Treatment Center',
                'Maternity and Pediatric Care'
            ],
            'sample_questions' => [
                'What are your visiting hours?',
                'Do you accept my insurance?',
                'How do I schedule an appointment?',
                'What emergency services do you provide?',
                'Do you have a cardiac care unit?',
                'What are the costs for a routine checkup?',
                'Do you offer preventive care services?',
                'What specialists do you have available?',
                'How do I access my medical records?',
                'Do you provide maternity services?'
            ],
            'is_active' => true
        ]);
        
        echo "✅ New healthcare demo created!\n";
    }
    
} catch (\Exception $e) {
    echo "\n❌ Error: " . $e->getMessage() . "\n";
}

echo "\n=== Healthcare Demo Fixed ===\n";