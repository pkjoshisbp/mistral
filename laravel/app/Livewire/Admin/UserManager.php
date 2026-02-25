<?php

namespace App\Livewire\Admin;

use Livewire\Component;
use Livewire\WithPagination;
use App\Models\User;
use App\Models\Organization;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

class UserManager extends Component
{
    use WithPagination;

    public $search = '';
    public $selectedRole = '';
    public $showCreateModal = false;
    public $showEditModal = false;
    public $editingUser = null;
    
    // Edit form fields
    public $name = '';
    public $email = '';
    public $role = '';
    public $password = '';
    public $selectedOrganizations = [];

    protected function createRules(): array
    {
        $rules = [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'role' => ['required', Rule::in(['admin', 'customer'])],
            'password' => ['required', 'string', 'min:8'],
        ];

        if ($this->role === 'customer') {
            $rules['selectedOrganizations'] = ['required', 'array', 'min:1'];
            $rules['selectedOrganizations.*'] = ['integer', 'exists:organizations,id'];
        }

        return $rules;
    }

    protected function updateRules(): array
    {
        $rules = [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($this->editingUser?->id)],
            'role' => ['required', Rule::in(['admin', 'customer'])],
            'password' => ['nullable', 'string', 'min:8'],
        ];

        if ($this->role === 'customer') {
            $rules['selectedOrganizations'] = ['required', 'array', 'min:1'];
            $rules['selectedOrganizations.*'] = ['integer', 'exists:organizations,id'];
        }

        return $rules;
    }

    public function updatingSearch()
    {
        $this->resetPage();
    }

    public function editUser($userId)
    {
        $this->showCreateModal = false;
        $this->editingUser = User::with('organizations')->findOrFail($userId);
        $this->name = $this->editingUser->name;
        $this->email = $this->editingUser->email;
        $this->role = $this->editingUser->role;
        $this->selectedOrganizations = $this->editingUser->organizations->pluck('id')->toArray();
        $this->password = '';

        $this->showEditModal = true;
    }

    public function openCreateModal()
    {
        $this->showEditModal = false;
        $this->resetForm();
        $this->role = 'customer';
        $this->showCreateModal = true;
    }

    public function createUser()
    {
        $validated = $this->validate($this->createRules());

        $organizationId = $validated['role'] === 'customer'
            ? ($validated['selectedOrganizations'][0] ?? null)
            : null;

        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'role' => $validated['role'],
            'password' => Hash::make($validated['password']),
            'organization_id' => $organizationId,
        ]);

        if ($validated['role'] === 'customer') {
            $user->organizations()->sync($validated['selectedOrganizations']);
        }

        session()->flash('success', 'User created successfully!');
        $this->closeModals();
    }

    public function assignOrganization($userId, $organizationId)
    {
        $user = User::findOrFail($userId);
        $organization = Organization::findOrFail($organizationId);
        
        if (!$user->organizations->contains($organizationId)) {
            // Pivot table 'organization_user' does not have a 'role' column.
            // Attach without extra attributes to avoid SQL errors.
            $user->organizations()->attach($organizationId);
            session()->flash('success', 'Organization assigned successfully!');
        } else {
            session()->flash('info', 'User is already assigned to this organization.');
        }
    }

    public function removeFromOrganization($userId, $organizationId)
    {
        $user = User::findOrFail($userId);
        $user->organizations()->detach($organizationId);
        session()->flash('success', 'User removed from organization successfully!');
    }

    public function updateUser()
    {
        $validated = $this->validate($this->updateRules());

        $organizationId = $validated['role'] === 'customer'
            ? ($validated['selectedOrganizations'][0] ?? null)
            : null;

        $this->editingUser->update([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'role' => $validated['role'],
            'organization_id' => $organizationId,
        ]);

        if (!empty($validated['password'])) {
            $this->editingUser->update(['password' => Hash::make($validated['password'])]);
        }

        if ($validated['role'] === 'customer') {
            $this->editingUser->organizations()->sync($validated['selectedOrganizations']);
        } else {
            $this->editingUser->organizations()->sync([]);
        }

        session()->flash('success', 'User updated successfully!');
        $this->closeModals();
    }

    public function deleteUser($userId)
    {
        User::findOrFail($userId)->delete();
        session()->flash('success', 'User deleted successfully!');
    }

    public function resetForm()
    {
        $this->name = '';
        $this->email = '';
        $this->role = '';
        $this->password = '';
        $this->selectedOrganizations = [];
        $this->editingUser = null;
    }

    public function closeModals()
    {
        $this->showCreateModal = false;
        $this->showEditModal = false;
        $this->resetErrorBag();
        $this->resetValidation();
        $this->resetForm();
    }

    public function render()
    {
        $users = User::query()
            ->when($this->search, function ($query) {
                $query->where(function ($q) {
                    $q->where('name', 'like', '%' . $this->search . '%')
                      ->orWhere('email', 'like', '%' . $this->search . '%');
                });
            })
            ->when($this->selectedRole, function ($query) {
                $query->where('role', $this->selectedRole);
            })
            ->with('organizations')
            ->orderBy('created_at', 'desc')
            ->paginate(10);

        $organizations = Organization::all();

        return view('livewire.admin.user-manager', compact('users', 'organizations'))
            ->layout('layouts.admin');
    }
}
