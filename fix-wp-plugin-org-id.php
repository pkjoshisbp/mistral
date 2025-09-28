<?php
// Update WordPress plugin options to use correct organization ID
// Run this script from WordPress admin or via WP-CLI

// This should be run in WordPress context
if (function_exists('get_option')) {
    $options = get_option('aics_options', array());
    $options['org_id'] = 9; // Use the correct organization ID
    $options['widget_enabled'] = true;
    
    update_option('aics_options', $options);
    
    echo "Updated WordPress plugin to use organization ID 9\n";
    echo "Current options: " . print_r($options, true) . "\n";
} else {
    echo "This script must be run in WordPress context\n";
}
?>