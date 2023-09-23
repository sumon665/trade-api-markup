<?php
/**
 * Product updater class.
 *
 * Gets price and availability.
 *
 * Sets regular price, stock status (in or out of stock), and stores "bid" price in product meta.
 *
 * @package DG_Markups
 */

namespace DG\Markups_Product_Updater;

/**
 * Main class file.
 */
class DG_Product_Updater {

	/**
	 *
	 * Plugin settings.
	 *
	 * @var array Settings array
	 */
	private static $settings;

	/**
	 *
	 * Plugin settings.
	 *
	 * @var integer Max number of product to update per batch run.
	 */
	private static $max_per_batch = 20;

	/**
	 *
	 * Plugin settings.
	 *
	 * @var string API endpoint URL for GetPriceCalcInfo.
	 */
	private static $endpoint_url_calc;

	/**
	 *
	 * Plugin settings.
	 *
	 * @var string API endpoint URL for GetPrice.
	 */
	private static $endpoint_url_price;

	/**
	 * Custom meta key that stores the DG SKU.
	 *
	 * @var stastic
	 */
	private static $meta_key = 'dg_sku';

	/**
	 * Constructor.
	 */
	public function __construct() {
		global $fiztrade_markup_settings;

		self::$settings = $fiztrade_markup_settings;

		if ( empty( self::$settings['enable'] ) || 'yes' !== self::$settings['enable'] ) {
			return;
		}

		if ( empty( self::$settings['api_key'] ) ) {
			return;
		}

		if ( 'staging' === self::$settings['api_type'] ) {
			self::$endpoint_url_calc  = trailingslashit( 'https://stage-connect.fiztrade.com/FizServices/GetPriceCalcInfo/' . self::$settings['api_key'] );
			self::$endpoint_url_price = trailingslashit( 'https://stage-connect.fiztrade.com/FizServices/GetPrice/' . self::$settings['api_key'] );
		} else {
			self::$endpoint_url_calc  = trailingslashit( 'https://connect.fiztrade.com/FizServices/GetPriceCalcInfo/' . self::$settings['api_key'] );
			self::$endpoint_url_price = trailingslashit( 'https://connect.fiztrade.com/FizServices/GetPrice/' . self::$settings['api_key'] );
		}

		add_action( 'fiztrade_markup_updater', __CLASS__ . '::product_updater', 1 );
	}

	/**
	 * Enqueue product price and stock updates
	 *
	 * @return void
	 */
	public static function queue_updates() {
		// By default unless the PHP max time limit is higher:
		// With 20 actions per batch run this gives us 20 * 25 = 500 products per batch, per minute (approximately)
		// since the action scheduler only runs batches approximately once per minute.

		// Allow filtering this number:
		// Useful on high power servers where the match size can be increased.
		self::$max_per_batch = apply_filters( 'ign_metals_max_product_batch_size', self::$max_per_batch );

		// Sanity check for faulty filters.
		if ( absint( self::$max_per_batch ) <= 0 ) {
			self::$max_per_batch = 20;
		}

		@set_time_limit( 0 ); //phpcs:ignore

		add_filter( 'woocommerce_product_data_store_cpt_get_products_query', __CLASS__ . '::handle_custom_query_var', 10, 2 );

		// Get all "Simple" products.
		$products = wc_get_products(
			array(
				'limit'  => -1,
				'status' => 'publish',
				'type'   => 'simple',
				'dg_sku' => true,
			)
		);

		remove_filter( 'woocommerce_product_data_store_cpt_get_products_query', __CLASS__ . '::handle_custom_query_var', 10, 2 );

		if ( empty( $products ) ) {
			\DG\Markup_Logger\DG_Logger::log( 'No products found' );
			return;
		}

		$count = count( $products );

		\DG\Markup_Logger\DG_Logger::log( sprintf( 'Checking SKUs for %s products', $count ) );

		$skus = array();

		// Skip non-Fiztrade products.
		for ( $i = 0; $i < $count; $i++ ) {

			$product = $products[ $i ];

			if ( empty( $product ) || ! is_a( $product, 'WC_Product' ) ) {
				\DG\Markup_Logger\DG_Logger::log( sprintf( 'Product ID %s could not be loaded. Skipping.', $product_id ) );
				continue;
			}

			$dg_sku = $product->get_meta( self::$meta_key, true );

			if ( empty( $dg_sku ) ) {
				// \DG\Markup_Logger\DG_Logger::log( sprintf( 'Product ID %s has no SKU. Skipping.', $product->get_id() ) );
				unset( $products[ $i ] );
				continue;
			}

			$skus[ $product->get_id() ] = $dg_sku;
		}

		$item_sets = array_chunk( $skus, self::$max_per_batch, true );

		// Queue each set.
		foreach ( $item_sets as $set ) {

			$args = array( 'set' => $set );

			$data = implode( ', ', array_values( $set ) );

			if ( as_next_scheduled_action( 'fiztrade_markup_updater', $args, 'fiztrade_updates' ) ) {
				\DG\Markup_Logger\DG_Logger::log( sprintf( 'Batch already scheduled for IDs: %s', $data ) );
				continue;
			}

			if ( function_exists( 'as_enqueue_async_action' ) ) {
				$res = as_enqueue_async_action( 'fiztrade_markup_updater', $args, 'fiztrade_updates' );
			} else {
				$res = as_schedule_single_action( time() - 1000, 'fiztrade_markup_updater', $args, 'fiztrade_updates' );
			}

			$batch_size = apply_filters( 'action_scheduler_queue_runner_batch_size', self::$max_per_batch );

			\DG\Markup_Logger\DG_Logger::log( sprintf( 'Scheduling product update. Batch size %s, products per batch: %s, SKUs: %s', $batch_size, self::$max_per_batch, $data ) );
		}
	}

	/**
	 * Adds custom query vars for use with wc_get_products()
	 *
	 * @param array $query Query.
	 * @param array $query_vars Query vars.
	 * @return array
	 */
	public static function handle_custom_query_var( $query, $query_vars ) {
		if ( ! empty( $query_vars['dg_sku'] ) ) {
			$query['meta_query'][] = array(
				'key'     => self::$meta_key,
				'value'   => '',
				'compare' => '!=',
			);
		}

		return $query;
	}

	/**
	 * Get readable JSON error text
	 *
	 * @return string JSON error message text.
	 */
	public static function get_json_error_text() {
		switch ( json_last_error() ) {
			case JSON_ERROR_NONE:
				return ' - No errors';
			break;
			case JSON_ERROR_DEPTH:
				return ' - Maximum stack depth exceeded';
			break;
			case JSON_ERROR_STATE_MISMATCH:
				return ' - Underflow or the modes mismatch';
			break;
			case JSON_ERROR_CTRL_CHAR:
				return ' - Unexpected control character found';
			break;
			case JSON_ERROR_SYNTAX:
				return ' - Syntax error, malformed JSON';
			break;
			case JSON_ERROR_UTF8:
				return ' - Malformed UTF-8 characters, possibly incorrectly encoded';
			break;
			default:
				return ' - Unknown error';
			break;
		}
	}
	/**
	 * Updates products
	 *
	 * @param array $set Array of product IDs to update.
	 * @return void
	 */
	public static function product_updater( $set ) {
		global $ign_metals;

		$data = implode( ', ', array_values( $set ) );

		\DG\Markup_Logger\DG_Logger::log( sprintf( 'Processing SKUs: %s', $data ) );

		$admin_email = bloginfo( 'admin_email' );

		foreach ( $set as $id => $sku ) {
			\DG\Markup_Logger\DG_Logger::log( sprintf( 'Processing API for product ID %s, SKU: %s', $id, $sku ) );

			$product = wc_get_product( $id );

			if ( empty( $product ) || ! is_a( $product, 'WC_Product' ) ) {
				\DG\Markup_Logger\DG_Logger::log( sprintf( 'Product ID %s could not be loaded. Skipping.', $id ) );
				continue;
			}

			self::process_markup( $id, $sku, $product );
			self::process_short_desc( $id, $sku, $product );

		}
	}

	/**
	 * Handles getting and setting the markup
	 *
	 * @param int    $id Product ID.
	 * @param string $sku Dillon Gage SKU.
	 * @param object $product WC Product object.
	 * @return void
	 */
	public static function process_markup( $id, $sku, $product ) {
		global $ign_metals;

		$request = wp_remote_get( self::$endpoint_url_calc . $sku . '/NONE' );

		$body = wp_remote_retrieve_body( $request );

		if ( is_wp_error( $request ) ) {
			\DG\Markup_Logger\DG_Logger::log( sprintf( 'API response error. Skipping ID ', $id ) );
			return;
		}

		$body = wp_remote_retrieve_body( $request );

		$json_data = json_decode( $body, true );

		if ( empty( $json_data ) || is_null( $json_data ) ) {
			\DG\Markup_Logger\DG_Logger::log( sprintf( 'API response data response error: %s. Skipping update.', self::get_json_error_text() ) );
			\DG\Markup_Logger\DG_Logger::log( sprintf( 'API response data response error. ID: %s, Response: %s', $id, print_r( $body, true ) ) ); //phpcs:ignore
			return;
		}

		if ( ! empty( $data['error'] ) ) {
			\DG\Markup_Logger\DG_Logger::log( sprintf( 'API response data error for Product ID: %s, SKU %s: %s', $id, $sku, $data['error'] ) );
			return;
		}

		if ( empty( $json_data ) ) {
			\DG\Markup_Logger\DG_Logger::log( sprintf( 'API response data JSON error for Product ID: %s, SKU: %s, response empty.', $id, $sku ) );
			return;
		}

		$p = $json_data['premium'];

		$markup_percent = '';

		if ( ! empty( $p['percentAsk'] ) && floatval( $p['percentAsk'] ) > 0 ) {
				$markup         = ( floatval( $p['percentAsk'] ) / 100 ) . '%';
				$markup_percent = ( floatval( $p['percentAsk'] ) / 100 );
		} elseif ( ! empty( $p['fixedAsk'] ) && floatval( $p['fixedAsk'] ) > 0 ) {
				$markup = $p['fixedAsk'];
		}

		if ( empty( $markup ) ) {
			\DG\Markup_Logger\DG_Logger::log( sprintf( 'No markup set for ID %s, SKU %s. Skipping.', $id, $sku ) );
			$subject = __( 'DG SKU MARKUP NOT FOUND', 'dillon-gage-markups' );
			$message = sprintf( __( 'No price calc information set for product ID %1$s, DG SKU: %2$s', 'dillon-gage-markups' ), $id, $sku );
			wp_mail( $admin_email, $subject, $message );
		}

		if ( empty( $json_data['premium'] ) ) {
			\DG\Markup_Logger\DG_Logger::log( sprintf( __( 'No price calc data for product ID %1$s, DG SKU: %2$s', 'dillon-gage-markups' ), $id, $sku ) );
			return;
		}

		\DG\Markup_Logger\DG_Logger::log( sprintf( 'Setting product ID %s (%s) markup to %s".', $id, $sku, $markup ) );

		// Save markup amount to "Markup 1" field, first markup field.
		$product->update_meta_data( '_markup_rate', $markup );
		$product->save();

		// Tell IGN Metals plugin to recalculate product price.
		$ign_metals->update_single_product( $id );

		\DG\Markup_Logger\DG_Logger::log( sprintf( 'Product ID %s (%s) price updated to %s', $id, $sku, $product->get_price() ) );

	}

	/**
	 * Handles setting the short description
	 *
	 * @param int    $id Product ID.
	 * @param string $sku Dillon Gage SKU.
	 * @param object $product WC Product object.
	 * @return void
	 */
	public static function process_short_desc( $id, $sku, $product ) {
		global $ign_metals;

		$request = wp_remote_get( self::$endpoint_url_price . $sku . '/none' );

		$body = wp_remote_retrieve_body( $request );

		if ( is_wp_error( $request ) ) {
			\DG\Markup_Logger\DG_Logger::log( sprintf( 'API response error for GetPrice endpoint. Skipping ID %s', $id ) );
			return;
		}

		$body = wp_remote_retrieve_body( $request );

		$json_data = json_decode( $body, true );

		if ( empty( $json_data ) || is_null( $json_data ) ) {
			\DG\Markup_Logger\DG_Logger::log( sprintf( 'API response data response error: %s. Skipping update.', self::get_json_error_text() ) );
			\DG\Markup_Logger\DG_Logger::log( sprintf( 'API response data response error. ID: %s, Response: %s', $id, print_r( $body, true ) ) ); //phpcs:ignore
			return;
		}

		if ( ! empty( $data['error'] ) ) {
			\DG\Markup_Logger\DG_Logger::log( sprintf( 'API response data error for Product ID: %s, SKU %s: %s', $id, $sku, $data['error'] ) );
			return;
		}

		if ( empty( $json_data ) ) {
			\DG\Markup_Logger\DG_Logger::log( sprintf( 'API response data JSON error for Product ID: %s, SKU: %s, response empty.', $id, $sku ) );
			return;
		}

		$price = $json_data['ask'];

		if ( empty( $price ) ) {
			\DG\Markup_Logger\DG_Logger::log( sprintf( 'No ask price set for ID %s, SKU %s. Skipping.', $id, $sku ) );
			$subject = __( 'DG SKU PRICE NOT FOUND', 'dillon-gage-markups' );
			$message = sprintf( __( 'No bid price information set for product ID %1$s, DG SKU: %2$s', 'dillon-gage-markups' ), $id, $sku );
			wp_mail( $admin_email, $subject, $message );
		}

		if ( empty( $json_data['ask'] ) ) {
			\DG\Markup_Logger\DG_Logger::log( sprintf( __( 'No ask price data for product ID %1$s, DG SKU: %2$s', 'dillon-gage-markups' ), $id, $sku ) );
			return;
		}

		$availability = $json_data['availability'];
		$active_sell = $json_data['isActiveSell'];
		$stock_in = array('Not Available','No Indication', 'Delay', 'Limited Quantity');
		
		// Change stock status
		if ( $active_sell == 'Y' && $availability && !in_array($availability, $stock_in) ) {
			$product->set_manage_stock( false );
			$product->set_stock_status('instock');
			$product->update_meta_data('availability', $availability);
		} else {
			$product->set_manage_stock( false );
			$product->set_stock_status('outofstock');		
			$product->update_meta_data('availability', '');
		}

		if ($sku): wp_set_object_terms($id, 93, 'product_cat', true);
		else: wp_remove_object_terms($id, 93, 'product_cat'); endif;		

		// Save markup amount to "Markup 1" field, first markup field.
		$product->set_short_description( sprintf( 'Sell to us for $%s', number_format( $price, 2, '.', ',' ) ) );
		$product->save();

		// Tell IGN Metals plugin to recalculate product price.
		$ign_metals->update_single_product( $id );

		\DG\Markup_Logger\DG_Logger::log( sprintf( 'Product ID %s (%s) short description updated to %s', $availability, $sku, number_format( $price, 2, '.', ',' ) ) );

	}
}

new DG_Product_Updater();
