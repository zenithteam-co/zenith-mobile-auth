<?php
/**
 * Plugin Name: Zenith Mobile Auth (OTP)
 * Plugin URI:  https://zenithteam.co
 * Description: Replaces WooCommerce login/register forms with a mobile-only OTP system. Supports IPPanel, Styling, and Rate Limiting.
 * Version:     1.0.1
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
define( 'ZMA_VERSION', '1.0.1' );

// Include Classes
require_once ZMA_PATH . 'includes/class-zma-gateway.php';
require_once ZMA_PATH . 'includes/class-zma-ajax.php';
require_once ZMA_PATH . 'includes/class-zma-public.php';

if ( is_admin() ) {
    require_once ZMA_PATH . 'includes/class-zma-admin.php';
}

// Updater Logic
require_once ZMA_PATH . 'includes/class-zma-updater.php';

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
        // Init Public
        new Zenith_Mobile_Auth_Public();
        
        // Init Ajax
        new Zenith_Mobile_Auth_Ajax();

        // Init Admin
        if ( is_admin() ) {
            new Zenith_Mobile_Auth_Admin();
            
            // Init Updater
            // REPLACE 'your-github-username' and 'your-repo-name' below
            new Zenith_Mobile_Auth_Updater( __FILE__, 'zenithteam-co', 'zenith-mobile-auth' );
        }
    }
}

// Initialize
Zenith_Mobile_Auth::get_instance();
