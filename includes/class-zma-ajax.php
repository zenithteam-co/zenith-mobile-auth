<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class Zenith_Mobile_Auth_Ajax {

    public function __construct() {
        add_action( 'wp_ajax_nopriv_zma_send_otp', [ $this, 'send_otp' ] );
        add_action( 'wp_ajax_zma_send_otp', [ $this, 'send_otp' ] );
        add_action( 'wp_ajax_nopriv_zma_verify_otp', [ $this, 'verify_otp' ] );
        add_action( 'wp_ajax_zma_verify_otp', [ $this, 'verify_otp' ] );
        add_action( 'wp_ajax_zma_test_sms', [ $this, 'test_sms' ] );
        
        add_action( 'wp_ajax_zma_reset_user_limits', [ $this, 'reset_user_limits' ] );
        add_action( 'wp_ajax_zma_update_user_info', [ $this, 'update_user_info' ] );
    }

    private function normalize_phone( $phone ) {
        $persian = ['۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹'];
        $arabic  = ['٠', '١', '٢', '٣', '٤', '٥', '٦', '٧', '٨', '٩'];
        $english = range( 0, 9 );
        $phone = str_replace( $persian, $english, $phone );
        $phone = str_replace( $arabic, $english, $phone );
        $phone = preg_replace( '/\D/', '', $phone );
        if ( substr( $phone, 0, 2 ) === '98' ) $phone = '0' . substr( $phone, 2 );
        if ( strlen( $phone ) === 10 && substr( $phone, 0, 1 ) === '9' ) $phone = '0' . $phone;
        if ( ! preg_match( '/^09[0-9]{9}$/', $phone ) ) return false;
        return $phone;
    }

    private function check_rate_limit( $phone ) {
        $settings = get_option('zma_settings');
        $limit = isset($settings['daily_limit']) ? (int)$settings['daily_limit'] : 10;
        $key = 'zma_limit_' . md5($phone . '_' . date('Y-m-d'));
        $count = get_transient($key);
        if ( $count === false ) $count = 0;
        if ( $count >= $limit ) return false;
        return $count; 
    }

    private function increment_rate_limit( $phone, $current_count ) {
        $key = 'zma_limit_' . md5($phone . '_' . date('Y-m-d'));
        set_transient($key, $current_count + 1, 24 * HOUR_IN_SECONDS);
    }

    public function send_otp() {
        check_ajax_referer( 'zma_auth_nonce', 'security' );
        $phone = $this->normalize_phone( $_POST['phone'] ?? '' );
        if ( ! $phone ) wp_send_json_error( [ 'message' => __('Invalid Phone', 'zenith-mobile-auth') ] );

        $current_count = $this->check_rate_limit($phone);
        if ( $current_count === false ) wp_send_json_error( [ 'message' => __('Daily SMS limit reached. Try tomorrow.', 'zenith-mobile-auth') ] );

        $phone_hash = md5($phone);
        if ( get_transient( 'zma_wait_' . $phone_hash ) ) {
            $settings = get_option('zma_settings');
            $wait = isset($settings['resend_time']) ? (int)$settings['resend_time'] : 120;
            wp_send_json_error( [ 'message' => sprintf( __('Please wait %d seconds before requesting a new code.', 'zenith-mobile-auth'), $wait ) ] );
        }

        $settings = get_option('zma_settings');
        $len = isset($settings['otp_length']) ? (int)$settings['otp_length'] : 4;
        $min = pow(10, $len - 1);
        $max = pow(10, $len) - 1;
        $otp = rand( $min, $max );
        $session_token = wp_generate_password( 32, false );

        set_transient( 'zma_otp_' . $phone_hash, ['otp' => $otp, 'token' => $session_token], 120 );
        delete_transient( 'zma_otp_attempts_' . $phone_hash );

        $gateway = new Zenith_Mobile_Auth_Gateway_IPPanel();
        $sent = $gateway->send_pattern( $phone, $otp );

        if ( $sent === true ) {
            $this->increment_rate_limit($phone, $current_count);
            $wait_time = isset($settings['resend_time']) ? (int)$settings['resend_time'] : 120;
            set_transient( 'zma_wait_' . $phone_hash, 1, $wait_time );
            wp_send_json_success( [ 'message' => __('Code Sent', 'zenith-mobile-auth'), 'phone' => $phone, 'token' => $session_token ] );
        } else {
            wp_send_json_error( [ 'message' => $sent ] );
        }
    }

    public function verify_otp() {
        check_ajax_referer( 'zma_auth_nonce', 'security' );
        
        $phone = $this->normalize_phone( $_POST['phone'] ?? '' );
        $otp_input = $_POST['otp'] ?? '';
        $client_token = $_POST['token'] ?? '';

        $persian = ['۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹'];
        $arabic  = ['٠', '١', '٢', '٣', '٤', '٥', '٦', '٧', '٨', '٩'];
        $english = range( 0, 9 );
        $otp_input = str_replace( $persian, $english, $otp_input );
        $otp_input = str_replace( $arabic, $english, $otp_input );

        if ( ! $phone ) wp_send_json_error( [ 'message' => __('Invalid Phone', 'zenith-mobile-auth') ] );

        $phone_hash = md5( $phone );
        $stored_data = get_transient( 'zma_otp_' . $phone_hash );

        if ( ! $stored_data ) wp_send_json_error( [ 'message' => __('Code expired. Please request a new code.', 'zenith-mobile-auth') ] );
        if ( ! is_array($stored_data) || ! isset($stored_data['token']) || $stored_data['token'] !== $client_token ) wp_send_json_error( [ 'message' => __('Session mismatch. Please request a new code.', 'zenith-mobile-auth') ] );

        $stored_otp = $stored_data['otp'];
        $settings = get_option('zma_settings');
        $max_attempts = isset($settings['max_attempts']) ? (int)$settings['max_attempts'] : 3;
        $attempts_key = 'zma_otp_attempts_' . $phone_hash;
        $attempts = get_transient($attempts_key) ?: 0;

        if ( $attempts >= $max_attempts ) {
            delete_transient( 'zma_otp_' . $phone_hash );
            delete_transient( $attempts_key );
            wp_send_json_error( [ 'message' => __('Too many failed attempts. Code invalidated.', 'zenith-mobile-auth') ] );
        }

        if ( $stored_otp != $otp_input ) {
            $attempts++;
            set_transient( $attempts_key, $attempts, 120 ); 
            $remaining = $max_attempts - $attempts;
            if ($remaining <= 0) {
                 delete_transient( 'zma_otp_' . $phone_hash );
                 delete_transient( $attempts_key );
                 wp_send_json_error( [ 'message' => __('Too many failed attempts. Code invalidated.', 'zenith-mobile-auth') ] );
            }
            wp_send_json_error( [ 'message' => sprintf( __('Invalid Code. %d attempts remaining.', 'zenith-mobile-auth'), $remaining ) ] );
        }

        // --- SUCCESS ---
        delete_transient( 'zma_otp_' . $phone_hash );
        delete_transient( $attempts_key );
        delete_transient( 'zma_limit_' . md5($phone . '_' . date('Y-m-d')) );

        $user = get_user_by( 'login', $phone );
        $is_new_user = false;

        if ( ! $user ) {
            $is_new_user = true;
            $user_id = wp_create_user( $phone, wp_generate_password(), $phone . '@' . $_SERVER['SERVER_NAME'] );
            if ( is_wp_error( $user_id ) ) wp_send_json_error( [ 'message' => $user_id->get_error_message() ] );
            $user = get_user_by( 'id', $user_id );
        } else {
            update_user_meta( $user->ID, 'billing_phone', $phone );
        }

        wp_set_auth_cookie( $user->ID, true );
        wp_set_current_user( $user->ID );

        // Determine Redirect URL
        $redirect = home_url();
        if ( ! empty( $_REQUEST['redirect_to'] ) ) {
            $redirect = esc_url_raw( $_REQUEST['redirect_to'] );
        } elseif ( $myaccount = get_option( 'woocommerce_myaccount_page_id' ) ) {
            $redirect = get_permalink( $myaccount );
        }

        // Check Info Requirement
        $require_info = false;
        if ( isset($settings['enable_name_field']) && $settings['enable_name_field'] == '1' ) {
            if ( empty($user->first_name) && empty($user->last_name) ) {
                $require_info = true;
            }
        }

        // --- NEW: GENERATE FRESH NONCE FOR LOGGED-IN SESSION ---
        $new_nonce = wp_create_nonce( 'zma_auth_nonce' );

        wp_send_json_success( [ 
            'message' => __('Login Successful', 'zenith-mobile-auth'), 
            'redirect_to' => $redirect,
            'require_info' => $require_info,
            'new_nonce' => $new_nonce // Send valid nonce for next step
        ] );
    }

    public function update_user_info() {
        check_ajax_referer( 'zma_auth_nonce', 'security' );
        
        $user_id = get_current_user_id();
        if ( ! $user_id ) wp_send_json_error( [ 'message' => __('Session expired. Please login again.', 'zenith-mobile-auth') ] );

        $fname = sanitize_text_field( $_POST['fname'] ?? '' );
        $lname = sanitize_text_field( $_POST['lname'] ?? '' );
        $gender = sanitize_text_field( $_POST['gender'] ?? '' );

        // Update User
        wp_update_user([ 'ID' => $user_id, 'first_name' => $fname, 'last_name' => $lname ]);
        
        // Update Meta
        update_user_meta( $user_id, 'billing_first_name', $fname );
        update_user_meta( $user_id, 'billing_last_name', $lname );
        
        if ( ! empty($gender) ) {
            update_user_meta( $user_id, 'gender', $gender );
        }

        $redirect = home_url();
        if ( ! empty( $_POST['redirect_to'] ) ) {
            $redirect = esc_url_raw( $_POST['redirect_to'] );
        }

        wp_send_json_success( [ 'message' => __('Profile Updated', 'zenith-mobile-auth'), 'redirect_to' => $redirect ] );
    }

    public function test_sms() {
        check_ajax_referer( 'zma_test_nonce', 'security' );
        if( ! current_user_can( 'manage_options' ) ) wp_send_json_error(['message' => 'Unauthorized']);
        $phone = $this->normalize_phone( $_POST['phone'] ?? '' );
        if(!$phone) wp_send_json_error(['message'=>'Invalid Phone']);
        $gateway = new Zenith_Mobile_Auth_Gateway_IPPanel();
        $res = $gateway->send_pattern( $phone, '1234' );
        if($res === true) wp_send_json_success(['message'=>'Test SMS Sent']);
        else wp_send_json_error(['message'=>$res]);
    }

    public function reset_user_limits() {
        check_ajax_referer( 'zma_admin_nonce', 'security' );
        if ( ! current_user_can( 'edit_users' ) ) wp_send_json_error( [ 'message' => 'Unauthorized' ] );
        $user_id = isset( $_POST['user_id'] ) ? intval( $_POST['user_id'] ) : 0;
        $user = get_user_by( 'id', $user_id );
        if ( ! $user ) wp_send_json_error( [ 'message' => 'User not found' ] );
        $phone = $this->normalize_phone( $user->user_login ); 
        if ( ! $phone ) wp_send_json_error( [ 'message' => 'User login is not a valid phone number' ] );
        $phone_hash = md5( $phone );
        delete_transient( 'zma_limit_' . md5($phone . '_' . date('Y-m-d')) );
        delete_transient( 'zma_wait_' . $phone_hash );
        delete_transient( 'zma_otp_attempts_' . $phone_hash );
        delete_transient( 'zma_otp_' . $phone_hash );
        wp_send_json_success( [ 'message' => 'User limits and blocks have been reset successfully.' ] );
    }
}
