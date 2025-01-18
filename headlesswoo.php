<?php
/*
Plugin Name: Headless Woo for Flutter APP
Description: This Plugin Provides Proper Authentication Order Management and Cart creation and product listing and categorization.
Version: 1.0
Author: Amit Batham
Author URI: https://profiles.wordpress.org/amitbatham
License: APACHE2
*/

if (!defined('ABSPATH')) {
    exit;
}

require_once plugin_dir_path(__FILE__).'./vendor/autoload.php';
require_once plugin_dir_path(__FILE__).'./Auth/JwtManager.php';
require_once plugin_dir_path(__FILE__).'/Admin/headlesswoo_admin.php';

class HeadlessWoo {
    private string $secretKey;
    private \HeadlessWoo\Auth\JWTManager\JwtManager $auth_obj;
    private string $base_url;
    private $woo_api_key;
    private $woo_api_secret;

    public function __construct() {
        $this->init();
        $this->registerHooks();
    }

    private function init() {
        $options = get_option('headlesswoo_options');
        $this->secretKey = $options['headlesswoo_field_jwt_secret'] ?? 'default_secret';
        $this->woo_api_key = $options['headlesswoo_field_api_key'] ?? '';
        $this->woo_api_secret = $options['headlesswoo_field_api_key_secret'] ?? '';
        $this->auth_obj = new HeadlessWoo\Auth\JWTManager\JwtManager($this->secretKey);
        $this->base_url = get_home_url();
    }

    private function registerHooks() {
        add_action('rest_api_init', array($this, 'registerRoutes'));
        add_action('woocommerce_init', array($this, 'checkWooCommerceActive'));
    }

    public function registerRoutes() {
        $routes = array(
            'login' => array('POST', 'handleLogin'),
            'logout' => array('POST', 'handleLogout'),
            'register' => array('POST', 'handleRegister'),
            'verify' => array('POST', 'verifyToken'),
            'products' => array('GET', 'getProducts'),
            'product/(?P<id>\d+)' => array('GET', 'getProduct'),
            'categories' => array('GET', 'getCategories'),
            'cart' => array('GET', 'getCart'),
            'cart/add' => array('POST', 'addToCart'),
            'cart/remove' => array('POST', 'removeFromCart'),
            'orders' => array('GET', 'getOrders'),
            'order/create' => array('POST', 'createOrder')
        );

        foreach ($routes as $endpoint => $config) {
            register_rest_route('headlesswoo/v1', '/' . $endpoint, array(
                'methods' => $config[0],
                'callback' => array($this, $config[1]),
                'permission_callback' => '__return_true'
            ));
        }
    }

    private function validateRequest($request) {
        $authHeader = $request->get_header('Authorization');
        if (empty($authHeader)) {
            return new WP_Error('auth_required', 'Authorization header is required', array('status' => 401));
        }

        $token = str_replace('Bearer ', '', $authHeader);
        if (!$this->auth_obj->validateToken($token)) {
            return new WP_Error('invalid_token', 'Invalid or expired token', array('status' => 401));
        }

        return true;
    }

    private function makeWooRequest($endpoint, $method = 'GET', $body = null) {
        $args = array(
            'method' => $method,
            'headers' => array(
                'Authorization' => 'Basic ' . base64_encode($this->woo_api_key . ':' . $this->woo_api_secret)
            )
        );

        if ($body && in_array($method, array('POST', 'PUT'))) {
            $args['body'] = json_encode($body);
            $args['headers']['Content-Type'] = 'application/json';
        }

        $response = wp_remote_request(
            $this->base_url . '/wp-json/wc/v3/' . $endpoint,
            $args
        );

        if (is_wp_error($response)) {
            return new WP_Error('api_error', $response->get_error_message(), array('status' => 500));
        }

        $response_code = wp_remote_retrieve_response_code($response);
        if ($response_code !== 200) {
            return new WP_Error('api_error', 'WooCommerce API request failed', array('status' => $response_code));
        }

        return json_decode(wp_remote_retrieve_body($response), true);
    }

    public function handleLogin($request) {
        $data = $request->get_json_params();

        if (empty($data['username']) || empty($data['password'])) {
            return new WP_Error('missing_fields', 'Username and password are required', array('status' => 400));
        }

        $user = wp_authenticate($data['username'], $data['password']);
        if (is_wp_error($user)) {
            return new WP_Error('login_failed', 'Invalid credentials', array('status' => 401));
        }

        $payload = array(
            'user_id' => $user->ID,
            'username' => $user->user_login,
            'exp' => time() + 3600
        );

        $token = $this->auth_obj->generateToken(json_encode($payload));
        update_user_meta($user->ID, '_headlesswoo_jwt_token', $token);

        return new WP_REST_Response(array(
            'token' => $token,
            'user' => array(
                'id' => $user->ID,
                'username' => $user->user_login,
                'email' => $user->user_email
            )
        ), 200);
    }

    public function handleLogout($request) {
        $validation = $this->validateRequest($request);
        if (is_wp_error($validation)) return $validation;

        $token = str_replace('Bearer ', '', $request->get_header('Authorization'));
        $payload = $this->auth_obj->decodeToken($token);

        if ($payload && isset($payload['user_id'])) {
            delete_user_meta($payload['user_id'], '_headlesswoo_jwt_token');
        }

        return new WP_REST_Response(array('message' => 'Logged out successfully'), 200);
    }

    public function handleRegister($request) {
        $data = $request->get_json_params();

        if (empty($data['username']) || empty($data['password']) || empty($data['email'])) {
            return new WP_Error('missing_fields', 'Required fields are missing', array('status' => 400));
        }

        $user_id = wp_create_user(
            sanitize_user($data['username']),
            $data['password'],
            sanitize_email($data['email'])
        );

        if (is_wp_error($user_id)) {
            return new WP_Error('registration_failed', $user_id->get_error_message(), array('status' => 400));
        }

        return new WP_REST_Response(array('message' => 'Registration successful'), 201);
    }

    public function getProducts($request) {
        $validation = $this->validateRequest($request);
        if (is_wp_error($validation)) return $validation;

        $params = $request->get_params();
        $endpoint = 'products?' . http_build_query($params);

        return new WP_REST_Response($this->makeWooRequest($endpoint), 200);
    }

    public function getProduct($request) {
        $validation = $this->validateRequest($request);
        if (is_wp_error($validation)) return $validation;

        $product_id = $request->get_param('id');
        return new WP_REST_Response($this->makeWooRequest("products/{$product_id}"), 200);
    }

    public function getCategories($request) {
        $validation = $this->validateRequest($request);
        if (is_wp_error($validation)) return $validation;

        return new WP_REST_Response($this->makeWooRequest('products/categories'), 200);
    }

    public function getCart($request) {
        $validation = $this->validateRequest($request);
        if (is_wp_error($validation)) return $validation;

        $token = str_replace('Bearer ', '', $request->get_header('Authorization'));
        $payload = $this->auth_obj->decodeToken($token);
        $cart = get_user_meta($payload['user_id'], '_headlesswoo_cart', true) ?: array();

        return new WP_REST_Response($cart, 200);
    }

    public function addToCart($request) {
        $validation = $this->validateRequest($request);
        if (is_wp_error($validation)) return $validation;

        $data = $request->get_json_params();
        if (empty($data['product_id']) || empty($data['quantity'])) {
            return new WP_Error('missing_fields', 'Product ID and quantity are required', array('status' => 400));
        }

        $token = str_replace('Bearer ', '', $request->get_header('Authorization'));
        $payload = $this->auth_obj->decodeToken($token);
        $cart = get_user_meta($payload['user_id'], '_headlesswoo_cart', true) ?: array();

        $cart[$data['product_id']] = array(
            'quantity' => $data['quantity'],
            'added_at' => current_time('mysql')
        );

        update_user_meta($payload['user_id'], '_headlesswoo_cart', $cart);
        return new WP_REST_Response($cart, 200);
    }

    public function createOrder($request) {
        $validation = $this->validateRequest($request);
        if (is_wp_error($validation)) return $validation;

        $data = $request->get_json_params();
        return new WP_REST_Response($this->makeWooRequest('orders', 'POST', $data), 201);
    }

    public function getOrders($request) {
        $validation = $this->validateRequest($request);
        if (is_wp_error($validation)) return $validation;

        $token = str_replace('Bearer ', '', $request->get_header('Authorization'));
        $payload = $this->auth_obj->decodeToken($token);

        $params = array('customer' => $payload['user_id']);
        $endpoint = 'orders?' . http_build_query($params);

        return new WP_REST_Response($this->makeWooRequest($endpoint), 200);
    }

    public function checkWooCommerceActive() {
        if (!class_exists('WooCommerce')) {
            add_action('admin_notices', function() {
                echo '<div class="error"><p>HeadlessWoo requires WooCommerce to be installed and active.</p></div>';
            });
        }
    }
}

new HeadlessWoo();
