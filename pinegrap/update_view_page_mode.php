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

if (($_GET['mode'] ?? '') == 'edit') {
    $_SESSION['software']['view_page_mode'] = 'edit';

    // force toolbar closed when in edit mode
    $_SESSION['software']['toolbar_enabled'] = false;

} else {
    $_SESSION['software']['view_page_mode'] = 'preview';
}

// If there is not a current cookie or it is not equal to the current
// view page mode, then create/update it.
if ($_COOKIE['software']['view_page_mode'] != $_SESSION['software']['view_page_mode']) {
    setcookie('software[view_page_mode]', $_SESSION['software']['view_page_mode'], time() + 315360000, '/');
}

go(($_GET['send_to'] ?? ''));
?>