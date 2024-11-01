<?php
    /**
     * Execute plugin cron jobs
     *
     * Debug cron with the following url
     * http://[URL]/wp-cron.php?doing_cron
     * 
     * @return void
     */
    function wpToKlickTippCron() {
        if (get_option('wptkt_wordpress_cron') == '1') {
            
            $versionResponse = wptoktCheckPluginVersion();
            if ($versionResponse != 0) {
			// plugin is inactive
                // cron-wp
                include('cron_wordpress.php');
                
                // cron-woocommerce if plugin is activated
                if (function_exists('woocommerce_get_page_id')) {
                    include('cron_woocommerce.php');
                }
            }
        }
    }
?>