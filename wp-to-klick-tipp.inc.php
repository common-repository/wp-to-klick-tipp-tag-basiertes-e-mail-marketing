<?php
    if (basename($_SERVER['SCRIPT_FILENAME']) == 'wp-to-klick-tipp.inc.php') { die ("Please do not access this file directly. Thanks!"); }

    if (!defined( 'WPINC')) {
        die();
    }
	error_reporting(0);


	global $wpdb;

	$aWPUploadDir = wp_upload_dir();

    define('WP_UPLOAD_PATH', $aWPUploadDir['path']);
    define('WP_UPLOAD_URL', $aWPUploadDir['url']);
	define('WC_ACTIVE_FLAG',(class_exists('WooCommerce') ? TRUE : FALSE));

    define('WP_TO_KLICK_TIPP_VERSION', '3.7.7');
    define('WP_TO_KLICK_TIPP_DB_VERSION', '2.0.0');

	define('WP_TO_KLICK_TIPP_CSV_FILE_PRAEFIX','klick-tipp_export_');

    define('WP_TO_KLICK_TIPP_TABLE_LOG', $wpdb->prefix . 'wptkt_log');

    // Include 'WordPress To Klick Tipp' Application
    require_once(WP_TO_KLICK_TIPP_DIR . 'inc/class.wp-to-klick-tipp.php');
    $wptktApp = new WpToKlickTipp();
	$wptktAdmin = new WpToKlickTippAdmin();
	$bKlickTipp = !$wptktAdmin->klickTippL();
	unset($wptktAdmin);
	$bKlickTippExpiredAdminNotice = (!$bKlickTipp && get_option('wptkt_license_email') && get_option('wptkt_license_key') ? TRUE : FALSE);

    // CSV Download starts here to prevent Worpdress to send a header
	// before we can send the download header
    add_action( 'init', 'wptkt_force_csv_download' );
    function wptkt_force_csv_download() {
		global $pagenow;
        // Download CSV file for initial import in Klick-Tipp
		if ($pagenow === 'admin.php') {
			$sMod = (isset($_GET['mod']) ? $_GET['mod'] : '');
			$sAction = (isset($_GET['action']) ? $_GET['action'] : '');
            if ($sMod === 'export' && $sAction === 'download') {
				include(WP_TO_KLICK_TIPP_DIR . 'inc/csv_export.inc.php');
            }
		}
	}
	
    // Write/update KT data during save process of shop order
    add_action('save_post', 'wptkt_save_post', 11);
	function wptkt_save_post($ID) {

        // If this is a 'shop_order' and if all data are saved and an email address is available
		// go ahead
		if($_POST['wc_order_action'] == '') {
	        if (get_post_type( $ID ) === 'shop_order' && get_post_meta( $ID,'_billing_email',true)) {
						$background_proc = new WpToKlickTippBackgroundProcess();
						$background_proc->push_to_queue( $ID );
						$background_proc->save()->dispatch();
	        }
		}
		

		return $ID;
	}

	add_action( 'shutdown', 'wptkt_bg_healthcheck', 20 );

	function wptkt_bg_healthcheck() {
		if ( is_admin() ) {
			$background_proc = new WpToKlickTippBackgroundProcess();
			$background_proc->handle_cron_healthcheck();
		}
	}
	

	

    // write/update user meta to KT during profile update
    add_action( 'profile_update', 'wptkt_profile_update', 10, 2 );
    add_action( 'user_register', 'wptkt_profile_update', 10, 2 );
    function wptkt_profile_update($iUserID) {
		if (get_option('wptkt-plugin-active') == 'klicktipp') {
			// call include to write additional WP user stuff
			include(plugin_dir_path( __FILE__ ) . 'inc/wpuser2kt.inc.php');
			return $sResult;
		} else if (get_option('wptkt-plugin-active') == 'autoresponder') {
            include (plugin_dir_path( __FILE__ ) . 'inc/wpuser2autoresponder.inc.php');
        }
    }



	// Output some notices on admin screen
    add_action( 'admin_notices', function() use ($bKlickTippExpiredAdminNotice) {
		if ($_GET["page"]!='wp-to-klick-tipp-tag-basiertes-e-mail-marketing') {
			$bError = FALSE; // Pre set boolen to no errors
			$sError = '<div class="error"><p><b>'.WP_TO_KLICK_TIPP_PLUGIN_NAME.':</b>&nbsp;';
			// check if all requirements are available
			// Woocommerce
			//
			// HINT: If you add or delete requirements please double check also requirements in admin-requirement.phtml!!!
			//

			$pluginActive = get_option('wptkt-plugin-active');

			if ($pluginActive === 'klicktipp') {
				// API Key
				if (!get_option('wptkt_klicktipp_apikey')) {
					$sError .= __('Please set up your <a href="admin.php?page=wptkt&mod=account">Klick-Tipp API credentials</a>. Until done you cannot use ','wp-to-klick-tipp-tag-basiertes-e-mail-marketing').'&nbsp;'.WP_TO_KLICK_TIPP_PLUGIN_NAME.'.</p>';
					$bError = TRUE;
				}

			} else if ($pluginActive === 'autoresponder') {
				global $bKlickTipp;
				if ($bKlickTipp) {
					$autoresponders = get_option('wptkt_autoresponders');
					if (count($autoresponders) == 0) {
						echo '<div class="error">' . __('<p>Missing data in autoresponder. <a href="admin.php?page=wptkt&mod=autoresponder">Check settings</a></p>', 'wp-to-klick-tipp-tag-basiertes-e-mail-marketing') . '</div>';

					}
				} else {
					$autoresponderCode = get_option('wptkt_autoresponderCode');
					if (empty($autoresponderCode)) {
						$sError .= __('<p>Missing data in autoresponder. <a href="admin.php?page=wptkt&mod=autoresponder">Check settings</a></p>', 'wp-to-klick-tipp-tag-basiertes-e-mail-marketing');
						$bError = TRUE;
					}
				}

			} else {
				$sError .= __('<b>Thanks for using WooEMI and WP-EMI.</b><br />Please adjust the <a href="admin.php?page=wptkt">plugin settings</a> to enable the data sending between<br /> Wordpress & WooCommerce and your autoresponder like MailChimp or Klick-Tipp.', 'wp-to-klick-tipp-tag-basiertes-e-mail-marketing');
				$bError = TRUE;

			}

			// PHP version 5.1 or above
			$iPhpVersion = preg_replace("/[^0-9]/","",substr(phpversion(),0,3)); // use phpversion() for server with PHP< 5.3
			if ($iPhpVersion < 54) { // needed PHP version is 5.4
				$sError .= '<p>'.__("Please install PHP version 5.4 or above on your server.",'wp-to-klick-tipp-tag-basiertes-e-mail-marketing').'</p>';
				$bError = TRUE;
			}

			// Hints, if license is not valid
			if ($bKlickTippExpiredAdminNotice) {
				$sError .= '<p>'.__("Your WooEMI premium license got invalid! To use all features please update your license key.",'wp-to-klick-tipp-tag-basiertes-e-mail-marketing').'</p>';
				$bError = TRUE;
			}

			if ($bError) echo $sError.'</div>';
		}

    });


	/**
	* Check if the plugin version is active
	*
	* code 0 = plugin is inactive
	* code 1 = plugin is active
	* code 2 = plugin will be inactive
	*
	* @return int Status code if plugin is active
	*/
    function wptoktCheckPluginVersion() {
	    $url = 'https://saleswonder.biz/klicktip-capi/check_version.php?plugin=wptokt&version=' . WP_TO_KLICK_TIPP_VERSION;
	    if (function_exists('curl_version')) {
 		    $curl = curl_init();
		    curl_setopt($curl, CURLOPT_URL, $url);
		    curl_setopt($curl,CURLOPT_USERAGENT,'Mozilla/5.0 (Windows NT 6.1; WOW64) AppleWebKit/537.17 (KHTML, like Gecko) Chrome/24.0.1312.52 Safari/537.17');
		    curl_setopt($curl, CURLOPT_AUTOREFERER, true);
		    curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
		    curl_setopt($curl, CURLOPT_FOLLOWLOCATION, 1);
		    curl_setopt($curl, CURLOPT_VERBOSE, 1);
		    $response = trim(curl_exec($curl));
		    curl_close($curl);

	    } else if (file_get_contents(__FILE__) && ini_get('allow_url_fopen')) {
		    $response = trim(file_get_contents($url));

	    }
	    if ($response == '1') {
		    return 1;
	    } else if ($response == '2') {
		    return 2;
	    }
	    return 0;
    }

	function getWptoktPromoDownloadUrl() {

		$affiliate_id = wptkt_get_affiliate();
		if ( false !== $affiliate_id ) {
			$promoUrl = 'https://saleswonder.biz/plugins_download_server/download.php?plugin=wptokt&affiliate=' . $affiliate_id;
		} else {
			$promoUrl = 'https://saleswonder.biz/plugins_download_server/download.php?plugin=wptokt&affiliate=';
		}

		$campaign_key = wptkt_get_campaignkey();
		if ( false !== $campaign_key ) {
			$promoUrl .= '&campaign=' . $campaign_key;
		}
		return $promoUrl;
	}

	// Output notice if plugin is deprecated
    add_action( 'admin_notices', function() {
		$error = '<div class="error"><p><b>'.WP_TO_KLICK_TIPP_PLUGIN_NAME.':</b>&nbsp;';

		$versionResponse = wptoktCheckPluginVersion();
		if ($versionResponse == 0) {
			// plugin is inactive
			echo '<div class="error">'.sprintf(__('<p><b>Your %s Version is outdated and stopped working!</b></p><p>Please update the plugin: <a href="plugins.php?plugin_status=upgrade">Go to the update.</a></p>', 'wp-to-klick-tipp-tag-basiertes-e-mail-marketing'), WP_TO_KLICK_TIPP_PLUGIN_NAME, getWptoktPromoDownloadUrl()) . '</div>';

		} else if ($versionResponse == 2) {
			// plugin will be inactive
			echo '<div class="error">'. sprintf(__('<p><b>Your %s Version nearly outdated and will stopped working with the release of the next version!</b></p><p>Please update the plugin: <a href="plugins.php?plugin_status=upgrade">Go to the update.</a></p>', 'wp-to-klick-tipp-tag-basiertes-e-mail-marketing'), WP_TO_KLICK_TIPP_PLUGIN_NAME, getWptoktPromoDownloadUrl()) . '</div>';
		}

		// check for background processing
		global $wpdb;
		$bg_proc = $wpdb->get_var( "SELECT COUNT(*) FROM $wpdb->options WHERE ( option_name LIKE 'wp_wptkt_save_post_batch_%' )" );
		
		if ( $bg_proc > 0 ) {
			echo '<br /><div class="update-nag"><p>' . WP_TO_KLICK_TIPP_PLUGIN_NAME . ': ' . sprintf( _n( '%s order processing in background.', '%s orders processing in background.', $bg_proc, 'wp-to-klick-tipp-tag-basiertes-e-mail-marketing' ), $bg_proc ) . '</p></div><br />';
			wp_register_script( 'wptkp-admin-bg', plugins_url( 'inc/js/admin-background.js', __FILE__ ) , array( 'jquery' ), WP_TO_KLICK_TIPP_VERSION, true );
			wp_localize_script( 'wptkp-admin-bg', 'wptkp_init_bg', 
				array(
					'ajax_url' => admin_url( 'admin-ajax.php' ),
					'ajax_nonce' => wp_create_nonce( 'wptkp-init-bg-nonce' ),
				)
			);
			wp_enqueue_script( 'wptkp-admin-bg' );
		}

	});

	add_action( 'wp_ajax_wptkp_init_bg', 'wptkp_init_bg' );

	function wptkp_init_bg() {
		
		//security checks
		if ( ! isset( $_POST['security'] ) || ! wp_verify_nonce( sanitize_key( $_POST['security'] ), 'wptkp-init-bg-nonce' ) || ! current_user_can( 'administrator' ) ) {
			wp_die();
		}
		
		global $wpdb;
		$background_proc = new WpToKlickTippBackgroundProcess();
		$background_proc->handle_cron_healthcheck();

		$bg_proc = $wpdb->get_var( "SELECT COUNT(*) FROM $wpdb->options WHERE ( option_name LIKE 'wp_wptkt_save_post_batch_%' )" );		
		wp_send_json(
			array(
			 'success' => 'success',
			 'remaining' => $bg_proc,
			)
		);
		wp_die();
	}
	
	


	// Create an additional WooCommerce options Tab for WPTKT Settings
	// only if WooCommerce is sctive
	if (WC_ACTIVE_FLAG && $bKlickTipp) {
		// Create WooCommerce tab for WPTKT settings
		add_action('woocommerce_product_write_panel_tabs', 'wptkt_custom_tab_options_tab');
		function wptkt_custom_tab_options_tab() {
		    echo '<li class="custom_tab"><a href="#custom_tab_data">'.__('WooEMI Settings', 'wp-to-klick-tipp-tag-basiertes-e-mail-marketing').'</a></li>';
		}

		// Set Klick-Tipp options on WooCommerce tab
		add_action('woocommerce_product_write_panels', 'custom_tab_options');
		function custom_tab_options() {
			global $post;
			$wptktAdmin = new WpToKlickTippAdmin();

			$dNewsletterstart = get_post_meta($post->ID, 'wptkt_tab_newsletterstart', true);
			$sNewsletterstartFieldName = get_post_meta($post->ID, 'wptkt_tab_newsletterstart_wptkt_field', true);

			// get available fields from Klick Tipp
            $oConnector = new WooEMI_KlicktippConnector();
            $oConnector->login(get_option('wptkt_klicktipp_username'), get_option('wptkt_klicktipp_password'));
            $aKTfields = $oConnector->field_index();
            $oConnector->logout();
		?>
		<div id="custom_tab_data" class="panel woocommerce_options_panel">
			<div class="options_group">
			<p><?php _e('Set up a date when you want to start sending an follow-up email to your customers, who bought this product.<br /> Accociate customer with an existing Klick-Tipp date/time field.<br /> Please create date/time field manually inside Klick-Tipp (/ContactCloud/NewField)', 'wp-to-klick-tipp-tag-basiertes-e-mail-marketing'); ?></p>
				<p class="form-field">
				<label for="wptkt_tab_newsletterstart"><?php _e('Follow-Up Email Start Date', 'wp-to-klick-tipp-tag-basiertes-e-mail-marketing');?></label>
                <input name="wptkt_tab_newsletterstart" class="short hasDatepicker" id="wptkt_tab_newsletterstart" type="text" maxlength="10" placeholder="YYYY-MM-DD" pattern="[0-9]{4}-(0[1-9]|1[012])-(0[1-9]|1[0-9]|2[0-9]|3[01])" value="<?php echo esc_attr($dNewsletterstart); ?>">
				</p>
				<p class="form-field">
				<label for="wptkt_tab_newsletterstart_wptkt_field"><?php _e( 'Associated Klick-Tipp Field', 'wp-to-klick-tipp-tag-basiertes-e-mail-marketing' ); ?></label>
                <select name="wptkt_tab_newsletterstart_wptkt_field" class="select short" id="wptkt_tab_newsletterstart_wptkt_field">
                    <option selected="selected" value=""><?php _e( 'Do not send to Klick-Tipp', 'wp-to-klick-tipp-tag-basiertes-e-mail-marketing' ); ?></option>
					<?php
	                foreach($aKTfields as $sKey => $sValue) {
						// check if everything after the prefix 'field' is only nummeric, so it is a custom field
						if(is_numeric(substr($sKey,5))) echo '<option value="'.$sKey.'"'.($sNewsletterstartFieldName==$sKey ? ' selected="selected"' : ' ').'>'.$sValue.'</option>';
					}
					?>
                </select>
				</p>
		  </div>
		</div>
		<?php
		}

		// Processes the custom tab options when a post is saved
		add_action('woocommerce_process_product_meta', 'wptkt_process_product_meta_custom_tab');
		function wptkt_process_product_meta_custom_tab( $post_id ) {
			$wptktWriteMeta = new WpToKlickTippAdmin();
			$aTags = array();
            $oPost = get_post($post_id);

            // send product category tags to KT
            $wptktWriteMeta->sendProductCategoryToKT($oPost);

			//security check, proceed only if date is valid
			if(validateDate($_POST['wptkt_tab_newsletterstart']) && $_POST['wptkt_tab_newsletterstart_wptkt_field']) {
				update_post_meta($post_id, 'wptkt_tab_newsletterstart', $_POST['wptkt_tab_newsletterstart']);
				update_post_meta($post_id, 'wptkt_tab_newsletterstart_wptkt_field', $_POST['wptkt_tab_newsletterstart_wptkt_field']);

				// Tag for product fast_track
				$aTags[$oPost->post_name.'_fast-track'] = 'Created by WPtoKT';
				$wptktWriteMeta->writeTags($aTags);
				unset($wptktWriteMeta);
			} else {
				delete_post_meta($post_id, 'wptkt_tab_newsletterstart');
				delete_post_meta($post_id, 'wptkt_tab_newsletterstart_wptkt_field');
			}
		}
	} // if(WC_ACTIVE_FLAG)

	// Create database during activation process
	function wptkt_activate () {
		global $wpdb;

        // create database, if neccessary
		$currentDbVersion = get_option( "wptkt_db_version" );

		if (($currentDbVersion != WP_TO_KLICK_TIPP_DB_VERSION) || ($wpdb->get_var("SHOW TABLES LIKE '".WP_TO_KLICK_TIPP_TABLE_LOG."'") != WP_TO_KLICK_TIPP_TABLE_LOG)) {

		    $charsetCollate = $wpdb->get_charset_collate();

		    $sql = "CREATE TABLE ".WP_TO_KLICK_TIPP_TABLE_LOG." (
    		id mediumint(9) NOT NULL AUTO_INCREMENT,
	    	time datetime DEFAULT '0000-00-00 00:00:00' NOT NULL,
    		module tinytext NOT NULL,
	    	text text NOT NULL,
	    	UNIQUE KEY id (id)
    		) $charsetCollate;";

	    	require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
		    dbDelta( $sql );

		    // Save new DB cersion
		    update_option( 'wptkt_db_version', WP_TO_KLICK_TIPP_DB_VERSION);

			// Fill database with values during activation process
			$wpdb->insert(
			    WP_TO_KLICK_TIPP_TABLE_LOG,
    			array(
			    'time' => current_time( 'mysql' ),
			    'module' => 'Setup',
			    'text' => 'New table '.WP_TO_KLICK_TIPP_DB_VERSION.' created')
	    	);
		}
        // pre-define options
        update_option('wptkt_wordpress_cron', 1); // set flag to run sync with wordpress cronjob
	}

	// clean data and db if plugin is deinstalled
	function wptkt_uninstall() {
		global $wpdb;

		// drop table
        $wpdb->query( 'DROP TABLE IF EXISTS '.WP_TO_KLICK_TIPP_TABLE_LOG.';');

		// clean all option
        //delete_option('wptkt_klicktipp_username');
        //delete_option('wptkt_klicktipp_password');
		//delete_option('wptkt_klicktipp_api');
        //delete_option('wptkt_klicktipp_apikey');
		//delete_option('wptkt_license_email');
		//delete_option('wptkt_license_key');
		delete_option('wptkt_wordpress_cron');
		delete_option('wptkt_last_export');
		delete_option('klicktiip_unsubscribe_order');
    }

	// validate date string
	function validateDate($sDate, $sFormat = 'Y-m-d')
	{
		$d = DateTime::createFromFormat($sFormat, $sDate);
		return $d && $d->format($sFormat) == $sDate;
	}

	// Check for the affiliate id
	function wptkt_get_affiliate() {
		$wp_to_klick_tipp_affiliate   = get_option( 'wp_to_klick_tipp_affiliate' );

		if( ( defined( 'WP_TO_KLICK_TIPP_AFFILIATE' ) && $wp_to_klick_tipp_affiliate != WP_TO_KLICK_TIPP_AFFILIATE && 'Tobias-Conrad' != WP_TO_KLICK_TIPP_AFFILIATE ) || ( false === $wp_to_klick_tipp_affiliate && defined( 'WP_TO_KLICK_TIPP_AFFILIATE' ) ) ) {
			$wp_to_klick_tipp_affiliate = WP_TO_KLICK_TIPP_AFFILIATE;
			update_option( 'wp_to_klick_tipp_affiliate', $wp_to_klick_tipp_affiliate );
		}

		return $wp_to_klick_tipp_affiliate;

	}

	// Check for the affiliate id.  If its not in the database then add it
	function wptkt_get_campaignkey() {
		$wp_to_klick_tipp_campaignkey = get_option( 'wp_to_klick_tipp_campaignkey' );


		if ( ( defined( 'WP_TO_KLICK_TIPP_CAMPAIGNKEY' ) && $wp_to_klick_tipp_campaignkey != WP_TO_KLICK_TIPP_CAMPAIGNKEY && 'WooEMI_plugin_BE' != WP_TO_KLICK_TIPP_CAMPAIGNKEY ) || ( false === $wp_to_klick_tipp_campaignkey && defined( 'WP_TO_KLICK_TIPP_CAMPAIGNKEY' ) ) ) {
			$wp_to_klick_tipp_campaignkey = WP_TO_KLICK_TIPP_CAMPAIGNKEY;
			update_option( 'wp_to_klick_tipp_campaignkey', $wp_to_klick_tipp_campaignkey );
		}

		return $wp_to_klick_tipp_campaignkey;

	}

	//invalidate the transient cache if one of the options is updated
	function wtkt_invalidate_check( $new_value, $old_value ) {
		delete_transient( 'wptkt_license_check' );
		delete_transient( 'wptkt_license_check_wp' );
		return $new_value;
	}
	function wtkt_invalidate_transient() {
		add_filter( 'pre_update_option_wptkt_license_email', 'wtkt_invalidate_check', 10, 2 );
		add_filter( 'pre_update_option_wptkt_license_key', 'wtkt_invalidate_check', 10, 2 );
	}
	add_action( 'init', 'wtkt_invalidate_transient' );

	function woo_emi_add_tag_to_mail($emailAddress, $tagName) {
		require_once(WP_TO_KLICK_TIPP_DIR . 'inc/class.wp-to-klick-tipp-api.php');
		$yes		= WpToKlickTippApi::check_sw_license();
		if($yes > 0) {
			$username = get_option('wptkt_klicktipp_username');
			$password = get_option('wptkt_klicktipp_password');

			if($username && $password) {

	            $apiValue = get_option('wptkt_klicktipp_api');
		        $apiUrl = ($apiValue==='br' ? 'https://www.klickmail.com.br/api' : 'https://www.klick-tipp.com/api');

	            $connector = new WooEMI_KlicktippConnector($apiUrl);
		        $result = $connector->login($username, $password);

		        $tags = $connector->tag_index();

		        $tagId = array_search($tagName, $tags);
				if (is_null($tagId) || $tagId === false) {
					$tagId = $connector->tag_create($tagName, '');
				}
				
				$subscriber_id = $connector->subscriber_search($emailAddress);
				if($subscriber_id) {
					$connector->tag($emailAddress, $tagId);
				} else {
					$connector->subscribe($emailAddress,0, $tagId, []);
				}

		        $connector->logout();
		    }
		}
	}

?>
