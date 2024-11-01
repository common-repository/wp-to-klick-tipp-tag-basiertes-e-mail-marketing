<?php
/**
* Class WpToKlickTippBackgroundSave
*
* @version 2.0.0
* @author Tobias B. Conrad <support@saleswonder.biz>
*/

class WpToKlickTippBackgroundSave {

	/**
	 * @var WP_Example_Process
	 */
	protected $process_all;

	/**
	 * Example_Background_Processing constructor.
	 */
	public function __construct() {
		add_action( 'plugins_loaded', array( $this, 'init' ) );
	}

	/**
	 * Init
	 */
	public function init() {

		if ( ! class_exists( 'WP_Async_Request' ) ) {
			require_once( WP_TO_KLICK_TIPP_DIR . 'vendor/wp-background-processing/classes/wp-async-request.php' );
			require_once( WP_TO_KLICK_TIPP_DIR . 'vendor/wp-background-processing/classes/wp-background-process.php' );
		}
		require_once( WP_TO_KLICK_TIPP_DIR . 'inc/class.wp-to-klick-tipp-background-process.php' );

		$this->process_all    = new WpToKlickTippBackgroundProcess();
	}

}

new WpToKlickTippBackgroundSave();
