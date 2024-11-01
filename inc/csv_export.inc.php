<?php 

set_time_limit(0);

global $wpdb;
global $woocommerce;

$site_url = site_url();

function filter_where_wpa89154($where = '') {
    /*posts since last export*/
    $lastCSVExport = trim(get_option('wptkt_last_export_CSV',true));
    if($lastCSVExport == '' || $lastCSVExport == '1')
		$where = '';
	else
		$where .= " AND post_modified_gmt > '" . date('Y-m-d H:i:s', $lastCSVExport) . "'";
	return $where;
}

// add filter only if download not all data
if ($_POST['download-depth'] === 'download-last') add_filter('posts_where', 'filter_where_wpa89154');

/* get all the orders of woocomerce */
$order_args	=	array(
				'posts_per_page'   => -1,
				'orderby'          => 'date',
				'order'            => 'DESC',
				'post_type'        => 'shop_order',
				'suppress_filters' => false ); 

$order_posts =	get_posts($order_args);

// remove filter only if download not all data
if ($_POST['download-depth'] === 'download-last') remove_filter('posts_where', 'filter_where_wpa89154');


if(!empty($order_posts)){
	
	// prepare CSV output
	// ---
	// make a DateTime object and get a time stamp for the filename
	$dCurrentDate = new DateTime();
	$sDate = $dCurrentDate->format("Ymd-Gis");
		
	// filename with a time stamp, to avoid duplicate filenames
	$filename = WP_TO_KLICK_TIPP_CSV_FILE_PRAEFIX . $sDate.'.csv';

	$fp = fopen(WP_UPLOAD_PATH.'/'.$filename, "w+");
	$iCSVrow = 0; // set number of rows of CSV file to zero
	$aUserInCSV = array(); // prepare array to save emails of all user in CSV files
	// ---

	foreach($order_posts as $order){
		$email_address	=	get_post_meta($order->ID,'_billing_email',true);
			
		// get user by the email address
		// added January 13, 2015
		$emailUser = get_user_by('email', $email_address);
			 
		$fields = array();
		$args   = array();	
		$woo_order = new WC_Order($order->ID);
				
		$orderdata = (array) $woo_order;
		$order_status = $orderdata['post_status'];
		
		$billing_first_name	=	get_post_meta($order->ID,'_billing_first_name',true);
		$billing_last_name	=	get_post_meta($order->ID,'_billing_last_name',true);
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
//				$items.=$item_row->order_item_name.",";
				/*$itemsA[]=strtolower($item_row->order_item_name);*/
				$product_id = $wpdb->get_row( "SELECT meta_value FROM ".$wpdb->prefix."woocommerce_order_itemmeta WHERE order_item_id = $item_row->order_item_id and meta_key='_product_id'", OBJECT );
				$post_data = get_post($product_id->meta_value, ARRAY_A);
				$itemsA[] = array('slug' => $post_data['post_name'], 'id' => $post_data['ID']); 
				$items.=$post_data['post_name'].",";

				$categoryArr = get_the_terms( $product_id->meta_value, 'product_cat' );
				if(!empty($categoryArr)){
					foreach($categoryArr as $category_arr){
						$itemsCat[]		=	$category_arr->slug;
					} // end foreach
				} // endif $categoryArr
			} // end foreach
			$itemsCat	=	array_unique($itemsCat);
		} // endif $item_results

		$items 	=	rtrim($items,",");
		$fields = array (
		   'E-Mail-Adresse' => $email_address,
		   'Herkunft' => $site_url,
		   'Vorname' => $billing_first_name,
		   'Nachname' => $billing_last_name,
		   'Firma' => $billing_company,
		   'Straße 1' => $billing_address_1,
		   'Straße 2' => $billing_address_2,
		   'Stadt' => $billing_city,
		   'Bundesland' => $billing_state,
		   'Postleitzahl' => $billing_postcode,
		   'Land' => $billing_country,
		   'Telefon' => $billing_phone,
		   'Tagging' => $order_status.','.$items // add order state and all items to tag field
		 );

		// add key words of all possible fields, in the header, if data set is for the first row
		if($iCSVrow==0) {
			// get all KT fields
            $oConnector = new WooEMI_KlicktippConnector();
	        $oConnector->login(get_option('wptkt_klicktipp_username'), get_option('wptkt_klicktipp_password'));
       		$aKTfields = $oConnector->field_index();
            $oConnector->logout();
			
			// get all products/posts of products
			$args = array(
				'post_type' => 'product',
				'meta_query' => array('key' => 'wptkt_tab_newsletterstart_wptkt_field')
				);
			$oProducts = get_posts( $args );
			if ($oProducts) {
				// Loop the products
				foreach ( $oProducts as $post ) {
					setup_postdata( $post );
					$iProductID = $post->ID;
					$sNewsletterFieldID = get_post_meta($iProductID, 'wptkt_tab_newsletterstart_wptkt_field', TRUE);
					if($sNewsletterFieldID) {
						$sNewsletterFieldName = ($aKTfields[$sNewsletterFieldID] ? $aKTfields[$sNewsletterFieldID] : 'Unknown field ID '.$sNewsletterFieldID);
						$fields[$sNewsletterFieldName] = '';
					}
				}
				wp_reset_postdata();
			}
		}

		/* tag all if order status is completed */
		if($order_status == 'wc-completed'){
			/***** create Category tag item wise if already there don't create *****/	
			/* first checke exist or not*/
			foreach($itemsCat as $item_cat ){
				$item_cat = html_entity_decode($item_cat);
				$fields['Tagging'] = $fields['Tagging'] . ',' . $item_cat; 
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
			$fields['Tagging'] = $fields['Tagging'] . ',' . $amount_tag; 

//            $fields = setTagWithinMonth(6, $email_address, $order_date, $fields, $aTemp);

			// write tag for if the order is within the last 3/6 months
			if(strtotime($order_date)>strtotime("-3 months")) {
				/* it means current order is coming in between 3 months from last order */
				/* check if exist within 3month tag then dont add else add */
                $fields['Tagging'] = $fields['Tagging'] . ',' . 'order_within_3month';
			}

			if(strtotime($order_date)>strtotime("-6 months")) {
				/* it means current order is coming in between 6 months from last order */
				/* check if exist within 6month tag then dont add else add */
                $fields['Tagging'] = $fields['Tagging'] . ',' . 'order_within_6month';
			}
		}
		
		// add product option for newsletterstart to array
		// and add order time to newsletter date field
		foreach($itemsA as $aItems) {
			$sNewsletterDate = get_post_meta($aItems['id'], 'wptkt_tab_newsletterstart', TRUE);
			$sNewsletterFieldID = get_post_meta($aItems['id'], 'wptkt_tab_newsletterstart_wptkt_field', TRUE);
			if($sNewsletterDate && $sNewsletterFieldID) {
				// at the first request get an index of field name from KT
				if(!$aKTfields) {
		            $oConnector = new WooEMI_KlicktippConnector();
    		        $oConnector->login(get_option('wptkt_klicktipp_username'), get_option('wptkt_klicktipp_password'));
            		$aKTfields = $oConnector->field_index();
		            $oConnector->logout();
				}
				$sNewsletterFieldName = ($aKTfields[$sNewsletterFieldID] ? $aKTfields[$sNewsletterFieldID] : 'Unknown field ID '.$sNewsletterFieldID);
			    $fields[$sNewsletterFieldName] = $sNewsletterDate.substr($order_date, strpos($order_date, " ")); // Add order time to Nesletter start date
			}
		}

		// check if download is only for customer with order within the last 6 months
		if ($_POST['download-value'] === 'download-customers_6month') {
			$tsOrderDate = strtotime($order_date);
			$ts6Month = strtotime("-6 month");
			$bWriteContent = ($tsOrderDate > $ts6Month) ? TRUE : FALSE;
		} else {
			$bWriteContent = TRUE;
		}
		// write content
		if($bWriteContent == TRUE) {
   			if($iCSVrow==0) fputcsv($fp, array_keys($fields),"\t"); // write keys in first row
	    	fputcsv($fp, $fields,"\t"); //write values
	    	$iCSVrow++; // count rows
			
			array_push($aUserInCSV, $email_address); // add email to list of users in csv
		}
	}

	// add WP user (no customers) if necessary
	// first we added customers, now we have to add also WP users
	if ($_POST['download-value'] === 'download-all') {
		// delete all double entries
		$aUserInCSV = array_unique($aUserInCSV);
		// get all WP useres
		$oWPUsers = get_users();

		// Array of WP_User objects.
		foreach ( $oWPUsers as $aWPUser ) {
			$email_address = $aWPUser->user_email;
			// if WP user is not in list of customers
			if(!in_array($email_address, $aUserInCSV)) {
				$billing_first_name	=	get_user_meta($aWPUser->ID,'billing_first_name',true);
				$billing_last_name	=	get_user_meta($aWPUser->ID,'billing_last_name',true);
				$billing_company	=	get_user_meta($aWPUser->ID,'billing_company',true);
				$billing_address_1	=	get_user_meta($aWPUser->ID,'billing_address_1',true);
				$billing_address_2	=	get_user_meta($aWPUser->ID,'billing_address_2',true);
				$billing_city	=	get_user_meta($aWPUser->ID,'billing_city',true);
				$billing_state	=	get_user_meta($aWPUser->ID,'billing_state',true);
				$billing_postcode	=	get_user_meta($aWPUser->ID,'billing_postcode',true);
				$billing_country	=	get_user_meta($aWPUser->ID,'billing_country',true);
				$billing_phone	=	get_user_meta($aWPUser->ID,'billing_phone',true);
				$fields = array (
				   'E-Mail-Adresse' => $email_address,
				   'Herkunft' => $site_url,
				   'Vorname' => $billing_first_name,
				   'Nachname' => $billing_last_name,
				   'Firma' => $billing_company,
				   'Straße 1' => $billing_address_1,
				   'Straße 2' => $billing_address_2,
				   'Stadt' => $billing_city,
				   'Bundesland' => $billing_state,
				   'Postleitzahl' => $billing_postcode,
				   'Land' => $billing_country,
				   'Telefon' => $billing_phone,
				);
				// write content
		   		if($iCSVrow==0) fputcsv($fp, array_keys($fields),"\t"); // write keys in first row
	   			fputcsv($fp, $fields,"\t"); //write walues
			   	$iCSVrow++; // count rows
			}
		}
	}

	fclose($fp);

    // send header to force file download
    header('pragma: public');
    header('expires: ' . gmdate('D, d M Y H:i:s') . ' GMT');
    header('cache-control: no-store, no-cache, must-revalidate');
    header('cache-control: pre-check=0, post-check=0, max-age=0');
    header('content-type: application/force-download');
    header('content-type: application/octet-stream');
    header('content-type: application/download');
    header('content-disposition: attachment;filename='.$filename);
    header('Content-Length: '.filesize(WP_UPLOAD_PATH.'/'.$filename));
	readfile(WP_UPLOAD_PATH.'/'.$filename);

    unlink(WP_UPLOAD_PATH.'/'.$filename); // delete file to prevent fill up upload folder
   	update_option('wptkt_last_export_CSV',time()); // after export update option of last updated time
    exit; // exit script to prevent getting more than the csv file.
}

// This function is prepared for an upcomming version and not in use, for the moment
function setTagWithinMonth_NEW($iMonth, $sEmail, $sOrderDate, &$fields, &$aTemp) {
	global $wpdb;
	
	// get last order
    $oSqlRow  = $wpdb->get_row("select b.`post_id`,a.`post_date` from ".$wpdb->prefix."postmeta b, ".$wpdb->prefix."posts a where b.meta_key='_billing_email' and b.meta_value='".$sEmail."' and b.post_id=a.id order by `meta_id` desc limit 0,1");

/*			$last_6_row = $wpdb->get_row("SELECT post_id, post_date
FROM ".$wpdb->prefix."postmeta
LEFT JOIN ".$wpdb->prefix."posts
ON ".$wpdb->prefix."postmeta.post_id=".$wpdb->prefix."posts.ID
WHERE meta_key='_billing_email' AND meta_value='".$email_address."'
ORDER BY meta_id DESC
limit 1,1");
*/
    // check if customer with email has already done an order
	if(!empty($oSqlRow)) {
		$tsLastOrderDate = strtotime($oSqlRow->post_date);
		$tsOrderDate = strtotime($sOrderDate);

		list($last_date,$last_time)	=	explode(" ",$sLastOrderDate);
		list($y,$m,$d)	=	explode("-",$last_date);
		list($h,$i,$s)	=	explode(":",$last_time);
		$$tsOrderInMonth = mktime($h,$i,$s,$m+$iMonth,$d,$y);

die($tsOrderDate.'<='.$tsOrderInMonth);

		if($tsOrderDate<=$tsOrderInMonth){
			/* it means current order is coming in between 6 months from last order */
			/* check if exist within 6month tag then dont add else add */
			$fields['Tagging'] = $fields['Tagging'] . ',' . 'order_within_6month'; 

array_push($aTemp, array($sEmail => 'order_within_'.$iMonth.'month'));
			}
	}
echo '<pre>'; print_r($aTemp); echo '</pre>'; 
}


?>