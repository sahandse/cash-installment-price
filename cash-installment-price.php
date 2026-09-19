<?php
/**
 * Plugin Name: قیمت نقدی و اقساطی ووکامرس
 * Plugin URI: https://github.com/sahandse/cash-installment-price
 * Description: نمایش قیمت نقدی و اقساطی محصولات ووکامرس با امکان تنظیم درصد یا مبلغ ثابت افزایش قیمت.
 * Version: 1.0.1
 * Author: Sahand Rezvan
 * Author URI: https://github.com/sahandse
 * Text Domain: cash-installment-price
 * Requires at least: 6.2
 * Requires PHP: 7.4
 * WC requires at least: 7.0
 */

defined('ABSPATH') || exit;

final class CIP_Plugin {
    const VERSION = '1.0.1';
    const OPTION  = 'cip_settings';

    public function __construct() {
        add_action('before_woocommerce_init', [$this, 'declare_hpos']);
        add_action('plugins_loaded', [$this, 'boot']);
    }

    public function declare_hpos() {
        if (class_exists('Automattic\\WooCommerce\\Utilities\\FeaturesUtil')) {
            Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility('custom_order_tables', __FILE__, true);
        }
    }

    public function boot() {
        if (!class_exists('WooCommerce')) {
            add_action('admin_notices', [$this, 'woocommerce_notice']);
            return;
        }

        add_action('admin_menu', [$this, 'admin_menu']);
        add_action('admin_init', [$this, 'register_settings']);
        add_action('admin_enqueue_scripts', [$this, 'admin_assets']);

        add_filter('woocommerce_get_price_html', [$this, 'price_html'], 20, 2);
        add_filter('woocommerce_available_variation', [$this, 'variation_data'], 20, 3);
    }

    public function woocommerce_notice() {
        echo '<div class="notice notice-error"><p>افزونه قیمت نقدی و اقساطی برای اجرا به WooCommerce نیاز دارد.</p></div>';
    }

    public function defaults() {
        return [
            'enabled' => 'yes',
            'mode' => 'percent',
            'value' => 10,
            'cash_label' => 'قیمت نقدی',
            'installment_label' => 'قیمت اقساطی',
            'show_cash' => 'yes',
            'show_installment' => 'yes',
            'accent' => '#111827',
        ];
    }

    public function settings() {
        return wp_parse_args((array)get_option(self::OPTION, []), $this->defaults());
    }

    public function register_settings() {
        register_setting('cip_group', self::OPTION, [$this, 'sanitize_settings']);
    }

    public function sanitize_settings($in) {
        $d = $this->defaults();
        return [
            'enabled' => !empty($in['enabled']) ? 'yes' : 'no',
            'mode' => in_array($in['mode'] ?? '', ['percent','fixed'], true) ? $in['mode'] : $d['mode'],
            'value' => max(0, (float)($in['value'] ?? 0)),
            'cash_label' => sanitize_text_field($in['cash_label'] ?? $d['cash_label']),
            'installment_label' => sanitize_text_field($in['installment_label'] ?? $d['installment_label']),
            'show_cash' => !empty($in['show_cash']) ? 'yes' : 'no',
            'show_installment' => !empty($in['show_installment']) ? 'yes' : 'no',
            'accent' => sanitize_hex_color($in['accent'] ?? '') ?: $d['accent'],
        ];
    }

    public function admin_menu() {
        if (function_exists('s_store_register_submenu')) {
            s_store_register_submenu('cash-installment-price', 'قیمت نقدی و اقساطی', [$this, 'settings_page'], 'manage_woocommerce', 'قیمت نقدی و اقساطی');
            return;
        }
        add_submenu_page(
            'woocommerce',
            'قیمت نقدی و اقساطی',
            'قیمت نقدی/اقساطی',
            'manage_woocommerce',
            'cash-installment-price',
            [$this, 'settings_page']
        );
    }

    public function admin_assets($hook) {
        if (false === strpos($hook, 'cash-installment-price')) return;
        wp_enqueue_style('cip-admin', plugin_dir_url(__FILE__) . 'assets/admin.css', [], self::VERSION);
    }

    public function settings_page() {
        if (!current_user_can('manage_woocommerce')) return;
        $s = $this->settings();
        ?>
        <div class="wrap cip-admin">
            <div class="cip-hero">
                <div>
                    <h1>قیمت نقدی و اقساطی</h1>
                    <p>نمایش دو قیمت برای محصولات ووکامرس با کنترل کامل روی فرمول قیمت اقساطی.</p>
                </div>
                <span>v<?php echo esc_html(self::VERSION); ?></span>
            </div>

            <form method="post" action="options.php">
                <?php settings_fields('cip_group'); ?>
                <div class="cip-grid">
                    <section class="cip-card">
                        <h2>تنظیمات عمومی</h2>
                        <label class="cip-switch">
                            <span>فعال بودن افزونه</span>
                            <input type="checkbox" name="<?php echo self::OPTION; ?>[enabled]" value="1" <?php checked($s['enabled'],'yes'); ?>>
                        </label>

                        <label>نوع افزایش قیمت اقساطی
                            <select name="<?php echo self::OPTION; ?>[mode]">
                                <option value="percent" <?php selected($s['mode'],'percent'); ?>>درصدی</option>
                                <option value="fixed" <?php selected($s['mode'],'fixed'); ?>>مبلغ ثابت</option>
                            </select>
                        </label>

                        <label>مقدار
                            <input type="number" min="0" step="0.01" name="<?php echo self::OPTION; ?>[value]" value="<?php echo esc_attr($s['value']); ?>">
                        </label>
                    </section>

                    <section class="cip-card">
                        <h2>برچسب‌ها</h2>
                        <label>عنوان قیمت نقدی
                            <input type="text" name="<?php echo self::OPTION; ?>[cash_label]" value="<?php echo esc_attr($s['cash_label']); ?>">
                        </label>
                        <label>عنوان قیمت اقساطی
                            <input type="text" name="<?php echo self::OPTION; ?>[installment_label]" value="<?php echo esc_attr($s['installment_label']); ?>">
                        </label>
                        <label class="cip-switch">
                            <span>نمایش قیمت نقدی</span>
                            <input type="checkbox" name="<?php echo self::OPTION; ?>[show_cash]" value="1" <?php checked($s['show_cash'],'yes'); ?>>
                        </label>
                        <label class="cip-switch">
                            <span>نمایش قیمت اقساطی</span>
                            <input type="checkbox" name="<?php echo self::OPTION; ?>[show_installment]" value="1" <?php checked($s['show_installment'],'yes'); ?>>
                        </label>
                    </section>

                    <section class="cip-card">
                        <h2>ظاهر</h2>
                        <label>رنگ اصلی
                            <input type="color" name="<?php echo self::OPTION; ?>[accent]" value="<?php echo esc_attr($s['accent']); ?>">
                        </label>
                    </section>

                    <section class="cip-card">
                        <h2>فرمول فعلی</h2>
                        <p><?php echo 'percent' === $s['mode']
                            ? esc_html('قیمت اقساطی = قیمت محصول + ' . $s['value'] . '٪')
                            : esc_html('قیمت اقساطی = قیمت محصول + ' . $s['value']); ?></p>
                    </section>
                </div>
                <?php submit_button('ذخیره تنظیمات'); ?>
            </form>
        </div>
        <?php
    }

    private function installment_price($price) {
        $s = $this->settings();
        if ('fixed' === $s['mode']) return (float)$price + (float)$s['value'];
        return (float)$price + ((float)$price * ((float)$s['value'] / 100));
    }

    public function price_html($html, $product) {
        $s = $this->settings();
        if ('yes' !== $s['enabled'] || !$product || !$product->is_purchasable()) return $html;

        $price = (float)$product->get_price();
        if ($price <= 0) return $html;

        $out = '<span class="cip-prices" style="--cip-accent:' . esc_attr($s['accent']) . '">';

        if ('yes' === $s['show_cash']) {
            $out .= '<span class="cip-row cip-cash"><small>' . esc_html($s['cash_label']) . '</small><strong>' . wp_kses_post(wc_price($price)) . '</strong></span>';
        }

        if ('yes' === $s['show_installment']) {
            $out .= '<span class="cip-row cip-installment"><small>' . esc_html($s['installment_label']) . '</small><strong>' . wp_kses_post(wc_price($this->installment_price($price))) . '</strong></span>';
        }

        $out .= '</span>';
        $out .= '<style>.cip-prices{display:inline-flex;flex-direction:column;gap:6px}.cip-row{display:flex;gap:8px;align-items:center}.cip-row small{font-size:12px;color:#6b7280}.cip-installment strong{color:var(--cip-accent)}</style>';

        return $out;
    }

    public function variation_data($data, $product, $variation) {
        $price = (float)$variation->get_price();
        if ($price > 0) {
            $data['cip_installment_price_html'] = wc_price($this->installment_price($price));
        }
        return $data;
    }
}

new CIP_Plugin();
