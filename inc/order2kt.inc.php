<?php

set_time_limit(0);



global $wpdb;

global $woocommerce;



$yes		= WpToKlickTippApi::check_sw_license();



$order = get_post($ID);


if(!empty($order)){



	if (!class_exists('WooEMI_KlicktippConnector')) {

		include(dirname(__FILE__) . '/../vendor/klicktipp.api.inc.php');

	}



    $klicktip_username = get_option('wptkt_klicktipp_username');

    $klicktip_password = get_option('wptkt_klicktipp_password');

    $klicktip_apikey = get_option('wptkt_klicktipp_apikey');



    $apiValue = get_option('wptkt_klicktipp_api');

    $apiUrl = ($apiValue === 'br') ? ('https://www.klickmail.com.br/api') : ('https://www.klick-tipp.com/api');



    $connector = new WooEMI_KlicktippConnector($apiUrl);

    $connector->login($klicktip_username, $klicktip_password);



	$email_address = get_post_meta($order->ID,'_billing_email',true);

	// get subscriber ID at KT

	$subscriber_id = $connector->subscriber_search($email_address);



	// get user by the email address

	// added January 13, 2015

	$emailUser = get_user_by('email', $email_address);



	$fields = array();

	$args   = array();

	$woo_order = new WC_Order($order->ID);



	$orderdata = (array) $woo_order;

	$order_status = version_compare( WC()->version, '3.0', '>=' ) ? 'wc-'.$woo_order->get_status() : 'wc-'.$woo_order->status;

	$billing_first_name	=	get_post_meta($order->ID,'_billing_first_name',true);

	$billing_last_name	=	get_post_meta($order->ID,'_billing_last_name',true);



	// get/create tag order_status

	// get existing tags from KT account

	$tag_existA = $aOrderTags = $connector->tag_index();

	// ... and search for tag id of order status

	$order_tag_id = array_search($order_status,$tag_existA);

	// create order tag and get ID, if not already exist

	if (!$order_tag_id) $order_tag_id = $connector->tag_create($order_status, $text = 'WooCommerce Order Status created by WPtoKT');



	if($yes>0){

		// get double optin ID for user role

		$emailUserRoles = $emailUser->roles;

		if (count($emailUserRoles) > 0) {

			$emailUserRole = strtolower($emailUserRoles[0]);

			$double_optin_process_id = get_option('wptkt_role_' . $emailUserRole . '_id');

			if ($double_optin_process_id === false) {

				$double_optin_process_id = (get_option('wptkt_klicktipp_prozess-id') ? get_option('wptkt_klicktipp_prozess-id') : 0 );

			}

		} else {

			$double_optin_process_id = (get_option('wptkt_klicktipp_prozess-id') ? get_option('wptkt_klicktipp_prozess-id') : 0 );

		}



		$billing_company	=	get_post_meta($order->ID,'_billing_company',true);

		$billing_address_1	=	get_post_meta($order->ID,'_billing_address_1',true);

		$billing_address_2	=	get_post_meta($order->ID,'_billing_address_2',true);

		$billing_city	=	get_post_meta($order->ID,'_billing_city',true);

		$billing_state	=	get_post_meta($order->ID,'_billing_state',true);

		$billing_postcode	=	get_post_meta($order->ID,'_billing_postcode',true);

		$billing_country	=	get_post_meta($order->ID,'_billing_country',true);

		$billing_phone	=	get_post_meta($order->ID,'_billing_phone',true);

		$order_total	=	get_post_meta($order->ID,'_order_total',true);

		$order_shipping	=	get_post_meta($order->ID,'_order_shipping',true);

		$cart_discount	=	get_post_meta($order->ID,'_cart_discount',true);

		$order_discount	=	get_post_meta($order->ID,'_order_discount',true);

		$order_tax		=	get_post_meta($order->ID,'_order_tax',true);

		$order_shipping_tax		=	get_post_meta($order->ID,'_order_shipping_tax',true);

		$total_discount	=	$order_discount+$cart_discount;

		$order_date		=	$order->post_date;



		$items = '';

		$itemsA		=	array();

		$itemsCat	=	array();

		/* get all item names */

		$item_results = $wpdb->get_results( "SELECT * FROM ".$wpdb->prefix."woocommerce_order_items WHERE order_id = $order->ID and order_item_type!='shipping'", OBJECT );



		if($item_results){

			foreach($item_results as $item_row){

				$items.=$item_row->order_item_name.",";

				/*$itemsA[]=strtolower($item_row->order_item_name);*/

				$product_id = $wpdb->get_row( "SELECT meta_value FROM ".$wpdb->prefix."woocommerce_order_itemmeta WHERE order_item_id = $item_row->order_item_id and meta_key='_product_id'", OBJECT );

				$post_data = get_post($product_id->meta_value, ARRAY_A);

				$itemsA[] = array('slug' => $post_data['post_name'], 'id' => $post_data['ID']);

				$categoryArr = get_the_terms( $product_id->meta_value, 'product_cat' );

				if(!empty($categoryArr)){

					foreach($categoryArr as $category_arr){

						$itemsCat[]		=	$category_arr->slug;

					}

				}

			}



			$itemsCat	=	array_unique($itemsCat);

		}

		$items 	=	rtrim($items,",");

		$fields = array (

		   'fieldFirstName' => $billing_first_name,

		   'fieldLastName' => $billing_last_name,

		   'fieldCompanyName' => $billing_company,

		   'fieldStreet1' => $billing_address_1,

		   'fieldStreet2' => $billing_address_2,

		   'fieldCity' => $billing_city,

		   'fieldState' => $billing_state,

		   'fieldZip' => $billing_postcode,

		   'fieldCountry' => $billing_country,

		   'fieldMobilePhone' => $billing_phone,

		);



		// set product option for newsletterstart to KT

		// and add order time to newsletter date field

		foreach($itemsA as $aItems) {

			if(get_post_meta($aItems['id'], 'wptkt_tab_newsletterstart_wptkt_field', TRUE && get_post_meta($aItems['id'], 'wptkt_tab_newsletterstart', TRUE))) {

				$tsNewsletterstart = strtotime(get_post_meta($aItems['id'], 'wptkt_tab_newsletterstart', TRUE).substr($order_date, strpos($order_date, " ")));

			    $fields[get_post_meta($aItems['id'], 'wptkt_tab_newsletterstart_wptkt_field', TRUE)] = $tsNewsletterstart;

			}

		}



		/* if he is already subscribed  update it instead of inserting*/

		if($subscriber_id) {

			/*first untag him from order status tag*/

			$subscriber = $connector->subscriber_update($subscriber_id,$fields);



			// create a tag index of all existing order status tags

			// use it below to delete existing order tags at subscribers account

			foreach($aOrderTags as $iTagID => $sTagName){

				if(substr($sTagName, 0, 3) != 'wc-') unset($aOrderTags[$iTagID]);

			}



			// get a list of existing tags from subscriber

			$oSubscriber = $connector->subscriber_get($subscriber_id);

			$aSubscriberTags = $oSubscriber->tags;



			// delete all order status tags at subscribers account

			if(!empty($aSubscriberTags)){

				// delete last order status tags

				foreach($aOrderTags as $iTagID => $iTagName){

					if(in_array($iTagID,$aSubscriberTags))

						$connector->untag($email_address,$iTagID);

				}

			}



			//set new order status tag

			$connector->tag($email_address, $order_tag_id);

		}else{ // if subscriberse not exist

			// create new subscriber with order tag

			$subscriber = $connector->subscribe($email_address,$double_optin_process_id, $order_tag_id, $fields);

		}



		/* tag all if order status is completed */
		if($order_status === 'wc-completed'){

			/* Rashmi */

			$array=array();

			$subscribed = $connector->subscriber_get($subscriber->ID);

			if(trim($subscribed->status)!='Subscribed')

			{

				$array=unserialize(get_option('klicktiip_unsubscribe_order'));

				$array[]=$order->ID;

				$array = array_unique($array);

				$NS_id=serialize($array);

				update_option('klicktiip_unsubscribe_order',$NS_id);

			}

			/***** create product tag item wise if already there don't create *****/

			/* first checke exist or not*/

			foreach($itemsA as $item_name ){

				$item_name	=	$item_name;

				$product_tag_id = array_search($item_name['slug'],$tag_existA);



				if($product_tag_id){

					$subscriber_item = $connector->tag($email_address, $product_tag_id);

				}

				else{

					/* create tag */

					$product_tag_id = $connector->tag_create($item_name,'');

					$connector->tag($email_address, $product_tag_id);

				}

			}



			/***** create Category tag item wise if already there don't create *****/

			/* first checke exist or not*/

			foreach($itemsCat as $item_cat ){

				$item_cat	=	html_entity_decode($item_cat);

				$product_cat_tag_id = array_search($item_cat,$tag_existA);

				if($product_cat_tag_id){

					$subscriber_item = $connector->tag($email_address, $product_cat_tag_id);

				}

				else{

					/* create tag */

					$product_cat_tag_id = $connector->tag_create($item_cat,'');

					$connector->tag($email_address, $product_cat_tag_id);

				}

			}



			/* tag for amounts without shipping but including tax */

			$amount_tag = ( ($order_total-$order_shipping)+ ($order_shipping_tax+$order_tax) );

			if($amount_tag<50){

				$amount_tag = 'under50';

			}

			else{

				$divinder = (int)($amount_tag/50);

				$lower_amount_tag = $divinder*50;

				$upper_amount_tag = $lower_amount_tag+50;

				$amount_tag = $lower_amount_tag."-".$upper_amount_tag;

			}

			$amount_tag_id = array_search($amount_tag,$tag_existA);

			if($amount_tag_id){

				$subscriber_amount = $connector->tag($email_address, $amount_tag_id);

			}

			else{

				/* create tag */

				$amount_tag_id = $connector->tag_create($amount_tag,'');

				$connector->tag($email_address, $amount_tag_id);

			}



			// write tag for if the order is within the last 3/6 months

			$within_3month_tag_id = array_search('order_within_3month',$tag_existA);

			if(strtotime($order_date)>strtotime("-3 months")) {

				/* it means current order is coming in between 3 months from last order */

				/* check if exist within 3month tag then dont add else add */

				if($within_3month_tag_id){

					$subscriber_item = $connector->tag($email_address, $within_3month_tag_id);

				}

				else{

					/* create tag */

					$within_3month_tag_id = $connector->tag_create('order_within_3month','');

					$connector->tag($email_address, $within_3month_tag_id);

				}

			}

			else{

				/* delete the tag */

				if($within_3month_tag_id)

				$connector->untag($email_address,$within_3month_tag_id);

			}



			$within_6month_tag_id = array_search('order_within_6month',$tag_existA);

			if(strtotime($order_date)>strtotime("-6 months")) {

				/* it means current order is coming in between 6 months from last order */

				/* check if exist within 6month tag then dont add else add */

				if($within_6month_tag_id){

					$subscriber_item = $connector->tag($email_address, $within_6month_tag_id);

				}else{

					/* create tag */

					$within_6month_tag_id = $connector->tag_create('order_within_6month','');

					$connector->tag($email_address, $within_6month_tag_id);

				}

			}

			else{

				/* delete the tag */

				if($within_6month_tag_id)

				$connector->untag($email_address,$within_6month_tag_id);

			}



			// set fast_track tag for newsletterstart to KT

			// if order date/time < newsletterstart date/time field

			foreach($itemsA as $aItems) {

				if(get_post_meta($aItems['id'], 'wptkt_tab_newsletterstart_wptkt_field', TRUE) && get_post_meta($aItems['id'], 'wptkt_tab_newsletterstart', TRUE)) {

					$tsNewsletterstart = strtotime(get_post_meta($aItems['id'], 'wptkt_tab_newsletterstart', TRUE).substr($order_date, strpos($order_date, " ")));



					if($tsNewsletterstart <= strtotime($order_date)) {

						$sFastTrackTag = $aItems['slug'].'_fast-track';

						$iFastTagID = array_search($sFastTrackTag,$tag_existA);



						if($iFastTagID){

							$connector->tag($email_address, $iFastTagID);

						}

						else{

							/* create tag */

							$iFastTagID = $connector->tag_create($sFastTrackTag,'Created by WPtoKT');

							$connector->tag($email_address, $iFastTagID);

						}

					}

				}

			}

		} // end $order_status == 'wc-completed'

	} // else $yes>0

	else{

		// free account function follows here
		$tag_id = 0;

		$fields = array (

		   'fieldFirstName' => $billing_first_name,

		   'fieldLastName' => $billing_last_name

		);



		if($order_status==='wc-on-hold' || $order_status==='wc-completed' || $order_status==='wc-processing' || $order_status==='wc-pending'){

			// if subscriber exist update instead create

			if($subscriber_id) {

				/*first untag him from order status tag*/

				$subscriber = $connector->subscriber_update($subscriber_id,$fields);



				// create a tag index of all existing order status tags

				// use it below to delete existing order tags at subscribers account

				foreach($aOrderTags as $iTagID => $sTagName){

					if(substr($sTagName, 0, 3) != 'wc-') unset($aOrderTags[$iTagID]);

				}



				// get a list of existing tags from subscriber

				$oSubscriber = $connector->subscriber_get($subscriber_id);

				$aSubscriberTags = $oSubscriber->tags;



				// delete all order status tags at subscribers account

				if(!empty($aSubscriberTags)){

					// delete last order status tags

					foreach($aOrderTags as $iTagID => $iTagName){

						if(in_array($iTagID,$aSubscriberTags))

							$connector->untag($email_address,$iTagID);

					}

				}

				//set new order status tag

				$connector->tag($email_address, $order_tag_id);

			}else{
				$license = WpToKlickTippApi::check_sw_license_wp();
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
				foreach ($roles AS $slug => $role) {
					if (in_array($slug, $user->roles) || $slug == 'guest') {
						$doubleOptinProcessId = get_option('wptkt_role_' . $slug . '_id');
					}
				}

				if (!isset($doubleOptinProcessId) || empty($doubleOptinProcessId)) {
					// if exist set variable to Standard Double Opt-In Prozess-ID, elswhere set to 0
				    $doubleOptinProcessId = (get_option('wptkt_klicktipp_prozess-id') ? get_option('wptkt_klicktipp_prozess-id') : 0 );
			    }

				$subscriber = $connector->subscribe($email_address,$doubleOptinProcessId, $order_tag_id, $fields);

			}

			}

		}

	} // end $yes>0

	$orderIDS=array();

	$subscribeId=array();

	if ( false === ( $wptkt_klicktipp_recheck_orders = get_transient( 'wptkt_klicktipp_recheck_orders' ) ) ) {
		$orderID=get_option('klicktiip_unsubscribe_order');
		$orderIDS=unserialize($orderID);
		set_transient( 'wptkt_klicktipp_recheck_orders', true, 1 * HOUR_IN_SECONDS );
	}

	if(!empty($orderIDS))

	{

		foreach($orderIDS as $order_id)

		{

			/*$terms =  wp_get_post_terms( $order_id, 'shop_order_status', $args );

			$order_status = $terms[0]->name;*/
			if(get_post_status( $order_id ) !== false) {

				$woo_order = new WC_Order($order_id);



				$orderdata = (array) $woo_order;

				$order_status = $orderdata['post_status'];



				if($order_status==='wc-completed')

				{

					$email_address	=	get_post_meta($order_id,'_billing_email',true);



					$subscribe_prev = $connector->subscriber_get($subscriber_id);

					if(trim($subscribe_prev->status)==='Subscribed')

					{



						// get user by the email address

						// added January 13, 2015

						$emailUser = get_user_by('email', $email_address);



						/* Replace 123 with the id of the double optin process.*/

						$fields = array();

						$args   = array();



						if($yes>0){

							// get double optin ID for user role

							$emailUserRoles = $emailUser->roles;

							if (count($emailUserRoles) > 0) {

								$emailUserRole = strtolower($emailUserRoles[0]);

								$double_optin_process_id = get_option('wptkt_role_' . $emailUserRole . '_id');

								if ($double_optin_process_id === false) {

									$double_optin_process_id = (get_option('wptkt_klicktipp_prozess-id') ? get_option('wptkt_klicktipp_prozess-id') : 0 );

								}

							} else {

								$double_optin_process_id = (get_option('wptkt_klicktipp_prozess-id') ? get_option('wptkt_klicktipp_prozess-id') : 0 );

							}



							$billing_first_name	=	get_post_meta($order_id,'_billing_first_name',true);

							$billing_last_name	=	get_post_meta($order_id,'_billing_last_name',true);

							$billing_company	=	get_post_meta($order_id,'_billing_company',true);

							$billing_address_1	=	get_post_meta($order_id,'_billing_address_1',true);

							$billing_address_2	=	get_post_meta($order_id,'_billing_address_2',true);

							$billing_city	=	get_post_meta($order_id,'_billing_city',true);

							$billing_state	=	get_post_meta($order_id,'_billing_state',true);

							$billing_postcode	=	get_post_meta($order_id,'_billing_postcode',true);

							$billing_country	=	get_post_meta($order_id,'_billing_country',true);

							$billing_phone	=	get_post_meta($order_id,'_billing_phone',true);

							$order_total	=	get_post_meta($order_id,'_order_total',true);

							$order_shipping	=	get_post_meta($order_id,'_order_shipping',true);

							$cart_discount	=	get_post_meta($order_id,'_cart_discount',true);

							$order_discount	=	get_post_meta($order_id,'_order_discount',true);

							$order_tax		=	get_post_meta($order_id,'_order_tax',true);

							$order_shipping_tax		=	get_post_meta($order_id,'_order_shipping_tax',true);

							$total_discount	=	$order_discount+$cart_discount;

							$order_date		=	$order->post_date;



							$items = '';

							$itemsA		=	array();

							$itemsCat	=	array();

							/* get all item names */

							$item_results = $wpdb->get_results( "SELECT * FROM ".$wpdb->prefix."woocommerce_order_items WHERE order_id = $order_id and order_item_type!='shipping'", OBJECT );

							if($item_results){

								foreach($item_results as $item_row){

									$items.=$item_row->order_item_name.",";

									$product_id = $wpdb->get_row( "SELECT meta_value FROM ".$wpdb->prefix."woocommerce_order_itemmeta WHERE order_item_id = $item_row->order_item_id and meta_key='_product_id'", OBJECT );

									$post_data = get_post($product_id->meta_value, ARRAY_A);

									$slug = $post_data['post_name'];

									$itemsA[]=$slug;

									$categoryArr	=	get_the_terms( $product_id->meta_value, 'product_cat' );

									if(!empty($categoryArr)){

										foreach($categoryArr as $category_arr){

											$itemsCat[]		=	$category_arr->slug;

										}

									}

								}

								$itemsCat	=	array_unique($itemsCat);

							}

							$items 	=	rtrim($items,",");

							$fields = array (

							   'fieldFirstName' => $billing_first_name,

							   'fieldLastName' => $billing_last_name,

							   'fieldCompanyName' => $billing_company,

							   'fieldStreet1' => $billing_address_1,

							   'fieldStreet2' => $billing_address_2,

							   'fieldCity' => $billing_city,

							   'fieldState' => $billing_state,

							   'fieldZip' => $billing_postcode,

							   'fieldCountry' => $billing_country,

							   'fieldMobilePhone' => $billing_phone,

							   'field15494' => $order_id, /* Order Number */

							   'field15495' => $order_status, /* Order Status */

							   'field15496' => $order_total,     /* Total Amount */

							   'field15497' => $items,	/* Items */

							   'field15498' => $order_date,   /* Order Date */

							   'field15550' => $order_shipping,   /* Total Shipping Fee */

							   'field15551' => $total_discount   /* Total Discount */

							 );



							// set product option for newsletterstart to KT

							// and add order time to newsletter date field

							foreach($itemsA as $aItems) {

								if(get_post_meta($aItems['id'], 'wptkt_tab_newsletterstart_wptkt_field', TRUE && get_post_meta($aItems['id'], 'wptkt_tab_newsletterstart', TRUE))) {

									$tsNewsletterstart = strtotime(get_post_meta($aItems['id'], 'wptkt_tab_newsletterstart', TRUE).substr($order_date, strpos($order_date, " ")));

									$fields[get_post_meta($aItems['id'], 'wptkt_tab_newsletterstart_wptkt_field', TRUE)] = $tsNewsletterstart;

								}

							}



							if($subscriber_id) {

								/*first untag him from order status tag*/

								$subscriber = $connector->subscriber_update($subscriber_id,$fields);



								// create a tag index of all existing order status tags

								foreach($aOrderTags as $iTagID => $sTagName){

									if(substr($sTagName, 0, 3) != 'wc-') unset($aOrderTags[$iTagID]);

								}



								// get a list of existing tags from subscriber

								$oSubscriber = $connector->subscriber_get($subscriber_id);

								$aSubscriberTags = $oSubscriber->tags;



								// delete all order status tags at subscribers account

								if(!empty($aSubscriberTags)){

									// delete last order status tags

									foreach($aOrderTags as $iTagID => $iTagName){

										if(in_array($iTagID,$aSubscriberTags))

											$connector->untag($email_address,$iTagID);

									}

								}



								//set new order status tag

								$connector->tag($email_address, $order_tag_id);

							}

							/* tag all if order status is completed */

							if($order_status === 'wc-completed'){



								/***** create product tag item wise if already there don't create *****/

								/* first checke exist or not*/

								$tag_existA = $connector->tag_index();



								foreach($itemsA as $item_name ){

									$item_name	=	html_entity_decode($item_name);

									$product_tag_id = array_search($item_name,$tag_existA);

									if($product_tag_id){

										$subscriber_item = $connector->tag($email_address, $product_tag_id);

									}

									else{

										/* create tag */

										$product_tag_id = $connector->tag_create($item_name,'');

										$connector->tag($email_address, $product_tag_id);

									}

								}





								/***** create Category tag item wise if already there don't create *****/

								/* first checke exist or not*/

								foreach($itemsCat as $item_cat ){

									$item_cat	=	html_entity_decode($item_cat);

									$product_cat_tag_id = array_search($item_cat,$tag_existA);

									if($product_cat_tag_id){

										$subscriber_item = $connector->tag($email_address, $product_cat_tag_id);

									}

									else{

										/* create tag */

										$product_cat_tag_id = $connector->tag_create($item_cat,'');

										$connector->tag($email_address, $product_cat_tag_id);

									}

								}



								/* tag for amounts without shipping but including tax */

								$amount_tag = ( ($order_total-$order_shipping)+ ($order_shipping_tax+$order_tax) );

								if($amount_tag<50){

									$amount_tag = 'under50';

								}

								else{

									$divinder = (int)($amount_tag/50);

									$lower_amount_tag = $divinder*50;

									$upper_amount_tag = $lower_amount_tag+50;

									$amount_tag = $lower_amount_tag."-".$upper_amount_tag;

								}

								$amount_tag_id = array_search($amount_tag,$tag_existA);

								if($amount_tag_id){

									$subscriber_amount = $connector->tag($email_address, $amount_tag_id);

								}

								else{

									/* create tag */

									$amount_tag_id = $connector->tag_create($amount_tag,'');

									$connector->tag($email_address, $amount_tag_id);

								}



								// write tag for if the order is within the last 3/6 months

								$within_3month_tag_id = array_search('order_within_3month',$tag_existA);

								if(strtotime($order_date)>strtotime("-3 months")) {

									/* it means current order is coming in between 3 months from last order */

									/* check if exist within 3month tag then dont add else add */

									if($within_3month_tag_id){

										$subscriber_item = $connector->tag($email_address, $within_3month_tag_id);

									}

									else{

										/* create tag */

										$within_3month_tag_id = $connector->tag_create('order_within_3month','');

										$connector->tag($email_address, $within_3month_tag_id);

									}

								}

								else{

									/* delete the tag */

									if($within_3month_tag_id)

									$connector->untag($email_address,$within_3month_tag_id);

								}



								$within_6month_tag_id = array_search('order_within_6month',$tag_existA);

								if(strtotime($order_date)>strtotime("-6 months")) {

									/* it means current order is coming in between 6 months from last order */

									/* check if exist within 6month tag then dont add else add */

									if($within_6month_tag_id){

										$subscriber_item = $connector->tag($email_address, $within_6month_tag_id);

									}else{

										/* create tag */

										$within_6month_tag_id = $connector->tag_create('order_within_6month','');

										$connector->tag($email_address, $within_6month_tag_id);

									}

								}

								else{

									/* delete the tag */

									if($within_6month_tag_id)

									$connector->untag($email_address,$within_6month_tag_id);

								}

							}

						}

					}


					/*remove order id from klicktiip_unsubscribe_order */



					$subscribeId[]=$order_id;





				} // end $subscribe_prev->status=='Subscribed'

			} // end $order_status=='wc-completed'

		} // end foreach


		$filtered = array_diff($orderIDS, $subscribeId);

		$unsubID = array_unique($filtered);

		$unsubIDS=serialize($unsubID);

		update_option('klicktiip_unsubscribe_order',$unsubIDS);



	} // end !empty($orderIDS)



$connector->logout();

?>

