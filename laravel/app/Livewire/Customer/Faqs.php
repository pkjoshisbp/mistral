<?php

namespace App\Livewire\Customer;

use Livewire\Component;
use App\Models\OrganizationFaq;
use App\Services\AiAgentService;

class Faqs extends Component
{
    public $showForm=false; public $editingId=null; public $question=''; public $answer=''; public $category=''; public $keywords=''; public $sort_order=0; public $is_active=true;
    protected $rules=[ 'question'=>'required|string|min:3', 'answer'=>'required|string|min:3', 'category'=>'nullable|string', 'keywords'=>'nullable|string', 'sort_order'=>'nullable|integer', 'is_active'=>'boolean' ];
    private function orgId(){ $u=auth()->user(); return $u->organization->id ?? $u->organizations->first()->id ?? null; }
    public function getFaqsProperty(){ return OrganizationFaq::where('organization_id',$this->orgId())->orderBy('sort_order')->orderByDesc('id')->get(); }
    public function resetForm(){ $this->editingId=null; $this->question=$this->answer=$this->category=$this->keywords=''; $this->sort_order=0; $this->is_active=true; }
    public function create(){ $this->validate(); $org=$this->orgId(); try { $faq=OrganizationFaq::create(['organization_id'=>$org,'question'=>$this->question,'answer'=>$this->answer,'category'=>$this->category,'sort_order'=>$this->sort_order,'is_active'=>$this->is_active]); $ai=new AiAgentService(); $content="FAQ Question: {$this->question}\nAnswer: {$this->answer}\nCategory: {$this->category}"; $vector=$ai->embed($content.' '.($this->keywords??'')); if($vector){ $collection='org_'.$org.'_data'; $payload=['content'=>$content,'org_id'=>$org,'type'=>'faq','keywords'=>$this->keywords,'id'=>$faq->id]; $ai->addToQdrant($collection,$vector,$payload,$faq->id);} session()->flash('message','FAQ added'); $this->resetForm(); $this->showForm=false; } catch(\Throwable $e){ session()->flash('error','Add failed: '.$e->getMessage()); } }
    public function edit($id){ $f=OrganizationFaq::where('organization_id',$this->orgId())->find($id); if(!$f) return; $this->editingId=$id; $this->question=$f->question; $this->answer=$f->answer; $this->category=$f->category; $this->sort_order=$f->sort_order??0; $this->is_active=(bool)$f->is_active; $this->showForm=true; }
    public function update(){ $this->validate(); $f=OrganizationFaq::where('organization_id',$this->orgId())->find($this->editingId); if(!$f) return; try { $f->update(['question'=>$this->question,'answer'=>$this->answer,'category'=>$this->category,'sort_order'=>$this->sort_order,'is_active'=>$this->is_active]); $ai=new AiAgentService(); $content="FAQ Question: {$this->question}\nAnswer: {$this->answer}\nCategory: {$this->category}"; $vector=$ai->embed($content.' '.($this->keywords??'')); if($vector){ $collection='org_'.$this->orgId().'_data'; $payload=['content'=>$content,'org_id'=>$this->orgId(),'type'=>'faq','keywords'=>$this->keywords,'id'=>$f->id]; $ai->addToQdrant($collection,$vector,$payload,$f->id);} session()->flash('message','FAQ updated'); $this->resetForm(); $this->showForm=false; } catch(\Throwable $e){ session()->flash('error','Update failed: '.$e->getMessage()); } }
    public function delete($id){ $f=OrganizationFaq::where('organization_id',$this->orgId())->find($id); if(!$f) return; $org=$this->orgId(); try{ $f->delete(); $ai=new AiAgentService(); $collection='org_'.$org.'_data'; $ai->deleteFromQdrant($collection,$id); session()->flash('message','Deleted'); }catch(\Throwable $e){ session()->flash('error','Delete failed: '.$e->getMessage()); }}
    public function render(){ return view('livewire.customer.faqs')->layout('layouts.customer'); }
}
