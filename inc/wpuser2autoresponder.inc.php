<?php
/**
 * @version 3.4.2
 */

set_time_limit(0);

global $wpdb;

function deleteValueFromArray($value, &$array) {
    if (($key = array_search($value, $array)) !== false) {
        unset($array[$key]);
    }
}

// Get user details by ID
$user = get_user_by('id', $iUserID);

if (!empty($user)) {
    $wptktAdmin = WpToKlickTippAdmin::getInstance();

    // get user data for klick-tipp api
    $emailAddress = $user->data->user_email;
    $userData = get_userdata($user->data->ID);
    $firstName = $userData->first_name;
    $userRoles = $user->roles;

    if (!$wptktAdmin->klickTippL()) {
        $autoresponders = get_option('wptkt_autoresponders');

        foreach ($autoresponders AS $autoresponder) {
            // check user role
            $validUserCheck = false;
            $tempUserRoles = $autoresponder['user_role'];
            if (count($tempUserRoles) > 0) { // additonal check if user is registered
                if (count($userRoles) > 0) {
                    foreach ($userRoles as $userRole) {
                        if (in_array(strtolower($userRole), $tempUserRoles)) {
                            deleteValueFromArray($userRole, $tempUserRoles);
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
            if (count($tempUserRoles) > 0) { // additonal check if user is registered
                if (count($userRoles) > 0) {
                    foreach ($userRoles as $userRole) {
                        if (in_array(strtolower($userRole), $tempUserRoles)) {
                            deleteValueFromArray($userRole, $tempUserRoles);
                        }
                    }

                    if (count($tempUserRoles) == 0) {
                        // if temp user roles is empty all requirements are true
                        $validUserCheckNot = false;
                    }
                }
            }


            if ($validUserCheck && $validUserCheckNot) {
                // product passed every check for this autoresponder, send email
                $wptktAdmin->sendToAutoresponderCode($firstName, $emailAddress, $autoresponder);
            }
        }
    }
}
?>
