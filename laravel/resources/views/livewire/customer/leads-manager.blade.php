<div>
    <section class="content-header">
        <div class="container-fluid">
            <div class="row mb-2">
                <div class="col-sm-6">
                    <h1><i class="fas fa-address-card"></i> My Leads</h1>
                </div>
            </div>
        </div>
    </section>
    <section class="content">
        <div class="container-fluid">
            <div class="card mb-4">
                <div class="card-header d-flex align-items-center justify-content-between flex-wrap gap-2">
                    <h3 class="card-title mb-0"><i class="fas fa-list mr-2"></i>Leads
                        <span class="badge badge-secondary ml-2">{{ $this->leads->count() }}</span>
                    </h3>
                    <div class="d-flex gap-2 flex-wrap">
                        <input wire:model.debounce.300ms="search"
                               type="text"
                               class="form-control form-control-sm"
                               placeholder="Search name, email, phone..."
                               style="width:220px;">
                        <select wire:model="statusFilter" class="form-control form-control-sm" style="width:140px;">
                            <option value="">All Statuses</option>
                            <option value="new">New</option>
                            <option value="contacted">Contacted</option>
                            <option value="qualified">Qualified</option>
                            <option value="converted">Converted</option>
                            <option value="lost">Lost</option>
                        </select>
                    </div>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-striped table-hover mb-0">
                            <thead class="thead-dark">
                                <tr>
                                    <th>Name</th>
                                    <th>Email</th>
                                    <th>Phone</th>
                                    <th>Location</th>
                                    <th>Source</th>
                                    <th>Intent</th>
                                    <th>Priority</th>
                                    <th>Status</th>
                                    <th>Created</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($this->leads as $lead)
                                    @php
                                        $locationData = is_array($lead->location_data)
                                            ? $lead->location_data
                                            : json_decode($lead->location_data ?? '{}', true);
                                        $locationParts = array_filter([
                                            $locationData['city'] ?? '',
                                            $locationData['region'] ?? '',
                                            $locationData['country'] ?? '',
                                        ]);
                                        $locationStr = implode(', ', $locationParts) ?: '-';

                                        $priorityBadge = match(strtolower($lead->priority ?? 'normal')) {
                                            'high'     => 'badge-warning',
                                            'critical' => 'badge-danger',
                                            default    => 'badge-secondary',
                                        };
                                        $statusBadge = match(strtolower($lead->status ?? 'new')) {
                                            'new'       => 'badge-primary',
                                            'contacted' => 'badge-info',
                                            'qualified' => 'badge-success',
                                            'converted' => 'badge-success',
                                            'lost'      => 'badge-danger',
                                            default     => 'badge-secondary',
                                        };
                                    @endphp
                                    <tr>
                                        <td><strong>{{ $lead->name ?: '-' }}</strong></td>
                                        <td>{{ $lead->email ?: '-' }}</td>
                                        <td>{{ $lead->phone ?: '-' }}</td>
                                        <td><small>{{ $locationStr }}</small></td>
                                        <td><span class="badge badge-light">{{ $lead->source ?: '-' }}</span></td>
                                        <td>{{ $lead->intent ?? '-' }}</td>
                                        <td><span class="badge {{ $priorityBadge }}">{{ ucfirst($lead->priority ?? 'Normal') }}</span></td>
                                        <td><span class="badge {{ $statusBadge }}">{{ ucfirst($lead->status ?? 'New') }}</span></td>
                                        <td><small>{{ $lead->created_at->format('M d, Y H:i') }}</small></td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="9" class="text-center text-muted py-4">
                                            <i class="fas fa-inbox fa-2x mb-2 d-block"></i>
                                            {{ $search || $statusFilter ? 'No leads match your filters.' : 'No leads captured yet.' }}
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </section>
</div>
