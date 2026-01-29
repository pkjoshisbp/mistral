<?php

namespace App\Livewire\Admin;

use App\Models\OrganizationAction;
use App\Models\Organization;
use App\Services\ActionService;
use Livewire\Component;
use Livewire\WithPagination;
use Illuminate\Support\Facades\Log;

class ActionManager extends Component
{
    use WithPagination;

    public $search = '';
    public $selectedOrganization = '';
    public $showModal = false;
    public $editingAction = null;
    public $templateOrganizationId = '';
    public $templateType = '';
    
    // Form fields
    public $organization_id = '';
    public $name = '';
    public $action_type = '';
    public $description = '';
    public $aliases = [];
    public $keywords = [];
    public $source_type = '';
    public $source_config = [];
    public $params_template = [];
    public $required_params = [];
    public $optional_params = [];
    public $min_score_threshold = 0.75;
    public $cache_ttl = 300;
    public $is_active = true;
    public $roles_allowed = [];
    public $response_template = '';
    public $output_format = 'text';
    
    // Helper fields
    public $aliasInput = '';
    public $keywordInput = '';
    public $requiredParamInput = '';
    public $optionalParamInput = '';
    
    // Source config fields
    public $api_method = 'GET';
    public $api_url = '';
    public $api_headers = [];
    public $api_timeout = 30;
    public $csv_file_path = '';
    public $csv_delimiter = ',';
    public $csv_has_header = true;
    public $excel_file_path = '';
    public $excel_sheet_name = '';
    public $db_table = '';
    public $db_columns = ['*'];
    public $db_connection = 'mysql';
    public $sheets_spreadsheet_id = '';
    public $sheets_range = 'A:Z';

    protected $rules = [
        'organization_id' => 'required|exists:organizations,id',
        'name' => 'required|string|max:255',
        'action_type' => 'required|string|max:255',
        'description' => 'required|string',
        'source_type' => 'required|in:api,csv,excel,google_sheets,database',
        'min_score_threshold' => 'required|numeric|between:0,1',
        'cache_ttl' => 'required|integer|min:0',
    ];

    public function mount()
    {
        $this->resetForm();
    }

    public function applyActionTemplate()
    {
        if (!$this->templateOrganizationId) {
            session()->flash('error', 'Please select an organization to apply templates.');
            return;
        }

        $organization = Organization::find($this->templateOrganizationId);
        if (!$organization) {
            session()->flash('error', 'Organization not found.');
            return;
        }

        $type = $this->templateType ?: ($organization->settings['org_type'] ?? null);
        if (!$type) {
            session()->flash('error', 'Please select a template type.');
            return;
        }

        $templates = $this->getActionTemplates();
        $actions = $templates[$type] ?? null;
        if (!$actions) {
            session()->flash('error', 'No templates found for this type.');
            return;
        }

        $created = 0;
        foreach ($actions as $actionData) {
            $exists = OrganizationAction::where('organization_id', $organization->id)
                ->where('name', $actionData['name'])
                ->exists();

            if ($exists) {
                continue;
            }

            $actionData['organization_id'] = $organization->id;
            $actionData['is_active'] = false; // templates are inactive by default

            $action = OrganizationAction::create($actionData);
            $created++;

            try {
                $actionService = app(ActionService::class);
                $actionService->syncActionToVectorDB($action);
            } catch (\Exception $e) {
                Log::warning('Failed to sync template action to vector DB', [
                    'action_id' => $action->id,
                    'error' => $e->getMessage()
                ]);
            }
        }

        if ($created === 0) {
            session()->flash('error', 'No new actions created. They may already exist.');
            return;
        }

        session()->flash('success', "{$created} template actions created. Configure and activate them when ready.");
    }

    private function getActionTemplates(): array
    {
        $baseApi = function (string $url, string $method = 'GET', array $body = []): array {
            return [
                'source_type' => 'api',
                'source_config' => [
                    'method' => $method,
                    'url' => $url,
                    'headers' => [],
                    'body' => $body,
                    'timeout' => 15,
                ],
                'aliases' => [],
                'params_template' => [],
                'required_params' => [],
                'optional_params' => [],
                'min_score_threshold' => 0.75,
                'cache_ttl' => 300,
                'roles_allowed' => [],
                'response_template' => '',
                'output_format' => 'text',
            ];
        };

        return [
            'clinic' => [
                array_merge($baseApi('https://api.example.com/appointments/availability?date={date}&doctor={doctor}'), [
                    'name' => 'Check Appointment Availability',
                    'action_type' => 'availability',
                    'description' => 'Check appointment availability for a doctor and date.',
                    'keywords' => ['appointment availability', 'doctor available', 'available slots', 'schedule availability'],
                    'required_params' => ['date', 'doctor'],
                ]),
                array_merge($baseApi('https://api.example.com/appointments/book', 'POST', [
                    'name' => '{name}',
                    'phone' => '{phone}',
                    'date' => '{date}',
                    'doctor' => '{doctor}'
                ]), [
                    'name' => 'Book Appointment',
                    'action_type' => 'booking',
                    'description' => 'Book a clinic appointment.',
                    'keywords' => ['book appointment', 'schedule appointment', 'book slot'],
                    'required_params' => ['name', 'phone', 'date'],
                    'optional_params' => ['doctor'],
                ]),
                array_merge($baseApi('https://api.example.com/tests/pricing?test={test}'), [
                    'name' => 'Get Test Pricing',
                    'action_type' => 'pricing',
                    'description' => 'Get pricing for lab tests.',
                    'keywords' => ['test price', 'lab test cost', 'fees', 'pricing'],
                    'required_params' => ['test'],
                ]),
            ],
            'hospital' => [
                array_merge($baseApi('https://api.example.com/doctors/availability?date={date}&specialty={specialty}'), [
                    'name' => 'Check Doctor Availability',
                    'action_type' => 'availability',
                    'description' => 'Check doctor availability by specialty.',
                    'keywords' => ['doctor availability', 'specialist available', 'availability'],
                    'required_params' => ['date'],
                    'optional_params' => ['specialty'],
                ]),
                array_merge($baseApi('https://api.example.com/appointments/book', 'POST', [
                    'name' => '{name}',
                    'phone' => '{phone}',
                    'date' => '{date}',
                    'department' => '{department}'
                ]), [
                    'name' => 'Book Hospital Appointment',
                    'action_type' => 'booking',
                    'description' => 'Book a hospital appointment.',
                    'keywords' => ['book appointment', 'schedule visit', 'book doctor'],
                    'required_params' => ['name', 'phone', 'date'],
                    'optional_params' => ['department'],
                ]),
                array_merge($baseApi('https://api.example.com/packages/pricing?package={package}'), [
                    'name' => 'Get Package Pricing',
                    'action_type' => 'pricing',
                    'description' => 'Get pricing for hospital packages.',
                    'keywords' => ['package price', 'pricing', 'fees', 'cost'],
                    'required_params' => ['package'],
                ]),
            ],
            'ecommerce' => [
                array_merge($baseApi('https://api.example.com/orders/status?order_id={order_id}'), [
                    'name' => 'Check Order Status',
                    'action_type' => 'status',
                    'description' => 'Check order delivery/status by order ID.',
                    'keywords' => ['order status', 'track order', 'delivery status'],
                    'required_params' => ['order_id'],
                ]),
                array_merge($baseApi('https://api.example.com/products/search?q={query}'), [
                    'name' => 'Search Products',
                    'action_type' => 'search',
                    'description' => 'Search products by query.',
                    'keywords' => ['search product', 'find product', 'product search'],
                    'required_params' => ['query'],
                ]),
                array_merge($baseApi('https://api.example.com/inventory?sku={sku}'), [
                    'name' => 'Check Inventory',
                    'action_type' => 'inventory',
                    'description' => 'Check stock availability by SKU.',
                    'keywords' => ['stock', 'inventory', 'availability', 'in stock'],
                    'required_params' => ['sku'],
                ]),
            ],
            'restaurant' => [
                array_merge($baseApi('https://api.example.com/tables/availability?date={date}&time={time}&guests={guests}'), [
                    'name' => 'Check Table Availability',
                    'action_type' => 'availability',
                    'description' => 'Check table availability for a date/time.',
                    'keywords' => ['table availability', 'available table', 'reservation availability'],
                    'required_params' => ['date', 'time', 'guests'],
                ]),
                array_merge($baseApi('https://api.example.com/tables/reserve', 'POST', [
                    'name' => '{name}',
                    'phone' => '{phone}',
                    'date' => '{date}',
                    'time' => '{time}',
                    'guests' => '{guests}'
                ]), [
                    'name' => 'Reserve Table',
                    'action_type' => 'booking',
                    'description' => 'Create a table reservation.',
                    'keywords' => ['book table', 'reserve table', 'table booking'],
                    'required_params' => ['name', 'phone', 'date', 'time', 'guests'],
                ]),
                array_merge($baseApi('https://api.example.com/menu/search?q={query}'), [
                    'name' => 'Menu Item Search',
                    'action_type' => 'search',
                    'description' => 'Search menu items by keyword.',
                    'keywords' => ['menu', 'find dish', 'search menu'],
                    'required_params' => ['query'],
                ]),
            ],
            'real_estate' => [
                array_merge($baseApi('https://api.example.com/listings/search?location={location}&budget={budget}'), [
                    'name' => 'Search Property Listings',
                    'action_type' => 'search',
                    'description' => 'Search property listings by location and budget.',
                    'keywords' => ['find property', 'search listings', 'available properties'],
                    'required_params' => ['location'],
                    'optional_params' => ['budget'],
                ]),
                array_merge($baseApi('https://api.example.com/visits/book', 'POST', [
                    'name' => '{name}',
                    'phone' => '{phone}',
                    'date' => '{date}',
                    'property_id' => '{property_id}'
                ]), [
                    'name' => 'Schedule Site Visit',
                    'action_type' => 'booking',
                    'description' => 'Schedule a property visit.',
                    'keywords' => ['schedule visit', 'site visit', 'book viewing'],
                    'required_params' => ['name', 'phone', 'date'],
                    'optional_params' => ['property_id'],
                ]),
            ],
            'real_estate_rental' => [
                array_merge($baseApi('https://api.example.com/rentals/search?location={location}&budget={budget}'), [
                    'name' => 'Search Rentals',
                    'action_type' => 'search',
                    'description' => 'Search rental listings by location and budget.',
                    'keywords' => ['rental', 'rentals', 'find rental', 'available rentals'],
                    'required_params' => ['location'],
                    'optional_params' => ['budget'],
                ]),
                array_merge($baseApi('https://api.example.com/rentals/visit', 'POST', [
                    'name' => '{name}',
                    'phone' => '{phone}',
                    'date' => '{date}',
                    'property_id' => '{property_id}'
                ]), [
                    'name' => 'Schedule Rental Visit',
                    'action_type' => 'booking',
                    'description' => 'Schedule a rental property visit.',
                    'keywords' => ['rental visit', 'book viewing', 'schedule visit'],
                    'required_params' => ['name', 'phone', 'date'],
                    'optional_params' => ['property_id'],
                ]),
            ],
            'automobile_dealer' => [
                array_merge($baseApi('https://api.example.com/test-drive/book', 'POST', [
                    'name' => '{name}',
                    'phone' => '{phone}',
                    'date' => '{date}',
                    'model' => '{model}'
                ]), [
                    'name' => 'Book Test Drive',
                    'action_type' => 'booking',
                    'description' => 'Book a test drive for a vehicle model.',
                    'keywords' => ['test drive', 'book test drive', 'schedule test drive'],
                    'required_params' => ['name', 'phone', 'date'],
                    'optional_params' => ['model'],
                ]),
                array_merge($baseApi('https://api.example.com/vehicles/availability?model={model}'), [
                    'name' => 'Check Vehicle Availability',
                    'action_type' => 'inventory',
                    'description' => 'Check vehicle availability by model.',
                    'keywords' => ['vehicle availability', 'stock', 'available cars'],
                    'required_params' => ['model'],
                ]),
                array_merge($baseApi('https://api.example.com/vehicles/price?model={model}'), [
                    'name' => 'Get On-road Price',
                    'action_type' => 'pricing',
                    'description' => 'Get on-road price for a vehicle.',
                    'keywords' => ['on-road price', 'price', 'cost', 'emi'],
                    'required_params' => ['model'],
                ]),
            ],
            'school' => [
                array_merge($baseApi('https://api.example.com/admissions/visit', 'POST', [
                    'name' => '{name}',
                    'phone' => '{phone}',
                    'date' => '{date}',
                    'grade' => '{grade}'
                ]), [
                    'name' => 'Schedule Admission Visit',
                    'action_type' => 'booking',
                    'description' => 'Schedule a school admission visit.',
                    'keywords' => ['admission visit', 'campus visit', 'schedule visit'],
                    'required_params' => ['name', 'phone', 'date'],
                    'optional_params' => ['grade'],
                ]),
                array_merge($baseApi('https://api.example.com/fees?grade={grade}'), [
                    'name' => 'Get Fee Structure',
                    'action_type' => 'pricing',
                    'description' => 'Get fee structure by grade.',
                    'keywords' => ['fees', 'tuition', 'fee structure', 'cost'],
                    'required_params' => ['grade'],
                ]),
                array_merge($baseApi('https://api.example.com/courses/search?q={query}'), [
                    'name' => 'Search Courses',
                    'action_type' => 'search',
                    'description' => 'Search courses or programs.',
                    'keywords' => ['courses', 'programs', 'subjects', 'find course'],
                    'required_params' => ['query'],
                ]),
            ],
            'college' => [
                array_merge($baseApi('https://api.example.com/admissions/visit', 'POST', [
                    'name' => '{name}',
                    'phone' => '{phone}',
                    'date' => '{date}',
                    'program' => '{program}'
                ]), [
                    'name' => 'Schedule Campus Visit',
                    'action_type' => 'booking',
                    'description' => 'Schedule a campus visit.',
                    'keywords' => ['campus visit', 'admission visit', 'schedule visit'],
                    'required_params' => ['name', 'phone', 'date'],
                    'optional_params' => ['program'],
                ]),
                array_merge($baseApi('https://api.example.com/fees?program={program}'), [
                    'name' => 'Get Program Fees',
                    'action_type' => 'pricing',
                    'description' => 'Get fee structure for a program.',
                    'keywords' => ['fees', 'tuition', 'fee structure', 'cost'],
                    'required_params' => ['program'],
                ]),
                array_merge($baseApi('https://api.example.com/programs/search?q={query}'), [
                    'name' => 'Search Programs',
                    'action_type' => 'search',
                    'description' => 'Search programs or departments.',
                    'keywords' => ['programs', 'departments', 'courses', 'find program'],
                    'required_params' => ['query'],
                ]),
            ],
            'ngo' => [
                array_merge($baseApi('https://api.example.com/volunteer/signup', 'POST', [
                    'name' => '{name}',
                    'phone' => '{phone}',
                    'date' => '{date}',
                    'interest' => '{interest}'
                ]), [
                    'name' => 'Volunteer Signup',
                    'action_type' => 'booking',
                    'description' => 'Register a volunteer signup.',
                    'keywords' => ['volunteer', 'signup', 'join program'],
                    'required_params' => ['name', 'phone', 'date'],
                    'optional_params' => ['interest'],
                ]),
                array_merge($baseApi('https://api.example.com/donations/options'), [
                    'name' => 'Donation Options',
                    'action_type' => 'pricing',
                    'description' => 'Get donation options and tiers.',
                    'keywords' => ['donate', 'donation', 'contribution'],
                    'required_params' => [],
                ]),
                array_merge($baseApi('https://api.example.com/programs/search?q={query}'), [
                    'name' => 'Find Programs',
                    'action_type' => 'search',
                    'description' => 'Search NGO programs or projects.',
                    'keywords' => ['programs', 'projects', 'find program'],
                    'required_params' => ['query'],
                ]),
            ],
            'travel' => [
                array_merge($baseApi('https://api.example.com/packages/search?destination={destination}'), [
                    'name' => 'Search Travel Packages',
                    'action_type' => 'search',
                    'description' => 'Search travel packages by destination.',
                    'keywords' => ['packages', 'travel packages', 'find trips'],
                    'required_params' => ['destination'],
                ]),
                array_merge($baseApi('https://api.example.com/packages/pricing?package={package}'), [
                    'name' => 'Get Package Pricing',
                    'action_type' => 'pricing',
                    'description' => 'Get pricing for travel packages.',
                    'keywords' => ['package price', 'pricing', 'cost'],
                    'required_params' => ['package'],
                ]),
                array_merge($baseApi('https://api.example.com/packages/availability?package={package}&date={date}'), [
                    'name' => 'Check Package Availability',
                    'action_type' => 'availability',
                    'description' => 'Check availability for travel packages.',
                    'keywords' => ['availability', 'slots', 'dates'],
                    'required_params' => ['package', 'date'],
                ]),
            ],
            'fitness' => [
                array_merge($baseApi('https://api.example.com/classes/availability?date={date}&class={class}'), [
                    'name' => 'Check Class Availability',
                    'action_type' => 'availability',
                    'description' => 'Check availability for fitness classes.',
                    'keywords' => ['class availability', 'slots', 'class schedule'],
                    'required_params' => ['date'],
                    'optional_params' => ['class'],
                ]),
                array_merge($baseApi('https://api.example.com/classes/book', 'POST', [
                    'name' => '{name}',
                    'phone' => '{phone}',
                    'date' => '{date}',
                    'class' => '{class}'
                ]), [
                    'name' => 'Book Fitness Class',
                    'action_type' => 'booking',
                    'description' => 'Book a fitness class.',
                    'keywords' => ['book class', 'schedule class', 'reserve class'],
                    'required_params' => ['name', 'phone', 'date'],
                    'optional_params' => ['class'],
                ]),
                array_merge($baseApi('https://api.example.com/membership/pricing'), [
                    'name' => 'Membership Pricing',
                    'action_type' => 'pricing',
                    'description' => 'Get membership pricing.',
                    'keywords' => ['membership', 'pricing', 'plans', 'fees'],
                    'required_params' => [],
                ]),
            ],
            'logistics' => [
                array_merge($baseApi('https://api.example.com/shipments/track?tracking_id={tracking_id}'), [
                    'name' => 'Track Shipment',
                    'action_type' => 'status',
                    'description' => 'Track shipment by tracking ID.',
                    'keywords' => ['track shipment', 'tracking', 'delivery status'],
                    'required_params' => ['tracking_id'],
                ]),
                array_merge($baseApi('https://api.example.com/shipments/quote?from={from}&to={to}&weight={weight}'), [
                    'name' => 'Get Shipping Quote',
                    'action_type' => 'pricing',
                    'description' => 'Get a shipping quote.',
                    'keywords' => ['shipping cost', 'quote', 'rate'],
                    'required_params' => ['from', 'to'],
                    'optional_params' => ['weight'],
                ]),
                array_merge($baseApi('https://api.example.com/shipments/pickup', 'POST', [
                    'name' => '{name}',
                    'phone' => '{phone}',
                    'date' => '{date}',
                    'address' => '{address}'
                ]), [
                    'name' => 'Schedule Pickup',
                    'action_type' => 'booking',
                    'description' => 'Schedule a shipment pickup.',
                    'keywords' => ['schedule pickup', 'book pickup', 'pickup request'],
                    'required_params' => ['name', 'phone', 'date', 'address'],
                ]),
            ],
            'fintech' => [
                array_merge($baseApi('https://api.example.com/account/status?account_id={account_id}'), [
                    'name' => 'Check Account Status',
                    'action_type' => 'status',
                    'description' => 'Check account status.',
                    'keywords' => ['account status', 'status', 'balance'],
                    'required_params' => ['account_id'],
                ]),
                array_merge($baseApi('https://api.example.com/fees'), [
                    'name' => 'Get Fee Details',
                    'action_type' => 'pricing',
                    'description' => 'Get fee and pricing details.',
                    'keywords' => ['fees', 'pricing', 'charges'],
                    'required_params' => [],
                ]),
                array_merge($baseApi('https://api.example.com/transactions/search?query={query}'), [
                    'name' => 'Find Transaction',
                    'action_type' => 'search',
                    'description' => 'Search transactions by query.',
                    'keywords' => ['transaction', 'find transaction', 'search transaction'],
                    'required_params' => ['query'],
                ]),
            ],
            'other' => [
                array_merge($baseApi('https://api.example.com/search?q={query}'), [
                    'name' => 'Generic Search',
                    'action_type' => 'search',
                    'description' => 'Generic search action for your data source.',
                    'keywords' => ['search', 'find', 'lookup'],
                    'required_params' => ['query'],
                ]),
                array_merge($baseApi('https://api.example.com/pricing?item={item}'), [
                    'name' => 'Generic Pricing',
                    'action_type' => 'pricing',
                    'description' => 'Generic pricing action for your data source.',
                    'keywords' => ['price', 'pricing', 'cost'],
                    'required_params' => ['item'],
                ]),
            ],
        ];
    }

    public function render()
    {
        $organizations = Organization::all();
        
        $query = OrganizationAction::with('organization')
            ->when($this->search, fn($q) => 
                $q->where('name', 'like', '%' . $this->search . '%')
                  ->orWhere('action_type', 'like', '%' . $this->search . '%')
                  ->orWhere('description', 'like', '%' . $this->search . '%')
            )
            ->when($this->selectedOrganization, fn($q) => 
                $q->where('organization_id', $this->selectedOrganization)
            )
            ->orderBy('organization_id')
            ->orderBy('name');

        $actions = $query->paginate(10);

        return view('livewire.admin.action-manager', compact('organizations', 'actions'))
            ->layout('layouts.admin');
    }

    public function openModal($actionId = null)
    {
        $this->resetForm();
        
        if ($actionId) {
            $this->editingAction = OrganizationAction::find($actionId);
            
            $this->organization_id = $this->editingAction->organization_id;
            $this->name = $this->editingAction->name;
            $this->action_type = $this->editingAction->action_type;
            $this->description = $this->editingAction->description;
            $this->aliases = $this->editingAction->aliases ?? [];
            $this->keywords = $this->editingAction->keywords ?? [];
            $this->source_type = $this->editingAction->source_type;
            $this->source_config = $this->editingAction->source_config ?? [];
            $this->params_template = $this->editingAction->params_template ?? [];
            $this->required_params = $this->editingAction->required_params ?? [];
            $this->optional_params = $this->editingAction->optional_params ?? [];
            $this->min_score_threshold = $this->editingAction->min_score_threshold;
            $this->cache_ttl = $this->editingAction->cache_ttl;
            $this->is_active = $this->editingAction->is_active;
            $this->roles_allowed = $this->editingAction->roles_allowed ?? [];
            $this->response_template = $this->editingAction->response_template ?? '';
            $this->output_format = $this->editingAction->output_format;
            
            $this->loadSourceConfigFields();
        }
        
        $this->showModal = true;
    }

    public function closeModal()
    {
        $this->showModal = false;
        $this->resetForm();
        $this->resetValidation();
    }

    public function save()
    {
        $this->validate();
        
        // Build source config based on type
        $this->buildSourceConfig();
        
        $data = [
            'organization_id' => $this->organization_id,
            'name' => $this->name,
            'action_type' => $this->action_type,
            'description' => $this->description,
            'aliases' => array_filter($this->aliases),
            'keywords' => array_filter($this->keywords),
            'source_type' => $this->source_type,
            'source_config' => $this->source_config,
            'params_template' => $this->params_template,
            'required_params' => array_filter($this->required_params),
            'optional_params' => array_filter($this->optional_params),
            'min_score_threshold' => $this->min_score_threshold,
            'cache_ttl' => $this->cache_ttl,
            'is_active' => $this->is_active,
            'roles_allowed' => array_filter($this->roles_allowed),
            'response_template' => $this->response_template,
            'output_format' => $this->output_format,
        ];

        if ($this->editingAction) {
            $this->editingAction->update($data);
            session()->flash('success', 'Action updated successfully!');
        } else {
            $action = OrganizationAction::create($data);
            session()->flash('success', 'Action created successfully!');
            
            // Sync to vector database
            try {
                $actionService = app(ActionService::class);
                $actionService->syncActionToVectorDB($action);
            } catch (\Exception $e) {
                Log::warning('Failed to sync action to vector DB', [
                    'action_id' => $action->id,
                    'error' => $e->getMessage()
                ]);
            }
        }

        $this->closeModal();
    }

    public function delete($actionId)
    {
        $action = OrganizationAction::find($actionId);
        
        if ($action) {
            // Remove from vector database first
            try {
                $actionService = app(ActionService::class);
                $actionService->removeActionFromVectorDB($action);
            } catch (\Exception $e) {
                Log::warning('Failed to remove action from vector DB', [
                    'action_id' => $action->id,
                    'error' => $e->getMessage()
                ]);
            }
            
            $action->delete();
            session()->flash('success', 'Action deleted successfully!');
        }
    }

    public function toggleStatus($actionId)
    {
        $action = OrganizationAction::find($actionId);
        if ($action) {
            $action->update(['is_active' => !$action->is_active]);
            session()->flash('success', 'Action status updated!');
        }
    }

    // Helper methods for form management
    public function addAlias()
    {
        if ($this->aliasInput && !in_array($this->aliasInput, $this->aliases)) {
            $this->aliases[] = $this->aliasInput;
            $this->aliasInput = '';
        }
    }

    public function removeAlias($index)
    {
        unset($this->aliases[$index]);
        $this->aliases = array_values($this->aliases);
    }

    public function addKeyword()
    {
        if ($this->keywordInput && !in_array($this->keywordInput, $this->keywords)) {
            $this->keywords[] = $this->keywordInput;
            $this->keywordInput = '';
        }
    }

    public function removeKeyword($index)
    {
        unset($this->keywords[$index]);
        $this->keywords = array_values($this->keywords);
    }

    public function addRequiredParam()
    {
        if ($this->requiredParamInput && !in_array($this->requiredParamInput, $this->required_params)) {
            $this->required_params[] = $this->requiredParamInput;
            $this->requiredParamInput = '';
        }
    }

    public function removeRequiredParam($index)
    {
        unset($this->required_params[$index]);
        $this->required_params = array_values($this->required_params);
    }

    public function addOptionalParam()
    {
        if ($this->optionalParamInput && !in_array($this->optionalParamInput, $this->optional_params)) {
            $this->optional_params[] = $this->optionalParamInput;
            $this->optionalParamInput = '';
        }
    }

    public function removeOptionalParam($index)
    {
        unset($this->optional_params[$index]);
        $this->optional_params = array_values($this->optional_params);
    }

    public function testAction($actionId)
    {
        $action = OrganizationAction::find($actionId);
        
        if (!$action) {
            session()->flash('error', 'Action not found!');
            return;
        }

        try {
            $actionService = app(ActionService::class);
            $testQuery = "Test query for " . $action->name;
            
            $result = $actionService->processQuery($testQuery, $action->organization_id);
            
            if ($result['type'] === 'action_executed' && $result['result']['success']) {
                session()->flash('success', 'Action test successful! Retrieved ' . 
                    (is_array($result['result']['data']) ? count($result['result']['data']) : 1) . ' records.');
            } else {
                session()->flash('error', 'Action test failed: ' . ($result['result']['error'] ?? 'Unknown error'));
            }
            
        } catch (\Exception $e) {
            session()->flash('error', 'Action test failed: ' . $e->getMessage());
        }
    }

    private function resetForm()
    {
        $this->editingAction = null;
        $this->organization_id = '';
        $this->name = '';
        $this->action_type = '';
        $this->description = '';
        $this->aliases = [];
        $this->keywords = [];
        $this->source_type = '';
        $this->source_config = [];
        $this->params_template = [];
        $this->required_params = [];
        $this->optional_params = [];
        $this->min_score_threshold = 0.75;
        $this->cache_ttl = 300;
        $this->is_active = true;
        $this->roles_allowed = [];
        $this->response_template = '';
        $this->output_format = 'text';
        
        $this->aliasInput = '';
        $this->keywordInput = '';
        $this->requiredParamInput = '';
        $this->optionalParamInput = '';
        
        $this->resetSourceConfigFields();
    }

    private function resetSourceConfigFields()
    {
        $this->api_method = 'GET';
        $this->api_url = '';
        $this->api_headers = [];
        $this->api_timeout = 30;
        $this->csv_file_path = '';
        $this->csv_delimiter = ',';
        $this->csv_has_header = true;
        $this->excel_file_path = '';
        $this->excel_sheet_name = '';
        $this->db_table = '';
        $this->db_columns = ['*'];
        $this->db_connection = 'mysql';
        $this->sheets_spreadsheet_id = '';
        $this->sheets_range = 'A:Z';
    }

    private function loadSourceConfigFields()
    {
        $config = $this->source_config;
        
        switch ($this->source_type) {
            case 'api':
                $this->api_method = $config['method'] ?? 'GET';
                $this->api_url = $config['url'] ?? '';
                $this->api_headers = $config['headers'] ?? [];
                $this->api_timeout = $config['timeout'] ?? 30;
                break;
                
            case 'csv':
                $this->csv_file_path = $config['file_path'] ?? '';
                $this->csv_delimiter = $config['delimiter'] ?? ',';
                $this->csv_has_header = $config['has_header'] ?? true;
                break;
                
            case 'excel':
                $this->excel_file_path = $config['file_path'] ?? '';
                $this->excel_sheet_name = $config['sheet_name'] ?? '';
                break;
                
            case 'database':
                $this->db_table = $config['table'] ?? '';
                $this->db_columns = $config['columns'] ?? ['*'];
                $this->db_connection = $config['connection'] ?? 'mysql';
                break;
                
            case 'google_sheets':
                $this->sheets_spreadsheet_id = $config['spreadsheet_id'] ?? '';
                $this->sheets_range = $config['range'] ?? 'A:Z';
                break;
        }
    }

    private function buildSourceConfig()
    {
        switch ($this->source_type) {
            case 'api':
                $this->source_config = [
                    'method' => $this->api_method,
                    'url' => $this->api_url,
                    'headers' => $this->api_headers,
                    'timeout' => $this->api_timeout
                ];
                break;
                
            case 'csv':
                $this->source_config = [
                    'file_path' => $this->csv_file_path,
                    'delimiter' => $this->csv_delimiter,
                    'has_header' => $this->csv_has_header
                ];
                break;
                
            case 'excel':
                $this->source_config = [
                    'file_path' => $this->excel_file_path,
                    'sheet_name' => $this->excel_sheet_name,
                    'has_header' => true
                ];
                break;
                
            case 'database':
                $this->source_config = [
                    'table' => $this->db_table,
                    'columns' => is_array($this->db_columns) ? $this->db_columns : [$this->db_columns],
                    'connection' => $this->db_connection,
                    'limit' => 100
                ];
                break;
                
            case 'google_sheets':
                $this->source_config = [
                    'spreadsheet_id' => $this->sheets_spreadsheet_id,
                    'range' => $this->sheets_range,
                    'has_header' => true
                ];
                break;
        }
    }
}