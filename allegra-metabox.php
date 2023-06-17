<?php
// Agrega una nueva pestaña en el área de administración de productos de WooCommerce
add_filter('woocommerce_product_data_tabs', 'allegra_commerce_add_product_data_tab', 99, 1);
function allegra_commerce_add_product_data_tab($tabs) {
    $tabs['allegra_commerce'] = array(
        'label' => __('Allegra Commerce', 'allegra-commerce'),
        'target' => 'allegra_commerce_data',
        'class' => array('show_if_simple', 'show_if_variable'),
    );
    return $tabs;
}

// Muestra el campo del identificador de Allegra en la nueva pestaña
add_action('woocommerce_product_data_panels', 'allegra_commerce_add_product_data_fields');
function allegra_commerce_add_product_data_fields() {
    global $post;

    echo '<div id="allegra_commerce_data" class="panel woocommerce_options_panel hidden">';
    
    woocommerce_wp_text_input(array(
        'id' => '_allegra_commerce_api_product_id',
        'label' => __('ID del producto Allegra', 'allegra-commerce'),
        'description' => __('Este es el ID del producto en Allegra Commerce.', 'allegra-commerce'),
        'desc_tip' => true,
        'value' => get_post_meta($post->ID, '_allegra_commerce_api_product_id', true),
        'custom_attributes' => array('readonly' => 'readonly'),
    ));

    echo '</div>';
}

?>