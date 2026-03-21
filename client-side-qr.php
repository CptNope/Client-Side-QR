<?php
/**
 * Plugin Name: Client-Side QR Code Generator
 * Description: A lightweight premium shortcode to generate highly customizable QR codes natively on the client side using qr-code-styling.
 * Version: 4.0.0
 * Author: Jeremy Anderson
 * Author URI: https://jeremyanderson.tech
 * Text Domain: csqr
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

function csqr_enqueue_scripts() {
    wp_register_script('qrcode-js', 'https://cdn.jsdelivr.net/npm/qr-code-styling@1.5.0/lib/qr-code-styling.js', array(), '1.5.0', true);
    wp_register_script('csqr-script', plugins_url('/assets/qr-script.js', __FILE__), array('qrcode-js'), '4.0.0', true);
    wp_register_style('csqr-style', plugins_url('/assets/qr-style.css', __FILE__), array(), '4.0.0');
}
add_action('wp_enqueue_scripts', 'csqr_enqueue_scripts');

function csqr_register_block() {
    if (!function_exists('register_block_type')) {
        return;
    }
    
    wp_enqueue_script('qrcode-js-admin', 'https://cdn.jsdelivr.net/npm/qr-code-styling@1.5.0/lib/qr-code-styling.js', array(), '1.5.0', true);

    wp_register_script(
        'csqr-block-script',
        plugins_url('/assets/qr-block.js', __FILE__),
        array('wp-blocks', 'wp-element', 'wp-components', 'wp-editor', 'qrcode-js-admin'),
        '4.0.0'
    );
    
    register_block_type('csqr/generator', array(
        'editor_script' => 'csqr-block-script',
        'render_callback' => 'csqr_shortcode'
    ));
}
add_action('init', 'csqr_register_block');

function csqr_shortcode($attributes = array()) {
    $atts = shortcode_atts(array(
        'qrColorDark' => '#111111',
        'qrColorDark2' => '#111111',
        'qrColorLight' => '#ffffff',
        'qrSize' => 256,
        'qrCorrectLevel' => 'H',
        'qrDotStyle' => 'square',
        'qrEyeStyle' => 'square',
        'qrEyeColor' => '',
        'qrGradient' => false,
        'logoUrl' => '',
        'allowUserColor' => false,
        'allowUserSize' => false,
        'allowUserCorrectLevel' => false,
        'enableUrl' => true,
        'enableWifi' => true,
        'enableEmail' => true,
        'enableSms' => true,
        'enableVcard' => true,
        'enableCrypto' => true,
        'enablePaypal' => true,
    ), $attributes);

    $allowUserColor = filter_var($atts['allowUserColor'], FILTER_VALIDATE_BOOLEAN);
    $allowUserSize = filter_var($atts['allowUserSize'], FILTER_VALIDATE_BOOLEAN);
    $allowUserCorrectLevel = filter_var($atts['allowUserCorrectLevel'], FILTER_VALIDATE_BOOLEAN);
    $qrGradient = filter_var($atts['qrGradient'], FILTER_VALIDATE_BOOLEAN);
    
    $enableUrl = filter_var($atts['enableUrl'], FILTER_VALIDATE_BOOLEAN);
    $enableWifi = filter_var($atts['enableWifi'], FILTER_VALIDATE_BOOLEAN);
    $enableEmail = filter_var($atts['enableEmail'], FILTER_VALIDATE_BOOLEAN);
    $enableSms = filter_var($atts['enableSms'], FILTER_VALIDATE_BOOLEAN);
    $enableVcard = filter_var($atts['enableVcard'], FILTER_VALIDATE_BOOLEAN);
    $enableCrypto = filter_var($atts['enableCrypto'], FILTER_VALIDATE_BOOLEAN);
    $enablePaypal = filter_var($atts['enablePaypal'], FILTER_VALIDATE_BOOLEAN);

    $activeTab = '';
    if ($enableUrl) $activeTab = 'url';
    elseif ($enableWifi) $activeTab = 'wifi';
    elseif ($enableVcard) $activeTab = 'vcard';
    elseif ($enableEmail) $activeTab = 'email';
    elseif ($enableSms) $activeTab = 'sms';
    elseif ($enableCrypto) $activeTab = 'crypto';
    elseif ($enablePaypal) $activeTab = 'paypal';

    wp_enqueue_script('qrcode-js');
    wp_enqueue_script('csqr-script');
    wp_enqueue_style('csqr-style');

    $uid = uniqid('csqr_');

    ob_start();
    ?>
    <div class="csqr-container" 
         data-color-dark="<?php echo esc_attr($atts['qrColorDark']); ?>" 
         data-color-dark2="<?php echo esc_attr($atts['qrColorDark2']); ?>" 
         data-gradient="<?php echo $qrGradient ? 'true' : 'false'; ?>"
         data-color-light="<?php echo esc_attr($atts['qrColorLight']); ?>" 
         data-size="<?php echo esc_attr($atts['qrSize']); ?>"
         data-correct-level="<?php echo esc_attr($atts['qrCorrectLevel']); ?>"
         data-dot-style="<?php echo esc_attr($atts['qrDotStyle']); ?>"
         data-eye-style="<?php echo esc_attr($atts['qrEyeStyle']); ?>"
         data-eye-color="<?php echo esc_attr($atts['qrEyeColor']); ?>"
         data-logo-url="<?php echo esc_url($atts['logoUrl']); ?>">
        
        <h3 class="csqr-title"><?php esc_html_e('Generate QR Code', 'csqr'); ?></h3>
        <div class="csqr-input-group">
            
            <div class="csqr-data-type-tabs">
                <?php if ($enableUrl): ?>
                <label class="csqr-tab <?php echo $activeTab === 'url' ? 'active' : ''; ?>">
                    <input type="radio" name="tab-<?php echo esc_attr($uid); ?>" value="url" class="csqr-data-type-radio" <?php echo $activeTab === 'url' ? 'checked' : ''; ?>> <?php esc_html_e('URL', 'csqr'); ?>
                </label>
                <?php endif; ?>
                <?php if ($enableWifi): ?>
                <label class="csqr-tab <?php echo $activeTab === 'wifi' ? 'active' : ''; ?>">
                    <input type="radio" name="tab-<?php echo esc_attr($uid); ?>" value="wifi" class="csqr-data-type-radio" <?php echo $activeTab === 'wifi' ? 'checked' : ''; ?>> <?php esc_html_e('WiFi', 'csqr'); ?>
                </label>
                <?php endif; ?>
                <?php if ($enableVcard): ?>
                <label class="csqr-tab <?php echo $activeTab === 'vcard' ? 'active' : ''; ?>">
                    <input type="radio" name="tab-<?php echo esc_attr($uid); ?>" value="vcard" class="csqr-data-type-radio" <?php echo $activeTab === 'vcard' ? 'checked' : ''; ?>> <?php esc_html_e('vCard', 'csqr'); ?>
                </label>
                <?php endif; ?>
                <?php if ($enableEmail): ?>
                <label class="csqr-tab <?php echo $activeTab === 'email' ? 'active' : ''; ?>">
                    <input type="radio" name="tab-<?php echo esc_attr($uid); ?>" value="email" class="csqr-data-type-radio" <?php echo $activeTab === 'email' ? 'checked' : ''; ?>> <?php esc_html_e('Email', 'csqr'); ?>
                </label>
                <?php endif; ?>
                <?php if ($enableSms): ?>
                <label class="csqr-tab <?php echo $activeTab === 'sms' ? 'active' : ''; ?>">
                    <input type="radio" name="tab-<?php echo esc_attr($uid); ?>" value="sms" class="csqr-data-type-radio" <?php echo $activeTab === 'sms' ? 'checked' : ''; ?>> <?php esc_html_e('SMS', 'csqr'); ?>
                </label>
                <?php endif; ?>
                <?php if ($enableCrypto): ?>
                <label class="csqr-tab <?php echo $activeTab === 'crypto' ? 'active' : ''; ?>">
                    <input type="radio" name="tab-<?php echo esc_attr($uid); ?>" value="crypto" class="csqr-data-type-radio" <?php echo $activeTab === 'crypto' ? 'checked' : ''; ?>> <?php esc_html_e('Crypto', 'csqr'); ?>
                </label>
                <?php endif; ?>
                <?php if ($enablePaypal): ?>
                <label class="csqr-tab <?php echo $activeTab === 'paypal' ? 'active' : ''; ?>">
                    <input type="radio" name="tab-<?php echo esc_attr($uid); ?>" value="paypal" class="csqr-data-type-radio" <?php echo $activeTab === 'paypal' ? 'checked' : ''; ?>> <?php esc_html_e('PayPal', 'csqr'); ?>
                </label>
                <?php endif; ?>
            </div>

            <!-- Dynamic Fields Container -->
            <?php if ($enableUrl): ?>
            <div class="csqr-fields-container csqr-url-fields" style="display: <?php echo $activeTab === 'url' ? 'flex' : 'none'; ?>;">
                <input type="url" class="csqr-input csqr-url-input" placeholder="<?php esc_attr_e('Enter URL or text here...', 'csqr'); ?>" />
                <details class="csqr-utm-builder">
                    <summary><?php esc_html_e('Add UTM Parameters', 'csqr'); ?></summary>
                    <div class="csqr-utm-grid">
                        <input type="text" class="csqr-input csqr-utm-source" placeholder="<?php esc_attr_e('Source (e.g. print)', 'csqr'); ?>" />
                        <input type="text" class="csqr-input csqr-utm-medium" placeholder="<?php esc_attr_e('Medium (e.g. poster)', 'csqr'); ?>" />
                        <input type="text" class="csqr-input csqr-utm-campaign" placeholder="<?php esc_attr_e('Campaign Name', 'csqr'); ?>" />
                    </div>
                </details>
            </div>
            <?php endif; ?>
            
            <?php if ($enableWifi): ?>
            <div class="csqr-fields-container csqr-wifi-fields" style="display: <?php echo $activeTab === 'wifi' ? 'flex' : 'none'; ?>;">
                <input type="text" class="csqr-input csqr-wifi-ssid" placeholder="<?php esc_attr_e('Network Name (SSID)', 'csqr'); ?>" />
                <input type="password" class="csqr-input csqr-wifi-pass" placeholder="<?php esc_attr_e('Password', 'csqr'); ?>" />
                <div class="csqr-wifi-options">
                    <select class="csqr-input csqr-wifi-enc" style="width: 50%;">
                        <option value="WPA"><?php esc_html_e('WPA/WPA2', 'csqr'); ?></option>
                        <option value="WEP"><?php esc_html_e('WEP', 'csqr'); ?></option>
                        <option value="nopass"><?php esc_html_e('No Password', 'csqr'); ?></option>
                    </select>
                    <label style="font-size: 0.9em;"><input type="checkbox" class="csqr-wifi-hidden"> <?php esc_html_e('Hidden Network', 'csqr'); ?></label>
                </div>
            </div>
            <?php endif; ?>

            <?php if ($enableVcard): ?>
            <div class="csqr-fields-container csqr-vcard-fields" style="display: <?php echo $activeTab === 'vcard' ? 'flex' : 'none'; ?>;">
                <div class="csqr-row csqr-half-row">
                    <input type="text" class="csqr-input csqr-vcard-fname" placeholder="<?php esc_attr_e('First Name', 'csqr'); ?>" />
                    <input type="text" class="csqr-input csqr-vcard-lname" placeholder="<?php esc_attr_e('Last Name', 'csqr'); ?>" />
                </div>
                <div class="csqr-row csqr-half-row">
                    <input type="tel" class="csqr-input csqr-vcard-phone" placeholder="<?php esc_attr_e('Phone Number', 'csqr'); ?>" />
                    <input type="email" class="csqr-input csqr-vcard-email" placeholder="<?php esc_attr_e('Email Address', 'csqr'); ?>" />
                </div>
                <input type="text" class="csqr-input csqr-vcard-company" placeholder="<?php esc_attr_e('Company', 'csqr'); ?>" />
                <input type="text" class="csqr-input csqr-vcard-title" placeholder="<?php esc_attr_e('Job Title', 'csqr'); ?>" />
                <input type="url" class="csqr-input csqr-vcard-url" placeholder="<?php esc_attr_e('Website', 'csqr'); ?>" />
                <input type="text" class="csqr-input csqr-vcard-address" placeholder="<?php esc_attr_e('Physical Address', 'csqr'); ?>" />
            </div>
            <?php endif; ?>

            <?php if ($enableEmail): ?>
            <div class="csqr-fields-container csqr-email-fields" style="display: <?php echo $activeTab === 'email' ? 'flex' : 'none'; ?>;">
                <input type="email" class="csqr-input csqr-email-address" placeholder="<?php esc_attr_e('Email Address', 'csqr'); ?>" />
                <input type="text" class="csqr-input csqr-email-subject" placeholder="<?php esc_attr_e('Subject', 'csqr'); ?>" />
                <textarea class="csqr-input csqr-email-body" placeholder="<?php esc_attr_e('Message Body...', 'csqr'); ?>"></textarea>
            </div>
            <?php endif; ?>

            <?php if ($enableSms): ?>
            <div class="csqr-fields-container csqr-sms-fields" style="display: <?php echo $activeTab === 'sms' ? 'flex' : 'none'; ?>;">
                <input type="tel" class="csqr-input csqr-sms-phone" placeholder="<?php esc_attr_e('Phone Number', 'csqr'); ?>" />
                <textarea class="csqr-input csqr-sms-message" placeholder="<?php esc_attr_e('Message...', 'csqr'); ?>"></textarea>
            </div>
            <?php endif; ?>

            <?php if ($enableCrypto): ?>
            <div class="csqr-fields-container csqr-crypto-fields" style="display: <?php echo $activeTab === 'crypto' ? 'flex' : 'none'; ?>;">
                <select class="csqr-input csqr-crypto-currency">
                    <option value="bitcoin"><?php esc_html_e('Bitcoin (BTC)', 'csqr'); ?></option>
                    <option value="ethereum"><?php esc_html_e('Ethereum (ETH)', 'csqr'); ?></option>
                    <option value="litecoin"><?php esc_html_e('Litecoin (LTC)', 'csqr'); ?></option>
                </select>
                <input type="text" class="csqr-input csqr-crypto-address" placeholder="<?php esc_attr_e('Wallet Address', 'csqr'); ?>" />
                <input type="number" class="csqr-input csqr-crypto-amount" placeholder="<?php esc_attr_e('Amount (Optional)', 'csqr'); ?>" step="any" />
            </div>
            <?php endif; ?>

            <?php if ($enablePaypal): ?>
            <div class="csqr-fields-container csqr-paypal-fields" style="display: <?php echo $activeTab === 'paypal' ? 'flex' : 'none'; ?>;">
                <input type="text" class="csqr-input csqr-paypal-username" placeholder="<?php esc_attr_e('PayPal.me Username', 'csqr'); ?>" />
                <div class="csqr-row csqr-half-row">
                    <input type="number" class="csqr-input csqr-paypal-amount" placeholder="<?php esc_attr_e('Amount (Optional)', 'csqr'); ?>" step="any" />
                    <select class="csqr-input csqr-paypal-currency" style="width: 50%;">
                        <option value="USD">USD</option>
                        <option value="EUR">EUR</option>
                        <option value="GBP">GBP</option>
                        <option value="CAD">CAD</option>
                        <option value="AUD">AUD</option>
                    </select>
                </div>
            </div>
            <?php endif; ?>
            
            <?php if ($allowUserColor || $allowUserSize || $allowUserCorrectLevel): ?>
                <div class="csqr-user-settings">
                    <?php if ($allowUserColor): ?>
                        <div class="csqr-setting-row" style="flex-direction:row; align-items:center;">
                            <label><input type="checkbox" class="csqr-transparent-bg"> <?php esc_html_e('Transparent Background', 'csqr'); ?></label>
                        </div>
                        <div class="csqr-setting-row">
                            <label><?php esc_html_e('Foreground 1', 'csqr'); ?> <input type="color" class="csqr-color-dark" value="<?php echo esc_attr($atts['qrColorDark']); ?>"></label>
                            <?php if ($qrGradient): ?>
                            <label><?php esc_html_e('Foreground 2', 'csqr'); ?> <input type="color" class="csqr-color-dark2" value="<?php echo esc_attr($atts['qrColorDark2']); ?>"></label>
                            <?php endif; ?>
                            <label class="csqr-bg-picker"><?php esc_html_e('Background', 'csqr'); ?> <input type="color" class="csqr-color-light" value="<?php echo esc_attr($atts['qrColorLight']); ?>"></label>
                        </div>
                    <?php endif; ?>
                    
                    <?php if ($allowUserSize): ?>
                        <div class="csqr-setting-row">
                            <label><?php esc_html_e('Size:', 'csqr'); ?> <span class="csqr-size-val"><?php echo esc_attr($atts['qrSize']); ?></span><?php esc_html_e('px', 'csqr'); ?></label>
                            <input type="range" class="csqr-size-input" min="100" max="600" step="10" value="<?php echo esc_attr($atts['qrSize']); ?>">
                        </div>
                    <?php endif; ?>

                    <?php if ($allowUserCorrectLevel): ?>
                        <div class="csqr-setting-row">
                            <label><?php esc_html_e('Error Correction', 'csqr'); ?>
                                <select class="csqr-correct-level">
                                    <option value="L" <?php selected($atts['qrCorrectLevel'], 'L'); ?>><?php esc_html_e('Low (7%)', 'csqr'); ?></option>
                                    <option value="M" <?php selected($atts['qrCorrectLevel'], 'M'); ?>><?php esc_html_e('Medium (15%)', 'csqr'); ?></option>
                                    <option value="Q" <?php selected($atts['qrCorrectLevel'], 'Q'); ?>><?php esc_html_e('Quartile (25%)', 'csqr'); ?></option>
                                    <option value="H" <?php selected($atts['qrCorrectLevel'], 'H'); ?>><?php esc_html_e('High (30%)', 'csqr'); ?></option>
                                </select>
                            </label>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
        <div class="csqr-output-wrapper">
            <div class="csqr-qrcode"></div>
            <div class="csqr-downloads" style="display: none; gap: 10px; margin-top: 15px; width: 100%; justify-content: center; flex-wrap: wrap;">
                <button class="csqr-download-png-btn"><?php esc_html_e('Download PNG', 'csqr'); ?></button>
                <button class="csqr-download-svg-btn"><?php esc_html_e('Download SVG', 'csqr'); ?></button>
                <button class="csqr-copy-btn"><?php esc_html_e('Copy Image', 'csqr'); ?></button>
            </div>
        </div>
    </div>
    <?php
    return ob_get_clean();
}
add_shortcode('client_side_qr', 'csqr_shortcode');
