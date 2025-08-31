<?php

namespace App\Livewire\Admin;

use Livewire\Component;
use Livewire\WithPagination;
use App\Models\User;
use App\Models\Organization;
use Illuminate\Support\Facades\Hash;

class UserManager extends Component
{
    use WithPagination;

    public $search = '';
    public $selectedRole = '';
    public $showEditModal = false;
    public $editingUser = null;
    
    // Edit form fields
    public $name = '';
    public $email = '';
    public $role = '';
    public $password = '';
    public $selectedOrganizations = [];

    protected $rules = [
        'name' => 'required|string|max:255',
        'email' => 'required|email|unique:users,email',
        'role' => 'required|in:admin,customer',
        'password' => 'nullable|min:8',
    ];

    public function updatingSearch()
    {
        $this->resetPage();
    }

    public function editUser($userId)
    {
        $this->editingUser = User::with('organizations')->findOrFail($userId);
        $this->name = $this->editingUser->name;
        $this->email = $this->editingUser->email;
        $this->role = $this->editingUser->role;
        $this->selectedOrganizations = $this->editingUser->organizations->pluck('id')->toArray();
        $this->password = '';
        
        $this->rules['email'] = 'required|email|unique:users,email,' . $userId;
        $this->showEditModal = true;
    }

    public function assignOrganization($userId, $organizationId)
    {
        $user = User::findOrFail($userId);
        $organization = Organization::findOrFail($organizationId);
        
        if (!$user->organizations->contains($organizationId)) {
            $user->organizations()->attach($organizationId, ['role' => 'member']);
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
        $this->validate();

        $this->editingUser->update([
            'name' => $this->name,
            'email' => $this->email,
            'role' => $this->role,
        ]);

        if ($this->password) {
            $this->editingUser->update(['password' => Hash::make($this->password)]);
        }

        // Update organization assignments
        $this->editingUser->organizations()->sync($this->selectedOrganizations);

        session()->flash('success', 'User updated successfully!');
        $this->showEditModal = false;
        $this->resetForm();
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

        return view('livewire.admin.user-manager', compact('users', 'organizations'));
    }
}
