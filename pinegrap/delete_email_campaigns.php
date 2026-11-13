<?php
/**
 * PineGrap - Enterprise Website Platform
 *
 * Originally developed as LiveSite by Camelback Web Architects.
 * Since 2017, maintained and evolved by Erdal Güral (Kodpen) under the name PineGrap.
 * The final LiveSite update (2019) has been integrated into PineGrap.
 * LiveSite remains available as a separate downloadable legacy version.
 *
 * @author      Camelback Web Architects
 *              Erdal Güral (Kodpen)
 * @link        https://livesite.com
 *              https://kodpen.com
 * @copyright   2001–2019 Camelback Consulting, Inc.
 *              2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

include('init.php');
require_once(PG_FUNCTIONS_DIR . '/includes/fn/email_campaign_list.php');
$user = validate_user();
validate_email_access($user);

validate_token_field();

include_once('liveform.class.php');

// Both campaign states are one list now (view_email_campaigns.php).
$liveform = new liveform('view_email_campaigns');

// The selected campaigns a role 3 user created, every selected one above.
if ($_POST['email_campaigns'] ?? array()) {
    $number_of_email_campaigns = pg_email_campaigns_delete((array) $_POST['email_campaigns'], $user);

    // if 1 or more e-mail campaigns were deleted then log this information and output a notice
    if ($number_of_email_campaigns > 0) {
        log_activity(lang(array('string'=>'{var:1} campaign(s) were deleted','vars'=>$number_of_email_campaigns)), $_SESSION['sessionusername']);

        $liveform->add_notice(lang(array('string'=>'{var:1} campaign(s) were deleted','vars'=>$number_of_email_campaigns)));
    }
}

// If there is a send to set, then forward user to send to.
if (($_POST['send_to'] ?? '') != '') {
    header('Location: ' . URL_SCHEME . HOSTNAME . pg_safe_redirect_path(($_POST['send_to'] ?? '')));
    
// Otherwise there is not a send to set, so forward user to view e-mail campaigns screen.
} else {
    header('Location: ' . URL_SCHEME . HOSTNAME . PATH . SOFTWARE_DIRECTORY . '/view_email_campaigns.php');
}
?>