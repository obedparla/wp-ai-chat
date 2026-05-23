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

if (!function_exists('wc_load_cart')) {
    function wc_load_cart(): void {}
}

if (!class_exists('WooCommerce')) {
    class WooCommerce {
        /** @var WC_Cart */
        public $cart;
        public function __construct() {
            $this->cart = new WC_Cart();
        }
        public function initialize_session(): void {}
        public function initialize_cart(): void {}
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
        public function get_cart_subtotal(): string {
            return '';
        }
        /** @return array<string, array<string, mixed>> */
        public function get_cart(): array {
            return [];
        }
        public function get_product_subtotal($product, int $quantity): string {
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
        public function get_stock_status(): string {
            return 'instock';
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

if (!class_exists('WP_Post')) {
    class WP_Post {
        public int $ID = 0;
        public string $post_title = '';
        public string $post_type = 'post';
    }
}

if (!class_exists('WP_Term')) {
    class WP_Term {
        public int $term_id = 0;
        public string $name = '';
        public string $slug = '';
        public string $taxonomy = '';
    }
}

if (!function_exists('wc_get_checkout_url')) {
    function wc_get_checkout_url(): string {
        return '';
    }
}

if (!function_exists('wc_get_page_id')) {
    function wc_get_page_id(string $page): int {
        return 0;
    }
}

if (!function_exists('is_product')) {
    function is_product(): bool {
        return false;
    }
}

if (!function_exists('is_cart')) {
    function is_cart(): bool {
        return false;
    }
}

if (!function_exists('is_checkout')) {
    function is_checkout(): bool {
        return false;
    }
}

if (!function_exists('is_shop')) {
    function is_shop(): bool {
        return false;
    }
}

if (!function_exists('is_product_category')) {
    function is_product_category(): bool {
        return false;
    }
}

if (!function_exists('is_product_tag')) {
    function is_product_tag(): bool {
        return false;
    }
}

if (!function_exists('is_singular')) {
    function is_singular(): bool {
        return false;
    }
}

if (!function_exists('get_queried_object')) {
    function get_queried_object(): WP_Post|WP_Term|null {
        return null;
    }
}

if (!function_exists('get_queried_object_id')) {
    function get_queried_object_id(): int {
        return 0;
    }
}

if (!function_exists('get_term_link')) {
    /**
     * @param WP_Term $term
     */
    function get_term_link($term): string {
        return '';
    }
}

if (!function_exists('get_post_type_archive_link')) {
    function get_post_type_archive_link(string $post_type): string|false {
        return false;
    }
}

if (!function_exists('get_the_title')) {
    function get_the_title(int $post_id = 0): string {
        return '';
    }
}

if (!class_exists('WC_Product_Simple')) {
    class WC_Product_Simple extends WC_Product {
        public function set_name(string $name): void {}
        public function set_description(string $description): void {}
        public function set_short_description(string $description): void {}
        public function set_regular_price(string $price): void {}
        public function set_sale_price(string $price): void {}
        public function set_sku(string $sku): void {}
        public function set_manage_stock(bool $manage): void {}
        public function set_stock_quantity(?int $quantity): void {}
        public function set_stock_status(string $status): void {}
        public function set_weight(string $weight): void {}
        public function set_width(string $width): void {}
        public function set_height(string $height): void {}
        public function set_length(string $length): void {}
        public function set_average_rating(string $rating): void {}
        public function set_review_count(int $count): void {}
        /** @param array<int, int> $ids */
        public function set_category_ids(array $ids): void {}
        /** @param array<int, int> $ids */
        public function set_tag_ids(array $ids): void {}
        /** @param array<int, WC_Product_Attribute> $attributes */
        public function set_attributes(array $attributes): void {}
        public function update_meta_data(string $key, mixed $value): void {}
        public function save(): int {
            return 0;
        }
    }
}

if (!class_exists('WC_Product_Attribute')) {
    class WC_Product_Attribute {
        public function set_name(string $name): void {}
        /** @param array<int, string> $options */
        public function set_options(array $options): void {}
        public function set_visible(bool $visible): void {}
        public function set_variation(bool $variation): void {}
    }
}

if (!function_exists('wc_get_product_id_by_sku')) {
    function wc_get_product_id_by_sku(string $sku): int {
        return 0;
    }
}

if (!class_exists('WP_CLI')) {
    class WP_CLI {
        public static function error(string $message): void {}
        public static function warning(string $message): void {}
        public static function log(string $message): void {}
        public static function success(string $message): void {}
        public static function add_command(string $name, mixed $callable): void {}
    }
}

if (!class_exists('WPAIC_PHPStan_ProgressBar')) {
    class WPAIC_PHPStan_ProgressBar {
        public function tick(): void {}
        public function finish(): void {}
    }
}
