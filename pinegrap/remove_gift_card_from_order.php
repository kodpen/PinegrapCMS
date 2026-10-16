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

validate_token_field();

// The id is one string; one not given, or given as an array, names no applied gift card.
pg_request_single_values(array('applied_gift_card_id'));

// delete applied gift card
$query = "DELETE FROM applied_gift_cards WHERE (id = '" . escape($_GET['applied_gift_card_id'] ?? '') . "') AND (order_id = '" . ($_SESSION['ecommerce']['order_id'] ?? '') . "')";
$result = mysqli_query(db::$con, $query) or output_error('Query failed.');

// send user back to previous screen
header('Location: ' . URL_SCHEME . HOSTNAME . pg_safe_redirect_path($_GET['send_to'] ?? ''));
?>