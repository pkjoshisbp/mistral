<?php
/**
 * Plugin Name: AI Chat Support
 * Plugin URI: https://ai-chat.support
 * Description: Connect your WordPress/WooCommerce store with AI Chat Support for automated customer service.
 * Version: 1.0.0
 * Author: AI Chat Support Team
 * Author URI: https://ai-chat.support
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: ai-chat-support
 * Requires at least: 5.0
 * Tested up to: 6.4
 * Requires PHP: 7.4
 * Network: false
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('AICS_VERSION', '1.0.0');
define('AICS_PLUGIN_URL', plugin_dir_url(__FILE__));
define('AICS_PLUGIN_PATH', plugin_dir_path(__FILE__));
define('AICS_PLUGIN_FILE', __FILE__);

/**
 * Main AI Chat Support Plugin Class
 */
class AIChatSupport {

    private static $instance = null;
    private $api_url = 'https://ai-chat.support';

    /**
     * Get singleton instance
     */
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Constructor
     */
    private function __construct() {
        add_action('init', array($this, 'init'));
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_init', array($this, 'settings_init'));
        add_action('wp_head', array($this, 'output_widget_script'));
        add_action('wp_ajax_aics_register', array($this, 'handle_registration'));
        add_action('wp_ajax_aics_test_connection', array($this, 'test_connection'));
        
        // Add settings link on plugin page
        add_filter('plugin_action_links_' . plugin_basename(__FILE__), array($this, 'add_settings_link'));
    }

    /**
     * Initialize plugin
     */
    public function init() {
        // Load text domain for translations
        load_plugin_textdomain('ai-chat-support', false, dirname(plugin_basename(__FILE__)) . '/languages');
    }

    /**
     * Add admin menu
     */
    public function add_admin_menu() {
        add_menu_page(
            __('AI Chat Support', 'ai-chat-support'),
            __('AI Chat Support', 'ai-chat-support'),
            'manage_options',
            'ai-chat-support',
            array($this, 'admin_page'),
            'data:image/svg+xml;base64,' . base64_encode('<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor"><path d="M20 2H4c-1.1 0-2 .9-2 2v12c0 1.1.9 2 2 2h4l4 4 4-4h4c1.1 0 2-.9 2-2V4c0-1.1-.9-2-2-2zm-2 12H6v-2h12v2zm0-3H6V9h12v2zm0-3H6V6h12v2z"/></svg>'),
            30
        );
    }

    /**
     * Initialize settings
     */
    public function settings_init() {
        register_setting('aics_settings', 'aics_options', array($this, 'sanitize_options'));
        
        add_settings_section(
            'aics_connection_section',
            __('Connection Settings', 'ai-chat-support'),
            array($this, 'connection_section_callback'),
            'ai-chat-support'
        );

        add_settings_field(
            'aics_api_url',
            __('API URL', 'ai-chat-support'),
            array($this, 'api_url_field'),
            'ai-chat-support',
            'aics_connection_section'
        );

        add_settings_field(
            'aics_org_id',
            __('Organization ID', 'ai-chat-support'),
            array($this, 'org_id_field'),
            'ai-chat-support',
            'aics_connection_section'
        );

        add_settings_field(
            'aics_status',
            __('Connection Status', 'ai-chat-support'),
            array($this, 'status_field'),
            'ai-chat-support',
            'aics_connection_section'
        );

        // Widget settings section
        add_settings_section(
            'aics_widget_section',
            __('Widget Settings', 'ai-chat-support'),
            array($this, 'widget_section_callback'),
            'ai-chat-support'
        );

        add_settings_field(
            'aics_widget_enabled',
            __('Enable Widget', 'ai-chat-support'),
            array($this, 'widget_enabled_field'),
            'ai-chat-support',
            'aics_widget_section'
        );

        add_settings_field(
            'aics_widget_position',
            __('Widget Position', 'ai-chat-support'),
            array($this, 'widget_position_field'),
            'ai-chat-support',
            'aics_widget_section'
        );

        add_settings_field(
            'aics_primary_color',
            __('Primary Color', 'ai-chat-support'),
            array($this, 'primary_color_field'),
            'ai-chat-support',
            'aics_widget_section'
        );

        add_settings_field(
            'aics_welcome_message',
            __('Welcome Message', 'ai-chat-support'),
            array($this, 'welcome_message_field'),
            'ai-chat-support',
            'aics_widget_section'
        );
    }

    /**
     * Sanitize options
     */
    public function sanitize_options($input) {
        $output = array();
        
        if (isset($input['api_url'])) {
            $output['api_url'] = esc_url_raw($input['api_url']);
        }
        
        if (isset($input['org_id'])) {
            $output['org_id'] = absint($input['org_id']);
        }
        
        if (isset($input['widget_enabled'])) {
            $output['widget_enabled'] = (bool) $input['widget_enabled'];
        }
        
        if (isset($input['widget_position'])) {
            $output['widget_position'] = sanitize_text_field($input['widget_position']);
        }
        
        if (isset($input['primary_color'])) {
            $output['primary_color'] = sanitize_hex_color($input['primary_color']);
        }
        
        if (isset($input['welcome_message'])) {
            $output['welcome_message'] = sanitize_textarea_field($input['welcome_message']);
        }
        
        return $output;
    }

    /**
     * Admin page
     */
    public function admin_page() {
        $options = get_option('aics_options', array());
        ?>
        <div class="wrap">
            <h1><?php _e('AI Chat Support', 'ai-chat-support'); ?></h1>
            
            <?php if (isset($_GET['registered']) && $_GET['registered'] === '1'): ?>
                <div class="notice notice-success is-dismissible">
                    <p><?php _e('Successfully registered with AI Chat Support!', 'ai-chat-support'); ?></p>
                </div>
            <?php endif; ?>

            <div class="card" style="max-width: none;">
                <h2 class="title"><?php _e('Getting Started', 'ai-chat-support'); ?></h2>
                <p><?php _e('Connect your WordPress site with AI Chat Support to add an intelligent chat widget that can answer customer questions automatically.', 'ai-chat-support'); ?></p>
                
                <?php if (empty($options['org_id'])): ?>
                    <div class="notice notice-info inline">
                        <p><strong><?php _e('Setup Required:', 'ai-chat-support'); ?></strong> <?php _e('Please register your site with AI Chat Support to get started.', 'ai-chat-support'); ?></p>
                    </div>
                    
                    <div style="background: #f0f0f1; padding: 20px; margin: 20px 0; border-left: 4px solid #0073aa;">
                        <h3><?php _e('Quick Setup', 'ai-chat-support'); ?></h3>
                        <p><?php _e('Fill in your organization details and register your site with AI Chat Support:', 'ai-chat-support'); ?></p>
                        <ul>
                            <li><?php _e('Create your organization account', 'ai-chat-support'); ?></li>
                            <li><?php _e('Generate your unique widget configuration', 'ai-chat-support'); ?></li>
                            <li><?php _e('Enable the chat widget on your site', 'ai-chat-support'); ?></li>
                            <li><?php _e('Get 20,000 free tokens for testing', 'ai-chat-support'); ?></li>
                        </ul>
                        
                        <form id="aics-register-form" style="background: white; padding: 20px; border-radius: 5px; margin: 15px 0;">
                            <table class="form-table">
                                <tr>
                                    <th scope="row"><?php _e('Site Name', 'ai-chat-support'); ?></th>
                                    <td>
                                        <input type="text" id="aics-site-name" value="<?php echo esc_attr(get_bloginfo('name')); ?>" 
                                               class="regular-text" required />
                                        <p class="description"><?php _e('Your organization name', 'ai-chat-support'); ?></p>
                                    </td>
                                </tr>
                                <tr>
                                    <th scope="row"><?php _e('Contact Email', 'ai-chat-support'); ?></th>
                                    <td>
                                        <input type="email" id="aics-admin-email" value="<?php echo esc_attr(get_option('admin_email')); ?>" 
                                               class="regular-text" required />
                                        <p class="description"><?php _e('Primary contact email for your organization', 'ai-chat-support'); ?></p>
                                    </td>
                                </tr>
                                <tr>
                                    <th scope="row"><?php _e('Phone Number', 'ai-chat-support'); ?></th>
                                    <td>
                                        <input type="tel" id="aics-phone" class="regular-text" />
                                        <p class="description"><?php _e('Optional: Contact phone number', 'ai-chat-support'); ?></p>
                                    </td>
                                </tr>
                                <tr>
                                    <th scope="row"><?php _e('Organization Description', 'ai-chat-support'); ?></th>
                                    <td>
                                        <textarea id="aics-description" rows="3" class="large-text" 
                                                  placeholder="<?php _e('Brief description of your business or website...', 'ai-chat-support'); ?>"></textarea>
                                        <p class="description"><?php _e('Optional: Helps AI provide more relevant responses', 'ai-chat-support'); ?></p>
                                    </td>
                                </tr>
                                <tr>
                                    <th scope="row"><?php _e('Welcome Message', 'ai-chat-support'); ?></th>
                                    <td>
                                        <textarea id="aics-welcome-message" rows="2" class="large-text" 
                                                  placeholder="<?php _e('Hello! How can I help you today?', 'ai-chat-support'); ?>">Hello! How can I help you today?</textarea>
                                        <p class="description"><?php _e('First message visitors see in the chat widget', 'ai-chat-support'); ?></p>
                                    </td>
                                </tr>
                            </table>
                            
                            <p>
                                <button type="button" id="aics-register-btn" class="button button-primary button-large">
                                    <?php _e('Register with AI Chat Support', 'ai-chat-support'); ?>
                                </button>
                                <span id="aics-register-loading" style="display: none;">
                                    <span class="spinner is-active" style="float: none; margin: 0 10px;"></span>
                                    <?php _e('Registering...', 'ai-chat-support'); ?>
                                </span>
                            </p>
                        </form>
                    </div>
                <?php endif; ?>
            </div>

            <form action="options.php" method="post">
                <?php
                settings_fields('aics_settings');
                do_settings_sections('ai-chat-support');
                
                if (!empty($options['org_id'])) {
                    submit_button(__('Save Settings', 'ai-chat-support'));
                }
                ?>
            </form>
            
            <?php if (!empty($options['org_id'])): ?>
                <div class="card">
                    <h2 class="title"><?php _e('Advanced Configuration', 'ai-chat-support'); ?></h2>
                    <p><?php _e('For advanced features like FAQ management, analytics, and custom styling, visit your', 'ai-chat-support'); ?> 
                        <a href="<?php echo esc_url($options['api_url'] ?? $this->api_url); ?>/customer/dashboard?org=<?php echo $options['org_id']; ?>" target="_blank">
                            <?php _e('AI Chat Support Dashboard', 'ai-chat-support'); ?>
                        </a>
                    </p>
                </div>
            <?php endif; ?>
        </div>

        <script>
        jQuery(document).ready(function($) {
            $('#aics-register-btn').on('click', function() {
                var $btn = $(this);
                var $loading = $('#aics-register-loading');
                
                // Validate required fields
                var siteName = $('#aics-site-name').val().trim();
                var adminEmail = $('#aics-admin-email').val().trim();
                
                if (!siteName) {
                    alert('<?php _e('Please enter a site name.', 'ai-chat-support'); ?>');
                    $('#aics-site-name').focus();
                    return;
                }
                
                if (!adminEmail) {
                    alert('<?php _e('Please enter a contact email.', 'ai-chat-support'); ?>');
                    $('#aics-admin-email').focus();
                    return;
                }
                
                $btn.hide();
                $loading.show();
                
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'aics_register',
                        nonce: '<?php echo wp_create_nonce('aics_register'); ?>',
                        site_url: '<?php echo home_url(); ?>',
                        site_name: siteName,
                        admin_email: adminEmail,
                        phone: $('#aics-phone').val().trim(),
                        description: $('#aics-description').val().trim(),
                        welcome_message: $('#aics-welcome-message').val().trim() || 'Hello! How can I help you today?'
                    },
                    success: function(response) {
                        if (response.success) {
                            window.location.href = window.location.href + '&registered=1';
                        } else {
                            alert('<?php _e('Registration failed: ', 'ai-chat-support'); ?>' + (response.data.message || 'Unknown error'));
                            $btn.show();
                            $loading.hide();
                        }
                    },
                    error: function() {
                        alert('<?php _e('Registration failed. Please try again.', 'ai-chat-support'); ?>');
                        $btn.show();
                        $loading.hide();
                    }
                });
            });
        });
        </script>
        <?php
    }

    // Field callbacks
    public function connection_section_callback() {
        echo '<p>' . __('Configure your connection to AI Chat Support.', 'ai-chat-support') . '</p>';
    }

    public function widget_section_callback() {
        echo '<p>' . __('Customize how the chat widget appears on your site.', 'ai-chat-support') . '</p>';
    }

    public function api_url_field() {
        $options = get_option('aics_options', array());
        $value = $options['api_url'] ?? $this->api_url;
        echo '<input type="url" id="aics_api_url" name="aics_options[api_url]" value="' . esc_attr($value) . '" class="regular-text" />';
        echo '<p class="description">' . __('The AI Chat Support API URL (default: https://ai-chat.support)', 'ai-chat-support') . '</p>';
    }

    public function org_id_field() {
        $options = get_option('aics_options', array());
        $value = $options['org_id'] ?? '';
        echo '<input type="number" id="aics_org_id" name="aics_options[org_id]" value="' . esc_attr($value) . '" class="regular-text" readonly />';
        echo '<p class="description">' . __('Your organization ID (automatically set during registration)', 'ai-chat-support') . '</p>';
    }

    public function status_field() {
        $options = get_option('aics_options', array());
        if (!empty($options['org_id'])) {
            echo '<span style="color: #46b450; font-weight: bold;">✓ ' . __('Connected', 'ai-chat-support') . '</span>';
            echo '<p class="description">' . sprintf(__('Connected as Organization ID: %s', 'ai-chat-support'), $options['org_id']) . '</p>';
        } else {
            echo '<span style="color: #dc3232; font-weight: bold;">✗ ' . __('Not Connected', 'ai-chat-support') . '</span>';
            echo '<p class="description">' . __('Please register to connect your site.', 'ai-chat-support') . '</p>';
        }
    }

    public function widget_enabled_field() {
        $options = get_option('aics_options', array());
        $checked = isset($options['widget_enabled']) ? $options['widget_enabled'] : true;
        echo '<input type="checkbox" id="aics_widget_enabled" name="aics_options[widget_enabled]" value="1" ' . checked(1, $checked, false) . ' />';
        echo '<label for="aics_widget_enabled">' . __('Show chat widget on your website', 'ai-chat-support') . '</label>';
    }

    public function widget_position_field() {
        $options = get_option('aics_options', array());
        $value = $options['widget_position'] ?? 'bottom-right';
        $positions = array(
            'bottom-right' => __('Bottom Right', 'ai-chat-support'),
            'bottom-left' => __('Bottom Left', 'ai-chat-support'),
            'top-right' => __('Top Right', 'ai-chat-support'),
            'top-left' => __('Top Left', 'ai-chat-support'),
        );
        
        echo '<select id="aics_widget_position" name="aics_options[widget_position]">';
        foreach ($positions as $key => $label) {
            echo '<option value="' . esc_attr($key) . '"' . selected($value, $key, false) . '>' . esc_html($label) . '</option>';
        }
        echo '</select>';
    }

    public function primary_color_field() {
        $options = get_option('aics_options', array());
        $value = $options['primary_color'] ?? '#007bff';
        echo '<input type="color" id="aics_primary_color" name="aics_options[primary_color]" value="' . esc_attr($value) . '" />';
        echo '<p class="description">' . __('The primary color for the chat widget', 'ai-chat-support') . '</p>';
    }

    public function welcome_message_field() {
        $options = get_option('aics_options', array());
        $value = $options['welcome_message'] ?? 'Hello! How can I help you today?';
        echo '<textarea id="aics_welcome_message" name="aics_options[welcome_message]" rows="3" cols="50" class="large-text">' . esc_textarea($value) . '</textarea>';
        echo '<p class="description">' . __('The message shown when users open the chat widget', 'ai-chat-support') . '</p>';
    }

    /**
     * Output widget script
     */
    public function output_widget_script() {
        $options = get_option('aics_options', array());
        
        // Only output if widget is enabled and org_id is set
        if (empty($options['org_id']) || (isset($options['widget_enabled']) && !$options['widget_enabled'])) {
            return;
        }

        $api_url = $options['api_url'] ?? $this->api_url;
        $org_id = $options['org_id'];
        
        ?>
        <script>
        (function() {
            // AI Chat Support Widget Loader
            var script = document.createElement('script');
            script.src = '<?php echo esc_js($api_url); ?>/api/integrations/widget-script/<?php echo esc_js($org_id); ?>';
            script.async = true;
            document.head.appendChild(script);
        })();
        </script>
        <?php
    }

    /**
     * Handle registration AJAX
     */
    public function handle_registration() {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'], 'aics_register')) {
            wp_die(__('Security check failed', 'ai-chat-support'));
        }

        // Check permissions
        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have sufficient permissions', 'ai-chat-support'));
        }

        $site_url = sanitize_url($_POST['site_url']);
        $site_name = sanitize_text_field($_POST['site_name']);
        $admin_email = sanitize_email($_POST['admin_email']);
        $phone = sanitize_text_field($_POST['phone'] ?? '');
        $description = sanitize_textarea_field($_POST['description'] ?? '');
        $welcome_message = sanitize_textarea_field($_POST['welcome_message'] ?? 'Hello! How can I help you today!');
        
        $options = get_option('aics_options', array());
        $api_url = $options['api_url'] ?? $this->api_url;

        // Step 1: Register with AI Chat Support
        $response = wp_remote_post($api_url . '/api/integrations/register', array(
            'body' => json_encode(array(
                'provider' => 'wordpress',
                'shop' => $site_url
            )),
            'headers' => array(
                'Content-Type' => 'application/json'
            ),
            'timeout' => 30
        ));

        if (is_wp_error($response)) {
            wp_send_json_error(array('message' => $response->get_error_message()));
            return;
        }

        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);

        if (!$data['ok'] || empty($data['token'])) {
            wp_send_json_error(array('message' => $data['message'] ?? 'Registration failed'));
            return;
        }

        // Step 2: Complete registration
        $complete_response = wp_remote_post($api_url . '/api/integrations/complete', array(
            'body' => json_encode(array(
                'token' => $data['token'],
                'site_name' => $site_name,
                'admin_email' => $admin_email,
                'phone' => $phone,
                'description' => $description,
                'welcome_message' => $welcome_message
            )),
            'headers' => array(
                'Content-Type' => 'application/json'
            ),
            'timeout' => 30
        ));

        if (is_wp_error($complete_response)) {
            wp_send_json_error(array('message' => $complete_response->get_error_message()));
            return;
        }

        $complete_body = wp_remote_retrieve_body($complete_response);
        $complete_data = json_decode($complete_body, true);

        if (!$complete_data['ok'] || empty($complete_data['org_id'])) {
            wp_send_json_error(array('message' => $complete_data['message'] ?? 'Registration completion failed'));
            return;
        }

        // Save organization ID
        $options['org_id'] = $complete_data['org_id'];
        $options['widget_enabled'] = true; // Enable widget by default
        update_option('aics_options', $options);

        wp_send_json_success(array(
            'message' => __('Registration successful!', 'ai-chat-support'),
            'org_id' => $complete_data['org_id']
        ));
    }

    /**
     * Test connection
     */
    public function test_connection() {
        if (!wp_verify_nonce($_POST['nonce'], 'aics_test')) {
            wp_die(__('Security check failed', 'ai-chat-support'));
        }

        $options = get_option('aics_options', array());
        if (empty($options['org_id'])) {
            wp_send_json_error(array('message' => 'No organization ID configured'));
            return;
        }

        $api_url = $options['api_url'] ?? $this->api_url;
        
        $response = wp_remote_get($api_url . '/api/integrations/widget-config/' . $options['org_id']);

        if (is_wp_error($response)) {
            wp_send_json_error(array('message' => $response->get_error_message()));
            return;
        }

        $code = wp_remote_retrieve_response_code($response);
        if ($code === 200) {
            wp_send_json_success(array('message' => 'Connection successful!'));
        } else {
            wp_send_json_error(array('message' => 'Connection failed (HTTP ' . $code . ')'));
        }
    }

    /**
     * Add settings link to plugin page
     */
    public function add_settings_link($links) {
        $settings_link = '<a href="admin.php?page=ai-chat-support">' . __('Settings', 'ai-chat-support') . '</a>';
        array_unshift($links, $settings_link);
        return $links;
    }
}

// Initialize the plugin
AIChatSupport::get_instance();

// Activation hook
register_activation_hook(__FILE__, function() {
    // Set default options
    $default_options = array(
        'api_url' => 'https://ai-chat.support',
        'widget_enabled' => true,
        'widget_position' => 'bottom-right',
        'primary_color' => '#007bff',
        'welcome_message' => 'Hello! How can I help you today?'
    );
    
    $existing_options = get_option('aics_options', array());
    $options = array_merge($default_options, $existing_options);
    update_option('aics_options', $options);
});

// Deactivation hook
register_deactivation_hook(__FILE__, function() {
    // Clean up if needed
});