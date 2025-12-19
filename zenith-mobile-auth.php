<?php
/**
 * Plugin Name: Zenith Mobile Auth (OTP)
 * Plugin URI:  https://zenithteam.co
 * Description: Replaces WooCommerce login/register forms with a mobile-only OTP system. Supports Multiple Gateways (IPPanel default).
 * Version:     1.0.0
 * Author:      Mahdi Soltani
 * Author URI:  https://zenithteam.co/mahdi-soltani
 * License:     GPL2
 * Text Domain: zenith-mobile-auth
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

define( 'ZMA_PATH', plugin_dir_path( __FILE__ ) );
define( 'ZMA_URL', plugin_dir_url( __FILE__ ) );
define( 'ZMA_VERSION', '1.0.0' );

// 1. Load Abstract & Manager First
require_once ZMA_PATH . 'includes/abstract-zma-gateway.php';
require_once ZMA_PATH . 'includes/class-zma-gateway-manager.php';

// 2. Load Core
require_once ZMA_PATH . 'includes/class-zma-ajax.php';
require_once ZMA_PATH . 'includes/class-zma-public.php';

// 3. Load Gateways (Force load built-ins so they are available for Manager init)
foreach ( glob( ZMA_PATH . 'includes/gateways/*.php' ) as $filename ) {
    require_once $filename;
}

if ( is_admin() ) {
    require_once ZMA_PATH . 'includes/class-zma-admin.php';
}

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
        // Initialize Gateway Manager immediately
        ZMA_Gateway_Manager::init();

        new Zenith_Mobile_Auth_Public();
        new Zenith_Mobile_Auth_Ajax();

        if ( is_admin() ) {
            new Zenith_Mobile_Auth_Admin();
            if ( class_exists( 'Zenith_Mobile_Auth_Updater' ) ) {
                new Zenith_Mobile_Auth_Updater( __FILE__, 'ZenithTeam', 'zenith-mobile-auth' );
            }
        }
    }
}

Zenith_Mobile_Auth::get_instance();