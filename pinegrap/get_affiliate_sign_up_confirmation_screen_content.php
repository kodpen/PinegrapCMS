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

function get_affiliate_sign_up_confirmation_screen_content()
{
    $output =
        '<table>
            <tr>
                <td>' . lang('First Name') . ':</td>
                <td>' . h(($_SESSION['software']['affiliate_sign_up_confirmation']['first_name'] ?? '')) . '</td>
            </tr>
            <tr>
                <td>' . lang('Last Name') . ':</td>
                <td>' . h(($_SESSION['software']['affiliate_sign_up_confirmation']['last_name'] ?? '')) . '</td>
            </tr>
            <tr>
                <td>' . lang('Address 1') . ':</td>
                <td>' . h(($_SESSION['software']['affiliate_sign_up_confirmation']['address_1'] ?? '')). '</td>
            </tr>
            <tr>
                <td>' . lang('Address 2') . ':</td>
                <td>' . h(($_SESSION['software']['affiliate_sign_up_confirmation']['address_2'] ?? '')) . '</td>
            </tr>
            <tr>
                <td>' . lang('City') . ':</td>
                <td>' . h(($_SESSION['software']['affiliate_sign_up_confirmation']['city'] ?? '')) . '</td>
            </tr>
            <tr>
                <td>' . lang('State / Province') . ':</td>
                <td>' . h(($_SESSION['software']['affiliate_sign_up_confirmation']['state'] ?? '')) . '</td>
            </tr>
            <tr>
                <td>' . lang('Zip / Postal Code') . ':</td>
                <td>' . h(($_SESSION['software']['affiliate_sign_up_confirmation']['zip_code'] ?? '')) . '</td>
            </tr>
            <tr>
                <td>' . lang('Country') . ':</td>
                <td>' . h(($_SESSION['software']['affiliate_sign_up_confirmation']['country'] ?? '')) . '</td>
            </tr>
            <tr>
                <td>' . lang('Phone') . ':</td>
                <td>' . h(($_SESSION['software']['affiliate_sign_up_confirmation']['phone_number'] ?? '')) . '</td>
            </tr>
            <tr>
                <td>' . lang('Fax') . ':</td>
                <td>' . h(($_SESSION['software']['affiliate_sign_up_confirmation']['fax_number'] ?? '')) . '</td>
            </tr>
            <tr>
                <td>' . lang('Email') . ':</td>
                <td>' . h(($_SESSION['software']['affiliate_sign_up_confirmation']['email_address'] ?? '')) .  '</td>
            </tr>
            <tr>
                <td>' . lang('Affiliate Code') . ':</td>
                <td>' . h(($_SESSION['software']['affiliate_sign_up_confirmation']['affiliate_code'] ?? '')) . '</td>
            </tr>
            <tr>
                <td>' . lang('Affiliate / Company Name') . ':</td>
                <td>' . h(($_SESSION['software']['affiliate_sign_up_confirmation']['affiliate_name'] ?? '')) . '</td>
            </tr>
            <tr>
                <td>' . lang('Affiliate Website') . ':</td>
                <td>' . h(($_SESSION['software']['affiliate_sign_up_confirmation']['affiliate_website'] ?? '')) . '</td>
            </tr>
        </table>';

    return $output;
}
?>