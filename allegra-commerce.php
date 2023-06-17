<?php
/**
 * Plugin Name: Allegra-Commerce
 * Plugin URI: Desing Maker
 * Description: Plugin hecho a la medida para Allegra.
 * Version: 1.0.0
 * Author: Dylan Irzi
 * Author URI: https://example.com
 * License: GPL-2.0+
 * License URI: http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain: allegra-commerce
 * Domain Path: /languages
 */
 
require_once plugin_dir_path(__FILE__) . 'allegra-metabox.php';


// Verificar si WooCommerce está activo
if (in_array('woocommerce/woocommerce.php', apply_filters('active_plugins', get_option('active_plugins')))) {

    add_action('admin_menu', 'allegra_commerce_register_options_page');
    add_action('admin_init', 'allegra_commerce_settings_init');
    add_action('save_post', 'allegra_commerce_save_product_id', 10, 2);
    add_action('woocommerce_order_status_completed', 'allegra_commerce_create_sale', 10, 1);
    add_action('add_meta_boxes', 'add_generate_invoice_meta_box');
    add_action('wp_ajax_generate_invoice_in_alegra', 'generate_invoice_in_alegra');

} else {
    add_action('admin_notices', 'allegra_commerce_woocommerce_inactive_notice');
}

function allegra_commerce_woocommerce_inactive_notice() {
    ?>
    <div class="notice notice-warning is-dismissible">
        <p><?php _e('El plugin Allegra-Commerce requiere WooCommerce para funcionar correctamente. Por favor, active WooCommerce.', 'allegra-commerce'); ?></p>
    </div>
    <?php
}

add_action('admin_menu', 'allegra_commerce_register_options_page');

function allegra_commerce_enqueue_scripts($hook) {
    if ($hook != 'settings_page_allegra_commerce') {
        return;
    }

    wp_register_script('allegra-commerce', plugins_url('/allegra-commerce.js', __FILE__), array('jquery'), '1.0.0', true);

    wp_localize_script('allegra-commerce', 'allegra_commerce_vars', array(
        'ajax_url' => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('allegra_commerce_sync_products_nonce'),
    ));

    wp_enqueue_script('allegra-commerce');
}

add_action('admin_enqueue_scripts', 'allegra_commerce_enqueue_scripts');

function allegra_commerce_register_options_page() {
    add_options_page(
        'Allegra Commerce', // Título de la página
        'Allegra Commerce', // Título del menú
        'manage_options', // Capacidad requerida
        'allegra-commerce', // Slug del menú
        'allegra_commerce_options_page' // Función de devolución de llamada
    );
}

function allegra_commerce_options_page() {
    ?>
    <h1><?php _e('Configuración de Allegra Commerce', 'allegra-commerce'); ?></h1>
        <form method="post" action="options.php">
            <?php
            settings_fields('allegra_commerce_options_group');
            do_settings_sections('allegra_commerce_options');
            submit_button();
            ?>
        </form>
        <hr>
        <h2>Sincronizar productos de WooCommerce con Alegra</h2>
        <form action="" method="post" id="sync-products-form">
            <?php wp_nonce_field('allegra_commerce_sync_products', 'allegra_commerce_sync_products_nonce'); ?>
            <input type="hidden" name="action" value="allegra_commerce_sync_products">
            <input type="submit" value="Sincronizar productos" class="button button-primary">
        </form>
        <div id="sync-result" style="display: none;"></div>
    <script>
        jQuery(document).ready(function ($) {
            $('#sync-products-form').on('submit', function (event) {
                event.preventDefault();

                var formData = $(this).serialize();

                $.ajax({
                    type: 'POST',
                    url: ajaxurl,
                    data: formData,
                    beforeSend: function () {
                        $('#sync-result').hide();
                    },
                    success: function (response) {
                        if (response.success) {
                            var message = response.data.message;
                            var productos_sin_id = response.data.productos_sin_id;
                            var resultText = message;

                            if (productos_sin_id.length > 0) {
                                resultText += '<br><br>Productos sin ID de Alegra:<br>';
                                resultText += productos_sin_id.join(', ');
                            }

                            $('#sync-result').html(resultText).show();
                        } else {
                            $('#sync-result').html('Error al cargar productos.').show();
                        }
                    },
                    error: function () {
                        $('#sync-result').html('Error al cargar productos.').show();
                    }
                });
            });
        });
    </script>
	<style>
		#sync-result {
			display: none;
			margin-top: 20px;
			padding: 15px;
			background-color: #e6f2ff;
			border: 1px solid #b3d1ff;
			border-radius: 4px;
		}
	</style>
    <?php
}

add_action('admin_init', 'allegra_commerce_settings_init');

function allegra_commerce_settings_init() {
    register_setting('allegra_commerce_options_group', 'allegra_commerce_options');

    add_settings_section(
        'allegra_commerce_api_settings', // ID de la sección
        __('Configuración de la API', 'allegra-commerce'), // Título de la sección
        null, // Función de devolución de llamada de la sección
        'allegra_commerce_options' // Página en la que se mostrará la sección
    );

    add_settings_field(
        'email', // ID del campo
        __('Correo electrónico', 'allegra-commerce'), // Título del campo
        'allegra_commerce_email_field', // Función de devolución de llamada del campo
        'allegra_commerce_options', // Página en la que se mostrará el campo
        'allegra_commerce_api_settings' // Sección en la que se mostrará el campo
    );

    add_settings_field(
        'api_key', // ID del campo
        __('Clave API', 'allegra-commerce'), // Título del campo
        'allegra_commerce_api_key_field', // Función de devolución de llamada del campo
        'allegra_commerce_options', // Página en la que se mostrará el campo
        'allegra_commerce_api_settings' // Sección en la que se mostrará el campo
    );
}


function allegra_commerce_email_field() {
    $options = get_option('allegra_commerce_options');
    $email = isset($options['email']) ? $options['email'] : '';
    echo '<input type="email" id="email" name="allegra_commerce_options[email]" value="' . esc_attr($email) . '" />';
}

function allegra_commerce_api_key_field() {
    $options = get_option('allegra_commerce_options');
    $api_key = isset($options['api_key']) ? $options['api_key'] : '';
    echo '<input type="text" id="api_key" name="allegra_commerce_options[api_key]" value="' . esc_attr($api_key) . '" />';
}

add_action('save_post', 'allegra_commerce_save_product_id', 10, 2);

function allegra_commerce_save_product_id($post_id, $post) {
    // Verifica si el post es un producto de WooCommerce
    if ($post->post_type != 'product') {
        return;
    }

    // Verifica si el producto es nuevo (cambia de 'auto-draft' a 'publish')
    $new_status = get_post_status($post_id);
    $old_status = get_post_meta($post_id, '_old_status', true);

    if ($new_status === 'publish' && $old_status !== 'publish') {
        // Guarda el ID del producto en la base de datos
        update_post_meta($post_id, '_allegra_commerce_product_id', $post_id);
        $api_product_id = get_post_meta($post_id, '_allegra_commerce_product_id', true);
        allegra_commerce_send_data_to_api($api_product_id);
    }

    // Actualiza el estado antiguo del producto
    update_post_meta($post_id, '_old_status', $new_status);
}

function obtener_precio_regular($product) {
    if ($product->is_type('variable')) {
        $variation_prices = $product->get_variation_prices();
        $min_price = min($variation_prices['regular_price']);
        return $min_price;
    } else {
        return $product->get_regular_price();
    }
}


function allegra_commerce_send_data_to_api($product_id) {
    // Obtén el objeto del producto usando el ID del producto
    $product = wc_get_product($product_id);

    if (!$product) {
        error_log('Allegra-Commerce: No se encontró el producto con ID: ' . $product_id);
        return;
    }

    // Verificar si el producto tiene SKU
    $sku = $product->get_sku();
    if (empty($sku)) {
        error_log('Allegra-Commerce: El producto no tiene SKU.');
        return;
    }

    $precio_regular = obtener_precio_regular($product);

    if ($precio_regular === '' || $precio_regular === null) {
        error_log('Allegra-Commerce: El precio del producto no es válido.');
        return;
    }

    $api_url = 'https://api.alegra.com/api/v1/items';
    $options = get_option('allegra_commerce_options');
    $email = isset($options['email']) ? $options['email'] : '';
    $api_key = isset($options['api_key']) ? $options['api_key'] : '';
    $authorization = 'Basic ' . base64_encode($email . ':' . $api_key);

    // Obtén la cantidad de stock del producto
    $stock_quantity = $product->get_stock_quantity();

    // Si no se ha configurado el stock, establece la cantidad de stock en 1
    if ($stock_quantity === null) {
        $stock_quantity = 1;
    }


    $product_data = array(
        'name' => $product->get_name(),
        'description' => $product->get_description(),
        'reference' => $product->get_sku(),
        'price' => array(array('price' => $precio_regular)),
        'type' => 'product',
        'inventory' => array(
            'unit' => 'unit', // Unidad de medida (puedes ajustar esto según tus necesidades)
            'unitCost' => $precio_regular, // Costo unitario del producto
            'initialQuantity' => $stock_quantity, // Cantidad inicial del producto
        ),
    );

    error_log('Allegra-Commerce: Precio del producto: ' . $precio_regular);

    $response = wp_remote_post($api_url, array(
        'headers' => array(
            'Authorization' => $authorization,
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
        ),
        'body' => json_encode($product_data),
    ));

    if (is_wp_error($response)) {
        error_log('Allegra-Commerce: ' . $response->get_error_message());
    } else {
        $http_code = wp_remote_retrieve_response_code($response);

        if ($http_code == 201) {
            error_log('Allegra-Commerce: Producto Almacenado con Exito.');
            // Decodifica la respuesta JSON y almacena el ID en la variable $api_product_id
            $api_product_id = json_decode(wp_remote_retrieve_body($response))->id;

            // Almacena el ID del producto de Allegra en el campo de metadatos del producto de WooCommerce
            update_post_meta($product_id, '_allegra_commerce_api_product_id', $api_product_id);
        } else {
            error_log('Allegra-Commerce: Error al enviar la solicitud a la API. Código de estado HTTP: ' . $http_code);
            error_log('Allegra-Commerce: Respuesta de la API: ' . wp_remote_retrieve_body($response));
        }
    }
}


add_action('woocommerce_order_status_completed', 'allegra_commerce_create_sale', 10, 1);

function allegra_commerce_create_sale($order_id) {
    $order = wc_get_order($order_id);

    if (!$order) {
        error_log('Allegra-Commerce: No se encontró la orden con ID: ' . $order_id);
        return;
    }

    // Obtén los datos de la orden
    $order_data = $order->get_data();

    // Prepara los datos de los productos de la orden para enviar a Alegra
    $order_items = array();
    foreach ($order->get_items() as $item_id => $item_data) {
        $product_id = $item_data->get_product_id();
        $api_product_id = get_post_meta($product_id, '_allegra_commerce_api_product_id', true);
        $quantity = $item_data->get_quantity();

        if ($api_product_id) {
            $order_items[] = array(
                'id' => $api_product_id,
                'quantity' => $quantity,
            );

           // Obtén la cantidad de stock restante del producto
           $product = wc_get_product($product_id);
           $remaining_stock = $product->get_stock_quantity();

           // Llama a la función edit_product_in_alegra con la cantidad de stock restante
           edit_product_in_alegra($api_product_id, $remaining_stock);
        }
    }
}

function edit_product_in_alegra($api_product_id, $remaining_stock) {
    // Obtén las opciones de Allegra Commerce
    $options = get_option('allegra_commerce_options');
    $email = isset($options['email']) ? $options['email'] : '';
    $api_key = isset($options['api_key']) ? $options['api_key'] : '';
    $authorization = 'Basic ' . base64_encode($email . ':' . $api_key);

    // URL de la API de Alegra para editar un producto
    $url = 'https://api.alegra.com/api/v1/items/' . $api_product_id;

    // Preparar los datos de la solicitud
    $product_data = array(
        'inventory' => array(
            'unit' => 'unit',
            'initialQuantity' => $remaining_stock,
            'negativeSale' => false,
        ),
    );

    // Codifica los datos del producto en formato JSON
    $product_data_json = json_encode($product_data);

    // Configurar los encabezados de la solicitud
    $headers = array(
        'Content-Type: application/json',
        'Authorization: ' . $authorization,
    );

    // Inicializar cURL
    $ch = curl_init($url);

    // Establecer las opciones de cURL
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PUT');
    curl_setopt($ch, CURLOPT_POSTFIELDS, $product_data_json);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

    // Ejecutar la solicitud de cURL y obtener la respuesta
    $response = curl_exec($ch);

    // Cerrar cURL
    curl_close($ch);

    // Decodificar la respuesta JSON
    $response_data = json_decode($response, true);

    // Verificar si la actualización fue exitosa
    if (isset($response_data['code']) && $response_data['code'] == 200) {
        // La actualización del producto fue exitosa
        error_log('Allegra-Commerce: Producto actualizado con éxito. ID de producto en Alegra: ' . $api_product_id);
    } else {
        // Hubo un error al actualizar el producto
        error_log('Allegra-Commerce: Error al actualizar el producto. ID de producto en Alegra: ' . $api_product_id . '. Detalles del error: ' . $response);
    }
}

function add_generate_invoice_meta_box() {
    add_meta_box(
        'generate_invoice_meta_box',
        'Generar factura en Alegra',
        'generate_invoice_meta_box_content',
        'shop_order',
        'side',
        'default'
    );
}
add_action('add_meta_boxes', 'add_generate_invoice_meta_box');

function generate_invoice_meta_box_content($post) {
    ?>
    <div id="generate_invoice_button_container">
        <button id="generate_invoice_button" type="button" class="button">Generar factura en Alegra</button>
        <div id="generate_invoice_response" style="margin-top: 10px; border: 1px solid #ccc; padding: 10px; display: none;"></div>
    </div>
    <?php
}


function generate_invoice_button_admin_scripts($hook) {
	
	global $post;

    if ('post.php' === $hook && 'shop_order' === $post->post_type) {
        ?>
		<script src="https://code.jquery.com/jquery-3.6.4.min.js"></script>
        <script type="text/javascript">
            jQuery(document).ready(function ($) {
                $('#generate_invoice_button').on('click', function () {
                    var order_id = '<?php echo $post->ID; ?>';

                    $.ajax({
                        type: 'POST',
                        url: '<?php echo admin_url('admin-ajax.php'); ?>',
                        data: {
                            action: 'generate_invoice_in_alegra',
                            order_id: order_id,
                            security: '<?php echo wp_create_nonce('generate_invoice_in_alegra_nonce'); ?>'
                        },
                        beforeSend: function () {
                            $('#generate_invoice_button').prop('disabled', true);
                            $('#generate_invoice_response').hide().html('');
                        },
                        success: function (response) {
                            $('#generate_invoice_button').prop('disabled', false);
                            $('#generate_invoice_response').show().html(response);
                        }
                    });
                });
            });
        </script>
        <?php
    }
}
add_action('admin_enqueue_scripts', 'generate_invoice_button_admin_scripts');

function remove_spaces($input_string) {
    return str_replace(" ", "", $input_string);
}

function getcedula($order_id) {
$cedula_nit = get_post_meta($order_id, '_billing_cedula_nit', true);
return $cedula_nit;
}

function generate_contact_to_allegra($order_id) {
    // Obtén el objeto de la orden usando el ID de la orden
    $order = wc_get_order($order_id);

    if (!$order) {
        error_log('Allegra-Commerce: No se encontró la orden con ID: ' . $order_id);
        return;
    }

    // Obtén los datos del comprador
    $customer_id = $order->get_customer_id();
    $customer = new WC_Customer($customer_id);

    if (!$customer) {
        error_log('Allegra-Commerce: No se encontró el cliente con ID: ' . $customer_id);
        return;
    }

    $api_url = 'https://api.alegra.com/api/v1/contacts';
    $options = get_option('allegra_commerce_options');
    $email = isset($options['email']) ? $options['email'] : '';
    $api_key = isset($options['api_key']) ? $options['api_key'] : '';
    $authorization = 'Basic ' . base64_encode($email . ':' . $api_key);

    $order = wc_get_order($order_id);
    $billing_address = $order->get_address('billing');	
	$cedula = getcedula($order_id);

    $customer_data = array(
			'nameObject' => array(
				'firstName' => $customer->get_first_name(),
				'secondName' => '',
				'lastName' => $customer->get_last_name(),
			),
			'identificationObject' => array(
				'number' => remove_spaces($cedula),
				'type' => 'CC',
			),
			'kindOfPerson' => 'PERSON_ENTITY',
			'regime' => 'SIMPLIFIED_REGIME',
			'address' => array(
				'city' => $billing_address['city'],
				'department' => $billing_address['state'],
				'address' => $billing_address['address_1'] . ' ' . $billing_address['address_2'],
				'zipCode' => $billing_address['postcode'],
				'country' => $billing_address['country'],
			),
			'phonePrimary' => $billing_address['phone'],
			'phoneSecondary' => '',
			'mobile' => '',
			'email' => $customer->get_email(),
			'emailSecondary' => '',
			'type' => 'client',
		);
    
    $response = wp_remote_post($api_url, array(
        'headers' => array(
            'Authorization' => $authorization,
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
        ),
        'body' => json_encode($customer_data),
    ));
	
	// error_log('Allegra-Commerce: Respuesta completa: ' . print_r($response, true));
    
    if (is_wp_error($response)) {
        error_log('Allegra-Commerce: ' . $response->get_error_message());
    } else {
        $http_code = wp_remote_retrieve_response_code($response);
    
        if ($http_code == 201) {
            error_log('Allegra-Commerce: Contacto creado con éxito.');
            $api_contact_id = json_decode(wp_remote_retrieve_body($response))->id;
        
            // Almacena el ID del contacto de Allegra en los metadatos del cliente de WooCommerce
            update_user_meta($customer_id, '_allegra_commerce_api_contact_id', $api_contact_id);
			return $api_contact_id;
			// return 'Contacto creado con éxito';
        } else {
            error_log('Allegra-Commerce: Error al enviar la solicitud a la API. Código de estado HTTP: ' . $http_code);
			return 'Error al enviar la solicitud a la API.';
            error_log('Allegra-Commerce: Respuesta de la API: ' . wp_remote_retrieve_body($response));
        }
    
    }
}


function create_invoice_in_allegra($order_id, $client_id) {
    // Obtén el objeto de la orden usando el ID de la orden
    $order = wc_get_order($order_id);

    if (!$order) {
        error_log('Allegra-Commerce: No se encontró la orden con ID: ' . $order_id);
        return;
    }

    // Obtén los ítems de la orden
    $order_items = $order->get_items();

    // Preparar los ítems de factura
    $invoice_items = array();

    foreach ($order_items as $item) {
        $product = $item->get_product();
        $woocommerce_product_id = $product->get_id();
    
        // Obtén el ID del producto de Alegra guardado como metadato en el producto de WooCommerce
        $alegra_product_id = get_post_meta($woocommerce_product_id, '_allegra_commerce_api_product_id', true);
    
        $item_data = array(
            'id' => $alegra_product_id, // Usa el ID del producto de Alegra en lugar del de WooCommerce
            'name' => $product->get_name(),
            'discount' => 0,
            'observations' => '',
            'tax' => array(), // Añadir impuestos si los hay
            'price' => $product->get_price(),
            'quantity' => $item->get_quantity(),
        );
        array_push($invoice_items, $item_data);
    }

    
    $api_url = 'https://api.alegra.com/api/v1/invoices';
    $options = get_option('allegra_commerce_options');
    $email = isset($options['email']) ? $options['email'] : '';
    $api_key = isset($options['api_key']) ? $options['api_key'] : '';
    $authorization = 'Basic ' . base64_encode($email . ':' . $api_key);

    $order = wc_get_order($order_id);
    $billing_address = $order->get_address('billing');
    $api_product_id = get_post_meta($product_id, '_allegra_commerce_api_product_id', true);

    $invoice_data = [
        'paymentMethod' => 'CASH',
        'paymentForm' => 'CASH',
        'type' => 'NATIONAL',
        'stamp' => [
            'generateStamp' => false
        ],
        'operationType' => 'STANDARD',
        'status' => 'open',
        'numberTemplate' => [
            'id' => 1 // Asegúrate de establecer la ID correcta de la plantilla de numeración aquí
        ],
        'items' => $invoice_items,
        'dueDate' => date('Y-m-d'),
        'date' => date('Y-m-d'),
        'client' => [
            'id' => $client_id
        ]
    ];
    
    $response = wp_remote_post($api_url, array(
        'headers' => array(
            'Authorization' => $authorization,
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
        ),
        'body' => json_encode($invoice_data),
    ));
	
	error_log('Allegra-Commerce: Respuesta completa: ' . print_r($response, true));
    
    if (is_wp_error($response)) {
        error_log('Allegra-Commerce: ' . $response->get_error_message());
    } else {
        $http_code = wp_remote_retrieve_response_code($response);
    
        if ($http_code == 201) {
            error_log('Allegra-Commerce: Factura Creada con Exito');
            $api_factura_id = json_decode(wp_remote_retrieve_body($response))->id;
        
            // Almacena el ID del contacto de Allegra en los metadatos del cliente de WooCommerce
            update_post_meta($order_id, 'alegra_invoice_id', $api_factura_id);
			return 'Factura Creada con éxito';
			// return 'Contacto creado con éxito';
        } else {
            error_log('Allegra-Commerce: Error al enviar la solicitud a la API. Código de estado HTTP: ' . $http_code);
			return 'Error al enviar la solicitud a la API.';
            error_log('Allegra-Commerce: Respuesta de la API: ' . wp_remote_retrieve_body($response));
        }
    
    }
}

function ajax_generate_invoice_in_alegra() {
    check_ajax_referer('generate_invoice_in_alegra_nonce', 'security');

    $order_id = isset($_POST['order_id']) ? intval($_POST['order_id']) : 0;

    if ($order_id > 0) {
        // $response = generate_invoice_in_alegra($order_id);
        // 
        $idcliente = generate_contact_to_allegra($order_id);
		error_log('Allegra-Commerce: Id del cliente Allegra: ' . $idcliente);
        $response = create_invoice_in_allegra($order_id,$idcliente);
	
		//$response = "Allegra-Commerce: Contacto creado con éxito.";
        // Muestra la respuesta en el espacio de respuesta
        echo $response;
    } else {
        echo 'Error: No se pudo generar la factura en Alegra.';
    }

    wp_die();
}
add_action('wp_ajax_nopriv_generate_invoice_in_alegra', 'ajax_generate_invoice_in_alegra');
add_action('wp_ajax_generate_invoice_in_alegra', 'ajax_generate_invoice_in_alegra');

function obtener_productos_sin_id_allegra() {
    $args = array(
        'post_type' => 'product',
        'posts_per_page' => -1,
        'meta_query' => array(
            array(
                'key' => '_allegra_commerce_api_product_id',
                'compare' => 'NOT EXISTS',
            ),
        ),
    );

    $productos_sin_id_allegra = array();
    $products_query = new WP_Query($args);

    if ($products_query->have_posts()) {
        while ($products_query->have_posts()) {
            $products_query->the_post();
            $product_id = get_the_ID();
            $productos_sin_id_allegra[] = $product_id;
        }
    }

    wp_reset_postdata();

    return $productos_sin_id_allegra;
}

function sync_woocommerce_products_with_allegra($productos_sin_id) {
    $request_counter = 0;

    foreach ($productos_sin_id as $product_id) {
        $response = allegra_commerce_send_data_to_api($product_id);
        $request_counter++;

        if ($request_counter >= 60) {
            sleep(60);
            $request_counter = 0;
        }

        if ($response['response']['code'] == 429) {
            sleep(60);
        } else {
            if ($response['response']['code'] >= 200 && $response['response']['code'] < 300) {
                $response_body = json_decode($response['body'], true);
                if (isset($response_body['id'])) {
                    $api_product_id = $response_body['id'];
                    update_post_meta($product_id, '_allegra_commerce_api_product_id', $api_product_id);
                }
            }
        }
    }
}
               
add_action('wp_ajax_allegra_commerce_sync_products', 'allegra_commerce_sync_products_ajax_handler');
add_action('wp_ajax_nopriv_allegra_commerce_sync_products', 'allegra_commerce_sync_products_ajax_handler');

function allegra_commerce_sync_products_ajax_handler() {
    // Verificar nonce
    check_ajax_referer('allegra_commerce_sync_products', 'allegra_commerce_sync_products_nonce');

   // Obtener los productos sin ID de Alegra
    $productos_sin_id = obtener_productos_sin_id_allegra();

    // Llamar a la función de sincronización con los productos sin ID de Alegra
    sync_woocommerce_products_with_allegra($productos_sin_id);
	

    if (!empty($productos_sin_id)) {
        // Devolver una respuesta JSON
        wp_send_json_success(array('message' => 'Productos cargados exitosamente.', 'productos_sin_id' => $productos_sin_id));
		
    } else {
        // Si no se encontraron productos sin el ID de Alegra, devuelve un error
        wp_send_json_error(array('message' => 'No se cargo ningun producto nuevo a Allegra.'));
    }
}

?>