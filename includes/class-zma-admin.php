<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class Zenith_Mobile_Auth_Admin {

    private $option_group = 'zma_plugin_options';
    private $option_name  = 'zma_settings';

    public function __construct() {
        add_action( 'admin_menu', [ $this, 'add_plugin_page' ] );
        add_action( 'admin_init', [ $this, 'page_init' ] );
        add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_admin_assets' ] );
        
        // Hooks
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

    // (render_dimensions helper omitted for brevity - same as previous)
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
                <button type="button" class="zma-link-btn <?php echo $is_linked === '1' ? 'active' : ''; ?>" title="Link values together"><span class="dashicons dashicons-admin-links"></span><span class="dashicons dashicons-editor-unlink"></span></button>
            </div>
        </div>
        <?php
    }

    public function create_admin_page() {
        $o = get_option( $this->option_name );
        // Defaults
        $o = wp_parse_args( $o, [ 
            'active_gateway' => 'ippanel', 
            'api_key' => '', 'style_btn_bg' => '#333333', 'style_btn_text' => '#ffffff', 
            'style_container_bg' => '#ffffff', 'style_input_border' => '#dddddd', 'style_container_border' => '#e5e5e5', 
            'title_font_size' => '20px', 'title_color' => '#333333', 'title_margin' => '10px', 
            'desc_font_size' => '14px', 'desc_color' => '#666666', 'desc_margin' => '20px', 
            'max_attempts' => '3', 'daily_limit' => '10', 'enable_name_field' => '0', 
            'allow_skip_name' => '0', 'enable_gender_field' => '0' 
        ]);

        $gateways = ZMA_Gateway_Manager::get_all();
        ?>
        <div class="zma-admin-wrapper">
            <div class="zma-admin-header"><h1>Zenith Mobile Auth</h1><p>Advanced OTP Authentication for WooCommerce</p></div>
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
                        <div class="zma-save-area"><?php submit_button( 'Save Changes', 'primary large zma-save-btn' ); ?></div>
                    </div>
                    <div class="zma-content">
                        <!-- TAB: General -->
                        <div id="tab-general" class="zma-tab-content active">
                            <h2>SMS Gateway</h2>
                            <div class="zma-form-group">
                                <label>Select Provider</label>
                                <select name="zma_settings[active_gateway]" class="widefat">
                                    <?php foreach($gateways as $id => $gw): ?>
                                        <option value="<?php echo esc_attr($id); ?>" <?php selected($o['active_gateway'], $id); ?>>
                                            <?php echo esc_html($gw->get_name()); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <hr>
                            <h3>Configuration</h3>
                            <div class="zma-form-group"><label>API Key / Token</label><input type="text" name="zma_settings[api_key]" value="<?php echo esc_attr( $o['api_key'] ); ?>" class="widefat"></div>
                            <div class="zma-form-group"><label>Originator</label><input type="text" name="zma_settings[originator]" value="<?php echo esc_attr( $o['originator'] ?? '' ); ?>" class="widefat"></div>
                            <div class="zma-form-group"><label>Pattern Code</label><input type="text" name="zma_settings[pattern_code]" value="<?php echo esc_attr( $o['pattern_code'] ?? '' ); ?>" class="widefat"></div>
                            <div class="zma-form-group"><label>Variable Name</label><input type="text" name="zma_settings[otp_variable]" value="<?php echo esc_attr( $o['otp_variable'] ?? 'code' ); ?>" class="widefat"></div>
                        </div>
                        
                        <!-- (Rest of tabs unchanged: registration, container, texts, inputs, security) -->
                        <div id="tab-registration" class="zma-tab-content"><h2>Registration Information</h2><div class="zma-form-group"><label><input type="checkbox" name="zma_settings[enable_name_field]" value="1" <?php checked($o['enable_name_field'], '1'); ?>> Ask for Name & Family Name</label></div><div class="zma-form-group"><label><input type="checkbox" name="zma_settings[allow_skip_name]" value="1" <?php checked($o['allow_skip_name'], '1'); ?>> Allow Skipping</label></div><div class="zma-form-group"><label><input type="checkbox" name="zma_settings[enable_gender_field]" value="1" <?php checked($o['enable_gender_field'], '1'); ?>> Enable Gender Field</label></div></div>
                        <div id="tab-container" class="zma-tab-content"><h2>Container Box Style</h2><div class="zma-card"><h3>Background & Dimensions</h3><div class="zma-row"><div class="zma-col"><label>Max Width</label><input type="text" class="zma-live-css" data-css-target=".zma-container" data-css-prop="max-width" name="zma_settings[con_width]" value="<?php echo esc_attr( $o['con_width'] ?? '400px' ); ?>"></div><div class="zma-col"><label>Background Color</label><input type="text" class="zma-color-picker zma-live-css" data-css-target=".zma-container" data-css-prop="background-color" name="zma_settings[con_bg]" value="<?php echo esc_attr( $o['con_bg'] ?? '#ffffff' ); ?>"></div></div></div><div class="zma-card"><h3>Padding & Radius</h3><?php $this->render_dimensions('Container Padding', 'con_padding', $o, '.zma-container', 'padding', '20px'); $this->render_dimensions('Border Radius', 'con_radius', $o, '.zma-container', 'border-radius', '8px'); ?></div><div class="zma-card"><h3>Border</h3><div class="zma-row"><div class="zma-col"><label>Style</label><select class="zma-live-css" data-css-target=".zma-container" data-css-prop="border-style" name="zma_settings[con_border_style]"><option value="solid" <?php selected($o['con_border_style'] ?? 'solid', 'solid'); ?>>Solid</option><option value="none" <?php selected($o['con_border_style'] ?? 'solid', 'none'); ?>>None</option></select></div><div class="zma-col"><label>Color</label><input type="text" class="zma-color-picker zma-live-css" data-css-target=".zma-container" data-css-prop="border-color" name="zma_settings[con_border_color]" value="<?php echo esc_attr( $o['con_border_color'] ?? '#e5e5e5' ); ?>"></div></div><?php $this->render_dimensions('Border Width', 'con_border_w', $o, '.zma-container', 'border-width', '1px'); ?></div></div>
                        <div id="tab-texts" class="zma-tab-content"><h2>Typography & Texts</h2><div class="zma-card"><h3>Form Title</h3><div class="zma-form-group"><label>Text</label><input type="text" class="widefat zma-live-text" data-target="#zma-preview-title" name="zma_settings[form_title]" value="<?php echo esc_attr( $o['form_title'] ?? 'Login or Register' ); ?>"></div><div class="zma-row"><div class="zma-col"><label>Font Size</label><input type="text" class="zma-live-css" data-css-target="#zma-preview-title" data-css-prop="font-size" name="zma_settings[title_font_size]" value="<?php echo esc_attr( $o['title_font_size'] ?? '20px' ); ?>"></div><div class="zma-col"><label>Color</label><input type="text" class="zma-color-picker zma-live-css" data-css-target="#zma-preview-title" data-css-prop="color" name="zma_settings[title_color]" value="<?php echo esc_attr( $o['title_color'] ?? '#333333' ); ?>"></div><div class="zma-col"><label>Margin Bottom</label><input type="text" class="zma-live-css" data-css-target="#zma-preview-title" data-css-prop="margin-bottom" name="zma_settings[title_margin]" value="<?php echo esc_attr( $o['title_margin'] ?? '10px' ); ?>"></div></div></div><div class="zma-card"><h3>Form Description</h3><div class="zma-form-group"><label>Text</label><textarea class="widefat zma-live-text" data-target="#zma-preview-desc" name="zma_settings[form_desc]" rows="2"><?php echo esc_textarea( $o['form_desc'] ?? 'We will send an OTP code to your mobile number.' ); ?></textarea></div><div class="zma-row"><div class="zma-col"><label>Font Size</label><input type="text" class="zma-live-css" data-css-target="#zma-preview-desc" data-css-prop="font-size" name="zma_settings[desc_font_size]" value="<?php echo esc_attr( $o['desc_font_size'] ?? '14px' ); ?>"></div><div class="zma-col"><label>Color</label><input type="text" class="zma-color-picker zma-live-css" data-css-target="#zma-preview-desc" data-css-prop="color" name="zma_settings[desc_color]" value="<?php echo esc_attr( $o['desc_color'] ?? '#666666' ); ?>"></div><div class="zma-col"><label>Margin Bottom</label><input type="text" class="zma-live-css" data-css-target="#zma-preview-desc" data-css-prop="margin-bottom" name="zma_settings[desc_margin]" value="<?php echo esc_attr( $o['desc_margin'] ?? '20px' ); ?>"></div></div></div></div>
                        <div id="tab-inputs" class="zma-tab-content"><h2>Elements Style</h2><div class="zma-card"><h3>Input Fields</h3><?php $this->render_dimensions('Padding', 'inp_padding', $o, '.zma-input', 'padding', '10px'); $this->render_dimensions('Border Radius', 'inp_radius', $o, '.zma-input', 'border-radius', '4px'); ?><div class="zma-row" style="margin-top:15px;"><div class="zma-col"><label>Border Color</label><input type="text" class="zma-color-picker zma-live-css" data-css-target=".zma-input" data-css-prop="border-color" name="zma_settings[inp_border_color]" value="<?php echo esc_attr( $o['inp_border_color'] ?? '#cccccc' ); ?>"></div><div class="zma-col"><label>Bottom Spacing</label><input type="text" class="zma-live-css" data-css-target=".zma-input-group" data-css-prop="margin-bottom" name="zma_settings[inp_margin]" value="<?php echo esc_attr( $o['inp_margin'] ?? '15px' ); ?>"></div></div></div><div class="zma-card"><h3>Submit Button</h3><div class="zma-row"><div class="zma-col"><label>Background</label><input type="text" class="zma-color-picker zma-live-css" data-css-target=".zma-btn" data-css-prop="background-color" name="zma_settings[btn_bg]" value="<?php echo esc_attr( $o['btn_bg'] ?? '#333333' ); ?>"></div><div class="zma-col"><label>Text Color</label><input type="text" class="zma-color-picker zma-live-css" data-css-target=".zma-btn" data-css-prop="color" name="zma_settings[btn_text]" value="<?php echo esc_attr( $o['btn_text'] ?? '#ffffff' ); ?>"></div></div><?php $this->render_dimensions('Padding', 'btn_padding', $o, '.zma-btn', 'padding', '12px'); $this->render_dimensions('Border Radius', 'btn_radius', $o, '.zma-btn', 'border-radius', '4px'); ?></div></div>
                        <div id="tab-security" class="zma-tab-content"><h2>Security & Constraints</h2><div class="zma-form-group"><label>OTP Code Length</label><input type="number" name="zma_settings[otp_length]" value="<?php echo esc_attr( $o['otp_length'] ?? 4 ); ?>" min="4" max="6" class="small-text"></div><div class="zma-form-group"><label>Resend Timer (Seconds)</label><input type="number" name="zma_settings[resend_time]" value="<?php echo esc_attr( $o['resend_time'] ?? 120 ); ?>" class="small-text"></div><div class="zma-form-group"><label>Daily SMS Limit (Per Phone)</label><input type="number" name="zma_settings[daily_limit]" value="<?php echo esc_attr( $o['daily_limit'] ?? 10 ); ?>" class="small-text"></div><div class="zma-form-group"><label>Max Incorrect Attempts</label><input type="number" name="zma_settings[max_attempts]" value="<?php echo esc_attr( $o['max_attempts'] ?? 3 ); ?>" class="small-text"></div></div>
                    </div>
                    
                    <!-- Preview -->
                    <div class="zma-preview-pane">
                        <div class="zma-preview-sticky"><h3>Live Preview</h3><div class="zma-preview-box"><div class="zma-container" style="max-width: <?php echo esc_attr($o['con_width'] ?? '400px'); ?>; background-color: <?php echo esc_attr($o['con_bg'] ?? '#fff'); ?>; padding: <?php echo esc_attr( ($o['con_padding_top']??'20px').' '.($o['con_padding_right']??'20px').' '.($o['con_padding_bottom']??'20px').' '.($o['con_padding_left']??'20px') ); ?>; border-radius: <?php echo esc_attr( ($o['con_radius_top']??'8px').' '.($o['con_radius_right']??'8px').' '.($o['con_radius_bottom']??'8px').' '.($o['con_radius_left']??'8px') ); ?>; border-width: <?php echo esc_attr( ($o['con_border_w_top']??'1px').' '.($o['con_border_w_right']??'1px').' '.($o['con_border_w_bottom']??'1px').' '.($o['con_border_w_left']??'1px') ); ?>; border-style: <?php echo esc_attr($o['con_border_style'] ?? 'solid'); ?>; border-color: <?php echo esc_attr($o['con_border_color'] ?? '#e5e5e5'); ?>;"><div class="zma-header"><h3 id="zma-preview-title" style="font-size: <?php echo esc_attr($o['title_font_size'] ?? '20px'); ?>; color: <?php echo esc_attr($o['title_color'] ?? '#333'); ?>; margin-bottom: <?php echo esc_attr($o['title_margin'] ?? '10px'); ?>;"><?php esc_html_e( 'Login or Register', 'zenith-mobile-auth' ); ?></h3><p id="zma-preview-desc" style="font-size: <?php echo esc_attr($o['desc_font_size'] ?? '14px'); ?>; color: <?php echo esc_attr($o['desc_color'] ?? '#666'); ?>; margin-bottom: <?php echo esc_attr($o['desc_margin'] ?? '20px'); ?>;"><?php esc_html_e( 'We will send an OTP code to your mobile number.', 'zenith-mobile-auth' ); ?></p></div><div class="zma-input-group" style="margin-bottom: <?php echo esc_attr($o['inp_margin'] ?? '15px'); ?>;"><label><?php esc_html_e( 'Phone Number', 'zenith-mobile-auth' ); ?></label><input type="tel" class="zma-input" placeholder="0912..." disabled style="padding: <?php echo esc_attr( ($o['inp_padding_top']??'10px').' '.($o['inp_padding_right']??'10px').' '.($o['inp_padding_bottom']??'10px').' '.($o['inp_padding_left']??'10px') ); ?>; border-radius: <?php echo esc_attr( ($o['inp_radius_top']??'4px').' '.($o['inp_radius_right']??'4px').' '.($o['inp_radius_bottom']??'4px').' '.($o['inp_radius_left']??'4px') ); ?>; border-color: <?php echo esc_attr( $o['inp_border_color'] ?? '#ccc' ); ?>;"></div><button type="button" class="zma-btn" style="background-color: <?php echo esc_attr($o['btn_bg'] ?? '#333'); ?>; color: <?php echo esc_attr($o['btn_text'] ?? '#fff'); ?>; padding: <?php echo esc_attr( ($o['btn_padding_top']??'12px').' '.($o['btn_padding_right']??'12px').' '.($o['btn_padding_bottom']??'12px').' '.($o['btn_padding_left']??'12px') ); ?>; border-radius: <?php echo esc_attr( ($o['btn_radius_top']??'4px').' '.($o['btn_radius_right']??'4px').' '.($o['btn_radius_bottom']??'4px').' '.($o['btn_radius_left']??'4px') ); ?>;"><?php esc_html_e( 'Send Code', 'zenith-mobile-auth' ); ?></button></div></div></div></div>
                </div>
            </form>
        </div>
        <?php
    }

    public function render_user_profile_fields( $user ) {
        // (Existing Logic for stats...)
        if ( ! current_user_can( 'edit_users' ) ) return;
        $phone = $user->user_login; // Simplified for brevity in this output, assumes normalized
        // ... (Stats calculation) ...
        ?>
        <!-- (Stats Table HTML) -->
        <?php
    }

    public function render_admin_user_gender( $user ) {
        if ( ! current_user_can( 'edit_users' ) ) return;
        $gender = get_user_meta( $user->ID, 'gender', true );
        ?>
        <table class="form-table zma-gender-table-row" style="display:none;">
            <tr class="zma-gender-row">
                <th><label><?php esc_html_e( 'Gender', 'zenith-mobile-auth' ); ?></label></th>
                <td>
                    <fieldset>
                        <label style="margin-right: 15px;"><input type="radio" name="zma_gender" value="male" <?php checked( $gender, 'male' ); ?>> <?php esc_html_e( 'Male', 'zenith-mobile-auth' ); ?></label>
                        <label><input type="radio" name="zma_gender" value="female" <?php checked( $gender, 'female' ); ?>> <?php esc_html_e( 'Female', 'zenith-mobile-auth' ); ?></label>
                    </fieldset>
                </td>
            </tr>
        </table>
        <script>jQuery(document).ready(function($) { var $roleRow = $('.user-role-wrap'); if ($roleRow.length) { $('.zma-gender-row').insertAfter($roleRow); $('.zma-gender-table-row').remove(); } else { $('.zma-gender-table-row').show(); } });</script>
        <?php
    }

    public function save_admin_user_gender( $user_id ) {
        if ( ! current_user_can( 'edit_user', $user_id ) ) return false;
        if ( isset( $_POST['zma_gender'] ) ) update_user_meta( $user_id, 'gender', sanitize_text_field( $_POST['zma_gender'] ) );
    }

    public function print_user_profile_js() { /* JS for Reset Button */ }
}