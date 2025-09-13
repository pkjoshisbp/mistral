<?php

namespace Database\Seeders;

use App\Models\Organization;
use App\Models\OrganizationAction;
use Illuminate\Database\Seeder;

class OrganizationActionsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Get the AI Chat Support organization
        $organization = Organization::where('slug', 'ai-chat-support')->first();
        
        if (!$organization) {
            $this->command->error('Organization "ai-chat-support" not found. Please create it first.');
            return;
        }

        $this->command->info('Creating sample actions for organization: ' . $organization->name);

        // Sample Action 1: Check Room Availability (API-based)
        OrganizationAction::create([
            'organization_id' => $organization->id,
            'name' => 'Check Room Availability',
            'action_type' => 'CHECK_AVAILABILITY',
            'description' => 'Check real-time room availability for booking. Use when user asks about room availability, booking, or reservations.',
            'aliases' => [
                'can I reserve a room',
                'room availability',
                'book a room',
                'check availability',
                'is there a room available',
                'room booking'
            ],
            'keywords' => ['room', 'availability', 'book', 'reserve', 'available', 'booking'],
            'source_type' => 'api',
            'source_config' => [
                'method' => 'GET',
                'url' => 'https://api.example.com/v1/rooms/availability?date={date}&guests={guests}',
                'headers' => [
                    'Authorization' => 'Bearer {{API_KEY}}',
                    'Accept' => 'application/json'
                ],
                'timeout' => 30,
                'data_path' => 'data.rooms'
            ],
            'params_template' => [
                'date' => '{{date}}',
                'guests' => '{{guests}}'
            ],
            'required_params' => ['date'],
            'optional_params' => ['guests'],
            'min_score_threshold' => 0.75,
            'cache_ttl' => 300, // 5 minutes
            'response_template' => 'Available rooms on {{date}}: {{rooms_list}}',
            'roles_allowed' => ['booking', 'support']
        ]);

        // Sample Action 2: Get Pricing Information (CSV-based)
        OrganizationAction::create([
            'organization_id' => $organization->id,
            'name' => 'Get Service Pricing',
            'action_type' => 'GET_PRICING',
            'description' => 'Get current pricing for services from pricing sheet. Use when user asks about costs, fees, or pricing.',
            'aliases' => [
                'how much does it cost',
                'pricing information',
                'service fees',
                'what are your rates',
                'cost of services',
                'price list'
            ],
            'keywords' => ['price', 'cost', 'fee', 'rate', 'pricing', 'charge'],
            'source_type' => 'csv',
            'source_config' => [
                'file_path' => 'data/pricing.csv',
                'delimiter' => ',',
                'has_header' => true,
                'filter_column' => 'service_name',
                'filter_param' => 'service'
            ],
            'params_template' => [
                'service' => '{{service}}'
            ],
            'required_params' => [],
            'optional_params' => ['service'],
            'min_score_threshold' => 0.70,
            'cache_ttl' => 3600, // 1 hour
            'response_template' => 'Pricing for {{service}}: {{price_details}}'
        ]);

        // Sample Action 3: Search Customer Records (Database)
        OrganizationAction::create([
            'organization_id' => $organization->id,
            'name' => 'Search Customer Records',
            'action_type' => 'SEARCH_RECORDS',
            'description' => 'Search customer database for account information. Use when user asks about account details or customer information.',
            'aliases' => [
                'find my account',
                'customer information',
                'account details',
                'search records',
                'my booking history',
                'customer lookup'
            ],
            'keywords' => ['account', 'customer', 'records', 'search', 'find', 'lookup'],
            'source_type' => 'database',
            'source_config' => [
                'connection' => 'mysql',
                'table' => 'customers',
                'columns' => ['id', 'name', 'email', 'phone', 'created_at'],
                'where' => [
                    [
                        'column' => 'email',
                        'operator' => '=',
                        'param' => 'email'
                    ],
                    [
                        'column' => 'phone',
                        'operator' => 'LIKE',
                        'param' => 'phone'
                    ]
                ],
                'limit' => 10
            ],
            'params_template' => [
                'email' => '{{email}}',
                'phone' => '{{phone}}'
            ],
            'required_params' => ['email'],
            'optional_params' => ['phone'],
            'min_score_threshold' => 0.80,
            'cache_ttl' => 600, // 10 minutes
            'roles_allowed' => ['support', 'admin']
        ]);

        // Sample Action 4: Check Inventory Status (Excel)
        OrganizationAction::create([
            'organization_id' => $organization->id,
            'name' => 'Check Inventory Status',
            'action_type' => 'CHECK_INVENTORY',
            'description' => 'Check current inventory levels from Excel spreadsheet. Use when user asks about stock levels or product availability.',
            'aliases' => [
                'inventory levels',
                'stock status',
                'product availability',
                'in stock',
                'out of stock',
                'inventory check'
            ],
            'keywords' => ['inventory', 'stock', 'available', 'in stock', 'product'],
            'source_type' => 'excel',
            'source_config' => [
                'file_path' => 'data/inventory.xlsx',
                'sheet_name' => 'Current Stock',
                'has_header' => true,
                'filter_column' => 'product_name',
                'filter_param' => 'product'
            ],
            'params_template' => [
                'product' => '{{product}}'
            ],
            'required_params' => [],
            'optional_params' => ['product'],
            'min_score_threshold' => 0.75,
            'cache_ttl' => 1800, // 30 minutes
            'response_template' => 'Inventory status for {{product}}: {{stock_level}} units available'
        ]);

        // Sample Action 5: Google Sheets Integration
        OrganizationAction::create([
            'organization_id' => $organization->id,
            'name' => 'Get Live Schedule',
            'action_type' => 'GET_SCHEDULE',
            'description' => 'Get current schedule from Google Sheets. Use when user asks about schedules, appointments, or time slots.',
            'aliases' => [
                'schedule information',
                'available slots',
                'appointment times',
                'when are you open',
                'business hours',
                'time slots'
            ],
            'keywords' => ['schedule', 'appointment', 'time', 'slot', 'hours', 'open'],
            'source_type' => 'google_sheets',
            'source_config' => [
                'spreadsheet_id' => '1BxiMVs0XRA5nFMdKvBdBZjgmUUqptlbs74OgvE2upms',
                'range' => 'Schedule!A:E',
                'has_header' => true,
                'api_key' => '{{GOOGLE_SHEETS_API_KEY}}'
            ],
            'params_template' => [
                'date' => '{{date}}'
            ],
            'required_params' => [],
            'optional_params' => ['date'],
            'min_score_threshold' => 0.70,
            'cache_ttl' => 900, // 15 minutes
            'response_template' => 'Schedule for {{date}}: {{schedule_details}}'
        ]);

        $this->command->info('Sample actions created successfully!');
        $this->command->info('Note: Update the source_config with actual API endpoints, file paths, and credentials.');
    }
}