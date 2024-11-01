<?php
    /**
     * Class WpToKlickTippAdmin
     *
     * @version 3.4.2
     * @author Tobias B. Conrad <support@saleswonder.biz>
     */
    class WpToKlickTippAdmin {

        private static $instance = null;
        private $error;
        public $message;
        private $klickTippLU = 'aHR0cDovL3NhbGVzd29uZGVyLmJpei9rbGlja3RpcC1jYXBpL2NoZWNrX2xpY2Vuc2Vfd3AucGhw';

        /**
         * Get an instance of this class
         *
         * @static
         * @access public
         * @return object
         */
        public static function getInstance() {
            if (is_null(self::$instance)) {
                self::$instance = new self();
            }
            return self::$instance;
        }

        /**
         * Add admin menu action
         *
         * @access public
         * @return void
         */
        public function addMenu() {
    	    add_action('admin_menu', array($this, 'addMenuConfig'));
        }

        /**
         * Add admin menu page
         *
         * @access public
         * @return void
         */
        public function addMenuConfig() {
            add_menu_page(__("WooEMI WP-EMI", 'wp-to-klick-tipp-tag-basiertes-e-mail-marketing'), __("WooEMI WP-EMI", 'wp-to-klick-tipp-tag-basiertes-e-mail-marketing'), "administrator", "wptkt", array($this, 'run'));
        }

        /**
         * Add admin files
         *
         * @access public
         * @return void
         */
        public function addFiles() {
            // css files
            wp_register_style('wptkt-general-css', WP_TO_KLICK_TIPP_URL . 'assets/css/custom-style.css');
            wp_enqueue_style('wptkt-general-css');

            wp_register_style('wptkt-chosen-css', WP_TO_KLICK_TIPP_URL . 'assets/css/chosen.min.css');
            wp_enqueue_style('wptkt-chosen-css');

            // js files
            wp_enqueue_script('jquery');
            wp_enqueue_script('wptkt-general', WP_TO_KLICK_TIPP_URL . 'assets/js/general.js', array(), '1.0.0', true);

            wp_enqueue_script('wptkt-chosen-js', WP_TO_KLICK_TIPP_URL . 'assets/js/chosen.jquery.min.js', array(), '1.0.0', true);
        }

        /**
         * Run the admin backend
         *
         * @access public
         * @return void
         */
        public function run() {
            $wptktAdmin = self::getInstance();

            // add css/js files
            $wptktAdmin->addFiles();

            $wptktAdmin->handleForms();

            include(WP_TO_KLICK_TIPP_DIR . 'view/admin.phtml');
        }

        /**
         * Get Errors
         *
         * @access public
         * @return string
         */
        public function getError() {
            return $this->error;
        }

        /*
         * Get Messages
         *
         * @access public
         * @return string
         */
        public function getMessage() {
            return $this->message;
        }

        /**
         * Handle the submitted forms
         *
         * @access private
         * @return void
         */
        private function handleForms() {
            if (isset($_GET['action'])) {
                 // run cron manually
                if ($_GET['action'] == 'trigger-cron') {
//                    include('cron/cron_wordpress.php');
		            // cron-woocommerce if plugin is activated
		            if (function_exists('woocommerce_get_page_id')) {
			            include('cron/cron_woocommerce.php');
		            }
                    $this->message = __('You just run the cron manually.', 'wp-to-klick-tipp-tag-basiertes-e-mail-marketing');
                }

				// Set Klick-Tipp license
                if ($_GET['action'] == 'save-license') {

					if (!empty($_POST['wptkt_change_domain'])) {

						$licenseEmail = trim($_POST['license-email']);
						$licenseKey = trim($_POST['license-key']);

						if ($licenseEmail == '') {
							$this->error = __('Please enter your email', 'wp-to-klick-tipp-tag-basiertes-e-mail-marketing');
						} else if ($licenseKey == '') {
							$this->error = __('Please enter your license key', 'wp-to-klick-tipp-tag-basiertes-e-mail-marketing');
						} else {
							$res = $this->changeDomain($licenseEmail, $licenseKey);
							if ($res) {
								$datastring = 'product=wptokt&license_email=' . $licenseEmail . '&license_key=' . $licenseKey . '&site_url=' . site_url();
								if ($this->checkKlickTipp($datastring)) {
									update_option('wptkt_license_email', $licenseEmail);
									update_option('wptkt_license_key', $licenseKey);

									$this->message = __('The license has been moved to this domain', 'wp-to-klick-tipp-tag-basiertes-e-mail-marketing');
								} else {
									$this->error = __('The license could not be moved to this domain', 'wp-to-klick-tipp-tag-basiertes-e-mail-marketing');
								}

							} else {
								$this->error = __('The license could not be moved to this domain', 'wp-to-klick-tipp-tag-basiertes-e-mail-marketing');
							}
						}
					}


					if(isset($_POST['license-delete'])) {
						// delete license key
						delete_option('wptkt_license_email');
						delete_option('wptkt_license_key');
						$this->message = __('License key deleted successfully', 'wp-to-klick-tipp-tag-basiertes-e-mail-marketing');
						$this->writeLog('Licensing',__('license key deleted', 'wp-to-klick-tipp-tag-basiertes-e-mail-marketing'));
					} else {
						// double check and save license key
						$licenseEmail = trim($_POST['license-email']);
						$licenseKey = trim($_POST['license-key']);

						if ($licenseEmail == '') {
							$this->error = __('Please enter your email address.', 'wp-to-klick-tipp-tag-basiertes-e-mail-marketing');
						} else if ($licenseKey == '') {
							$this->error = __('Please enter your license key.', 'wp-to-klick-tipp-tag-basiertes-e-mail-marketing');
						} else {
							$datastring = 'product=wptokt&license_email=' . $licenseEmail . '&license_key=' . $licenseKey . '&site_url=' . site_url();

							if ($this->checkKlickTipp($datastring)) {
								update_option('wptkt_license_email', $licenseEmail);
								update_option('wptkt_license_key', $licenseKey);
								$this->message = __('Saved successfully', 'wp-to-klick-tipp-tag-basiertes-e-mail-marketing');
								$this->writeLog('Licensing',__('Credentials (email and/or key) changed', 'wp-to-klick-tipp-tag-basiertes-e-mail-marketing'));
							} else {
								$this->error = __('Wrong API Credentials', 'wp-to-klick-tipp-tag-basiertes-e-mail-marketing');
								$this->writeLog('Licensing',__('Wrong API Credentials', 'wp-to-klick-tipp-tag-basiertes-e-mail-marketing').' ('.$licenseEmail.', '.$licenseKey.', '.site_url().')');
							}
						}
					} // end if isset($_POST['license-delete'])
                }

                // Set Klick-Tipp account data
                if ($_GET['action'] == 'save-account') {

                    // save klick-tipp api
                    if (isset($_POST['account-api'])) {
                        $apiValue = trim($_POST['account-api']);
                        $apiValues = $this->getApiSelect();
                        if (array_key_exists($apiValue, $apiValues)) {
                            update_option('wptkt_klicktipp_api', $apiValue);
                        }
                    }

                    $klicktippUsername = trim($_POST['account-username']);
                    $klicktippPassword = trim($_POST['account-password']);
                    $klicktippAPIkey = trim($_POST['account-apikey']);
                    $klicktippProzessId = trim($_POST['account-prozess-id']);
                    if ($klicktippUsername == '') {
                        $this->error = __('Please enter your Klick-Tipp username.', 'wp-to-klick-tipp-tag-basiertes-e-mail-marketing');
                    } else if ($klicktippPassword == '') {
                        $this->error = __('Please enter your Klick-Tipp password.', 'wp-to-klick-tipp-tag-basiertes-e-mail-marketing');
                    } else if ($klicktippAPIkey == '' || strlen($klicktippAPIkey) < 13 ) {
                        $this->error = __('Please check your Klick-Tipp API key.', 'wp-to-klick-tipp-tag-basiertes-e-mail-marketing');
                    } else {
                        $correctCreds = $this->connectToKlickTipp($klicktippUsername, $klicktippPassword);
                        if ($correctCreds) {
                            update_option('wptkt_klicktipp_username', $klicktippUsername);
                            update_option('wptkt_klicktipp_password', $klicktippPassword);
                            update_option('wptkt_klicktipp_apikey', $klicktippAPIkey);
                            update_option('wptkt_klicktipp_prozess-id', $klicktippProzessId);
                            $this->message = __('Klick-Tipp Account Saved Successfully', 'wp-to-klick-tipp-tag-basiertes-e-mail-marketing');
					        $this->writeLog('Klick-Tipp',__('Klick-Tipp Account Saved Successfully', 'wp-to-klick-tipp-tag-basiertes-e-mail-marketing'));
                        } else {
                            $this->error = __('Wrong Klick-Tipp Credentials', 'wp-to-klick-tipp-tag-basiertes-e-mail-marketing');
					        $this->writeLog('Klick-Tipp',__('Wrong Klick-Tipp Credentials', 'wp-to-klick-tipp-tag-basiertes-e-mail-marketing'));
                        }
                    }
                }

                // Set Double-Opt-In-Process-Id
                if ($_GET['action'] == 'save-role-setting') {

                    $roles = $this->getUserRoles();

                    foreach ($roles AS $slug => $role) {
                        $roleEnabled = trim($_POST[$slug . '_enabled']);
                        if ($this->klickTippL()) {
	                    	if($slug != 'customer' && $slug != 'subscriber' && $slug != 'guest'){
	                    		if (isset($roleEnabled) && $roleEnabled == 1) {
	                    			$roleEnabled = 0;
	                    		}
	                    	}
	                    }
                        if (isset($roleEnabled) && $roleEnabled == 1) {
                            update_option('wptkt_role_' . $slug, 1);
                        } else {
                            update_option('wptkt_role_' . $slug, 0);
                        }
                        $roleProcessId = trim($_POST[$slug . '_id']);
                        if (isset($roleProcessId)) {
                            update_option('wptkt_role_' . $slug . '_id', $roleProcessId);
                        } else {
                            update_option('wptkt_role_' . $slug . '_id', 0);
                        }
                    }
                }

				// Save autoresponder
				if ($_GET['action'] == 'save-autoresponder') {
					if (array_key_exists('submit-save', $_POST)) {
						// save autoresponder data
						if (!$this->klickTippL()) {

							if (array_key_exists('ar_select_name', $_POST) && !empty($_POST['ar_select_name'])) {

                                // set default values
								$autoresponder = $this->getDefaultAutoresponder();

								$autoresponderKey = $this->createAutoresponderKey($_POST['ar_select_name']);

								// if autoresponder exists just update it
								$autoresponderExists = false;
								$autoresponders = array_values(get_option('wptkt_autoresponders'));
								if (is_array($autoresponders)) {
									foreach ($autoresponders AS $ar) {
										if ($ar['key'] == $autoresponderKey) {
											$autoresponder = $ar;
											$autoresponderExists = true;
										}
									}
								} else {
									$autoresponders = array();
								}

								if (!$autoresponderExists) {
									$autoresponder['key'] = $autoresponderKey;
								}

								if (array_key_exists('ar_select_name', $_POST)) {
									$autoresponder['name'] = $_POST['ar_select_name'];
								}
								if (array_key_exists('ar_woo_status', $_POST)) {
									$autoresponder['woo_status'] = $_POST['ar_woo_status'];
								} else {
									$autoresponder['woo_status'] = array();
								}
								if (array_key_exists('ar_woo_status_not', $_POST)) {
									$autoresponder['woo_status_not'] = $_POST['ar_woo_status_not'];
								} else {
									$autoresponder['woo_status_not'] = array();
								}
								if (array_key_exists('ar_woo_product', $_POST)) {
									$autoresponder['woo_product'] = $_POST['ar_woo_product'];
								} else {
									$autoresponder['woo_product'] = array();
								}
								if (array_key_exists('ar_woo_product_not', $_POST)) {
									$autoresponder['woo_product_not'] = $_POST['ar_woo_product_not'];
								} else {
									$autoresponder['woo_product_not'] = array();
								}
								if (array_key_exists('ar_woo_product_category', $_POST)) {
									$autoresponder['woo_product_category'] = $_POST['ar_woo_product_category'];
								} else {
									$autoresponder['woo_product_category'] = array();
								}
								if (array_key_exists('ar_woo_product_category_not', $_POST)) {
									$autoresponder['woo_product_category_not'] = $_POST['ar_woo_product_category_not'];
								} else {
									$autoresponder['woo_product_category_not'] = array();
								}
								if (array_key_exists('ar_user_role', $_POST)) {
									$autoresponder['user_role'] = $_POST['ar_user_role'];
								} else {
									$autoresponder['user_role'] = array();
								}
								if (array_key_exists('ar_user_role_not', $_POST)) {
									$autoresponder['user_role_not'] = $_POST['ar_user_role_not'];
								} else {
									$autoresponder['user_role_not'] = array();
								}
								if (array_key_exists('ar_code', $_POST)) {
									$autoresponder['ar_code'] = $_POST['ar_code'];
								}
								if (array_key_exists('ar_url', $_POST)) {
									$autoresponder['ar_url'] = $_POST['ar_url'];
								}
								if (array_key_exists('ar_name', $_POST)) {
									$autoresponder['ar_name'] = $_POST['ar_name'];
								}
								if (array_key_exists('ar_email', $_POST)) {
									$autoresponder['ar_email'] = $_POST['ar_email'];
								}
								if (array_key_exists('ar_hidden', $_POST)) {
									$autoresponder['ar_hidden'] = $_POST['ar_hidden'];
								}

								// update the autoresponder array again
								if ($autoresponderExists) {
									for ($i = 0; $i < count($autoresponders); $i++) {
										if ($autoresponders[$i]['key'] == $autoresponder['key']) {
											$autoresponders[$i] = $autoresponder;
										}
									}
								} else {
									$autoresponders[] = $autoresponder;
								}
								update_option('wptkt_autoresponders', $autoresponders);
								$this->message = __('Settings saved successfully', 'wp-to-klick-tipp-tag-basiertes-e-mail-marketing');

							} else {
								$this->error = __('Could not save settings. You need to set a name.', 'wp-to-klick-tipp-tag-basiertes-e-mail-marketing');
							}

						} else {
							if (array_key_exists('ar_woo_status', $_POST)) {
								update_option('wptkt_autoresponderWooStatus', $_POST['ar_woo_status']);
							} else {
								update_option('wptkt_autoresponderWooStatus', array());
							}
							if (array_key_exists('ar_code', $_POST)) {
								update_option('wptkt_autoresponderCode', $_POST['ar_code']);
							}
							if (array_key_exists('ar_url', $_POST)) {
								update_option('wptkt_autoresponderUrl', $_POST['ar_url']);
							}
							if (array_key_exists('ar_name', $_POST)) {
								update_option('wptkt_autoresponderName', $_POST['ar_name']);
							}
							if (array_key_exists('ar_email', $_POST)) {
								update_option('wptkt_autoresponderEmail', $_POST['ar_email']);
							}
							if (array_key_exists('ar_hidden', $_POST)) {
								update_option('wptkt_autoresponderHidden', $_POST['ar_hidden']);
							}

							$this->message = __('Settings saved successfully', 'wp-to-klick-tipp-tag-basiertes-e-mail-marketing');
						}

					} else if (array_key_exists('submit-reset', $_POST)) {
						// reset autoresponder data
						if (!$this->klickTippL()) {
							$autoresponders = get_option('wptkt_autoresponders');
							$autoresponderKey = $this->getSelectedAutoresponder();
							for ($i = 0; $i < count($autoresponders); $i++) {
								if ($autoresponders[$i]['key'] == $autoresponderKey) {
									unset($autoresponders[$i]);
								}
							}
							update_option('wptkt_autoresponders', array_values($autoresponders));

						} else {
							update_option('wptkt_autoresponderWooStatus', '');
							update_option('wptkt_autoresponderCode', '');
							update_option('wptkt_autoresponderUrl', '');
							update_option('wptkt_autoresponderName', '');
							update_option('wptkt_autoresponderEmail', '');
							update_option('wptkt_autoresponderHidden', '');
						}

						$this->message = __('Settings saved successfully', 'wp-to-klick-tipp-tag-basiertes-e-mail-marketing');

					}
				}

				// Save settings
				if ($_GET['action'] == 'save-settings') {
					if (array_key_exists('update_klicktippListbuilding', $_POST)) {
						// save setting data
						if ($_POST['plugin-activate']) {
							$allowedPlugins = array('klicktipp', 'autoresponder');
							if (in_array($_POST['plugin-activate'], $allowedPlugins)) {
								update_option('wptkt-plugin-active', trim($_POST['plugin-activate']));
							} else {
								update_option('wptkt-plugin-active', '');
							}
						} else {
							update_option('wptkt-plugin-active', '');
						}

						$this->message = __('Settings saved successfully', 'wp-to-klick-tipp-tag-basiertes-e-mail-marketing');
					}
				}

                // Delete all entries from Log table
                if ($_GET['action'] == 'flush-log-db') {
                    $this->clearLog();
                }
            }
        }

		private function createAutoresponderKey($name) {
			return sanitize_title(trim($name));
		}

        /**
         * Check if system requirements are given. If not, set an error message.
         *
         * @access public
         * @return boolean
         */
        public function checkSystemRequirements() {
			// is PHP module cURL installed?
            if(!in_array('curl', get_loaded_extensions())) {
                $this->error .= __('It is not possible to validate your license, because of a server error. Please install PHP module cURL on this server.<br>', 'wp-to-klick-tipp-tag-basiertes-e-mail-marketing');
		        $this->writeLog('System',__('PHP cURL missing.', 'wp-to-klick-tipp-tag-basiertes-e-mail-marketing'));
                return false;
            }
			// is licence URL reachable?
            if(!$this->checkKlickTippL()) {
                $this->error .= __('License URL is not reachable, at the moment. Please try again later.<br>', 'wp-to-klick-tipp-tag-basiertes-e-mail-marketing');
		        $this->writeLog('Licensing',__('License URL not reachable.', 'wp-to-klick-tipp-tag-basiertes-e-mail-marketing'));
                return false;
            }
            return true;
        }

        /**
         * Check if cURL is installed
         *
         * @access public
         * @return boolean
         */
        private function checkPHPCurl() {
            if  (in_array('curl', get_loaded_extensions())) { return true;}
			else {return false;}
        }

		/**
		 *
		 */
		private function changeDomain($licenseEmail, $licenseKey) {
			$data = 'product=wptokt&license_email=' . $licenseEmail . '&license_key=' . $licenseKey . '&site_url=' . site_url();

			if (function_exists('curl_version')) {
                $ch = curl_init('https://saleswonder.biz/klicktip-capi/change_domain.php');
                curl_setopt($ch, CURLOPT_POST, true);
                curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                $response = curl_exec($ch);
                curl_error($ch);

               $jsonObj = json_decode($response);
                if (is_object($jsonObj)) {
                    if ($jsonObj->success == 1) {
						return true;
					}
                }
            }
			return false;
		}

        public function checkKlickTippL() {
            if($this->checkPHPCurl()) {
                $ch = curl_init(base64_decode($this->klickTippLU));
				$iTimeout = 5;
				curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
				curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $iTimeout);
				$sData = curl_exec($ch);
				curl_close($ch);
				return (strpos($sData,'check') !== false && strpos($sData,'email') !== false && strpos($sData,'key') !== false) ? true : false;
            } else {
                return false;
            }
        }

        public function checkKlickTipp($data) {
            if($this->checkPHPCurl() && $this->checkKlickTippL()) {
                $ch = curl_init(base64_decode($this->klickTippLU));
                curl_setopt($ch, CURLOPT_POST, true);
                curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                $response = curl_exec($ch);
                curl_error($ch);
				curl_close($ch);


				$jsonObj = json_decode($response);
                if (is_object($jsonObj)) {
                    if ($jsonObj->check == 1) return true;
                }
            }
            return false;
        }

        public function isApiConnected() {
            $klicktip_username = get_option('wptkt_klicktipp_username');
            $klicktip_password = get_option('wptkt_klicktipp_password');

            return $this->connectToKlickTipp($klicktip_username, $klicktip_password);
        }

        private function connectToKlickTipp($username, $password) {
			if(!$username) $username = get_option('wptkt_klicktipp_username');
			if(!$password) $password = get_option('wptkt_klicktipp_password');

            $apiValue = get_option('wptkt_klicktipp_api');
            if ($apiValue === 'br') {
                $apiUrl = 'https://www.klickmail.com.br/api';
            } else {
                $apiUrl = 'https://www.klick-tipp.com/api';
            }

            $connector = new WooEMI_KlicktippConnector($apiUrl);
            return $connector->login($username, $password);
        }

        public function getNavigation() {
            $nav = array(
				'settings' => (object) array(
					'class' => 'nav-tab',
                    'href' => 'admin.php?page=wptkt&mod=settings',
                    'name' => __('Settings', 'wp-to-klick-tipp-tag-basiertes-e-mail-marketing')
				),
				'autoresponder' => (object) array(
                    'class' => 'nav-tab',
                    'href' => 'admin.php?page=wptkt&mod=autoresponder',
                    'name' => __('Autoresponder', 'wp-to-klick-tipp-tag-basiertes-e-mail-marketing')
                ),
                'account' => (object) array(
                    'class' => 'nav-tab',
                    'href' => 'admin.php?page=wptkt&mod=account',
                    'name' => __('Klick-Tipp (API)', 'wp-to-klick-tipp-tag-basiertes-e-mail-marketing')
                ),
                'export' => (object) array(
                    'class' => 'nav-tab',
                    'href' => 'admin.php?page=wptkt&mod=export',
                    'name' => __('CSV Export', 'wp-to-klick-tipp-tag-basiertes-e-mail-marketing')
				),
                'role-setting' => (object) array(
                    'class' => 'nav-tab',
                    'href' => 'admin.php?page=wptkt&mod=role-setting',
                    'name' => __('Role Settings (Klick-Tipp)', 'wp-to-klick-tipp-tag-basiertes-e-mail-marketing')
                ),
                'license' => (object) array(
                    'class' => 'nav-tab',
                    'href' => 'admin.php?page=wptkt&mod=license',
                    'name' => __('License', 'wp-to-klick-tipp-tag-basiertes-e-mail-marketing')
                ),
            );

            // set active class
            $mod = $_GET['mod'];
            if (isset($mod)) {
				if ($mod === 'settings') {
					$nav['settings']->class = 'nav-tab nav-tab-active';
				} else if ($mod === 'license') {
                    $nav['license']->class = 'nav-tab nav-tab-active';
                } else if ($mod === 'account') {
                    $nav['account']->class = 'nav-tab nav-tab-active';
                } else if ($mod === 'cron-setting') {
                    $nav['cron-setting']->class = 'nav-tab nav-tab-active';
                } else if ($mod === 'export') {
                    $nav['export']->class = 'nav-tab nav-tab-active';
                } else if ($mod === 'role-setting') {
                    $nav['role-setting']->class = 'nav-tab nav-tab-active';
                } else if ($mod === 'autoresponder') {
					$nav['autoresponder']->class = 'nav-tab nav-tab-active';
				}
            } else {
                $nav['settings']->class = 'nav-tab nav-tab-active';
            }

            return $nav;
        }

        public function getLastExport($sOption) {
            $last_updated_date = trim(get_option($sOption));
            if ($last_updated_date == '') {
				switch ($sOption) {
					case 'wptkt_last_export':
                        return __("Not transferred any data to Klick-Tipp.", 'wp-to-klick-tipp-tag-basiertes-e-mail-marketing');
	    			    break;
					case 'wptkt_last_export_CSV':
                        return __("So far, no file has been created.", 'wp-to-klick-tipp-tag-basiertes-e-mail-marketing');
	    			    break;
				}
            } else {
                /* get gmt offset */
                $gmt_offset	=	get_option('gmt_offset');
                if ($gmt_offset!='') {
                    $gmt_offset = get_option('gmt_offset');
                    $explode_time = explode('.', $gmt_offset);
                    $matched = strpos($explode_time[0], "-");

                    if (trim($matched) === '') {
                        $min_sign = '+';
                    } else {
                        $min_sign = '-';
                    }

                    if (!empty($explode_time[1])) {
                        if ($explode_time[1] == '5') {
                            $min = '30';
                        } elseif ($explode_time[1] == '75') {
                            $min = '45';
                        } else {
                            $min = '0';
                        }
                    } else {
                        $min = '0';
                    }

                    return date("d.m.Y H:i:s",strtotime($explode_time[0]." hours ".$min_sign.$min." min",$last_updated_date));

                } else {
                    return date("d.m.Y H:i:s",$last_updated_date);
                }
            }
        }

        public function getVersion() {
            $version = sprintf(__('Thank you for using the <b>Free Version</b>. <br /><a target="_blank" href="%s">Please upgrade to Premium</a> to get full data <br />transferred and all functions activated.', 'wp-to-klick-tipp-tag-basiertes-e-mail-marketing'), $this->getPromoUrl());
            if (!$this->klickTippL()) $version = __('</b>Premium Version active</b><br /> Thank you for using the Premium Version.', 'wp-to-klick-tipp-tag-basiertes-e-mail-marketing');

            return $version;
        }

		public function getPromoUrl() {
			$splittestUrl = "http://api.splittest-club.com/splittest.php?test=" . __('15379', 'wp-to-klick-tipp-tag-basiertes-e-mail-marketing') . "&format=clean&ip=" . $_SERVER["REMOTE_ADDR"];

			$promoProductId = '';
			$response = wp_remote_get( $splittestUrl, array( 'timeout' => 120, 'httpversion' => '1.1' ) );
			if( is_array($response) ) {
				$promoProductId = $response['body'];
			}

			$affiliate_id = wptkt_get_affiliate();

			if ( false !== $affiliate_id ) {
				$licensePromoUrl = 'http://go.' . $affiliate_id . '.' . $promoProductId . '.digistore24.com/';
			} else {
				$licensePromoUrl = 'http://go.tobias-conrad.' . $promoProductId . '.digistore24.com/';
			}

			$campaign_key = wptkt_get_campaignkey();
			if ( false !== $campaign_key ) {
				$licensePromoUrl .= $campaign_key;
			}

			return $licensePromoUrl;
		}


		public function getAffiliateParameterString() {
			$parameters = '';

			$affiliate_id = wptkt_get_affiliate();

			if ( false !== $affiliate_id ) {
				$parameters .= '&aff=' . $affiliate_id . '&affiliate=' . $affiliate_id;
			}

			$campaign_key = wptkt_get_campaignkey();
			if ( false !== $campaign_key ) {
				$parameters .= '&campaign=' . $campaign_key;
			}

			return $parameters;
		}

        public function klickTippL() {
            $lEmail = get_option('wptkt_license_email');
            $lKey = get_option('wptkt_license_key');
			if($lEmail && $lKey) {
				$datastring = 'product=wptokt&license_email=' . $lEmail . '&license_key=' . $lKey . '&site_url=' . site_url();
                if ($this->checkKlickTipp($datastring)) {
                    return false;
                }
			}
            return true;
        }

        public function getUserRoles() {
        	$resRoles = array();
            $roles = get_editable_roles();
            foreach ($roles as $key => $value) {
            	$resRoles[$key] = $value;
            	if($key == 'customer') {
            		$resRoles['guest'] = array('name' => __('Customer (Guest)', 'wp-to-klick-tipp-tag-basiertes-e-mail-marketing'));
            	}
            }
            return $resRoles;
        }

        public function getApiSelect() {
            $apiValues = array(
                'de' => (object) array(
                    'name' => __('Germany', 'wp-to-klick-tipp-tag-basiertes-e-mail-marketing')
                ),
                'br' => (object) array(
                    'name' => __('Brasil', 'wp-to-klick-tipp-tag-basiertes-e-mail-marketing')
                )
            );

            $apiValue = get_option('wptkt_klicktipp_api','');
            if (array_key_exists($apiValue, $apiValues)) {
                $apiValues[$apiValue]->selected = 'selected="selected"';
            }

            return $apiValues;
        }

        /**
         * Write new tag in KT
         *
         * @access public
         * @param array $aTags
         */
        public function writeTags($aTags) {
			$username = get_option('wptkt_klicktipp_username');
			$password = get_option('wptkt_klicktipp_password');

			if($username && $password) {

	            $apiValue = get_option('wptkt_klicktipp_api');
    	        $apiUrl = ($apiValue==='br' ? 'https://www.klickmail.com.br/api' : 'https://www.klick-tipp.com/api');

	            $connector = new WooEMI_KlicktippConnector($apiUrl);
    	        $result = $connector->login($username, $password);

				if($result) {
					$aTagsExistsInKT = $connector->tag_index();
					foreach($aTags as $sTag=>$sTagText) {
						// create tag if not exist
						if(!array_search($sTag,$aTagsExistsInKT))
							$iTagID = $connector->tag_create($sTag,$sTagText);
					}
					$connector->logout();
					return TRUE;
				} else {
					return FALSE;
				}
			} else {
				return FALSE;
			}
        }


        /**
         * get all wptkt WP options
         *
         * @access public
         * @return array
         */

		public function getWptktOptions() {
			$oOptions= wp_load_alloptions();
			$aWptktOptions = array();
			foreach( $oOptions as $sName => $sValue ) {
				if(stristr($sName, 'wp-to-klick-tipp-tag-basiertes-e-mail-marketing')) $aWptktOptions[$sName] = $sValue;
			}
			return $aWptktOptions;
		}

        /**
         * Write Log Entry
         *
         * @access public
         * @param string $sModule
         * @param string $sText
         * @return boolean
         */
        public function writeLog($sModule,$sText) {
		    global $wpdb;

		    $wpdb->insert(
		        WP_TO_KLICK_TIPP_TABLE_LOG,
    		    array(
			        'time' => current_time( 'mysql' ),
			        'module' => $sModule,
			        'text' => $sText
        		)
     	    );

            return true;
        }

        /**
         * Return Log Entry
         *
         * @access public
         * @param int $iLines
         * @return array
         */
        public function getLog($iLines=100) {
            global $wpdb;

			return $wpdb->get_results('SELECT * FROM '.WP_TO_KLICK_TIPP_TABLE_LOG.' ORDER BY `id` DESC LIMIT '.$iLines.';', ARRAY_A);
        }

        /**
         * Clear Log Table
         *
         * @access private
         * @return boolean
         */
        private function clearLog() {
            global $wpdb;
			$wpdb->get_results('TRUNCATE '.WP_TO_KLICK_TIPP_TABLE_LOG);
			$this->writeLog('Logging',__('All log data deleted.', 'wp-to-klick-tipp-tag-basiertes-e-mail-marketing'));
			return true;
        }

		public function echoAdminMessage() {
			$pluginActive = get_option('wptkt-plugin-active');
			if ($pluginActive === 'klicktipp') {
				// API Key
				if (!get_option('wptkt_klicktipp_apikey')) {
					echo '<div class="error"><p>' . __("Until you have not set up your API credentials you cannot use ",'wp-to-klick-tipp-tag-basiertes-e-mail-marketing').'&nbsp;'.WP_TO_KLICK_TIPP_PLUGIN_NAME.'.</p></div>';

				} else {
					if (!$this->isApiConnected()) {
						echo '<div class="error"><p>' . __('Sync is deactivated. Enter your correct Klick-Tipp credentials.', 'wp-to-klick-tipp-tag-basiertes-e-mail-marketing') . '</p></div>';
					}
				}

			} else if ($pluginActive === 'autoresponder') {
				if (!$this->klickTippL()) {
					$autoresponders = get_option('wptkt_autoresponders');
					if (count($autoresponders) == 0) {
						echo '<div class="error">' . __('<p>Missing data in autoresponder. <a href="admin.php?page=wptkt&mod=autoresponder">Check settings</a></p>', 'wp-to-klick-tipp-tag-basiertes-e-mail-marketing') . '</div>';

					}
				} else {
					$autoresponderCode = get_option('wptkt_autoresponderCode');
					if (empty($autoresponderCode)) {
						echo '<div class="error">' . __('<p>Missing data in autoresponder. <a href="admin.php?page=wptkt&mod=autoresponder">Check settings</a></p>', 'wp-to-klick-tipp-tag-basiertes-e-mail-marketing') . '</div>';

					}
				}
			}
		}

		public function getWooStatuses() {
			if (function_exists('wc_get_order_statuses')) {
				$statuses = wc_get_order_statuses();

				return $statuses;

			} else {
				return array();
			}
		}

		public function getWooCategories() {
			$categories = array();

			$productCategories = get_terms('product_cat', $args);
			foreach ($productCategories AS $cat) {
				$categories[$cat->term_id] = $cat->name;
			}

			return $categories;
		}

		public function getWooProducts() {
			$products = array();

			$args = array(
				'post_type' => 'product',
				'posts_per_page' => -1
			);
			$loop = new WP_Query( $args );
			if ( $loop->have_posts() ) {
				while ( $loop->have_posts() ) : $loop->the_post();
					$products[$loop->post->ID] = get_the_title();
				endwhile;
			} else {
				echo __( 'No products found' );
			}
			wp_reset_postdata();

			return $products;
		}

        public function sendProductCategoryToKT($product) {
            global $wpdb;

            $username = get_option('wptkt_klicktipp_username');
            $password = get_option('wptkt_klicktipp_password');

            if ($username && $password) {

                $apiValue = get_option('wptkt_klicktipp_api');
                $apiUrl = ($apiValue==='br' ? 'https://www.klickmail.com.br/api' : 'https://www.klick-tipp.com/api');

                $connector = new WooEMI_KlicktippConnector($apiUrl);
                $result = $connector->login($username, $password);

                if($result) {

                    $tag_existA = $connector->tag_index();

					// check product tag
					$product_tag_id = array_search($product->post_name, $tag_existA);
					if (!$product_tag_id) {
						/* create tag */
						$connector->tag_create($product->post_name,'');
					}

					// check product categories
					$categories = array();
					$terms = get_the_terms( $product->ID, 'product_cat' );
					foreach ($terms as $term) {
						$product_cat_tag_id = array_search($term->slug, $tag_existA);
						if (!$product_cat_tag_id) {
							/* create tag */
							$connector->tag_create($term->slug,'');
						}
					}
                }
            }
        }

		private function getDefaultAutoresponder() {
			return array(
				'key' => '',
				'name' => '',
				'woo_status' => array(),
				'woo_status_not' => array(),
				'woo_product' => array(),
				'woo_product_not' => array(),
				'woo_product_category' => array(),
				'woo_product_category_not' => array(),
				'user_role' => array(),
				'user_role_not' => array(),
				'ar_code' => '',
				'ar_name' => '',
				'ar_url' => '',
				'ar_email' => '',
				'ar_hidden' => '',
			);
		}

		public function getSelectedAutoresponder() {
			if (array_key_exists('ar', $_GET)) {
				return $_GET['ar'];
			} else {
				$wptktAutoresponder = get_option('wptkt_autoresponders');
				if (count($wptktAutoresponder) > 0) {
					return $wptktAutoresponder[0]['key'];
				}
			}
			return '';
		}

		public function getCurrentSelectedAutoresponder() {
			if (array_key_exists('ar', $_GET)) {
				$wptktAutoresponder = get_option('wptkt_autoresponders');
				foreach($wptktAutoresponder AS $ar) {
					if ($ar['key'] == $_GET['ar']) {
						return $ar;
					}
				}
			} else {
				$wptktAutoresponder = get_option('wptkt_autoresponders');
				if (count($wptktAutoresponder) > 0) {
					return $wptktAutoresponder[0];
				}
			}
			return $this->getDefaultAutoresponder();
		}

		public function getAllAutoresponder() {
			$autoresponders = array();

			$wptktAutoresponder = get_option('wptkt_autoresponders');
			foreach($wptktAutoresponder AS $ar) {
				$autoresponders[$ar['key']] = $ar['name'];
			}

			return $autoresponders;
		}

		public function sendToAutoresponderCode($name, $email, $autoresponder = null) {
			if (is_null($autoresponder)) {
				$arName = get_option('wptkt_autoresponderName');
				$arEmail = get_option('wptkt_autoresponderEmail');
				$arUrl = get_option('wptkt_autoresponderUrl');
				$arHidden = get_option('wptkt_autoresponderHidden');

			} else {
				$arName = $autoresponder['ar_name'];
				$arEmail = $autoresponder['ar_email'];
				$arUrl = $autoresponder['ar_url'];
				$arHidden = $autoresponder['ar_hidden'];

			}

			// Build first array
			$viewable_fields = array(
				stripslashes($arName) => stripslashes($name),
				stripslashes($arEmail) => stripslashes($email)
			);
			// Get and extract hidden fields from options
			if (!empty($arHidden)) {
				$html = stripslashes($arHidden);
				$dom = new DOMDocument();
				$dom->loadHTML($html);
				$xpath = new DOMXPath($dom);
				$tags = $xpath->query('//input[@type="hidden"]');

				$hidden_fields = array();
				foreach ($tags as $tag) {
					$hidden_fields[$tag->getAttribute('name')] = $tag->getAttribute('value');
				}
			}
			// Build body to submit
			$body = array_merge($viewable_fields, $hidden_fields);

			$data = array(
				'method' => 'POST',
				'timeout' => 45,
				'redirection' => 5,
				'httpversion' => '1.0',
				'blocking' => true,
				'headers' => array(),
				'body' => $body,
				'cookies' => array()
			);

			// Send to email service
			return $this->sendForm(stripslashes($arUrl), $data);
		}

		public function sendForm($postUrl, $data){
			$request = new WP_Http();
			$response = $request->post($postUrl, $data);

			if ($response instanceof WP_Error) {
				return false;
			} else {
				return true;
			}
		}

    }


?>
