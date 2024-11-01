<?php
/**
* Class WpToKlickTippBackgroundSave
*
* @version 2.0.0
* @author Tobias B. Conrad <support@saleswonder.biz>
*/
class WpToKlickTippBackgroundProcess extends WP_Background_Process {

	/**
	 * @var string
	 */
	protected $action = 'wptkt_save_post';
	/**
	 * Task
	 *
	 * Override this method to perform any actions required on each
	 * queue item. Return the modified item for further processing
	 * in the next pass through. Or, return false to remove the
	 * item from the queue.
	 *
	 * @param mixed $item Queue item to iterate over
	 *
	 * @return mixed
	 */
	protected function task( $ID ) {

		if ( false === get_post_status( $ID ) ) {
			return false;
		}

		$oOrder  = new WC_Order( $ID );
		$iUserID = version_compare( WC()->version, '3.0', '>=' ) ? $oOrder->get_customer_id() : $oOrder->user_id;	

		if ( 'klicktipp' === get_option( 'wptkt-plugin-active' ) ) {

			include( WP_TO_KLICK_TIPP_DIR . 'inc/wpuser2kt.inc.php' );
			include( WP_TO_KLICK_TIPP_DIR . 'inc/order2kt.inc.php' );

			// If this is a 'product' with status 'publish' go ahead and save tags in KT
			if ( 'product' === get_post_type( $ID ) && 'publish' === get_post_status( $ID ) ) {

				$wptktProductCall = new WpToKlickTippAdmin();
				$aTags = array();
				$oPost = get_post( $ID );

				// Tag for produkt
				$aTags[$oPost->post_name] = 'Created by WooEMI';

				// Tags for categories
				$oCategories = get_the_terms( $ID, 'product_cat' );
				foreach ($oCategories as $aCategory) {
					$aTags[$aCategory->slug] = 'Created by WooEMI';
				}
				$wptktProductCall->writeTags($aTags);
				unset($wptktProductCall);
			}

		} elseif ( 'autoresponder' === get_option('wptkt-plugin-active') ) {
			include( WP_TO_KLICK_TIPP_DIR . 'inc/order2autoresponder.inc.php');
		}		

		return false;
	}
	/**
	 * Complete
	 *
	 * Override if applicable, but ensure that the below actions are
	 * performed, or, call parent::complete().
	 */
	protected function complete() {
		parent::complete();
		// Show notice to user or perform some other arbitrary task...
	}
}
?>
