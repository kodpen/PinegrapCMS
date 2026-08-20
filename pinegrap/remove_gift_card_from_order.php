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

// delete applied gift card
$query = "DELETE FROM applied_gift_cards WHERE (id = '" . escape($_GET['applied_gift_card_id']) . "') AND (order_id = '" . ($_SESSION['ecommerce']['order_id'] ?? '') . "')";
$result = mysqli_query(db::$con, $query) or output_error('Query failed.');

// send user back to previous screen
header('Location: ' . URL_SCHEME . HOSTNAME . ($_GET['send_to'] ?? ''));
?>