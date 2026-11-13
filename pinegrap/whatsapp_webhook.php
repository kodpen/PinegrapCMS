<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * The address Meta calls for the site's WhatsApp Business account (the
 * Callback URL of the Communication › WhatsApp card):
 *
 *   GET  whatsapp_webhook.php?hub.mode=subscribe&hub.verify_token=…&hub.challenge=…
 *                              Meta's verify call, answered with the
 *                              challenge when the verify token is the card's
 *   POST whatsapp_webhook.php  an event, signed with the app secret
 *                              (X-Hub-Signature-256)
 *
 * Called by Meta's servers: the verify token and the signature are the
 * credentials. What an answer depends on is in includes/fn/whatsapp.php.
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

require_once(PG_FUNCTIONS_DIR . '/includes/fn/whatsapp.php');

pg_whatsapp_webhook_receive();
