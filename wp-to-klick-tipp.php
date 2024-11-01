<?php
/**
 * Plugin Name: WooCommerce & WP Email Marketing Integration
 * Version: 3.8.1
 * Plugin URI: https://saleswonder.biz?=utm_source=wpadmin&utm_medium=plugin&utm_campaign=WooEMI
 * Description: Action based user data sending, between Wordpress & WooCommerce and Klick-Tipp
 * Author: Tobias B. Conrad
 * Author URI: https://saleswonder.biz
 * Requires at least: 5.0
 * Tested up to: 5.5
 * WC requires at least: 4
 * WC tested up to: 4.4
 * Requires at least WooCommerce: 4
 * Tested up to WooCommerce: 4.4
 * Text Domain: wp-to-klick-tipp-tag-basiertes-e-mail-marketing
 * Domain Path: /languages
 * License: GPLv3 or later
 */


/**
 * WooCommerce & WP Email Marketing Integration
 * Copyright (C) since 2014, Tobias B. Conrad (email : support@saleswonder.biz)
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 */

 /**
 * DISTRIBUTION
 * You are very welcome to share the unaltered version.
 * Also see our partner program https://saleswonder.biz/?p=599&utm_source=wpadmin&utm_medium=plugin&utm_campaign=WooEMI
 *
 * Requirements for this Plugin:
 * - PHP >= 5.4
 * - PHP curl Library
 * - WooCommerce for all functions
 * AFFILIATE_ID, CAMPAIGN_KEY
 */

    if (basename($_SERVER['SCRIPT_FILENAME']) == 'wp-to-klick-tipp.php') { die ("Please do not access this file directly. Thanks!"); }

    if (!defined( 'WPINC')) {
        die();
    }

	define('WP_TO_KLICK_TIPP_AFFILIATE', 'Tobias-Conrad');

	define('WP_TO_KLICK_TIPP_CAMPAIGNKEY', 'WooEMI_plugin_BE');



	define('WP_TO_KLICK_TIPP_DIR', plugin_dir_path(__FILE__));

	define('WP_TO_KLICK_TIPP_URL', plugin_dir_url(__FILE__));
	define('WP_TO_KLICK_TIPP_PLUGIN_NAME', __('WooEMI','wp-to-klick-tipp-tag-basiertes-e-mail-marketing'));
	define('WP_TO_KLICK_TIPP_PLUGIN_BASENAME', plugin_basename(__FILE__));

	require_once(WP_TO_KLICK_TIPP_DIR . 'wp-to-klick-tipp.inc.php');

	// Register activation / deactivation hooks
	register_activation_hook(__FILE__, 'wptkt_activate' );
	register_activation_hook(__FILE__, array('WpToKlickTipp', 'activate'));
	register_deactivation_hook(__FILE__, array('WpToKlickTipp', 'deactivate'));
	register_uninstall_hook(__FILE__, 'wptkt_uninstall');

?>
