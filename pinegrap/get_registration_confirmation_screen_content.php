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

function get_registration_confirmation_screen_content()
{
    $email_address = '';
    $username = '';
    
    // if the user is logged in, then get user's e-mail address and username
    if (isset($_SESSION['sessionusername']) == true) {
        // get e-mail address
        $query = "SELECT user_email FROM user WHERE user_username = '" . escape($_SESSION['sessionusername']) . "'";
        $result = mysqli_query(db::$con, $query) or output_error('Query failed.');
        $row = mysqli_fetch_assoc($result);
        $email_address = $row['user_email'];
        
        // set username
        $username = $_SESSION['sessionusername'];
    }
    
    return
        'Email: ' . h($email_address) . '<br />
        Username: ' . h($username) . '<br />
        <br />
        <a href="' . URL_SCHEME . HOSTNAME . h($_REQUEST['send_to']) . '" class="software_button_primary">Continue</a><br />';
}
?>