<?php

namespace App\Livewire\Admin;

use Livewire\Component;
use Livewire\WithPagination;
use App\Models\ChatSession;
use App\Models\Organization;

class ChatHistoryManager extends Component
{
    use WithPagination;

    public $search = '';
    public $organizationId = '';
    public $dateFrom;
    public $dateTo;
    public $showDetails = [];

    public function mount()
    {
        $this->dateFrom = now()->subDays(30)->format('Y-m-d');
        $this->dateTo = now()->format('Y-m-d');
    }

    public function updatingSearch() { $this->resetPage(); }
    public function updatingOrganizationId() { $this->resetPage(); }
    public function updatingDateFrom() { $this->resetPage(); }
    public function updatingDateTo() { $this->resetPage(); }

    public function toggleDetails($id)
    {
        if (isset($this->showDetails[$id])) {
            unset($this->showDetails[$id]);
        } else {
            $this->showDetails[$id] = true;
        }
    }

    public function exportSession($sessionId)
    {
        $session = ChatSession::with('messages','organization')->find($sessionId);
        if ($session) {
            $html = view('exports.chat-session', [
                'session' => $session,
                'duration' => $session->created_at->diffForHumans($session->updated_at, true)
            ])->render();
            if (class_exists(\Dompdf\Dompdf::class)) {
                $pdf = app('dompdf.wrapper');
                $pdf->loadHTML($html)->setPaper('a4');
                return response()->streamDownload(function() use ($pdf) { echo $pdf->output(); }, 'chat-session-' . $sessionId . '.pdf');
            }
            return response()->streamDownload(function() use ($html) { echo strip_tags($html); }, 'chat-session-' . $sessionId . '.txt');
        }
    }

    public function render()
    {
        $query = ChatSession::with(['organization','messages']);
        if ($this->search) {
            $query->whereHas('messages', function($q){ $q->where('content','like','%'.$this->search.'%'); });
        }
        if ($this->organizationId) {
            $query->where('organization_id', $this->organizationId); 
        }
        if ($this->dateFrom) { $query->whereDate('created_at','>=',$this->dateFrom); }
        if ($this->dateTo) { $query->whereDate('created_at','<=',$this->dateTo); }

        $sessions = $query->orderByDesc('created_at')->paginate(15);
        $organizations = Organization::orderBy('name')->get();

        return view('livewire.admin.chat-history-manager', compact('sessions','organizations'));
    }
}
