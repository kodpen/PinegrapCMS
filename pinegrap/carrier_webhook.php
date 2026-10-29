<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * The address a carrier label service posts its parcel statuses to (a
 * webhook):
 *
 *   POST carrier_webhook.php?driver=<key>&key=<secret>   one message
 *
 * Called by the service's servers, with the secret key in the address as
 * the only credential; registered at the service when the driver is saved
 * switched on. What a message changes is in includes/fn/carrier_webhook.php.
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

require_once(PG_FUNCTIONS_DIR . '/includes/fn/carrier_webhook.php');

pg_carrier_webhook_receive();
