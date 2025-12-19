<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class Zenith_Mobile_Auth_Gateway_IPPanel {
    private $api_url = 'https://edge.ippanel.com/v1/api/send';
    private $api_key, $originator, $pattern_code, $otp_var;

    public function __construct() {
        $options = get_option( 'zma_settings' );
        $this->api_key      = $options['api_key'] ?? '';
        $this->originator   = $options['originator'] ?? '';
        $this->pattern_code = $options['pattern_code'] ?? '';
        $this->otp_var      = $options['otp_variable'] ?? 'code';
    }

    public function send_pattern( $phone, $otp ) {
        if ( empty( $this->api_key ) ) return 'API Key Missing';

        $recipient_e164 = '+98' . ltrim( $phone, '0' );
        $originator_e164 = $this->clean_originator( $this->originator );

        $body = [
            'sending_type' => 'pattern',
            'from_number'  => $originator_e164,
            'recipients'   => [ $recipient_e164 ],
            'code'         => $this->pattern_code,
            'params'       => [ $this->otp_var => (string)$otp ]
        ];

        $args = [
            'body'    => json_encode( $body ),
            'headers' => [ 'Authorization' => $this->api_key, 'Content-Type' => 'application/json' ],
            'timeout' => 15,
        ];

        $response = wp_remote_post( $this->api_url, $args );
        if ( is_wp_error( $response ) ) return $response->get_error_message();

        $code = wp_remote_retrieve_response_code( $response );
        $data = json_decode( wp_remote_retrieve_body( $response ) );

        if ( $code === 200 ) return true;
        return isset( $data->meta->message ) ? $data->meta->message : 'Error ' . $code;
    }

    private function clean_originator( $num ) {
        $num = trim($num);
        if(substr($num,0,1) === '0') $num = substr($num,1);
        if(substr($num,0,2) !== '98') $num = '98'.$num;
        if(substr($num,0,1) !== '+') $num = '+'.$num;
        return $num;
    }
}
