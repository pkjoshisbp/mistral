<div>
    <!-- Search and Filter -->
    <div class="card">
        <div class="card-body">
            <div class="row">
                <div class="col-md-6">
                    <div class="form-group">
                        <label for="search">Search Users</label>
                        <input type="text" class="form-control" wire:model.live="search" placeholder="Search by name or email...">
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="form-group">
                        <label for="role">Filter by Role</label>
                        <select class="form-control" wire:model.live="selectedRole">
                            <option value="">All Roles</option>
                            <option value="admin">Admin</option>
                            <option value="customer">Customer</option>
                        </select>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Users Table -->
    <div class="card mt-4">
        <div class="card-header">
            <h4 class="card-title mb-0">
                <i class="fas fa-users"></i>
                User Management
            </h4>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-striped">
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Email</th>
                            <th>Role</th>
                            <th>Organizations</th>
                            <th>Created</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($users as $user)
                            <tr>
                                <td>{{ $user->name }}</td>
                                <td>{{ $user->email }}</td>
                                <td>
                                    <span class="badge badge-{{ $user->role === 'admin' ? 'primary' : 'success' }}">
                                        {{ ucfirst($user->role) }}
                                    </span>
                                </td>
                                <td>
                                    @if($user->organizations->count() > 0)
                                        @foreach($user->organizations as $org)
                                            <span class="badge badge-info me-1">{{ $org->name }}</span>
                                            @if($user->role === 'customer')
                                                <button wire:click="removeFromOrganization({{ $user->id }}, {{ $org->id }})" 
                                                        class="btn btn-xs btn-outline-danger"
                                                        onclick="return confirm('Remove user from this organization?')">
                                                    <i class="fas fa-times"></i>
                                                </button>
                                            @endif
                                        @endforeach
                                    @else
                                        <span class="text-muted">No organizations</span>
                                        @if($user->role === 'customer')
                                            <div class="dropdown d-inline">
                                                <button class="btn btn-sm btn-outline-primary dropdown-toggle" type="button" data-toggle="dropdown">
                                                    Assign
                                                </button>
                                                <div class="dropdown-menu">
                                                    @foreach($organizations as $org)
                                                        <a class="dropdown-item" href="#" 
                                                           wire:click="assignOrganization({{ $user->id }}, {{ $org->id }})">
                                                            {{ $org->name }}
                                                        </a>
                                                    @endforeach
                                                </div>
                                            </div>
                                        @endif
                                    @endif
                                </td>
                                <td>{{ $user->created_at->format('M j, Y') }}</td>
                                <td>
                                    <button wire:click="editUser({{ $user->id }})" class="btn btn-sm btn-outline-primary">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    @if($user->id !== auth()->id())
                                        <button wire:click="deleteUser({{ $user->id }})" 
                                                class="btn btn-sm btn-outline-danger"
                                                onclick="return confirm('Are you sure you want to delete this user?')">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            
            <!-- Pagination -->
            <div class="d-flex justify-content-center">
                {{ $users->links() }}
            </div>
        </div>
    </div>

    <!-- Edit User Modal -->
    @if($showEditModal)
        <div class="modal fade show" style="display: block; background-color: rgba(0,0,0,0.5);">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">Edit User</h5>
                        <button type="button" class="close" wire:click="$set('showEditModal', false)">
                            <span>&times;</span>
                        </button>
                    </div>
                    <div class="modal-body">
                        <form wire:submit.prevent="updateUser">
                            <div class="form-group mb-3">
                                <label for="name">Name</label>
                                <input type="text" class="form-control @error('name') is-invalid @enderror" 
                                       wire:model="name">
                                @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                            
                            <div class="form-group mb-3">
                                <label for="email">Email</label>
                                <input type="email" class="form-control @error('email') is-invalid @enderror" 
                                       wire:model="email">
                                @error('email')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                            
                            <div class="form-group mb-3">
                                <label for="role">Role</label>
                                <select class="form-control @error('role') is-invalid @enderror" wire:model="role">
                                    <option value="">Select Role</option>
                                    <option value="admin">Admin</option>
                                    <option value="customer">Customer</option>
                                </select>
                                @error('role')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                            
                            <div class="form-group mb-3">
                                <label for="password">Password (leave blank to keep current)</label>
                                <input type="password" class="form-control @error('password') is-invalid @enderror" 
                                       wire:model="password">
                                @error('password')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                            
                            @if($role === 'customer')
                                <div class="form-group mb-3">
                                    <label for="organizations">Organizations</label>
                                    @foreach($organizations as $org)
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" 
                                                   wire:model="selectedOrganizations" value="{{ $org->id }}"
                                                   id="org_{{ $org->id }}">
                                            <label class="form-check-label" for="org_{{ $org->id }}">
                                                {{ $org->name }}
                                            </label>
                                        </div>
                                    @endforeach
                                </div>
                            @endif
                            
                            <div class="d-flex justify-content-end">
                                <button type="button" class="btn btn-secondary me-2" wire:click="$set('showEditModal', false)">
                                    Cancel
                                </button>
                                <button type="submit" class="btn btn-primary">
                                    Update User
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    @endif
</div>
