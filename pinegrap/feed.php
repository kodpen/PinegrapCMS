<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * The address a shopping platform reads a product feed from:
 *
 *   GET feed.php?f=...   the OpenAI feed (JSONL or CSV) or the Google
 *                        Merchant Center file (TSV) the token names
 *
 * Read by the platform's own servers, with the token as the only
 * credential. The file is written once a day by product_feed_job.php; what it
 * carries and why is in includes/fn/product_feeds.php.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

// No session: the token is the credential, and a panel cookie in the same
// browser must never decide what the address shows. $_SESSION stays an array
// so the shared code that looks into it finds nothing rather than warning.
define('PG_NO_SESSION', true);

$_SESSION = array();

// Whatever the shared code prints on the way through would sit in front of
// the feed; it is dropped before the answer is sent.
ob_start();

include('init.php');

pg_product_feed_serve((isset($_GET['f']) && is_string($_GET['f'])) ? $_GET['f'] : '');
