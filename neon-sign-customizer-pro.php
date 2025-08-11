<?php
/**
 * Plugin Name: Neon Sign Customizer PRO (WooCommerce)
 * Description: Exact neon customizer UI (preview + inches + price) embedded on product page, with dynamic cart price.
 * Version: 1.3.0
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
        add_action('wp', [$this,'setup_front']);

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
        wp_enqueue_style('neon-pro', plugins_url('assets/css/customizer.css', __FILE__), [], '1.3.0');
        wp_enqueue_script('neon-pro', plugins_url('assets/js/customizer.js', __FILE__), ['jquery'], '1.3.0', true);
    }

    function setup_front(){
        if (!function_exists('is_product') || !is_product()) return;
        global $post;
        if(!$post) return;
        if (get_post_meta($post->ID, self::META_ENABLED, true) !== 'yes') return;

        remove_action('woocommerce_before_single_product_summary', 'woocommerce_show_product_images', 20);
        remove_action('woocommerce_single_product_summary', 'woocommerce_template_single_price', 10);
        add_action('woocommerce_before_add_to_cart_button', [$this,'render'], 5);
    }

    function render(){
        global $product; if (!$product) return;
        if (get_post_meta($product->get_id(), self::META_ENABLED, true) !== 'yes') return;

        $base  = get_post_meta($product->get_id(), self::META_BASE_PRICE, true) ?: '112';
        $sizes = get_post_meta($product->get_id(), self::META_SIZES, true) ?: '20,29,40,60,79,99';
        $bg    = get_post_meta($product->get_id(), self::META_BG, true);
        $maxc  = get_post_meta($product->get_id(), self::META_MAX, true) ?: 21;
        $sizes_arr = array_filter(array_map('absint', explode(',', $sizes)));
        if(empty($sizes_arr)) $sizes_arr=[20,29,40,60,79,99];
        $sizes_attr = implode(',', $sizes_arr);
        $fonts = [
            "'Pacifico', cursive" => 'Pacifico',
            "'Arial', sans-serif" => 'Arial',
            "'Courier New', monospace" => 'Courier',
            "'Lobster', cursive" => 'Lobster',
        ];
        $font_keys = array_keys($fonts);
        $first_font = $font_keys[0];
        $colors = [
            '#ff00ff' => 'Pink',
            '#00ffff' => 'Cyan',
            '#ffff00' => 'Yellow',
            '#ffffff' => 'White',
        ];
        $color_keys = array_keys($colors);
        $first_color = $color_keys[0];

        ?>
        <div id="neon-customizer" class="nf-wrap" data-base="<?php echo esc_attr($base); ?>" data-max="<?php echo esc_attr($maxc); ?>" data-bg="<?php echo esc_attr($bg); ?>" data-sizes="<?php echo esc_attr($sizes_attr); ?>">
            <div class="nf-grid">
                <div class="nf-mockup"<?php if($bg){ echo ' style="background-image:url('.esc_url($bg).')"'; } ?>>
                    <div id="nf-preview" class="nf-neon">Let’s Create</div>
                </div>
                <div class="nf-panel">
                    <h2 class="nf-title"><?php esc_html_e('Create your Neon Sign','neon'); ?></h2>
                    <div id="nf-price" class="nf-price">$<?php echo esc_html(number_format((float)$base,2)); ?></div>
                    <div class="nf-divider"></div>

                    <div class="nf-field">
                        <span class="nf-label"><?php esc_html_e('WIDTH','neon'); ?></span>
                        <div id="nf-width" class="nf-pills">
                            <?php foreach($sizes_arr as $i=>$in): $cm = round($in*2.5/5)*5; ?>
                                <button type="button" role="button" aria-pressed="<?php echo $i===0?'true':'false'; ?>" class="nf-chip<?php echo $i===0?' is-active':''; ?>" data-in="<?php echo esc_attr($in); ?>"><?php echo esc_html($in.'" / '.$cm.' cm'); ?></button>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <div class="nf-help">
                        <span><?php esc_html_e('MAX 7 LETTERS PER LINE','neon'); ?></span>
                        <span id="nf-count" class="nf-count"><?php echo esc_html($maxc.' characters left'); ?></span>
                    </div>

                    <div class="nf-field">
                        <span class="nf-label"><?php esc_html_e('WRITE YOUR TEXT','neon'); ?></span>
                        <textarea id="nf-text" name="neon_text" maxlength="<?php echo esc_attr($maxc); ?>"></textarea>
                        <div id="nf-warning" class="nf-warning" aria-live="polite"></div>
                    </div>

                    <div class="nf-field">
                        <span class="nf-label"><?php esc_html_e('CHOOSE YOUR FONT','neon'); ?></span>
                        <div id="nf-fonts" class="nf-fonts">
                            <?php $fi=0; foreach($fonts as $val=>$label): ?>
                                <button type="button" role="button" aria-pressed="<?php echo $fi===0?'true':'false'; ?>" class="nf-chip<?php echo $fi===0?' is-active':''; ?>" data-font="<?php echo esc_attr($val); ?>" style="font-family:<?php echo esc_attr($val); ?>"><?php echo esc_html($label); ?></button>
                            <?php $fi++; endforeach; ?>
                        </div>
                    </div>

                    <div class="nf-field">
                        <span class="nf-label"><?php esc_html_e('CHOOSE YOUR COLOR','neon'); ?></span>
                        <div id="nf-colors" class="nf-colors">
                            <?php $ci=0; foreach($colors as $val=>$name): ?>
                                <button type="button" role="button" title="<?php echo esc_attr($name); ?>" aria-pressed="<?php echo $ci===0?'true':'false'; ?>" class="nf-color<?php echo $ci===0?' is-active':''; ?>" data-color="<?php echo esc_attr($val); ?>" style="--c:<?php echo esc_attr($val); ?>"></button>
                            <?php $ci++; endforeach; ?>
                        </div>
                    </div>

                    <input type="hidden" id="neon_width_in" name="neon_width_in" value="<?php echo esc_attr($sizes_arr[0]); ?>">
                    <input type="hidden" id="neon_font" name="neon_font" value="<?php echo esc_attr($first_font); ?>">
                    <input type="hidden" id="neon_color" name="neon_color" value="<?php echo esc_attr($first_color); ?>">
                    <input type="hidden" id="neon_estimated_price" name="neon_estimated_price" value="<?php echo esc_attr(number_format((float)$base,2,'.','')); ?>">
                </div>
            </div>
        </div>
        <?php
    }

    function cart_item_data($data, $product_id, $variation_id){
        $fields = ['neon_text','neon_width_in','neon_font','neon_color','neon_estimated_price'];
        $has = false; foreach($fields as $f){ if(isset($_POST[$f]) && $_POST[$f] !== ''){ $has=true; break; } }
        if(!$has) return $data;

        $data['neon'] = [
            'text'  => sanitize_text_field($_POST['neon_text'] ?? ''),
            'size'  => absint($_POST['neon_width_in'] ?? 0),
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
