<?php

/*
******************************************************************
******************************************************************
 */

/* shipping method for call to order product */

// HERE your settings - Utility function
function your_country_shipping_settings(){
    $results = array();
    // Can be based on "Product IDs" (or "Product categories" ==> false)
    $results['type'] = false; // or false

    // Allowed countries (Only compatible country codes - 2 digits)
    $results['countries'] = array('US');
    if( $results['type'] ){
        // Restricted product IDs
        $results['matching'] = array( 37, 38 );
    } else {
        // Restricted product categories (IDs, Slugs or Names)
        $results['matching'] = array('call-to-order');
    }
    // Message
    $results['message'] = __( "can not be delivered to your country.", "woocommerce" );
    return $results;
}

// Utility function that check cart items
function get_items_names( $matching, $package, $type ){
    $product_names = array();
    // Search in cart items
    foreach( $package['contents'] as $item ){
        if( $type ){
            if( in_array( $item['data']->get_id(), $matching ) )
                $product_names[] = $item['data']->get_name();
        } else {
           if( has_term( $matching, 'product_cat', $item['product_id'] ) )
                $product_names[] = $item['data']->get_name();
        }
    }
    return $product_names;
}

// Conditionally disabling shipping methods
add_filter('woocommerce_package_rates','custom_country_shipping_rules', 10, 2 );
function custom_country_shipping_rules( $rates, $package ){
    if( isset($package['destination']['country']) && isset($package['contents']) ){
        // Load your settings
        $data = your_country_shipping_settings();

        // If matching allowed countries ==> We Exit
        if( in_array( $package['destination']['country'], $data['countries'] ) )
            return $rates; // Exit

        $product_names = get_items_names( $data['matching'], $package, $data['type'] );

        // When product match we Remove all shipping methods
        if( count($product_names) > 0 ){
            // Removing all shipping methods
            foreach( $rates as $rate_id => $rate )
                unset( $rates[$rate_id] );
        }
    }
    return $rates;
}

// Conditionally displaying a shipping message
add_filter('woocommerce_cart_no_shipping_available_html','custom_country_shipping_notice', 10, 1 );
add_filter('woocommerce_no_shipping_available_html','custom_country_shipping_notice', 10, 1 );
function custom_country_shipping_notice( $html ){
    $package = WC()->shipping->get_packages()[0];
    if( isset($package['destination']['country']) && isset($package['contents']) ){
        // Load your settings
        $data = your_country_shipping_settings();

        // If matching allowed countries ==> We Exit
        if( in_array( $package['destination']['country'], $data['countries'] ) )
           return $html; // Exit

        $product_names = get_items_names( $data['matching'], $package, $data['type'] );

        if( count($product_names) > 0 ){
            $text = '"' . implode( '", "', $product_names ) . '" ' . $data['message'];
            $html  = wpautop( $text );
        }
    }
    return $html;
}