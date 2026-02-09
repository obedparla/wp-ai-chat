<?php
/**
 * PHPStan bootstrap file - defines constants for static analysis.
 */

if (!defined('ABSPATH')) {
    define('ABSPATH', '/tmp/wordpress/');
}
if (!defined('WPAIC_VERSION')) {
    define('WPAIC_VERSION', '1.0.0');
}
if (!defined('WPAIC_PLUGIN_DIR')) {
    define('WPAIC_PLUGIN_DIR', __DIR__ . '/');
}
if (!defined('WPAIC_PLUGIN_URL')) {
    define('WPAIC_PLUGIN_URL', 'https://example.com/wp-content/plugins/wp-ai-chatbot/');
}
if (!defined('WPAIC_PLUGIN_BASENAME')) {
    define('WPAIC_PLUGIN_BASENAME', 'wp-ai-chatbot/wp-ai-chatbot.php');
}

if (!function_exists('wc_get_cart_url')) {
    function wc_get_cart_url(): string {
        return '';
    }
}

if (!function_exists('wc_get_product')) {
    /**
     * @param int $product_id
     * @return WC_Product|false
     */
    function wc_get_product($product_id) {
        return false;
    }
}

if (!function_exists('wc_get_order')) {
    /**
     * @param int|string $order_id
     * @return WC_Order|false
     */
    function wc_get_order($order_id) {
        return false;
    }
}

if (!function_exists('wc_get_order_status_name')) {
    function wc_get_order_status_name(string $status): string {
        return $status;
    }
}

if (!function_exists('woocommerce_mini_cart')) {
    function woocommerce_mini_cart(): void {}
}

if (!function_exists('WC')) {
    function WC(): WooCommerce {
        return new WooCommerce();
    }
}

if (!class_exists('WooCommerce')) {
    class WooCommerce {
        /** @var WC_Cart */
        public $cart;
        public function __construct() {
            $this->cart = new WC_Cart();
        }
    }
}

if (!class_exists('WC_Cart')) {
    class WC_Cart {
        /**
         * @param int $product_id
         * @param int $quantity
         * @return string|false
         */
        public function add_to_cart($product_id, $quantity = 1) {
            return '';
        }
        public function get_cart_contents_count(): int {
            return 0;
        }
        public function get_cart_total(): string {
            return '';
        }
    }
}

if (!function_exists('wc_attribute_label')) {
    function wc_attribute_label(string $name, $product = null): string {
        return $name;
    }
}

if (!class_exists('WC_Product')) {
    class WC_Product {
        public function is_purchasable(): bool {
            return true;
        }
        public function is_in_stock(): bool {
            return true;
        }
        public function get_id(): int {
            return 0;
        }
        public function get_name(): string {
            return '';
        }
        public function get_sku(): string {
            return '';
        }
        public function get_description(): string {
            return '';
        }
        public function get_short_description(): string {
            return '';
        }
        public function get_type(): string {
            return 'simple';
        }
        public function is_type(string $type): bool {
            return false;
        }
    }
}

if (!class_exists('WC_Product_Variable')) {
    class WC_Product_Variable extends WC_Product {
        /** @return array<string, array<int, string>> */
        public function get_variation_attributes(): array {
            return [];
        }
        /** @return array<int, array<string, mixed>> */
        public function get_available_variations(): array {
            return [];
        }
    }
}

if (!class_exists('WC_Order')) {
    class WC_Order {
        public function get_id(): int {
            return 0;
        }
        public function get_status(): string {
            return '';
        }
        public function get_total(): string {
            return '';
        }
        public function get_date_created(): ?WC_DateTime {
            return null;
        }
        /** @return WC_Order_Item[] */
        public function get_items(): array {
            return [];
        }
        public function get_billing_first_name(): string {
            return '';
        }
        public function get_billing_last_name(): string {
            return '';
        }
        public function get_billing_email(): string {
            return '';
        }
        public function get_order_number(): string {
            return '';
        }
        public function get_formatted_order_total(): string {
            return '';
        }
        public function get_shipping_method(): string {
            return '';
        }
        /**
         * @param WC_Order_Item $item
         * @return string
         */
        public function get_formatted_line_subtotal($item): string {
            return '';
        }
        /**
         * @param string $key
         * @return mixed
         */
        public function get_meta(string $key) {
            return '';
        }
    }
}

if (!class_exists('WC_DateTime')) {
    class WC_DateTime extends DateTime {
        public function date(string $format): string {
            return parent::format($format);
        }
    }
}

if (!class_exists('WC_Order_Item')) {
    class WC_Order_Item {
        public function get_name(): string {
            return '';
        }
        public function get_quantity(): int {
            return 0;
        }
        public function get_total(): string {
            return '';
        }
    }
}
