<?php 

set_time_limit(0);

global $wpdb;
global $woocommerce;

$site_url	=	site_url();

if (!function_exists('requestKlickTipp')) {
	function requestKlickTipp($datastring) {
		$ch		=	curl_init(base64_decode('aHR0cHM6Ly9zYWxlc3dvbmRlci5iaXova2xpY2t0aXAtY2FwaS9jaGVja19saWNlbnNlLnBocA=='));
		curl_setopt($ch,CURLOPT_POST,true);				
		curl_setopt($ch,CURLOPT_POSTFIELDS,$datastring);	
		curl_setopt($ch,CURLOPT_RETURNTRANSFER,true);		
		$output	=	curl_exec($ch);		
		curl_error($ch);	
		return $output;	
	
	}
}
$license_email	=	get_option('wptkt_license_email');		
$license_key	=	get_option('wptkt_license_key');
$datastring = 'product=wptokt&license_email='.$license_email.'&license_key='.$license_key.'&site_url='.$site_url;
$yes		= requestKlickTipp($datastring);

/* get all the orders of woocomerce older than 3 month */

$order_args	=	array(
						'posts_per_page'   => -1,
						'offset'           => 0,
						'category'         => '',
						'orderby'          => 'post_date',
						'order'            => 'DESC',
						'include'          => '',
						'exclude'          => '',
						'meta_key'         => '',
						'meta_value'       => '',
						'post_type'        => 'shop_order',
						'post_mime_type'   => '',
						'post_parent'      => '',
						'post_status'      => 'publish',
					    'date_query'       => array('before' => date('Y-m-d', strtotime('-3 months'))),
						'suppress_filters' => false ); 

$order_posts	=	get_posts($order_args);

if(!empty($order_posts)){
    require_once(dirname(__FILE__) . '/../../vendor/klicktipp.api.inc.php');

	$klicktip_username = get_option('wptkt_klicktipp_username');
	$klicktip_password = get_option('wptkt_klicktipp_password');
	$klicktip_apikey = get_option('wptkt_klicktipp_apikey');
	
	$apiValue = get_option('wptkt_klicktipp_api');
	if ($apiValue == 'br') {
		$apiUrl = 'https://www.klickmail.com.br/api';
	} else {
		$apiUrl = 'https://www.klick-tipp.com/api';
	}
	
	$connector = new WooEMI_KlicktippConnector($apiUrl);
	$connector->login($klicktip_username, $klicktip_password);

	// get list of available Tags
	$aTagIndex=$connector->tag_index();
	
	// check if tag already exist in the account, which we want to untag
    if(in_array('order_within_6month',$aTagIndex) || in_array('order_within_3month',$aTagIndex)) {
		// get tag ids
		$iTagID6month = array_search('order_within_6month',$aTagIndex);
		$iTagID3month = array_search('order_within_3month',$aTagIndex);

    	foreach($order_posts as $order){
			$email_address	=	get_post_meta($order->ID,'_billing_email',true);
			$order_date		=	$order->post_date;
			
			if(strtotime($order_date)<strtotime("-3 months") && $iTagID3month) {
				$t=$connector->untag($email_address,$iTagID3month);
			}
			if(strtotime($order_date)<strtotime("-6 months") && $iTagID6month) {
				$t=$connector->untag($email_address,$iTagID6month);
			}
		}
    }
} // end !empty($orderIDS)
$connector->logout();
?>