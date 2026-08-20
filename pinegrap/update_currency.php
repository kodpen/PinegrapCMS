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

if ($_REQUEST['currency_id']) {
    $_SESSION['ecommerce']['currency_id'] = $_REQUEST['currency_id'];
    
    // if visitor tracking is on or there is an order for this visitor, then get currency code
    if ((VISITOR_TRACKING == true) || (isset($_SESSION['ecommerce']['order_id']) == true)) {
        $query = 
            "SELECT
                code
            FROM currencies
            WHERE id = '" . escape($_SESSION['ecommerce']['currency_id']) . "'";
        $result = mysqli_query(db::$con, $query) or output_error('Query failed.');
        $row = mysqli_fetch_assoc($result);
        $currency_code = $row['code'];
        
        // if there is a currency code, then continue
        if ($currency_code != '') {
            // if visitor tracking is on, update visitor record with currency code
            if (VISITOR_TRACKING == true) {
                $query = 
                    "UPDATE visitors
                    SET
                        currency_code = '" . escape($currency_code) . "',
                        stop_timestamp = UNIX_TIMESTAMP()
                    WHERE id = '" . escape($_SESSION['software']['visitor_id']) . "'";
                $result = mysqli_query(db::$con, $query) or output_error('Query failed.');
            }
            
            // if visitor has an order, update order with currency code
            if (isset($_SESSION['ecommerce']['order_id']) == true) {
                $query = "UPDATE orders SET currency_code = '" . escape($currency_code) . "' WHERE id = '" . ($_SESSION['ecommerce']['order_id'] ?? '') . "'";
                $result = mysqli_query(db::$con, $query) or output_error('Query failed.');
            }
        }
    }
    
    header('Location: ' . URL_SCHEME . $_SERVER['HTTP_HOST'] . $_REQUEST['send_to']);
    exit();
}
?>