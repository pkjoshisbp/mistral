<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\AiAgentService;

class CreateCarDealershipDemo extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'demo:create-automotive';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create car dealership demo data in Qdrant';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Creating car dealership demo data...');
        
        // Create car dealership demo data - need integer IDs for Qdrant
        $carDealershipData = [];
        $id = 1;
        
        $baseData = [
            [
                'question' => 'What car models do you have available?',
                'answer' => 'We have a wide selection of new and certified pre-owned vehicles including sedans, SUVs, trucks, and luxury cars from top brands like Toyota, Honda, Ford, BMW, and Mercedes-Benz.',
            ],
            [
                'question' => 'Do you offer financing options?',
                'answer' => 'Yes! We offer competitive financing options with rates as low as 2.9% APR for qualified buyers. We work with multiple lenders to get you the best deal.',
            ],
            [
                'question' => 'Can I trade in my current vehicle?',
                'answer' => 'Absolutely! We accept trade-ins and will provide you with a competitive market value assessment. Our trade-in process is quick and hassle-free.',
            ],
            [
                'question' => 'Do you have certified pre-owned vehicles?',
                'answer' => 'Yes, we have an extensive selection of certified pre-owned vehicles that come with extended warranties and have passed rigorous multi-point inspections.',
            ],
            [
                'question' => 'What is your warranty coverage?',
                'answer' => 'New vehicles come with full manufacturer warranty. Certified pre-owned vehicles include extended powertrain warranty up to 100,000 miles.',
            ],
            [
                'question' => 'Can I schedule a test drive?',
                'answer' => 'Of course! You can schedule a test drive online or call us. We encourage test drives to help you find the perfect vehicle.',
            ],
            [
                'question' => 'What are your current promotions?',
                'answer' => 'We have seasonal promotions including cash back offers, low APR financing, and lease specials. Check our website or visit our showroom for current deals.',
            ],
            [
                'question' => 'Do you deliver vehicles?',
                'answer' => 'Yes, we offer home delivery service within 50 miles for your convenience. Contact us to arrange vehicle delivery.',
            ]
        ];

        foreach ($baseData as $item) {
            $carDealershipData[] = array_merge($item, [
                'id' => "demo_automotive_$id",
                'category' => 'automotive',
                'organization' => 'AutoMax Dealership',
                'features' => ['New Vehicle Sales', 'Certified Pre-Owned', 'Financing Options', 'Trade-In Services', 'Home Delivery', 'Extended Warranties'],
                'qdrant_id' => $id
            ]);
            $id++;
        }

        // Add feature-specific entries
        $features = ['New Vehicle Sales', 'Certified Pre-Owned', 'Financing Options', 'Trade-In Services', 'Home Delivery', 'Extended Warranties'];
        foreach ($features as $index => $feature) {
            $carDealershipData[] = [
                'id' => "demo_automotive_feature_$index",
                'question' => "Tell me about $feature",
                'answer' => "Our $feature service at AutoMax Dealership is designed to meet your automotive needs with quality and reliability. Leading car dealership offering comprehensive vehicle sales including new cars, certified pre-owned vehicles, and exceptional customer service. Contact us to learn more about how this service can benefit you.",
                'category' => 'automotive',
                'organization' => 'AutoMax Dealership',
                'features' => $features,
                'qdrant_id' => $id
            ];
            $id++;
        }

        $this->info('Total entries: ' . count($carDealershipData));

        // Initialize AI service
        $aiService = app(AiAgentService::class);
        $collectionName = 'demo_automotive';

        $this->info("Starting upload to Qdrant collection: $collectionName");

        $successCount = 0;
        $errorCount = 0;

        $progressBar = $this->output->createProgressBar(count($carDealershipData));
        $progressBar->start();

        foreach ($carDealershipData as $item) {
            try {
                // Create search content for embedding
                $searchContent = $item['question'] . ' ' . $item['answer'];
                
                // Generate embedding
                $embedding = $aiService->embed($searchContent);
                
                if ($embedding && is_array($embedding)) {
                    // Upload to Qdrant - use integer ID
                    $result = $aiService->addToQdrant($collectionName, $embedding, $item, $item['qdrant_id']);
                    
                    if ($result) {
                        $successCount++;
                    } else {
                        $errorCount++;
                    }
                } else {
                    $errorCount++;
                }
                
                $progressBar->advance();
                
                // Small delay to avoid overwhelming the API
                usleep(100000); // 0.1 second
                
            } catch (\Exception $e) {
                $this->error("Exception for {$item['id']}: " . $e->getMessage());
                $errorCount++;
                $progressBar->advance();
            }
        }

        $progressBar->finish();
        $this->newLine();

        $this->info("=== Upload Summary ===");
        $this->info("Successful uploads: $successCount");
        $this->info("Failed uploads: $errorCount");
        $this->info("Total processed: " . count($carDealershipData));

        if ($successCount > 0) {
            $this->info("✅ Car dealership demo collection '$collectionName' created successfully!");
            $this->info("You can now test it at: https://ai-chat.support/demo/automotive");
        } else {
            $this->error("❌ Failed to create car dealership demo collection.");
        }
    }
}
