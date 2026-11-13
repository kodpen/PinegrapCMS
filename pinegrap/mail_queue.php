<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * The mail queue, now a door: the e-mail and SMS queues are listed in the
 * Sends tab of Communication (messages_sends.php) with their channel. The
 * address stays for bookmarks, Site Settings › Jobs, the bell's notices and
 * the ops alerts.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

include('init.php');
require_once(PG_FUNCTIONS_DIR . '/includes/fn/messages_sends.php');

// Only the tab is carried across (?tab=sms is the SMS channel); the new
// screen's gate answers, so this door asks no right of its own.
validate_user();
go(PATH . SOFTWARE_DIRECTORY . '/' . pg_messages_sends_queue_door($_GET));
