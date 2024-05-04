<?php
/**
 * Plugin Name: Book Links Assistant for WooCommerce
 * Description: Enable authors to link to their books on various Amazon stores using the SKU field. Authors can enable or disable specific Amazon stores and customize the heading text and style. Make sure there are no blank spaces before or after the SKU.
 * Version: 1.1
 * Author: Ceri Clark
 * Author URI: https://cericlark.com/
 */

// Add custom fields to the product general tab
add_action('woocommerce_product_options_general_product_data', 'abl_add_custom_fields');
function abl_add_custom_fields() {
    global $post;

    // Checkbox to enable Amazon Book Link
    woocommerce_wp_checkbox([
        'id' => '_amazon_book_link_enabled',
        'label' => __('Amazon Book Link?', 'amazon-book-links'),
        'description' => __('Check this box if this is an Amazon book link. Remember to fill out the SKU with your ASIN or ISBN in Inventory.', 'amazon-book-links'),
    ]);

    // Text input for custom heading text
    woocommerce_wp_text_input([
        'id' => '_amazon_heading_text',
        'label' => __('Amazon Heading Text', 'amazon-book-links'),
        'description' => __('Enter the heading text for the Amazon links section.', 'amazon-book-links'),
        'default' => 'Available from Amazon',
        'desc_tip' => true,
    ]);

    // Select dropdown for heading style
    woocommerce_wp_select([
        'id' => '_amazon_heading_style',
        'label' => __('Amazon Heading Style', 'amazon-book-links'),
        'options' => [
            'h1' => 'H1',
            'h2' => 'H2',
            'h3' => 'H3 (default)',
            'h4' => 'H4',
            'h5' => 'H5',
            'h6' => 'H6',
        ],
        'description' => __('Select the heading style for the Amazon links section.', 'amazon-book-links'),
        'desc_tip' => true,
        'value' => get_post_meta($post->ID, '_amazon_heading_style', true) ?: 'h3',
    ]);

   // Multi-select dropdown for selecting Amazon stores
echo '<p class="form-field"><label for="_amazon_stores_select">Select Amazon Stores</label>';
echo '<select id="_amazon_stores_select" name="_amazon_stores_select[]" class="wc-enhanced-select" multiple="multiple" style="width: 50%;" data-placeholder="Choose stores...">';
$amazon_stores = [
    'USA', 'UK', 'Canada', 'Australia', 'Belgium', 'Brazil', 
    'France', 'Germany', 'India', 'Italy', 'Japan', 'Mexico', 
    'Netherlands', 'Singapore', 'Spain', 'Sweden', 'Turkey',
];

$preselected_stores = ['USA', 'UK', 'Canada', 'Australia']; // Preselected stores
$selected_stores = get_post_meta($post->ID, '_amazon_stores_selected', true);
if (empty($selected_stores)) {
    $selected_stores = $preselected_stores; // Default to preselected stores if none are selected
}

foreach ($amazon_stores as $store) {
    echo '<option value="' . esc_attr($store) . '"' . (in_array($store, $selected_stores) ? ' selected="selected"' : '') . '>' . esc_html($store) . '</option>';
}
echo '</select>';

// Add "Add all stores" and "Deselect all" buttons
echo '<button type="button" class="button" onclick="selectAllStores()">Add all stores</button>';
echo '<button type="button" class="button" onclick="deselectAllStores()">Deselect all</button>';

// JavaScript for buttons
?>
<script>
function selectAllStores() {
    jQuery('#_amazon_stores_select option').prop('selected', true);
    jQuery('#_amazon_stores_select').trigger('change');
}
function deselectAllStores() {
    jQuery('#_amazon_stores_select option').prop('selected', false);
    jQuery('#_amazon_stores_select').trigger('change');
}
</script>
<?php
echo '<span class="description">Hold down the Ctrl (Windows) / Command (Mac) button to select multiple options, or click into the box, select and repeat until you have all the stores you want.</span></p>';
}

// Save the custom fields
add_action('woocommerce_process_product_meta', 'abl_save_custom_fields');
function abl_save_custom_fields($post_id) {
    $amazon_book_link_enabled = isset($_POST['_amazon_book_link_enabled']) ? 'yes' : 'no';
    update_post_meta($post_id, '_amazon_book_link_enabled', $amazon_book_link_enabled);

    if (isset($_POST['_amazon_heading_text'])) {
        update_post_meta($post_id, '_amazon_heading_text', sanitize_text_field($_POST['_amazon_heading_text']));
    }
    if (isset($_POST['_amazon_heading_style'])) {
        update_post_meta($post_id, '_amazon_heading_style', sanitize_text_field($_POST['_amazon_heading_style']));
    }

    $selected_stores = isset($_POST['_amazon_stores_select']) ? (array)$_POST['_amazon_stores_select'] : [];
    update_post_meta($post_id, '_amazon_stores_selected', $selected_stores);
}

// Check SKU before save
add_action('woocommerce_admin_process_product_object', 'abl_check_sku_before_save');
function abl_check_sku_before_save($product) {
    if ('yes' === $product->get_meta('_amazon_book_link_enabled') && empty($product->get_sku())) {
        WC_Admin_Meta_Boxes::add_error('Oops, please enter a valid SKU for Amazon Book Link in the Inventory tab. This should be the ASIN or ISBN of your product.');
    }
}

// Display Amazon links dropdown on the product page
add_action('woocommerce_single_product_summary', 'abl_display_amazon_links_dropdown', 30);
function abl_display_amazon_links_dropdown() {
    global $product;
    if ('yes' === get_post_meta($product->get_id(), '_amazon_book_link_enabled', true)) {
        $sku = $product->get_sku();
        if (empty($sku)) return; // Exit if SKU is not set

        $selected_stores = get_post_meta($product->get_id(), '_amazon_stores_selected', true);
        if (empty($selected_stores)) return; // Exit if no stores are selected

        $heading_text = get_post_meta($product->get_id(), '_amazon_heading_text', true) ?: 'Available from Amazon';
        $heading_style = get_post_meta($product->get_id(), '_amazon_heading_style', true) ?: 'h3';

        echo "<$heading_style>$heading_text</$heading_style>"; // Dynamic heading based on author input

        // Base URLs for Amazon stores
        $base_urls = [
            'USA' => 'https://www.amazon.com/dp/',
            'UK' => 'https://www.amazon.co.uk/dp/',
            'Canada' => 'https://www.amazon.ca/dp/',
            'Australia' => 'https://www.amazon.com.au/dp/',
            'Belgium' => 'https://www.amazon.com.be/dp/',
            'Brazil' => 'https://www.amazon.com.br/dp/',
            'France' => 'https://www.amazon.fr/dp/',
            'Germany' => 'https://www.amazon.de/dp/',
            'India' => 'https://www.amazon.in/dp/',
            'Italy' => 'https://www.amazon.it/dp/',
            'Japan' => 'https://www.amazon.co.jp/dp/',
            'Mexico' => 'https://www.amazon.com.mx/dp/',
            'Netherlands' => 'https://www.amazon.nl/dp/',
            'Singapore' => 'https://www.amazon.sg/dp/',
            'Spain' => 'https://www.amazon.es/dp/',
            'Sweden' => 'https://www.amazon.se/dp/',
            'Turkey' => 'https://www.amazon.com.tr/dp/',
        ];

        // Dropdown for Amazon store links
        echo '<select onchange="if (this.value) window.open(this.value, \'_blank\')" style="height: auto; padding: 8px;">';
        echo '<option value="">Select Amazon Store...</option>';
        foreach ($selected_stores as $store) {
            if (isset($base_urls[$store])) {
                echo '<option value="' . esc_attr($base_urls[$store] . $sku) . '">' . esc_html($store) . '</option>';
            }
        }
        echo '</select>';
    }
}