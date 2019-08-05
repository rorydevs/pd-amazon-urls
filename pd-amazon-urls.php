<?php
/**
 * Plugin Name: PD Amazon Redirect URLs
 * Description: Custom review and product shortcode URLs for Amazon
 * Version: 1.0
 * Author: Rory M
 *
 * Text Domain: pd-amazon-redirect-urls
 *
 * @package PD-Amazon-Redirect-URLs
 */

/**
 * Instantiates the menu option under `Settings`
 *
 * @return void
 */
function pd_amazon_admin_menu() {        
    add_options_page( 'Amazon Link Settings', 'Amazon Redirect URLs', 'administrator', 'pd-amazon-redirect-urls', '_settings' );    
}
add_action( 'admin_menu', 'pd_amazon_admin_menu' );

/**
 * Settings form
 *
 * @return void
 */
function _settings() {
    // Update options if there is POST data
    if($_POST) {
        update_option('pd_short_url_prefix', filter_input(INPUT_POST, 'pd_short_url_prefix'));
        update_option('pd_short_url_landing_page', filter_input(INPUT_POST, 'pd_short_url_landing_page'));
        update_option('pd_show_buy_now_link', filter_input(INPUT_POST ,'pd_show_buy_now_link'));
    }
    // Get variables
    $pd_short_url_prefix = get_option('pd_short_url_prefix');
    $pd_short_url_landing_page = get_option('pd_short_url_landing_page');
    $pd_show_buy_now_link = get_option('pd_show_buy_now_link');
    $product_checked = '';
    $review_checked = '';
    $buy_now_yes = '';
    $buy_now_no = '';
    // Set the 'product' value as checked
    if($pd_short_url_landing_page == 'product') {
        $product_checked = 'checked="checked"';
    }
    // Set the 'review' value as checked
    if($pd_short_url_landing_page == 'review') {
        $review_checked = 'checked="checked"';
    }

    if($pd_show_buy_now_link == 1) {
        $buy_now_yes = 'checked="checked"';
    } else {
        $buy_now_no = 'checked="checked"';
    }
?>
    <div class="wrap">
        <h1>Amazon Short URL Settings</h1>
        <p>Control settings for your Amazon Short URLs.</p>
        <form method="post" action="">
            <table class="form-table">
                <tr class="form-field form-required" style="max-width: 25em;">
                    <th scope="row"><label for="pd_short_url_prefix">Short URL Prefix</label></th>
                    <td><input type="text" id="pd_short_url_prefix" name="pd_short_url_prefix" value="<?php echo $pd_short_url_prefix ?>" style="max-width: 25em; width: 100%;" />
                        <p class="description">This is a prefix to help identify your short link. Try something short like "AMZ" or "WP".</p></td>
                </tr>
                <tr class="form-field form-required">
                    <th scope="row"><label for="pd_short_url_prefix">Landing Page</label></th>
                    <td>
                        <label style="margin-right: 15px"><input type="radio" class="radio" name="pd_short_url_landing_page" value="product" <?php echo $product_checked ?> /> Product Page</label> 
                        <label style="margin-right: 15px"><input type="radio" class="radio" name="pd_short_url_landing_page" value="review" <?php echo $review_checked ?> /> Review Page</label> 
                        <p class="description">The landing page after a user follows the link. The short link and QR code will default to the Amazon product page.</p>
                    </td>
                </tr>
                <tr class="form-field form-required">
                    <th scope="row"><label for="pd_show_buy_now_link">Show "Buy on Amazon" Link</label></th>
                    <td><label style="margin-right: 15px"><input type="radio" class="radio" name="pd_show_buy_now_link" value="1" <?php echo $buy_now_yes ?> /> Yes</label> 
                        <label style="margin-right: 15px"><input type="radio" class="radio" name="pd_show_buy_now_link" value="0" <?php echo $buy_now_no ?> /> No</label>
                        <p class="description">Displays a customizable "Buy Now on Amazon" link underneath the Add to Cart button.</p>
                    </td>
                    </tr>
            </table>
            <?php submit_button(); ?>
        </form>
    </div>
<?php
}



/**
 * Short URL finder for QR Codes
 * If the URI begins with "VK", decode the string and find the product ID
 * then redirect the user to the appropriate product
 */
add_action('init', 'shortcode_parse');
function shortcode_parse() {

    define('AMAZON_PRODUCT_URL', 'https://www.amazon.com/dp/');
    define('AMAZON_REVIEW_URL', 'https://www.amazon.com/product-reviews/');
        
    $uri = $_SERVER['REQUEST_URI'];
    $pd_short_url_prefix = get_option('pd_short_url_prefix');
    $pd_short_url_landing_page = get_option('pd_short_url_landing_page');
    $pd_short_url_prefix_length = (int)strlen($pd_short_url_prefix) + 1;

    if($pd_short_url_prefix_length < 2 || strlen($pd_short_url_landing_page) < 1) {
        return false;
    }
        
    if(substr($uri, 0, $pd_short_url_prefix_length) == '/' . $pd_short_url_prefix) {
        
        $encoded_id = substr($uri, $pd_short_url_prefix_length);
        
        $post_id = base64_decode($encoded_id);

        $amazon_asin = get_post_meta($post_id, 'pd_amazon_asin', true);

	    if($pd_short_url_landing_page == 'product') {
            $amazon_url = AMAZON_PRODUCT_URL . $amazon_asin;
        } else if ($pd_short_url_landing_page == 'review') {
            $amazon_url = AMAZON_REVIEW_URL . $amazon_asin;
        } else {
            $amazon_url = AMAZON_PRODUCT_URL . $amazon_asin;
        }
                
        if(strlen($amazon_url) > 0) {
            
            wp_redirect($amazon_url, 302, 'Volkano');
            exit;
            
        }
        
    }
    
}

/**
 * Add extra metabox to the product page if a wpcf-amazon-url value is found
 */

function add_pd_fields_meta_box() {
    
    $post_id = get_the_ID();
    $amazon_asin = get_post_meta($post_id, 'pd_amazon_asin', true);
    
    //if(strlen($amazon_asin) > 0) {
        add_meta_box(
                'pd_meta_box', // $id
                'Amazon Product Short Links', // $title
                'pd_amazon_metabox_markup', // $callback
                'product', // $screen
                'normal', // $context
                'high' // $priority
        );
    //}
}
add_action( 'add_meta_boxes', 'add_pd_fields_meta_box' );

function pd_amazon_metabox_markup() {

    wp_nonce_field(basename(__FILE__), "pd_amazon_meta_box_nonce");

    $amazon_asin = get_post_meta(get_the_ID(), 'pd_amazon_asin', true);
?>
    <label for="pd_amazon_asin">Product ASIN on Amazon</label>
    <input type="text" name="pd_amazon_asin" id="pd_amazon_asin" value="<?php echo $amazon_asin; ?>" />
    <br/><br/>
<?php

    if(strlen($amazon_asin) > 0) {

        $pd_short_url_prefix = get_option('pd_short_url_prefix');
        $key = $pd_short_url_prefix . base64_encode(get_the_ID());
        // Clean the key of any base64 `=` leftovers
        $clean_key = str_replace('=', '', $key);
        // Generate a short URL
        $short_url = get_bloginfo('url') . '/' . $clean_key;
        // Create templated 
        $api_url = 'https://chart.googleapis.com/chart?chs=%%SIZE%%&cht=qr&chl=' . urlencode($short_url) . '&choe=UTF-8';
        // Create URLs for a 150x150 and 500x500 size image
        $img_url = str_replace('%%SIZE%%', '150x150', $api_url);
        $link_url = str_replace('%%SIZE%%', '500x500', $api_url);

        echo "<b>Links for Review Cards</b><br/>";
        echo "<b>Short URL:</b> <a href=\"$short_url\" target=\"_blank\">$short_url</a><br/>";
        echo '<b>QR Code:</b> Click for big<br/><a href="' . $link_url . '" target="_blank"><img src="' . $img_url . '" title="Volkano Product Shortlink" /></a>';

    }
    
}

function pd_save_amazon_metabox($post_id, $post, $update) {

    if (!isset($_POST["pd_amazon_meta_box_nonce"]) || !wp_verify_nonce($_POST["pd_amazon_meta_box_nonce"], basename(__FILE__))) {
        die('Nonce error');
        return $post_id;
    }

    if(!current_user_can("edit_post", $post_id)) {
        die('Permissions error');
        return $post_id;
    }

    if(defined("DOING_AUTOSAVE") && DOING_AUTOSAVE) {
        die('Doing Autosave');
        return $post_id;
    }

    $slug = "product";
    if($slug != $post->post_type) {
        die('slug != post type');
        return $post_id;
    }

    $pd_amazon_asin = filter_input(INPUT_POST, 'pd_amazon_asin');

    update_post_meta($post_id, "pd_amazon_asin", $pd_amazon_asin);

}

add_action('save_post', 'pd_save_amazon_metabox', 10, 3);

add_filter('plugin_action_links_'.plugin_basename(__FILE__), 'pd_add_plugin_page_settings_link');
function pd_add_plugin_page_settings_link( $links ) {

    $links = array_merge(array(
        '<a href="' . admin_url( 'options-general.php?pd-amazon-redirect-urls' ) . '">' . __('Settings') . '</a>'
    ), $links);

	return $links;
}

add_action( 'woocommerce_before_description', 'pd_add_buy_button_after_addtocart_button' );
function pd_add_buy_button_after_addtocart_button() {

    $amazon_asin = get_post_meta(get_the_ID(), 'pd_amazon_asin', true);
    $pd_show_buy_now_link = get_option('pd_show_buy_now_link');
    
    if(strlen($amazon_asin) > 0 && $pd_show_buy_now_link == 1) {
        
        echo '<a href="https://www.amazon.com/dp/' . $amazon_asin . '" rel="noreferrer" class="amazon pd_buy_now_on_amazon" target="_blank">Buy now on Amazon</a><br/>';
    }

}

