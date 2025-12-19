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
        return $this->send_request( $phone, $this->settings['pattern_code'], [ 
            $this->settings['otp_variable'] => (string)$otp 
        ]);
    }

    public function send_notification( $phone, $pattern_code, $args ) {
        // Map common keys if needed, IPPanel usually expects specific keys
        // We assume $args keys match pattern variables in IPPanel
        return $this->send_request( $phone, $pattern_code, $args );
    }

    private function send_request( $phone, $pattern, $values ) {
        $api_key = $this->settings['api_key'] ?? '';
        $originator = $this->settings['originator'] ?? '';

        if ( empty( $api_key ) ) return __( 'API Key Missing', 'zenith-mobile-auth' );

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
            'code'         => $pattern,
            'params'       => $values
        ];

        $args = [
            'body'    => json_encode( $body ),
            'headers' => [ 'Authorization' => $api_key, 'Content-Type'  => 'application/json' ],
            'timeout' => 15,
        ];

        $response = wp_remote_post( $this->api_url, $args );

        if ( is_wp_error( $response ) ) return $response->get_error_message();

        $code = wp_remote_retrieve_response_code( $response );
        $data = json_decode( wp_remote_retrieve_body( $response ) );

        if ( $code === 200 ) return true;
        if ( isset( $data->meta->message ) ) return 'Error: ' . $data->meta->message;
        if ( isset( $data->message ) ) return 'Error: ' . $data->message;

        return 'Unknown Error (Status ' . $code . ')';
    }
}