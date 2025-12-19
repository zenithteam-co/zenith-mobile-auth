<?php
/**
 * Plugin Name: Zenith Mobile Auth (OTP)
 * Plugin URI:  https://zenithteam.co
 * Description: Replaces WooCommerce login/register forms with a mobile-only OTP system. Supports Multiple Gateways (IPPanel default).
 * Version:     2.2.1
 * Author:      Mahdi Soltani
 * Author URI:  https://zenithteam.co/mahdi-soltani
 * License:     GPL2
 * Text Domain: zenith-mobile-auth
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Define Constants
define( 'ZMA_PATH', plugin_dir_path( __FILE__ ) );
define( 'ZMA_URL', plugin_dir_url( __FILE__ ) );
define( 'ZMA_VERSION', '2.2.1' );

// 1. Load Abstract Base Class
require_once ZMA_PATH . 'includes/abstract-zma-gateway.php';

// 2. Load Gateway Manager
require_once ZMA_PATH . 'includes/class-zma-gateway-manager.php';

// 3. Load Ajax & Public
require_once ZMA_PATH . 'includes/class-zma-ajax.php';
require_once ZMA_PATH . 'includes/class-zma-public.php';

// 4. Load Built-in Gateways (Ensure this file exists in includes/gateways/)
if ( file_exists( ZMA_PATH . 'includes/gateways/class-zma-gateway-ippanel.php' ) ) {
    require_once ZMA_PATH . 'includes/gateways/class-zma-gateway-ippanel.php';
}

if ( is_admin() ) {
    require_once ZMA_PATH . 'includes/class-zma-admin.php';
}

// Updater Logic
if ( file_exists( ZMA_PATH . 'includes/class-zma-updater.php' ) ) {
    require_once ZMA_PATH . 'includes/class-zma-updater.php';
}

/**
 * Main Class
 */
class Zenith_Mobile_Auth {
    private static $instance = null;

    public static function get_instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        // Init Gateway Manager
        ZMA_Gateway_Manager::init();

        // Init Public
        new Zenith_Mobile_Auth_Public();
        
        // Init Ajax
        new Zenith_Mobile_Auth_Ajax();

        // Init Admin
        if ( is_admin() ) {
            new Zenith_Mobile_Auth_Admin();
            
            // Init Updater
            if ( class_exists( 'Zenith_Mobile_Auth_Updater' ) ) {
                new Zenith_Mobile_Auth_Updater( __FILE__, 'ZenithTeam', 'zenith-mobile-auth' );
            }
        }
    }
}

// Initialize
Zenith_Mobile_Auth::get_instance();