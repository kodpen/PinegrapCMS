<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Google Search Console: which searches bring people to the site, how often
 * its pages are shown in Google, and the one-click setup that verifies the
 * site, adds it to Search Console and submits its sitemap.
 *
 * The Google account is a connected account (includes/fn/connect.php) with
 * the search_console purpose. Which account and which Search Console
 * property the site uses, and how far the daily download has come, are one
 * JSON value in config.search_console. The verification code Google asks the
 * pages to carry is config.google_site_verification, read on every request as
 * GOOGLE_SITE_VERIFICATION and printed by get_page_content.php.
 *
 * The figures are kept per day in seo_search_daily (2026.4.10, 10.73), at
 * three levels told apart by kind:
 *
 *   site   the day's totals for the property (dimension date);
 *   page   one row per address (date, page);
 *   query  one row per search (date, query).
 *
 * The totals are read on their own rather than added up from the other two:
 * Google leaves anonymised searches out of the query rows, so a sum of them
 * would be lower than what Search Console itself shows.
 *
 * Calls to Google run in seo_search_job.php and behind the buttons a person
 * waits for on search_console.php, never while a visitor's page is built.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_FUNCTIONS_DIR')) {
    exit;
}


/* ---------------------------------------------------------------------------
   Pure helpers (tests/search_console_test.php)
   --------------------------------------------------------------------------- */

/**
 * The setup as stored, with every key present and typed.
 *
 * @param string|null $json config.search_console
 * @return array
 */
function pg_gsc_settings_parse($json)
{
    $settings = array(
        'connection_id' => 0,
        'property'      => '',
        'permission'    => '',
        'linked_at'     => 0,
        'synced_at'     => 0,
        'synced_to'     => '',
        'sync_error'    => '',
        'sitemap_at'    => 0,
        'sitemap_error' => '',
    );

    $stored = json_decode((string) $json, true);

    if (!is_array($stored)) {
        return $settings;
    }

    foreach ($settings as $key => $default) {
        if (!array_key_exists($key, $stored) || is_array($stored[$key])) {
            continue;
        }

        $settings[$key] = is_int($default) ? (int) $stored[$key] : (string) $stored[$key];
    }

    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $settings['synced_to'])) {
        $settings['synced_to'] = '';
    }

    return $settings;
}

/**
 * A Search Console permission level, as one short word.
 *
 * The API answers "siteOwner"; its discovery document spells the same values
 * "SITE_OWNER". Both are read.
 *
 * @param string $level
 * @return string 'owner', 'full', 'restricted', 'unverified' or ''
 */
function pg_gsc_permission($level)
{
    $map = array(
        'siteowner'          => 'owner',
        'sitefulluser'       => 'full',
        'siterestricteduser' => 'restricted',
        'siteunverifieduser' => 'unverified',
    );

    $key = strtolower(preg_replace('/[^A-Za-z]/', '', (string) $level));

    return isset($map[$key]) ? $map[$key] : '';
}

/**
 * The Search Console property that is this site, out of the ones the
 * account can see.
 *
 * A URL-prefix property for exactly this address comes first; otherwise a
 * domain property for the host or one of its parent domains, the closest
 * first. Within the same kind of match, the higher permission wins. A
 * property the account has not verified is no use and is skipped.
 *
 * @param array  $entries  the siteEntry list of sites.list
 * @param string $site_url this site's address, scheme to path, ending in "/"
 * @return array|null property, permission, match ('prefix' or 'domain')
 */
function pg_gsc_property_pick($entries, $site_url)
{
    if (!is_array($entries)) {
        return null;
    }

    $parts = parse_url((string) $site_url);

    if (!is_array($parts) || empty($parts['host'])) {
        return null;
    }

    $host = strtolower($parts['host']);
    $wanted = pg_gsc_prefix_key($site_url);

    // The host and every parent domain with at least one dot left: for
    // shop.example.com that is shop.example.com and example.com.
    $domains = array();
    $labels = explode('.', $host);

    while (count($labels) >= 2) {
        $domains[] = implode('.', $labels);
        array_shift($labels);
    }

    $rank_permission = array('owner' => 0, 'full' => 1, 'restricted' => 2);

    $best = null;
    $best_rank = null;

    foreach ($entries as $entry) {
        if (!is_array($entry) || !isset($entry['siteUrl'])) {
            continue;
        }

        $property = (string) $entry['siteUrl'];
        $permission = pg_gsc_permission(isset($entry['permissionLevel']) ? $entry['permissionLevel'] : '');

        if (!isset($rank_permission[$permission])) {
            continue;
        }

        if (strpos($property, 'sc-domain:') === 0) {
            $position = array_search(strtolower(substr($property, 10)), $domains, true);

            if ($position === false) {
                continue;
            }

            $match = 'domain';
            $rank = 100 + ($position * 10) + $rank_permission[$permission];
        } elseif (pg_gsc_prefix_key($property) === $wanted) {
            $match = 'prefix';
            $rank = $rank_permission[$permission];
        } else {
            continue;
        }

        if (($best_rank === null) || ($rank < $best_rank)) {
            $best_rank = $rank;
            $best = array('property' => $property, 'permission' => $permission, 'match' => $match);
        }
    }

    return $best;
}

/**
 * A URL-prefix property address in a form two spellings of the same address
 * compare equal in: scheme and host lower-cased, the path ending in "/".
 *
 * @param string $url
 * @return string '' when it is not an http(s) address
 */
function pg_gsc_prefix_key($url)
{
    $parts = parse_url((string) $url);

    if (!is_array($parts) || empty($parts['host']) || empty($parts['scheme'])) {
        return '';
    }

    $scheme = strtolower($parts['scheme']);

    if (($scheme !== 'http') && ($scheme !== 'https')) {
        return '';
    }

    $path = isset($parts['path']) ? $parts['path'] : '/';

    if (substr($path, -1) !== '/') {
        $path .= '/';
    }

    return $scheme . '://' . strtolower($parts['host']) . (isset($parts['port']) ? ':' . (int) $parts['port'] : '') . $path;
}

/**
 * The page filter a property needs so that only this site's addresses are
 * counted: none for the URL-prefix property of this site, a regular
 * expression (RE2) on the page for a domain property, which also covers the
 * other hosts of the domain.
 *
 * @param string $property
 * @param string $site_url
 * @return string '' for no filter
 */
function pg_gsc_page_filter($property, $site_url)
{
    if (strpos((string) $property, 'sc-domain:') !== 0) {
        return '';
    }

    $parts = parse_url((string) $site_url);

    if (!is_array($parts) || empty($parts['host'])) {
        return '';
    }

    $path = isset($parts['path']) ? $parts['path'] : '/';

    return '^https?://' . preg_quote(strtolower($parts['host'])) . preg_quote($path);
}

/**
 * The verification code inside the meta tag Google hands out, or the code
 * itself when that is what was given.
 *
 * @param string $token
 * @return string '' when there is no code of the expected shape
 */
function pg_gsc_meta_content($token)
{
    $token = trim((string) $token);

    if (preg_match('/content\s*=\s*["\']([^"\']+)["\']/i', $token, $match)) {
        $token = trim($match[1]);
    }

    return preg_match('/^[A-Za-z0-9_\-]{10,200}$/', $token) ? $token : '';
}

/**
 * The days a download asks for: from three days before the last day already
 * read (Google keeps revising the most recent days), or the whole window the
 * first time, up to yesterday.
 *
 * @param string $today     Y-m-d
 * @param string $synced_to the last day read, '' for none
 * @param int    $days      how far back the window reaches
 * @return array from, to (Y-m-d)
 */
function pg_gsc_sync_range($today, $synced_to, $days = 90)
{
    $today_time = strtotime((string) $today . ' 12:00:00');

    $to = date('Y-m-d', $today_time - 86400);
    $oldest = date('Y-m-d', $today_time - ((int) $days * 86400));

    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $synced_to)) {
        return array('from' => $oldest, 'to' => $to);
    }

    $from = date('Y-m-d', strtotime($synced_to . ' 12:00:00') - (3 * 86400));

    if ($from < $oldest) {
        $from = $oldest;
    }

    if ($from > $to) {
        $from = $to;
    }

    return array('from' => $from, 'to' => $to);
}

/**
 * The reporting period ending on the last day with figures, and the one of
 * the same length before it.
 *
 * @param string $latest Y-m-d
 * @param int    $days
 * @return array from, to, previous_from, previous_to
 */
function pg_gsc_period($latest, $days)
{
    $days = max(1, (int) $days);
    $end = strtotime((string) $latest . ' 12:00:00');

    return array(
        'from'          => date('Y-m-d', $end - (($days - 1) * 86400)),
        'to'            => date('Y-m-d', $end),
        'previous_from' => date('Y-m-d', $end - (((2 * $days) - 1) * 86400)),
        'previous_to'   => date('Y-m-d', $end - ($days * 86400)),
    );
}

/**
 * The key a row of seo_search_daily is replaced by.
 *
 * @return string 40 hex characters
 */
function pg_gsc_row_key($kind, $page, $query)
{
    return sha1((string) $kind . "\n" . (string) $page . "\n" . (string) $query);
}

/**
 * Search Analytics rows as seo_search_daily rows.
 *
 * @param string $kind 'site', 'page' or 'query'
 * @param array  $rows the rows of a searchAnalytics.query answer; keys[0] is
 *                     the date, keys[1] the page or the query
 * @return array day, kind, page, query, clicks, impressions, position
 */
function pg_gsc_rows_from_api($kind, $rows)
{
    $out = array();

    if (!is_array($rows)) {
        return $out;
    }

    foreach ($rows as $row) {
        if (!is_array($row) || !isset($row['keys'][0]) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $row['keys'][0])) {
            continue;
        }

        $second = isset($row['keys'][1]) ? (string) $row['keys'][1] : '';

        if (($kind !== 'site') && ($second === '')) {
            continue;
        }

        $out[] = array(
            'day'         => (string) $row['keys'][0],
            'kind'        => $kind,
            'page'        => ($kind === 'page') ? pg_gsc_clip($second, 2048) : '',
            'query'       => ($kind === 'query') ? pg_gsc_clip($second, 500) : '',
            'clicks'      => isset($row['clicks']) ? max(0, (int) round($row['clicks'])) : 0,
            'impressions' => isset($row['impressions']) ? max(0, (int) round($row['impressions'])) : 0,
            'position'    => isset($row['position']) ? round((float) $row['position'], 2) : 0.0,
        );
    }

    return $out;
}

function pg_gsc_clip($text, $length)
{
    return function_exists('mb_substr') ? mb_substr((string) $text, 0, $length) : substr((string) $text, 0, $length);
}

/**
 * Clicks over impressions, as a fraction.
 *
 * @return float
 */
function pg_gsc_ctr($clicks, $impressions)
{
    return ((int) $impressions > 0) ? ((int) $clicks / (int) $impressions) : 0.0;
}

/**
 * How far a figure moved against the period before, as a fraction; null when
 * the period before had none to compare with.
 *
 * @return float|null
 */
function pg_gsc_change($now, $before)
{
    if ((float) $before <= 0) {
        return null;
    }

    return ((float) $now - (float) $before) / (float) $before;
}

/**
 * A page address as the screens show it: the path under the site, or the
 * whole address without its scheme when it is another host's.
 *
 * @param string $url
 * @param string $site_url
 * @return string
 */
function pg_gsc_page_label($url, $site_url)
{
    $url = (string) $url;
    $prefix = pg_gsc_prefix_key($site_url);
    $parts = parse_url($url);

    if (is_array($parts) && !empty($parts['host']) && ($prefix !== '')) {
        $key = pg_gsc_prefix_key($url);

        if (($key !== '') && (strpos($key, $prefix) === 0)) {
            $path = isset($parts['path']) ? $parts['path'] : '/';
            $base = (string) parse_url($prefix, PHP_URL_PATH);

            return '/' . ltrim(substr($path, strlen(rtrim($base, '/'))), '/')
                . (isset($parts['query']) ? '?' . $parts['query'] : '');
        }
    }

    return preg_replace('#^https?://#i', '', $url);
}

/**
 * What went wrong in a call to Google, in a line.
 *
 * @param array $result what pg_connect_api() returned
 * @return string
 */
function pg_gsc_error_text($result)
{
    $data = isset($result['data']) ? $result['data'] : null;

    if (is_array($data) && isset($data['error']['message']) && is_string($data['error']['message'])) {
        return pg_connect_clip('HTTP ' . (int) $result['status'] . ': ' . $data['error']['message']);
    }

    if (isset($result['error']) && ((string) $result['error'] !== '')) {
        return pg_connect_clip($result['error']);
    }

    return 'HTTP ' . (int) (isset($result['status']) ? $result['status'] : 0);
}


/* ---------------------------------------------------------------------------
   Setup
   --------------------------------------------------------------------------- */

/**
 * Whether the table and the config columns (10.73) are there.
 *
 * @return bool
 */
function pg_gsc_schema_ready()
{
    return pg_schema_has('seo_search_daily')
        && pg_schema_has('config', 'search_console')
        && pg_schema_has('config', 'google_site_verification')
        && function_exists('pg_connect_schema_ready') && pg_connect_schema_ready();
}

/**
 * This site's address as a URL-prefix property: scheme, host and path.
 *
 * @return string
 */
function pg_gsc_site_url()
{
    return URL_SCHEME . HOSTNAME_SETTING . PATH;
}

/**
 * @return array pg_gsc_settings_parse()
 */
function pg_gsc_settings()
{
    if (!pg_gsc_schema_ready()) {
        return pg_gsc_settings_parse('');
    }

    return pg_gsc_settings_parse(db_value("SELECT search_console FROM config"));
}

/**
 * Changes some of the setup and keeps the rest.
 *
 * @param array $changes key => value, keys of pg_gsc_settings_parse()
 * @return array the setup after the change
 */
function pg_gsc_settings_save($changes)
{
    $settings = pg_gsc_settings();

    foreach ($changes as $key => $value) {
        if (array_key_exists($key, $settings)) {
            $settings[$key] = is_int($settings[$key]) ? (int) $value : (string) $value;
        }
    }

    db("UPDATE config SET search_console = '" . e(json_encode($settings)) . "'");

    return $settings;
}

/**
 * Whether the site is linked to a Search Console property: what decides
 * whether the Welcome card is shown and the daily job does anything.
 *
 * @return bool
 */
function pg_gsc_linked()
{
    if (!pg_gsc_schema_ready()) {
        return false;
    }

    $settings = pg_gsc_settings();

    return ($settings['property'] !== '') && ($settings['connection_id'] > 0);
}

/**
 * The connected Google account Search Console is read through: the one the
 * site was linked with while it still serves the purpose, otherwise the
 * newest site account connected for it.
 *
 * @return array|null pg_connect_account()
 */
function pg_gsc_account()
{
    if (!pg_gsc_schema_ready()) {
        return null;
    }

    $settings = pg_gsc_settings();

    if ($settings['connection_id'] > 0) {
        $account = pg_connect_account($settings['connection_id']);

        if (is_array($account) && ($account['user_id'] === 0) && ($account['provider'] === 'google')
            && in_array('search_console', $account['purposes'], true)) {
            return $account;
        }
    }

    $accounts = pg_connect_accounts(array('provider' => 'google', 'user_id' => 0, 'purpose' => 'search_console'));

    return $accounts ? $accounts[0] : null;
}

/**
 * One call to a Google API with the account's token.
 *
 * @param int         $connection_id
 * @param string      $method
 * @param string      $url
 * @param array|null  $body  sent as JSON; null sends none
 * @return array ok, status, data, error
 */
function pg_gsc_call($connection_id, $method, $url, $body = null)
{
    $options = array(
        'headers'  => array('Accept: application/json'),
        'timeout'  => 40,
        'max_body' => 8388608,
    );

    if ($body !== null) {
        $options['body'] = json_encode($body);
        $options['headers'][] = 'Content-Type: application/json';
    } elseif (($method === 'PUT') || ($method === 'POST')) {
        // A PUT with no body still has to say so, or Google answers 411.
        $options['body'] = '';
    }

    $result = pg_connect_api($connection_id, $method, $url, $options);

    return array(
        'ok'     => $result['ok'],
        'status' => (int) $result['status'],
        'data'   => is_array($result['data']) ? $result['data'] : array(),
        'error'  => $result['ok'] ? '' : pg_gsc_error_text($result),
    );
}

/**
 * The address of a Search Console call about a property.
 *
 * @param string $property
 * @param string $path what follows the property, starting with "/" or ''
 * @return string
 */
function pg_gsc_url($property, $path = '')
{
    return 'https://searchconsole.googleapis.com/webmasters/v3/sites/' . rawurlencode((string) $property) . $path;
}

/**
 * Links the site to Search Console in one go: finds this site among the
 * account's properties, or verifies it with a meta tag and adds it; then
 * submits the sitemap and reads the first figures.
 *
 * @param array $account pg_connect_account()
 * @param int   $budget  seconds the first download may take
 * @return array ok, error, notices (list of lines)
 */
function pg_gsc_link($account, $budget = 40)
{
    $notices = array();
    $site_url = pg_gsc_site_url();

    $fail = function ($error) use (&$notices) {
        return array('ok' => false, 'error' => $error, 'notices' => $notices);
    };

    $sites = pg_gsc_call($account['id'], 'GET', 'https://searchconsole.googleapis.com/webmasters/v3/sites');

    if (!$sites['ok']) {
        return $fail(lang(array('string' => 'Search Console did not list the properties: {var:1}', 'vars' => $sites['error'])));
    }

    $pick = pg_gsc_property_pick(isset($sites['data']['siteEntry']) ? $sites['data']['siteEntry'] : array(), $site_url);

    if (!is_array($pick)) {

        // Not in Search Console yet: verify the site with a meta tag on its
        // pages, then add it. The tag goes live before Google is asked to
        // look for it, because the check is Google reading the home page.
        $token = pg_gsc_call($account['id'], 'POST', 'https://www.googleapis.com/siteVerification/v1/token', array(
            'site'               => array('identifier' => $site_url, 'type' => 'SITE'),
            'verificationMethod' => 'META',
        ));

        $content = $token['ok'] ? pg_gsc_meta_content(isset($token['data']['token']) ? $token['data']['token'] : '') : '';

        if ($content === '') {
            return $fail(lang(array('string' => 'Google did not give a verification code: {var:1}', 'vars' => ($token['error'] !== '') ? $token['error'] : lang('the answer had no code'))));
        }

        db("UPDATE config SET google_site_verification = '" . e($content) . "'");

        $verify = pg_gsc_call(
            $account['id'],
            'POST',
            'https://www.googleapis.com/siteVerification/v1/webResource?verificationMethod=META',
            array('site' => array('identifier' => $site_url, 'type' => 'SITE')));

        if (!$verify['ok']) {
            return $fail(lang(array('string' => 'Google could not verify the site: {var:1}', 'vars' => $verify['error'])));
        }

        $notices[] = lang('Google verified that the site is yours.');

        $add = pg_gsc_call($account['id'], 'PUT', pg_gsc_url($site_url));

        if (!$add['ok']) {
            return $fail(lang(array('string' => 'The site could not be added to Search Console: {var:1}', 'vars' => $add['error'])));
        }

        $notices[] = lang('The site was added to Search Console.');

        $pick = array('property' => $site_url, 'permission' => 'owner', 'match' => 'prefix');

    } else {

        $notices[] = lang(array('string' => 'The site is linked to the Search Console property {var:1}.', 'vars' => $pick['property']));

    }

    // A property that changed means figures from another one: start over.
    $before = pg_gsc_settings();

    if ($before['property'] !== $pick['property']) {
        db("DELETE FROM seo_search_daily");
        $before['synced_to'] = '';
    }

    pg_gsc_settings_save(array(
        'connection_id' => $account['id'],
        'property'      => $pick['property'],
        'permission'    => $pick['permission'],
        'linked_at'     => time(),
        'synced_to'     => $before['synced_to'],
        'sync_error'    => '',
    ));

    if (in_array($pick['permission'], array('owner', 'full'), true)) {
        $sitemap = pg_gsc_submit_sitemap($account['id'], $pick['property']);

        $notices[] = $sitemap['ok']
            ? lang('The sitemap was submitted to Google.')
            : lang(array('string' => 'The sitemap could not be submitted: {var:1}', 'vars' => $sitemap['error']));
    } else {
        $notices[] = lang('The account may only read this property, so the sitemap was not submitted.');
    }

    $sync = pg_gsc_sync($budget);

    $notices[] = $sync['ok']
        ? lang(array('string' => 'The search figures up to {var:1} were downloaded.', 'vars' => prepare_form_data_for_output($sync['to'], 'date', false)))
        : lang(array('string' => 'The search figures could not be downloaded yet: {var:1}', 'vars' => $sync['error']));

    pg_gsc_job_switch_on();

    if (function_exists('log_activity')) {
        log_activity(lang(array('string' => 'linked the site to the Google Search Console property {var:1}', 'vars' => $pick['property'])), $_SESSION['sessionusername'] ?? '');
    }

    return array('ok' => true, 'error' => '', 'notices' => $notices);
}

/**
 * Submits the site's sitemap to the property.
 *
 * @return array ok, error
 */
function pg_gsc_submit_sitemap($connection_id, $property)
{
    $sitemap = URL_SCHEME . HOSTNAME_SETTING . PATH . 'sitemap.xml';

    $call = pg_gsc_call($connection_id, 'PUT', pg_gsc_url($property, '/sitemaps/' . rawurlencode($sitemap)));

    pg_gsc_settings_save($call['ok']
        ? array('sitemap_at' => time(), 'sitemap_error' => '')
        : array('sitemap_error' => $call['error']));

    return array('ok' => $call['ok'], 'error' => $call['error']);
}

/**
 * Forgets the property and its figures. The verification code stays on the
 * pages: removing it would make Google drop the site's verification, which is
 * the owner's to decide in Search Console, not a side effect of unlinking.
 */
function pg_gsc_unlink()
{
    $settings = pg_gsc_settings();

    db("DELETE FROM seo_search_daily");

    pg_gsc_settings_save(array(
        'connection_id' => 0,
        'property'      => '',
        'permission'    => '',
        'linked_at'     => 0,
        'synced_at'     => 0,
        'synced_to'     => '',
        'sync_error'    => '',
        'sitemap_at'    => 0,
        'sitemap_error' => '',
    ));

    if (function_exists('log_activity') && ($settings['property'] !== '')) {
        log_activity(lang(array('string' => 'unlinked the site from the Google Search Console property {var:1}', 'vars' => $settings['property'])), $_SESSION['sessionusername'] ?? '');
    }
}

/**
 * Adds the daily download to the jobs the general job runs, when the general
 * job is the one running them. A site that schedules each job on its own
 * keeps its own schedule.
 *
 * @return bool true when the job is (now) in the list
 */
function pg_gsc_job_switch_on()
{
    if (!defined('JOB_DISPATCH_ENABLED') || !JOB_DISPATCH_ENABLED) {
        return false;
    }

    $list = pg_cron_dispatch_list();

    if (in_array('seo_search_job', $list, true)) {
        return true;
    }

    $list[] = 'seo_search_job';

    db("UPDATE config SET job_dispatch = '" . e(implode(',', $list)) . "'");

    return true;
}


/* ---------------------------------------------------------------------------
   Download
   --------------------------------------------------------------------------- */

/**
 * Reads the figures of the days pg_gsc_sync_range() names into
 * seo_search_daily, and drops the ones older than Google keeps.
 *
 * @param int $budget seconds; a level that would start after it is left for
 *                    the next run, which reads the same days again
 * @return array ok, skipped, error, from, to, rows
 */
function pg_gsc_sync($budget = 120)
{
    $started = time();

    $answer = array('ok' => false, 'skipped' => false, 'error' => '', 'from' => '', 'to' => '', 'rows' => 0);

    if (!pg_gsc_schema_ready()) {
        $answer['skipped'] = true;

        return $answer;
    }

    $settings = pg_gsc_settings();
    $account = pg_gsc_account();

    if (($settings['property'] === '') || !is_array($account)) {
        $answer['skipped'] = true;

        return $answer;
    }

    $range = pg_gsc_sync_range(date('Y-m-d'), $settings['synced_to']);
    $filter = pg_gsc_page_filter($settings['property'], pg_gsc_site_url());

    $answer['from'] = $range['from'];
    $answer['to'] = $range['to'];

    $levels = array(
        'site'  => array('date'),
        'page'  => array('date', 'page'),
        'query' => array('date', 'query'),
    );

    foreach ($levels as $kind => $dimensions) {

        if ((time() - $started) > $budget) {
            $answer['error'] = lang('The download took too long and continues on the next run.');
            pg_gsc_settings_save(array('sync_error' => $answer['error']));

            return $answer;
        }

        $fetched = pg_gsc_fetch($account['id'], $settings['property'], $range, $dimensions, $filter);

        if (!$fetched['ok']) {
            $answer['error'] = $fetched['error'];
            pg_gsc_settings_save(array('sync_error' => $fetched['error']));

            return $answer;
        }

        $answer['rows'] += pg_gsc_store($kind, $range, pg_gsc_rows_from_api($kind, $fetched['rows']));
    }

    // Search Console keeps sixteen months; so does this table.
    db("DELETE FROM seo_search_daily WHERE day < '" . e(date('Y-m-d', strtotime('-16 months'))) . "'");

    pg_gsc_settings_save(array(
        'synced_at'  => time(),
        'synced_to'  => $range['to'],
        'sync_error' => '',
    ));

    $answer['ok'] = true;

    return $answer;
}

/**
 * Every Search Analytics row of a range at one level, page by page of the
 * answer, up to 50 000 rows.
 *
 * @return array ok, rows, error
 */
function pg_gsc_fetch($connection_id, $property, $range, $dimensions, $filter)
{
    $rows = array();
    $limit = 5000;

    for ($start = 0; $start < 50000; $start += $limit) {

        $body = array(
            'startDate'  => $range['from'],
            'endDate'    => $range['to'],
            'dimensions' => $dimensions,
            'type'       => 'web',
            'rowLimit'   => $limit,
            'startRow'   => $start,
        );

        if ($filter !== '') {
            $body['dimensionFilterGroups'] = array(array('filters' => array(array(
                'dimension'  => 'page',
                'operator'   => 'includingRegex',
                'expression' => $filter,
            ))));
        }

        $call = pg_gsc_call($connection_id, 'POST', pg_gsc_url($property, '/searchAnalytics/query'), $body);

        if (!$call['ok']) {
            return array('ok' => false, 'rows' => array(), 'error' => $call['error']);
        }

        $batch = (isset($call['data']['rows']) && is_array($call['data']['rows'])) ? $call['data']['rows'] : array();

        foreach ($batch as $row) {
            $rows[] = $row;
        }

        if (count($batch) < $limit) {
            break;
        }
    }

    return array('ok' => true, 'rows' => $rows, 'error' => '');
}

/**
 * Replaces one level's rows of a range with what Google answered.
 *
 * @param string $kind
 * @param array  $range from, to
 * @param array  $rows  pg_gsc_rows_from_api()
 * @return int rows written
 */
function pg_gsc_store($kind, $range, $rows)
{
    db("DELETE FROM seo_search_daily
        WHERE kind = '" . e($kind) . "' AND day BETWEEN '" . e($range['from']) . "' AND '" . e($range['to']) . "'");

    $values = array();
    $written = 0;

    foreach ($rows as $row) {
        $values[] = "('" . e($row['day']) . "', '" . pg_gsc_row_key($row['kind'], $row['page'], $row['query']) . "', '"
            . e($row['kind']) . "', '" . e($row['page']) . "', '" . e($row['query']) . "', "
            . (int) $row['clicks'] . ", " . (int) $row['impressions'] . ", '" . sprintf('%.2f', (float) $row['position']) . "')";

        if (count($values) >= 500) {
            $written += pg_gsc_store_batch($values);
            $values = array();
        }
    }

    if ($values) {
        $written += pg_gsc_store_batch($values);
    }

    return $written;
}

function pg_gsc_store_batch($values)
{
    db("INSERT INTO seo_search_daily (day, row_key, kind, page, query, clicks, impressions, position)
        VALUES " . implode(', ', $values) . "
        ON DUPLICATE KEY UPDATE
            clicks = VALUES(clicks),
            impressions = VALUES(impressions),
            position = VALUES(position)");

    return count($values);
}


/* ---------------------------------------------------------------------------
   Reading the figures
   --------------------------------------------------------------------------- */

/**
 * The last day with figures, or '' when there are none.
 *
 * @return string Y-m-d
 */
function pg_gsc_latest_day()
{
    if (!pg_gsc_schema_ready()) {
        return '';
    }

    $day = db_value("SELECT MAX(day) FROM seo_search_daily WHERE kind = 'site'");

    return ($day === null) ? '' : (string) $day;
}

/**
 * The totals of a range: clicks, impressions, click rate and the average
 * position weighted by impressions.
 *
 * @return array clicks, impressions, ctr, position
 */
function pg_gsc_totals($from, $to)
{
    $row = db_item(
        "SELECT
            COALESCE(SUM(clicks), 0) AS clicks,
            COALESCE(SUM(impressions), 0) AS impressions,
            COALESCE(SUM(position * impressions), 0) AS weighted
        FROM seo_search_daily
        WHERE kind = 'site' AND day BETWEEN '" . e($from) . "' AND '" . e($to) . "'");

    $clicks = is_array($row) ? (int) $row['clicks'] : 0;
    $impressions = is_array($row) ? (int) $row['impressions'] : 0;

    return array(
        'clicks'      => $clicks,
        'impressions' => $impressions,
        'ctr'         => pg_gsc_ctr($clicks, $impressions),
        'position'    => ($impressions > 0) ? ((float) $row['weighted'] / $impressions) : 0.0,
    );
}

/**
 * Clicks and impressions per day of a range, every day present.
 *
 * @return array Y-m-d => array(clicks, impressions)
 */
function pg_gsc_daily($from, $to)
{
    $days = array();

    for ($time = strtotime($from . ' 12:00:00'); $time <= strtotime($to . ' 12:00:00'); $time += 86400) {
        $days[date('Y-m-d', $time)] = array('clicks' => 0, 'impressions' => 0);
    }

    $rows = db_items(
        "SELECT day, clicks, impressions
        FROM seo_search_daily
        WHERE kind = 'site' AND day BETWEEN '" . e($from) . "' AND '" . e($to) . "'");

    foreach ($rows as $row) {
        if (isset($days[$row['day']])) {
            $days[$row['day']] = array('clicks' => (int) $row['clicks'], 'impressions' => (int) $row['impressions']);
        }
    }

    return $days;
}

/**
 * The pages or the searches of a range, most clicks first.
 *
 * @param string $kind 'page' or 'query'
 * @return array name, clicks, impressions, ctr, position
 */
function pg_gsc_top($kind, $from, $to, $limit = 25)
{
    $column = ($kind === 'page') ? 'page' : 'query';

    $rows = db_items(
        "SELECT
            " . $column . " AS name,
            SUM(clicks) AS clicks,
            SUM(impressions) AS impressions,
            SUM(position * impressions) AS weighted
        FROM seo_search_daily
        WHERE kind = '" . (($kind === 'page') ? 'page' : 'query') . "' AND day BETWEEN '" . e($from) . "' AND '" . e($to) . "'
        GROUP BY " . $column . "
        ORDER BY clicks DESC, impressions DESC
        LIMIT " . max(1, (int) $limit));

    $top = array();

    foreach ($rows as $row) {
        $impressions = (int) $row['impressions'];

        $top[] = array(
            'name'        => (string) $row['name'],
            'clicks'      => (int) $row['clicks'],
            'impressions' => $impressions,
            'ctr'         => pg_gsc_ctr($row['clicks'], $impressions),
            'position'    => ($impressions > 0) ? ((float) $row['weighted'] / $impressions) : 0.0,
        );
    }

    return $top;
}
