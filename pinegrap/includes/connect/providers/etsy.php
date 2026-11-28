<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Etsy as a provider of connected accounts (includes/fn/connect.php): the
 * seller's shop the Etsy connector works for
 * (includes/api/outbound/connectors/etsy.php).
 *
 * The application is the site's own app on Etsy's developer site: its
 * keystring is the client ID and its shared secret the client secret, which
 * is sent only inside the x-api-key header every request carries. The
 * token request authenticates with PKCE alone; an access token lives an
 * hour, its refresh token 90 days. connect.php is entered as the app's
 * callback URL.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_FUNCTIONS_DIR')) {
    exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/fn/etsy.php');

return pg_etsy_provider();
