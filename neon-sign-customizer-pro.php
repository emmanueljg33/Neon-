<?php
/**
 * Plugin Name: Neon Sign Customizer PRO (WooCommerce)
 * Description: Exact neon customizer UI (preview + inches + price) embedded on product page, with dynamic cart price.
 * Version: 1.2.0
 * Author: ChatGPT + Jose
 * Requires Plugins: woocommerce
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class Neon_Sign_Customizer_PRO {
    const META_ENABLED = '_neon_enabled';
    const META_BASE_PRICE = '_neon_base_price';
    const META_SIZES = '_neon_sizes_inches'; // CSV inches
    const META_BG = '_neon_bg_image'; // background image URL
    const META_MAX = '_neon_max_chars';

    function __construct() {
        // Admin
        add_action('woocommerce_product_options_general_product_data', [$this,'admin_fields']);
        add_action('woocommerce_process_product_meta', [$this,'save_admin_fields']);

        // Front
        add_action('wp_enqueue_scripts', [$this,'assets']);
        add_action('woocommerce_before_add_to_cart_button', [$this,'render'], 5);

        // Cart / Order
        add_filter('woocommerce_add_cart_item_data', [$this,'cart_item_data'], 10, 3);
        add_filter('woocommerce_get_item_data', [$this,'cart_line_meta'], 10, 2);
        add_action('woocommerce_checkout_create_order_line_item', [$this,'order_item_meta'], 10, 4);
        add_action('woocommerce_before_calculate_totals', [$this,'override_price'], 10);
    }

    function admin_fields(){
        echo '<div class="options_group">';
        woocommerce_wp_checkbox([
            'id'=>self::META_ENABLED,
            'label'=>__('Enable Neon Customizer','neon'),
            'description'=>__('Show the configurator on this product','neon'),
        ]);
        woocommerce_wp_text_input([
            'id'=>self::META_BASE_PRICE,
            'label'=>__('Base price (USD)','neon'),
            'type'=>'number',
            'custom_attributes'=>['step'=>'0.01','min'=>'0'],
            'placeholder'=>'112',
            'description'=>__('Starting price for formula','neon'),
        ]);
        woocommerce_wp_text_input([
            'id'=>self::META_SIZES,
            'label'=>__('Width options (inches)','neon'),
            'placeholder'=>'20,29,40,60,79,99',
            'description'=>__('CSV list of width choices in inches','neon'),
        ]);
        woocommerce_wp_text_input([
            'id'=>self::META_BG,
            'label'=>__('Preview background image URL','neon'),
            'placeholder'=>'https://...',
        ]);
        woocommerce_wp_text_input([
            'id'=>self::META_MAX,
            'label'=>__('Max characters','neon'),
            'placeholder'=>'21',
        ]);
        echo '</div>';
    }

    function save_admin_fields($post_id){
        update_post_meta($post_id, self::META_ENABLED, isset($_POST[self::META_ENABLED]) ? 'yes' : 'no');
        update_post_meta($post_id, self::META_BASE_PRICE, wc_format_decimal($_POST[self::META_BASE_PRICE] ?? '112'));
        update_post_meta($post_id, self::META_SIZES, sanitize_text_field($_POST[self::META_SIZES] ?? '20,29,40,60,79,99'));
        update_post_meta($post_id, self::META_BG, esc_url_raw($_POST[self::META_BG] ?? ''));
        update_post_meta($post_id, self::META_MAX, absint($_POST[self::META_MAX] ?? 21));
    }

    function assets(){
        if (!function_exists('is_product') || !is_product()) return;
        wp_enqueue_style('neon-pro', plugins_url('assets/css/customizer.css', __FILE__), [], '1.2.0');
        wp_enqueue_script('neon-pro', plugins_url('assets/js/customizer.js', __FILE__), ['jquery'], '1.2.0', true);
    }

    function render(){
        global $product; if (!$product) return;
        if (get_post_meta($product->get_id(), self::META_ENABLED, true) !== 'yes') return;

        $base  = get_post_meta($product->get_id(), self::META_BASE_PRICE, true) ?: '112';
        $sizes = get_post_meta($product->get_id(), self::META_SIZES, true) ?: '20,29,40,60,79,99';
        $bg    = get_post_meta($product->get_id(), self::META_BG, true);
        $maxc  = get_post_meta($product->get_id(), self::META_MAX, true) ?: 21;
        $sizes_arr = array_filter(array_map('absint', explode(',', $sizes)));

        ?>
        <div id="neon-configurator" class="neon-wrap" data-base="<?php echo esc_attr($base); ?>" data-bg="<?php echo esc_attr($bg); ?>">
            <h2 class="neon-title"><?php esc_html_e('Create Your Neon Sign','neon'); ?></h2>

            <div class="preview" <?php if($bg){ echo 'style="background-image:url('.esc_url($bg).')"'; } ?>>
                <div id="previewText" class="neon-text">Let’s Create</div>
            </div>

            <div class="controls">
                <label for="textInput"><?php esc_html_e('Write your text:','neon'); ?></label>
                <input type="text" id="textInput" name="neon_text" value="Let’s Create" maxlength="<?php echo esc_attr($maxc); ?>" />

                <label for="sizeSelect"><?php esc_html_e('Select width (in):','neon'); ?></label>
                <select id="sizeSelect" name="neon_size">
                    <?php foreach($sizes_arr as $in): ?>
                        <option value="<?php echo esc_attr($in); ?>"><?php echo esc_html($in.' in'); ?></option>
                    <?php endforeach; ?>
                </select>

                <label for="fontSelect"><?php esc_html_e('Select font:','neon'); ?></label>
                <select id="fontSelect" name="neon_font">
                    <option value="'Pacifico', cursive">Pacifico</option>
                    <option value="'Arial', sans-serif">Arial</option>
                    <option value="'Courier New', monospace">Courier</option>
                    <option value="'Lobster', cursive">Lobster</option>
                </select>

                <label for="colorSelect"><?php esc_html_e('Neon color:','neon'); ?></label>
                <select id="colorSelect" name="neon_color">
                    <option value="#ff00ff">Pink</option>
                    <option value="#00ffff">Cyan</option>
                    <option value="#ffff00">Yellow</option>
                    <option value="#ffffff">White</option>
                </select>

                <div class="price"><strong><?php esc_html_e('Price:','neon'); ?></strong> <span id="priceDisplay">$<?php echo esc_html(number_format((float)$base,2)); ?></span></div>
                <input type="hidden" id="neon_estimated_price" name="neon_estimated_price" value="<?php echo esc_attr($base); ?>" />
            </div>
        </div>
        <?php
    }

    function cart_item_data($data, $product_id, $variation_id){
        $fields = ['neon_text','neon_size','neon_font','neon_color','neon_estimated_price'];
        $has = false; foreach($fields as $f){ if(isset($_POST[$f]) && $_POST[$f] !== ''){ $has=true; break; } }
        if(!$has) return $data;

        $data['neon'] = [
            'text'  => sanitize_text_field($_POST['neon_text'] ?? ''),
            'size'  => absint($_POST['neon_size'] ?? 0),
            'font'  => sanitize_text_field($_POST['neon_font'] ?? ''),
            'color' => sanitize_hex_color($_POST['neon_color'] ?? '#ff00ff'),
            'price' => (float) wc_format_decimal($_POST['neon_estimated_price'] ?? 0),
        ];
        $data['unique_key'] = md5(json_encode($data['neon']).microtime());
        return $data;
    }

    function cart_line_meta($item_data, $cart_item){
        if(isset($cart_item['neon'])){
            $n=$cart_item['neon'];
            $item_data[]=['key'=>__('Text','neon'),'value'=>$n['text']];
            $item_data[]=['key'=>__('Width','neon'),'value'=>$n['size'].' in'];
            $item_data[]=['key'=>__('Color','neon'),'value'=>$n['color']];
        }
        return $item_data;
    }

    function order_item_meta($item,$cart_item_key,$values,$order){
        if(isset($values['neon'])){
            foreach(['text'=>'Neon Text','size'=>'Neon Width (in)','color'=>'Neon Color','font'=>'Neon Font'] as $k=>$label){
                if(isset($values['neon'][$k])) $item->add_meta_data($label, $values['neon'][$k]);
            }
        }
    }

    function override_price($cart){
        if ( is_admin() && ! defined( 'DOING_AJAX' ) ) return;
        foreach($cart->get_cart() as $ci){
            if(isset($ci['neon']['price']) && $ci['neon']['price']>0){
                $ci['data']->set_price( (float)$ci['neon']['price'] );
            }
        }
    }
}
new Neon_Sign_Customizer_PRO();
