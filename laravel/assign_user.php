<?php

require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';

$user = \App\Models\User::where('email', 'customer@ai-chat.support')->first();
$org = \App\Models\Organization::where('slug', 'ai-chat-support')->first();

if ($user && $org) {
    if (!$user->organizations->contains($org->id)) {
        $user->organizations()->attach($org->id);
        echo "User assigned to organization successfully\n";
    } else {
        echo "User already assigned to organization\n";
    }
} else {
    echo "User or organization not found\n";
}
