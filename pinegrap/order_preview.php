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

// if this is not the request that PayPal Express Checkout returns,
// then validate token field.  PayPal Express Checkout uses "token" for a query string parameter,
// like we do, so that is why we can't validate the token.  This should not expose any major problem.
if (($_GET['mode'] ?? '') != 'paypal_express_checkout_return' and ($_GET['mode'] ?? '') != 'iyzipay_threedsecure_return') {
    //fix samesite error while 3dsecure
    // if php version above 7.3+
    if (version_compare(PHP_VERSION, '7.3.0', '>=')) {
        if (URL_SCHEME == 'https://') {
            setcookie('' . session_name() . '', '' . session_id() . '', ['samesite' => 'None', 'secure' => true]);
        }else{
            setcookie('' . session_name() . '', '' . session_id() . '', ['samesite' => 'None']);
        }
    }else{
        if (URL_SCHEME == 'https://') {
            header('Set-Cookie: cross-site-cookie=bar; SameSite=None; Secure');
        }else{
            header('Set-Cookie: same-site-cookie=foo; SameSite=Lax');
        }
    }

    // Pay With Iyzico callback is a cross-site POST from iyzico and does not include the CSRF token field
    if (($_GET['mode'] ?? '') != 'pay_with_iyzico_return') {
        validate_token_field();
    }
}


initialize_order();

// if the apply gift card button was clicked and gift cards are enabled, then check to see if a gift card code was entered
if ((isset($_POST['submit_apply_gift_card']) == TRUE) && (ECOMMERCE_GIFT_CARD == TRUE)) {
    include_once('liveform.class.php');
    $liveform = new liveform('order_preview');
    
    $liveform->assign_field_value('gift_card_code', $_POST['gift_card_code']);
    
    // if the gift card code field was not blank, then continue to check if the code is valid
    if ($liveform->get_field_value('gift_card_code') != '') {
        // If there are only gift card products in the order,
        // then do not allow a gift card to be applied to order.
        if (
            db_value(
                "SELECT COUNT(*)
                FROM order_items
                LEFT JOIN products ON order_items.product_id = products.id
                WHERE
                    (order_items.order_id = '" . ($_SESSION['ecommerce']['order_id'] ?? '') . "')
                    AND products.gift_card = '0'") == 0
        ) {
            $liveform->assign_field_value('gift_card_code', '');
            $liveform->mark_error('gift_card_code', lang('Sorry, we do not allow a gift card to be applied to an order that only contains gift cards. Please add a different type of item to your order in order to apply the gift card.'));
            go(PATH . encode_url_path(get_page_name($_POST['page_id'])));
        }

        // Remove non-alphanumeric characters from gift card code,
        // in order to get rid of dashes, spaces, and etc.
        $gift_card_code = preg_replace('/[^A-Za-z0-9]/', '', $liveform->get_field_value('gift_card_code'));

        // check if the gift card has already been added to the order
        $query = "SELECT COUNT(*) FROM applied_gift_cards WHERE (order_id = '" . ($_SESSION['ecommerce']['order_id'] ?? '') . "') AND (code = '" . escape($gift_card_code) . "')";
        $result = mysqli_query(db::$con, $query) or output_error('Query failed.');
        $row = mysqli_fetch_row($result);
        
        // if the gift card has already been added to the order, then add error
        if ($row[0] > 0) {
            $liveform->mark_error('gift_card_code', lang('Sorry, the gift card has already been applied. Please enter a different code.'));
            
        // else the gift card has not already been added to the order, so determine if the gift card code is valid
        } else {
            // Check for a gift card in this system.
            $gift_card = db_item(
                "SELECT
                    id,
                    balance,
                    expiration_date
                FROM gift_cards
                WHERE code = '" . escape($gift_card_code)  . "'");

            // If a gift card could not be found in this system,
            // and givex is disabled, then output error.
            if (($gift_card['id'] == '') && (ECOMMERCE_GIVEX == false)) {
                $liveform->mark_error('gift_card_code', lang('Sorry, we could not find a gift card for that code. Please check that you entered the code correctly.'));
                go(PATH . encode_url_path(get_page_name($_POST['page_id'])));
            }

            // If a gift card was found in this system, but it has expired, then output error.
            if (
                ($gift_card['id'] != '')
                && ($gift_card['expiration_date'] != '0000-00-00')
                && ($gift_card['expiration_date'] < date('Y-m-d'))
            ) {
                $liveform->mark_error('gift_card_code', lang('Sorry, we cannot accept the gift card because it has expired. You may enter a different gift card code.'));
                $liveform->assign_field_value('gift_card_code', '');
                go(PATH . encode_url_path(get_page_name($_POST['page_id'])));
            }

            // If a gift card was found in this system, but the balance is 0, then output error.
            if (($gift_card['id'] != '') && ($gift_card['balance'] == 0)) {
                $liveform->mark_error('gift_card_code', lang('Sorry, we cannot accept the gift card because there is no remaining balance. You may enter a different gift card code.'));
                $liveform->assign_field_value('gift_card_code', '');
                go(PATH . encode_url_path(get_page_name($_POST['page_id'])));
            }

            // If a gift card was found in this system, then prepare values.
            if ($gift_card['id'] != '') {
                $gift_card_id = $gift_card['id'];
                $old_balance = $gift_card['balance'];
                $givex = 0;

            // Otherwise a gift card was not found in this system,
            // so if givex is enabled, then check if card exists at givex.
            } else if (ECOMMERCE_GIVEX == true) {
                // if cURL does not exist, then add error and send user back to previous screen
                if (function_exists('curl_init') == FALSE) {
                    $liveform->mark_error('gift_card_code', lang('Sorry, we cannot communicate with the gift card service, because cURL is not installed, so we cannot accept the gift card. The administrator of this website should install cURL. If you would like to proceed without using the gift card, then you may remove the code and continue.'));
                    
                    // send visitor back to the previous screen
                    header('Location: ' . URL_SCHEME . HOSTNAME . PATH . encode_url_path(get_page_name($_POST['page_id'])));
                    exit();
                }
                
                // send a balance request to Givex in order to check if the gift card code is valid
                $result = send_givex_request('balance', $gift_card_code);
                
                // if there was an error or the balance is 0 then prepare error message and send user back to previous screen
                if ((isset($result['curl_errno']) == TRUE) || (isset($result['error_message']) == TRUE) || ($result['balance'] == 0)) {
                    // if there was a curl error, then prepare error message
                    if (isset($result['curl_errno']) == TRUE) {
                        $error_message = lang(array('string' => 'Sorry, we cannot communicate with the gift card service at the moment, so we cannot accept the gift card. Please try again later. If the problem continues, then please contact the administrator of this website. If you would like to proceed without using the gift card, then you may remove the code and continue. cURL Error Number: {var:1}. cURL Error Message: {var:2}.', 'vars' => array(h($result['curl_errno']), h($result['curl_error']))));
                        
                    // else if there was a Givex error, then prepare error message
                    } else if (isset($result['error_message']) == TRUE) {
                        $error_message = lang(array('string' => 'Sorry, we cannot accept the gift card because there was an error from the gift card service: {var:1}. Please verify that you have entered the gift card code correctly.', 'vars' => h($result['error_message'])));
                        
                    // else if the balance is 0, then prepare error message
                    } else if ($result['balance'] == 0) {
                        $error_message = lang('Sorry, we cannot accept the gift card because there is no remaining balance. You may enter a different gift card code.');
                        $liveform->assign_field_value('gift_card_code', '');
                    }
                    
                    // add error
                    $liveform->mark_error('gift_card_code', $error_message);
                    
                    // send visitor back to the previous screen
                    header('Location: ' . URL_SCHEME . HOSTNAME . PATH . encode_url_path(get_page_name($_POST['page_id'])));
                    exit();
                }

                $gift_card_id = '';
                $old_balance = $result['balance'] * 100;
                $givex = 1;
            }
            
            // add record to database for applied gift card
            $query =
                "INSERT INTO applied_gift_cards (
                    gift_card_id,
                    order_id,
                    code,
                    old_balance,
                    givex)
                VALUES (
                    '" . $gift_card_id . "',
                    '" . ($_SESSION['ecommerce']['order_id'] ?? '') . "',
                    '" . escape($gift_card_code) . "',
                    '" . escape($old_balance) . "',
                    '" . $givex . "')";
            $result = mysqli_query(db::$con, $query) or output_error('Query failed.');
            
            // clear the gift card code form field
            $liveform->assign_field_value('gift_card_code', '');
        }
    }
    
    // send visitor back to the previous screen
    header('Location: ' . URL_SCHEME . HOSTNAME . PATH . encode_url_path(get_page_name($_POST['page_id'])));
    exit();
    
// else the mode is paypal_express_checkout_return or the purchase now button was clicked, so submit the order
} else {
    require(dirname(__FILE__) . '/submit_order.php');
    submit_order('order preview');
}
?>
