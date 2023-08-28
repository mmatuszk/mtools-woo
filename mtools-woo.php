<?php
/*
Plugin Name: MTools Woo
Description: Tools for updating woocommerce products.
Version: 1.0
Author: Marcin Matuszkiewicz
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
            $prompt = "normalize to title case, keep case of MSRP, roman numbers: ".$title;
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

function mtoolswoo_set_primary_color_attribute($product_id) {
    error_log('mtoolswoo_set_primary_color_attribute function triggered.');

    // Check if the product has an image
    if (has_post_thumbnail($product_id)) {
        $image = wp_get_attachment_image_src(get_post_thumbnail_id($product_id), 'single-post-thumbnail');
        error_log('Product Image URL: ' . $image[0]);
        
        // Use Composer's autoload for ColorThief
        require_once(plugin_dir_path(__FILE__) . 'vendor/autoload.php');
        
        $colorThief = new ColorThief\ColorThief();
        $dominantColor = $colorThief->getColor($image[0]);
        
        if (!$dominantColor) {
            error_log('Failed to get the dominant color.');
            return;  // exit if ColorThief fails
        }

        $dominantColorHex = sprintf("#%02x%02x%02x", $dominantColor[0], $dominantColor[1], $dominantColor[2]);
        error_log('Primary Color: ' . $dominantColorHex);

        // Fetch the product object
        $product = wc_get_product($product_id);
        if (!$product) {
            error_log('Failed to get the product object.');
            return;
        }

        // Check if the term exists
        $term = get_term_by('name', $dominantColorHex, 'pa_primary_color');
        if (!$term) {
            $term = wp_insert_term($dominantColorHex, 'pa_primary_color');
            if (is_wp_error($term)) {
                error_log('Failed to create term: ' . $term->get_error_message());
                return;
            }
            $term_id = $term['term_id'];
        } else {
            $term_id = $term->term_id;
        }

        // Set the 'Primary Color' attribute for the product using the WooCommerce native method
        $attributes = $product->get_attributes();
        $attributes['pa_primary_color'] = array(
            'name' => 'pa_primary_color',
            'value' => array($term_id),
            'position' => 1,
            'is_visible' => 1,
            'is_variation' => 0,
            'is_taxonomy' => 1
        );
        $product->set_attributes($attributes);
        error_log('Attributes to be saved: ' . print_r($attributes, true));
        $saved = $product->save();
        if ( wc_notice_count( 'error' ) > 0 ) {
            $errors = wc_get_notices( 'error' );
            error_log( 'WooCommerce Errors: ' . print_r( $errors, true ) );
            wc_clear_notices();
        }
        
        
        if (is_wp_error($saved)) {
            error_log('Failed to save the product attributes: ' . $saved->get_error_message());
        } else {
            error_log('Product attributes saved successfully.');
        }

        // Log the product attributes after saving
        $current_attributes = $product->get_attributes();
        $attributes_log = array();
        foreach ($current_attributes as $attribute_key => $attribute_data) {
            if ($attribute_data->is_taxonomy()) {
                $terms = wp_get_post_terms($product_id, $attribute_key);
                $term_names = array_map(function ($term) {
                    return $term->name;
                }, $terms);
                $attributes_log[$attribute_key] = implode(', ', $term_names);
            } else {
                $attributes_log[$attribute_key] = $attribute_data->get_options();
            }
        }
        error_log('Product attributes after saving: ' . print_r($attributes_log, true));

    } else {
        error_log('Product does not have an image.');
    }
}


add_action('woocommerce_process_product_meta', 'mtoolswoo_set_primary_color_attribute');
