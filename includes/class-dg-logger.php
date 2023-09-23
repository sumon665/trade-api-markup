<?php
/**
 * Logger class file.
 *
 * @package DG_Markups
 */

namespace DG\Markup_Logger;

/**
 * Main class file.
 */
class DG_Logger {

	/**
	 * Log a message
	 *
	 * @param string $message Message to log.
	 * @param string $context empty|'order' Context of which log to write to.
	 */
	public static function log( $message, $context = '' ) {
		global $fiztrade_markup_settings;

		if ( 'no' === $fiztrade_markup_settings['debug'] ) {
			return;
		}

		$logger = wc_get_logger();

		if ( empty( $context ) ) {
			$context = array( 'source' => 'fiztrade-markup-updates' );
		}

		$logger->log( 'debug', $message, $context );
	}
}
