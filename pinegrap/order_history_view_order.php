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

// if user is not logged in, send user to registration entrance screen to login or register
if (pg_session_signed_in() == false) {
    header('Location: ' . URL_SCHEME . HOSTNAME . PATH . SOFTWARE_DIRECTORY . '/registration_entrance.php?send_to=' . urlencode(get_request_uri()));
    exit();
}

// The screen draws the order into the site's page of the "view order" type.
// Without one, get_view_order_screen() falls back to a legacy screen that has
// no page to read its properties from and ends in a missing template; the
// account widget links here only while the page exists, but a link kept from
// an old e-mail or a bookmark still arrives, and is told the page is missing.
$query = "SELECT page_id FROM page WHERE page_type = 'view order' LIMIT 1";
$result = mysqli_query(db::$con, $query) or output_error(lang('Query failed.'));

if (mysqli_num_rows($result) == 0) {
    output_error('<a href="javascript:history.go(-1)">' . lang('Page cannot be found.') . '</a>', 404);
}

print get_view_order_screen();
?>