<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * My SMS Campaigns, now a door: the SMS campaigns are listed in the
 * Campaigns tab of Communication (view_email_campaigns.php?channel=sms).
 * The address stays for bookmarks and the links of older notices.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

include('init.php');

// My SMS Campaigns is the Campaigns tab of Communication with the SMS channel.
// The new screen's gate answers; this door asks no right of its own.
validate_user();
go(PATH . SOFTWARE_DIRECTORY . '/view_email_campaigns.php?channel=sms');
