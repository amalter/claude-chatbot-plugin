<?php
/**
 * Plugin Name: Content-Aware Chatbot
 * Description: A chatbot that answers questions based on website content using Claude AI
 * Version: 1.0
 * Author: Amber & AI
 */

// Prevent direct access
if (!defined('ABSPATH')) exit;

class ContentAwareChatbot {
    private $content_index = [];
    private $options_page_slug = 'chatbot-settings';
    private $option_name = 'chatbot_settings';
    
    public function __construct() {
        // Initialize hooks
        add_action('init', [$this, 'init_plugin']);
        add_action('admin_menu', [$this, 'add_admin_menu']);
        add_action('admin_init', [$this, 'register_settings']);
        add_action('wp_enqueue_scripts', [$this, 'enqueue_scripts']);
        add_action('wp_footer', [$this, 'add_chatbot_html']);
        add_action('rest_api_init', [$this, 'register_endpoints']);
    }

    public function init_plugin() {
        $this->build_content_index();
    }

    private function build_content_index() {
        // Get all public post types
        $post_types = get_post_types([
            'public' => true,
        ]);
        
        // Get all published content from all public post types
        $args = array(
            'post_type' => array_values($post_types), // Include all public post types
            'post_status' => 'publish',
            'posts_per_page' => -1,
        );
        
        $posts = get_posts($args);
        
        foreach ($posts as $post) {
            // Get all meta data for the post
            $meta = get_post_meta($post->ID);
            $meta_content = '';
            
            // Convert meta data to searchable text
            foreach ($meta as $key => $values) {
                // Skip internal WordPress meta
                if (strpos($key, '_') === 0) continue;
                
                foreach ($values as $value) {
                    $meta_content .= ' ' . maybe_unserialize($value);
                }
            }
            
            // Get post excerpt
            $excerpt = get_the_excerpt($post);
            
            $this->content_index[] = array(
                'id' => $post->ID,
                'title' => $post->post_title,
                'content' => wp_strip_all_tags($post->post_content) . ' ' . 
                            wp_strip_all_tags($excerpt) . ' ' . 
                            wp_strip_all_tags($meta_content),
                'url' => get_permalink($post->ID)
            );
        }

        // Add About/Bio information if it exists in options
        $site_title = get_bloginfo('name');
        $site_description = get_bloginfo('description');
        $this->content_index[] = array(
            'id' => 0,
            'title' => 'Site Information',
            'content' => "This is {$site_title}. {$site_description}",
            'url' => home_url()
        );
    }

    public function add_admin_menu() {
        add_options_page(
            'Chatbot Settings',          // Page title
            'Chatbot Settings',          // Menu title
            'manage_options',            // Capability required
            $this->options_page_slug,    // Menu slug
            [$this, 'render_settings_page'] // Callback function
        );
    }

    public function register_settings() {
        // Register settings section
        add_settings_section(
            'chatbot_api_settings',
            'API Configuration',
            [$this, 'render_section_info'],
            $this->options_page_slug
        );

        // Register API key field
        register_setting(
            $this->option_name,
            $this->option_name,
            [$this, 'sanitize_settings']
        );

        // Add API key field
        add_settings_field(
            'anthropic_api_key',
            'Anthropic API Key',
            [$this, 'render_api_key_field'],
            $this->options_page_slug,
            'chatbot_api_settings'
        );
    }

    public function sanitize_settings($input) {
        $sanitized = [];
        
        if (isset($input['anthropic_api_key'])) {
            $api_key = sanitize_text_field($input['anthropic_api_key']);
            
            // Only encrypt if the API key is not empty and has changed
            if (!empty($api_key)) {
                $current_options = get_option($this->option_name);
                $current_key = isset($current_options['anthropic_api_key']) ? $this->decrypt_api_key($current_options['anthropic_api_key']) : '';
                
                // Only encrypt if the key has changed
                if ($api_key !== $current_key) {
                    $sanitized['anthropic_api_key'] = $this->encrypt_api_key($api_key);
                } else {
                    // Keep the already encrypted key
                    $sanitized['anthropic_api_key'] = $current_options['anthropic_api_key'];
                }
            } else {
                $sanitized['anthropic_api_key'] = '';
            }
        }

        return $sanitized;
    }

    public function render_section_info() {
        echo '<p>Enter your Anthropic API key to enable the chatbot. You can get your API key from the <a href="https://console.anthropic.com/" target="_blank">Anthropic Console</a>.</p>';
    }

    public function render_api_key_field() {
        $options = get_option($this->option_name);
        $api_key = isset($options['anthropic_api_key']) ? $this->decrypt_api_key($options['anthropic_api_key']) : '';
        
        ?>
        <input type="password" 
               id="anthropic_api_key" 
               name="<?php echo $this->option_name; ?>[anthropic_api_key]" 
               value="<?php echo esc_attr($api_key); ?>" 
               class="regular-text"
               autocomplete="off">
        <p class="description">Your API key will be encrypted before being stored in the database.</p>
        <?php
    }

    public function render_settings_page() {
        // Check user capabilities
        if (!current_user_can('manage_options')) {
            return;
        }

        // Show success message if settings were updated
        if (isset($_GET['settings-updated'])) {
            add_settings_error(
                'chatbot_messages',
                'chatbot_message',
                'Settings Saved',
                'updated'
            );
        }

        ?>
        <div class="wrap">
            <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
            
            <?php settings_errors('chatbot_messages'); ?>

            <form action="options.php" method="post">
                <?php
                settings_fields($this->option_name);
                do_settings_sections($this->options_page_slug);
                submit_button('Save Settings');
                ?>
            </form>

            <div class="chatbot-status">
                <h2>Chatbot Status</h2>
                <?php $this->render_status_check(); ?>
            </div>
        </div>
        <?php
    }

    private function render_status_check() {
        $options = get_option($this->option_name);
        $encrypted_key = isset($options['anthropic_api_key']) ? $options['anthropic_api_key'] : '';
        $api_key = empty($encrypted_key) ? '' : $this->decrypt_api_key($encrypted_key);
        
        if (empty($api_key)) {
            echo '<div class="notice notice-warning"><p>⚠️ API key not configured. The chatbot will not function until you add an API key.</p></div>';
            return;
        }

        // Updated regex pattern to match actual Anthropic API key format
        if (!preg_match('/^sk-ant-api\d+-[A-Za-z0-9_-]+$/', $api_key)) {
            echo '<div class="notice notice-error"><p>❌ Invalid API key format. API key should start with "sk-ant-api" followed by the key.</p></div>';
            return;
        }

        echo '<div class="notice notice-success"><p>✅ API key configured. The chatbot is ready to use.</p></div>';
    }

    public function get_api_key() {
        $options = get_option($this->option_name);
        $encrypted_key = isset($options['anthropic_api_key']) ? $options['anthropic_api_key'] : '';
        
        if (empty($encrypted_key)) {
            return '';
        }
        
        return $this->decrypt_api_key($encrypted_key);
    }

    /**
     * Encrypt API key
     */
    private function encrypt_api_key($api_key) {
        // Generate a random encryption key if not already set
        $encryption_key = get_option('chatbot_encryption_key');
        if (!$encryption_key) {
            $encryption_key = bin2hex(random_bytes(32)); // 256-bit key
            update_option('chatbot_encryption_key', $encryption_key);
        }
        
        // Create initialization vector
        $iv = openssl_random_pseudo_bytes(openssl_cipher_iv_length('aes-256-cbc'));
        
        // Encrypt the API key
        $encrypted = openssl_encrypt(
            $api_key,
            'aes-256-cbc',
            hex2bin($encryption_key),
            0,
            $iv
        );
        
        // Combine IV and encrypted data
        return base64_encode($iv . $encrypted);
    }

    /**
     * Decrypt API key
     */
    private function decrypt_api_key($encrypted_data) {
        $encryption_key = get_option('chatbot_encryption_key');
        
        // If no encryption key exists, we can't decrypt
        if (!$encryption_key) {
            return '';
        }
        
        // Decode the combined string
        $decoded = base64_decode($encrypted_data);
        if ($decoded === false) {
            return '';
        }
        
        // Extract IV and encrypted data
        $iv_length = openssl_cipher_iv_length('aes-256-cbc');
        if (strlen($decoded) <= $iv_length) {
            return '';
        }
        
        $iv = substr($decoded, 0, $iv_length);
        $encrypted = substr($decoded, $iv_length);
        
        // Decrypt
        $decrypted = openssl_decrypt(
            $encrypted,
            'aes-256-cbc',
            hex2bin($encryption_key),
            0,
            $iv
        );
        
        return $decrypted !== false ? $decrypted : '';
    }

    public function process_query($request) {
        $api_key = $this->get_api_key();
        
        if (empty($api_key)) {
            return new WP_Error(
                'no_api_key',
                'Chatbot is not configured. Please add your API key in the WordPress dashboard.',
                ['status' => 503]
            );
        }

        $query = sanitize_text_field($request->get_param('query'));
        if (empty($query)) {
            return new WP_Error(
                'empty_query',
                'Query cannot be empty',
                ['status' => 400]
            );
        }

        // Search through content index for relevant content
        $relevant_content = $this->search_content($query);
        
        try {
            // Generate response using Claude
            $response = $this->generate_claude_response($query, $relevant_content, $api_key);
            
            if (is_wp_error($response)) {
                error_log('Claude API Error: ' . print_r($response, true));
                return $response;
            }

            return new WP_REST_Response([
                'response' => $response['message'],
                'sources' => isset($relevant_content['sources']) ? $relevant_content['sources'] : []
            ], 200);

        } catch (Exception $e) {
            error_log('Chatbot Error: ' . $e->getMessage());
            return new WP_Error(
                'chatbot_error',
                'Error processing request: ' . $e->getMessage(),
                ['status' => 500]
            );
        }
    }

    private function search_content($query) {
        $relevant_posts = [];
        $sources = [];
        $search_terms = explode(' ', strtolower($query));
        
        foreach ($this->content_index as $post) {
            $relevance_score = 0;
            $content_lower = strtolower($post['content']);
            $title_lower = strtolower($post['title']);
            
            foreach ($search_terms as $term) {
                // Skip common words
                if (strlen($term) < 3) continue;
                
                // Higher score for title matches
                if (stripos($title_lower, $term) !== false) {
                    $relevance_score += 2;
                }
                
                // Score for content matches
                if (stripos($content_lower, $term) !== false) {
                    $relevance_score += 1;
                }
            }
            
            // Include content if it has any relevance
            if ($relevance_score > 0) {
                $relevant_posts[] = $post['content'];
                $sources[] = [
                    'title' => $post['title'],
                    'url' => $post['url']
                ];
            }
        }

        // If no relevant content found, include site information
        if (empty($relevant_posts)) {
            $site_name = get_bloginfo('name');
            $site_description = get_bloginfo('description');
            $relevant_posts[] = "This website belongs to {$site_name}. {$site_description}";
        }

        return [
            'content' => implode("\n\n", $relevant_posts),
            'sources' => $sources
        ];
    }

    private function generate_claude_response($query, $relevant_content, $api_key) {
        $curl = curl_init();
        
        $system_prompt = "You are a helpful website assistant. Use the provided content to answer questions. If you can't find relevant information in the content, say so.";
        $user_prompt = "Based on this content:\n\n{$relevant_content['content']}\n\nQuestion: {$query}";

        $data = [
            'model' => 'claude-3-7-sonnet-20250219',
            'system' => $system_prompt,
            'messages' => [
                ['role' => 'user', 'content' => $user_prompt]
            ],
            'temperature' => 0.7,
            'max_tokens' => 4096
        ];

        // Debug log the request data
        error_log('Claude API Request Data: ' . json_encode($data));

        curl_setopt_array($curl, [
            CURLOPT_URL => 'https://api.anthropic.com/v1/messages',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'POST',
            CURLOPT_POSTFIELDS => json_encode($data),
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'x-api-key: ' . $api_key,
                'anthropic-version: 2023-06-01'
            ],
        ]);

        $response = curl_exec($curl);
        $http_code = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        $err = curl_error($curl);
        
        // Debug log the response
        error_log('Claude API Response Code: ' . $http_code);
        error_log('Claude API Raw Response: ' . $response);
        
        curl_close($curl);
        
        if ($err) {
            error_log('Curl Error: ' . $err);
            return new WP_Error('curl_error', $err);
        }
        
        $decoded_response = json_decode($response, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            error_log('JSON Decode Error: ' . json_last_error_msg());
            error_log('Raw Response: ' . $response);
            return new WP_Error('json_decode_error', 'Failed to decode API response');
        }
        
        // Check for API errors
        if (isset($decoded_response['error'])) {
            $error_message = $decoded_response['error']['message'];
            error_log('Claude API Error: ' . $error_message);
            
            if (strpos($error_message, 'credit balance is too low') !== false) {
                return new WP_Error(
                    'api_error',
                    'The chatbot is currently unavailable due to API credit limits. Please contact the site administrator.'
                );
            }
            
            return new WP_Error(
                'api_error',
                'Claude API Error: ' . $error_message
            );
        }

        // Handle successful response
        if (!isset($decoded_response['content']) || 
            !is_array($decoded_response['content']) || 
            empty($decoded_response['content'][0]['text'])) {
            error_log('Unexpected API Response Structure: ' . print_r($decoded_response, true));
            return new WP_Error('api_error', 'Unexpected API response format');
        }

        return [
            'message' => $decoded_response['content'][0]['text']
        ];
    }

    public function enqueue_scripts() {
        wp_enqueue_style(
            'content-aware-chatbot',
            plugins_url('css/chatbot.css', __FILE__),
            [],
            '1.0.0'
        );

        wp_enqueue_script(
            'content-aware-chatbot',
            plugins_url('js/chatbot.js', __FILE__),
            ['jquery'],
            '1.0.0',
            true
        );

        wp_localize_script(
            'content-aware-chatbot',
            'chatbotAjax',
            [
                'ajaxurl' => rest_url('content-aware-chatbot/v1/query'),
                'nonce' => wp_create_nonce('wp_rest')
            ]
        );
    }

    public function add_chatbot_html() {
        ?>
        <div id="chatbot-widget" class="chatbot-container">
            <div class="chatbot-header">
                <h3>Website Assistant</h3>
                <button class="chatbot-toggle">-</button>
            </div>
            <div class="chatbot-messages">
                <!-- Messages will be added here dynamically -->
            </div>
            <div class="chatbot-input">
                <input type="text" class="chatbot-input-field" placeholder="Type your message...">
                <button class="chatbot-send" disabled>Send</button>
            </div>
        </div>
        <?php
    }

    public function register_endpoints() {
        register_rest_route('content-aware-chatbot/v1', '/query', [
            'methods' => WP_REST_Server::CREATABLE,
            'callback' => [$this, 'process_query'],
            'permission_callback' => '__return_true',
            'args' => [
                'query' => [
                    'required' => true,
                    'type' => 'string'
                ]
            ]
        ]);
    }

    // Rest of the class methods remain the same...
}

// Initialize the plugin
new ContentAwareChatbot();
