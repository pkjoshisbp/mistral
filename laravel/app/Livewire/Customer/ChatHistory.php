<?php

namespace App\Livewire\Customer;

use Livewire\Component;
use Livewire\WithPagination;
use App\Models\ChatSession;
use Illuminate\Support\Facades\Auth;

class ChatHistory extends Component
{
    use WithPagination;

    public $search = '';
    public $selectedOrganization = '';
    public $dateFrom = '';
    public $dateTo = '';
    public $showDetails = [];

    protected $queryString = ['search', 'selectedOrganization', 'dateFrom', 'dateTo'];

    public function mount()
    {
        $this->dateFrom = now()->subDays(30)->format('Y-m-d');
        $this->dateTo = now()->format('Y-m-d');
    }

    public function updatedSearch()
    {
        $this->resetPage();
    }

    public function updatedSelectedOrganization()
    {
        $this->resetPage();
    }

    public function updatedDateFrom()
    {
        $this->resetPage();
    }

    public function updatedDateTo()
    {
        $this->resetPage();
    }

    public function toggleDetails($sessionId)
    {
        if (isset($this->showDetails[$sessionId])) {
            unset($this->showDetails[$sessionId]);
        } else {
            $this->showDetails[$sessionId] = true;
        }
    }

    public function clearFilters()
    {
        $this->search = '';
        $this->selectedOrganization = '';
        $this->dateFrom = now()->subDays(30)->format('Y-m-d');
        $this->dateTo = now()->format('Y-m-d');
        $this->resetPage();
    }

    public function deleteSession($sessionId)
    {
        $session = ChatSession::where('id', $sessionId)
            ->where('user_id', Auth::id())
            ->first();

        if ($session) {
            $session->delete();
            session()->flash('success', 'Chat session deleted successfully.');
        }
    }

    public function exportSession($sessionId)
    {
        $session = ChatSession::with('messages')
            ->where('id', $sessionId)
            ->where('user_id', Auth::id())
            ->first();

        if ($session) {
            // Basic HTML content for PDF/text export
            $html = view('exports.chat-session', [
                'session' => $session,
                'duration' => $this->formatDuration($session->created_at, $session->updated_at)
            ])->render();

            if (class_exists(\Dompdf\Dompdf::class)) {
                $pdf = app('dompdf.wrapper');
                $pdf->loadHTML($html)->setPaper('a4', 'portrait');
                return response()->streamDownload(function() use ($pdf) {
                    echo $pdf->output();
                }, 'chat-session-' . $sessionId . '.pdf');
            }

            // Fallback to txt export
            return response()->streamDownload(function () use ($html) {
                echo strip_tags($html);
            }, 'chat-session-' . $sessionId . '.txt');
        }
    }

    private function formatDuration($start, $end)
    {
        $diff = $start->diffInMinutes($end);
        if ($diff < 60) {
            return $diff . ' minutes';
        }
        return $start->diffInHours($end) . ' hours ' . ($diff % 60) . ' minutes';
    }

    public function render()
    {
        $query = ChatSession::with(['organization', 'messages'])
            ->where('user_id', Auth::id());

        if ($this->search) {
            $query->where(function ($q) {
                $q->whereHas('messages', function ($mq) {
                    $mq->where('content', 'like', '%' . $this->search . '%');
                })
                ->orWhereHas('organization', function ($oq) {
                    $oq->where('name', 'like', '%' . $this->search . '%');
                });
            });
        }

        if ($this->selectedOrganization) {
            $query->where('organization_id', $this->selectedOrganization);
        }

        if ($this->dateFrom) {
            $query->whereDate('created_at', '>=', $this->dateFrom);
        }

        if ($this->dateTo) {
            $query->whereDate('created_at', '<=', $this->dateTo);
        }

        $sessions = $query->orderBy('created_at', 'desc')->paginate(10);

        $primary = Auth::user()->primaryOrganization();
        $organizations = $primary ? 
            collect([$primary]) : 
            collect([]);

        return view('livewire.customer.chat-history', [
            'sessions' => $sessions,
            'organizations' => $organizations
        ])->layout('layouts.customer', [
            'layoutData' => [
                'title' => 'Chat History'
            ]
        ]);
    }
}
