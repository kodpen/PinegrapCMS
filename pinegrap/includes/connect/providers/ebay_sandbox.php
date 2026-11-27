<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * eBay's sandbox as a provider of connected accounts: the same definition
 * as ebay.php against auth.sandbox.ebay.com and api.sandbox.ebay.com, with
 * the sandbox keyset and RuName of the site's application. An eBay
 * marketplace account set to the test environment works through it.
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

return pg_ebay_provider('sandbox');
