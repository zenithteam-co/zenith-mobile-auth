<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Abstract Class for SMS Gateways
 */
abstract class ZMA_Gateway {

    protected $id;
    protected $name;
    protected $settings;

    public function __construct() {
        $this->settings = get_option( 'zma_settings' );
    }

    public function get_id() {
        return $this->id;
    }

    public function get_name() {
        return $this->name;
    }

    abstract public function send_pattern( $phone, $otp );
}