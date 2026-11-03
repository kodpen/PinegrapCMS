<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * The address a mail sending service posts its bounce, complaint and
 * delivery reports to (a webhook):
 *
 *   POST mail_webhook.php?t=<transport>&key=<secret>   one report
 *
 * Called by the service's servers, with the secret key in the address as
 * the credential (and the service's signature where it signs). What a
 * report changes is in includes/fn/mail_webhook.php.
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

pg_mail_webhook_receive();
