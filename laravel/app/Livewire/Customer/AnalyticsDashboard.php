<?php

namespace App\Livewire\Customer;

use Livewire\Component;
use App\Models\Analytics;
use App\Models\Lead;
use Carbon\Carbon;

class AnalyticsDashboard extends Component
{
    public $period = 7; // days
    public $analytics = [];
    public $metrics = [];
    public $organization;

    protected $queryString = ['period'];

    public function mount()
    {
        $this->organization = auth()->user()->organization ?? null;
        $this->loadAnalytics();
    }

    public function updatedPeriod()
    {
        $this->loadAnalytics();
    }

    public function loadAnalytics()
    {
        if (!$this->organization) {
            $this->analytics = [];
            $this->metrics = [];
            return;
        }

        $startDate = Carbon::now()->subDays($this->period);
        $orgId = $this->organization->id;

        $analyticsData = Analytics::where('organization_id', $orgId)
            ->where('created_at', '>=', $startDate)
            ->orderBy('created_at', 'desc')
            ->get();

        $this->metrics = [
            'total_page_views' => $analyticsData->where('event_type', 'page_view')->count(),
            'unique_visitors' => $analyticsData->where('event_type', 'page_view')->pluck('visitor_id')->unique()->count(),
            'total_sessions' => $analyticsData->pluck('session_id')->unique()->count(),
            'widget_interactions' => $analyticsData->whereIn('event_type', ['widget_open', 'chat_message'])->count(),
            'avg_time_on_page' => round($analyticsData->where('event_type', 'page_view')->avg('time_on_page') ?? 0, 2),
            'intent_events' => $analyticsData->where('event_type', 'intent_detected')->count(),
            'unanswered_questions' => $analyticsData->where('event_type', 'unanswered_question')->count(),
        ];

        $leadData = Lead::where('organization_id', $orgId)
            ->where('created_at', '>=', $startDate)
            ->get();

        $totalLeads = $leadData->count();
        $qualifiedLeads = $leadData->where('status', 'qualified')->count();
        $newLeads = $leadData->where('status', 'new')->count();
        $chatMessages = $analyticsData->where('event_type', 'chat_message')->count();

        $this->analytics['lead_funnel'] = [
            'chat_messages' => $chatMessages,
            'leads' => $totalLeads,
            'new_leads' => $newLeads,
            'qualified_leads' => $qualifiedLeads,
            'lead_capture_rate' => $chatMessages > 0 ? round(($totalLeads / $chatMessages) * 100, 2) : 0,
            'qualification_rate' => $totalLeads > 0 ? round(($qualifiedLeads / $totalLeads) * 100, 2) : 0,
        ];

        $intentEvents = $analyticsData->where('event_type', 'intent_detected');
        $this->analytics['intent_distribution'] = $intentEvents->groupBy(function ($item) {
                return $item->event_data['intent'] ?? 'unknown';
            })
            ->map(function ($group, $intent) {
                $avgConfidence = $group->average(function ($item) {
                    return (float) ($item->event_data['confidence'] ?? 0);
                });
                return [
                    'intent' => $intent,
                    'count' => $group->count(),
                    'avg_confidence' => round($avgConfidence, 2),
                ];
            })
            ->sortByDesc('count')
            ->values();

        $unansweredEvents = $analyticsData->where('event_type', 'unanswered_question');
        $this->analytics['unanswered_questions'] = $unansweredEvents->groupBy(function ($item) {
                return $item->event_data['message'] ?? 'Unknown question';
            })
            ->map(function ($group, $question) {
                return [
                    'question' => $question,
                    'count' => $group->count(),
                    'last_seen' => $group->max('created_at'),
                ];
            })
            ->sortByDesc('count')
            ->take(10)
            ->values();

        $this->analytics['top_pages'] = $analyticsData->where('event_type', 'page_view')
            ->groupBy('page_url')
            ->map(function ($group) {
                return [
                    'url' => $group->first()->page_url,
                    'title' => $group->first()->page_title,
                    'views' => $group->count(),
                    'unique_visitors' => $group->pluck('visitor_id')->unique()->count(),
                ];
            })
            ->sortByDesc('views')
            ->take(10)
            ->values();

        $this->analytics['traffic_by_country'] = $analyticsData->where('event_type', 'page_view')
            ->whereNotNull('country')
            ->groupBy('country')
            ->map(function ($group) {
                return [
                    'country' => $group->first()->country,
                    'visitors' => $group->pluck('visitor_id')->unique()->count(),
                    'views' => $group->count(),
                ];
            })
            ->sortByDesc('visitors')
            ->take(10)
            ->values();

        $this->analytics['recent_activity'] = $analyticsData->take(50)->map(function ($item) {
            return [
                'time' => $item->created_at->format('M j, H:i'),
                'event_type' => $item->event_type,
                'page_url' => $item->page_url,
                'country' => $item->country,
                'visitor_id' => substr($item->visitor_id, -8),
            ];
        });

        $this->analytics['daily_stats'] = Analytics::where('organization_id', $orgId)
            ->where('created_at', '>=', $startDate)
            ->selectRaw('DATE(created_at) as date, 
                        COUNT(*) as total_events,
                        COUNT(CASE WHEN event_type = "page_view" THEN 1 END) as page_views,
                        COUNT(DISTINCT visitor_id) as unique_visitors')
            ->groupBy('date')
            ->orderBy('date')
            ->get()
            ->keyBy('date');
    }

    public function render()
    {
        return view('livewire.customer.analytics-dashboard')
            ->layout('layouts.customer');
    }
}
