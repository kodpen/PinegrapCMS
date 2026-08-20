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
 *              2016–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

include('init.php');

validate_token_field();

// if the passed device type is valid, then continue to update the device type
if (
    ($_GET['device_type'] == 'desktop')
    || ($_GET['device_type'] == 'mobile')
) {
    // update the device type in the session
    $_SESSION['software']['device_type'] = $_GET['device_type'];

    // Set device type in cookie for 10 years so that we can remember the device type
    setcookie('software[device_type]', $_SESSION['software']['device_type'], time() + 315360000, '/');
}

header('Location: ' . URL_SCHEME . HOSTNAME . ($_GET['send_to'] ?? ''));
exit();
?>