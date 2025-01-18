<?php
require 'vendor/autoload.php';
require_once 'Auth/JwtManager.php';
use HeadlessWoo\Auth\JWTManager\JwtManager;
$secretKey = 'Testing Secret Key';
// Create an instance of JwtManager
$jwtManager = new JwtManager($secretKey);

class AuthClass
{
    public function __construct(){
    add_action('rest_api_init', array($this, 'registerAuthRoutes'));
    }
    public function registerAuthRoutes(){
        register_rest_route('headlesswoo/v1', '/login', array(
            'methods' => 'POST',
            'callback' => array($this, 'login')
        ));
        register_rest_route('headlesswoo/v1', '/logout', array(
            'methods' => 'POST',
            'callback' => array($this, 'logout')
        ));
        register_rest_route('headlesswoo/v1', '/register', array(
            'methods' => 'POST',
            'callback' => array($this, 'register')
        ));
    }

    public function register($request){
        if ($request instanceof WP_REST_Request) {
            $data= $request->get_params();
            if (!empty($data)){
                var_dump($data);
            }
        }
    }

}
