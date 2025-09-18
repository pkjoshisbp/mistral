<?php

namespace App\Livewire;

use Livewire\Component;
use Livewire\WithPagination;
use App\Models\AffiliateLink;
use Illuminate\Support\Str;

class AffiliateLinks extends Component
{
    use WithPagination;

    public $name = '';
    public $originalUrl = '';
    public $showCreateForm = false;

    protected $rules = [
        'name' => 'required|string|max:255',
        'originalUrl' => 'required|url|max:500'
    ];

    public function createLink()
    {
        $this->validate();

        $affiliate = auth()->user()->affiliate;
        
        if (!$affiliate) {
            session()->flash('error', 'You must be an approved affiliate to create links.');
            return;
        }

        AffiliateLink::create([
            'affiliate_id' => $affiliate->id,
            'name' => $this->name,
            'original_url' => $this->originalUrl,
            'tracking_code' => Str::random(12),
            'is_active' => true
        ]);

        $this->reset(['name', 'originalUrl']);
        $this->showCreateForm = false;
        session()->flash('message', 'Affiliate link created successfully!');
    }

    public function toggleLinkStatus($linkId)
    {
        $link = AffiliateLink::where('affiliate_id', auth()->user()->affiliate->id)
                           ->findOrFail($linkId);
        
        $link->update(['is_active' => !$link->is_active]);
        
        session()->flash('message', 'Link status updated successfully!');
    }

    public function deleteLink($linkId)
    {
        $link = AffiliateLink::where('affiliate_id', auth()->user()->affiliate->id)
                           ->findOrFail($linkId);
        
        $link->delete();
        
        session()->flash('message', 'Link deleted successfully!');
    }

    public function render()
    {
        $affiliate = auth()->user()->affiliate;
        
        $links = AffiliateLink::where('affiliate_id', $affiliate->id)
                            ->orderBy('created_at', 'desc')
                            ->paginate(10);

        return view('livewire.affiliate-links', compact('links'))
            ->layout('layouts.affiliate');
    }
}