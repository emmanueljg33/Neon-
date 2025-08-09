<?php
/**
 * Plugin Name: Neon Sign Customizer PRO (WooCommerce)
 * Description: Neon sign configurator with live preview and dynamic WooCommerce pricing.
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
        add_action('woocommerce_before_add_to_cart_button', [$this,'render']);
        add_shortcode('neon_customizer', [$this,'shortcode']);

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
        global $post; if(!$post) return;
        if (get_post_meta($post->ID, self::META_ENABLED, true) !== 'yes') return;
        wp_enqueue_style('neon-pro', plugins_url('assets/css/customizer.css', __FILE__), [], '1.3.0');
        wp_enqueue_script('neon-pro', plugins_url('assets/js/customizer.js', __FILE__), [], '1.3.0', true);
    }

    private function html($product){
        $base  = (float) (get_post_meta($product->get_id(), self::META_BASE_PRICE, true) ?: 112);
        $sizes = get_post_meta($product->get_id(), self::META_SIZES, true) ?: '20,29,40,60,79,99';
        $bg    = get_post_meta($product->get_id(), self::META_BG, true);
        $maxc  = (int) (get_post_meta($product->get_id(), self::META_MAX, true) ?: 21);
        $sizes_arr = array_values(array_filter(array_map('absint', explode(',', $sizes))));
        $default_text  = "Let's Create";
        $default_width = $sizes_arr[0] ?? 20;

        $fonts = [
            "'Arial',sans-serif"        => ['label'=>'Arial'],
            "'Courier New',monospace"   => ['label'=>'Courier'],
            "'Pacifico',cursive"       => ['label'=>'Pacifico','url'=>'https://fonts.googleapis.com/css2?family=Pacifico&display=swap'],
            "'Lobster',cursive"        => ['label'=>'Lobster','url'=>'https://fonts.googleapis.com/css2?family=Lobster&display=swap'],
        ];
        $colors = [
            '#ff00ff' => 'Pink',
            '#00ffff' => 'Cyan',
            '#ffff00' => 'Yellow',
            '#ffffff' => 'White',
        ];

        $first_font_key = array_key_first($fonts);
        $first_color_key = array_key_first($colors);
        $price = $base + ($default_width * 0.5) + (strlen($default_text) * 2);

        ob_start();
        ?>
        <div id="neon-customizer" class="nf-wrap" data-base="<?php echo esc_attr($base); ?>" data-max="<?php echo esc_attr($maxc); ?>" data-bg="<?php echo esc_attr($bg); ?>">
          <div class="nf-grid">
            <aside class="nf-mockup">
              <div id="nf-preview" class="nf-neon"><?php echo esc_html($default_text); ?></div>
            </aside>
            <section class="nf-panel">
              <h1>Create your Neon Sign</h1>
              <div class="nf-price" id="nf-price">$<?php echo esc_html(number_format($price,2)); ?></div>

              <div class="nf-field">
                <div class="nf-label">WIDTH</div>
                <div class="nf-pills" id="nf-width">
                  <?php foreach($sizes_arr as $i=>$in): ?>
                    <button type="button" data-in="<?php echo esc_attr($in); ?>" aria-pressed="<?php echo $i===0?'true':'false'; ?>"><?php echo esc_html($in.'"'); ?></button>
                  <?php endforeach; ?>
                </div>
              </div>

              <div class="nf-help">
                <span>MAX 7 LETTERS PER LINE</span>
                <span class="nf-count" id="nf-count"><?php echo esc_html($maxc); ?> characters left</span>
              </div>

              <div class="nf-field">
                <div class="nf-label">WRITE YOUR TEXT</div>
                <textarea id="nf-text" name="neon_text" maxlength="<?php echo esc_attr($maxc); ?>" placeholder="<?php echo esc_attr($default_text); ?>"><?php echo esc_html($default_text); ?></textarea>
              </div>

              <div class="nf-field">
                <div class="nf-label">CHOOSE YOUR FONT</div>
                <div class="nf-fonts" id="nf-fonts">
                  <?php $findex=0; foreach($fonts as $css=>$info): ?>
                    <button type="button" data-font="<?php echo esc_attr($css); ?>" <?php if(!empty($info['url'])) echo 'data-font-url="'.esc_url($info['url']).'"'; ?> aria-pressed="<?php echo $findex===0?'true':'false'; ?>" style="font-family:<?php echo esc_attr($css); ?>"><?php echo esc_html($info['label']); ?></button>
                  <?php $findex++; endforeach; ?>
                </div>
              </div>

              <div class="nf-field">
                <div class="nf-label">CHOOSE YOUR COLOR</div>
                <div class="nf-colors" id="nf-colors">
                  <?php $cindex=0; foreach($colors as $hex=>$title): ?>
                    <button type="button" data-color="<?php echo esc_attr($hex); ?>" title="<?php echo esc_attr($title); ?>" aria-pressed="<?php echo $cindex===0?'true':'false'; ?>" style="--nf-color:<?php echo esc_attr($hex); ?>"></button>
                  <?php $cindex++; endforeach; ?>
                </div>
              </div>

              <input type="hidden" name="neon_width_in" id="neon_width_in" value="<?php echo esc_attr($default_width); ?>">
              <input type="hidden" name="neon_font" id="neon_font" value="<?php echo esc_attr($first_font_key); ?>">
              <input type="hidden" name="neon_color" id="neon_color" value="<?php echo esc_attr($first_color_key); ?>">
              <input type="hidden" name="neon_estimated_price" id="neon_estimated_price" value="<?php echo esc_attr(number_format($price,2,'.','')); ?>">
            </section>
          </div>
        </div>
        <?php
        return ob_get_clean();
    }

    function render(){
        global $product; if (!$product) return;
        if (get_post_meta($product->get_id(), self::META_ENABLED, true) !== 'yes') return;
        echo $this->html($product);
    }

    function shortcode(){
        global $product; if(!$product) return '';
        if (get_post_meta($product->get_id(), self::META_ENABLED, true) !== 'yes') return '';
        return $this->html($product);
    }

    function cart_item_data($data, $product_id, $variation_id){
        $fields = ['neon_text','neon_width_in','neon_font','neon_color','neon_estimated_price'];
        $has = false; foreach($fields as $f){ if(isset($_POST[$f]) && $_POST[$f] !== ''){ $has=true; break; } }
        if(!$has) return $data;

        $text  = sanitize_text_field($_POST['neon_text'] ?? '');
        $width = absint($_POST['neon_width_in'] ?? 0);
        $font  = sanitize_text_field($_POST['neon_font'] ?? '');
        $color = sanitize_hex_color($_POST['neon_color'] ?? '#ff00ff');
        $base  = (float) (get_post_meta($product_id, self::META_BASE_PRICE, true) ?: 112);
        $price = $base + ($width * 0.5) + (strlen($text) * 2);

        $data['neon'] = [
            'text'  => $text,
            'width' => $width,
            'font'  => $font,
            'color' => $color,
            'price' => $price,
        ];
        $data['unique_key'] = md5(json_encode($data['neon']).microtime());
        return $data;
    }

    function cart_line_meta($item_data, $cart_item){
        if(isset($cart_item['neon'])){
            $n=$cart_item['neon'];
            $item_data[]=['key'=>__('Text','neon'),'value'=>$n['text']];
            $item_data[]=['key'=>__('Width (in)','neon'),'value'=>$n['width']];
            $item_data[]=['key'=>__('Font','neon'),'value'=>$n['font']];
            $item_data[]=['key'=>__('Color','neon'),'value'=>$n['color']];
        }
        return $item_data;
    }

    function order_item_meta($item,$cart_item_key,$values,$order){
        if(isset($values['neon'])){
            foreach(['text'=>'Neon Text','width'=>'Neon Width (in)','color'=>'Neon Color','font'=>'Neon Font'] as $k=>$label){
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
