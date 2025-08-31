<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use App\Models\Organization;
use Illuminate\Support\Facades\Hash;

class AssignAiChatUserSeeder extends Seeder
{
    public function run()
    {
        // Find or create the user
        $user = User::firstOrCreate(
            ['email' => 'customer@ai-chat.support'],
            [
                'name' => 'AI Chat Support Customer',
                'password' => Hash::make('password123'),
                'role' => 'customer',
                'email_verified_at' => now()
            ]
        );

        // Find the AI Chat Support organization
        $organization = Organization::where('name', 'AI Chat Support')->first();

        if ($organization) {
            // Load the user's organizations relationship
            $user->load('organizations');
            
            // Check if user is already assigned
            if (!$user->organizations->contains($organization->id)) {
                $user->organizations()->attach($organization->id);
                echo "✅ User {$user->email} assigned to organization {$organization->name}\n";
            } else {
                echo "ℹ️  User {$user->email} already assigned to organization {$organization->name}\n";
            }
        } else {
            echo "❌ AI Chat Support organization not found\n";
        }

        // Display current assignments
        $user->load('organizations');
        echo "User organizations: " . $user->organizations->pluck('name')->implode(', ') . "\n";
    }
}
