<?php
	/**
	 * This script sync wordpress user with an active role.
	 * (except the woocommerce roles!)
	 *
	 * @version 3.4.2
	 */

	// creat a new opject if not exist
	//if(!isset($wptktAdmin)) $wptktAdmin = new WpToKlickTippAdmin();

	global $wpdb;

	if (!class_exists('WooEMI_KlicktippConnector')) {
		include(dirname(__FILE__) . '/../vendor/klicktipp.api.inc.php');
	}
	
	if (!class_exists('WpToKlickTippApi')) {
		include(dirname(__FILE__) . '/../inc/class.wp-to-klick-tipp-api.php');
	}

	$license = WpToKlickTippApi::check_sw_license_wp();

	// Get Klick-Tipp credentials
	$klicktipUsername = get_option('wptkt_klicktipp_username');
	$klicktipPassword = get_option('wptkt_klicktipp_password');

	// Connect with the Klick-Tipp API
	$apiValue = get_option('wptkt_klicktipp_api');
	if ($apiValue == 'br') {
		$apiUrl = 'https://www.klickmail.com.br/api';
	} else {
		$apiUrl = 'https://www.klick-tipp.com/api';
	}

	$connector = new WooEMI_KlicktippConnector($apiUrl);
	$isConnected = $connector->login($klicktipUsername, $klicktipPassword);

	/**
	 * Get the tag id of an specified name
	 *
	 * @param object $connector The API connector
	 * @param string $tagName The name of the tag
	 * @param array $tags The KlickTipps tags array
	 * @return int The ID of the tag
	 */
	if (!function_exists('getTagId')) {
		function getTagId($connector, $tagName, $tags) {
			$tagId = array_search($tagName, $tags);
			if (is_null($tagId) || $tagId === false) {
				// tag does not exist, create a new one
				$tagId = $connector->tag_create($tagName, '');
			}
			return $tagId;
		}
	}

	/**
	 * Check if the given customer has at least one completed order
	 */
	if (!function_exists('hasCustomerCompletedOrder')) {
		function hasCustomerCompletedOrder($customerId) {
			global $wpdb;

			$orders = $wpdb->get_row('
				SELECT COUNT(*) AS count FROM
					' . $wpdb->prefix . "posts
				WHERE
					post_status = 'wc-completed'
				AND
					ID IN (
						SELECT
							post_id
						FROM
							" . $wpdb->prefix . "postmeta
						WHERE
							meta_key = '_customer_user'
						AND
							meta_value = '" . $customerId . "'
					)
			");
			if (!is_null($orders) && ($orders->count > 0)) {
				return true;
			} else {
				return false;
			}
		}
	}

	if ($isConnected) {
		// get all existing tags from Klick-Tipp
		$tags = $connector->tag_index();

		// Get user details by ID
		$user = get_user_by('id',$iUserID);

		global $wp_roles;
		if ($license) {
			$roles = $wp_roles->get_names();			
			$roles['guest'] = 'Customer-Guest';
			$roles['customer'] = 'Customer';
		} else {
			$roles = array(
				'customer' 		=> 'Customer',
				'subscriber' 	=> 'Subscriber',
				'guest'			=> 'Customer-Guest'
			);
		}
		// check every group if it is active
		foreach ($roles AS $slug => $role) {
			$roleOption = get_option('wptkt_role_' . $slug);
			if ($roleOption != 1) {
				unset($roles[$slug]);
			}
		}

		if($user->roles) {
			unset($roles['guest']);
		}

		// check if the user has an active role for the sync
		foreach ($roles AS $slug => $role) {
			if (in_array($slug, $user->roles) || $slug == 'guest') {
			    // get user data for klick-tipp api
			    if($user) {
				    $emailAddress = $user->data->user_email;
				    $userData = get_userdata($user->data->ID);
				    $fields = array(
					    'fieldFirstName' => $userData->first_name,
					    'fieldLastName' => $userData->last_name
				    );
				} else {
					$customer = new WC_Customer( $ID );
					$emailAddress = version_compare( WC()->version, '3.0', '>=' ) ? $oOrder->get_billing_email() : $oOrder->billing_email;
				}
				// check if the user is already an klick-tipp subscriber
				$subscriberId = $connector->subscriber_search($emailAddress);
				if ($subscriberId) {
					// don't update fields for woocommerce subscriber
					if ($slug === 'customer' || $slug === 'shop_manager') {
						$fields = array();
					}
					// update existing subscriber
					$connector->subscriber_update($subscriberId, $fields);

					// update subscriber tag if not exist
					$subscriber = $connector->subscriber_get($subscriberId);
					$subscriberTags = $subscriber->tags;
					$tagId = getTagId($connector, $role, $tags);

					if (!in_array($tagId, $subscriberTags)) {
						$connector->tag($emailAddress, $tagId);
					}

				} else {

				    $doubleOptinProcessId = get_option('wptkt_role_' . $slug . '_id');
				    if (!isset($doubleOptinProcessId) || empty($doubleOptinProcessId)) {
						// if exist set variable to Standard Double Opt-In Prozess-ID, elswhere set to 0
					    $doubleOptinProcessId = (get_option('wptkt_klicktipp_prozess-id') ? get_option('wptkt_klicktipp_prozess-id') : 0 );
				    }
				    
				    // add user role as an tag for the subscriber and get the tagid
				    $tagId = getTagId($connector, $role, $tags);

				    if ($slug === 'customer') {
					    // special sync behavior for customers
					    if (hasCustomerCompletedOrder($user->data->ID)) {
						    // only sync customers if they have an completed order
						    // add new subscriber
						    $subscriber = $connector->subscribe($emailAddress, $doubleOptinProcessId, $tagId, $fields);
					    }
				    } else {
					    // add new subscriber
					    $subscriber = $connector->subscribe($emailAddress, $doubleOptinProcessId, $tagId, $fields);
				    }
			    }
			}
		}
		// disconnect from the Klick-Tipp API
		$connector->logout();
	} // End if ($isConnected)
?>
