<?php
    /**
     * Class WpToKlickTipp
     *
     * @version 2.0.0
     * @author Tobias B. Conrad <support@saleswonder.biz>
     */
    class WpToKlickTipp {

        /**
         * Constructor
         */
        public function __construct() {
            $this->loadDependencies();
            $this->addAction();
			$this->addFilter();
            
            if (is_admin()) {
                $this->addAdminActions();
            }

            // set cron
            $cronManager = new WpToKlickTippCronManager();
            $cronManager->setScheduleHook();
        }

        /**
         * Load dependencies for this class
         *
         * @access private
         * @return void
         */
        private function loadDependencies() {
            require_once(WP_TO_KLICK_TIPP_DIR . 'vendor/klicktipp.api.inc.php');
			require_once(WP_TO_KLICK_TIPP_DIR . 'inc/cron/cron.php');
            require_once(WP_TO_KLICK_TIPP_DIR . 'inc/class.wp-to-klick-tipp-admin.php');
            require_once(WP_TO_KLICK_TIPP_DIR . 'inc/class.wp-to-klick-tipp-cron-manager.php');
			require_once(WP_TO_KLICK_TIPP_DIR . 'inc/class.wp-to-klick-tipp-background-save.php');
        }

        /**
         *
         */
        private function addAction() {
			add_action('plugins_loaded', array($this, 'setLocalization'));
        }

		/**
		 *
		 */
		private function addFilter() {
			add_filter( 'plugin_action_links_' . WP_TO_KLICK_TIPP_PLUGIN_BASENAME, array($this, 'setAdminActionPluginLinks'), 10, 5 );
		}

		/**
		 *
		 */
		public function setAdminActionPluginLinks( $actions, $plugin_file ) {
			return array_merge(array('settings' => '<a href="options-general.php?page=wptkt">Einstellungen</a>'), $actions);;
		}

        /**
         * Set the localization text domain for the plugin
         *
         * @access public
         * @return void
         */
        public function setLocalization() {
            load_plugin_textdomain('wp-to-klick-tipp-tag-basiertes-e-mail-marketing', false, dirname(plugin_basename(__FILE__)) . '/../languages/');
        }

        /**
         * Add some other admin stuff
         *
         * @access private
         * @return void
         */
        private function addAdminActions() {
            $admin = new WpToKlickTippAdmin();
            $admin->addMenu();
        }

        /**
         * Activate the plugin
         *
         * @access public
         * @return void
         */
        public function activate() {
            // moved this into the constructor because the cron always disappeard
            //$cronManager = new WpToKlickTippCronManager();
            //$cronManager->setScheduleHook();
        }

        /**
         * Deactivate the plugin
         *
         * @access public
         * @return void
         */
        public function deactivate() {
            $cronManager = new WpToKlickTippCronManager();
            $cronManager->clearScheduleHook();
        }
    }
?>
