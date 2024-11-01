<?php
/**
 * Class WpToKlickTippApi
 *
* @version 1.0.0
*/
class WpToKlickTippApi {

	public static $sw_check_wp = false;
	public static $sw_license_wp = 0;

	public static $sw_check = false;
	public static $sw_license = 0;

	/*
	 *  Perform SW license check
	 */
	public static function check_sw_license() {

		if ( false === self::$sw_check ) {

			// Check if transient is set
			$license_check = get_transient( 'wptkt_license_check' );

			if ( false === $license_check ) {
				//Transient not set
				$licenseEmail = get_option( 'wptkt_license_email' );
				$licenseKey = get_option( 'wptkt_license_key' );

				$datastring = 'license_email=' . $licenseEmail . '&license_key=' . $licenseKey . '&site_url=' . site_url();
				$response = wp_remote_get( base64_decode( 'aHR0cHM6Ly9zYWxlc3dvbmRlci5iaXova2xpY2t0aXAtY2FwaS9jaGVja19saWNlbnNlLnBocA==' ) . '?' . $datastring, array( 'timeout' => 30, 'httpversion' => '1.1' ) );

				if ( is_array( $response ) ) {
					self::$sw_license = $response['body'];
				}

				set_transient( 'wptkt_license_check', self::$sw_license, HOUR_IN_SECONDS * 8 );
				self::$sw_check = true;
				return self::$sw_license;
			} else {
				//Transient is set
				self::$sw_check = true;
				self::$sw_license = $license_check;
				return self::$sw_license;
			}
		} else {
			return self::$sw_license;
		}

	}

	/*
	 *  Perform SW license check
	 */
	public static function check_sw_license_wp() {

		if ( false === self::$sw_check_wp ) {

			// Check if transient is set
			$license_check = get_transient( 'wptkt_license_check_wp' );

			if ( false === $license_check ) {
				//Transient not set
				$licenseEmail = get_option( 'wptkt_license_email' );
				$licenseKey = get_option( 'wptkt_license_key' );

				$datastring = 'license_email=' . $licenseEmail . '&license_key=' . $licenseKey . '&site_url=' . site_url();
				$response = wp_remote_get( base64_decode( 'aHR0cHM6Ly9zYWxlc3dvbmRlci5iaXova2xpY2t0aXAtY2FwaS9jaGVja19saWNlbnNlX3dwLnBocA==' ) . '?' . $datastring, array( 'timeout' => 30, 'httpversion' => '1.1' ) );

				if ( is_array( $response ) ) {
					$response_json = json_decode( $response['body'] );
					if ( is_object( $response_json ) && 1 == $response_json->check ) {
						self::$sw_license_wp = 1;
					}
				}

				set_transient( 'wptkt_license_check_wp', self::$sw_license_wp, HOUR_IN_SECONDS * 8 );
				self::$sw_check_wp = true;
				return self::$sw_license_wp;
			} else {
				//Transient is set
				self::$sw_check_wp = true;
				self::$sw_license_wp = $license_check;
				return self::$sw_license_wp;
			}
		} else {
			return self::$sw_license_wp;
		}

	}

		/*
		 * Add/modify user to contact cloud at Klick-Tipp
         *
         * @access private
         * @param string $username
         * @param string $password
         * @return boolean
		 */
		public function User2KT($iOrderID) {
            $sKTUsername = get_option('wptkt_klicktipp_username');
            $sKTPassword = get_option('wptkt_klicktipp_password');
            $sKTApiKey = get_option('wptkt_klicktipp_apikey');
			$sUserEmail = get_post_meta($iOrderID,'_billing_email',true);

			$aFields = $this->getKTfields($iOrderID);

            $oConnector = new WooEMI_KlicktippConnector();
			$sRedirectUrl = $oConnector->signin($sKTUsername, $sUserEmail, $aFields);

			if (!$sRedirectUrl) {
				print $connector->get_last_error();
			}
		}


        /**
         * Connect to the Klick-Tipp API
         *
         * @access private
         * @param string $username
         * @param string $password
         * @return boolean
         */
        private function connectToKlickTipp($username, $password) {
            $apiValue = get_option('wptkt_klicktipp_api');
            if ($apiValue == 'br') {
                $apiUrl = 'https://www.klickmail.com.br/api';
            } else {
                $apiUrl = 'https://www.klick-tipp.com/api';
            }

            $connector = new WooEMI_KlicktippConnector($apiUrl);
            return $connector->login($username, $password);
        }

        /**
         * Check if a connection to klick-tipp is established
         *
         * @access public
         * @return boolean
         */
        public function isApiConnected() {
            $klicktip_username = get_option('wptkt_klicktipp_username');
            $klicktip_password = get_option('wptkt_klicktipp_password');

            return $this->connectToKlickTipp($klicktip_username, $klicktip_password);
        }


		public function getKlickTippFields(){
            $klicktip_username = get_option('wptkt_klicktipp_username');
            $klicktip_password = get_option('wptkt_klicktipp_password');

            $connector = $this->connectToKlickTipp($klicktip_username, $klicktip_password);
			$fields = $connector->field_index();

			$connector->logout();



			if ($fields) {
				print('<pre>'.print_r($fields, true).'</pre>');
				return $fields;
			} else {
				return $connector->get_last_error();
			}

		}

        /**
         * Get user roles from wordpress
         *
         * @access public
         * @return array
         */
        public function getUserRoles() {
            if (!$this->klickTippL()) {
                $roles = get_editable_roles();
            } else {
                $roles = array(
                    'customer' => array('name' => 'Customer'),
                    'subscriber' => array('name' => 'Subscriber')
                );
            }
            return $roles;
        }

    }

?>
