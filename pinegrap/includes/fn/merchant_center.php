<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Google Merchant Center through the Merchant API (merchantapi.googleapis.com).
 *
 * A site Google account connected for the merchant_center purpose
 * (includes/connect/purposes/merchant_center.php) picks one Merchant Center
 * account. The site then registers its Google TSV product feed address
 * (feed.php) as a data source Google fetches once a day, and reads the
 * account's aggregate product statuses on a schedule so System Status can
 * show the product problems Merchant Center reports.
 *
 * Nothing has a table of its own: the account, the data source and the last
 * reading of the issues are kept under the "merchant" key of
 * config.product_feeds, next to the feed they belong to
 * (pg_product_feed_settings_parse() passes the key through, this module
 * types it with pg_merchant_state_parse()).
 *
 * The pure parts (state, request body, source matching, issue summary, cache
 * window, tile rows) are tested in tests/merchant_center_test.php.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_FUNCTIONS_DIR')) {
    exit;
}

if (!defined('PG_MERCHANT_API')) {
    define('PG_MERCHANT_API', 'https://merchantapi.googleapis.com');
}

// How long a reading of the product issues is kept before the daily job
// reads them again. Shorter than a day so the daily run always finds it due;
// a "Check now" on the card resets it.
if (!defined('PG_MERCHANT_ISSUES_TTL')) {
    define('PG_MERCHANT_ISSUES_TTL', 43200);
}

// Issues kept for the card and the tile, worst first.
if (!defined('PG_MERCHANT_TOP_ISSUES')) {
    define('PG_MERCHANT_TOP_ISSUES', 5);
}

// The hour (site time zone) Google is asked to fetch the feed: after the
// nightly build has written it.
if (!defined('PG_MERCHANT_FETCH_HOUR')) {
    define('PG_MERCHANT_FETCH_HOUR', 6);
}

/* ---------------------------------------------------------------------------
   Pure parts
   --------------------------------------------------------------------------- */

// The Merchant Center state as stored under config.product_feeds "merchant",
// typed; anything unknown or malformed falls back to its default.
function pg_merchant_state_parse($stored)
{
    $state = array(
        'connection_id'  => 0,
        'account'        => '',
        'account_name'   => '',
        'country'        => '',
        'data_source'    => '',
        'registered_at'  => 0,
        'register_error' => '',
        'checked_at'     => 0,
        'check_error'    => '',
        'issues'         => pg_merchant_issue_summary(array()),
    );

    if (!is_array($stored)) {
        return $state;
    }

    foreach (array('connection_id', 'registered_at', 'checked_at') as $key) {
        if (isset($stored[$key]) && is_scalar($stored[$key])) {
            $state[$key] = max(0, (int) $stored[$key]);
        }
    }

    foreach (array('account_name', 'register_error', 'check_error') as $key) {
        if (isset($stored[$key]) && is_scalar($stored[$key])) {
            $state[$key] = pg_merchant_clip((string) $stored[$key], 255);
        }
    }

    if (isset($stored['account']) && is_scalar($stored['account']) && preg_match('/^\d{1,20}$/', (string) $stored['account'])) {
        $state['account'] = (string) $stored['account'];
    }

    if (isset($stored['country']) && is_string($stored['country']) && preg_match('/^[A-Z]{2}$/', $stored['country'])) {
        $state['country'] = $stored['country'];
    }

    if (isset($stored['data_source']) && is_string($stored['data_source'])
        && preg_match('#^accounts/\d{1,20}/dataSources/\d{1,20}$#', $stored['data_source'])) {
        $state['data_source'] = $stored['data_source'];
    }

    if (isset($stored['issues']) && is_array($stored['issues'])) {
        $state['issues'] = pg_merchant_summary_parse($stored['issues']);
    }

    return $state;
}

// A stored issue summary, typed again (it was written by
// pg_merchant_issue_summary(), but the column is plain JSON).
function pg_merchant_summary_parse($stored)
{
    $summary = pg_merchant_issue_summary(array());

    foreach (array('counts', 'products') as $group) {
        foreach (array_keys($summary[$group]) as $key) {
            if (isset($stored[$group][$key]) && is_scalar($stored[$group][$key])) {
                $summary[$group][$key] = max(0, (int) $stored[$group][$key]);
            }
        }
    }

    if (isset($stored['top']) && is_array($stored['top'])) {
        foreach (array_slice($stored['top'], 0, PG_MERCHANT_TOP_ISSUES) as $issue) {
            if (!is_array($issue) || !isset($issue['code'], $issue['severity'], $issue['description'])) {
                continue;
            }

            $severity = pg_merchant_severity((string) $issue['severity']);

            $summary['top'][] = array(
                'code'        => pg_merchant_clip((string) $issue['code'], 100),
                'severity'    => $severity,
                'description' => pg_merchant_clip((string) $issue['description'], 255),
                'attribute'   => isset($issue['attribute']) ? pg_merchant_clip((string) $issue['attribute'], 100) : '',
                'products'    => isset($issue['products']) ? max(0, (int) $issue['products']) : 0,
                'help'        => isset($issue['help']) ? pg_merchant_help_url($issue['help']) : '',
            );
        }
    }

    return $summary;
}

// One line of outside text: control characters become spaces, then the
// line is cut to a length (characters, not bytes).
function pg_merchant_clip($text, $max)
{
    $text = trim(preg_replace('/[\x00-\x1F\x7F]+/', ' ', (string) $text));

    if (function_exists('mb_substr')) {
        return mb_substr($text, 0, $max, 'UTF-8');
    }

    return substr($text, 0, $max);
}

// A Merchant API severity as one of the three this module knows; anything
// else (SEVERITY_UNSPECIFIED, a future value) counts as not impacting.
function pg_merchant_severity($severity)
{
    return in_array($severity, array('DISAPPROVED', 'DEMOTED'), true) ? $severity : 'NOT_IMPACTED';
}

// A help address from Google, kept only when it is a plain https address on
// a Google host, so a value from outside never becomes a script link.
function pg_merchant_help_url($url)
{
    $url = is_string($url) ? trim($url) : '';

    if (($url === '') || (strlen($url) > 500) || !preg_match('#^https://([a-z0-9.-]+)(/[^\s"<>]*)?$#i', $url, $match)) {
        return '';
    }

    $host = strtolower($match[1]);

    return (($host === 'google.com') || (substr($host, -11) === '.google.com')) ? $url : '';
}

// The feed label a data source is created with: Google wants up to 20
// upper-case letters, digits and dashes; the target country is used.
function pg_merchant_feed_label($country)
{
    return substr(preg_replace('/[^A-Z0-9-]/', '', strtoupper((string) $country)), 0, 20);
}

// The body of accounts.dataSources.create for the feed: a primary product
// data source whose file Google fetches once a day.
function pg_merchant_data_source_body($feed_url, $language, $country, $time_zone, $name)
{
    $language = strtolower(substr(preg_replace('/[^A-Za-z]/', '', (string) $language), 0, 2));
    $country = strtoupper((string) $country);

    $fetch = array(
        'enabled'   => true,
        'fetchUri'  => (string) $feed_url,
        'frequency' => 'FREQUENCY_DAILY',
        'timeOfDay' => array('hours' => PG_MERCHANT_FETCH_HOUR),
    );

    if ((string) $time_zone !== '') {
        $fetch['timeZone'] = (string) $time_zone;
    }

    return array(
        'displayName'              => pg_merchant_clip($name, 100),
        'primaryProductDataSource' => array(
            'contentLanguage' => ($language !== '') ? $language : 'en',
            'feedLabel'       => pg_merchant_feed_label($country),
            'countries'       => array($country),
        ),
        'fileInput'                => array(
            'fetchSettings' => $fetch,
        ),
    );
}

// The data source of a list that fetches the feed: the one whose fetch
// address is the feed address, else the one the site registered before
// (its address is from an older token and is moved to the new one), else
// none.
function pg_merchant_find_source($sources, $feed_url, $known = '')
{
    $found = array('name' => '', 'same_url' => false);

    foreach ((array) $sources as $source) {
        if (!is_array($source) || !isset($source['name']) || !is_string($source['name'])) {
            continue;
        }

        $uri = isset($source['fileInput']['fetchSettings']['fetchUri']) ? (string) $source['fileInput']['fetchSettings']['fetchUri'] : '';

        if (($uri !== '') && (trim($uri) === (string) $feed_url)) {
            return array('name' => $source['name'], 'same_url' => true);
        }

        if (($known !== '') && ($source['name'] === $known)) {
            $found = array('name' => $source['name'], 'same_url' => false);
        }
    }

    return $found;
}

// The summary of the aggregate product statuses: issue kinds per severity,
// products per state (the largest figure among the reporting contexts and
// countries, so a product listed for two programmes is not counted twice)
// and the worst issues with Merchant Center's own description.
function pg_merchant_issue_summary($statuses, $limit = PG_MERCHANT_TOP_ISSUES)
{
    $summary = array(
        'counts'   => array('DISAPPROVED' => 0, 'DEMOTED' => 0, 'NOT_IMPACTED' => 0),
        'products' => array('active' => 0, 'pending' => 0, 'disapproved' => 0, 'expiring' => 0),
        'top'      => array(),
    );
    $rank = array('DISAPPROVED' => 3, 'DEMOTED' => 2, 'NOT_IMPACTED' => 1);
    $issues = array();

    foreach ((array) $statuses as $status) {
        if (!is_array($status)) {
            continue;
        }

        foreach (array('active', 'pending', 'disapproved', 'expiring') as $key) {
            $field = $key . 'Count';

            if (isset($status['stats'][$field]) && is_scalar($status['stats'][$field])) {
                $summary['products'][$key] = max($summary['products'][$key], (int) $status['stats'][$field]);
            }
        }

        if (!isset($status['itemLevelIssues']) || !is_array($status['itemLevelIssues'])) {
            continue;
        }

        foreach ($status['itemLevelIssues'] as $issue) {
            if (!is_array($issue) || !isset($issue['code']) || !is_string($issue['code']) || ($issue['code'] === '')) {
                continue;
            }

            $severity = pg_merchant_severity(isset($issue['severity']) ? (string) $issue['severity'] : '');
            $products = isset($issue['productCount']) && is_scalar($issue['productCount']) ? max(0, (int) $issue['productCount']) : 0;
            $key = $issue['code'] . "\n" . (isset($issue['attribute']) ? (string) $issue['attribute'] : '');

            if (!isset($issues[$key])) {
                $issues[$key] = array(
                    'code'        => pg_merchant_clip($issue['code'], 100),
                    'severity'    => $severity,
                    'description' => pg_merchant_clip(isset($issue['description']) && is_string($issue['description']) ? $issue['description'] : $issue['code'], 255),
                    'attribute'   => isset($issue['attribute']) && is_string($issue['attribute']) ? pg_merchant_clip($issue['attribute'], 100) : '',
                    'products'    => $products,
                    'help'        => pg_merchant_help_url(isset($issue['documentationUri']) ? $issue['documentationUri'] : ''),
                );
                continue;
            }

            if ($rank[$severity] > $rank[$issues[$key]['severity']]) {
                $issues[$key]['severity'] = $severity;
            }

            $issues[$key]['products'] = max($issues[$key]['products'], $products);
        }
    }

    foreach ($issues as $issue) {
        $summary['counts'][$issue['severity']]++;
    }

    uasort($issues, function ($a, $b) use ($rank) {
        if ($rank[$a['severity']] !== $rank[$b['severity']]) {
            return $rank[$b['severity']] - $rank[$a['severity']];
        }

        if ($a['products'] !== $b['products']) {
            return $b['products'] - $a['products'];
        }

        return strcmp($a['code'], $b['code']);
    });

    $summary['top'] = array_slice(array_values($issues), 0, max(0, (int) $limit));

    return $summary;
}

// Whether the reading of the issues is old enough to read again.
function pg_merchant_issues_due($checked_at, $now, $ttl = PG_MERCHANT_ISSUES_TTL)
{
    $checked_at = (int) $checked_at;

    // A time ahead of the clock (the clock was set back) is due as well.
    return ($checked_at <= 0) || ($checked_at > (int) $now) || (((int) $now - $checked_at) >= (int) $ttl);
}

// The account's product diagnostics in Merchant Center.
function pg_merchant_center_url($account)
{
    return 'https://merchants.google.com/mc/products/diagnostics?a=' . rawurlencode(preg_replace('/\D/', '', (string) $account));
}

// The tile's state from the stored reading: 'none' (no tile: no account
// chosen), 'fail' (a product is disapproved, or the data source could not
// be registered), 'warn' (demoted products, or the last reading failed),
// 'ok' otherwise.
function pg_merchant_health($state)
{
    if ($state['account'] === '') {
        return 'none';
    }

    if (($state['register_error'] !== '') || ($state['issues']['counts']['DISAPPROVED'] > 0)) {
        return 'fail';
    }

    if (($state['check_error'] !== '') || ($state['issues']['counts']['DEMOTED'] > 0) || ($state['data_source'] === '')) {
        return 'warn';
    }

    return 'ok';
}

// The rows the System Status tile expands to: one per top issue, with
// Merchant Center's description and the number of products it touches.
// The text is left raw; the widget escapes every row it prints.
function pg_merchant_issue_detail($summary)
{
    $tones = array('DISAPPROVED' => 'fail', 'DEMOTED' => 'warn', 'NOT_IMPACTED' => 'info');
    $rows = array();

    foreach ($summary['top'] as $issue) {
        $rows[] = array(
            // Google's description often names the attribute already.
            'label' => $issue['description'] . ((($issue['attribute'] !== '') && (strpos($issue['description'], '[' . $issue['attribute'] . ']') === false)) ? ' [' . $issue['attribute'] . ']' : ''),
            'state' => $tones[$issue['severity']],
            'when'  => lang(array('string' => '{var:1} product(s)', 'vars' => (int) $issue['products'])),
        );
    }

    return $rows;
}

// Merchant Center accounts from accounts.list, id => name.
function pg_merchant_accounts_from_api($data)
{
    $accounts = array();

    if (!is_array($data) || !isset($data['accounts']) || !is_array($data['accounts'])) {
        return $accounts;
    }

    foreach ($data['accounts'] as $account) {
        if (!is_array($account)) {
            continue;
        }

        $id = isset($account['accountId']) ? (string) $account['accountId'] : '';

        if (($id === '') && isset($account['name']) && preg_match('#^accounts/(\d{1,20})$#', (string) $account['name'], $match)) {
            $id = $match[1];
        }

        if (!preg_match('/^\d{1,20}$/', $id)) {
            continue;
        }

        $name = isset($account['accountName']) && is_string($account['accountName']) ? pg_merchant_clip($account['accountName'], 255) : '';
        $accounts[$id] = ($name !== '') ? $name : $id;
    }

    return $accounts;
}

// The target country to offer first: the organization country when it is
// already a two-letter code, else the one the shop's currency belongs to
// when that is unambiguous, else none.
function pg_merchant_default_country($organization_country, $currency)
{
    $organization_country = strtoupper(trim((string) $organization_country));

    if (preg_match('/^[A-Z]{2}$/', $organization_country)) {
        return $organization_country;
    }

    $by_currency = array('TRY' => 'TR', 'USD' => 'US', 'GBP' => 'GB', 'JPY' => 'JP', 'CAD' => 'CA', 'AUD' => 'AU', 'CHF' => 'CH');
    $currency = strtoupper(trim((string) $currency));

    return isset($by_currency[$currency]) ? $by_currency[$currency] : '';
}

/* ---------------------------------------------------------------------------
   Storage
   --------------------------------------------------------------------------- */

// Whether the feed setup and connected accounts are there.
function pg_merchant_ready()
{
    return function_exists('pg_product_feed_schema_ready') && pg_product_feed_schema_ready()
        && function_exists('pg_connect_schema_ready') && pg_connect_schema_ready();
}

function pg_merchant_state()
{
    if (!function_exists('pg_product_feed_schema_ready') || !pg_product_feed_schema_ready()) {
        return pg_merchant_state_parse(null);
    }

    $settings = pg_product_feed_settings();

    return pg_merchant_state_parse(isset($settings['merchant']) ? $settings['merchant'] : null);
}

// Writes changes into a fresh read of the setup, so a feed build running
// meanwhile keeps its statuses.
function pg_merchant_state_save($changes)
{
    $settings = pg_product_feed_settings();
    $state = pg_merchant_state_parse(isset($settings['merchant']) ? $settings['merchant'] : null);

    foreach ($changes as $key => $value) {
        if (array_key_exists($key, $state)) {
            $state[$key] = $value;
        }
    }

    $state = pg_merchant_state_parse($state);
    $settings['merchant'] = $state;
    pg_product_feed_settings_save($settings);

    return $state;
}

// The Google account Merchant Center is reached through: the one chosen,
// while it still serves the purpose, else the newest site account connected
// for it.
function pg_merchant_connection($state = null)
{
    if (!pg_merchant_ready()) {
        return null;
    }

    $state = is_array($state) ? $state : pg_merchant_state();

    if ($state['connection_id'] > 0) {
        $account = pg_connect_account($state['connection_id']);

        if (is_array($account) && ($account['user_id'] === 0) && ($account['provider'] === 'google')
            && in_array('merchant_center', $account['purposes'], true)) {
            return $account;
        }
    }

    $accounts = pg_connect_accounts(array('provider' => 'google', 'user_id' => 0, 'purpose' => 'merchant_center'));

    return $accounts ? $accounts[0] : null;
}

// The Google TSV feed's address, or '' while that feed is off or its address
// cannot be shown (no site key).
function pg_merchant_feed_url()
{
    $settings = pg_product_feed_settings();
    $entry = $settings['formats']['google_tsv'];

    if (!$entry['enabled'] || ($entry['token_hash'] === '')) {
        return '';
    }

    $token = pg_product_feed_token($entry);

    return ($token !== '') ? pg_product_feed_url($token) : '';
}

/* ---------------------------------------------------------------------------
   Calls
   --------------------------------------------------------------------------- */

// One Merchant API call.
function pg_merchant_call($connection_id, $method, $path, $body = null)
{
    $options = array(
        'headers'  => array('Accept: application/json'),
        'timeout'  => 40,
        'max_body' => 4194304,
    );

    if ($body !== null) {
        $options['body'] = json_encode($body, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $options['headers'][] = 'Content-Type: application/json';
    }

    $result = pg_connect_api($connection_id, $method, PG_MERCHANT_API . $path, $options);
    $data = is_array($result['data']) ? $result['data'] : array();
    $error = '';

    if (!$result['ok']) {
        $error = isset($data['error']['message']) && is_string($data['error']['message'])
            ? pg_merchant_clip('HTTP ' . (int) $result['status'] . ': ' . $data['error']['message'], 255)
            : pg_merchant_clip(($result['error'] !== '') ? $result['error'] : 'HTTP ' . (int) $result['status'], 255);
    }

    return array('ok' => $result['ok'], 'status' => (int) $result['status'], 'data' => $data, 'error' => $error);
}

// Every page of a list call, joined.
function pg_merchant_list($connection_id, $path, $field, $pages = 10)
{
    $items = array();
    $token = '';

    for ($page = 0; $page < $pages; $page++) {
        $call = pg_merchant_call($connection_id, 'GET', $path . (($token !== '') ? '&pageToken=' . rawurlencode($token) : ''));

        if (!$call['ok']) {
            return array('ok' => false, 'items' => $items, 'error' => $call['error']);
        }

        if (isset($call['data'][$field]) && is_array($call['data'][$field])) {
            $items = array_merge($items, $call['data'][$field]);
        }

        $token = isset($call['data']['nextPageToken']) ? (string) $call['data']['nextPageToken'] : '';

        if ($token === '') {
            break;
        }
    }

    return array('ok' => true, 'items' => $items, 'error' => '');
}

// The Merchant Center accounts the connected Google account can reach.
function pg_merchant_accounts($connection_id)
{
    $list = pg_merchant_list($connection_id, '/accounts/v1/accounts?pageSize=250', 'accounts', 4);

    return array('ok' => $list['ok'], 'accounts' => pg_merchant_accounts_from_api(array('accounts' => $list['items'])), 'error' => $list['error']);
}

// Registers the feed as the account's fetched data source, once: a source
// already fetching the feed address is kept, the one registered before with
// an older address is moved to the new one, otherwise one is created.
function pg_merchant_register()
{
    $state = pg_merchant_state();
    $connection = pg_merchant_connection($state);
    $feed_url = pg_merchant_feed_url();
    $error = '';

    if (!is_array($connection) || ($state['account'] === '')) {
        $error = lang('Connect a Google account and choose a Merchant Center account first.');
    } elseif ($feed_url === '') {
        $error = lang('Switch the Google Merchant Center feed on and save, so it has an address Google can fetch.');
    } elseif ($state['country'] === '') {
        $error = lang('Choose the country the products are sold in.');
    }

    if ($error !== '') {
        pg_merchant_state_save(array('register_error' => $error));

        return array('ok' => false, 'action' => '', 'error' => $error);
    }

    $base = '/datasources/v1/accounts/' . $state['account'] . '/dataSources';
    $list = pg_merchant_list($connection['id'], $base . '?pageSize=1000', 'dataSources', 5);
    $action = '';

    if ($list['ok']) {
        $found = pg_merchant_find_source($list['items'], $feed_url, $state['data_source']);

        if ($found['same_url']) {
            $call = array('ok' => true, 'data' => array('name' => $found['name']), 'error' => '');
            $action = 'kept';
        } elseif ($found['name'] !== '') {
            $call = pg_merchant_call($connection['id'], 'PATCH', '/datasources/v1/' . $found['name'] . '?updateMask=fileInput.fetchSettings.fetchUri',
                array('fileInput' => array('fetchSettings' => array('fetchUri' => $feed_url))));
            $action = 'moved';
        } else {
            $call = pg_merchant_call($connection['id'], 'POST', $base, pg_merchant_data_source_body(
                $feed_url,
                defined('TRANSLATION_SOURCE_LANGUAGE') ? TRANSLATION_SOURCE_LANGUAGE : 'en',
                $state['country'],
                defined('TIMEZONE') ? TIMEZONE : '',
                lang('Website product feed')
            ));
            $action = 'created';
        }
    } else {
        $call = $list;
    }

    if (!$call['ok']) {
        pg_merchant_state_save(array('register_error' => $call['error']));

        return array('ok' => false, 'action' => $action, 'error' => $call['error']);
    }

    pg_merchant_state_save(array(
        'connection_id'  => $connection['id'],
        'data_source'    => isset($call['data']['name']) ? (string) $call['data']['name'] : $state['data_source'],
        'registered_at'  => time(),
        'register_error' => '',
    ));

    return array('ok' => true, 'action' => $action, 'error' => '');
}

// Reads the account's product issues into the stored state, when the last
// reading is older than the cache window or when forced.
function pg_merchant_refresh_issues($force = false)
{
    if (!pg_merchant_ready()) {
        return array('ok' => false, 'ran' => false, 'error' => '');
    }

    $state = pg_merchant_state();

    if (($state['account'] === '') || (!$force && !pg_merchant_issues_due($state['checked_at'], time()))) {
        return array('ok' => true, 'ran' => false, 'error' => '');
    }

    $connection = pg_merchant_connection($state);

    if (!is_array($connection)) {
        $error = lang('The Google account connected for Merchant Center is no longer there.');
        pg_merchant_state_save(array('checked_at' => time(), 'check_error' => $error));

        return array('ok' => false, 'ran' => true, 'error' => $error);
    }

    $list = pg_merchant_list($connection['id'], '/issueresolution/v1/accounts/' . $state['account'] . '/aggregateProductStatuses?pageSize=250', 'aggregateProductStatuses', 8);

    if (!$list['ok']) {
        pg_merchant_state_save(array('checked_at' => time(), 'check_error' => $list['error']));

        return array('ok' => false, 'ran' => true, 'error' => $list['error']);
    }

    pg_merchant_state_save(array(
        'checked_at'  => time(),
        'check_error' => '',
        'issues'      => pg_merchant_issue_summary($list['items']),
    ));

    return array('ok' => true, 'ran' => true, 'error' => '');
}

// What the Product Feeds card asked for: choose an account (and country),
// register the feed again, read the issues now, or forget the account.
// Choosing an account registers the feed and reads the issues at once.
function pg_merchant_save_from_screen($input)
{
    $messages = array();

    if (!pg_merchant_ready()) {
        return $messages;
    }

    $state = pg_merchant_state();

    if (!empty($input['unlink'])) {
        pg_merchant_state_save(array(
            'account' => '', 'account_name' => '', 'data_source' => '', 'registered_at' => 0, 'register_error' => '',
            'checked_at' => 0, 'check_error' => '', 'issues' => pg_merchant_issue_summary(array()),
        ));
        $messages[] = array('notice', lang('Merchant Center is no longer used. The data source stays in the Merchant Center account until you delete it there.'));

        return $messages;
    }

    $country = strtoupper(trim(isset($input['country']) ? (string) $input['country'] : ''));

    if (($country !== '') && preg_match('/^[A-Z]{2}$/', $country) && ($country !== $state['country'])) {
        $state = pg_merchant_state_save(array('country' => $country));
    }

    $chosen = isset($input['account']) ? trim((string) $input['account']) : '';
    $register = !empty($input['register']);

    if (($chosen !== '') && ($chosen !== $state['account'])) {
        $connection = pg_merchant_connection($state);
        $offered = is_array($connection) ? pg_merchant_accounts($connection['id']) : array('ok' => false, 'accounts' => array(), 'error' => '');

        if (!isset($offered['accounts'][$chosen])) {
            $messages[] = array('error', lang('The Merchant Center account could not be chosen.') . (($offered['error'] !== '') ? ' ' . $offered['error'] : ''));

            return $messages;
        }

        pg_merchant_state_save(array(
            'connection_id' => $connection['id'], 'account' => $chosen, 'account_name' => $offered['accounts'][$chosen],
            'data_source' => '', 'registered_at' => 0, 'register_error' => '', 'checked_at' => 0, 'check_error' => '',
            'issues' => pg_merchant_issue_summary(array()),
        ));
        $register = true;
        $input['check'] = true;
    }

    if ($register) {
        $result = pg_merchant_register();
        $texts = array(
            'kept'    => lang('Merchant Center already fetches the feed from its address.'),
            'moved'   => lang('Merchant Center now fetches the feed from its new address.'),
            'created' => lang('The feed was added to Merchant Center as a data source fetched every day.'),
        );

        $messages[] = $result['ok']
            ? array('notice', $texts[$result['action']])
            : array('error', lang(array('string' => 'The feed could not be registered in Merchant Center: {var:1}', 'vars' => $result['error'])));
    }

    if (!empty($input['check'])) {
        $result = pg_merchant_refresh_issues(true);

        if (!$result['ok'] && ($result['error'] !== '')) {
            $messages[] = array('error', lang(array('string' => 'The product issues could not be read: {var:1}', 'vars' => $result['error'])));
        }
    }

    return $messages;
}
