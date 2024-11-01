<?php
/**
 * @version 3.4.2
 */

set_time_limit(0);

global $wpdb;
global $woocommerce;

function deleteValueFromArray($value, &$array) {
    if (($key = array_search($value, $array)) !== false) {
        unset($array[$key]);
    }
}


$order = get_post($ID);

if (!empty($order)) {

    $woo_order = new WC_Order($order->ID);

	$orderdata = (array) $woo_order;
	$order_status = $orderdata['post_status'];

    $email_address = get_post_meta($order->ID,'_billing_email',true);
	$billing_first_name	=	get_post_meta($order->ID,'_billing_first_name',true);

    // get user by the email address
	// added January 13, 2015
	$emailUser = get_user_by('email', $email_address);

    $wptktAdmin = WpToKlickTippAdmin::getInstance();

    if (!$wptktAdmin->klickTippL()) {
        $autoresponders = get_option('wptkt_autoresponders');

        foreach ($autoresponders AS $autoresponder) {
            // check order status
            $validStatusCheck = false;
            $tempOrderStatus = $autoresponder['woo_status'];
            if ((count($tempOrderStatus) > 0) && is_array($tempOrderStatus)) {
                if (in_array($order_status, $tempOrderStatus)) {
                    $validStatusCheck = true;
                }
            } else {
                // no status given
                $validStatusCheck = true;
            }


            // check order status (NOT)
            $validStatusCheckNot = true;
            $tempOrderStatus = $autoresponder['woo_status_not'];
            if ((count($tempOrderStatus) > 0) && is_array($tempOrderStatus)) {
                if (in_array($order_status, $tempOrderStatus)) {
                    $validStatusCheckNot = false;
                }
            }


            // check user role
            $validUserCheck = false;
            $tempUserRoles = $autoresponder['user_role'];
            if ((count($tempUserRoles) > 0) && ($emailUser !== false)) { // additonal check if user is registered
                $emailUserRoles = $emailUser->roles;
                if (count($emailUserRoles) > 0) {
                    foreach ($emailUserRoles as $userRole) {
                        if (in_array(strtolower($userRole), $tempUserRoles)) {
                            deleteValueFromArray(strtolower($userRole), $tempUserRoles);
                        }
                    }

                    if (count($tempUserRoles) == 0) {
                        // if temp user roles is empty all requirements are true
                        $validUserCheck = true;
                    }
                }
            } else {
                // no user role given
                $validUserCheck = true;
            }


            // check user role (NOT)
            $validUserCheckNot = true;
            $tempUserRoles = $autoresponder['user_role_not'];
            if ((count($tempUserRoles) > 0) && ($emailUser !== false)) { // additonal check if user is registered
                $emailUserRoles = $emailUser->roles;
                if (count($emailUserRoles) > 0) {
                    foreach ($emailUserRoles as $userRole) {
                        if (in_array(strtolower($userRole), $tempUserRoles)) {
                            deleteValueFromArray(strtolower($userRole), $tempUserRoles);
                        }
                    }

                    if (count($tempUserRoles) == 0) {
                        // if temp user roles is empty all requirements are true
                        $validUserCheckNot = false;
                    }
                }
            }


            // check product
            $validProductCheck = false;
            $tempWooProducts = $autoresponder['woo_product'];
            if (count($tempWooProducts) > 0) {
                $items = $woo_order->get_items();
                foreach ($items AS $item) {
                    if (in_array($item['product_id'], $tempWooProducts)) {
                        deleteValueFromArray($item['product_id'], $tempWooProducts);
                    }
                }

                if (count($tempWooProducts) == 0) {
                    // if temp woo products is empty all requirements are true
                    $validProductCheck = true;
                }
            } else {
                // no product given
                $validProductCheck = true;
            }


            // check product (NOT)
            $validProductCheckNot = true;
            $tempWooProducts = $autoresponder['woo_product_not'];
            if (count($tempWooProducts) > 0) {
                $items = $woo_order->get_items();
                foreach ($items AS $item) {
                    if (in_array($item['product_id'], $tempWooProducts)) {
                        deleteValueFromArray($item['product_id'], $tempWooProducts);
                    }
                }

                if (count($tempWooProducts) == 0) {
                    // if temp woo products is empty all requirements are true
                    $validProductCheckNot = false;
                }
            }


            // check product category
            $validCategoryCheck = false;
            $tempWooProductCategories = $autoresponder['woo_product_category'];
            if (count($tempWooProductCategories) > 0) {
                $items = $woo_order->get_items();
                foreach ($items as $item) {
                    $termList = wp_get_post_terms($item['product_id'],'product_cat',array('fields'=>'ids'));
                    foreach ($termList as $catId) {
                        if (in_array($catId, $tempWooProductCategories)) {
                            deleteValueFromArray($catId, $tempWooProductCategories);
                        }
                    }
                }

                if (count($tempWooProductCategories) == 0) {
                    // if temp woo product categories is empty all requirements are true
                    $validCategoryCheck = true;
                }
            } else {
                // no category given
                $validCategoryCheck = true;
            }


            // check product category (NOT)
            $validCategoryCheckNot = true;
            $tempWooProductCategories = $autoresponder['woo_product_category_not'];
            if (count($tempWooProductCategories) > 0) {
                $items = $woo_order->get_items();
                foreach ($items as $item) {
                    $termList = wp_get_post_terms($item['product_id'],'product_cat',array('fields'=>'ids'));
                    foreach ($termList as $catId) {
                        if (in_array($catId, $tempWooProductCategories)) {
                            deleteValueFromArray($catId, $tempWooProductCategories);
                        }
                    }
                }

                if (count($tempWooProductCategories) == 0) {
                    // if temp woo product categories is empty all requirements are true
                    $validCategoryCheckNot = false;
                }
            }


            if ($validStatusCheck && $validStatusCheckNot && $validUserCheck && $validUserCheckNot && $validProductCheck && $validProductCheckNot && $validCategoryCheck && $validCategoryCheckNot) {
                // product passed every check for this autoresponder, send email
                $wptktAdmin->sendToAutoresponderCode($billing_first_name, $email_address, $autoresponder);
            }
        }

    } else {
        // free version
        $arOrderStatus = get_option('wptkt_autoresponderWooStatus');
        if (is_array($arOrderStatus)) {
            if (count($arOrderStatus) > 0) {
                if (in_array($order_status, $arOrderStatus)) {
                    // order status matches
                    $wptktAdmin->sendToAutoresponderCode($billing_first_name, $email_address);
                }
            } else {
                // no status given
                $wptktAdmin->sendToAutoresponderCode($billing_first_name, $email_address);
            }
        } else {
            // no status given
            $wptktAdmin->sendToAutoresponderCode($billing_first_name, $email_address);
        }

    }


} // end !empty($orderIDS)
?>
