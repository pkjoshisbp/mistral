<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\DemoOrganization;

class DemoOrganizationSeeder extends Seeder
{
    public function run(): void
    {
        $demoOrgs = [
            [
                'industry' => 'healthcare',
                'name' => 'MediCare Plus Hospital',
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
                    'What are the costs for a routine checkup?'
                ],
                'is_active' => true
            ],
            [
                'industry' => 'education',
                'name' => 'Tech Valley University',
                'description' => 'Premier educational institution specializing in technology, engineering, and business programs with cutting-edge research facilities.',
                'features' => [
                    'Computer Science Programs',
                    'Engineering Degrees',
                    'MBA and Business Programs',
                    'Research Labs and Innovation Centers',
                    'Online Learning Platform',
                    'Career Services and Job Placement'
                ],
                'sample_questions' => [
                    'What programs do you offer?',
                    'How do I apply for admission?',
                    'What are the tuition fees?',
                    'Do you offer scholarships?',
                    'Can I take courses online?',
                    'What is the student-to-faculty ratio?'
                ],
                'is_active' => true
            ],
            [
                'industry' => 'automotive',
                'name' => 'AutoMax Service Center',
                'description' => 'Full-service automotive repair and maintenance center with certified technicians and state-of-the-art equipment.',
                'features' => [
                    'Complete Auto Repair Services',
                    'Oil Changes and Maintenance',
                    'Brake and Transmission Service',
                    'Engine Diagnostics',
                    'Tire Installation and Balancing',
                    'State Inspection Services'
                ],
                'sample_questions' => [
                    'Do you service all car makes and models?',
                    'How much does an oil change cost?',
                    'Do you offer warranties on repairs?',
                    'Can I schedule an appointment online?',
                    'Do you provide free estimates?',
                    'How long do repairs typically take?'
                ],
                'is_active' => true
            ],
            [
                'industry' => 'finance',
                'name' => 'SecureBank Financial',
                'description' => 'Community bank offering personal and business banking, loans, and investment services with personalized customer care.',
                'features' => [
                    'Personal and Business Banking',
                    'Home and Auto Loans',
                    'Investment and Retirement Planning',
                    'Online and Mobile Banking',
                    'Business Credit Lines',
                    'Wealth Management Services'
                ],
                'sample_questions' => [
                    'What types of accounts do you offer?',
                    'How do I apply for a mortgage?',
                    'What are your current interest rates?',
                    'Do you have mobile banking?',
                    'How do I set up online bill pay?',
                    'What investment options are available?'
                ],
                'is_active' => true
            ],
            [
                'industry' => 'restaurant',
                'name' => 'Bella Vista Italian Restaurant',
                'description' => 'Authentic Italian dining experience featuring traditional recipes, fresh ingredients, and a warm family atmosphere.',
                'features' => [
                    'Authentic Italian Cuisine',
                    'Fresh Pasta Made Daily',
                    'Wine Selection from Italy',
                    'Private Dining Rooms',
                    'Catering Services',
                    'Outdoor Patio Seating'
                ],
                'sample_questions' => [
                    'Do you take reservations?',
                    'What are your hours of operation?',
                    'Do you offer gluten-free options?',
                    'Can you accommodate large parties?',
                    'Do you provide catering services?',
                    'What payment methods do you accept?'
                ],
                'is_active' => true
            ],
            [
                'industry' => 'legal',
                'name' => 'Johnson & Associates Law Firm',
                'description' => 'Experienced legal professionals providing comprehensive legal services in personal injury, family law, and business litigation.',
                'features' => [
                    'Personal Injury Law',
                    'Family Law and Divorce',
                    'Business and Corporate Law',
                    'Real Estate Transactions',
                    'Estate Planning and Wills',
                    'Criminal Defense'
                ],
                'sample_questions' => [
                    'Do you offer free consultations?',
                    'What areas of law do you practice?',
                    'How are your fees structured?',
                    'How long do cases typically take?',
                    'Can you help with business contracts?',
                    'Do you handle criminal cases?'
                ],
                'is_active' => true
            ]
        ];

        foreach ($demoOrgs as $org) {
            DemoOrganization::create($org);
        }
    }
}
