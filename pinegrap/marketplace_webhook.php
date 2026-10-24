<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * The address a marketplace posts order packages to once a webhook has been
 * registered for one of the shop's marketplace accounts:
 *
 *   POST marketplace_webhook.php?account=<id>   one delivery, JSON
 *
 * Called by the marketplace's own servers, with the key it was given as the
 * only credential. What is checked and imported, and why it never calls the
 * marketplace back, is in includes/api/outbound/webhook_receiver.php.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

// No session: the key is the credential, and a panel cookie in the same
// browser must never decide what the address does. $_SESSION stays an array
// so the shared code that looks into it finds nothing rather than warning.
define('PG_NO_SESSION', true);

$_SESSION = array();

// Whatever the shared code prints on the way through would sit in front of
// the answer; it is dropped before the answer is sent.
ob_start();

include('init.php');

require_once(PG_FUNCTIONS_DIR . '/includes/api/outbound/webhook_receiver.php');

mp_webhook_receive();
