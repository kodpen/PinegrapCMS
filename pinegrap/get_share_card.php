<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * A share card (includes/fn/share_card.php) at its own address:
 * get_share_card.php?k=<kind>&i=<id>&s=<signature>. Only the signature of
 * the item's title as a page printed it, against the site as it is now,
 * and only for an item every visitor may open; anything else is 404. A card
 * not drawn yet is drawn first; while another request draws it, or every
 * encode slot is taken, the answer is 503 with a short wait.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

// No session: a link preview asks without credentials, and the card is the
// same for every visitor.
define('PG_NO_SESSION', true);

$_SESSION = array();

include(dirname(__FILE__) . '/init.php');

require_once(PG_FUNCTIONS_DIR . '/includes/fn/share_card.php');

// A drawing that has started finishes for the next request even when this
// one leaves.
ignore_user_abort(true);

if (function_exists('set_time_limit')) {
    @set_time_limit(60);
}

$share_card = pg_share_card_answer(pg_share_card_request($_GET));

header('X-Robots-Tag: noindex');
header('X-Content-Type-Options: nosniff');

if ($share_card['status'] !== 200) {
    header('Cache-Control: no-store');

    if ($share_card['status'] === 503) {
        header('HTTP/1.1 503 Service Unavailable');
        header('Retry-After: 5');
    } else {
        header('HTTP/1.1 404 Not Found');
    }

    exit();
}

// The address changes with anything the card shows, so the file never
// changes under it.
header('Content-Type: image/png');
header('Cache-Control: public, max-age=31536000, immutable');
header('Content-Length: ' . (int) @filesize($share_card['path']));
readfile($share_card['path']);
exit();
