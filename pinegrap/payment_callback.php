<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * The address a payment service posts its own messages to (a webhook, a
 * server-to-server callback):
 *
 *   POST payment_callback.php?driver=<key>   one message, as the service sends it
 *
 * Called by the service's servers, with the message's signature as the only
 * credential; the driver's notify() checks it. What the store does with a
 * message is in includes/payments/callback.php.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

// No session: the signature is the credential, and a panel cookie in the
// same browser must never decide what the address does. $_SESSION stays an
// array so the shared code that looks into it finds nothing rather than
// warning.
define('PG_NO_SESSION', true);

$_SESSION = array();

// Whatever the shared code prints on the way through would sit in front of
// the answer; it is dropped before the answer is sent.
ob_start();

include('init.php');

require_once(PG_FUNCTIONS_DIR . '/includes/payments/callback.php');

pg_payment_callback_receive();
