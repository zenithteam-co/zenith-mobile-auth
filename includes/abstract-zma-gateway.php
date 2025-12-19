<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Abstract Class for SMS Gateways
 * Developers should extend this class to add new providers.
 */
abstract class ZMA_Gateway {

    protected $id;
    protected $name;
    protected $settings;

    public function __construct() {
        $this->settings = get_option( 'zma_settings' );
    }

    /**
     * Get Gateway ID (e.g., 'ippanel')
     */
    public function get_id() {
        return $this->id;
    }

    /**
     * Get Gateway Name (e.g., 'IPPanel Edge')
     */
    public function get_name() {
        return $this->name;
    }

    /**
     * Send OTP Pattern
     * @param string $phone Sanitized phone number
     * @param string $otp The OTP code
     * @return bool|string True on success, Error message string on failure
     */
    abstract public function send_pattern( $phone, $otp );
}