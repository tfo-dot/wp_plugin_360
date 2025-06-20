<?php
/**
 * Plugin Name: WooCommerce 360 Product View
 * Description: Adds a 360° rotatable product image viewer to WooCommerce product pages using a sprite sheet.
 * Version: 1.6
 * Author: tfo.
 */

if (!defined('ABSPATH')) exit;

// Enqueue JS and CSS
add_action('wp_enqueue_scripts', 'wc360_enqueue_scripts');
function wc360_enqueue_scripts() {
    // Enqueue the ThreeSixty plugin script
    wp_enqueue_script('threesixty', plugin_dir_url(__FILE__) . 'assets/threesixty.min.js', ['jquery'], null, true);
    // Enqueue the custom style for the drag indicator
    wp_enqueue_style('wc360-style', plugin_dir_url(__FILE__) . 'assets/style.css'); // Make sure this CSS file exists
}

// Display viewer on single product page
// Set a lower priority (e.g., 5 or 1) to make it appear before other elements
// like woocommerce_show_product_sale_flash (priority 10) or default images (priority 20).
add_action('woocommerce_before_single_product_summary', 'wc360_display_viewer', 1); // Changed priority to 1
function wc360_display_viewer() {
    global $post;

    $sprite_url = get_post_meta($post->ID, '_wc360_sprite_url', true);
    $single_frame_width = get_post_meta($post->ID, '_wc360_frame_width', true);
    $single_frame_height = get_post_meta($post->ID, '_wc360_frame_height', true);
    $sprite_sheet_width = get_post_meta($post->ID, '_wc360_sheet_width', true);
    $sprite_sheet_height = get_post_meta($post->ID, '_wc360_sheet_height', true);

    // Exit if sprite URL or dimensions are missing
    if (empty($sprite_url) || empty($single_frame_width) || empty($single_frame_height) || empty($sprite_sheet_width) || empty($sprite_sheet_height)) {
        // If your 360 viewer has no data, you might want the default WooCommerce gallery to show.
        add_action('woocommerce_before_single_product_summary', 'woocommerce_show_product_images', 20);
        return;
    }

    // Remove default gallery *only if* your custom viewer will be displayed
    remove_action('woocommerce_before_single_product_summary', 'woocommerce_show_product_images', 20);
    remove_action('woocommerce_before_single_product_summary', 'woocommerce_show_product_sale_flash', 10); // Optionally remove sale flash if you want viewer to be *first* thing.

    // Output HTML Structure for the viewer
    // Added wc360-indicator-wrapper for positioning the drag indicator
    echo '<div id="product360" class="threesixty woocommerce-product-gallery woocommerce-product-gallery--with-images woocommerce-product-gallery--columns-4 images" style="height:' . esc_attr($single_frame_height) . 'px; width:' . esc_attr($single_frame_width) . 'px; background-image: url(' . esc_url($sprite_url) . '); position: relative;">'; // Added position: relative here
    echo '<div class="wc360-drag-indicator"></div>'; // The drag indicator element
    echo '</div>';

    // Prepare the JavaScript for inline loading
    $js_code = "
        jQuery(document).ready(function($) {
            $('#product360').threesixty({
                dragAxis: 'x',
                spriteDim: { x: " . esc_js($single_frame_width) . ", y: " . esc_js($single_frame_height) . " },
                spriteSheetDim: { x: " . esc_js($sprite_sheet_width) . ", y: " . esc_js($sprite_sheet_height) . " },
                sensitivity: 100
            });

            // Hide the indicator after a few seconds, or on first drag
            var indicator = $('.wc360-drag-indicator');
            setTimeout(function() {
                indicator.fadeOut('slow');
            }, 3000); // Fades out after 3 seconds

            $('#product360').on('mousedown touchstart', function() {
                indicator.stop(true, true).fadeOut('fast'); // Immediately hide on user interaction
            });
        });
    ";

    wp_add_inline_script('threesixty', $js_code);
}


add_action('add_meta_boxes', 'wc360_add_meta_box');
function wc360_add_meta_box() {
    add_meta_box(
        'wc360_images',
        '360° Product Sprite Sheet & Dimensions',
        'wc360_meta_box_callback',
        'product',
        'side'
    );
}

function wc360_meta_box_callback($post) {
    wp_nonce_field('wc360_save_images', 'wc360_images_nonce');
    $sprite_url = get_post_meta($post->ID, '_wc360_sprite_url', true);
    $single_frame_width = get_post_meta($post->ID, '_wc360_frame_width', true);
    $single_frame_height = get_post_meta($post->ID, '_wc360_frame_height', true);
    $sprite_sheet_width = get_post_meta($post->ID, '_wc360_sheet_width', true);
    $sprite_sheet_height = get_post_meta($post->ID, '_wc360_sheet_height', true);
    ?>

    <div id="wc360-sprite-wrapper">
        <?php
        if (!empty($sprite_url)) {
            echo "<div style='display:inline-block;margin:2px;'><img src='{$sprite_url}' style='width:60px;height:60px;max-width: none;'></div>";
        } else {
             echo 'No sprite sheet uploaded.';
        }
        ?>
    </div>
    <input type="hidden" id="wc360_sprite_url" name="wc360_sprite_url" value="<?php echo esc_attr($sprite_url); ?>" />
    <button type="button" class="button" id="wc360_upload_sprite_button">Upload 360 Sprite Sheet</button>
    <button type="button" class="button" id="wc360_remove_sprite_button" style="<?php echo empty($sprite_url) ? 'display:none;' : ''; ?>">Remove Sprite Sheet</button>

    <hr/> <h4>Sprite Dimensions (in pixels)</h4>
    <p>
        <label for="wc360_frame_width">Single Frame Width:</label><br/>
        <input type="number" id="wc360_frame_width" name="wc360_frame_width" value="<?php echo esc_attr($single_frame_width); ?>" style="width: 100%;" min="1" step="1" placeholder="e.g., 400" />
    </p>
    <p>
        <label for="wc360_frame_height">Single Frame Height:</label><br/>
        <input type="number" id="wc360_frame_height" name="wc360_frame_height" value="<?php echo esc_attr($single_frame_height); ?>" style="width: 100%;" min="1" step="1" placeholder="e.g., 400" />
    </p>
    <p>
        <label for="wc360_sheet_width">Sprite Sheet Total Width:</label><br/>
        <input type="number" id="wc360_sheet_width" name="wc360_sheet_width" value="<?php echo esc_attr($sprite_sheet_width); ?>" style="width: 100%;" min="1" step="1" placeholder="e.g., 4000" />
    </p>
    <p>
        <label for="wc360_sheet_height">Sprite Sheet Total Height:</label><br/>
        <input type="number" id="wc360_sheet_height" name="wc360_sheet_height" value="<?php echo esc_attr($sprite_sheet_height); ?>" style="width: 100%;" min="1" step="1" placeholder="e.g., 400" />
    </p>


    <script>
    jQuery(document).ready(function($){
        var frame_sprite;
        $('#wc360_upload_sprite_button').on('click', function(e){
            e.preventDefault();
            if (frame_sprite) { frame_sprite.open(); return; }
            frame_sprite = wp.media({
                title: 'Select 360° Sprite Sheet',
                button: { text: 'Use This Sprite Sheet' },
                multiple: false // Only select one sprite sheet
            });

            frame_sprite.on('select', function(){
                var attachment = frame_sprite.state().get('selection').first().toJSON();
                $('#wc360_sprite_url').val(attachment.url);
                $('#wc360-sprite-wrapper').html('<div style="display:inline-block;margin:2px;"><img src="'+attachment.url+'" style="width:60px;height:60px;max-width: none;"></div>');
                $('#wc360_remove_sprite_button').show();

                // Optional: Try to auto-populate sprite sheet dimensions if available from attachment data
                if (attachment.width && attachment.height) {
                    $('#wc360_sheet_width').val(attachment.width);
                    $('#wc360_sheet_height').val(attachment.height);
                }
                // Single frame dimensions typically need manual input as they vary per sprite arrangement
            });

            frame_sprite.open();
        });

        $('#wc360_remove_sprite_button').on('click', function(e){
            e.preventDefault();
            $('#wc360_sprite_url').val('');
            $('#wc360-sprite-wrapper').html('No sprite sheet uploaded.');
            $(this).hide();
            // Clear dimension fields as well
            $('#wc360_frame_width').val('');
            $('#wc360_frame_height').val('');
            $('#wc360_sheet_width').val('');
            $('#wc360_sheet_height').val('');
        });
    });
    </script>
    <?php
}

add_action('save_post', 'wc360_save_meta_box_data');
function wc360_save_meta_box_data($post_id) {
    if (!isset($_POST['wc360_images_nonce']) || !wp_verify_nonce($_POST['wc360_images_nonce'], 'wc360_save_images')) return;
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
    if (!current_user_can('edit_post', $post_id)) return;

    // Save sprite URL
    if (isset($_POST['wc360_sprite_url'])) {
        update_post_meta($post_id, '_wc360_sprite_url', sanitize_url($_POST['wc360_sprite_url']));
    }

    // Save dimension fields, sanitizing as integers
    if (isset($_POST['wc360_frame_width'])) {
        update_post_meta($post_id, '_wc360_frame_width', intval($_POST['wc360_frame_width']));
    }
    if (isset($_POST['wc360_frame_height'])) {
        update_post_meta($post_id, '_wc360_frame_height', intval($_POST['wc360_frame_height']));
    }
    if (isset($_POST['wc360_sheet_width'])) {
        update_post_meta($post_id, '_wc360_sheet_width', intval($_POST['wc360_sheet_width']));
    }
    if (isset($_POST['wc360_sheet_height'])) {
        update_post_meta($post_id, '_wc360_sheet_height', intval($_POST['wc360_sheet_height']));
    }
}