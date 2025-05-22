<?php
/* Template for WooCommerce Single Product Override - Shows Video and variations */
get_header();
global $product;

// Find linked stock video by matching SKU or custom field, or use post meta
$stock_video_id = get_post_meta(get_the_ID(), '_linked_stock_video_id', true);
if ($stock_video_id) {
    $vadoo = get_post_meta($stock_video_id, '_svl_vadoo_embed', true);
    $publit = get_post_meta($stock_video_id, '_svl_publit_url', true);
    $gdrive = get_post_meta($stock_video_id, '_svl_gdrive_url', true);
}
?>
<main id="main" class="site-main">
    <div class="product-video">
        <?php
        if (!empty($vadoo)) {
            echo $vadoo;
        } elseif (!empty($publit)) {
            echo '<video src="' . esc_url($publit) . '" controls style="width:100%"></video>';
        } elseif (!empty($gdrive)) {
            echo '<video src="' . esc_url($gdrive) . '" controls style="width:100%"></video>';
        }
        ?>
    </div>
    <div class="product-summary">
        <?php woocommerce_template_single_title(); ?>
        <?php woocommerce_template_single_price(); ?>
        <?php woocommerce_template_single_add_to_cart(); ?>
        <?php woocommerce_template_single_meta(); ?>
        <?php woocommerce_template_single_excerpt(); ?>
    </div>
</main>
<?php get_footer(); ?>
