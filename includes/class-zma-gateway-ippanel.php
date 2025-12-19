<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class ZMA_Gateway_IPPanel extends ZMA_Gateway {

    private $api_url = 'https://edge.ippanel.com/v1/api/send';

    public function __construct() {
        parent::__construct();
        $this->id = 'ippanel';
        $this->name = 'IPPanel (Edge API)';
    }

    public function send_pattern( $phone, $otp ) {
        $api_key = isset($this->settings['api_key']) ? $this->settings['api_key'] : '';
        $originator = isset($this->settings['originator']) ? $this->settings['originator'] : '';
        $pattern_code = isset($this->settings['pattern_code']) ? $this->settings['pattern_code'] : '';
        $otp_var = isset($this->settings['otp_variable']) ? $this->settings['otp_variable'] : 'code';

        if ( empty( $api_key ) ) return __( 'API Key Missing', 'zenith-mobile-auth' );

        // E.164 Formatting
        $recipient_e164 = '+98' . ltrim( $phone, '0' );
        $originator_e164 = trim( $originator );
        
        if ( ! empty( $originator_e164 ) ) {
            if ( substr( $originator_e164, 0, 1 ) === '0' ) $originator_e164 = substr( $originator_e164, 1 ); 
            if ( substr( $originator_e164, 0, 2 ) !== '98' ) $originator_e164 = '98' . $originator_e164; 
            if ( substr( $originator_e164, 0, 1 ) !== '+' ) $originator_e164 = '+' . $originator_e164; 
        }

        $body = [
            'sending_type' => 'pattern',
            'from_number'  => $originator_e164,
            'recipients'   => [ $recipient_e164 ],
            'code'         => $pattern_code,
            'params'       => [ $otp_var => (string)$otp ]
        ];

        $args = [
            'body'    => json_encode( $body ),
            'headers' => [
                'Authorization' => $api_key, 
                'Content-Type'  => 'application/json',
            ],
            'timeout' => 15,
        ];

        $response = wp_remote_post( $this->api_url, $args );

        if ( is_wp_error( $response ) ) {
            return $response->get_error_message();
        }

        $code = wp_remote_retrieve_response_code( $response );
        $body_res = wp_remote_retrieve_body( $response );
        $data = json_decode( $body_res );

        if ( $code === 200 ) {
            return true; 
        }

        if ( isset( $data->meta->message ) ) return 'Provider Error: ' . $data->meta->message;
        if ( isset( $data->message ) ) return 'Provider Error: ' . $data->message;

        return 'Unknown Error (Status ' . $code . ')';
    }
}