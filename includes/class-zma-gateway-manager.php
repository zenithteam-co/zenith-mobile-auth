<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class ZMA_Gateway_Manager {

    private static $gateways = [];

    public static function init() {
        // Hook to register gateways
        add_action( 'init', [ __CLASS__, 'load_gateways' ] );
    }

    public static function load_gateways() {
        // Register Default
        self::register( new ZMA_Gateway_IPPanel() );

        // Allow other plugins to register
        do_action( 'zma_register_gateways' );
    }

    public static function register( ZMA_Gateway $gateway ) {
        self::$gateways[ $gateway->get_id() ] = $gateway;
    }

    public static function get_all() {
        return self::$gateways;
    }

    public static function get_active_gateway() {
        $settings = get_option( 'zma_settings' );
        $active_id = isset( $settings['active_gateway'] ) ? $settings['active_gateway'] : 'ippanel';
        
        if ( isset( self::$gateways[ $active_id ] ) ) {
            return self::$gateways[ $active_id ];
        }

        // Fallback to first available or return null
        return !empty(self::$gateways) ? reset(self::$gateways) : null;
    }
}