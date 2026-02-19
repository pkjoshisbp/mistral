<?php

namespace App\Livewire\Admin;

use App\Models\AdminSetting;
use App\Models\PersonalAssistantProfile;
use Illuminate\Support\Str;
use Livewire\Component;
use Livewire\WithPagination;

class PersonalAssistantPlanManager extends Component
{
    use WithPagination;

    public string $search = '';
    public string $monthlyPrice = '12';
    public int $trialDays = 14;

    protected $queryString = ['search'];

    public function mount(): void
    {
        $this->monthlyPrice = (string) AdminSetting::get('assistant_monthly_price_usd', '12');
        $this->trialDays = (int) AdminSetting::get('assistant_trial_days', 14);
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function saveGlobalSettings(): void
    {
        $this->validate([
            'monthlyPrice' => 'required|numeric|min:1|max:500',
            'trialDays' => 'required|integer|min:1|max:60',
        ]);

        AdminSetting::set(
            'assistant_monthly_price_usd',
            $this->monthlyPrice,
            'text',
            'billing',
            'Personal Assistant Monthly Price (USD)',
            'Monthly price shown in customer personal assistant module.'
        );

        AdminSetting::set(
            'assistant_trial_days',
            (string) $this->trialDays,
            'number',
            'billing',
            'Personal Assistant Trial Days',
            'Default free trial duration for personal assistant.'
        );

        session()->flash('success', 'Personal Assistant pricing/trial settings saved.');
    }

    public function activateSubscription(int $profileId): void
    {
        $profile = PersonalAssistantProfile::findOrFail($profileId);
        $settings = $profile->settings ?? [];
        $settings['assistant_plan_status'] = 'active';
        $profile->settings = $settings;
        $profile->save();

        session()->flash('success', 'Subscription activated for selected profile.');
    }

    public function deactivateSubscription(int $profileId): void
    {
        $profile = PersonalAssistantProfile::findOrFail($profileId);
        $settings = $profile->settings ?? [];
        $settings['assistant_plan_status'] = 'inactive';
        $profile->settings = $settings;
        $profile->save();

        session()->flash('success', 'Subscription deactivated for selected profile.');
    }

    public function resetTrial(int $profileId): void
    {
        $profile = PersonalAssistantProfile::findOrFail($profileId);
        $settings = $profile->settings ?? [];
        $now = now();
        $settings['assistant_plan_status'] = 'trial';
        $settings['assistant_trial_started_at'] = $now->toISOString();
        $settings['assistant_trial_ends_at'] = $now->copy()->addDays((int) $this->trialDays)->toISOString();
        $profile->settings = $settings;
        $profile->save();

        session()->flash('success', 'Trial reset for selected profile.');
    }

    private function queryProfiles()
    {
        $query = PersonalAssistantProfile::query()->with(['user', 'organization']);

        if (trim($this->search) !== '') {
            $term = '%' . trim($this->search) . '%';
            $query->where(function ($q) use ($term) {
                $q->whereHas('user', function ($uq) use ($term) {
                    $uq->where('name', 'like', $term)->orWhere('email', 'like', $term);
                })->orWhereHas('organization', function ($oq) use ($term) {
                    $oq->where('name', 'like', $term)->orWhere('slug', 'like', $term);
                });
            });
        }

        return $query->latest();
    }

    private function normalizedStatus(PersonalAssistantProfile $profile): array
    {
        $settings = $profile->settings ?? [];
        $status = (string) ($settings['assistant_plan_status'] ?? 'trial');
        $trialEndsAt = $settings['assistant_trial_ends_at'] ?? null;

        $trialEnd = null;
        if ($trialEndsAt) {
            try {
                $trialEnd = now()->parse($trialEndsAt);
            } catch (\Throwable $e) {
                $trialEnd = null;
            }
        }

        $daysLeft = $trialEnd ? max(0, now()->diffInDays($trialEnd, false)) : null;

        return [
            'status' => $status,
            'trial_ends_at' => $trialEnd?->toDateTimeString(),
            'trial_days_left' => $daysLeft,
            'badge' => match ($status) {
                'active' => 'success',
                'trial' => ($daysLeft !== null && $daysLeft > 0) ? 'info' : 'warning',
                default => 'secondary',
            },
        ];
    }

    public function render()
    {
        $profiles = $this->queryProfiles()->paginate(20);

        $rows = $profiles->getCollection()->map(function (PersonalAssistantProfile $profile) {
            $normalized = $this->normalizedStatus($profile);

            return [
                'id' => $profile->id,
                'user_name' => $profile->user?->name ?? 'Unknown',
                'user_email' => $profile->user?->email ?? 'N/A',
                'organization' => $profile->organization?->name ?? 'N/A',
                'status' => $normalized['status'],
                'badge' => $normalized['badge'],
                'trial_ends_at' => $normalized['trial_ends_at'],
                'trial_days_left' => $normalized['trial_days_left'],
                'last_used_at' => $profile->last_used_at?->toDateTimeString(),
            ];
        });

        $profiles->setCollection($rows);

        return view('livewire.admin.personal-assistant-plan-manager', [
            'profiles' => $profiles,
        ])->layout('layouts.admin');
    }
}
