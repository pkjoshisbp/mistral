<?php

namespace App\Livewire\Customer;

use Livewire\Component;
use App\Models\OrganizationData;
use App\Services\AiAgentService;

class Services extends Component
{
    public $showForm = false;
    public $editingId = null;
    public $name='';
    public $description='';
    public $price='';
    public $category='';
    public $requirements='';
    public $duration='';
    public $availability='';
    public $keywords='';

    protected $rules = [
        'name' => 'required|string|min:2',
        'description' => 'required|string|min:5',
        'price' => 'nullable|numeric',
        'category' => 'nullable|string',
        'requirements' => 'nullable|string',
        'duration' => 'nullable|string',
        'availability' => 'nullable|string',
        'keywords' => 'nullable|string'
    ];

    private function orgId()
    {
        $user = auth()->user();
        return $user->organization->id ?? $user->organizations->first()->id ?? null;
    }

    public function getServicesProperty()
    {
        return OrganizationData::where('type','service')->where('organization_id',$this->orgId())->orderByDesc('id')->get();
    }

    public function resetForm()
    {
        $this->editingId = null;
        $this->name = $this->description = $this->price = $this->category = $this->requirements = $this->duration = $this->availability = $this->keywords = '';
    }

    public function create()
    {
        $this->validate();
        $orgId = $this->orgId();
        try {
            $content = $this->composeContent();
            $record = OrganizationData::create([
                'organization_id' => $orgId,
                'type' => 'service',
                'name' => $this->name,
                'description' => $this->description,
                'content' => $content,
                'metadata' => [
                    'category' => $this->category,
                    'price' => $this->price,
                    'requirements' => $this->requirements,
                    'duration' => $this->duration,
                    'availability' => $this->availability,
                    'keywords' => $this->keywords,
                    'type' => 'manual_entry'
                ]
            ]);
            $ai = new AiAgentService();
            $vector = $ai->embed($content . ' ' . ($this->keywords ?? ''));
            if ($vector) {
                $collection = 'org_' . $orgId . '_data';
                $payload = $record->metadata;
                $payload['content'] = $content;
                $payload['org_id'] = $orgId;
                $payload['id'] = $record->id;
                $ai->addToQdrant($collection, $vector, $payload, $record->id);
            }
            session()->flash('message','Service added');
            $this->resetForm();
            $this->showForm = false;
        } catch (\Throwable $e) { session()->flash('error','Add failed: '.$e->getMessage()); }
    }

    public function edit($id)
    {
        $r = OrganizationData::where('organization_id',$this->orgId())->find($id);
        if(!$r) return;
        $this->editingId = $id;
        $this->name = $r->name;
        $this->description = $r->description;
        $m = $r->metadata ?? [];
        $this->price = $m['price'] ?? '';
        $this->category = $m['category'] ?? '';
        $this->requirements = $m['requirements'] ?? '';
        $this->duration = $m['duration'] ?? '';
        $this->availability = $m['availability'] ?? '';
        $this->keywords = $m['keywords'] ?? '';
        $this->showForm = true;
    }

    public function update()
    {
        $this->validate();
        $r = OrganizationData::where('organization_id',$this->orgId())->find($this->editingId);
        if(!$r) return;
        try {
            $content = $this->composeContent();
            $metadata = [
                'category'=>$this->category,
                'price'=>$this->price,
                'requirements'=>$this->requirements,
                'duration'=>$this->duration,
                'availability'=>$this->availability,
                'keywords'=>$this->keywords,
                'type'=>'manual_entry'
            ];
            $r->update([
                'name'=>$this->name,
                'description'=>$this->description,
                'content'=>$content,
                'metadata'=>$metadata
            ]);
            $ai = new AiAgentService();
            $vector = $ai->embed($content . ' ' . ($this->keywords ?? ''));
            if ($vector) {
                $collection = 'org_' . $this->orgId() . '_data';
                $payload = $metadata; $payload['content']=$content; $payload['org_id']=$this->orgId(); $payload['id']=$r->id;
                $ai->addToQdrant($collection,$vector,$payload,$r->id);
            }
            session()->flash('message','Service updated');
            $this->resetForm();
            $this->showForm=false;
        } catch(\Throwable $e){ session()->flash('error','Update failed: '.$e->getMessage()); }
    }

    public function delete($id)
    {
        $r = OrganizationData::where('organization_id',$this->orgId())->find($id);
        if(!$r) return; $org = $this->orgId();
        try { $r->delete(); $ai=new AiAgentService(); $collection='org_'.$org.'_data'; $ai->deleteFromQdrant($collection,$id); session()->flash('message','Deleted'); } catch(\Throwable $e){ session()->flash('error','Delete failed: '.$e->getMessage()); }
    }

    private function composeContent(): string
    { return "Service: {$this->name}\nDescription: {$this->description}\nPrice: {$this->price}\nCategory: {$this->category}\nRequirements: {$this->requirements}\nDuration: {$this->duration}\nAvailability: {$this->availability}"; }

    public function render(){ return view('livewire.customer.services')->layout('layouts.customer'); }
}
