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
        
        add_action( 'show_user_profile', [ $this, 'render_admin_user_gender' ] );
        add_action( 'edit_user_profile', [ $this, 'render_admin_user_gender' ] );
        
        add_action( 'personal_options_update', [ $this, 'save_admin_user_gender' ] );
        add_action( 'edit_user_profile_update', [ $this, 'save_admin_user_gender' ] );

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
        add_options_page( __( 'Zenith Mobile Auth', 'zenith-mobile-auth' ), __( 'Mobile Auth (OTP)', 'zenith-mobile-auth' ), 'manage_options', 'zenith-mobile-auth', [ $this, 'create_admin_page' ] );
    }

    public function page_init() { register_setting( $this->option_group, $this->option_name ); }

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
                            <input type="text" name="zma_settings[<?php echo esc_attr($prefix.'_'.$side); ?>]" value="<?php echo esc_attr($val); ?>" class="zma-dim-input zma-live-dim" data-css-target="<?php echo esc_attr($css_target); ?>" data-css-prop="<?php echo esc_attr($css_prop); ?>" data-side="<?php echo esc_attr($side); ?>" placeholder="<?php echo esc_attr($side); ?>">
                            <label><?php echo substr(strtoupper($side), 0, 1); ?></label>
                        </div>
                    <?php endforeach; ?>
                </div>
                <button type="button" class="zma-link-btn <?php echo $is_linked === '1' ? 'active' : ''; ?>" title="<?php esc_attr_e( 'Link values together', 'zenith-mobile-auth' ); ?>"><span class="dashicons dashicons-admin-links"></span><span class="dashicons dashicons-editor-unlink"></span></button>
            </div>
        </div>
        <?php
    }

    public function create_admin_page() {
        $o = get_option( $this->option_name );
        $o = wp_parse_args( $o, [ 
            'active_gateway' => 'ippanel', 
            'api_key' => '', 'gateway_username' => '', 
            'style_btn_bg' => '#333333', 'style_btn_text' => '#ffffff', 
            'style_container_bg' => '#ffffff', 'style_input_border' => '#dddddd', 'style_container_border' => '#e5e5e5', 
            'title_font_size' => '20px', 'title_color' => '#333333', 'title_margin' => '10px', 
            'desc_font_size' => '14px', 'desc_color' => '#666666', 'desc_margin' => '20px', 
            'max_attempts' => '3', 'daily_limit' => '10', 
            'enable_name_field' => '0', 'allow_skip_name' => '0', 'enable_gender_field' => '0',
            'label_font_size' => '14px', 'label_color' => '#333333', 'input_font_size' => '16px',
            'link_font_size' => '12px', 'link_color' => '#666666',
            'enable_welcome' => '0', 'welcome_pattern' => ''
        ]);

        $gateways = ZMA_Gateway_Manager::get_all();
        ?>
        <div class="zma-admin-wrapper">
            <div class="zma-admin-header"><h1><?php esc_html_e('Zenith Mobile Auth', 'zenith-mobile-auth'); ?></h1><p><?php esc_html_e('Advanced OTP Authentication for WooCommerce', 'zenith-mobile-auth'); ?></p></div>
            <form method="post" action="options.php" id="zma-main-form">
                <?php settings_fields( $this->option_group ); ?>
                <div class="zma-admin-body">
                    <div class="zma-sidebar">
                        <ul class="zma-nav">
                            <li class="active" data-tab="general"><span class="dashicons dashicons-admin-settings"></span> <?php esc_html_e('General', 'zenith-mobile-auth'); ?></li>
                            <li data-tab="registration"><span class="dashicons dashicons-id-alt"></span> <?php esc_html_e('Registration', 'zenith-mobile-auth'); ?></li>
                            <li data-tab="container"><span class="dashicons dashicons-layout"></span> <?php esc_html_e('Container Style', 'zenith-mobile-auth'); ?></li>
                            <li data-tab="texts"><span class="dashicons dashicons-text-page"></span> <?php esc_html_e('Typography', 'zenith-mobile-auth'); ?></li>
                            <li data-tab="inputs"><span class="dashicons dashicons-edit"></span> <?php esc_html_e('Inputs & Button', 'zenith-mobile-auth'); ?></li>
                            <li data-tab="security"><span class="dashicons dashicons-shield"></span> <?php esc_html_e('Security', 'zenith-mobile-auth'); ?></li>
                        </ul>
                        <div class="zma-save-area"><?php submit_button( __( 'Save Changes', 'zenith-mobile-auth' ), 'primary large zma-save-btn' ); ?></div>
                    </div>
                    <div class="zma-content">
                        <!-- TAB: General -->
                        <div id="tab-general" class="zma-tab-content active">
                            <h2><?php esc_html_e('SMS Gateway', 'zenith-mobile-auth'); ?></h2>
                            <div class="zma-form-group">
                                <label><?php esc_html_e('Select Provider', 'zenith-mobile-auth'); ?></label>
                                <select name="zma_settings[active_gateway]" class="widefat">
                                    <?php foreach($gateways as $id => $gw): ?>
                                        <option value="<?php echo esc_attr($id); ?>" <?php selected($o['active_gateway'], $id); ?>>
                                            <?php echo esc_html($gw->get_name()); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <hr>
                            <h3><?php esc_html_e('Configuration', 'zenith-mobile-auth'); ?></h3>
                            <div class="zma-form-group"><label><?php esc_html_e('API Key / Token', 'zenith-mobile-auth'); ?></label><input type="text" name="zma_settings[api_key]" value="<?php echo esc_attr( $o['api_key'] ); ?>" class="widefat"></div>
                            <div class="zma-form-group"><label><?php esc_html_e('Username (Optional)', 'zenith-mobile-auth'); ?></label><input type="text" name="zma_settings[gateway_username]" value="<?php echo esc_attr( $o['gateway_username'] ); ?>" class="widefat"><p class="desc"><?php esc_html_e('Required only for some gateways.', 'zenith-mobile-auth'); ?></p></div>
                            <div class="zma-form-group"><label><?php esc_html_e('Originator', 'zenith-mobile-auth'); ?></label><input type="text" name="zma_settings[originator]" value="<?php echo esc_attr( $o['originator'] ?? '' ); ?>" class="widefat"></div>
                            <div class="zma-form-group"><label><?php esc_html_e('Pattern Code (OTP)', 'zenith-mobile-auth'); ?></label><input type="text" name="zma_settings[pattern_code]" value="<?php echo esc_attr( $o['pattern_code'] ?? '' ); ?>" class="widefat"></div>
                            <div class="zma-form-group"><label><?php esc_html_e('Variable Name', 'zenith-mobile-auth'); ?></label><input type="text" name="zma_settings[otp_variable]" value="<?php echo esc_attr( $o['otp_variable'] ?? 'code' ); ?>" class="widefat"></div>
                        </div>
                        
                        <!-- TAB: Registration -->
                        <div id="tab-registration" class="zma-tab-content">
                            <h2><?php esc_html_e('Registration Information', 'zenith-mobile-auth'); ?></h2>
                            <div class="zma-form-group"><label><input type="checkbox" name="zma_settings[enable_name_field]" value="1" <?php checked($o['enable_name_field'], '1'); ?>> <?php esc_html_e('Ask for Name & Family Name', 'zenith-mobile-auth'); ?></label></div>
                            <div class="zma-form-group"><label><input type="checkbox" name="zma_settings[allow_skip_name]" value="1" <?php checked($o['allow_skip_name'], '1'); ?>> <?php esc_html_e('Allow Skipping', 'zenith-mobile-auth'); ?></label></div>
                            <div class="zma-form-group"><label><input type="checkbox" name="zma_settings[enable_gender_field]" value="1" <?php checked($o['enable_gender_field'], '1'); ?>> <?php esc_html_e('Enable Gender Field', 'zenith-mobile-auth'); ?></label></div>
                            
                            <hr>
                            <h3><?php esc_html_e('Welcome Message', 'zenith-mobile-auth'); ?></h3>
                            <div class="zma-form-group">
                                <label><input type="checkbox" id="zma_enable_welcome" name="zma_settings[enable_welcome]" value="1" <?php checked($o['enable_welcome'], '1'); ?> data-toggle-target="#zma_welcome_pattern_wrapper"> <?php esc_html_e('Send Welcome SMS', 'zenith-mobile-auth'); ?></label>
                                <p class="desc"><?php esc_html_e('Send a message after user fills their name/profile info.', 'zenith-mobile-auth'); ?></p>
                            </div>
                            <div class="zma-form-group" id="zma_welcome_pattern_wrapper" style="<?php echo ($o['enable_welcome'] !== '1') ? 'display:none;' : ''; ?>">
                                <label><?php esc_html_e('Welcome Pattern Code', 'zenith-mobile-auth'); ?></label>
                                <input type="text" name="zma_settings[welcome_pattern]" value="<?php echo esc_attr($o['welcome_pattern']); ?>" class="widefat">
                                <p class="desc"><?php esc_html_e('Pattern code for welcome message. Use %name% variable for Full Name.', 'zenith-mobile-auth'); ?></p>
                            </div>
                        </div>

                        <!-- TAB: Container Style -->
                        <div id="tab-container" class="zma-tab-content"><h2><?php esc_html_e('Container Box Style', 'zenith-mobile-auth'); ?></h2><div class="zma-card"><h3><?php esc_html_e('Background & Dimensions', 'zenith-mobile-auth'); ?></h3><div class="zma-row"><div class="zma-col"><label><?php esc_html_e('Max Width', 'zenith-mobile-auth'); ?></label><input type="text" class="zma-live-css" data-css-target=".zma-container" data-css-prop="max-width" name="zma_settings[con_width]" value="<?php echo esc_attr( $o['con_width'] ?? '400px' ); ?>"></div><div class="zma-col"><label><?php esc_html_e('Background Color', 'zenith-mobile-auth'); ?></label><input type="text" class="zma-color-picker zma-live-css" data-css-target=".zma-container" data-css-prop="background-color" name="zma_settings[con_bg]" value="<?php echo esc_attr( $o['con_bg'] ?? '#ffffff' ); ?>"></div></div></div><div class="zma-card"><h3><?php esc_html_e('Padding & Radius', 'zenith-mobile-auth'); ?></h3><?php $this->render_dimensions(__('Container Padding', 'zenith-mobile-auth'), 'con_padding', $o, '.zma-container', 'padding', '20px'); $this->render_dimensions(__('Border Radius', 'zenith-mobile-auth'), 'con_radius', $o, '.zma-container', 'border-radius', '8px'); ?></div><div class="zma-card"><h3><?php esc_html_e('Border', 'zenith-mobile-auth'); ?></h3><div class="zma-row"><div class="zma-col"><label><?php esc_html_e('Style', 'zenith-mobile-auth'); ?></label><select class="zma-live-css" data-css-target=".zma-container" data-css-prop="border-style" name="zma_settings[con_border_style]"><option value="solid" <?php selected($o['con_border_style'] ?? 'solid', 'solid'); ?>><?php esc_html_e('Solid', 'zenith-mobile-auth'); ?></option><option value="none" <?php selected($o['con_border_style'] ?? 'solid', 'none'); ?>><?php esc_html_e('None', 'zenith-mobile-auth'); ?></option></select></div><div class="zma-col"><label><?php esc_html_e('Color', 'zenith-mobile-auth'); ?></label><input type="text" class="zma-color-picker zma-live-css" data-css-target=".zma-container" data-css-prop="border-color" name="zma_settings[con_border_color]" value="<?php echo esc_attr( $o['con_border_color'] ?? '#e5e5e5' ); ?>"></div></div><?php $this->render_dimensions(__('Border Width', 'zenith-mobile-auth'), 'con_border_w', $o, '.zma-container', 'border-width', '1px'); ?></div></div>
                        
                        <!-- TAB: Texts -->
                        <div id="tab-texts" class="zma-tab-content"><h2><?php esc_html_e('Typography & Texts', 'zenith-mobile-auth'); ?></h2><div class="zma-card"><h3><?php esc_html_e('Form Title', 'zenith-mobile-auth'); ?></h3><div class="zma-form-group"><label><?php esc_html_e('Text', 'zenith-mobile-auth'); ?></label><input type="text" class="widefat zma-live-text" data-target="#zma-preview-title" name="zma_settings[form_title]" value="<?php echo esc_attr( $o['form_title'] ?? 'Login or Register' ); ?>"></div><div class="zma-row"><div class="zma-col"><label><?php esc_html_e('Font Size', 'zenith-mobile-auth'); ?></label><input type="text" class="zma-live-css" data-css-target="#zma-preview-title" data-css-prop="font-size" name="zma_settings[title_font_size]" value="<?php echo esc_attr( $o['title_font_size'] ?? '20px' ); ?>"></div><div class="zma-col"><label><?php esc_html_e('Color', 'zenith-mobile-auth'); ?></label><input type="text" class="zma-color-picker zma-live-css" data-css-target="#zma-preview-title" data-css-prop="color" name="zma_settings[title_color]" value="<?php echo esc_attr( $o['title_color'] ?? '#333333' ); ?>"></div><div class="zma-col"><label><?php esc_html_e('Margin Bottom', 'zenith-mobile-auth'); ?></label><input type="text" class="zma-live-css" data-css-target="#zma-preview-title" data-css-prop="margin-bottom" name="zma_settings[title_margin]" value="<?php echo esc_attr( $o['title_margin'] ?? '10px' ); ?>"></div></div></div><div class="zma-card"><h3><?php esc_html_e('Form Description', 'zenith-mobile-auth'); ?></h3><div class="zma-form-group"><label><?php esc_html_e('Text', 'zenith-mobile-auth'); ?></label><textarea class="widefat zma-live-text" data-target="#zma-preview-desc" name="zma_settings[form_desc]" rows="2"><?php echo esc_textarea( $o['form_desc'] ?? 'We will send an OTP code to your mobile number.' ); ?></textarea></div><div class="zma-row"><div class="zma-col"><label><?php esc_html_e('Font Size', 'zenith-mobile-auth'); ?></label><input type="text" class="zma-live-css" data-css-target="#zma-preview-desc" data-css-prop="font-size" name="zma_settings[desc_font_size]" value="<?php echo esc_attr( $o['desc_font_size'] ?? '14px' ); ?>"></div><div class="zma-col"><label><?php esc_html_e('Color', 'zenith-mobile-auth'); ?></label><input type="text" class="zma-color-picker zma-live-css" data-css-target="#zma-preview-desc" data-css-prop="color" name="zma_settings[desc_color]" value="<?php echo esc_attr( $o['desc_color'] ?? '#666666' ); ?>"></div><div class="zma-col"><label><?php esc_html_e('Margin Bottom', 'zenith-mobile-auth'); ?></label><input type="text" class="zma-live-css" data-css-target="#zma-preview-desc" data-css-prop="margin-bottom" name="zma_settings[desc_margin]" value="<?php echo esc_attr( $o['desc_margin'] ?? '20px' ); ?>"></div></div></div></div>
                        
                        <!-- TAB: Inputs -->
                        <div id="tab-inputs" class="zma-tab-content">
                            <h2><?php esc_html_e('Elements Style', 'zenith-mobile-auth'); ?></h2>
                            <div class="zma-card">
                                <h3><?php esc_html_e('Input Fields', 'zenith-mobile-auth'); ?></h3>
                                <div class="zma-row">
                                    <div class="zma-col"><label><?php esc_html_e('Font Size', 'zenith-mobile-auth'); ?></label><input type="text" class="zma-live-css" data-css-target=".zma-input" data-css-prop="font-size" name="zma_settings[input_font_size]" value="<?php echo esc_attr( $o['input_font_size'] ?? '16px' ); ?>"></div>
                                    <div class="zma-col"><label><?php esc_html_e('Border Color', 'zenith-mobile-auth'); ?></label><input type="text" class="zma-color-picker zma-live-css" data-css-target=".zma-input" data-css-prop="border-color" name="zma_settings[inp_border_color]" value="<?php echo esc_attr( $o['inp_border_color'] ?? '#cccccc' ); ?>"></div>
                                </div>
                                <div class="zma-row">
                                    <div class="zma-col"><label><?php esc_html_e('Label Font Size', 'zenith-mobile-auth'); ?></label><input type="text" class="zma-live-css" data-css-target=".zma-input-group label" data-css-prop="font-size" name="zma_settings[label_font_size]" value="<?php echo esc_attr( $o['label_font_size'] ?? '14px' ); ?>"></div>
                                    <div class="zma-col"><label><?php esc_html_e('Label Color', 'zenith-mobile-auth'); ?></label><input type="text" class="zma-color-picker zma-live-css" data-css-target=".zma-input-group label" data-css-prop="color" name="zma_settings[label_color]" value="<?php echo esc_attr( $o['label_color'] ?? '#333333' ); ?>"></div>
                                </div>
                                <?php 
                                $this->render_dimensions(__('Padding', 'zenith-mobile-auth'), 'inp_padding', $o, '.zma-input', 'padding', '10px'); 
                                $this->render_dimensions(__('Border Radius', 'zenith-mobile-auth'), 'inp_radius', $o, '.zma-input', 'border-radius', '4px'); 
                                ?>
                            </div>
                            
                            <div class="zma-card">
                                <h3><?php esc_html_e('Submit Button', 'zenith-mobile-auth'); ?></h3>
                                <div class="zma-row"><div class="zma-col"><label><?php esc_html_e('Background', 'zenith-mobile-auth'); ?></label><input type="text" class="zma-color-picker zma-live-css" data-css-target=".zma-btn" data-css-prop="background-color" name="zma_settings[btn_bg]" value="<?php echo esc_attr( $o['btn_bg'] ?? '#333333' ); ?>"></div><div class="zma-col"><label><?php esc_html_e('Text Color', 'zenith-mobile-auth'); ?></label><input type="text" class="zma-color-picker zma-live-css" data-css-target=".zma-btn" data-css-prop="color" name="zma_settings[btn_text]" value="<?php echo esc_attr( $o['btn_text'] ?? '#ffffff' ); ?>"></div></div><?php $this->render_dimensions(__('Padding', 'zenith-mobile-auth'), 'btn_padding', $o, '.zma-btn', 'padding', '12px'); $this->render_dimensions(__('Border Radius', 'zenith-mobile-auth'), 'btn_radius', $o, '.zma-btn', 'border-radius', '4px'); ?>
                            </div>

                            <div class="zma-card">
                                <h3><?php esc_html_e('Links & Texts', 'zenith-mobile-auth'); ?></h3>
                                <div class="zma-row">
                                    <div class="zma-col"><label><?php esc_html_e('Link Font Size', 'zenith-mobile-auth'); ?></label><input type="text" class="zma-live-css" data-css-target=".zma-text-btn, .zma-change-number" data-css-prop="font-size" name="zma_settings[link_font_size]" value="<?php echo esc_attr( $o['link_font_size'] ?? '12px' ); ?>"></div>
                                    <div class="zma-col"><label><?php esc_html_e('Link Color', 'zenith-mobile-auth'); ?></label><input type="text" class="zma-color-picker zma-live-css" data-css-target=".zma-text-btn, .zma-change-number" data-css-prop="color" name="zma_settings[link_color]" value="<?php echo esc_attr( $o['link_color'] ?? '#666666' ); ?>"></div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- TAB: Security -->
                        <div id="tab-security" class="zma-tab-content"><h2><?php esc_html_e('Security & Constraints', 'zenith-mobile-auth'); ?></h2><div class="zma-form-group"><label><?php esc_html_e('OTP Code Length', 'zenith-mobile-auth'); ?></label><input type="number" name="zma_settings[otp_length]" value="<?php echo esc_attr( $o['otp_length'] ?? 4 ); ?>" min="4" max="6" class="small-text"></div><div class="zma-form-group"><label><?php esc_html_e('Resend Timer (Seconds)', 'zenith-mobile-auth'); ?></label><input type="number" name="zma_settings[resend_time]" value="<?php echo esc_attr( $o['resend_time'] ?? 120 ); ?>" class="small-text"></div><div class="zma-form-group"><label><?php esc_html_e('Daily SMS Limit (Per Phone)', 'zenith-mobile-auth'); ?></label><input type="number" name="zma_settings[daily_limit]" value="<?php echo esc_attr( $o['daily_limit'] ?? 10 ); ?>" class="small-text"></div><div class="zma-form-group"><label><?php esc_html_e('Max Incorrect Attempts', 'zenith-mobile-auth'); ?></label><input type="number" name="zma_settings[max_attempts]" value="<?php echo esc_attr( $o['max_attempts'] ?? 3 ); ?>" class="small-text"></div></div>
                    </div>
                    
                    <!-- Preview -->
                    <div class="zma-preview-pane">
                        <div class="zma-preview-sticky">
                            <h3><?php esc_html_e('Live Preview', 'zenith-mobile-auth'); ?></h3>
                            <div class="zma-preview-box">
                                <div class="zma-container" style="max-width: <?php echo esc_attr($o['con_width'] ?? '400px'); ?>; background-color: <?php echo esc_attr($o['con_bg'] ?? '#fff'); ?>; padding: <?php echo esc_attr( ($o['con_padding_top']??'20px').' '.($o['con_padding_right']??'20px').' '.($o['con_padding_bottom']??'20px').' '.($o['con_padding_left']??'20px') ); ?>; border-radius: <?php echo esc_attr( ($o['con_radius_top']??'8px').' '.($o['con_radius_right']??'8px').' '.($o['con_radius_bottom']??'8px').' '.($o['con_radius_left']??'8px') ); ?>; border-width: <?php echo esc_attr( ($o['con_border_w_top']??'1px').' '.($o['con_border_w_right']??'1px').' '.($o['con_border_w_bottom']??'1px').' '.($o['con_border_w_left']??'1px') ); ?>; border-style: <?php echo esc_attr($o['con_border_style'] ?? 'solid'); ?>; border-color: <?php echo esc_attr($o['con_border_color'] ?? '#e5e5e5'); ?>;">
                                    <div class="zma-header"><h3 id="zma-preview-title" style="font-size: <?php echo esc_attr($o['title_font_size'] ?? '20px'); ?>; color: <?php echo esc_attr($o['title_color'] ?? '#333'); ?>; margin-bottom: <?php echo esc_attr($o['title_margin'] ?? '10px'); ?>;"><?php esc_html_e( 'Login or Register', 'zenith-mobile-auth' ); ?></h3><p id="zma-preview-desc" style="font-size: <?php echo esc_attr($o['desc_font_size'] ?? '14px'); ?>; color: <?php echo esc_attr($o['desc_color'] ?? '#666'); ?>; margin-bottom: <?php echo esc_attr($o['desc_margin'] ?? '20px'); ?>;"><?php esc_html_e( 'We will send an OTP code to your mobile number.', 'zenith-mobile-auth' ); ?></p></div>
                                    <div class="zma-input-group" style="margin-bottom: <?php echo esc_attr($o['inp_margin'] ?? '15px'); ?>;"><label style="font-size:<?php echo esc_attr($o['label_font_size']); ?>; color:<?php echo esc_attr($o['label_color']); ?>;"><?php esc_html_e( 'Phone Number', 'zenith-mobile-auth' ); ?></label><input type="tel" class="zma-input" placeholder="0912..." disabled style="font-size:<?php echo esc_attr($o['input_font_size']); ?>; padding: <?php echo esc_attr( ($o['inp_padding_top']??'10px').' '.($o['inp_padding_right']??'10px').' '.($o['inp_padding_bottom']??'10px').' '.($o['inp_padding_left']??'10px') ); ?>; border-radius: <?php echo esc_attr( ($o['inp_radius_top']??'4px').' '.($o['inp_radius_right']??'4px').' '.($o['inp_radius_bottom']??'4px').' '.($o['inp_radius_left']??'4px') ); ?>; border-color: <?php echo esc_attr( $o['inp_border_color'] ?? '#ccc' ); ?>;"></div>
                                    <button type="button" class="zma-btn" style="background-color: <?php echo esc_attr($o['btn_bg'] ?? '#333'); ?>; color: <?php echo esc_attr($o['btn_text'] ?? '#fff'); ?>; padding: <?php echo esc_attr( ($o['btn_padding_top']??'12px').' '.($o['btn_padding_right']??'12px').' '.($o['btn_padding_bottom']??'12px').' '.($o['btn_padding_left']??'12px') ); ?>; border-radius: <?php echo esc_attr( ($o['btn_radius_top']??'4px').' '.($o['btn_radius_right']??'4px').' '.($o['btn_radius_bottom']??'4px').' '.($o['btn_radius_left']??'4px') ); ?>;"><?php esc_html_e( 'Send Code', 'zenith-mobile-auth' ); ?></button>
                                    <div style="text-align:center; font-size:12px; margin-top:10px;"><a href="#" class="zma-change-number" style="font-size:<?php echo esc_attr($o['link_font_size']); ?>; color:<?php echo esc_attr($o['link_color']); ?>;"><?php esc_html_e( 'Change Number', 'zenith-mobile-auth' ); ?></a></div>
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
        <table class="form-table"><tr><th><?php esc_html_e( 'Daily SMS Usage', 'zenith-mobile-auth' ); ?></th><td><span style="font-weight:bold; color: <?php echo ($daily_count >= $daily_limit) ? 'red' : 'green'; ?>;"><?php echo esc_html( $daily_count . ' / ' . $daily_limit ); ?></span></td></tr><tr><th><?php esc_html_e( 'Security Status', 'zenith-mobile-auth' ); ?></th><td><?php if ( $is_waiting ) : ?><span class="dashicons dashicons-clock" style="color:orange;"></span> <strong style="color:orange;"><?php esc_html_e( 'In Wait Time', 'zenith-mobile-auth' ); ?></strong><br><?php endif; ?><?php if ( $attempts >= $max_attempts ) : ?><span class="dashicons dashicons-no" style="color:red;"></span> <strong style="color:red;"><?php esc_html_e( 'Blocked', 'zenith-mobile-auth' ); ?></strong><?php elseif ( $attempts > 0 ) : ?><span style="color:orange;"><?php printf( esc_html__( '%d Incorrect Attempts', 'zenith-mobile-auth' ), $attempts ); ?></span><?php else: ?><span style="color:green;"><?php esc_html_e( 'Clean', 'zenith-mobile-auth' ); ?></span><?php endif; ?></td></tr><tr><th><?php esc_html_e( 'Actions', 'zenith-mobile-auth' ); ?></th><td><button type="button" id="zma_reset_limits" class="button button-secondary" data-user-id="<?php echo esc_attr($user->ID); ?>"><?php esc_html_e( 'Unblock & Reset Limits', 'zenith-mobile-auth' ); ?></button><span id="zma_reset_msg" style="margin-left:10px;"></span></td></tr></table>
        <?php
    }

    public function render_admin_user_gender( $user ) {
        if ( ! current_user_can( 'edit_users' ) ) return;
        $gender = get_user_meta( $user->ID, 'gender', true );
        ?>
        <table class="form-table zma-gender-table-row" style="display:none;"><tr class="zma-gender-row"><th><label><?php esc_html_e( 'Gender', 'zenith-mobile-auth' ); ?></label></th><td><fieldset><label style="margin-right: 15px;"><input type="radio" name="zma_gender" value="male" <?php checked( $gender, 'male' ); ?>> <?php esc_html_e( 'Male', 'zenith-mobile-auth' ); ?></label><label><input type="radio" name="zma_gender" value="female" <?php checked( $gender, 'female' ); ?>> <?php esc_html_e( 'Female', 'zenith-mobile-auth' ); ?></label></fieldset></td></tr></table>
        <script>jQuery(document).ready(function($) { var $roleRow = $('.user-role-wrap'); if ($roleRow.length) { $('.zma-gender-row').insertAfter($roleRow); $('.zma-gender-table-row').remove(); } else { $('.zma-gender-table-row').show(); } });</script>
        <?php
    }

    public function save_admin_user_gender( $user_id ) {
        if ( ! current_user_can( 'edit_user', $user_id ) ) return false;
        if ( isset( $_POST['zma_gender'] ) ) update_user_meta( $user_id, 'gender', sanitize_text_field( $_POST['zma_gender'] ) );
    }

    public function print_user_profile_js() {
        $screen = get_current_screen();
        if ( ! $screen || ! in_array( $screen->base, ['user-edit', 'profile'] ) ) return;
        ?>
        <script>
        jQuery(document).ready(function($) {
            $('#zma_reset_limits').on('click', function() {
                var btn = $(this); var userId = btn.data('user-id'); var msg = $('#zma_reset_msg');
                btn.prop('disabled', true).text('Processing...'); msg.text('');
                $.post(ajaxurl, { action: 'zma_reset_user_limits', user_id: userId, security: '<?php echo wp_create_nonce("zma_admin_nonce"); ?>' }, function(response) {
                    btn.prop('disabled', false).text('Unblock & Reset Limits');
                    if (response.success) { msg.css('color', 'green').text(response.data.message); setTimeout(function(){ location.reload(); }, 1500); } else { msg.css('color', 'red').text(response.data.message); }
                });
            });
        });
        </script>
        <?php
    }
}