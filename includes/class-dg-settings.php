<?php
/**
 * Settings class file.
 *
 * @package DG_Markups
 */

namespace DG\Markup_Settings;

/**
 * Main class file.
 */
class DG_Settings extends \WC_Integration {

	/**
	 * Constructor
	 */
	public function __construct() {
		$this->id = 'dg_markups';

		$this->method_title       = __( 'Dillon Gage Markups', 'dillon-gage-markups' );
		$this->method_description = __( 'Adjust the settings before using this plugin.', 'dillon-gage-markups' );

		$this->init_form_fields();
		$this->init_settings();

		add_action( 'woocommerce_update_options_integration_' . $this->id, array( &$this, 'process_admin_options' ), 999 );

	}

	/**
	 * Define form fields.
	 *
	 * @return void
	 */
	public function init_form_fields() {
		$this->form_fields = array(
			'enable'   => array(
				'title'       => __( 'Enable', 'dillon-gage-markups' ),
				'type'        => 'checkbox',
				'default'     => '',
				'values'      => 'yes',
				'description' => '',
				'label'       => __( 'Enable', 'dillon-gage-markups' ),
			),
			'api_type' => array(
				'title'       => __( 'Live or Staging', 'dillon-gage-markups' ),
				'type'        => 'select',
				'default'     => 'staging',
				'options'     => array(
					'staging' => __( 'Staging' ),
					'live'    => __( 'Live' ),
				),
				'description' => '',
			),
			'api_key'  => array(
				'title'       => __( 'API key', 'dillon-gage-markups' ),
				'type'        => 'text',
				'default'     => '',
				'description' => __( 'API key for live or staging', 'dillon-gage-markups' ),
				'desc_tip'    => true,
			),
			'interval' => array(
				'title'       => __( 'Update Interval', 'dillon-gage-markups' ),
				'type'        => 'text',
				'default'     => '30',
				'description' => __( 'Update interval as minute, 30 = every 30 minutes', 'dillon-gage-markups' ),
				'desc_tip'    => true,
			),
			'debug'    => array(
				'title'       => __( 'Enable debug log', 'dillon-gage-markups' ),
				'type'        => 'checkbox',
				'default'     => 'yes',
				'values'      => 'yes',
				// translators: %s is a URL.
				'description' => sprintf( __( 'Enable debug logging to track API activity. Logs can be view at WooCommerce->Status-><a href="%s" target="_blank">Logs</a> tab', 'dillon-gage-markups' ), admin_url( 'admin.php?page=wc-status&tab=logs' ) ),
				'label'       => __( 'Enable', 'dillon-gage-markups' ),
				'desc_tip'    => false,
			),
		);
	}

	/**
	 * Save settings and delete the timeout transient
	 *
	 * @return void
	 */
	public function process_admin_options() {
		parent::process_admin_options();
		delete_transient( 'dg_markups_valid' );
	}
}

new DG_Settings();
