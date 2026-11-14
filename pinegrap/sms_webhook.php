<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * The address an SMS provider posts customers' messages to (the address
 * shown under Customers' messages in the SMS settings):
 *
 *   POST sms_webhook.php?provider=twilio   an incoming SMS, signed with the
 *                                          account's auth token
 *                                          (X-Twilio-Signature)
 *
 * Called by the provider's servers: the signature is the credential. What
 * an answer depends on is in includes/fn/sms_inbox.php.
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

// The WhatsApp layer answers and counts offences for both webhooks.
require_once(PG_FUNCTIONS_DIR . '/includes/fn/whatsapp.php');
require_once(PG_FUNCTIONS_DIR . '/includes/fn/sms_inbox.php');

pg_sms_webhook_receive();
