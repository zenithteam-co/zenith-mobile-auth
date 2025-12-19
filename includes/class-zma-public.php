<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class Zenith_Mobile_Auth_Public {

    private $options;

    public function __construct() {
        $this->options = get_option( 'zma_settings' );

        add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_assets' ] );
        add_action( 'wp_footer', [ $this, 'print_js_vars' ], 99 );

        add_action( 'woocommerce_login_form', [ $this, 'render_login_form' ] );
        add_action( 'woocommerce_checkout_login_form', [ $this, 'render_login_form' ] );

        add_action( 'wp_head', [ $this, 'dynamic_styles' ] );
        
        // WooCommerce Hooks
        add_filter( 'woocommerce_account_details_fields', [ $this, 'clean_account_details' ], 10, 1 );
        add_action( 'woocommerce_edit_account_form', [ $this, 'readonly_phone_field' ] );
        
        // Gender Hooks
        if ( isset($this->options['enable_gender_field']) && $this->options['enable_gender_field'] == '1' ) {
            add_action( 'woocommerce_edit_account_form', [ $this, 'render_gender_field_account' ] );
            add_action( 'woocommerce_save_account_details', [ $this, 'save_gender_field_account' ] );
            
            // Checkout Gender
            add_filter( 'woocommerce_checkout_fields', [ $this, 'add_gender_checkout_field' ] );
            add_action( 'woocommerce_checkout_update_user_meta', [ $this, 'save_gender_checkout_field' ] );
        }
    }

    public function enqueue_assets() {
        wp_enqueue_script( 'jquery' );
        wp_enqueue_style( 'zma-style', ZMA_URL . 'assets/css/zma-style.css', [], ZMA_VERSION );
        wp_enqueue_script( 'zma-script', ZMA_URL . 'assets/js/zma-script.js', ['jquery'], ZMA_VERSION, true );
    }

    private function get_dim( $prefix, $default ) {
        $t = isset($this->options[$prefix.'_top']) ? $this->options[$prefix.'_top'] : $default;
        $r = isset($this->options[$prefix.'_right']) ? $this->options[$prefix.'_right'] : $default;
        $b = isset($this->options[$prefix.'_bottom']) ? $this->options[$prefix.'_bottom'] : $default;
        $l = isset($this->options[$prefix.'_left']) ? $this->options[$prefix.'_left'] : $default;
        return "$t $r $b $l";
    }

    public function dynamic_styles() {
        $o = $this->options;
        
        $con_pad = $this->get_dim('con_padding', '20px');
        $con_rad = $this->get_dim('con_radius', '8px');
        $con_bw  = $this->get_dim('con_border_w', '1px');
        
        $inp_pad = $this->get_dim('inp_padding', '10px');
        $inp_rad = $this->get_dim('inp_radius', '4px');
        
        $btn_pad = $this->get_dim('btn_padding', '12px');
        $btn_rad = $this->get_dim('btn_radius', '4px');

        $css = "
            .woocommerce-form-login > p:not(.zma-container),
            .woocommerce-form-register, .u-column2, .col-2,
            .woocommerce-form-login .woocommerce-LostPassword,
            .woocommerce-form-login label[for='username'],
            .woocommerce-form-login label[for='password'],
            .woocommerce-form-login__submit { display: none !important; }
            .u-column1, .col-1 { width: 100% !important; max-width: 100% !important; flex: 0 0 100% !important; }
            
            .zma-container {
                max-width: " . esc_attr($o['con_width'] ?? '400px') . ";
                background-color: " . esc_attr($o['con_bg'] ?? '#ffffff') . ";
                padding: {$con_pad};
                border-radius: {$con_rad};
                border-width: {$con_bw};
                border-style: " . esc_attr($o['con_border_style'] ?? 'solid') . ";
                border-color: " . esc_attr($o['con_border_color'] ?? '#e5e5e5') . ";
            }
            .zma-header h3 {
                font-size: " . esc_attr($o['title_font_size'] ?? '20px') . ";
                color: " . esc_attr($o['title_color'] ?? '#333') . ";
                margin-bottom: " . esc_attr($o['title_margin'] ?? '10px') . ";
            }
            .zma-header p {
                font-size: " . esc_attr($o['desc_font_size'] ?? '14px') . ";
                color: " . esc_attr($o['desc_color'] ?? '#666') . ";
                margin-bottom: " . esc_attr($o['desc_margin'] ?? '20px') . ";
            }
            .zma-input, .zma-otp-digit, .zma-select {
                padding: {$inp_pad};
                border-radius: {$inp_rad};
                border-color: " . esc_attr($o['inp_border_color'] ?? '#ccc') . ";
                border-width: 1px; border-style: solid;
                width: 100%; box-sizing: border-box;
            }
            .zma-btn {
                background-color: " . esc_attr($o['btn_bg'] ?? '#333') . ";
                color: " . esc_attr($o['btn_text'] ?? '#fff') . ";
                padding: {$btn_pad};
                border-radius: {$btn_rad};
            }
            .zma-input-group { margin-bottom: " . esc_attr($o['inp_margin'] ?? '15px') . "; }
        ";
        echo "<style>{$css}</style>";
    }

    public function render_login_form() {
        if ( is_user_logged_in() ) return;
        global $zma_form_rendered;
        if ( isset($zma_form_rendered) && $zma_form_rendered ) return;
        $zma_form_rendered = true;
        
        $otp_len = $this->options['otp_length'] ?? 4;
        ?>
        <div class="zma-container">
            <div id="zma-toast"></div>
            <div class="zma-header">
                <h3><?php esc_html_e( 'Login or Register', 'zenith-mobile-auth' ); ?></h3>
                <p><?php esc_html_e( 'We will send an OTP code to your mobile number.', 'zenith-mobile-auth' ); ?></p>
            </div>
            
            <!-- Step 1: Phone -->
            <div id="zma-step-phone" class="zma-step active">
                <div class="zma-input-group">
                    <label for="zma-phone"><?php esc_html_e( 'Phone Number', 'zenith-mobile-auth' ); ?></label>
                    <input type="tel" id="zma-phone" class="zma-input" placeholder="0912..." maxlength="11" autocomplete="tel">
                </div>
                <button type="button" id="zma-send-otp-btn" class="zma-btn"><?php esc_html_e( 'Send Code', 'zenith-mobile-auth' ); ?></button>
            </div>

            <!-- Step 2: OTP -->
            <div id="zma-step-otp" class="zma-step">
                <p style="text-align:center; margin-bottom:15px;">
                    <?php esc_html_e( 'Code sent to', 'zenith-mobile-auth' ); ?> <span id="zma-phone-display" style="font-weight:bold;"></span>
                    <br><a href="#" id="zma-change-number" style="font-size:12px;"><?php esc_html_e( 'Change Number', 'zenith-mobile-auth' ); ?></a>
                </p>
                
                <div class="zma-input-group zma-otp-container">
                    <input type="text" id="zma-otp-real" inputmode="numeric" autocomplete="one-time-code" maxlength="<?php echo esc_attr($otp_len); ?>">
                    <div class="zma-otp-group">
                        <?php for($i=1; $i<=$otp_len; $i++): ?>
                            <input type="text" class="zma-otp-digit" maxlength="1" disabled>
                        <?php endfor; ?>
                    </div>
                </div>

                <button type="button" id="zma-verify-otp-btn" class="zma-btn"><?php esc_html_e( 'Verify & Login', 'zenith-mobile-auth' ); ?></button>
                <div class="zma-resend-wrapper">
                    <span id="zma-timer-text"><?php esc_html_e('Resend in', 'zenith-mobile-auth'); ?> <span id="zma-timer">0</span>s</span>
                    <button type="button" id="zma-resend-btn" class="zma-text-btn" disabled><?php esc_html_e('Resend Code', 'zenith-mobile-auth'); ?></button>
                </div>
            </div>

            <!-- Step 3: Info Collection (Hidden by default) -->
            <div id="zma-step-info" class="zma-step">
                <p style="text-align:center; margin-bottom:15px; font-weight:bold;">
                    <?php esc_html_e( 'Complete your profile', 'zenith-mobile-auth' ); ?>
                </p>
                
                <div class="zma-input-group">
                    <label for="zma-fname"><?php esc_html_e( 'First Name', 'zenith-mobile-auth' ); ?></label>
                    <input type="text" id="zma-fname" class="zma-input">
                </div>
                
                <div class="zma-input-group">
                    <label for="zma-lname"><?php esc_html_e( 'Last Name', 'zenith-mobile-auth' ); ?></label>
                    <input type="text" id="zma-lname" class="zma-input">
                </div>

                <?php if ( isset($this->options['enable_gender_field']) && $this->options['enable_gender_field'] == '1' ): ?>
                <div class="zma-input-group">
                    <label for="zma-gender"><?php esc_html_e( 'Gender', 'zenith-mobile-auth' ); ?></label>
                    <select id="zma-gender" class="zma-select">
                        <option value=""><?php esc_html_e( 'Select Gender', 'zenith-mobile-auth' ); ?></option>
                        <option value="male"><?php esc_html_e( 'Male', 'zenith-mobile-auth' ); ?></option>
                        <option value="female"><?php esc_html_e( 'Female', 'zenith-mobile-auth' ); ?></option>
                    </select>
                </div>
                <?php endif; ?>

                <button type="button" id="zma-save-info-btn" class="zma-btn"><?php esc_html_e( 'Save & Continue', 'zenith-mobile-auth' ); ?></button>
                
                <?php if ( isset($this->options['allow_skip_name']) && $this->options['allow_skip_name'] == '1' ): ?>
                    <button type="button" id="zma-skip-info-btn" class="zma-text-btn" style="width:100%; margin-top:10px;"><?php esc_html_e( 'Skip for now', 'zenith-mobile-auth' ); ?></button>
                <?php endif; ?>
            </div>
        </div>
        <?php
    }

    public function print_js_vars() {
        if(is_admin()) return;
        $resend_time = $this->options['resend_time'] ?? 120;
        $otp_len = $this->options['otp_length'] ?? 4;
        $vars = [
            'ajax_url' => admin_url( 'admin-ajax.php' ),
            'nonce'    => wp_create_nonce( 'zma_auth_nonce' ),
            'resend_time' => (int)$resend_time,
            'otp_len' => (int)$otp_len,
            'strings' => [
                'sending' => __('Sending...', 'zenith-mobile-auth'),
                'verifying' => __('Verifying...', 'zenith-mobile-auth'),
                'saving' => __('Saving...', 'zenith-mobile-auth'),
                'btn_send' => __('Send Code', 'zenith-mobile-auth'),
                'btn_verify' => __('Verify & Login', 'zenith-mobile-auth'),
                'error_phone' => __('Invalid Phone', 'zenith-mobile-auth'),
                'error_otp' => __('Enter Full Code', 'zenith-mobile-auth'),
                'error_name' => __('Please enter your name.', 'zenith-mobile-auth'),
            ]
        ];
        echo '<script>var zma_vars = ' . json_encode($vars) . ';</script>';
    }

    public function clean_account_details( $fields ) {
        if ( isset( $fields['account_email'] ) ) unset( $fields['account_email'] );
        return $fields;
    }

    public function readonly_phone_field() {
        $current_user = wp_get_current_user();
        ?>
        <p class="woocommerce-form-row form-row form-row-wide">
            <label><?php esc_html_e( 'Phone Number', 'zenith-mobile-auth' ); ?></label>
            <input type="text" class="input-text" value="<?php echo esc_attr($current_user->user_login); ?>" readonly disabled />
        </p>
        <style> #account_email_field { display: none !important; } </style>
        <?php
    }

    // Gender Field in My Account
    public function render_gender_field_account() {
        $user_id = get_current_user_id();
        $gender = get_user_meta( $user_id, 'gender', true );
        ?>
        <p class="woocommerce-form-row form-row form-row-wide">
            <label for="account_gender"><?php esc_html_e( 'Gender', 'zenith-mobile-auth' ); ?></label>
            <select name="account_gender" id="account_gender" class="woocommerce-Input">
                <option value=""><?php esc_html_e( 'Select Gender', 'zenith-mobile-auth' ); ?></option>
                <option value="male" <?php selected( $gender, 'male' ); ?>><?php esc_html_e( 'Male', 'zenith-mobile-auth' ); ?></option>
                <option value="female" <?php selected( $gender, 'female' ); ?>><?php esc_html_e( 'Female', 'zenith-mobile-auth' ); ?></option>
            </select>
        </p>
        <?php
    }

    public function save_gender_field_account( $user_id ) {
        if ( isset( $_POST['account_gender'] ) ) {
            update_user_meta( $user_id, 'gender', sanitize_text_field( $_POST['account_gender'] ) );
        }
    }

    // Gender in Checkout
    public function add_gender_checkout_field( $fields ) {
        $fields['billing']['billing_gender'] = array(
            'label'     => __( 'Gender', 'zenith-mobile-auth' ),
            'type'      => 'select',
            'options'   => [
                ''       => __( 'Select Gender', 'zenith-mobile-auth' ),
                'male'   => __( 'Male', 'zenith-mobile-auth' ),
                'female' => __( 'Female', 'zenith-mobile-auth' )
            ],
            'required'  => false,
            'class'     => ['form-row-wide'],
            'priority'  => 25
        );
        
        // Populate if logged in
        if ( is_user_logged_in() ) {
            $user_id = get_current_user_id();
            $gender = get_user_meta( $user_id, 'gender', true );
            if($gender) $fields['billing']['billing_gender']['default'] = $gender;
        }

        return $fields;
    }

    public function save_gender_checkout_field( $order_id ) {
        if ( ! empty( $_POST['billing_gender'] ) && is_user_logged_in() ) {
            update_user_meta( get_current_user_id(), 'gender', sanitize_text_field( $_POST['billing_gender'] ) );
        }
    }
}
