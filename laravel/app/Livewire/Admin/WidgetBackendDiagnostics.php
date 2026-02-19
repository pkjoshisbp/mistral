<?php

namespace App\Livewire\Admin;

use App\Models\Organization;
use Illuminate\Support\Facades\File;
use Livewire\Component;

class WidgetBackendDiagnostics extends Component
{
    public int $limit = 50;
    public string $selectedOrganization = '';
    public array $entries = [];

    public function mount(): void
    {
        $this->loadEntries();
    }

    public function updatedSelectedOrganization(): void
    {
        $this->loadEntries();
    }

    public function refreshEntries(): void
    {
        $this->loadEntries();
    }

    private function loadEntries(): void
    {
        $logPath = storage_path('logs/laravel-' . now()->format('Y-m-d') . '.log');

        if (!File::exists($logPath)) {
            $this->entries = [];
            return;
        }

        $lines = File::lines($logPath)->toArray();
        $diagnosticLines = array_values(array_filter($lines, function (string $line): bool {
            return str_contains($line, 'Widget stream backend diagnostics');
        }));

        $diagnosticLines = array_reverse($diagnosticLines);

        $parsed = [];
        foreach ($diagnosticLines as $line) {
            if (!preg_match('/^\[(.*?)\].*Widget stream backend diagnostics\s(\{.*\})$/', $line, $matches)) {
                continue;
            }

            $timestamp = $matches[1] ?? '';
            $payloadRaw = $matches[2] ?? '';
            $payload = json_decode($payloadRaw, true);

            if (!is_array($payload)) {
                continue;
            }

            $orgId = (string) ($payload['org_id'] ?? '');
            if ($this->selectedOrganization !== '' && $orgId !== $this->selectedOrganization) {
                continue;
            }

            $parsed[] = [
                'timestamp' => $timestamp,
                'org_id' => $payload['org_id'] ?? null,
                'session_id' => $payload['session_id'] ?? null,
                'backend_used' => $payload['backend_used'] ?? 'unknown',
                'fallback_used' => (bool) ($payload['fallback_used'] ?? false),
                'attempts' => $payload['attempts'] ?? [],
            ];

            if (count($parsed) >= $this->limit) {
                break;
            }
        }

        $orgIds = collect($parsed)->pluck('org_id')->filter()->unique()->values();
        $orgMap = Organization::whereIn('id', $orgIds)->pluck('name', 'id');

        foreach ($parsed as &$entry) {
            $entry['organization_name'] = $entry['org_id'] ? ($orgMap[$entry['org_id']] ?? 'Unknown') : 'Unknown';
        }

        $this->entries = $parsed;
    }

    public function render()
    {
        return view('livewire.admin.widget-backend-diagnostics', [
            'organizations' => Organization::orderBy('name')->get(['id', 'name']),
        ])->layout('layouts.admin');
    }
}
