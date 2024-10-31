<?php
/**
 * Plugin Name: REA Products for WooCommerce
 * Plugin URI:  https://reainc.net/rea-wc-products-plugin/
 * Description: Add custom endpoints for displaying WooCommerce products.
 * Version:     1.0.10
 * Author:      REA Inc.
 * Author URI:  https://reainc.net
 * License:     GPL-2.0+
 * License URI: http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain: reawcproducts
 * Requires at least: 5.8
 * Tested up to: 6.6.1
 * Requires PHP: 8.0
 */

defined('ABSPATH') || exit;

if ( ! defined('REAINC_WC_API_INTEGRATION_FILE')) {
    define('REAINC_WC_API_INTEGRATION_FILE', __FILE__);
}

if ( ! defined('REAINC_WC_API_INTEGRATION_URL')) {
    define('REAINC_WC_API_INTEGRATION_URL', plugin_dir_url(REAINC_WC_API_INTEGRATION_FILE));
}

if ( ! defined('REAINC_WC_API_INTEGRATION_PATH')) {
    define('REAINC_WC_API_INTEGRATION_PATH', plugin_dir_path(REAINC_WC_API_INTEGRATION_FILE));
}

/**
 * The core plugin class.
 */
class REAINC_WC_API_INTEGRATION_MAIN
{
    /**
     * Class instance.
     *
     * @var REAINC_WC_API_INTEGRATION_MAIN instance
     */
    protected static $instance = false;

    /**
     * The resulting page's hook_suffix of this plugin.
     *
     * @since    1.0.0
     * @access   protected
     * @var      string $hook The resulting page's hook_suffix.
     */
    protected $hook;

    /**
     * The unique identifier of this plugin.
     *
     * @since    1.0.0
     * @access   protected
     * @var      string $name The string used to uniquely identify this plugin.
     */
    protected $name = 'reawcproducts';

    /**
     * The current version of the plugin.
     *
     * @since    1.0.0
     * @access   protected
     * @var      string $version The current version of the plugin.
     */
    protected $version = '1.0.8';

    /**
     * The plugin options.
     *
     * @since    1.0.0
     * @access   protected
     * @var      string $options The plugin options.
     */
    protected $options;

    /**
     * Endpoint namespace.
     *
     * @var string
     */
    protected $namespace = 'wc/v3';

    /**
     * Route base.
     *
     * @var string
     */
    protected $rest_base = 'productsHtml';
		
    /**
     * Define the core functionality of the plugin.
     */
    public function __construct()
    {
        add_action( 'plugins_loaded', array( $this, 'on_plugins_loaded' ) );
        
        $this->options = empty(get_option($this->name)) ? [] : get_option($this->name);

        $this->run();
        
    }

    /**
     * Run all hooks within WordPress.
     *
     * @since    1.0.0
     */
    public function run()
    {
        /**
         * Add link to plugin settings
         */
         
        
        add_filter("plugin_action_links_" . plugin_basename(REAINC_WC_API_INTEGRATION_FILE), array($this, 'plugin_add_settings_link'));
        

        /**
         * Register plugin settings page
         */
        add_action('admin_init', array($this, 'register_plugin_settings'));

        /**
         * Admin plugin options page
         */
        add_action('admin_menu', array($this, 'add_settings_page_to_menu'), 99);

        /**
         * Admin plugin options page scripts
         */
        add_action('admin_enqueue_scripts', array($this, 'enqueue_scripts'));

        /**
         * Admin ajax action trigger cron.
         */
        add_action('wp_ajax_create_api_key', array($this, 'create_api_key'));

        /**
         * Register rest custom endpoints
         */
        add_action('rest_api_init', array($this, 'add_endpoint'), 100);

        /**
         * Check if domain that is making the request matches the one from the key.
         * Allow if matches, otherwise don't allow the request.
         *
         * @param  bool  $served  Whether the request has already been served. Default false.
         * @param  WP_HTTP_Response  $result  Result to send to the client. Usually a `WP_REST_Response`.
         * @param  WP_REST_Request  $request  Request used to generate the response.
         * @param  WP_REST_Server  $server  Server instance.
         */
        add_filter('rest_pre_serve_request', function ($served, $response, $request, $server) {

            /**
             * If not our route, move on.
             */
            if (strpos($request->get_route(), $this->namespace . '/' . $this->rest_base) === false) {
                return $served;
            }

            /**
             * Get current real origin.
             */
            $origin = get_http_origin();

            if (empty($origin)) {
                return $served;
            }

            /**
             * Remove current CORS Headers implementation.
             */
            remove_filter('rest_pre_serve_request', 'rest_send_cors_headers');

            /**
             * Requests from file:// and data: URLs send "Origin: null".
             */
            if ('null' !== $origin) {
                $origin = esc_url_raw($origin);
            }

            /*
             * Get current scheme, http or https.
             */
            $scheme = parse_url($origin, PHP_URL_SCHEME);

            /**
             * Key provided on request.
             */
            $key = $request->get_param('key');

            /**
             * Our matched domain.
             */
            $domain = null;

            /**
             * List of saved keys.
             */
            $keys = $this->get_option('keys');

            /**
             * Loop through saved keys and check if we this key saved.
             */
            foreach ($keys as $existingKey) {
                if ($key == $existingKey['key']) {
                    /**
                     * Get allowed domain from passed key.
                     */
                    $domain = $existingKey['domain'];
                }
            }

            // If domain not found, add current domain as origin.
            if (empty($domain)) {
                $domain = get_site_url();
            }

            if ($domain) {
                // Add current scheme to domain if not already added
                if (strpos($domain, 'http') === false) {
                    $domain = $scheme . '://' . $domain;
                }

                // Requests from file:// and data: URLs send "Origin: null".
                if ('null' !== $domain) {
                    $domain = esc_url_raw($domain);
                }

                header('Access-Control-Allow-Origin: ' . $domain);
                header('Access-Control-Allow-Methods: OPTIONS, GET, POST, PUT, PATCH, DELETE');
                header('Access-Control-Allow-Credentials: true');
                header('Vary: Origin', false);
            } elseif ( ! headers_sent() && 'GET' === $_SERVER['REQUEST_METHOD'] && ! is_user_logged_in()) {
                header('Vary: Origin', false);
            }

            return $served;
        }, 100, 4);
    }

    /**
     * Get option from DB.
     *
     * Gets an option from options, using defaults if necessary to prevent undefined notices.
     *
     * @param  string  $key  Option key.
     * @param  mixed  $empty_value  Value when empty.
     *
     * @return string|array The value specified for the option or a default value for the option.
     */
    private function get_option($key, $empty_value = null)
    {
        if (empty($this->options)) {
            return $empty_value;
        }

        // Get option default if unset.
        if ( ! isset($this->options[$key])) {
            return $empty_value;
        }

        if ( ! is_null($empty_value) && '' === $this->options[$key]) {
            return $empty_value;
        }

        return $this->options[$key];
    }
    
    /**
     * Request WooCommerce plugin
    */
    

    public function on_plugins_loaded() {

        function wc_admin_notice() {
            _e(
                sprintf(
                    '<div class="error"><p><strong>%1$s</strong></p></div>',
                    sprintf( 
         			    esc_html__( 'REA Products for WooCommerce requires the WooCommerce plugin to be installed and active. You can download %s here.', 'reawcproducts' ),
         			    '<a href="https://wordpress.org/plugins/woocommerce/" target="_blank">WooCommerce</a>'
     			    )
 		        )
     	    );
        }
    
		if ( ! class_exists( 'WooCommerce' ) ) {
			add_action(
				'admin_notices',
				'wc_admin_notice'
			);
			return;
		}
	}
		
    /**
     * Get class instance
     */
    public static function get_instance()
    {
        if ( ! self::$instance) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    /**
     * Show settings link on plugins list
     */
    public function plugin_add_settings_link($links)
    {
        if ( class_exists( 'WooCommerce' ) ) {
            $settings_link = '<a href='.admin_url().'admin.php?page=reawcproducts>'. __('Settings', 'reawcproducts') . '</a>';
            array_unshift($links, $settings_link);
        }
        return $links;
    }

    /**
     * Register plugin settings page
     */
    public function register_plugin_settings()
    {
        register_setting($this->name, $this->name);
    }

    /**
     * Admin plugin options page
     */
    public function add_settings_page_to_menu()
    {
        $this->hook = add_submenu_page('woocommerce', 'REA Products for WooCommerce', 'REA Products for WooCommerce',
            'manage_woocommerce', $this->name, array($this, 'admin_settings_page_content'));
    }

    /**
     * Content to show on plugin settings page
     */
    public function admin_settings_page_content()
    {
        ?>
        <div class="wrap">
            <?php
            
            if (isset($_GET['page']) && $_GET['page'] == 'reawcproducts' && isset($_GET['action']) && $_GET['action'] == 'new') { ?>
                <div class="reainc-form settings-panel">
                    <h2>Key details</h2>

                    <table id="api-keys-options" class="form-table">
                        <tbody>
                        <tr valign="top">
                            <th scope="row" class="titledesc">
                                <label for="key_domain">
                                    Domain <span class="woocommerce-help-tip" data-tip="Domain where this key will be assigned to." /></span>
                                </label>
                            </th>
                            <td class="forminp">
                                <input id="key_domain" type="text" class="input-text regular-text" value="">
                            </td>
                        </tr>
                        <tr valign="top">
                            <th scope="row" class="titledesc">
                                <label for="key_key">Key<span class="woocommerce-help-tip" data-tip="Key that will be assigned."></span>
                                </label>
                            </th>
                            <td class="forminp">
                                <input id="key_key" type="text" class="input-text regular-text" value="">
                                <div style="margin-top: 15px;">
                                    <input id="key_generate" type="checkbox" class="checkbox" value="" checked="checked" />
                                    <label for="key_generate">
                                        Generate a random key<span class="woocommerce-help-tip" data-tip="Generate a random key."></span>
                                    </label>
                                </div>
                            </td>
                        </tr>
                        <tr class="form_tr_invisible" valign="top">
                            <th scope="row" class="titledesc">
                                <label for="key_active">
                                    Active
                                </label>
                            </th>
                            <td class="forminp">
                                <input id="key_active" type="checkbox" class="checkbox" value="" checked="checked" />
                                
                            </td>
                        </tr>
                        <tr class="form_tr_invisible" valign="top">
                            <th scope="row" class="titledesc">
                                <label for="key_enabled">
                                    Create Date
                                </label>
                            </th>
                            <td class="forminp">
                                <input id="key_enabled" type="checkbox" class="checkbox" value="" checked="checked" />
                            </td>
                        </tr>
                        </tbody>
                    </table>
                    <div class="trigger-add">
                        <span class="button-holder">
                            <input type="submit" name="create_api_key" id="create_api_key" class="button button-primary" value="Generate API key">
                        </span>
                    </div>
                    <div class="messages">
                        <p class="attr_status"></p>
                    </div>
                    <?php wp_nonce_field('reainc-create-key', 'security'); ?>
                </div>
            <?php } else { ?>
                <form method="post" action="options.php" class="reainc-form">
                    <div class="reainc-from-header">
                        <div class="reainc-from-header-key">
                            <h2><?php
                                _e('REA Products for WooCommerce', 'reawcproducts'); ?>
                                <a href="?page=reawcproducts&action=new" id="add-new-key" class="add-new-h2">Add key</a>
                            </h2>
                        </div>
                        <div class="reainc-from-header-version">
                            <?php
                                $plugin_data = get_plugin_data( __FILE__ );
                            ?>
                            <p>Version <?php esc_html_e($plugin_data['Version']);?></p>
                            
                        </div>
                        <div class="reainc-from-header-link">
                            <p><a target="_blank" href="https://reainc.net/rea-wc-products-plugin/">Visit plugin site</a></p>
                        </div>
                    </div>
                    <?php
                    if (isset($_GET['key-saved']) && ! empty($_GET['key-saved'])) {
                        add_settings_error($this->name, $this->name,
                            __('API key saved successfully.', 'reawcproducts'),
                            'success');
                    }
                    settings_errors();
                    settings_fields($this->name); 
                    
                    //Created Date/Time
                    $timezone_string = wp_timezone_string();
                    $timezone = new DateTimeZone( $timezone_string );
                    $current_time = wp_date("Y-m-d H:i:s", null, $timezone );
                    
                    ?>

                    <table class="widefat fixed striped table-view-list keys">
                        <thead>
                        <tr>
                            <th scope="col" id="key_active" class="manage-column">Active</th>
                            <th scope="col" id="domain" class="manage-column column-domain column-primary">Domain</th>
                            <th scope="col" id="truncated_key" class="manage-column column-truncated_key">Key</th>
                            <th scope="col" id="enabled_date" class="manage-column">Enabled Date/Time</th>
                            <th scope="col" id="disabled_date" class="manage-column">Last Disabled Date/Time</th>
                        </tr>
                        </thead>
                        <tbody id="the-list" data-wp-lists="list:key">
                        <?php
                        foreach ($this->get_option('keys', []) as $index => $key) { ?>
                            <tr>
                                <td class="key_active_col" data-colname="key_active">
                                    <?php if($key['key_active'] == 'active'): ?>
                                        <input type="checkbox" name="reawcproducts[keys][<?php esc_html_e($index); ?>][key_active]" value="<?php esc_html_e($key['key_active']); ?>" checked="checked";>
                                    <?php else: ?>
                                        <input type="checkbox" name="reawcproducts[keys][<?php esc_html_e($index); ?>][key_active]" value="active">
                                    <?php endif; ?>
                                </td>
                                <td class="title column-domain has-row-actions column-primary" data-colname="Domain">
                                    <input type="hidden" name="reawcproducts[keys][<?php esc_html_e($index); ?>][domain]" value="<?php esc_html_e($key['domain']); ?>">
                                    <strong><?php esc_html_e($key['domain']); ?></strong>
                                    <div class="row-actions">
                                            <span class="trash">
                                                <a class="submitdelete" aria-label="Revoke Key" href="#">
                                                    Revoke
                                                </a>
                                            </span>
                                    </div>
                                </td>
                                <td class="truncated_key column-truncated_key" data-colname="Key">
                                    <input type="hidden" name="reawcproducts[keys][<?php esc_html_e($index); ?>][key]" value="<?php esc_html_e($key['key']); ?>">
                                    <input type="text" class="key_field_input" value="<?php esc_html_e($key['key']); ?>" readonly>
                                </td>
                                <td class="" data-colname="key_enabled">
                                    <?php if($key['key_active'] == 'active'): ?>
                                        <input type="hidden" name="reawcproducts[keys][<?php esc_html_e($index); ?>][key_enabled]" value="<?php esc_html_e($key['key_enabled']);?>" />
                                    <?php else: ?>
                                        <input type="hidden" name="reawcproducts[keys][<?php esc_html_e($index); ?>][key_enabled]" value="<?php esc_html_e($current_time);?>" />
                                    <?php endif; ?>
                                    <?php esc_html_e($key['key_enabled']);?>
                                </td>
                                <td class="" data-colname="key_disabled">
                                    <?php if($key['key_active'] == 'active'): ?>
                                        <input type="hidden" name="reawcproducts[keys][<?php esc_html_e($index); ?>][key_disabled]" value="<?php esc_html_e($current_time);?>" />
                                    <?php else: ?>
                                        <input type="hidden" name="reawcproducts[keys][<?php esc_html_e($index); ?>][key_disabled]" value="<?php esc_html_e($key['key_disabled']);?>" />
                                    <?php endif; ?>
                                    
                                    <?php esc_html_e($key['key_disabled']);?>
                                </td>
                            </tr>
                        <?php } ?>
                        </tbody>
                        <!--<tfoot>
                        <tr>
                            <th scope="col" class="manage-column">Active</th>
                            <th scope="col" class="manage-column column-title column-primary">Domain</th>
                            <th scope="col" class="manage-column column-truncated_key">Key</th>
                            <th scope="col" id="enabled_date" class="manage-column">Enabled Date/Time</th>
                            <th scope="col" id="disabled_date" class="manage-column">Last Disabled Date/Time</th>
                        </tr>
                        </tfoot>-->
                    </table>
                    <?php

                    wp_nonce_field('reainc-keys', 'security');

                    submit_button(); ?>

                </form>
            <?php } ?>
        </div>
        <?php
    }

    /**
     * Enqueues the plugin options scripts.
     */
    public function enqueue_scripts()
    {
        // check if we're on correct screen
        $screen = get_current_screen();
        if ($this->hook !== $screen->id) {
            return;
        }
        // enqueue plugin styles
        wp_enqueue_style($this->name, REAINC_WC_API_INTEGRATION_URL . 'assets/admin-style.css', '', $this->version,
            'all');

        // register script
        wp_register_script($this->name, REAINC_WC_API_INTEGRATION_URL . 'assets/index.js', 'jquery', $this->version,
            true);
        // add data available to js
        wp_localize_script($this->name, 'reainc', [
            'ajaxurl'      => admin_url('admin-ajax.php'),
            'redirect_url' => menu_page_url($this->name, false),
        ]);
        // enqueue script
        wp_enqueue_script($this->name);
    }

    /**
     * Ajax action run when triggering cronjob
     */
    public function create_api_key()
    {
        check_ajax_referer('reainc-create-key', 'security');

        //Created Date/Time
        $timezone_string = wp_timezone_string();
        $timezone = new DateTimeZone( $timezone_string );
        $current_time = wp_date("Y-m-d H:i:s", null, $timezone );
        
        $key      = isset($_POST['key']) ? sanitize_text_field($_POST['key']) : '';
        $generate = isset($_POST['generate']) && wp_validate_boolean($_POST['generate']);
        $domain   = isset($_POST['domain']) ? sanitize_text_field($_POST['domain']) : '';
        $key_active   = isset($_POST['key_active']) && $_POST['key_active'] == true ? 'active' : '';
        $key_enabled   = isset($_POST['key_enabled']) && $_POST['key_enabled'] == true  ? $current_time : '';

        // If a key not generated, generate a unique one.
        if ($generate) {
            $key = wp_generate_password(21, false, false);
        }
        $errors = [];
        
        
        $key_detail = get_option('reawcproducts')['keys'];
        $domain_list = [];    
        foreach($key_detail as $detail){
            foreach($detail as $k => $v){
                if($k=='domain'){
                    array_push($domain_list, $v);
                }
            }
        }
        
        if (empty($domain)) {
            $errors[] = __('Domain is required.', 'reawcproducts');
        }
        
        if (in_array($domain, $domain_list)){
            $errors[] = __('Domain already exists', 'reawcproducts');
        }
        
        if (empty($key)) {
            $errors[] = __('Key is required.', 'reawcproducts');
        }

        
        if ( ! empty($errors)) {
            wp_send_json_error([
                'message' => $errors,
            ]);
        }

        // check if this combination exists
        $keys = $this->get_option('keys');

        foreach ($keys as $existingKey) {
            if ($key == $existingKey['key']) {
                wp_send_json_error([
                    'message' => __('Key already used.', 'reawcproducts')
                ]);
            }
        }

        if ( ! isset($this->options['keys'])) {
            $this->options['keys'] = [];
        }

        $this->options['keys'][] = [
            'key'    => $key,
            'domain' => $domain,
            'key_active' => $key_active,
            'key_enabled' => $key_enabled
            
        ];

        update_option($this->name, $this->options);

        wp_send_json_success([
            'message' => __('Key added successfully.', 'reawcproducts'),
        ]);
    }
    
    public function current_time(){
        //Created Date/Time
        $timezone_string = wp_timezone_string();
        $timezone = new DateTimeZone( $timezone_string );
        $current_time = wp_date("Y-m-d H:i:s", null, $timezone );
        
        esc_html_e($current_time);
    }

    public function add_endpoint()
    {
        register_rest_route($this->namespace, '/' . $this->rest_base, [
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => [$this, 'getProducts'],
            'permission_callback' => array($this, 'check_permissions'),
        ]);
    }

    /**
     * Check if the request should be allowed.
     *
     * @param  WP_REST_Request  $request
     *
     * @return bool
     */
    public function check_permissions(WP_REST_Request $request)
    {
        /**
         * Get current real origin.
         */
        $origin = get_http_origin();
        
        if (empty($origin)) {
            return false;
        }

        // Key provided on request.
        $key = $request->get_param('key');
        // list of saved keys.
        $keys = $this->get_option('keys');

        // loop through saved keys and check if key is active
        foreach ($keys as $existingKey) {
            if ($key == $existingKey['key'] && (isset($existingKey['key_active']) && $existingKey['key_active'] === 'active')) {
                return true;
            }
        }

        // nothing found, don't allow the request
        return false;
    }

    public function getProducts(WP_REST_Request $request)
    {
        $product_id  = $request->get_param('product_id');
        $category_id = $request->get_param('category_id');
        $tag_id      = $request->get_param('tag_id');

        $args = array(
            'post_type'      => 'product',
            'post_status'    => 'publish',
            'posts_per_page' => '-1',
            'tax_query'      => [],
        );

        if ( ! empty($product_id)) {
            // get an array of product_id separated by coma
            $product_id = explode(',', $product_id);

            // remove empty spaces
            $product_id = array_map('trim', $product_id);

            // remove empty elements
            $product_id = array_filter($product_id);

            $args['post__in'] = is_array($product_id) ? $product_id : [$product_id];
        }

        if ( ! empty($category_id)) {

            // get an array of category_id separated by coma
            $category_id = explode(',', $category_id);

            // remove empty spaces
            $category_id = array_map('trim', $category_id);

            // remove empty elements
            $category_id = array_filter($category_id);

            $args['tax_query'][] = [
                'taxonomy' => 'product_cat',
                'field'    => 'term_id',
                'terms'    => $category_id,
                'operator' => 'IN',
            ];
        }

        if ( ! empty($tag_id)) {
            // get an array of tag_id separated by coma
            $tag_id = explode(',', $tag_id);

            // remove empty spaces
            $tag_id = array_map('trim', $tag_id);

            // remove empty elements
            $tag_id = array_filter($tag_id);

            $args['tax_query'][] = [
                'taxonomy' => 'product_tag',
                'field'    => 'term_id',
                'terms'    => $tag_id,
                'operator' => 'IN',
            ];
        }

        $query = new WP_Query($args);

        $columns  = wc_get_default_products_per_row();
        $classes  = ['woocommerce-html-products-list'];
        $products = $query->posts;

        ob_start();
        if ($products) {

            WC()->frontend_includes();
            $style = file_get_contents(REAINC_WC_API_INTEGRATION_PATH . 'assets/frontend-style.css');

            ?>
            <style>
                <?php
                   if ($style) {
                       esc_html_e($style);
                   }
                ?>
            </style>
            <?php

            // Setup the loop.
            wc_setup_loop(
                array(
                    'columns'      => $columns,
                    'name'         => 'products',
                    'is_shortcode' => true,
                    'is_search'    => false,
                    'is_paginated' => false,
                    'total'        => $query->found_posts,
                    'per_page'     => -1,
                )
            );

            $original_post = $GLOBALS['post'];

            woocommerce_product_loop_start();

            if (wc_get_loop_prop('total')) {
                foreach ($products as $productN) {
                    $GLOBALS['post'] = get_post($productN); // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited

                    setup_postdata($GLOBALS['post']);

                    // Render product template.
                    wc_get_template_part('content', 'product');
                }
            }

            $GLOBALS['post'] = $original_post; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
            woocommerce_product_loop_end();

            wp_reset_postdata();
            wc_reset_loop();

            return '<div class="' . esc_attr(implode(' ', $classes)) . '">' . ob_get_clean() . '</div>';

        } else {
            ob_start();
            wc_get_template('loop/no-products-found.php');

            return ob_get_clean();
        }

        // use this to return json
        return new WP_REST_Response([
            'content' => '<div class="' . esc_attr(implode(' ', $classes)) . '">' . ob_get_clean() . '</div>',
        ]);
    }

}

REAINC_WC_API_INTEGRATION_MAIN::get_instance();
