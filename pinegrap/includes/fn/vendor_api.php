<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * The Kodpen API client (api.kodpen.com/v1): the switch that turns it on,
 * the address every call starts from, the headers and timeouts every call
 * carries, the reader of an application/problem+json answer and the
 * canonical site name the licence server binds a licence's site rights to.
 * The licence check (license_check(), includes/fn/output.php) and the
 * software update (includes/fn/update.php) build their calls on these.
 *
 * While PG_VENDOR_API_V1 is false nothing here makes a call:
 * pg_vendor_api_request() refuses before cURL runs, and the licence check
 * and the update ask the www.kodpen.com/api2 server as before.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_FUNCTIONS_DIR')) {
    exit;
}

// The switch. Its line keeps exactly this form, alone on the line: the
// hosting platform reads this file as text to tell which server a site asks,
// and tests/vendor_api_test.php holds the line to it.
define('PG_VENDOR_API_V1', false);

/**
 * The address every call starts from: https://api.kodpen.com/v1, or
 * PG_VENDOR_API_URL when data/config.php sets one for a test server.
 *
 * @return string '' when PG_VENDOR_API_URL is anything but an https address
 *                ending in /v1: then nothing is called, the production
 *                server neither
 */
function pg_vendor_api_base()
{
    return pg_vendor_api_base_from(defined('PG_VENDOR_API_URL') ? PG_VENDOR_API_URL : null);
}

/**
 * pg_vendor_api_base() for a PG_VENDOR_API_URL value (null: not set). Taken
 * only as https, a host, an optional port and path, ending in /v1: no user,
 * query, fragment, blank or control byte.
 *
 * @param mixed $override
 * @return string
 */
function pg_vendor_api_base_from($override)
{
    if ($override === null) {
        return 'https://api.kodpen.com/v1';
    }

    if (is_string($override) && (preg_match('#\Ahttps://[A-Za-z0-9._-]+(?::[0-9]{1,5})?(?:/[A-Za-z0-9._~-]+)*/v1\z#', $override) === 1)) {
        return $override;
    }

    return '';
}

/**
 * The address of a call: the base followed by $path ('/licenses/verify').
 *
 * @param string $path
 * @return string '' when there is no base
 */
function pg_vendor_api_url($path)
{
    $base = pg_vendor_api_base();

    return ($base === '') ? '' : $base . $path;
}
