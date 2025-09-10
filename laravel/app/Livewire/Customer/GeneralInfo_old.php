<?php

namespace App\Livewire\Customer;

use Livewire\Component;
use App\Models\OrganizationData;
use App\Services\AiAgentService;

class GeneralInfo extends Component
{
    public $showForm=false; public $editingId=null; public $title=''; public $information=''; public $category=''; public $keywords='';
    protected $rules=[ 'title'=>'required|string|min:2', 'information'=>'required|string|min:5', 'category'=>'nullable|string', 'keywords'=>'nullable|string' ];
    private function orgId(){ $u=auth()->user(); return $u->organization->id ?? $u->organizations->first()->id ?? null; }
    public function getInfosProperty(){ return OrganizationData::where('type','info')->where('organization_id',$this->orgId())->orderByDesc('id')->get(); }
    public function resetForm(){ $this->editingId=null; $this->title=$this->information=$this->category=$this->keywords=''; }
    public function create(){ $this->validate(); $org=$this->orgId(); try{ $content=$this->compose(); $rec=OrganizationData::create(['organization_id'=>$org,'type'=>'info','name'=>$this->title,'description'=>$this->information,'content'=>$content,'metadata'=>['category'=>$this->category,'keywords'=>$this->keywords,'type'=>'manual_entry']]); $ai=new AiAgentService(); $vector=$ai->embed($content.' '.($this->keywords??'')); if($vector){ $collection='org_'.$org.'_data'; $payload=$rec->metadata; $payload['content']=$content; $payload['org_id']=$org; $payload['id']=$rec->id; $ai->addToQdrant($collection,$vector,$payload,$rec->id);} session()->flash('message','Information added'); $this->resetForm(); $this->showForm=false; }catch(\Throwable $e){ session()->flash('error','Add failed: '.$e->getMessage()); } }
    public function edit($id){ $r=OrganizationData::where('organization_id',$this->orgId())->find($id); if(!$r) return; $this->editingId=$id; $this->title=$r->name; $this->information=$r->description; $m=$r->metadata??[]; $this->category=$m['category']??''; $this->keywords=$m['keywords']??''; $this->showForm=true; }
    public function update(){ $this->validate(); $r=OrganizationData::where('organization_id',$this->orgId())->find($this->editingId); if(!$r) return; try{ $content=$this->compose(); $metadata=['category'=>$this->category,'keywords'=>$this->keywords,'type'=>'manual_entry']; $r->update(['name'=>$this->title,'description'=>$this->information,'content'=>$content,'metadata'=>$metadata]); $ai=new AiAgentService(); $vector=$ai->embed($content.' '.($this->keywords??'')); if($vector){ $collection='org_'.$this->orgId().'_data'; $payload=$metadata; $payload['content']=$content; $payload['org_id']=$this->orgId(); $payload['id']=$r->id; $ai->addToQdrant($collection,$vector,$payload,$r->id);} session()->flash('message','Information updated'); $this->resetForm(); $this->showForm=false; }catch(\Throwable $e){ session()->flash('error','Update failed: '.$e->getMessage()); } }
    public function delete($id){ $r=OrganizationData::where('organization_id',$this->orgId())->find($id); if(!$r) return; $org=$this->orgId(); try{ $r->delete(); $ai=new AiAgentService(); $collection='org_'.$org.'_data'; $ai->deleteFromQdrant($collection,$id); session()->flash('message','Deleted'); }catch(\Throwable $e){ session()->flash('error','Delete failed: '.$e->getMessage()); }}
    private function compose(): string { return "Information: {$this->title}\nDetails: {$this->information}\nCategory: {$this->category}"; }
    public function render(){ return view('livewire.customer.general-info')->layout('layouts.customer'); }
}
