<?php
/*
Plugin Name: MTools Woo
Description: Tools for updating woocommerce products.
Version: 1.6
Author: Marcin Matuszkiewicz
*/

/*
 * Revision history
 * 1.0 - plugin framework
 * 1.1 - normalize title implementation
 * 1.2
 *      Add SKU to products page
 *      Add Stock Quantity @ WooCommerce Shop / Cart / Archive Pages
 *      Exclude products from a particular category on the shop page
 *      Exclude Cat @ WooCommerce Product Search Results
 *      Add link to admin order confirmation email
 *      Add Woocommerce Filter by "On Sale" to Woocommerc->Products
 * 1.3
 *      Add 'End Sale' to 'Bulk Actions' dropdown
 * 1.4
 *      Add a FAQ button to product listings and product summary
 * 1.5
 *      Include Order Notes in Admin Order Search
 * 1.6 
 *      Renamed "On Sale" filter to "M Custom Filters"
 *      Added "featured" filter to M Custom Filters
 */

// add the "MTools Settings" section in the Admin Tools menu

add_action('admin_menu', 'mtoolswoo_add_admin_menu');

function mtoolswoo_add_admin_menu() {
    add_management_page('MTools Woo Settings', 'MTools Woo Settings', 'manage_options', 'mtoolswoo_settings', 'mtoolswoo_settings_page');
}

// implement MTools setting page functionality

function mtoolswoo_settings_page() {
    // Handle form submission
    if (isset($_POST['mtoolswoo_openai_key']) && isset($_POST['mtoolswoo_model'])) {
        update_option('mtoolswoo_openai_key', sanitize_text_field($_POST['mtoolswoo_openai_key']));
        update_option('mtoolswoo_model', sanitize_text_field($_POST['mtoolswoo_model']));
        echo '<div class="updated"><p>Settings saved!</p></div>';
    }
    
    $openai_key = get_option('mtoolswoo_openai_key', '');
    $selected_model = get_option('mtoolswoo_model', '');

    // For simplicity, we'll use a hardcoded list of models. In reality, you'd want to query OpenAI's API to get this.
    $models = array('gpt-3.5-turbo', 'gpt-4', 'text-davinci-003'); // add or replace with actual models

    // Check if the "Test OpenAI" button was pressed
    $test_output = '';
    if (isset($_POST['test_openai'])) {
        $test_output = mtoolswoo_query_openai("what model are you? ");
    }

    echo '<div class="wrap">';
    echo '<h2>MTools Settings</h2>';
    echo '<form method="post" action="">';
    echo '<table class="form-table">';
    echo '<tbody>';
    echo '<tr>';
    echo '<th scope="row"><label for="mtoolswoo_openai_key">OpenAI Key</label></th>';
    echo '<td><input name="mtoolswoo_openai_key" type="text" id="mtoolswoo_openai_key" value="' . esc_attr($openai_key) . '" class="regular-text"></td>';
    echo '</tr>';
    echo '<tr>';
    echo '<th scope="row"><label for="mtoolswoo_model">Model</label></th>';
    echo '<td><select name="mtoolswoo_model" id="mtoolswoo_model">';

    foreach ($models as $model) {
        echo '<option value="' . esc_attr($model) . '"' . selected($selected_model, $model, false) . '>' . esc_html($model) . '</option>';
    }

    echo '</select></td>';
    echo '</tr>';
    echo '</tbody>';
    echo '</table>';
    echo '<p class="submit"><input type="submit" name="submit" id="submit" class="button button-primary" value="Save Changes"></p>';

    // Test OpenAI button and output textbox
    echo '<hr>';
    echo '<h3>Test OpenAI Integration</h3>';
    echo '<input type="submit" name="test_openai" class="button" value="Test OpenAI">';
    echo '<br><br>';
    echo '<textarea rows="4" cols="50" readonly>' . esc_textarea($test_output) . '</textarea>';

    echo '</form>';
    echo '</div>';
}


// make sure the plugin's settings are cleaned up upon deactivation

register_activation_hook(__FILE__, 'mtoolswoo_activate');
register_deactivation_hook(__FILE__, 'mtoolswoo_deactivate');

function mtoolswoo_activate() {
    // Initialize default settings if they don't exist
    add_option('mtoolswoo_openai_key', '');
    add_option('mtoolswoo_model', '');
}

function mtoolswoo_deactivate() {
    // Clean up settings from database
    delete_option('mtoolswoo_openai_key');
    delete_option('mtoolswoo_model');
}

/**
 * Queries the OpenAI API with a given prompt.
 *
 * This function sends a prompt to the OpenAI API and retrieves the generated
 * response based on the chosen model. It fetches the API key and model 
 * from the WordPress database. If there's an error with the CURL request,
 * the function logs the error and returns false.
 * 
 * @param string $prompt The input prompt to send to OpenAI for processing.
 * 
 * @return string|bool Returns the generated response from OpenAI or false in case of an error.
 */

 function mtoolswoo_query_openai($prompt) {
    // Fetch API key and model from the WordPress database
    $api_key = get_option('mtoolswoo_openai_key');
    $model = get_option('mtoolswoo_model');

    // Ensure that we have the necessary settings
    if (!$api_key || !$model) {
        error_log('OpenAI settings not configured in MTools Woo.');
        return false;
    }

    error_log("Full Prompt sent to OpenAI: " . $prompt);

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, 'https://api.openai.com/v1/chat/completions');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_POST, 1);

    // Set headers
    $headers = [
        'Authorization: Bearer ' . $api_key,
        'Content-Type: application/json'
    ];
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

    // Set the data payload
    $data = array(
        'model' => $model,
        'messages' => array(
            array('role' => 'user', 'content' => $prompt)
        ),
        'temperature' => 0.7
    );
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));

    $response = curl_exec($ch);

    if(curl_errno($ch)) {
        error_log('Error querying OpenAI: ' . curl_error($ch));
        return false;  // or handle the error as you see fit
    }

    curl_close($ch);

    // Assuming the OpenAI response has a 'choices' array and we want the first text result
    $responseArray = json_decode($response, true);
    // Return the content of the response, assuming the second message contains the bot's reply.
    return $responseArray['choices'][0]['message']['content'] ?? '';
}

/*
 * Add tools tab to product edit page
 */

add_filter('woocommerce_product_data_tabs', 'add_mtoolswoo_tab', 50, 1);
function add_mtoolswoo_tab($tabs) {
    $tabs['tools'] = array(
        'label'  => __('MTools', 'woocommerce'),
        'target' => 'tools_product_data',
        'class'  => array(),
    );
    return $tabs;
}

add_action('woocommerce_product_data_panels', 'mtoolswoo_tab_content');

function mtoolswoo_tab_content() {
    echo '<div id="tools_product_data" class="panel woocommerce_options_panel">';
    echo '<div class="options_group">';
    echo '<button type="button" class="button button-secondary" id="normalize-title-button">' . __('Normalize Title', 'woocommerce') . '</button>';
    echo '</div>';
    echo '</div>';
}

add_action('admin_enqueue_scripts', 'enqueue_mtoolswoo_scripts');

function enqueue_mtoolswoo_scripts() {
    wp_enqueue_script('mtoolswoo-script', plugins_url('js/normalize-title.js', __FILE__), array('jquery'), '1.0.0', true);
    wp_localize_script('mtoolswoo-script', 'normalizeTitleParams', array(
        'ajax_url' => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('mtoolswoo-normalize-title-nonce')
    ));
}

add_action('wp_ajax_mtoolswoo_normalize_title', 'mtoolswoo_handle_normalize_title');

function mtoolswoo_handle_normalize_title() {

    check_ajax_referer('mtoolswoo-normalize-title-nonce', 'nonce');

    // Get product ID from AJAX request
    if (isset($_POST['product_id'])) {
        $product_id = intval($_POST['product_id']);  // Ensure it's a number
        $product = wc_get_product($product_id);

        if ($product) {
            $title = $product->get_name();
            $prompt = "normalize to title case; keep case of MSRP, roman numbers;replace $ with USD: ".$title;
            $normalized_title = mtoolswoo_query_openai($prompt);

            error_log('Original Title: ' . $title);
            error_log('Normalized Title: ' . $normalized_title);
            
            if ($normalized_title) {
                wp_send_json_success($normalized_title);
            } else {
                wp_send_json_error('Failed to normalize.');
            }            
        } else {
            error_log('Product not found.');
        }
    } else {
        error_log('Product ID not provided.');
    }
}

/**
 * Add SKU to products page
 */
add_action( 'woocommerce_single_product_summary', 'dev_designs_show_sku', 5 );
function dev_designs_show_sku(){
    global $product;
    echo 'SKU: ' . $product->get_sku();
}

/**
 *       Stock Quantity @ WooCommerce Shop / Cart / Archive Pages
*/
add_action( 'woocommerce_after_shop_loop_item', 'njengah_show_stock_shop', 10 );
function njengah_show_stock_shop() {
   global $product;
   echo wc_get_stock_html( $product );
}

/**
 * Exclude products from a particular category on the shop page
 */
function custom_pre_get_posts_query( $q ) {

    $tax_query = (array) $q->get( 'tax_query' );

    $tax_query[] = array(
           'taxonomy' => 'product_cat',
           'field' => 'slug',
           'terms' => array( 'bargain-bin' ), // Don't display products in the clothing category on the shop page.
           'operator' => 'NOT IN'
    );


    $q->set( 'tax_query', $tax_query );

}
//add_action( 'woocommerce_product_query', 'custom_pre_get_posts_query' );  

/**
 * @snippet       Exclude Cat @ WooCommerce Product Search Results
 * @how-to        Get CustomizeWoo.com FREE
 * @author        Rodolfo Melogli
 * @compatible    WooCommerce 6
 * @donate $9     https://businessbloomer.com/bloomer-armada/
 */
 
add_action( 'pre_get_posts', 'bbloomer_exclude_category_woocommerce_search' );
 
function bbloomer_exclude_category_woocommerce_search( $q ) {
   if ( ! $q->is_main_query() ) return;
   if ( ! $q->is_search() ) return;
   if ( ! is_admin() ) {
      $q->set( 'tax_query', array( array(
         'taxonomy' => 'product_cat',
         'field' => 'slug',
         'terms' => array( 'bargain-bin' ), // change 'courses' with your cat slug/s
         'operator' => 'NOT IN'
      )));
   }
}

/*
 * add link to admin order confirmation email
 */
add_filter( 'woocommerce_order_item_name', 'display_product_title_as_link', 10, 2 );
function display_product_title_as_link( $item_name, $item ) {

    $_product = wc_get_product( $item['variation_id'] ? $item['variation_id'] : $item['product_id'] );

    $link = get_permalink( $_product->get_id() );

    return '<a href="'. $link .'"  rel="nofollow">'. $item_name .'</a>';
}

/**
 * add shipping and return policy links to check out page
 */ 
add_action( 'woocommerce_review_order_before_submit', 'add_policy_links', 9 );

function add_policy_links() {
    echo '<div class="policy-links">';
    echo 'By proceeding, you agree to our <a href="https://silkresource.com/store/shipping-policy/" target="_blank">Shipping Policy</a> and <a href="https://silkresource.com/store/refund-and-return-policy/" target="_blank">Return Policy</a>.';
    echo '</div>';
}

/*
 * WooCommerce Custom Filter: "M Custom Filters" 
 * Adds a dropdown filter in the WooCommerce product admin area for Featured, On Sale, and Not On Sale products.
 */
function m_custom_woocommerce_filter($output) {
    global $wp_query;

    // Get selected filter value from the GET request
    $selected = filter_input(INPUT_GET, 'm_custom_filter', FILTER_VALIDATE_INT);
    if ($selected === false) {
        $selected = 0;
    }

    // Append the dropdown options for "Featured," "On Sale," and "Not On Sale" products
    $output .= '
        <select id="dropdown_m_custom_filter" name="m_custom_filter">
            <option value="">M Custom Filters</option>
            <option value="1" ' . (($selected === 1) ? 'selected="selected"' : '') . '>Featured</option>
            <option value="2" ' . (($selected === 2) ? 'selected="selected"' : '') . '>On Sale</option>
            <option value="3" ' . (($selected === 3) ? 'selected="selected"' : '') . '>Not On Sale</option>
        </select>
    ';
 
    return $output;
}
add_action('woocommerce_product_filters', 'm_custom_woocommerce_filter');

/*
 * Modify WooCommerce Query for "M Custom Filters"
 * Adjusts the WooCommerce admin query to filter products based on Featured, On Sale, or Not On Sale.
 */
function m_custom_woocommerce_filter_where_statement($where) {
    global $wpdb;

    // Retrieve selected filter option (1 for Featured, 2 for On Sale, 3 for Not On Sale)
    $selected = filter_input(INPUT_GET, 'm_custom_filter', FILTER_VALIDATE_INT);

    // Only modify the query if we're in the WooCommerce admin, viewing products, and a filter is selected
    if (!is_admin() || get_query_var('post_type') != "product" || !$selected) {
        return $where;
    }

    // Query for Featured products using the 'product_visibility' taxonomy and the 'featured' term
    if ($selected == 1) {
        $where .= " AND {$wpdb->posts}.ID IN (
            SELECT object_id
            FROM {$wpdb->term_relationships} AS tr
            JOIN {$wpdb->term_taxonomy} AS tt ON tr.term_taxonomy_id = tt.term_taxonomy_id
            JOIN {$wpdb->terms} AS t ON tt.term_id = t.term_id
            WHERE tt.taxonomy = 'product_visibility'
              AND t.slug = 'featured'
        )";
    }
    // Query for On Sale products
    elseif ($selected == 2) {
        $where .= " AND {$wpdb->posts}.ID IN (
            SELECT post_id 
            FROM {$wpdb->postmeta} 
            WHERE meta_key = '_sale_price' 
            AND meta_value > ''
        )";
    }
    // Query for Not On Sale products
    elseif ($selected == 3) {
        $where .= " AND {$wpdb->posts}.ID NOT IN (
            SELECT post_id 
            FROM {$wpdb->postmeta} 
            WHERE meta_key = '_sale_price' 
            AND meta_value > ''
        )";
    }

    return $where;
}
add_filter('posts_where', 'm_custom_woocommerce_filter_where_statement');





/*
 * Woocommerce Add 'End Sale' to 'Bulk Actions' dropdown
 */
function custom_bulk_admin_footer() {
    global $post_type;

    if ( 'product' == $post_type ) {
        ?>
        <script type="text/javascript">
            jQuery(document).ready(function() {
                jQuery('<option>').val('end_sale').text('<?php _e('End Sale')?>').appendTo("select[name='action']");
                jQuery('<option>').val('end_sale').text('<?php _e('End Sale')?>').appendTo("select[name='action2']");
            });
        </script>
        <?php
    }
}
add_action('admin_footer', 'custom_bulk_admin_footer');

/*
 * Woocommerce Add a FAQ button to product listings and product summary
 */
function add_faq_button_to_product_listing() {
    $faq_url = site_url('/faq'); // Dynamically generates the correct URL based on where WordPress is installed
    echo '<a href="' . esc_url($faq_url) . '" class="button faq-button">FAQ</a>';
}
add_action( 'woocommerce_after_shop_loop_item_title', 'add_faq_button_to_product_listing', 20 );

function add_faq_button_to_product_description() {
    $faq_url = site_url('/faq'); // Dynamically generates the correct URL based on where WordPress is installed
    echo '<a href="' . esc_url($faq_url) . '" class="button faq-button">FAQ</a>';
}
add_action( 'woocommerce_single_product_summary', 'add_faq_button_to_product_description', 25 );

/*
 * WooCommerce: Include Order Notes in Admin Order Search
 * 
 * This snippet modifies the default WooCommerce order search in the admin panel
 * to include the content of order notes. When an admin searches for a term,
 * the search results will also check the order notes for matches and include
 * any relevant orders in the results.
 * 
 */
add_filter( 'posts_search', 'search_order_notes', 10, 2 );
function search_order_notes( $search, $wp_query ) {
    global $wpdb;

    // Only modify the search query on WooCommerce orders page in the admin area
    if ( ! is_admin() || empty( $search ) || empty( $_GET['s'] ) || $wp_query->query['post_type'] !== 'shop_order' ) {
        return $search;
    }

    // Get the search term from the query
    $search_term = esc_sql( $wpdb->esc_like( $_GET['s'] ) );

    // Construct the subquery to search the order notes
    $search_order_notes_query = " OR EXISTS (
        SELECT * FROM {$wpdb->prefix}comments
        WHERE comment_post_ID = {$wpdb->posts}.ID
        AND comment_content LIKE '%$search_term%'
        AND comment_type IN ('order_note', 'note')
    )";

    // Append our subquery to the main search query
    $search = str_replace( '))', ")) {$search_order_notes_query}", $search );

    return $search;
}
