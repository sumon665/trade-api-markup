<?php
/**
 * Plugin Name: FizTrade API - Markup Handler
 * Plugin URI: https://github.com/sumon665
 * Description: FizTrade API - Markup Handler
 * Version: 1.0
 * Author: Md Sumon Mia
 * Author URI: https://github.com/sumon665
 */

namespace DG\Markups;

add_action( 'init', 'DG\Markups\load' );

/**
 * Load files.
 *
 * @return void
 */
function load() {
	global $fiztrade_markup_settings;

	$fiztrade_markup_settings = get_option( 'woocommerce_dg_markups_settings' );

	require DIRNAME( __FILE__ ) . '/includes/class-dg-product-updater.php';
	require DIRNAME( __FILE__ ) . '/includes/class-dg-logger.php';

	if ( empty( $fiztrade_markup_settings['interval'] ) || empty( $fiztrade_markup_settings['api_key'] ) ) {
		return;
	}

	if ( empty( $fiztrade_markup_settings['enable'] ) || 'no' === $fiztrade_markup_settings['enable'] ) {
		return;
	}

	// For debug testing.
	if ( ! empty( $_GET['fiztrade_delete_transient'] ) ) {
		delete_transient( 'dg_markups_valid' );
	}

	if ( ! empty( $_GET['fiztrade_test'] ) ) {
		\DG\Markups_Product_Updater\DG_Product_Updater::queue_updates();
		return;
	}

	if ( get_transient( 'dg_markups_valid' ) ) {
		return;
	}

	// Runs the API to update products every X hours.
	set_transient( 'dg_markups_valid', 1, $fiztrade_markup_settings['interval'] * MINUTE_IN_SECONDS );

	\DG\Markups_Product_Updater\DG_Product_Updater::queue_updates();
}

add_action( 'woocommerce_integrations', 'DG\Markups\add_integration', 999 );

/**
 * Add WC Integration for settings.
 *
 * @param array $integrations Array of integration classes.
 * @return array
 */
function add_integration( $integrations ) {
	require DIRNAME( __FILE__ ) . '/includes/class-dg-settings.php';
	$integrations[] = 'DG\Markup_Settings\DG_Settings';
	return $integrations;
}

/* call to order product custom code by Sumon Fiverr*/
require DIRNAME( __FILE__ ) . '/includes/call_to_order.php';