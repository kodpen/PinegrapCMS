<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * The address a GitHub repository linked to a Workspace channel posts its
 * events to (the webhook the site added when the link was saved):
 *
 *   POST github_webhook.php?link=<id>   one delivery
 *
 * Called by GitHub's servers; the X-Hub-Signature-256 header, signed with
 * the link's own secret, is the only credential. What a delivery writes is
 * in includes/workspace/github_links.php.
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

require_once(PG_FUNCTIONS_DIR . '/includes/workspace/bootstrap.php');

ws_github_webhook_receive();
