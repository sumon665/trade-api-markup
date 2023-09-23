<?php

/*
******************************************************************
******************************************************************
 */

/* shipping method for call to order product */

add_filter( 'woocommerce_package_rates', 'unset_shipping_when_call_to_order', 10,2 );
   
function unset_shipping_when_call_to_order( $rates, $package ) {
	global $woocommerce;
	$call_to_order = false;

	foreach ( $woocommerce->cart->get_cart() as $cart_item ) {
		$category_ids = $cart_item['data']->get_category_ids();
		if (in_array(93, $category_ids)) { 
			$call_to_order = true;
		}
	}

	if ($call_to_order) {
		unset( $rates['free_shipping:5'] );
		unset( $rates['free_shipping:16'] );
		unset( $rates['flat_rate:1'] );
		unset( $rates['flat_rate:19'] );
		unset( $rates['flat_rate:20'] );
		unset( $rates['flat_rate:18'] );
		unset( $rates['flat_rate:7'] );
		unset( $rates['flat_rate:15'] );
		unset( $rates['flat_rate:14'] );
		unset( $rates['flat_rate:23'] );
	} else {
		unset( $rates['flat_rate:24'] );
	}

	return $rates;
}


/* cart call to order product */

add_filter( 'woocommerce_add_to_cart_validation', 'call_to_order_cart', 9999, 2 );
   
function call_to_order_cart( $passed, $product_id ) {
   global $woocommerce;
	$current_pro = false;
	$call_to_order = false;
	$other_cat = array();
	$custom_msg = 'You cannot add a "Direct from Supplier" product to the cart with preorders or regular orders.';

	// $product = wc_get_product( $product_id );
	$terms = get_the_terms( $product_id, 'product_cat' );
	if ( $terms ) {
	  foreach ( $terms as $term ) {
	      if ( $term->slug == 'call-to-order' ) { 
	          $current_pro = true;
	      }
	  }
	}


	foreach ( $woocommerce->cart->get_cart() as $cart_item_key => $cart_item ) {
		$category_ids = $cart_item['data']->get_category_ids();
		if (in_array(93, $category_ids)) { 
			$call_to_order = true;
		} else {
			$other_cat[] .= $cart_item_key;
		}
	}


	if ($current_pro) {
		if (count($other_cat)) {
			foreach ($other_cat as $pid) {
				$woocommerce->cart->remove_cart_item($pid);
			}			
		}
		wc_add_notice( __( $custom_msg, 'woocommerce' ), 'notice' );		
	} else {
		if ($call_to_order) {
			$passed = false;
			wc_add_notice( __( $custom_msg, 'woocommerce' ), 'notice' );
		} else {
			$passed = true;
		}
	}	

   return $passed;
}


/* call to order product add order note */
function custom_order_note( $order_id ) {
    $order = wc_get_order( $order_id );
    $items = $order->get_items();
    foreach ( $items as $item ) {
        	$product_id = $item->get_product_id();
			$terms = get_the_terms( $product_id, 'product_cat' );
			if ( $terms ) {
			  foreach ( $terms as $term ) {
			      if ( $term->slug == 'call-to-order' ) { // replace "category-slug" with the slug of the target category
		            $order->add_order_note( __( 'drop-ship to Custom Field 2', 'woocommerce' ), false, false );
		            break;
			      }
			  }
			}
    }
}
add_action( 'woocommerce_checkout_update_order_meta', 'custom_order_note' );

 /*
******************************************************************
******************************************************************
 */


 /* add availability message shop page after add to cart button */
 add_action ( 'woocommerce_after_shop_loop_item', 'display_availability_shop_page', 10, 5 );
function display_availability_shop_page() {
	global $product;
	$product_id = $product->get_id();

	$cur_terms = get_the_terms ( $product_id, 'product_cat' );
	$availability = get_field( "availability", $product_id );

	foreach ( $cur_terms as $cur_term ) {

		$current_category[]=$cur_term->slug;
	}

	// if (in_array('call-to-order',$current_category)) {
	// 	echo '<p class="sup_dir">Supplier Direct</p>';
	// }	

	if ( in_array('call-to-order',$current_category) && $availability && $product->get_stock_status() == "instock" ) {

		switch ($availability) {
		  case "Live":
		    echo "<p class='pavail'>Ships in 1-2 days</p>";
		    break;
		  case "Limited Quantity":
		    echo "<p class='pavail'>Not Available</p>";
		    break;
		  default:
		    echo "<p class='pavail'>Ships in ".$availability."</p>";
		}

	}
}

/* add availability message single product page after add to cart button */

add_action( 'woocommerce_after_add_to_cart_button', 'display_availability_single_product_page', 10, 3 );

function display_availability_single_product_page() {

	global $product;
	$product_id = $product->get_id();

	$cur_terms = get_the_terms ( $product_id, 'product_cat' );
	$availability = get_field( "availability", $product_id );

	foreach ( $cur_terms as $cur_term ) {

		$current_category[]=$cur_term->slug;
	}

	if ( in_array('call-to-order',$current_category) && $availability ) {

		switch ($availability) {
		  case "Live":
		    echo "<p class='pavail'>Ships in 1-2 days</p>";
		    break;
		  default:
		    echo "<p class='pavail'>Ships in ".$availability."</p>";
		}

	}
}


/* add or remove call to order category */
add_action ('init', 'add_cat_func');

function add_cat_func() {
	
	global $post;

	$args = array(
	    'post_type' => 'product',
	    'posts_per_page' => -1,
	    'post_status' => 'publish',
	);
	$the_query = new WP_Query( $args );
	
	while ( $the_query->have_posts() ): $the_query->the_post();
	    $id = get_the_ID();
	    $sku = get_field( "dg_sku", $id );
	    // $product = wc_get_product( $id );

	    // if ( $product->get_stock_status() == "outofstock" ) {
		// 	$product->update_meta_data('availability', '');
		// 	$product->save();
	    // }

		if ($sku): wp_set_object_terms($id, 93, 'product_cat', true);
		else:wp_remove_object_terms($id, 93, 'product_cat', false); endif;

	endwhile;
	wp_reset_postdata();
}


/* add text Supplier Direct */
add_action( 'woocommerce_after_shop_loop_item_title', 'bbloomer_show_free_shipping_loop', 10, 5 );
 
function bbloomer_show_free_shipping_loop() {

	global $product;	
	$cat = array();
	$terms = get_the_terms ( $product->get_id(), 'product_cat' );
	
	if ($terms):
		foreach ($terms as $term) {
			$cat[] = $term->term_id;
		}
	endif;

	if (in_array(93, $cat)) {
		echo '<p class="sup_dir">Supplier Direct</p>';
	}
 	
}