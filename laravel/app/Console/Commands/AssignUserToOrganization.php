<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\User;
use App\Models\Organization;

class AssignUserToOrganization extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'user:assign-organization {email} {slug}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Assign a user to an organization';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $email = $this->argument('email');
        $slug = $this->argument('slug');
        
        $user = User::where('email', $email)->first();
        $organization = Organization::where('slug', $slug)->first();
        
        if (!$user) {
            $this->error("User with email {$email} not found");
            return 1;
        }
        
        if (!$organization) {
            $this->error("Organization with slug {$slug} not found");
            return 1;
        }
        
        if ($user->organizations->contains($organization->id)) {
            $this->info("User {$email} is already assigned to organization {$organization->name}");
            return 0;
        }
        
        $user->organizations()->attach($organization->id);
        $this->info("Successfully assigned user {$email} to organization {$organization->name}");
        
        return 0;
    }
}
