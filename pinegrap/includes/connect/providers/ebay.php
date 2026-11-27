<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * eBay as a provider of connected accounts (includes/fn/connect.php): the
 * seller's account the eBay connector works for
 * (includes/api/outbound/connectors/ebay.php).
 *
 * The application is the site's own keyset in eBay's developer program:
 * the App ID is the client ID and the Cert ID the secret. eBay keeps the
 * redirect address itself and names it with a RuName, which the
 * authorization and token requests carry as their redirect_uri
 * ('redirect_option'); connect.php is entered as the RuName's auth
 * accepted URL. The token request authenticates with HTTP Basic; a user
 * token lives two hours, its refresh token about eighteen months. One
 * connection serves every eBay site the seller sells on.
 *
 * The sandbox is a provider of its own (ebay_sandbox.php), so its keyset,
 * RuName and connection never mix with production's.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_FUNCTIONS_DIR')) {
    exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/fn/ebay.php');

return pg_ebay_provider('production');
