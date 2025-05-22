<?php
/**
 * Plugin Name: Stock Video Library
 * Description: Display and sell stock videos with WooCommerce integration. Adds a Stock Video post type, a video grid, and connects videos to WooCommerce products. Uses a clean stacked layout: video at the top, product info below. Image gallery is hidden when video is present.
 * Version: 1.4.1
 * Author: Your Name
 */

if (!defined('ABSPATH')) exit;

// 1. Register Custom Post Type for Stock Videos
function svl_register_stock_video_cpt() {
    $args = [
        'label' => 'Stock Videos',
        'public' => true,
        'menu_icon' => 'dashicons-format-video',
        'supports' => ['title', 'editor', 'thumbnail'],
        'has_archive' => false,
        'show_in_menu' => true,
        'rewrite' => ['slug' => 'stock-video'],
        'show_in_rest' => true,
    ];
    register_post_type('stock_video', $args);
}
add_action('init', 'svl_register_stock_video_cpt');

// 2. Add Meta Boxes for Video Sources
function svl_add_video_meta_boxes() {
    add_meta_box('svl_video_sources', 'Video Sources', 'svl_video_sources_callback', 'stock_video', 'normal', 'default');
}
add_action('add_meta_boxes', 'svl_add_video_meta_boxes');

function svl_video_sources_callback($post) {
    $vadoo = get_post_meta($post->ID, '_svl_vadoo_embed', true);
    $publit = get_post_meta($post->ID, '_svl_publit_url', true);
    $gdrive = get_post_meta($post->ID, '_svl_gdrive_url', true);
    $linked_product = get_post_meta($post->ID, '_svl_linked_product_id', true);
    ?>
    <p>
        <label for="svl_vadoo_embed"><strong>Vadoo.tv Embed Code:</strong></label><br>
        <textarea name="svl_vadoo_embed" style="width:100%;height:60px;"><?php echo esc_textarea($vadoo); ?></textarea>
    </p>
    <p>
        <label for="svl_publit_url"><strong>Publit.io MP4 URL:</strong></label><br>
        <input type="text" name="svl_publit_url" style="width:100%;" value="<?php echo esc_attr($publit); ?>">
    </p>
    <p>
        <label for="svl_gdrive_url"><strong>Google Drive URL:</strong></label><br>
        <input type="text" name="svl_gdrive_url" style="width:100%;" value="<?php echo esc_attr($gdrive); ?>">
    </p>
    <p>
        <label for="svl_linked_product_id"><strong>Link to WooCommerce Product (optional):</strong></label><br>
        <?php
        if (class_exists('WooCommerce')) {
            $products = get_posts(['post_type' => 'product', 'numberposts' => -1, 'post_status' => 'publish', 'orderby' => 'title', 'order' => 'ASC']);
            echo '<select name="svl_linked_product_id" style="width:100%;">';
            echo '<option value="">— None —</option>';
            foreach($products as $p) {
                $sel = ($linked_product == $p->ID) ? 'selected' : '';
                echo '<option value="'.$p->ID.'" '.$sel.'>'.esc_html($p->post_title).'</option>';
            }
            echo '</select>';
        } else {
            echo 'WooCommerce not active.';
        }
        ?>
    </p>
    <p style="color: #555;">
        <em>Fill in at least one video source field.<br>
        <strong>Tip:</strong> Use the dropdown in the WooCommerce product editor to link to this Stock Video.</em>
    </p>
    <?php
}

function svl_save_video_meta($post_id) {
    if (isset($_POST['svl_vadoo_embed']))
        update_post_meta($post_id, '_svl_vadoo_embed', $_POST['svl_vadoo_embed']);
    if (isset($_POST['svl_publit_url']))
        update_post_meta($post_id, '_svl_publit_url', $_POST['svl_publit_url']);
    if (isset($_POST['svl_gdrive_url']))
        update_post_meta($post_id, '_svl_gdrive_url', $_POST['svl_gdrive_url']);
    if (isset($_POST['svl_linked_product_id']))
        update_post_meta($post_id, '_svl_linked_product_id', $_POST['svl_linked_product_id']);
}
add_action('save_post', 'svl_save_video_meta');

// 3. Add a custom product data tab for linking Stock Videos
add_filter('woocommerce_product_data_tabs', function($tabs){
    $tabs['svl_stock_video_tab'] = [
        'label'    => __('Stock Video Link', 'stock-video-library'),
        'target'   => 'svl_stock_video_tab_content',
        'priority' => 60,
        'class'    => [],
    ];
    return $tabs;
});

add_action('woocommerce_product_data_panels', function() {
    global $post;
    $selected = get_post_meta($post->ID, '_linked_stock_video_id', true);
    $videos = get_posts([
        'post_type' => 'stock_video',
        'posts_per_page' => -1,
        'post_status' => 'publish',
        'orderby' => 'title',
        'order' => 'ASC'
    ]);
    ?>
    <div id="svl_stock_video_tab_content" class="panel woocommerce_options_panel">
        <p class="form-field">
            <label for="svl_linked_stock_video"><?php _e('Linked Stock Video', 'stock-video-library'); ?></label>
            <select id="svl_linked_stock_video" name="svl_linked_stock_video">
                <option value="">— None —</option>
                <?php foreach ($videos as $video): ?>
                    <option value="<?php echo esc_attr($video->ID); ?>" <?php selected($selected, $video->ID); ?>>
                        <?php echo esc_html($video->post_title); ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <span class="description"><?php _e('Select a Stock Video to display on the product page.', 'stock-video-library'); ?></span>
        </p>
    </div>
    <?php
});

add_action('woocommerce_process_product_meta', function($post_id) {
    if (isset($_POST['svl_linked_stock_video'])) {
        update_post_meta($post_id, '_linked_stock_video_id', sanitize_text_field($_POST['svl_linked_stock_video']));
        // Optionally sync the reverse relationship
        $stock_video_id = intval($_POST['svl_linked_stock_video']);
        if ($stock_video_id) {
            update_post_meta($stock_video_id, '_svl_linked_product_id', $post_id);
        }
    }
});

// 4. Show the video on the product page (above info), and hide gallery if video is present
add_action('woocommerce_before_single_product_summary', function() {
    global $post;
    $stock_video_id = get_post_meta($post->ID, '_linked_stock_video_id', true);
    if ($stock_video_id) {
        $vadoo = get_post_meta($stock_video_id, '_svl_vadoo_embed', true);
        $publit = get_post_meta($stock_video_id, '_svl_publit_url', true);
        $gdrive = get_post_meta($stock_video_id, '_svl_gdrive_url', true);
        echo '<div class="svl-product-video">';
        if (!empty($vadoo)) {
            echo $vadoo;
        } elseif (!empty($publit)) {
            echo '<video src="'.esc_url($publit).'" controls style="width:100%;max-width:900px;"></video>';
        } elseif (!empty($gdrive)) {
            echo '<video src="'.esc_url($gdrive).'" controls style="width:100%;max-width:900px;"></video>';
        } else {
            if (has_post_thumbnail($stock_video_id)) {
                echo get_the_post_thumbnail($stock_video_id, 'large');
            } else {
                echo '<div style="background:#eee;width:100%;height:360px;display:flex;align-items:center;justify-content:center;">No Video Available</div>';
            }
        }
        echo '</div>';
    }
}, 5); // ensure it's above the summary

// 5. CSS for stacked layout and hiding the gallery if video is present
add_action('wp_head', function() {
    ?>
    <style>
    /* 1. Force stacked layout: video then info, centered */
    .woocommerce div.product .product {
        display: block !important;
    }
    .woocommerce div.product .svl-product-video,
    .woocommerce div.product .summary {
        width: 100% !important;
        max-width: 700px;
        margin-left: auto !important;
        margin-right: auto !important;
        float: none !important;
        display: block !important;
        box-sizing: border-box;
    }
    .woocommerce div.product .svl-product-video {
        margin-top: 2em;
        margin-bottom: 1.5em;
        border-radius: 12px;
        overflow: hidden;
        background: #000;
        box-shadow: 0 2px 16px rgba(0,0,0,0.07);
        padding: 0;
    }
    .woocommerce div.product .svl-product-video video,
    .woocommerce div.product .svl-product-video iframe {
        width: 100% !important;
        height: auto;
        background: #000;
        border-radius: 0;
        display: block;
    }
    .woocommerce div.product .summary {
        margin-top: 0 !important;
        margin-bottom: 2em !important;
        padding: 2em 1.5em 1em 1.5em;
        background: #fff;
        border-radius: 12px;
        box-shadow: 0 2px 16px rgba(0,0,0,0.04);
    }
    @media (max-width: 800px) {
        .woocommerce div.product .svl-product-video,
        .woocommerce div.product .summary {
            max-width: 100vw;
            border-radius: 0;
            box-shadow: none;
            padding: 1em 0.5em;
        }
    }

    /* 2. Hide the gallery ONLY if the video is present */
    .svl-product-video + .woocommerce-product-gallery,
    .svl-product-video ~ .woocommerce-product-gallery,
    .woocommerce div.product .svl-product-video ~ .woocommerce-product-gallery,
    .woocommerce div.product .svl-product-video + .woocommerce-product-gallery {
        display: none !important;
    }
    /* Fallback: hide gallery if video is present anywhere */
    .woocommerce div.product .svl-product-video ~ .woocommerce-product-gallery {
        display: none !important;
    }
    </style>
    <script>
    // Extra fallback: JS to hide gallery when video is present
    document.addEventListener('DOMContentLoaded', function() {
      if (document.querySelector('.svl-product-video')) {
        var gallery = document.querySelector('.woocommerce-product-gallery');
        if (gallery) gallery.style.display = 'none';
      }
    });
    </script>
    <?php
});

// 6. [stock_video_grid] Shortcode for Video Grid Page (with product linking)
function svl_stock_video_grid_shortcode($atts) {
    $args = [
        'post_type' => 'stock_video',
        'posts_per_page' => -1,
        'post_status' => 'publish',
    ];
    $videos = new WP_Query($args);
    ob_start();
    ?>
    <div class="svl-stock-video-grid" style="display: flex; flex-wrap: wrap; gap: 24px;">
        <?php while ($videos->have_posts()): $videos->the_post();
            $post_id = get_the_ID();
            $vadoo = get_post_meta($post_id, '_svl_vadoo_embed', true);
            $publit = get_post_meta($post_id, '_svl_publit_url', true);
            $gdrive = get_post_meta($post_id, '_svl_gdrive_url', true);

            // Find the WooCommerce product that links to this Stock Video
            $product_link = '#';
            $linked_products = get_posts([
                'post_type'   => 'product',
                'post_status' => 'publish',
                'meta_query'  => [
                    [
                        'key'   => '_linked_stock_video_id',
                        'value' => $post_id,
                    ]
                ],
                'posts_per_page' => 1
            ]);
            if (!empty($linked_products)) {
                $product_link = get_permalink($linked_products[0]->ID);
            }

            ?>
            <div class="svl-video-item" style="width:320px;cursor:pointer;" onclick="window.location='<?php echo esc_url($product_link); ?>'">
                <div style="position:relative;">
                    <?php if (!empty($vadoo)): ?>
                        <div class="svl-vadoo-thumb" style="width:100%;height:180px;overflow:hidden;">
                            <?php echo $vadoo; ?>
                        </div>
                    <?php elseif (!empty($publit)): ?>
                        <video src="<?php echo esc_url($publit); ?>" muted preload="metadata" loop style="width:100%;height:180px;object-fit:cover;border-radius:8px;"></video>
                    <?php elseif (!empty($gdrive)): ?>
                        <video src="<?php echo esc_url($gdrive); ?>" muted preload="metadata" loop style="width:100%;height:180px;object-fit:cover;border-radius:8px;"></video>
                    <?php elseif (has_post_thumbnail()): ?>
                        <?php the_post_thumbnail('medium', ['style'=>'width:100%;height:180px;object-fit:cover;border-radius:8px;']); ?>
                    <?php else: ?>
                        <div style="width:100%;height:180px;background:#eee;display:flex;align-items:center;justify-content:center;color:#999;">No Preview</div>
                    <?php endif; ?>
                </div>
                <h3 style="margin:8px 0 0 0;font-size:1.1em;"><?php the_title(); ?></h3>
            </div>
        <?php endwhile; wp_reset_postdata(); ?>
    </div>
    <script>
    document.addEventListener('DOMContentLoaded', function(){
        document.querySelectorAll('.svl-video-item video').forEach(function(v){
            v.addEventListener('mouseenter', () => v.play());
            v.addEventListener('mouseleave', () => { v.pause(); v.currentTime = 0; });
        });
        document.querySelectorAll('.svl-video-item video').forEach(function(v){
            v.muted = true;
        });
    });
    </script>
    <?php
    return ob_get_clean();
}
add_shortcode('stock_video_grid', 'svl_stock_video_grid_shortcode');

// 7. Custom Shop Page Override: Display Grid Instead of Products
add_filter('template_include', function($template) {
    if (function_exists('is_shop') && is_shop()) {
        $custom = plugin_dir_path(__FILE__) . 'templates/archive-product.php';
        if (file_exists($custom)) return $custom;
    }
    return $template;
});

// 8. Create template file on plugin activation if not exists
register_activation_hook(__FILE__, function() {
    $tpl_dir = plugin_dir_path(__FILE__).'templates/';
    if (!file_exists($tpl_dir)) {
        mkdir($tpl_dir, 0755, true);
    }
    $archive_path = $tpl_dir.'archive-product.php';
    if (!file_exists($archive_path)) {
        file_put_contents($archive_path, "<?php\nget_header(); ?>\n<main id=\"main\" class=\"site-main\">\n    <h1>Stock Videos</h1>\n    <?php echo do_shortcode('[stock_video_grid]'); ?>\n</main>\n<?php get_footer(); ?>\n");
    }
});