<?php

namespace App\Services;

use App\Models\DemoOrganization;
use Illuminate\Support\Facades\Log;
use Exception;

class DemoQdrantService
{
    private $aiService;

    public function __construct()
    {
        $this->aiService = app(\App\Services\AiAgentService::class);
    }

    /**
     * Create demo collections for all active demo organizations
     */
    public function syncAllDemoCollections()
    {
        $demos = DemoOrganization::where('is_active', true)->get();
        
        foreach ($demos as $demo) {
            $this->syncDemoCollection($demo);
        }
        
        return [
            'success' => true,
            'synced' => $demos->count(),
            'message' => "Synced {$demos->count()} demo collections"
        ];
    }

    /**
     * Create and populate a demo collection for a specific organization
     */
    public function syncDemoCollection(DemoOrganization $demo)
    {
        $collectionName = "demo_{$demo->industry}";
        
        try {
            Log::info("Starting demo collection sync", [
                'collection' => $collectionName,
                'industry' => $demo->industry
            ]);
            
            // Check if collection exists, create if not
            if (!$this->aiService->collectionExists($collectionName)) {
                $createResult = $this->aiService->createCollection($collectionName);
                Log::info("Collection creation result", [
                    'collection' => $collectionName,
                    'result' => $createResult
                ]);
                
                if (!$createResult || (is_array($createResult) && isset($createResult['status']) && $createResult['status'] !== 'success')) {
                    throw new Exception("Failed to create collection: {$collectionName}");
                }
                Log::info("Created new demo collection", ['collection' => $collectionName]);
            } else {
                Log::info("Demo collection already exists", ['collection' => $collectionName]);
            }
            
            // Note: We'll create a new collection each time, which effectively clears old data
            
            // Generate FAQ data for this demo
            $faqData = $this->generateDemoFAQs($demo);
            
            // Insert FAQ data into collection using AiAgentService
            foreach ($faqData as $index => $faq) {
                $text = $faq['question'] . ' ' . $faq['answer'];
                
                // Generate embedding for the text
                $embedding = $this->aiService->embed($text);
                
                if ($embedding && is_array($embedding)) {
                    // Add to Qdrant collection
                    $success = $this->aiService->addToQdrant(
                        $collectionName,
                        $embedding,
                        $faq, // metadata payload
                        $index + 1 // unique ID
                    );
                    
                    if ($success) {
                        Log::info("Added FAQ to collection", [
                            'collection' => $collectionName,
                            'question' => substr($faq['question'], 0, 50) . '...'
                        ]);
                    } else {
                        Log::warning("Failed to add FAQ to collection", [
                            'collection' => $collectionName,
                            'question' => $faq['question']
                        ]);
                    }
                } else {
                    Log::error("Failed to generate embedding", [
                        'collection' => $collectionName,
                        'question' => $faq['question'],
                        'embedding_result' => $embedding
                    ]);
                }
            }
            
            Log::info("Demo collection synced successfully", [
                'collection' => $collectionName,
                'industry' => $demo->industry,
                'faqs_count' => count($faqData)
            ]);
            
            return true;
            
        } catch (Exception $e) {
            Log::error("Failed to sync demo collection", [
                'collection' => $collectionName,
                'industry' => $demo->industry,
                'error' => $e->getMessage()
            ]);
            
            return false;
        }
    }



    /**
     * Generate demo-specific FAQs based on demo organization data
     */
    private function generateDemoFAQs(DemoOrganization $demo)
    {
        $baseQuestions = $demo->sample_questions ?: [];
        $features = $demo->features ?: [];
        
        $faqData = [];
        
        // Create FAQ entries based on sample questions and features
        $industrySpecificFAQs = $this->getIndustrySpecificFAQs($demo->industry, $demo->name);
        
        foreach ($baseQuestions as $index => $question) {
            $answer = $industrySpecificFAQs[$question] ?? $this->generateGenericAnswer($question, $demo);
            
            $faqData[] = [
                'id' => "demo_{$demo->industry}_{$index}",
                'question' => $question,
                'answer' => $answer,
                'category' => $demo->industry,
                'organization' => $demo->name,
                'features' => $features
            ];
        }
        
        // Add feature-based FAQs
        foreach ($features as $index => $feature) {
            $question = "Tell me about {$feature}";
            $answer = $this->generateFeatureAnswer($feature, $demo);
            
            $faqData[] = [
                'id' => "demo_{$demo->industry}_feature_{$index}",
                'question' => $question,
                'answer' => $answer,
                'category' => $demo->industry,
                'organization' => $demo->name,
                'features' => $features
            ];
        }
        
        return $faqData;
    }

    /**
     * Get industry-specific FAQ answers
     */
    private function getIndustrySpecificFAQs($industry, $orgName)
    {
        $faqs = [];
        
        switch ($industry) {
            case 'healthcare':
                $faqs = [
                    'What are your visiting hours?' => "Our visiting hours are Monday-Friday: 8:00 AM - 8:00 PM, Saturday-Sunday: 10:00 AM - 6:00 PM. ICU visiting hours are more flexible for immediate family members.",
                    'Do you accept my insurance?' => "We accept most major insurance plans including Blue Cross, Aetna, Cigna, and UnitedHealth. Please bring your insurance card for verification.",
                    'How do I schedule an appointment?' => "You can schedule an appointment by calling our main number, using our online portal, or through our mobile app. We typically have same-day appointments available.",
                    'What emergency services do you provide?' => "We have a 24/7 emergency department with trauma care, cardiac services, and specialized emergency medicine physicians.",
                    'Do you have a cardiac care unit?' => "Yes, we have a dedicated cardiac care unit with specialized cardiologists and state-of-the-art monitoring equipment.",
                    'What are the costs for a routine checkup?' => "Routine checkup costs vary based on insurance coverage. With most insurance plans, you'll pay a copay of $25-50. We can verify your specific coverage."
                ];
                break;
                
            case 'education':
                $faqs = [
                    'What programs do you offer?' => "We offer undergraduate and graduate programs in Computer Science, Engineering, Business, Liberal Arts, and Sciences. We also have professional development and continuing education programs.",
                    'How do I apply for admission?' => "Applications can be submitted online through our admissions portal. We require transcripts, test scores, essays, and letters of recommendation. Application deadlines vary by program.",
                    'What are the tuition fees?' => "Tuition for undergraduate programs is $35,000 per year. Graduate programs range from $40,000-60,000 annually. Financial aid and scholarships are available.",
                    'Do you offer scholarships?' => "Yes, we offer merit-based scholarships, need-based financial aid, and specialized scholarships for various fields of study. Apply early for best consideration.",
                    'Can I take courses online?' => "We offer hybrid and fully online programs in select majors. Most courses have both in-person and online options to accommodate different learning preferences.",
                    'What is the student-to-faculty ratio?' => "Our student-to-faculty ratio is 15:1, ensuring personalized attention and mentorship opportunities for all students."
                ];
                break;
                
            case 'automotive':
                $faqs = [
                    'Do you service all car makes and models?' => "Yes, we service all makes and models of cars, trucks, and SUVs. Our certified technicians have experience with domestic, European, and Asian vehicles.",
                    'How much does an oil change cost?' => "Standard oil changes start at $29.99 for conventional oil. Synthetic oil changes are $49.99. We often have promotions and discounts available.",
                    'Do you offer warranties on repairs?' => "All repairs come with our comprehensive warranty - 12 months or 12,000 miles, whichever comes first. We stand behind our work 100%.",
                    'Can I schedule an appointment online?' => "Yes, you can schedule appointments through our website or mobile app. We also accept walk-ins, though appointments get priority service.",
                    'Do you provide free estimates?' => "We provide free estimates for all repair work. Our diagnostic fee is waived if you proceed with recommended repairs.",
                    'How long do repairs typically take?' => "Most routine maintenance takes 30-60 minutes. Larger repairs may take 1-3 days depending on parts availability and complexity."
                ];
                break;
                
            case 'finance':
                $faqs = [
                    'What types of accounts do you offer?' => "We offer checking, savings, money market, CDs, and specialized accounts like HSAs and IRAs. Each account type has different benefits and requirements.",
                    'How do I apply for a mortgage?' => "Mortgage applications can be started online or in-branch. You'll need income documentation, credit history, and down payment information. Pre-approval takes 24-48 hours.",
                    'What are your current interest rates?' => "Interest rates vary by account type and loan product. Current mortgage rates start at 6.5% APR. Contact us for personalized rate quotes.",
                    'Do you have mobile banking?' => "Yes, our mobile app offers full banking services including mobile deposits, bill pay, transfers, and account management. Download it from your app store.",
                    'How do I set up online bill pay?' => "Online bill pay is available through our website and mobile app. You can set up one-time or recurring payments to any payee in the US.",
                    'What investment options are available?' => "We offer investment accounts, retirement planning, mutual funds, and work with investment advisors to create personalized portfolios."
                ];
                break;
                
            case 'restaurant':
                $faqs = [
                    'Do you take reservations?' => "Yes, we accept reservations for parties of 2 or more. You can book online, call us, or use OpenTable. Walk-ins are welcome but may have a wait during peak hours.",
                    'What are your hours of operation?' => "We're open Tuesday-Thursday: 5:00 PM - 10:00 PM, Friday-Saturday: 5:00 PM - 11:00 PM, Sunday: 4:00 PM - 9:00 PM. Closed Mondays.",
                    'Do you offer gluten-free options?' => "Yes, we have several gluten-free pasta options and can modify many dishes. Please inform your server of any dietary restrictions.",
                    'Can you accommodate large parties?' => "We can accommodate parties up to 20 people. For groups of 8 or more, we recommend making a reservation and we can arrange special seating.",
                    'Do you provide catering services?' => "Yes, we offer catering for events of all sizes. Our catering menu includes appetizers, main courses, and desserts. Contact us for custom quotes.",
                    'What payment methods do you accept?' => "We accept cash, all major credit cards, and contactless payments like Apple Pay and Google Pay. We also offer gift cards."
                ];
                break;
                
            case 'legal':
                $faqs = [
                    'Do you offer free consultations?' => "Yes, we offer free 30-minute consultations for new clients to discuss your case and determine how we can help.",
                    'What areas of law do you practice?' => "We specialize in personal injury, family law, criminal defense, business law, real estate, and estate planning. Our attorneys have decades of combined experience.",
                    'How are your fees structured?' => "Fee structures vary by case type. Personal injury cases are handled on contingency (no fee unless we win). Other cases may be hourly or flat fee.",
                    'How long do cases typically take?' => "Case duration varies greatly depending on complexity. Simple matters may resolve in weeks, while complex litigation can take months or years.",
                    'Can you help with business contracts?' => "Yes, we provide comprehensive business legal services including contract drafting, review, negotiation, and dispute resolution.",
                    'Do you handle criminal cases?' => "Yes, we have experienced criminal defense attorneys who handle everything from misdemeanors to serious felonies."
                ];
                break;
        }
        
        return $faqs;
    }

    private function generateGenericAnswer($question, $demo)
    {
        return "Thank you for asking about this. At {$demo->name}, we strive to provide excellent service. For specific details about '{$question}', please contact us directly and we'll be happy to help you with personalized information.";
    }

    private function generateFeatureAnswer($feature, $demo)
    {
        return "Our {$feature} service at {$demo->name} is designed to meet your needs with quality and reliability. {$demo->description} Contact us to learn more about how this service can benefit you.";
    }

}
