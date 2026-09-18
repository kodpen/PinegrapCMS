<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

// A module of functions.php: Parasut accounting integration.
//
// Loaded by functions.php through require_once, never on its own. The
// functions were moved here from functions.php as they were; PG_FUNCTIONS_DIR
// is the software directory (what dirname(__FILE__) was in functions.php),
// see the note at the top of functions.php.
if (!defined('PG_FUNCTIONS_DIR')) {
    exit;
}

// NOTE (2026.1.29): the legacy cancel_order() helper was removed. It wrote the
// misspelled enum value 'canceled', which the 2026.1.26 schema change dropped
// from orders.status — every call silently stored an empty status. All
// cancellation now goes through process_order_cancellation() (see below),
// which owns the gateway void, the gift-card expiry and the cancellation
// bookkeeping columns in one place.

// ---------------------------------------------------------------------------
// Parasut API V4 helpers
// ---------------------------------------------------------------------------

/**
 * Make a cURL request to the Parasut API.
 *
 * @param string $method  GET | POST | PUT | PATCH | DELETE
 * @param string $url     Full URL
 * @param array  $body    Associative array (will be JSON-encoded) or null
 * @param string $token   Bearer token (omit for OAuth token endpoint)
 * @return array ['http_code' => int, 'body' => mixed]
 */
function _parasut_request($method, $url, $body = null, $token = null)
{
    $ch = curl_init($url);
    // Identify this installation on outgoing requests. Sent with no
    // User-Agent, a request looks like an anonymous client to the receiving
    // server's firewall and gets rejected — which is how Pinegrap ended up
    // blocking its own licence and update checks.
    curl_setopt($ch, CURLOPT_USERAGENT, function_exists('pinegrap_user_agent') ? pinegrap_user_agent() : 'Pinegrap');
    $headers = ['Accept: application/json'];

    if ($token) {
        $headers[] = 'Authorization: Bearer ' . $token;
    }

    if ($body !== null) {
        $json = json_encode($body);
        $headers[] = 'Content-Type: application/json';
        $headers[] = 'Content-Length: ' . strlen($json);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $json);
    }

    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST => $method,
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_SSL_VERIFYPEER => true,
    ]);

    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curl_error = curl_error($ch);
    curl_close($ch);

    if ($curl_error) {
        return ['http_code' => 0, 'body' => null, 'curl_error' => $curl_error];
    }

    return ['http_code' => $http_code, 'body' => json_decode($response, true)];
}

/**
 * Return the Parasut API base URL (production or sandbox).
 */
function _parasut_base()
{
    // Production only. The setting that used to switch this pointed at
    // api.heroku-staging.parasut.com, Parasut's own staging host rather than a
    // sandbox offered to customers: it does not answer to a merchant's
    // credentials, and because the OAuth call goes through here too, turning the
    // switch on stopped the integration working at all.
    return PARASUT_API_BASE;
}

/**
 * Return the company-scoped API prefix.
 */
function _parasut_prefix()
{
    return _parasut_base() . '/v4/' . PARASUT_COMPANY_ID;
}

/**
 * Extract a readable error message from a Parasut API response.
 */
function _parasut_extract_error($result)
{
    if (isset($result['curl_error'])) {
        return $result['curl_error'];
    }
    $body = $result['body'] ?? [];
    if (!empty($body['errors'])) {
        $msgs = [];
        foreach ((array) $body['errors'] as $err) {
            $msgs[] = ($err['title'] ?? '') . ': ' . ($err['detail'] ?? '');
        }
        return implode(' | ', $msgs);
    }
    if (!empty($body['error_description'])) {
        return $body['error_description'];
    }
    return 'HTTP ' . ($result['http_code'] ?? '?');
}

/**
 * Read the buyer's tax or identity number for an order.
 *
 * Which field holds it is an operator setting (PARASUT_TC_IN_FIELD). Two of the
 * three choices are columns on orders; the third, tax_number, is not - it lives
 * on the linked contact, which is where the settings screen sends the operator
 * and what view_order.php checks before offering the button. Reading it off the
 * order row returned nothing, so contacts created from those orders carried no
 * identity number at all and could never be billed by e-invoice.
 *
 * @param array $order  Order row
 * @return string  Empty when not configured or not filled in
 */
function _parasut_order_identity($order)
{
    if (!defined('PARASUT_TC_IN_FIELD') || PARASUT_TC_IN_FIELD === 'do not use') {
        return '';
    }

    $field = PARASUT_TC_IN_FIELD;

    if ($field === 'tax_number') {
        $contact_id = (int) ($order['contact_id'] ?? 0);
        if ($contact_id <= 0) {
            return '';
        }
        return trim((string) db_value("SELECT tax_number FROM contacts WHERE id = '" . escape($contact_id) . "' LIMIT 1"));
    }

    return trim((string) ($order[$field] ?? ''));
}

/**
 * Decide whether an order is billed by e-invoice or by e-archive.
 *
 * The rule is the tax authority's, not ours: a buyer registered for e-invoice
 * must be sent one, and everyone else gets an e-archive. Sending the wrong kind
 * produces a document that is not valid. Parasut answers the question through
 * e_invoice_inboxes, and the answer comes with the buyer's GIB address, which
 * the e-invoice has to carry as `to`.
 *
 * Asked at the moment of issue and not cached: the registered-user list has no
 * published update interval, and a stale "not registered" is the answer that
 * produces an invalid document.
 *
 * A buyer with no identity number cannot be registered, so that case skips the
 * lookup. A lookup that fails is not treated as "not registered" - the caller
 * is told, and no document is raised on a guess.
 *
 * @param string $identity  VKN or TCKN
 * @param string $token     Parasut access token
 * @return array ['success' => bool, 'kind' => 'einvoice'|'earchive', 'alias' => string, 'error' => string]
 */
function _parasut_resolve_edoc_kind($identity, $token)
{
    if ($identity === '') {
        return ['success' => true, 'kind' => 'earchive', 'alias' => '', 'error' => ''];
    }

    $url = _parasut_prefix() . '/e_invoice_inboxes?filter[vkn]=' . urlencode($identity);
    $result = _parasut_request('GET', $url, null, $token);

    if ($result['http_code'] !== 200) {
        return [
            'success' => false,
            'kind' => '',
            'alias' => '',
            'error' => lang(array('string' => 'Could not check whether the buyer is registered for e-invoice: {var:1}', 'vars' => array(_parasut_extract_error($result)))),
        ];
    }

    $items = $result['body']['data'] ?? [];
    if (empty($items)) {
        return ['success' => true, 'kind' => 'earchive', 'alias' => '', 'error' => ''];
    }

    // A buyer can publish more than one box. Nothing here can tell them apart,
    // so the first is used; picking per contact is a job for the ERP screens.
    $alias = '';
    foreach ($items as $item) {
        $candidate = trim((string) ($item['attributes']['e_invoice_address'] ?? ''));
        if ($candidate !== '') {
            $alias = $candidate;
            break;
        }
    }

    if ($alias === '') {
        return [
            'success' => false,
            'kind' => '',
            'alias' => '',
            'error' => lang('The buyer is registered for e-invoice but Parasut returned no address to send it to.'),
        ];
    }

    return ['success' => true, 'kind' => 'einvoice', 'alias' => $alias, 'error' => ''];
}

/**
 * Wait, briefly, for an e-document conversion to finish.
 *
 * Converting a sales invoice into an e-invoice or e-archive is asynchronous:
 * Parasut answers with a trackable job, and an accepted POST says only that the
 * work was queued. The code used to read that as "sent" and tell the operator
 * the document had been filed.
 *
 * The job id is only valid for fifteen minutes and there is no queue here yet to
 * come back to it, so this polls for a few seconds while the operator is still
 * on the request. Most conversions land inside that window; one that does not is
 * reported as still running rather than as done.
 *
 * @param string $job_id
 * @param string $token
 * @return array ['state' => 'done'|'pending'|'failed', 'error' => string]
 */
function _parasut_track_job($job_id, $token)
{
    if ($job_id === '') {
        return ['state' => 'pending', 'error' => ''];
    }

    // Growing waits, about six seconds in total. Long enough for the common case,
    // short enough that the page does not appear to hang.
    $waits = [1, 2, 3];

    foreach ($waits as $wait) {
        sleep($wait);

        $result = _parasut_request('GET', _parasut_prefix() . '/trackable_jobs/' . urlencode($job_id), null, $token);

        if ($result['http_code'] !== 200) {
            continue;
        }

        $attributes = $result['body']['data']['attributes'] ?? [];
        $status = strtolower((string) ($attributes['status'] ?? ''));

        if (($status === 'done') || ($status === 'completed') || ($status === 'success')) {
            return ['state' => 'done', 'error' => ''];
        }

        if (($status === 'error') || ($status === 'failed') || ($status === 'aborted')) {
            $errors = $attributes['errors'] ?? [];
            $messages = [];
            foreach ((array) $errors as $error) {
                $messages[] = is_array($error) ? implode(' ', array_map('strval', $error)) : (string) $error;
            }
            return ['state' => 'failed', 'error' => $messages ? implode(' | ', $messages) : lang('The conversion was rejected.')];
        }
    }

    return ['state' => 'pending', 'error' => ''];
}

/**
 * Decode the stored Parasut credentials.
 *
 * Held as one encrypted JSON blob in config.parasut_credentials_enc, in the
 * "<ciphertext>:<iv>" shape encrypt_string_with_iv() answers. Decoding fails
 * softly to empty strings: a site restored with a different ENCRYPTION_KEY
 * cannot recover these values, and the caller should report "not connected"
 * rather than stop on a fatal error.
 *
 * @return array ['client_secret' => string, 'password' => string]
 */
function _parasut_credentials()
{
    static $credentials = null;

    if ($credentials !== null) {
        return $credentials;
    }

    $credentials = ['client_secret' => '', 'password' => ''];

    $stored = defined('PARASUT_CREDENTIALS_ENC') ? (string) PARASUT_CREDENTIALS_ENC : '';
    if ($stored === '' || strpos($stored, ':') === false) {
        return $credentials;
    }

    list($cipher, $iv) = explode(':', $stored, 2);
    $json = decode_ssl_keys($cipher, $iv);
    $values = ($json === '') ? null : json_decode($json, true);

    if (is_array($values)) {
        $credentials['client_secret'] = (string) ($values['client_secret'] ?? '');
        $credentials['password'] = (string) ($values['password'] ?? '');
    }

    return $credentials;
}

/**
 * Get a valid OAuth2 access token, using a file cache to avoid repeated logins.
 *
 * @return array ['success' => bool, 'token' => string|null, 'error' => string]
 */
/**
 * Read the cached token, if it is still good for a while.
 *
 * @return array  Cache contents, or an empty array
 */
function _parasut_token_cache_read()
{
    if (!file_exists(PARASUT_TOKEN_CACHE)) {
        return [];
    }

    $raw = file_get_contents(PARASUT_TOKEN_CACHE);
    if ($raw === false) {
        return [];
    }

    $cache = json_decode($raw, true);

    return is_array($cache) ? $cache : [];
}

/**
 * Write the token cache in one step.
 *
 * Written to a temporary file and renamed into place: rename is atomic on the
 * same filesystem, so a second request reading the cache sees either the old
 * file or the new one, never a half-written one. The previous code wrote in
 * place, and a truncated file read back as no token at all, which sent every
 * request off to log in again.
 *
 * @param array $cache
 * @return void
 */
function _parasut_token_cache_write($cache)
{
    $temporary = PARASUT_TOKEN_CACHE . '.' . getmypid() . '.tmp';

    if (file_put_contents($temporary, json_encode($cache), LOCK_EX) === false) {
        return;
    }

    if (!@rename($temporary, PARASUT_TOKEN_CACHE)) {
        @unlink($temporary);
    }
}

/**
 * Exchange the stored refresh token for a new access token.
 *
 * The refresh token was already being saved and never used, so an expired token
 * meant sending the account password again on every renewal. Refreshing keeps
 * the password out of all but the first exchange, which is what makes it
 * possible to stop storing it later.
 *
 * @param string $refresh_token
 * @param array  $credentials
 * @return array ['success' => bool, 'data' => array|null]
 */
function _parasut_token_refresh($refresh_token, $credentials)
{
    if ($refresh_token === '') {
        return ['success' => false, 'data' => null];
    }

    $data = _parasut_token_exchange([
        'grant_type' => 'refresh_token',
        'refresh_token' => $refresh_token,
    ], $credentials);

    return ['success' => !empty($data['access_token']), 'data' => $data];
}

/**
 * POST to the OAuth token endpoint.
 *
 * Kept on plain cURL rather than api_http_request(): the token endpoint wants a
 * form-encoded body and HTTP Basic auth, neither of which that helper sends.
 *
 * @param array $body         Grant-specific fields
 * @param array $credentials  From _parasut_credentials()
 * @return array|null  Decoded response, or null on a transport error
 */
function _parasut_token_exchange($body, $credentials)
{
    $ch = curl_init(_parasut_base() . '/oauth/token');
    // Identify this installation on outgoing requests. Sent with no
    // User-Agent, a request looks like an anonymous client to the receiving
    // server's firewall and gets rejected — which is how Pinegrap ended up
    // blocking its own licence and update checks.
    curl_setopt($ch, CURLOPT_USERAGENT, function_exists('pinegrap_user_agent') ? pinegrap_user_agent() : 'Pinegrap');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => http_build_query($body),
        CURLOPT_USERPWD => PARASUT_CLIENT_ID . ':' . $credentials['client_secret'],
        CURLOPT_HTTPHEADER => ['Content-Type: application/x-www-form-urlencoded'],
        CURLOPT_TIMEOUT => 30,
        CURLOPT_SSL_VERIFYPEER => true,
    ]);

    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curl_error = curl_error($ch);
    curl_close($ch);

    if ($curl_error) {
        return ['error_description' => 'cURL error: ' . $curl_error];
    }

    $data = json_decode($response, true);

    if (!is_array($data)) {
        return ['error_description' => 'HTTP ' . $http_code];
    }

    if ($http_code !== 200) {
        $data['error_description'] = $data['error_description'] ?? ('HTTP ' . $http_code);
        unset($data['access_token']);
    }

    return $data;
}

/**
 * Get a valid OAuth2 access token, using a file cache to avoid repeated logins.
 *
 * Renewal is taken by one request at a time. Without that, every page that found
 * the token expired asked Parasut for a new one at the same moment, and each
 * answer invalidated the one before it, so the requests knocked each other out.
 * A request that cannot take the lock waits for whoever holds it and then reads
 * the cache that request wrote.
 *
 * @return array ['success' => bool, 'token' => string|null, 'error' => string]
 */
function parasut_get_token()
{
    // Keep a 60-second buffer: a token that expires mid-request is no use.
    $cache = _parasut_token_cache_read();
    if (!empty($cache['access_token']) && !empty($cache['expires_at']) && (time() < ($cache['expires_at'] - 60))) {
        return ['success' => true, 'token' => $cache['access_token'], 'error' => ''];
    }

    $credentials = _parasut_credentials();

    if (($credentials['client_secret'] === '') || ($credentials['password'] === '')) {
        return [
            'success' => false,
            'token' => null,
            'error' => lang('Parasut credentials are not set. Enter them in Settings, E-commerce, Invoicing.'),
        ];
    }

    $lock_name = 'pinegrap_parasut_token_' . substr(md5(PARASUT_COMPANY_ID . '|' . PARASUT_CLIENT_ID), 0, 20);
    $have_lock = (db_value("SELECT GET_LOCK('" . escape($lock_name) . "', 10)") == '1');

    if ($have_lock) {
        // Whoever we waited for may have renewed it already.
        $cache = _parasut_token_cache_read();
        if (!empty($cache['access_token']) && !empty($cache['expires_at']) && (time() < ($cache['expires_at'] - 60))) {
            db_value("SELECT RELEASE_LOCK('" . escape($lock_name) . "')");
            return ['success' => true, 'token' => $cache['access_token'], 'error' => ''];
        }
    }

    $refresh = _parasut_token_refresh((string) ($cache['refresh_token'] ?? ''), $credentials);
    $data = $refresh['success'] ? $refresh['data'] : _parasut_token_exchange([
        'grant_type' => 'password',
        'username' => PARASUT_USERNAME,
        'password' => $credentials['password'],
        'redirect_uri' => 'urn:ietf:wg:oauth:2.0:oob',
    ], $credentials);

    if (empty($data['access_token'])) {
        if ($have_lock) {
            db_value("SELECT RELEASE_LOCK('" . escape($lock_name) . "')");
        }
        $message = $data['error_description'] ?? 'no access token in the response';
        return ['success' => false, 'token' => null, 'error' => lang(array('string' => 'Parasut token error: {var:1}', 'vars' => array($message)))];
    }

    _parasut_token_cache_write([
        'access_token' => $data['access_token'],
        // Parasut does not always return a new refresh token; keep the old one
        // rather than losing the ability to renew without the password.
        'refresh_token' => $data['refresh_token'] ?? ($cache['refresh_token'] ?? ''),
        'expires_at' => time() + (int) ($data['expires_in'] ?? 7200),
    ]);

    if ($have_lock) {
        db_value("SELECT RELEASE_LOCK('" . escape($lock_name) . "')");
    }

    return ['success' => true, 'token' => $data['access_token'], 'error' => ''];
}

/**
 * Find or create a Parasut contact for the given order row.
 * Stores the Parasut contact ID back to orders.parasut_contact_id.
 *
/**
 * Ensure a Pinegrap product exists in Parasut and return its Parasut ID.
 * Creates the product in Parasut on first call and persists the ID to the DB.
 *
 * @param int    $product_id  Pinegrap products.id
 * @param string $token       Valid Parasut OAuth token
 * @return array ['success' => bool, 'parasut_product_id' => string|null, 'error' => string]
 */
/**
 * Return the company's first Parasut stock location ID.
 * Fetches from the API once and caches it in config.parasut_default_warehouse_id.
 *
 * @param string $token  Valid Parasut OAuth token
 * @return array ['success' => bool, 'warehouse_id' => string|null, 'error' => string]
 */
function parasut_ensure_default_warehouse($token)
{
    if (defined('PARASUT_DEFAULT_WAREHOUSE_ID') && PARASUT_DEFAULT_WAREHOUSE_ID !== '') {
        return ['success' => true, 'warehouse_id' => PARASUT_DEFAULT_WAREHOUSE_ID, 'error' => ''];
    }

    $result = _parasut_request('GET', _parasut_prefix() . '/warehouses?page[size]=1', null, $token);

    if ($result['http_code'] !== 200 || empty($result['body']['data'])) {
        return ['success' => false, 'warehouse_id' => null, 'error' => lang(array('string' => 'Could not fetch stock locations: {var:1}', 'vars' => array(_parasut_extract_error($result))))];
    }

    $warehouse_id = (string) $result['body']['data'][0]['id'];
    db("UPDATE config SET parasut_default_warehouse_id = '" . escape($warehouse_id) . "'");

    return ['success' => true, 'warehouse_id' => $warehouse_id, 'error' => ''];
}

function parasut_sync_product($product_id, $token)
{
    // Fetch product details (needed for both new creation and track_stock flag).
    $result = mysqli_query(db::$con, "SELECT name, short_description, price, inventory, inventory_quantity, tax_rate, parasut_product_id FROM products WHERE id = '" . escape($product_id) . "' LIMIT 1");
    $product = mysqli_fetch_assoc($result);
    if (!$product) {
        return ['success' => false, 'parasut_product_id' => null, 'track_stock' => false, 'error' => lang(array('string' => 'Product not found: {var:1}', 'vars' => array($product_id)))];
    }

    $track_stock = !empty($product['inventory']);

    // Already synced — return cached ID.
    if ($product['parasut_product_id']) {
        return ['success' => true, 'parasut_product_id' => $product['parasut_product_id'], 'track_stock' => $track_stock, 'error' => ''];
    }

    $product_name = $product['short_description'] ?: ($product['name'] ?: lang('No Product Name'));
    $unit_price = round($product['price'] / 100, 2);

    $attributes = [
        'name' => $product_name,
        'unit_price' => $unit_price,
        'currency' => 'TRL',
        // Default rate on the Parasut product card. Invoice lines carry their own
        // rate and override this, so it only matters for documents raised inside
        // Parasut. A null products.tax_rate means "the zone decides", which has no
        // equivalent on a product card - 0 is the safe default there.
        'vat_rate' => ($product['tax_rate'] === null || $product['tax_rate'] === '') ? 0 : (float) $product['tax_rate'],
        'product_type' => $track_stock ? 'physical' : 'service',
        'inventory_tracking' => $track_stock,
    ];
    if ($track_stock) {
        // Set initial stock count so the product has an inventory level from the start.
        $attributes['initial_stock_count'] = max(0, (int) $product['inventory_quantity']);
    }

    $body = [
        'data' => [
            'type' => 'products',
            'attributes' => $attributes,
        ],
    ];

    $api_result = _parasut_request('POST', _parasut_prefix() . '/products', $body, $token);

    if (!in_array($api_result['http_code'], [200, 201]) || empty($api_result['body']['data']['id'])) {
        return ['success' => false, 'parasut_product_id' => null, 'track_stock' => $track_stock, 'error' => _parasut_extract_error($api_result)];
    }

    $parasut_product_id = (string) $api_result['body']['data']['id'];
    db("UPDATE products SET parasut_product_id = '" . escape($parasut_product_id) . "' WHERE id = '" . escape($product_id) . "'");

    // For stock-tracked products, create an inventory level in the default warehouse
    // so the product has a stock entry and can appear in shipment documents.
    if ($track_stock) {
        $wh = parasut_ensure_default_warehouse($token);
        if ($wh['success']) {
            $initial_qty = max(0, (int) $product['inventory_quantity']);
            $inv_body = [
                'data' => [
                    'type' => 'stock_updates',
                    'attributes' => [
                        'quantity' => $initial_qty > 0 ? $initial_qty : 1,
                    ],
                    'relationships' => [
                        'product' => ['data' => ['type' => 'products', 'id' => $parasut_product_id]],
                        'warehouse' => ['data' => ['type' => 'warehouses', 'id' => $wh['warehouse_id']]],
                    ],
                ],
            ];
            $inv_result = _parasut_request('POST', _parasut_prefix() . '/stock_updates', $inv_body, $token);
            // Opening stock is best effort - the product is usable without it. Log
            // the failure without the response body: these payloads echo back the
            // record and the log is not a place for customer data.
            if (!in_array($inv_result['http_code'], [200, 201])) {
                error_log('Parasut: opening stock level failed for product ' . $parasut_product_id . ' (HTTP ' . $inv_result['http_code'] . ')');
            }
        }
    }

    return ['success' => true, 'parasut_product_id' => $parasut_product_id, 'track_stock' => $track_stock, 'error' => ''];
}

/**
 * Ensure an existing Parasut product has inventory tracking enabled (physical + inventory_tracking=true)
 * AND has at least $needed_qty available stock at the given warehouse.
 *
 * Parasut's documented way to add stock to an existing product via API is to create an inflow
 * shipment_document (a "Yeni Gelen İrsaliye" / incoming stock entry). The `/stock_updates` endpoint
 * only works on products that already have stock movements — it cannot seed stock from zero, and
 * returns "Güncellenen stok miktarı bulunamadı" ("no updated stock amount found") when there is
 * nothing to update.
 *
 * @param string      $parasut_product_id
 * @param int         $needed_qty        Minimum stock that must be present for the upcoming outflow.
 * @param string|null $seed_contact_id   Parasut contact id to attribute the inflow document to.
 * @param string|null $warehouse_id      Warehouse to credit the stock to.
 * @param string      $token
 * @return array ['success' => bool, 'current_stock' => float, 'error' => string]
 */
function parasut_ensure_product_stock_tracking($parasut_product_id, $needed_qty, $seed_contact_id, $warehouse_id, $token)
{
    // 1) Fetch current product state.
    $get = _parasut_request('GET', _parasut_prefix() . '/products/' . urlencode($parasut_product_id), null, $token);
    if ($get['http_code'] !== 200 || empty($get['body']['data'])) {
        return ['success' => false, 'current_stock' => 0, 'error' => lang(array('string' => 'Product fetch failed: {var:1}', 'vars' => array(_parasut_extract_error($get))))];
    }
    $attrs = $get['body']['data']['attributes'] ?? [];
    $is_tracked = !empty($attrs['inventory_tracking']) && (($attrs['product_type'] ?? '') === 'physical');
    $current_stock = (float) ($attrs['stock_count'] ?? 0);

    // 2) Enable inventory tracking if it's off (was originally created as 'service').
    if (!$is_tracked) {
        $patch_body = [
            'data' => [
                'id' => (string) $parasut_product_id,
                'type' => 'products',
                'attributes' => [
                    'product_type' => 'physical',
                    'inventory_tracking' => true,
                ],
            ],
        ];
        $patch = _parasut_request('PUT', _parasut_prefix() . '/products/' . urlencode($parasut_product_id), $patch_body, $token);
        if (!in_array($patch['http_code'], [200, 201])) {
            return ['success' => false, 'current_stock' => 0, 'error' => lang(array('string' => 'Product patch failed: {var:1}', 'vars' => array(_parasut_extract_error($patch))))];
        }
        $current_stock = 0; // freshly enabled — no stock yet.
    }

    // If the product already has stock movements (e.g. from sales_invoices), Parasut allows
    // negative stock outflow — no seeding needed. Skip the seed in that case so the main
    // shipment POST runs and its schema probes can fire.
    $has_movements = !empty($attrs['has_stock_movements']);
    if ($has_movements) {
        return ['success' => true, 'current_stock' => $current_stock, 'error' => ''];
    }

    // 3) If stock is insufficient for the outgoing shipment, seed it via an inflow shipment_document.
    if ($current_stock < $needed_qty && $seed_contact_id && $warehouse_id) {
        $seed_qty = max(1, (int) ceil($needed_qty - $current_stock));
        $seed_body = [
            'data' => [
                'type' => 'shipment_documents',
                'attributes' => [
                    'description' => lang('Opening stock entry (Pinegrap sync)'),
                    'issue_date' => date('Y-m-d'),
                    // inflow: true = incoming shipment / stock entry.
                    'inflow' => true,
                ],
                'relationships' => [
                    'contact' => [
                        'data' => ['type' => 'contacts', 'id' => (string) $seed_contact_id],
                    ],
                    'details' => [
                        'data' => [
                            [
                                'type' => 'shipment_document_details',
                                'attributes' => [
                                    'quantity' => $seed_qty,
                                    'unit' => 'Adet',
                                    'description' => lang('Opening stock'),
                                ],
                                'relationships' => [
                                    'product' => ['data' => ['type' => 'products', 'id' => (string) $parasut_product_id]],
                                    'warehouse' => ['data' => ['type' => 'warehouses', 'id' => (string) $warehouse_id]],
                                ],
                            ]
                        ],
                    ],
                ],
            ],
        ];
        $seed = _parasut_request('POST', _parasut_prefix() . '/shipment_documents', $seed_body, $token);
        if (!in_array($seed['http_code'], [200, 201])) {
            return [
                'success' => false,
                'current_stock' => $current_stock,
                'error' => lang(array('string' => 'Stock seeding (inflow) failed: {var:1}', 'vars' => array(_parasut_extract_error($seed)))),
            ];
        }
        $current_stock += $seed_qty;
    }

    return ['success' => true, 'current_stock' => $current_stock, 'error' => ''];
}

/**
 * @param array $order Full order row from DB (billing_* fields, contact_id, id)
 * @return array ['success' => bool, 'parasut_contact_id' => string|null, 'error' => string]
 */
function parasut_sync_contact($order)
{
    $token_result = parasut_get_token();
    if (!$token_result['success']) {
        return ['success' => false, 'parasut_contact_id' => null, 'error' => $token_result['error']];
    }
    $token = $token_result['token'];

    // If we already linked a Parasut contact to this order, reuse it.
    if (!empty($order['parasut_contact_id'])) {
        return ['success' => true, 'parasut_contact_id' => $order['parasut_contact_id'], 'error' => ''];
    }

    // Determine name and contact type.
    $billing_company = trim($order['billing_company'] ?? '');
    $billing_first = trim($order['billing_first_name'] ?? '');
    $billing_last = trim($order['billing_last_name'] ?? '');

    if ($billing_company !== '') {
        $contact_type = 'company';
        $name = $billing_company;
    } else {
        $contact_type = 'person';
        $name = trim($billing_first . ' ' . $billing_last);
    }

    // Identification number (TC / VKN).
    $id_number = _parasut_order_identity($order);

    $attributes = [
        'account_type' => 'customer',
        'contact_type' => $contact_type,
        'name' => $name,
        'email' => $order['billing_email_address'] ?? '',
        'phone' => $order['billing_phone_number'] ?? '',
    ];

    if ($id_number !== '') {
        // VKN for companies, TCKN for persons.
        if ($contact_type === 'company') {
            $attributes['tax_number'] = $id_number;
        } else {
            $attributes['tckn'] = $id_number;
        }
    }

    // Build address string.
    $addr_parts = array_filter([
        $order['billing_address_1'] ?? '',
        $order['billing_address_2'] ?? '',
        $order['billing_city'] ?? '',
        $order['billing_state'] ?? '',
        $order['billing_zip_code'] ?? '',
        $order['billing_country'] ?? '',
    ]);
    if ($addr_parts) {
        $attributes['address'] = implode(', ', $addr_parts);
    }

    $body = [
        'data' => [
            'type' => 'contacts',
            'attributes' => $attributes,
        ],
    ];

    $result = _parasut_request('POST', _parasut_prefix() . '/contacts', $body, $token);

    if (!in_array($result['http_code'], [200, 201]) || empty($result['body']['data']['id'])) {
        $msg = _parasut_extract_error($result);
        return ['success' => false, 'parasut_contact_id' => null, 'error' => lang(array('string' => 'Contact create failed: {var:1}', 'vars' => array($msg)))];
    }

    $parasut_contact_id = (string) $result['body']['data']['id'];

    // Persist the Parasut contact ID on the order.
    db("UPDATE orders SET parasut_contact_id = '" . escape($parasut_contact_id) . "' WHERE id = '" . escape($order['id']) . "'");

    return ['success' => true, 'parasut_contact_id' => $parasut_contact_id, 'error' => ''];
}

/**
 * Create a Parasut sales invoice for the given order and send it as
 * e-fatura (e_invoice) or e-arşiv (e_archive).
 *
 * @param int    $order_id  orders.id
 * @param string $type      'e_invoice' | 'e_archive'
 * @return array ['success' => bool, 'parasut_invoice_id' => string|null, 'error' => string]
 */
/**
 * Resolve the VAT rate that was actually charged on each line of a saved order.
 *
 * Parasut computes the VAT amount itself from the rate we send, so the rate has
 * to be the one the shopper was charged at - not a rate re-derived from the
 * stored amount: tax_total / line total gives back values like 20.12 that no tax
 * authority accepts.
 *
 * The resolution below mirrors update_order_item_taxes() step for step: an
 * exempt order pays nothing, a non-taxable product pays nothing, an address
 * outside every tax zone pays nothing, and where tax is due the product rate
 * beats the zone rate unless the product has none (get_effective_tax_rate()).
 * A recipient line is resolved against the shipping address, everything else
 * against the billing address.
 *
 * Each resolved rate is checked back against the stored amount. A mismatch
 * means the zone or the product rate was edited after the order was placed, so
 * the rate on file no longer explains what was charged; the caller is told
 * rather than sending a figure that would put a different total on the invoice
 * than the shopper paid.
 *
 * Lines flagged saved_for_later are left out here and everywhere else this file
 * reads order_items: they sit on the order but were never charged, so billing
 * them would put goods on the document the customer did not buy.
 *
 * @param int   $order_id
 * @param array $order     Order row, needs tax_exempt, billing_country, billing_state
 * @return array ['success' => bool, 'rates' => array<int,float>, 'error' => string]
 */
function _parasut_order_vat_rates($order_id, $order)
{
    $rates = [];

    if (!empty($order['tax_exempt'])) {
        $result = mysqli_query(db::$con, "SELECT id FROM order_items WHERE order_id = '" . escape($order_id) . "' AND saved_for_later = 0");
        while ($row = mysqli_fetch_assoc($result)) {
            $rates[(int) $row['id']] = 0.0;
        }
        return ['success' => true, 'rates' => $rates, 'error' => ''];
    }

    $billing_rate = get_tax_rate_for_address($order['billing_country'] ?? '', $order['billing_state'] ?? '');

    $query = "SELECT
                    order_items.id,
                    order_items.ship_to_id,
                    order_items.price,
                    order_items.quantity,
                    order_items.tax_total,
                    ship_tos.state AS ship_state,
                    ship_tos.country AS ship_country,
                    products.taxable,
                    products.tax_rate
              FROM order_items
              LEFT JOIN ship_tos ON order_items.ship_to_id = ship_tos.id
              LEFT JOIN products ON order_items.product_id = products.id
              WHERE order_items.order_id = '" . escape($order_id) . "'
                AND order_items.saved_for_later = 0";
    $result = mysqli_query(db::$con, $query) or output_error('Query failed.');

    // One lookup per distinct recipient - get_tax_rate_for_address() is two
    // joined queries and an order can carry the same recipient on many lines.
    $ship_rates = [];

    while ($item = mysqli_fetch_assoc($result)) {
        $item_id = (int) $item['id'];
        $apply_tax = false;
        $zone_rate = false;

        if (!empty($item['taxable'])) {
            if ((int) $item['ship_to_id'] > 0) {
                $ship_to_id = (int) $item['ship_to_id'];
                if (!array_key_exists($ship_to_id, $ship_rates)) {
                    $ship_rates[$ship_to_id] = get_tax_rate_for_address($item['ship_country'] ?? '', $item['ship_state'] ?? '');
                }
                $zone_rate = $ship_rates[$ship_to_id];
            } else {
                $zone_rate = $billing_rate;
            }
            // A zone rate of '0.000' is a real answer ("in a zone, rate zero")
            // and is falsy, which is why this asks the same way the order did.
            if ($zone_rate) {
                $apply_tax = true;
            }
        }

        $rate = $apply_tax ? (float) get_effective_tax_rate($item['tax_rate'], $zone_rate) : 0.0;

        // Same base as update_order_item_taxes(): the whole line.
        $line_quantity = max(1, (int) $item['quantity']);
        $expected_tax = (int) round($rate / 100 * (int) $item['price'] * $line_quantity);
        if ($expected_tax !== (int) $item['tax_total']) {
            return [
                'success' => false,
                'rates' => [],
                'error' => lang(array(
                    'string' => 'Tax rate on file no longer matches the tax charged on order item #{var:1} (charged {var:2}, rate {var:3}% gives {var:4}). The tax zone or the product rate was changed after this order was placed.',
                    'vars' => array($item_id, (int) $item['tax_total'], $rate, $expected_tax),
                )),
            ];
        }

        $rates[$item_id] = $rate;
    }

    return ['success' => true, 'rates' => $rates, 'error' => ''];
}

function parasut_create_invoice($order_id)
{
    $token_result = parasut_get_token();
    if (!$token_result['success']) {
        return ['success' => false, 'parasut_invoice_id' => null, 'error' => $token_result['error']];
    }
    $token = $token_result['token'];

    // Fetch full order.
    $query = "SELECT
                orders.*,
                contacts.company AS contact_company
              FROM orders
              LEFT JOIN contacts ON orders.contact_id = contacts.id
              WHERE orders.id = '" . escape($order_id) . "'
              LIMIT 1";
    $result = mysqli_query(db::$con, $query) or output_error('Query failed.');
    $order = mysqli_fetch_assoc($result);

    if (!$order) {
        return ['success' => false, 'parasut_invoice_id' => null, 'error' => lang('Order not found.')];
    }

    // An order discount is held on the header and is spread across the tax at the
    // header too, so the item lines still carry their undiscounted tax. Sending
    // those lines as they stand bills the customer for more than they paid, which
    // is worse than not billing them at all. Until the discount is allocated over
    // the lines, these orders are refused rather than invoiced wrong.
    if ((int) ($order['discount'] ?? 0) > 0) {
        return [
            'success' => false,
            'parasut_invoice_id' => null,
            'error' => lang('This order carries a discount, which cannot yet be put on the invoice lines. Invoicing it would overcharge the customer, so it was not created.'),
        ];
    }

    // Sync (or retrieve) the Parasut contact.
    $contact_result = parasut_sync_contact($order);
    if (!$contact_result['success']) {
        return ['success' => false, 'parasut_invoice_id' => null, 'error' => $contact_result['error']];
    }
    $parasut_contact_id = $contact_result['parasut_contact_id'];

    // Which document this has to be is settled before anything is created: a
    // sales invoice raised here and then found to need the other kind cannot be
    // taken back.
    $kind_result = _parasut_resolve_edoc_kind(_parasut_order_identity($order), $token);
    if (!$kind_result['success']) {
        return ['success' => false, 'parasut_invoice_id' => null, 'error' => $kind_result['error']];
    }
    $edoc_kind = $kind_result['kind'];
    $edoc_alias = $kind_result['alias'];

    $vat_result = _parasut_order_vat_rates($order_id, $order);
    if (!$vat_result['success']) {
        return ['success' => false, 'parasut_invoice_id' => null, 'error' => $vat_result['error']];
    }
    $vat_rates = $vat_result['rates'];

    // Fetch order items (join products to get display name and Parasut product ID).
    $items_query = "SELECT
                        order_items.id,
                        order_items.product_id,
                        order_items.quantity,
                        order_items.price,
                        order_items.product_name,
                        products.short_description,
                        products.parasut_product_id
                    FROM order_items
                    LEFT JOIN products ON order_items.product_id = products.id
                    WHERE order_items.order_id = '" . escape($order_id) . "'
                      AND order_items.saved_for_later = 0";
    $items_result = mysqli_query(db::$con, $items_query) or output_error('Query failed.');

    $default_parasut_product_id = defined('PARASUT_DEFAULT_PRODUCT_ID') ? PARASUT_DEFAULT_PRODUCT_ID : '';

    $details = [];
    while ($item = mysqli_fetch_assoc($items_result)) {
        $item_name = $item['short_description'] ?: ($item['product_name'] ?: lang('No Product Name'));
        $unit_price = round($item['price'] / 100, 2); // Convert from cents.

        // Resolve Parasut product ID: use synced ID, auto-sync if missing, fall back to default.
        $item_parasut_product_id = $item['parasut_product_id'];
        if (!$item_parasut_product_id && $item['product_id']) {
            $sync = parasut_sync_product($item['product_id'], $token);
            $item_parasut_product_id = $sync['success'] ? $sync['parasut_product_id'] : '';
        }
        if (!$item_parasut_product_id) {
            $item_parasut_product_id = $default_parasut_product_id;
        }

        $detail = [
            'type' => 'sales_invoice_details',
            'attributes' => [
                'quantity' => max(1, (int) $item['quantity']),
                'unit_price' => $unit_price,
                'vat_rate' => $vat_rates[(int) $item['id']] ?? 0,
                'description' => $item_name,
                'discount_type' => 'amount',
                'discount_value' => 0,
            ],
        ];
        if ($item_parasut_product_id) {
            $detail['relationships'] = [
                'product' => ['data' => ['type' => 'products', 'id' => $item_parasut_product_id]],
            ];
        }
        $details[] = $detail;
    }

    if (empty($details)) {
        // Fallback: single line with the order total. There is no line to read a
        // rate from here, so an order that carries tax cannot be expressed - and
        // billing it at 0% would hand the customer a document for less VAT than
        // they paid.
        if ((int) $order['tax'] > 0) {
            return [
                'success' => false,
                'parasut_invoice_id' => null,
                'error' => lang('This order has no items but does carry tax. Add the items before invoicing it.'),
            ];
        }
        $detail = [
            'type' => 'sales_invoice_details',
            'attributes' => [
                'quantity' => 1,
                'unit_price' => round($order['total'] / 100, 2),
                'vat_rate' => 0,
                'description' => lang('Order') . ' #' . $order['order_number'],
                'discount_type' => 'amount',
                'discount_value' => 0,
            ],
        ];
        if ($default_parasut_product_id) {
            $detail['relationships'] = [
                'product' => ['data' => ['type' => 'products', 'id' => $default_parasut_product_id]],
            ];
        }
        $details[] = $detail;
    }

    // Shipping and the credit-card surcharge are stored on the order header, not
    // as order_items, so without these two lines the invoice bills the goods only
    // and lands under what was charged. Neither carries tax in Pinegrap - the
    // order's tax comes from the item lines alone - so both go out at 0%.
    $shipping_cents = (int) ($order['shipping'] ?? 0);
    if ($shipping_cents > 0) {
        $details[] = [
            'type' => 'sales_invoice_details',
            'attributes' => [
                'quantity' => 1,
                'unit_price' => round($shipping_cents / 100, 2),
                'vat_rate' => 0,
                'description' => lang('Shipping'),
                'discount_type' => 'amount',
                'discount_value' => 0,
            ],
        ];
    }

    $surcharge_cents = (int) ($order['surcharge'] ?? 0);
    if ($surcharge_cents > 0) {
        $details[] = [
            'type' => 'sales_invoice_details',
            'attributes' => [
                'quantity' => 1,
                'unit_price' => round($surcharge_cents / 100, 2),
                'vat_rate' => 0,
                'description' => lang('Surcharge'),
                'discount_type' => 'amount',
                'discount_value' => 0,
            ],
        ];
    }

    // What the document should come to. A gift card is a means of payment, not a
    // reduction of the sale, so it is added back: the invoice states the whole
    // sale and the gift card settles part of it.
    $expected_cents = (int) $order['total'] + (int) ($order['gift_card_discount'] ?? 0);

    // What Parasut will make of the lines we are sending. It computes VAT from
    // the line total, which is the base the order now uses too, so these should
    // agree exactly. They are still compared: a difference means a line is
    // missing or wrong, and a legal document is not the place to find that out.
    $built_cents = 0;
    foreach ($details as $detail) {
        $line_cents = (int) round($detail['attributes']['unit_price'] * 100) * (int) $detail['attributes']['quantity'];
        $built_cents += $line_cents + (int) round($line_cents * $detail['attributes']['vat_rate'] / 100);
    }

    $variance_cents = $built_cents - $expected_cents;
    if (abs($variance_cents) > 100) {
        return [
            'success' => false,
            'parasut_invoice_id' => null,
            'error' => lang(array(
                'string' => 'Invoice total does not match the order total ({var:1} against {var:2}). The invoice was not created.',
                'vars' => array(number_format($built_cents / 100, 2), number_format($expected_cents / 100, 2)),
            )),
        ];
    }

    // orders.order_date is a UNIX timestamp; fall back to today when it is missing or zero.
    $ts = (int) ($order['order_date'] ?? 0);
    if ($ts <= 0) {
        $ts = time();
    }
    $issue_date = date('Y-m-d', $ts);

    // Map currency code to Parasut-accepted values.
    // Parasut accepts: TRL, USD, EUR, GBP. TRY is the ISO code but Parasut uses TRL.
    $currency_map = ['TRY' => 'TRL', 'TL' => 'TRL', '' => 'TRL'];
    $raw_currency = strtoupper(trim($order['currency_code'] ?? ''));
    $currency = $currency_map[$raw_currency] ?? $raw_currency;

    $invoice_body = [
        'data' => [
            'type' => 'sales_invoices',
            'attributes' => [
                'item_type' => 'invoice',
                'description' => lang('Order') . ' #' . $order['order_number'],
                'issue_date' => $issue_date,
                'due_date' => $issue_date,
                'currency' => $currency,
            ],
            'relationships' => [
                'contact' => [
                    'data' => ['type' => 'contacts', 'id' => $parasut_contact_id],
                ],
                'details' => ['data' => $details],
            ],
        ],
    ];

    $inv_result = _parasut_request('POST', _parasut_prefix() . '/sales_invoices', $invoice_body, $token);

    if (!in_array($inv_result['http_code'], [200, 201]) || empty($inv_result['body']['data']['id'])) {
        $msg = _parasut_extract_error($inv_result);
        return ['success' => false, 'parasut_invoice_id' => null, 'error' => lang(array('string' => 'Invoice create failed: {var:1}', 'vars' => array($msg)))];
    }

    $sales_invoice_id = (string) $inv_result['body']['data']['id'];

    // Now issue the e-invoice or e-archive document.
    if ($edoc_kind === 'einvoice') {
        $doc_body = [
            'data' => [
                'type' => 'e_invoices',
                'attributes' => [
                    'scenario' => 'commercial',
                    // The buyer's GIB address, from the registration lookup. An
                    // e-invoice with nowhere to go is rejected.
                    'to' => $edoc_alias,
                ],
                'relationships' => [
                    'invoice' => ['data' => ['type' => 'sales_invoices', 'id' => $sales_invoice_id]],
                ],
            ],
        ];
        $doc_result = _parasut_request('POST', _parasut_prefix() . '/e_invoices', $doc_body, $token);
    } else {
        // e_archive
        $doc_body = [
            'data' => [
                'type' => 'e_archives',
                'attributes' => [],
                'relationships' => [
                    'sales_invoice' => ['data' => ['type' => 'sales_invoices', 'id' => $sales_invoice_id]],
                ],
            ],
        ];
        $doc_result = _parasut_request('POST', _parasut_prefix() . '/e_archives', $doc_body, $token);
    }

    // Even if the e-invoice/e-archive step fails, we keep the sales invoice ID.
    if (!in_array($doc_result['http_code'], [200, 201])) {
        $msg = _parasut_extract_error($doc_result);
        // Keep the sales invoice ID so the e-document step can be retried, but do
        // not flag the order as exported: the flag drives the badge in the order
        // list, and marking it here would show a document as filed that never
        // reached the tax authority.
        db("UPDATE orders SET parasut_invoice_id = '" . escape($sales_invoice_id) . "' WHERE id = '" . escape($order_id) . "'");
        return ['success' => false, 'parasut_invoice_id' => $sales_invoice_id, 'edoc_kind' => $edoc_kind, 'error' => lang(array('string' => 'Sales invoice created (ID: {var:1}) but e-document failed: {var:2}', 'vars' => array($sales_invoice_id, $msg)))];
    }

    // The POST was accepted, which means the conversion was queued. Find out
    // whether it actually ran before telling the operator the document is filed.
    $job_id = (string) ($doc_result['body']['data']['id'] ?? '');
    $job = _parasut_track_job($job_id, $token);

    db("UPDATE orders SET parasut_invoice_id = '" . escape($sales_invoice_id) . "' WHERE id = '" . escape($order_id) . "'");

    if ($job['state'] === 'failed') {
        return [
            'success' => false,
            'parasut_invoice_id' => $sales_invoice_id,
            'edoc_kind' => $edoc_kind,
            'error' => lang(array('string' => 'Sales invoice created (ID: {var:1}) but the e-document was rejected: {var:2}', 'vars' => array($sales_invoice_id, $job['error']))),
        ];
    }

    // Only a conversion that finished counts as exported.
    if ($job['state'] === 'done') {
        db("UPDATE orders SET parasut_exported = 1 WHERE id = '" . escape($order_id) . "'");
    }

    return [
        'success' => true,
        'parasut_invoice_id' => $sales_invoice_id,
        'edoc_kind' => $edoc_kind,
        'edoc_state' => $job['state'],
        'variance_cents' => $variance_cents,
        'error' => '',
    ];
}

/**
 * Render the rounding variance from parasut_create_invoice() for display.
 *
 * Both sides work VAT out on the line total, so this should be zero. When it is
 * not, something is off that rounding does not explain, and the operator is told:
 * the document exists at that point and its figures are Parasut's, so the only
 * useful thing left is to say the amount does not tie to what was charged.
 *
 * @param array $result  Return value of parasut_create_invoice()
 * @return string  Empty when the totals agree
 */
function _parasut_variance_notice($result)
{
    $variance = (int) ($result['variance_cents'] ?? 0);
    if ($variance === 0) {
        return '';
    }

    return ' ' . lang([
        'string' => 'The invoice came out {var:1} away from the order total.',
        'vars' => prepare_price_for_output(abs($variance), false, '', 'plain_text', true, false),
    ]);
}

/**
 * Create a Parasut shipment document (e-irsaliye) for the given order.
 *
 * @param int $order_id  orders.id
 * @return array ['success' => bool, 'parasut_shipment_id' => string|null, 'error' => string]
 */
function parasut_create_shipment($order_id)
{
    $token_result = parasut_get_token();
    if (!$token_result['success']) {
        return ['success' => false, 'parasut_shipment_id' => null, 'error' => $token_result['error']];
    }
    $token = $token_result['token'];

    // Fetch full order.
    $query = "SELECT orders.*
              FROM orders
              WHERE orders.id = '" . escape($order_id) . "'
              LIMIT 1";
    $result = mysqli_query(db::$con, $query) or output_error('Query failed.');
    $order = mysqli_fetch_assoc($result);

    if (!$order) {
        return ['success' => false, 'parasut_shipment_id' => null, 'error' => lang('Order not found.')];
    }

    // Sync contact.
    $contact_result = parasut_sync_contact($order);
    if (!$contact_result['success']) {
        return ['success' => false, 'parasut_shipment_id' => null, 'error' => $contact_result['error']];
    }
    $parasut_contact_id = $contact_result['parasut_contact_id'];

    // Fetch order items (join products to get display name and Parasut product ID).
    $items_query = "SELECT
                        order_items.product_id,
                        order_items.quantity,
                        order_items.product_name,
                        products.short_description,
                        products.parasut_product_id,
                        products.inventory
                    FROM order_items
                    LEFT JOIN products ON order_items.product_id = products.id
                    WHERE order_items.order_id = '" . escape($order_id) . "'
                      AND order_items.saved_for_later = 0";
    $items_result = mysqli_query(db::$con, $items_query) or output_error('Query failed.');

    $default_parasut_product_id = defined('PARASUT_DEFAULT_PRODUCT_ID') ? PARASUT_DEFAULT_PRODUCT_ID : '';

    // Warehouse is required on every shipment_document_detail AND to seed stock for newly
    // tracked products — fetch it once up front.
    $warehouse_result = parasut_ensure_default_warehouse($token);
    $default_warehouse_id = $warehouse_result['success'] ? $warehouse_result['warehouse_id'] : '';
    if (!$default_warehouse_id) {
        return ['success' => false, 'parasut_shipment_id' => null, 'error' => lang(array('string' => 'No Parasut warehouse available: {var:1}', 'vars' => array(($warehouse_result['error'] ?? ''))))];
    }

    $details = [];
    $skipped_count = 0;
    while ($item = mysqli_fetch_assoc($items_result)) {
        // Skip non-shippable items — Parasut shipment_documents can only contain
        // stock-tracked (physical + inventory_tracking) products. Non-inventory items
        // (services, digital goods, etc.) are silently excluded from the e-irsaliye
        // (the e-waybill).
        if (empty($item['inventory'])) {
            $skipped_count++;
            continue;
        }

        $item_name = $item['short_description'] ?: ($item['product_name'] ?: lang('No Product Name'));
        $item_qty = max(1, (int) $item['quantity']);

        $item_parasut_product_id = $item['parasut_product_id'];
        if (!$item_parasut_product_id && $item['product_id']) {
            $sync = parasut_sync_product($item['product_id'], $token);
            $item_parasut_product_id = $sync['success'] ? $sync['parasut_product_id'] : '';
        }
        // Guarantee the Parasut product is physical + inventory_tracking=true AND has
        // enough stock. If stock is short, seed it via an inflow shipment_document
        // (the documented Parasut way to add stock to a product via API).
        if ($item_parasut_product_id) {
            $ensure = parasut_ensure_product_stock_tracking(
                $item_parasut_product_id,
                $item_qty,
                $parasut_contact_id,
                $default_warehouse_id,
                $token
            );
            if (!$ensure['success']) {
                return [
                    'success' => false,
                    'parasut_shipment_id' => null,
                    'error' => lang(array('string' => 'Stock preparation failed for {var:1}: {var:2}', 'vars' => array($item_name, $ensure['error']))),
                ];
            }
        }

        $detail = [
            'type' => 'shipment_document_details',
            'attributes' => [
                'quantity' => $item_qty,
                'unit' => 'Adet',
                'description' => $item_name,
            ],
            'relationships' => [
                'warehouse' => ['data' => ['type' => 'warehouses', 'id' => (string) $default_warehouse_id]],
            ],
        ];
        if ($item_parasut_product_id) {
            $detail['relationships']['product'] = ['data' => ['type' => 'products', 'id' => $item_parasut_product_id]];
        }
        $details[] = $detail;
    }

    if (empty($details)) {
        return [
            'success' => false,
            'parasut_shipment_id' => null,
            'error' => lang('This order has no shippable (stock-tracked) items. The e-waybill cannot be created.'),
        ];
    }

    // orders.order_date is a UNIX timestamp; fall back to today when it is missing or zero.
    $ts = (int) ($order['order_date'] ?? 0);
    if ($ts <= 0) {
        $ts = time();
    }
    $issue_date = date('Y-m-d', $ts);

    $body = [
        'data' => [
            'type' => 'shipment_documents',
            'attributes' => [
                'description' => lang('Order') . ' #' . $order['order_number'],
                'issue_date' => $issue_date,
                // inflow: false = outgoing (giden irsaliye, sale/shipment to customer)
                //         true  = incoming (gelen irsaliye, purchase from supplier)
                'inflow' => false,
            ],
            'relationships' => [
                'contact' => [
                    'data' => ['type' => 'contacts', 'id' => $parasut_contact_id],
                ],
                'details' => ['data' => $details],
            ],
        ],
    ];

    $result = _parasut_request('POST', _parasut_prefix() . '/shipment_documents', $body, $token);

    // One request, one outcome. A failed create is reported as it stands and is
    // never retried here with a reshaped body: shipment_documents has no
    // idempotency key, so a retry that succeeds leaves a second stock document
    // in Parasut that this code cannot see and the operator has to delete by hand.
    if (!in_array($result['http_code'], [200, 201]) || empty($result['body']['data']['id'])) {
        $msg = _parasut_extract_error($result);
        return ['success' => false, 'parasut_shipment_id' => null, 'error' => lang(array('string' => 'Shipment create failed: {var:1}', 'vars' => array($msg)))];
    }

    $shipment_id = (string) $result['body']['data']['id'];

    db("UPDATE orders SET parasut_shipment_id = '" . escape($shipment_id) . "' WHERE id = '" . escape($order_id) . "'");

    return ['success' => true, 'parasut_shipment_id' => $shipment_id, 'error' => ''];
}

/**
 * Check if a VKN/TCKN is registered for e-invoice in the GİB system.
 *
 * @param string $vkn  Tax/identity number to look up
 * @return array ['success' => bool, 'data' => array|null, 'error' => string]
 */
function parasut_check_einvoice_address($vkn)
{
    $token_result = parasut_get_token();
    if (!$token_result['success']) {
        return ['success' => false, 'data' => null, 'error' => $token_result['error']];
    }
    $token = $token_result['token'];

    $url = _parasut_prefix() . '/e_invoice_inboxes?filter[vkn]=' . urlencode($vkn);
    $result = _parasut_request('GET', $url, null, $token);

    if ($result['http_code'] !== 200) {
        return ['success' => false, 'data' => null, 'error' => _parasut_extract_error($result)];
    }

    $items = $result['body']['data'] ?? [];
    return ['success' => true, 'data' => $items, 'error' => ''];
}

/**
 * List purchase bills (incoming invoices) from Parasut.
 *
 * @param array $filters  Optional: ['start_date' => 'Y-m-d', 'end_date' => 'Y-m-d', 'contact_id' => '...', 'item_type' => '...']
 * @param int   $page
 * @param int   $per_page
 * @return array ['success' => bool, 'data' => array, 'included' => array, 'meta' => array, 'error' => string]
 */
function parasut_list_purchase_bills($filters = [], $page = 1, $per_page = 25)
{
    $token_result = parasut_get_token();
    if (!$token_result['success']) {
        return ['success' => false, 'data' => [], 'included' => [], 'meta' => [], 'error' => $token_result['error']];
    }
    $token = $token_result['token'];

    $params = [
        'page[number]' => $page,
        'page[size]' => $per_page,
        'sort' => '-issue_date',
        'include' => 'supplier',
    ];
    if (!empty($filters['start_date'])) {
        $params['filter[issue_date][gte]'] = $filters['start_date'];
    }
    if (!empty($filters['end_date'])) {
        $params['filter[issue_date][lte]'] = $filters['end_date'];
    }
    if (!empty($filters['contact_id'])) {
        $params['filter[supplier_id]'] = $filters['contact_id'];
    }
    if (!empty($filters['item_type'])) {
        $params['filter[item_type]'] = $filters['item_type'];
    }

    $url = _parasut_prefix() . '/purchase_bills?' . http_build_query($params);
    $result = _parasut_request('GET', $url, null, $token);

    if ($result['http_code'] !== 200) {
        return ['success' => false, 'data' => [], 'included' => [], 'meta' => [], 'error' => _parasut_extract_error($result)];
    }

    // Build a lookup map for included contacts (id => name).
    $included_contacts = [];
    foreach (($result['body']['included'] ?? []) as $inc) {
        if ($inc['type'] === 'contacts') {
            $included_contacts[$inc['id']] = $inc['attributes']['name'] ?? '';
        }
    }

    return [
        'success' => true,
        'data' => $result['body']['data'] ?? [],
        'contacts' => $included_contacts,
        'meta' => $result['body']['meta'] ?? [],
        'error' => '',
    ];
}

/**
 * List sales invoices from Parasut.
 *
 * @param array $filters  Optional: ['start_date', 'end_date', 'contact_id', 'item_type']
 * @param int   $page
 * @param int   $per_page
 * @return array ['success' => bool, 'data' => array, 'contacts' => array, 'meta' => array, 'error' => string]
 */
function parasut_list_sales_invoices($filters = [], $page = 1, $per_page = 25)
{
    $token_result = parasut_get_token();
    if (!$token_result['success']) {
        return ['success' => false, 'data' => [], 'contacts' => [], 'meta' => [], 'error' => $token_result['error']];
    }
    $token = $token_result['token'];

    $params = [
        'page[number]' => $page,
        'page[size]' => $per_page,
        'sort' => '-issue_date',
        'include' => 'contact',
    ];
    if (!empty($filters['start_date'])) {
        $params['filter[issue_date][gte]'] = $filters['start_date'];
    }
    if (!empty($filters['end_date'])) {
        $params['filter[issue_date][lte]'] = $filters['end_date'];
    }
    if (!empty($filters['contact_id'])) {
        $params['filter[contact_id]'] = $filters['contact_id'];
    }
    if (!empty($filters['item_type'])) {
        $params['filter[item_type]'] = $filters['item_type'];
    }

    $url = _parasut_prefix() . '/sales_invoices?' . http_build_query($params);
    $result = _parasut_request('GET', $url, null, $token);

    if ($result['http_code'] !== 200) {
        return ['success' => false, 'data' => [], 'contacts' => [], 'meta' => [], 'error' => _parasut_extract_error($result)];
    }

    $contacts = [];
    foreach (($result['body']['included'] ?? []) as $inc) {
        if ($inc['type'] === 'contacts') {
            $contacts[$inc['id']] = $inc['attributes']['name'] ?? '';
        }
    }

    return [
        'success' => true,
        'data' => $result['body']['data'] ?? [],
        'contacts' => $contacts,
        'meta' => $result['body']['meta'] ?? [],
        'error' => '',
    ];
}
