<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * The address eBay sends marketplace account deletion notices to, entered in
 * eBay's developer program before it gives an application production keys:
 *
 *   GET  ebay_account_deletion.php?challenge_code=…   eBay proves the address
 *   POST ebay_account_deletion.php                    one notice, JSON, signed
 *
 * Called by eBay's own servers; the signature is the only credential. What is
 * checked and what a notice becomes is in includes/fn/ebay.php
 * (pg_ebay_deletion_receive()).
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

require_once(PG_FUNCTIONS_DIR . '/includes/fn/ebay.php');

pg_ebay_deletion_receive();
