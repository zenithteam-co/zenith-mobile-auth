<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class Zenith_Mobile_Auth_Admin {

    private $option_group = 'zma_plugin_options';
    private $option_name  = 'zma_settings';

    public function __construct() {
        add_action( 'admin_menu', [ $this, 'add_plugin_page' ] );
        add_action( 'admin_init', [ $this, 'page_init' ] );
        add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_admin_assets' ] );

        // User Profile Hooks
        add_action( 'show_user_profile', [ $this, 'render_user_profile_fields' ] );
        add_action( 'edit_user_profile', [ $this, 'render_user_profile_fields' ] );
        add_action( 'admin_footer', [ $this, 'print_user_profile_js' ] );
    }

    public function enqueue_admin_assets( $hook ) {
        if ( 'settings_page_zenith-mobile-auth' !== $hook ) return;
        
        wp_enqueue_style( 'wp-color-picker' );
        wp_enqueue_script( 'wp-color-picker' );

        wp_enqueue_style( 'zma-admin-css', ZMA_URL . 'assets/css/zma-admin.css', [], ZMA_VERSION );
        wp_enqueue_script( 'zma-admin-js', ZMA_URL . 'assets/js/zma-admin.js', [ 'jquery', 'wp-color-picker' ], ZMA_VERSION, true );
        
        wp_enqueue_style( 'zma-public-css', ZMA_URL . 'assets/css/zma-style.css', [], ZMA_VERSION );
    }

    public function add_plugin_page() {
        add_options_page(
            __( 'Zenith Mobile Auth', 'zenith-mobile-auth' ),
            __( 'Mobile Auth (OTP)', 'zenith-mobile-auth' ),
            'manage_options',
            'zenith-mobile-auth',
            [ $this, 'create_admin_page' ]
        );
    }

    public function page_init() {
        register_setting( $this->option_group, $this->option_name );
    }

    private function render_dimensions($label, $prefix, $options, $css_target, $css_prop, $default = '0px') {
        $sides = ['top', 'right', 'bottom', 'left'];
        $is_linked = isset($options[$prefix.'_linked']) ? $options[$prefix.'_linked'] : '1'; 
        ?>
        <div class="zma-dim-wrapper">
            <span class="zma-dim-label"><?php echo esc_html($label); ?></span>
            <div class="zma-dim-control" data-linked="<?php echo esc_attr($is_linked); ?>">
                <input type="hidden" name="zma_settings[<?php echo esc_attr($prefix); ?>_linked]" value="<?php echo esc_attr($is_linked); ?>" class="zma-linked-val">
                <div class="zma-dim-inputs">
                    <?php foreach($sides as $side): 
                        $val = isset($options[$prefix.'_'.$side]) ? $options[$prefix.'_'.$side] : $default;
                    ?>
                        <div class="zma-dim-field">
                            <input type="text" 
                                   name="zma_settings[<?php echo esc_attr($prefix.'_'.$side); ?>]" 
                                   value="<?php echo esc_attr($val); ?>" 
                                   class="zma-dim-input zma-live-dim"
                                   data-css-target="<?php echo esc_attr($css_target); ?>"
                                   data-css-prop="<?php echo esc_attr($css_prop); ?>"
                                   data-side="<?php echo esc_attr($side); ?>"
                                   placeholder="<?php echo esc_attr($side); ?>">
                            <label><?php echo substr(strtoupper($side), 0, 1); ?></label>
                        </div>
                    <?php endforeach; ?>
                </div>
                <button type="button" class="zma-link-btn <?php echo $is_linked === '1' ? 'active' : ''; ?>" title="Link values together">
                    <span class="dashicons dashicons-admin-links"></span>
                    <span class="dashicons dashicons-editor-unlink"></span>
                </button>
            </div>
        </div>
        <?php
    }

    public function create_admin_page() {
        $o = get_option( $this->option_name );
        $o = wp_parse_args( $o, [
            'api_key' => '',
            'style_btn_bg' => '#333333',
            'style_btn_text' => '#ffffff',
            'style_container_bg' => '#ffffff',
            'style_input_border' => '#dddddd',
            'style_container_border' => '#e5e5e5',
            'title_font_size' => '20px',
            'title_color' => '#333333',
            'title_margin' => '10px',
            'desc_font_size' => '14px',
            'desc_color' => '#666666',
            'desc_margin' => '20px',
            'max_attempts' => '3',
            'daily_limit' => '10',
            // New Registration Settings
            'enable_name_field' => '0',
            'allow_skip_name' => '0',
            'enable_gender_field' => '0'
        ]);
        ?>
        <div class="zma-admin-wrapper">
            <div class="zma-admin-header">
                <h1>Zenith Mobile Auth</h1>
                <p>Advanced OTP Authentication for WooCommerce</p>
            </div>

            <form method="post" action="options.php" id="zma-main-form">
                <?php settings_fields( $this->option_group ); ?>
                
                <div class="zma-admin-body">
                    <div class="zma-sidebar">
                        <ul class="zma-nav">
                            <li class="active" data-tab="general"><span class="dashicons dashicons-admin-settings"></span> General</li>
                            <li data-tab="registration"><span class="dashicons dashicons-id-alt"></span> Registration Data</li>
                            <li data-tab="container"><span class="dashicons dashicons-layout"></span> Container Style</li>
                            <li data-tab="texts"><span class="dashicons dashicons-text-page"></span> Typography & Texts</li>
                            <li data-tab="inputs"><span class="dashicons dashicons-edit"></span> Inputs & Button</li>
                            <li data-tab="security"><span class="dashicons dashicons-shield"></span> Security</li>
                        </ul>
                        <div class="zma-save-area">
                            <?php submit_button( 'Save Changes', 'primary large zma-save-btn' ); ?>
                        </div>
                    </div>

                    <div class="zma-content">
                        <!-- TAB: General -->
                        <div id="tab-general" class="zma-tab-content active">
                            <h2>IPPanel Gateway</h2>
                            <div class="zma-form-group">
                                <label>API Key / Token</label>
                                <input type="text" name="zma_settings[api_key]" value="<?php echo esc_attr( $o['api_key'] ); ?>" class="widefat">
                            </div>
                            <div class="zma-form-group">
                                <label>Originator</label>
                                <input type="text" name="zma_settings[originator]" value="<?php echo esc_attr( $o['originator'] ?? '' ); ?>" class="widefat">
                            </div>
                            <div class="zma-form-group">
                                <label>Pattern Code</label>
                                <input type="text" name="zma_settings[pattern_code]" value="<?php echo esc_attr( $o['pattern_code'] ?? '' ); ?>" class="widefat">
                            </div>
                            <div class="zma-form-group">
                                <label>Variable Name</label>
                                <input type="text" name="zma_settings[otp_variable]" value="<?php echo esc_attr( $o['otp_variable'] ?? 'code' ); ?>" class="widefat">
                            </div>
                        </div>

                        <!-- TAB: Registration (NEW) -->
                        <div id="tab-registration" class="zma-tab-content">
                            <h2>Registration Information</h2>
                            <p class="description">Collect additional information after OTP verification if the user profile is incomplete.</p>
                            
                            <div class="zma-form-group">
                                <label>
                                    <input type="checkbox" name="zma_settings[enable_name_field]" value="1" <?php checked($o['enable_name_field'], '1'); ?>>
                                    Ask for Name & Family Name
                                </label>
                                <p class="desc">If enabled, users will be asked to enter their name after login/registration if it's missing.</p>
                            </div>

                            <div class="zma-form-group">
                                <label>
                                    <input type="checkbox" name="zma_settings[allow_skip_name]" value="1" <?php checked($o['allow_skip_name'], '1'); ?>>
                                    Allow Skipping
                                </label>
                                <p class="desc">Add a "Skip" button to the information step.</p>
                            </div>

                            <div class="zma-form-group">
                                <label>
                                    <input type="checkbox" name="zma_settings[enable_gender_field]" value="1" <?php checked($o['enable_gender_field'], '1'); ?>>
                                    Enable Gender Field
                                </label>
                                <p class="desc">Ask for gender during registration and show in WooCommerce Checkout/Account.</p>
                            </div>
                        </div>

                        <!-- TAB: Container Style -->
                        <div id="tab-container" class="zma-tab-content">
                            <h2>Container Box Style</h2>
                            <div class="zma-card">
                                <h3>Background & Dimensions</h3>
                                <div class="zma-row">
                                    <div class="zma-col">
                                        <label>Max Width</label>
                                        <input type="text" class="zma-live-css" data-css-target=".zma-container" data-css-prop="max-width" name="zma_settings[con_width]" value="<?php echo esc_attr( $o['con_width'] ?? '400px' ); ?>" placeholder="400px">
                                    </div>
                                    <div class="zma-col">
                                        <label>Background Color</label>
                                        <input type="text" class="zma-color-picker zma-live-css" data-css-target=".zma-container" data-css-prop="background-color" name="zma_settings[con_bg]" value="<?php echo esc_attr( $o['con_bg'] ?? '#ffffff' ); ?>">
                                    </div>
                                </div>
                            </div>
                            <div class="zma-card">
                                <h3>Padding & Radius</h3>
                                <?php 
                                $this->render_dimensions('Container Padding', 'con_padding', $o, '.zma-container', 'padding', '20px'); 
                                $this->render_dimensions('Border Radius', 'con_radius', $o, '.zma-container', 'border-radius', '8px'); 
                                ?>
                            </div>
                            <div class="zma-card">
                                <h3>Border</h3>
                                <div class="zma-row">
                                    <div class="zma-col">
                                        <label>Style</label>
                                        <select class="zma-live-css" data-css-target=".zma-container" data-css-prop="border-style" name="zma_settings[con_border_style]">
                                            <option value="solid" <?php selected($o['con_border_style'] ?? 'solid', 'solid'); ?>>Solid</option>
                                            <option value="none" <?php selected($o['con_border_style'] ?? 'solid', 'none'); ?>>None</option>
                                        </select>
                                    </div>
                                    <div class="zma-col">
                                        <label>Color</label>
                                        <input type="text" class="zma-color-picker zma-live-css" data-css-target=".zma-container" data-css-prop="border-color" name="zma_settings[con_border_color]" value="<?php echo esc_attr( $o['con_border_color'] ?? '#e5e5e5' ); ?>">
                                    </div>
                                </div>
                                <?php $this->render_dimensions('Border Width', 'con_border_w', $o, '.zma-container', 'border-width', '1px'); ?>
                            </div>
                        </div>

                        <!-- TAB: Texts -->
                        <div id="tab-texts" class="zma-tab-content">
                            <h2>Typography & Texts</h2>
                            <div class="zma-card">
                                <h3>Form Title</h3>
                                <div class="zma-form-group">
                                    <label>Text</label>
                                    <input type="text" class="widefat zma-live-text" data-target="#zma-preview-title" name="zma_settings[form_title]" value="<?php echo esc_attr( $o['form_title'] ?? 'Login or Register' ); ?>">
                                </div>
                                <div class="zma-row">
                                    <div class="zma-col">
                                        <label>Font Size</label>
                                        <input type="text" class="zma-live-css" data-css-target="#zma-preview-title" data-css-prop="font-size" name="zma_settings[title_font_size]" value="<?php echo esc_attr( $o['title_font_size'] ?? '20px' ); ?>" placeholder="20px">
                                    </div>
                                    <div class="zma-col">
                                        <label>Color</label>
                                        <input type="text" class="zma-color-picker zma-live-css" data-css-target="#zma-preview-title" data-css-prop="color" name="zma_settings[title_color]" value="<?php echo esc_attr( $o['title_color'] ?? '#333333' ); ?>">
                                    </div>
                                    <div class="zma-col">
                                        <label>Margin Bottom</label>
                                        <input type="text" class="zma-live-css" data-css-target="#zma-preview-title" data-css-prop="margin-bottom" name="zma_settings[title_margin]" value="<?php echo esc_attr( $o['title_margin'] ?? '10px' ); ?>" placeholder="10px">
                                    </div>
                                </div>
                            </div>
                            <div class="zma-card">
                                <h3>Form Description</h3>
                                <div class="zma-form-group">
                                    <label>Text</label>
                                    <textarea class="widefat zma-live-text" data-target="#zma-preview-desc" name="zma_settings[form_desc]" rows="2"><?php echo esc_textarea( $o['form_desc'] ?? 'We will send an OTP code to your mobile number.' ); ?></textarea>
                                </div>
                                <div class="zma-row">
                                    <div class="zma-col">
                                        <label>Font Size</label>
                                        <input type="text" class="zma-live-css" data-css-target="#zma-preview-desc" data-css-prop="font-size" name="zma_settings[desc_font_size]" value="<?php echo esc_attr( $o['desc_font_size'] ?? '14px' ); ?>" placeholder="14px">
                                    </div>
                                    <div class="zma-col">
                                        <label>Color</label>
                                        <input type="text" class="zma-color-picker zma-live-css" data-css-target="#zma-preview-desc" data-css-prop="color" name="zma_settings[desc_color]" value="<?php echo esc_attr( $o['desc_color'] ?? '#666666' ); ?>">
                                    </div>
                                    <div class="zma-col">
                                        <label>Margin Bottom</label>
                                        <input type="text" class="zma-live-css" data-css-target="#zma-preview-desc" data-css-prop="margin-bottom" name="zma_settings[desc_margin]" value="<?php echo esc_attr( $o['desc_margin'] ?? '20px' ); ?>" placeholder="20px">
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- TAB: Inputs -->
                        <div id="tab-inputs" class="zma-tab-content">
                            <h2>Elements Style</h2>
                            <div class="zma-card">
                                <h3>Input Fields</h3>
                                <?php 
                                $this->render_dimensions('Padding', 'inp_padding', $o, '.zma-input', 'padding', '10px'); 
                                $this->render_dimensions('Border Radius', 'inp_radius', $o, '.zma-input', 'border-radius', '4px'); 
                                ?>
                                <div class="zma-row" style="margin-top:15px;">
                                    <div class="zma-col">
                                        <label>Border Color</label>
                                        <input type="text" class="zma-color-picker zma-live-css" data-css-target=".zma-input" data-css-prop="border-color" name="zma_settings[inp_border_color]" value="<?php echo esc_attr( $o['inp_border_color'] ?? '#cccccc' ); ?>">
                                    </div>
                                    <div class="zma-col">
                                        <label>Bottom Spacing</label>
                                        <input type="text" class="zma-live-css" data-css-target=".zma-input-group" data-css-prop="margin-bottom" name="zma_settings[inp_margin]" value="<?php echo esc_attr( $o['inp_margin'] ?? '15px' ); ?>">
                                    </div>
                                </div>
                            </div>
                            <div class="zma-card">
                                <h3>Submit Button</h3>
                                <div class="zma-row">
                                    <div class="zma-col">
                                        <label>Background</label>
                                        <input type="text" class="zma-color-picker zma-live-css" data-css-target=".zma-btn" data-css-prop="background-color" name="zma_settings[btn_bg]" value="<?php echo esc_attr( $o['btn_bg'] ?? '#333333' ); ?>">
                                    </div>
                                    <div class="zma-col">
                                        <label>Text Color</label>
                                        <input type="text" class="zma-color-picker zma-live-css" data-css-target=".zma-btn" data-css-prop="color" name="zma_settings[btn_text]" value="<?php echo esc_attr( $o['btn_text'] ?? '#ffffff' ); ?>">
                                    </div>
                                </div>
                                <?php 
                                $this->render_dimensions('Padding', 'btn_padding', $o, '.zma-btn', 'padding', '12px'); 
                                $this->render_dimensions('Border Radius', 'btn_radius', $o, '.zma-btn', 'border-radius', '4px'); 
                                ?>
                            </div>
                        </div>

                        <!-- TAB: Security -->
                        <div id="tab-security" class="zma-tab-content">
                            <h2>Security & Constraints</h2>
                            <div class="zma-form-group">
                                <label>OTP Code Length</label>
                                <input type="number" name="zma_settings[otp_length]" value="<?php echo esc_attr( $o['otp_length'] ?? 4 ); ?>" min="4" max="6" class="small-text">
                            </div>
                            <div class="zma-form-group">
                                <label>Resend Timer (Seconds)</label>
                                <input type="number" name="zma_settings[resend_time]" value="<?php echo esc_attr( $o['resend_time'] ?? 120 ); ?>" class="small-text">
                            </div>
                            <div class="zma-form-group">
                                <label>Daily SMS Limit (Per Phone)</label>
                                <input type="number" name="zma_settings[daily_limit]" value="<?php echo esc_attr( $o['daily_limit'] ?? 10 ); ?>" class="small-text">
                            </div>
                            <div class="zma-form-group">
                                <label>Max Incorrect Attempts</label>
                                <input type="number" name="zma_settings[max_attempts]" value="<?php echo esc_attr( $o['max_attempts'] ?? 3 ); ?>" class="small-text">
                            </div>
                        </div>
                    </div>

                    <!-- Preview -->
                    <div class="zma-preview-pane">
                        <div class="zma-preview-sticky">
                            <h3>Live Preview</h3>
                            <div class="zma-preview-box">
                                <div class="zma-container" style="
                                    max-width: <?php echo esc_attr($o['con_width'] ?? '400px'); ?>;
                                    background-color: <?php echo esc_attr($o['con_bg'] ?? '#fff'); ?>;
                                    padding: <?php echo esc_attr( ($o['con_padding_top']??'20px').' '.($o['con_padding_right']??'20px').' '.($o['con_padding_bottom']??'20px').' '.($o['con_padding_left']??'20px') ); ?>;
                                    border-radius: <?php echo esc_attr( ($o['con_radius_top']??'8px').' '.($o['con_radius_right']??'8px').' '.($o['con_radius_bottom']??'8px').' '.($o['con_radius_left']??'8px') ); ?>;
                                    border-width: <?php echo esc_attr( ($o['con_border_w_top']??'1px').' '.($o['con_border_w_right']??'1px').' '.($o['con_border_w_bottom']??'1px').' '.($o['con_border_w_left']??'1px') ); ?>;
                                    border-style: <?php echo esc_attr($o['con_border_style'] ?? 'solid'); ?>;
                                    border-color: <?php echo esc_attr($o['con_border_color'] ?? '#e5e5e5'); ?>;
                                ">
                                    <div class="zma-header">
                                        <h3 id="zma-preview-title" style="
                                            font-size: <?php echo esc_attr($o['title_font_size'] ?? '20px'); ?>;
                                            color: <?php echo esc_attr($o['title_color'] ?? '#333'); ?>;
                                            margin-bottom: <?php echo esc_attr($o['title_margin'] ?? '10px'); ?>;
                                        "><?php esc_html_e( 'Login or Register', 'zenith-mobile-auth' ); ?></h3>
                                        <p id="zma-preview-desc" style="
                                            font-size: <?php echo esc_attr($o['desc_font_size'] ?? '14px'); ?>;
                                            color: <?php echo esc_attr($o['desc_color'] ?? '#666'); ?>;
                                            margin-bottom: <?php echo esc_attr($o['desc_margin'] ?? '20px'); ?>;
                                        "><?php esc_html_e( 'We will send an OTP code to your mobile number.', 'zenith-mobile-auth' ); ?></p>
                                    </div>
                                    <div class="zma-input-group" style="margin-bottom: <?php echo esc_attr($o['inp_margin'] ?? '15px'); ?>;">
                                        <label><?php esc_html_e( 'Phone Number', 'zenith-mobile-auth' ); ?></label>
                                        <input type="tel" class="zma-input" placeholder="0912..." disabled style="
                                            padding: <?php echo esc_attr( ($o['inp_padding_top']??'10px').' '.($o['inp_padding_right']??'10px').' '.($o['inp_padding_bottom']??'10px').' '.($o['inp_padding_left']??'10px') ); ?>;
                                            border-radius: <?php echo esc_attr( ($o['inp_radius_top']??'4px').' '.($o['inp_radius_right']??'4px').' '.($o['inp_radius_bottom']??'4px').' '.($o['inp_radius_left']??'4px') ); ?>;
                                            border-color: <?php echo esc_attr( $o['inp_border_color'] ?? '#ccc' ); ?>;
                                        ">
                                    </div>
                                    <button type="button" class="zma-btn" style="
                                        background-color: <?php echo esc_attr($o['btn_bg'] ?? '#333'); ?>;
                                        color: <?php echo esc_attr($o['btn_text'] ?? '#fff'); ?>;
                                        padding: <?php echo esc_attr( ($o['btn_padding_top']??'12px').' '.($o['btn_padding_right']??'12px').' '.($o['btn_padding_bottom']??'12px').' '.($o['btn_padding_left']??'12px') ); ?>;
                                        border-radius: <?php echo esc_attr( ($o['btn_radius_top']??'4px').' '.($o['btn_radius_right']??'4px').' '.($o['btn_radius_bottom']??'4px').' '.($o['btn_radius_left']??'4px') ); ?>;
                                    "><?php esc_html_e( 'Send Code', 'zenith-mobile-auth' ); ?></button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </form>
        </div>
        <?php
    }

    public function render_user_profile_fields( $user ) {
        if ( ! current_user_can( 'edit_users' ) ) return;

        // Calculate current stats
        $phone = $user->user_login;
        $phone = preg_replace( '/\D/', '', $phone );
        if ( substr( $phone, 0, 2 ) === '98' ) $phone = '0' . substr( $phone, 2 );
        if ( strlen( $phone ) === 10 && substr( $phone, 0, 1 ) === '9' ) $phone = '0' . $phone;

        $daily_key = 'zma_limit_' . md5($phone . '_' . date('Y-m-d'));
        $daily_count = get_transient($daily_key) ?: 0;
        
        $settings = get_option('zma_settings');
        $daily_limit = isset($settings['daily_limit']) ? $settings['daily_limit'] : 10;
        
        $phone_hash = md5( $phone );
        $is_waiting = get_transient( 'zma_wait_' . $phone_hash );
        $attempts = get_transient( 'zma_otp_attempts_' . $phone_hash ) ?: 0;
        $max_attempts = isset($settings['max_attempts']) ? $settings['max_attempts'] : 3;

        ?>
        <h3><?php esc_html_e( 'Zenith Mobile Auth Status', 'zenith-mobile-auth' ); ?></h3>
        <table class="form-table">
            <tr>
                <th><?php esc_html_e( 'Daily SMS Usage', 'zenith-mobile-auth' ); ?></th>
                <td>
                    <span style="font-weight:bold; color: <?php echo ($daily_count >= $daily_limit) ? 'red' : 'green'; ?>">
                        <?php echo esc_html( $daily_count . ' / ' . $daily_limit ); ?>
                    </span>
                </td>
            </tr>
            <tr>
                <th><?php esc_html_e( 'Security Status', 'zenith-mobile-auth' ); ?></th>
                <td>
                    <?php if ( $is_waiting ) : ?>
                        <span class="dashicons dashicons-clock" style="color:orange; vertical-align:middle;"></span> 
                        <strong style="color:orange;"><?php esc_html_e( 'In Wait Time (Resend Blocked)', 'zenith-mobile-auth' ); ?></strong><br>
                    <?php endif; ?>
                    
                    <?php if ( $attempts >= $max_attempts ) : ?>
                        <span class="dashicons dashicons-no" style="color:red; vertical-align:middle;"></span> 
                        <strong style="color:red;"><?php esc_html_e( 'Blocked (Too many incorrect attempts)', 'zenith-mobile-auth' ); ?></strong>
                    <?php elseif ( $attempts > 0 ) : ?>
                        <span style="color:orange;"><?php printf( esc_html__( '%d Incorrect Attempts (Max %d)', 'zenith-mobile-auth' ), $attempts, $max_attempts ); ?></span>
                    <?php else: ?>
                        <span style="color:green;"><?php esc_html_e( 'Clean (No issues)', 'zenith-mobile-auth' ); ?></span>
                    <?php endif; ?>
                </td>
            </tr>
            <tr>
                <th><?php esc_html_e( 'Actions', 'zenith-mobile-auth' ); ?></th>
                <td>
                    <button type="button" id="zma_reset_limits" class="button button-secondary" data-user-id="<?php echo esc_attr($user->ID); ?>">
                        <?php esc_html_e( 'Unblock & Reset Limits', 'zenith-mobile-auth' ); ?>
                    </button>
                    <span id="zma_reset_msg" style="margin-left:10px; font-weight:bold;"></span>
                    <p class="description"><?php esc_html_e( 'This will reset the daily limit count, wait timer, and incorrect attempt counter for this user.', 'zenith-mobile-auth' ); ?></p>
                </td>
            </tr>
        </table>
        <?php
    }

    public function print_user_profile_js() {
        $screen = get_current_screen();
        if ( ! $screen || ! in_array( $screen->base, ['user-edit', 'profile'] ) ) return;
        ?>
        <script>
        jQuery(document).ready(function($) {
            $('#zma_reset_limits').on('click', function() {
                var btn = $(this);
                var userId = btn.data('user-id');
                var msg = $('#zma_reset_msg');
                
                btn.prop('disabled', true).text('<?php echo esc_js( __( 'Processing...', 'zenith-mobile-auth' ) ); ?>');
                msg.text('');

                $.post(ajaxurl, {
                    action: 'zma_reset_user_limits',
                    user_id: userId,
                    security: '<?php echo wp_create_nonce("zma_admin_nonce"); ?>'
                }, function(response) {
                    btn.prop('disabled', false).text('<?php echo esc_js( __( 'Unblock & Reset Limits', 'zenith-mobile-auth' ) ); ?>');
                    if (response.success) {
                        msg.css('color', 'green').text(response.data.message);
                        setTimeout(function(){ location.reload(); }, 1500);
                    } else {
                        msg.css('color', 'red').text(response.data.message);
                    }
                });
            });
        });
        </script>
        <?php
    }
}
