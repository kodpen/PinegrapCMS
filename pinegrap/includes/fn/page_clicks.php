<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Clicks and scroll depth (2026.11.0, 11.0.224): which links and buttons the
 * visitors of a page clicked and how far down they came, in the day rollup
 * page_click_daily.
 *
 * A public page drawn for a visitor sends one beacon a page view to
 * page_view.php, travelling with the page speed figures when both are on:
 * the page id, its day signature (pg_page_clicks_sign()) and a JSON part
 * with the device class, the deepest tenth of the page the window's bottom
 * reached and at most PG_PAGE_CLICKS_BEACON elements clicked, each named by
 * four parts (the part of the page, link or button, the address, the label).
 * page_view.php drops it for staff, a signed-in member, a crawler or a
 * visitor who refused statistics in the cookie notice, checks the signature, folds every
 * address and label again (pg_page_clicks_fold_url(),
 * pg_page_clicks_fold_label()) and raises one row per page, day and device
 * of each kind with one INSERT ... ON DUPLICATE KEY UPDATE: the views, the
 * deepest tenth reached, one row per element and, past the bound of
 * PG_PAGE_CLICKS_ELEMENTS elements a page and day, one "other" row. No
 * cookie, no identifier, nothing about the visitor is kept.
 *
 * The general job removes days older than PG_PAGE_CLICKS_DAYS once a day.
 * The rollup is derived data: a wrong day is deleted, never repaired.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_FUNCTIONS_DIR')) {
    exit;
}

/**
 * Days the rollup keeps.
 */
define('PG_PAGE_CLICKS_DAYS', 400);

/**
 * Beacons one address may send in a minute; the rest are dropped.
 */
define('PG_PAGE_CLICKS_RATE_LIMIT', 30);

/**
 * Different elements a page keeps rows for in a day (the three devices
 * together); clicks on further ones count in the "other" row.
 */
define('PG_PAGE_CLICKS_ELEMENTS', 200);

/**
 * Elements one beacon may name.
 */
define('PG_PAGE_CLICKS_BEACON', 20);

/**
 * Characters a label keeps.
 */
define('PG_PAGE_CLICKS_LABEL', 60);

/**
 * Characters an address keeps.
 */
define('PG_PAGE_CLICKS_URL', 255);

/**
 * The state file of the daily sweep, under data/temp.
 */
define('PG_PAGE_CLICKS_SWEEP_STATE', 'page_clicks_sweep.json');

/**
 * True when the 2026.11.0 step (11.0.224) has created page_click_daily.
 *
 * @return bool
 */
function pg_page_clicks_ready()
{
    return pg_schema_has('page_click_daily', 'elements');
}

/**
 * The settings text of the "Clicks and scrolling" switch: '1' on, missing
 * off, so every site and a new install start off.
 */
define('PG_PAGE_CLICKS_SWITCH', 'page_clicks');

/**
 * The switch's stored value for on or off: '1', or '' (the row removed).
 * Pure.
 *
 * @param bool $on
 * @return string
 */
function pg_page_clicks_switch_value($on)
{
    return $on ? '1' : '';
}

/**
 * Whether the "Clicks and scrolling" switch is on. The one reader: the
 * Visitors section, the Features card, the beacon script and the
 * designer's layer all ask here.
 *
 * @return bool
 */
function pg_page_clicks_switch_on()
{
    if (!function_exists('pg_config_text')) {
        require_once(PG_FUNCTIONS_DIR . '/includes/fn/config_text.php');
    }

    return pg_config_text(PG_PAGE_CLICKS_SWITCH) === '1';
}

/**
 * Turns the switch on or off. The one saver: the Visitors section's "Turn
 * on" button and the Features card call it. Whoever calls it has checked
 * the right to change Features and the form's token.
 *
 * @param bool $on
 * @return bool written
 */
function pg_page_clicks_switch_save($on)
{
    if (!function_exists('pg_config_text_save')) {
        require_once(PG_FUNCTIONS_DIR . '/includes/fn/config_text.php');
    }

    return (bool) pg_config_text_save(PG_PAGE_CLICKS_SWITCH, pg_page_clicks_switch_value((bool) $on));
}

/**
 * The parts of the page an element can sit in, by its nearest landmark.
 *
 * @return array
 */
function pg_page_clicks_areas()
{
    return array('header', 'nav', 'main', 'footer', 'aside', 'page');
}

/**
 * The kinds a messaging address is kept as, by its scheme or its host: of
 * such a link only the kind is kept, as it carries a number or a name.
 *
 * @return array 'schemes' => scheme => kind, 'hosts' => host => kind
 */
function pg_page_clicks_messaging()
{
    return array(
        'schemes' => array(
            'mailto' => 'mailto:', 'tel' => 'tel:', 'sms' => 'sms:', 'smsto' => 'sms:', 'mms' => 'sms:',
            'callto' => 'tel:', 'facetime' => 'facetime:', 'facetime-audio' => 'facetime:',
            'whatsapp' => 'whatsapp:', 'tg' => 'telegram:', 'viber' => 'viber:', 'skype' => 'skype:',
            'sgnl' => 'signal:', 'fb-messenger' => 'messenger:', 'line' => 'line:', 'msteams' => 'teams:',
        ),
        'hosts' => array(
            'wa.me' => 'whatsapp:', 'api.whatsapp.com' => 'whatsapp:', 'web.whatsapp.com' => 'whatsapp:',
            'chat.whatsapp.com' => 'whatsapp:', 'whatsapp.com' => 'whatsapp:', 'www.whatsapp.com' => 'whatsapp:',
            't.me' => 'telegram:', 'telegram.me' => 'telegram:', 'm.me' => 'messenger:',
            'signal.me' => 'signal:', 'line.me' => 'line:', 'invite.viber.com' => 'viber:',
        ),
    );
}

/**
 * Cuts a text to a number of characters (UTF-8). Pure.
 *
 * @param string $text
 * @param int    $length
 * @return string
 */
function pg_page_clicks_cut($text, $length)
{
    if (function_exists('mb_substr')) {
        return mb_substr($text, 0, $length, 'UTF-8');
    }

    return preg_match('/^.{0,' . (int) $length . '}/su', $text, $match) ? $match[0] : substr($text, 0, $length);
}

/**
 * A path part that looks like a code: more than 16 characters, letters and
 * digits mixed, no hyphen (the key of an unsubscribe, tracking or reset
 * link). Pure.
 *
 * @param string $part
 * @return bool
 */
function pg_page_clicks_code_like($part)
{
    return (strlen($part) > 16)
        && (strpos($part, '-') === false)
        && preg_match('/[A-Za-z]/', $part)
        && preg_match('/[0-9]/', $part);
}

/**
 * An element's address as it is kept: no query string or fragment; this
 * site's own address by its path, another site's as //host/path; of an
 * e-mail, phone, SMS or messaging link only its kind ("tel:"); every
 * code-like path part as "*"; cut at PG_PAGE_CLICKS_URL characters. '' for
 * no address, a script address or one the browser did not resolve. Pure.
 *
 * @param mixed  $url
 * @param string $site_host this site's host, lower case
 * @return string
 */
function pg_page_clicks_fold_url($url, $site_host = '')
{
    if (!is_string($url)) {
        return '';
    }

    // White space and control characters are no part of an address.
    $url = preg_replace('/[\x00-\x20\x7F]+/', '', $url);
    $messaging = pg_page_clicks_messaging();

    if (preg_match('/^([a-z][a-z0-9+.\-]*):/i', $url, $match)) {
        $scheme = strtolower($match[1]);

        if (isset($messaging['schemes'][$scheme])) {
            return $messaging['schemes'][$scheme];
        }

        if (($scheme !== 'http') && ($scheme !== 'https')) {
            return '';
        }
    } elseif (substr($url, 0, 2) !== '//' && substr($url, 0, 1) !== '/') {
        return '';
    }

    $url = preg_replace('/[?#].*$/s', '', $url);
    $host = '';

    if (preg_match('#^(?:[a-z][a-z0-9+.\-]*:)?//([^/]*)(.*)$#i', $url, $match)) {
        $host = strtolower(preg_replace('/^[^@]*@|:\d+$/', '', $match[1]));
        $path = $match[2];

        if ($host === '') {
            return '';
        }

        if (isset($messaging['hosts'][$host])) {
            return $messaging['hosts'][$host];
        }

        if ($host === strtolower((string) $site_host)) {
            $host = '';
        }
    } else {
        $path = $url;
    }

    $parts = explode('/', $path);

    foreach ($parts as $i => $part) {
        if (pg_page_clicks_code_like($part)) {
            $parts[$i] = '*';
        }
    }

    $path = implode('/', $parts);

    if ($host === '') {
        $out = ($path === '') ? '/' : $path;
    } else {
        $out = '//' . $host . $path;
    }

    return pg_page_clicks_cut($out, PG_PAGE_CLICKS_URL);
}

/**
 * An element's label as it is kept: control characters dropped, white space
 * folded, cut at PG_PAGE_CLICKS_LABEL characters; empty when it holds an
 * e-mail address or seven or more digits in a row (a phone, an order or
 * card number), and the element is then known by its address. Pure.
 *
 * @param mixed $label
 * @return string
 */
function pg_page_clicks_fold_label($label)
{
    if (!is_string($label) || !preg_match('//u', $label)) {
        return '';
    }

    $label = preg_replace('/[\x00-\x08\x0E-\x1F\x7F]+/', '', $label);
    $label = trim(preg_replace('/\s+/u', ' ', $label));

    if (preg_match('/[^\s@]+@[^\s@]+\.[^\s@]+/u', $label) || preg_match('/[0-9]{7,}/', $label)) {
        return '';
    }

    return trim(pg_page_clicks_cut($label, PG_PAGE_CLICKS_LABEL));
}

/**
 * The beacon's JSON part read back: device ('d'), the deepest tenth reached
 * ('t', 0-10, absent when not measured) and the elements ('e', each the four
 * parts: area, link or button, address, label). Every address and label is
 * folded; an element folded alike to an earlier one counts once, one with
 * neither address nor label is left out. Null when anything is out of
 * shape: the whole beacon is dropped. Pure.
 *
 * @param mixed  $raw
 * @param string $site_host
 * @return array|null device, tenth (int or null), elements (list of 4 strings)
 */
function pg_page_clicks_parse($raw, $site_host = '')
{
    if (!is_string($raw) || (strlen($raw) > 8192)) {
        return null;
    }

    $data = json_decode($raw, true);

    if (!is_array($data) || !isset($data['d']) || !in_array($data['d'], array('desktop', 'mobile', 'tablet'), true)) {
        return null;
    }

    $tenth = null;

    if (array_key_exists('t', $data) && ($data['t'] !== null)) {
        if (!is_int($data['t']) || ($data['t'] < 0) || ($data['t'] > 10)) {
            return null;
        }

        $tenth = $data['t'];
    }

    $list = isset($data['e']) ? $data['e'] : array();

    if (!is_array($list) || (count($list) > PG_PAGE_CLICKS_BEACON) || (array_values($list) !== $list)) {
        return null;
    }

    $elements = array();

    foreach ($list as $element) {
        if (!is_array($element) || (count($element) !== 4) || (array_values($element) !== $element)) {
            return null;
        }

        list($area, $role, $url, $label) = $element;

        if (!in_array($area, pg_page_clicks_areas(), true) || !in_array($role, array('link', 'button'), true)
            || !is_string($url) || (strlen($url) > 2048) || !is_string($label) || (strlen($label) > 1024)) {
            return null;
        }

        $url = pg_page_clicks_fold_url($url, $site_host);
        $label = pg_page_clicks_fold_label($label);

        if (($url === '') && ($label === '')) {
            continue;
        }

        $elements[$area . "\n" . $role . "\n" . $url . "\n" . $label] = array($area, $role, $url, $label);
    }

    return array('device' => $data['d'], 'tenth' => $tenth, 'elements' => array_values($elements));
}

/**
 * The row key: sha1 of its parts as they are kept, one per line (a folded
 * address or label holds no line break). Pure.
 *
 * @return string 40 hex characters
 */
function pg_page_clicks_key($day, $page_id, $device, $kind, $depth, $area, $role, $url, $label)
{
    return sha1(implode("\n", array((string) $day, (int) $page_id, (string) $device, (string) $kind, (int) $depth,
        (string) $area, (string) $role, (string) $url, (string) $label)));
}

/**
 * One row of the rollup with its key. Pure.
 *
 * @return array id, day, page_id, device, kind, depth, area, role, url, label
 */
function pg_page_clicks_row($day, $page_id, $device, $kind, $depth = 0, $area = '', $role = '', $url = '', $label = '')
{
    return array(
        'id' => pg_page_clicks_key($day, $page_id, $device, $kind, $depth, $area, $role, $url, $label),
        'day' => (string) $day,
        'page_id' => (int) $page_id,
        'device' => (string) $device,
        'kind' => (string) $kind,
        'depth' => (int) $depth,
        'area' => (string) $area,
        'role' => (string) $role,
        'url' => (string) $url,
        'label' => (string) $label,
    );
}

/**
 * The rows one beacon raises: its views row, its tenth's row when the depth
 * was measured, and a row per element. Pure.
 *
 * @param string $day
 * @param int    $page_id
 * @param array  $beacon pg_page_clicks_parse()
 * @return array of pg_page_clicks_row()
 */
function pg_page_clicks_rows($day, $page_id, array $beacon)
{
    $device = $beacon['device'];
    $rows = array(pg_page_clicks_row($day, $page_id, $device, 'view'));

    if (isset($beacon['tenth']) && ($beacon['tenth'] !== null)) {
        $rows[] = pg_page_clicks_row($day, $page_id, $device, 'depth', (int) $beacon['tenth']);
    }

    foreach ($beacon['elements'] as $element) {
        $rows[] = pg_page_clicks_row($day, $page_id, $device, 'element', 0, $element[0], $element[1], $element[2], $element[3]);
    }

    return $rows;
}

/**
 * The "other" row of a page, day and device: the clicks on elements past the
 * bound. Pure.
 *
 * @return array pg_page_clicks_row()
 */
function pg_page_clicks_other_row($day, $page_id, $device)
{
    return pg_page_clicks_row($day, $page_id, $device, 'other');
}

/**
 * Where an element's click goes: its own row when it already has one or the
 * page has fewer than PG_PAGE_CLICKS_ELEMENTS element rows that day, else the
 * "other" row. Pure.
 *
 * @param int  $counted the page's element rows of the day
 * @param bool $exists  the element already has its row
 * @return string 'element' or 'other'
 */
function pg_page_clicks_route($counted, $exists)
{
    return ($exists || ((int) $counted < PG_PAGE_CLICKS_ELEMENTS)) ? 'element' : 'other';
}

/**
 * The upsert of one row; id, day, page_id, device, kind, depth, area, role,
 * url and label are bound in that order. Pure.
 *
 * @return string
 */
function pg_page_clicks_upsert_sql()
{
    return 'INSERT INTO page_click_daily (id, day, page_id, device, kind, depth, area, role, url, label, hits, elements)'
        . ' VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1, 0)'
        . ' ON DUPLICATE KEY UPDATE hits = hits + 1';
}

/**
 * The signature of a page's beacon for one day, with the site's key; '' without
 * a key or a page. Pure.
 *
 * @param int    $page_id
 * @param string $key ENCRYPTION_KEY
 * @param string $day Y-m-d, the day the page was drawn
 * @return string
 */
function pg_page_clicks_sign($page_id, $key, $day)
{
    $page_id = (int) $page_id;
    $key = (string) $key;

    if (($page_id <= 0) || ($key === '') || !preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $day)) {
        return '';
    }

    return hash_hmac('sha256', 'page-clicks:' . $page_id . '|' . $day, $key);
}

/**
 * Whether a beacon's page id carries the signature this site gave it today or
 * yesterday (a page drawn before midnight or kept by the page cache); an
 * older one is refused. Pure.
 *
 * @param mixed  $page_id
 * @param mixed  $signature
 * @param string $key
 * @param int    $now timestamp, 0 for now
 * @return int the page id, 0 when it does not
 */
function pg_page_clicks_verify($page_id, $signature, $key, $now = 0)
{
    if (!is_string($page_id) || !ctype_digit($page_id) || (strlen($page_id) > 10) || !is_string($signature) || ($signature === '')) {
        return 0;
    }

    $now = ((int) $now > 0) ? (int) $now : time();

    foreach (array(date('Y-m-d', $now), date('Y-m-d', strtotime('-1 day', $now))) as $day) {
        $expected = pg_page_clicks_sign((int) $page_id, $key, $day);

        if (($expected !== '') && hash_equals($expected, $signature)) {
            return (int) $page_id;
        }
    }

    return 0;
}

/**
 * Whether one more beacon from this address would pass the per-minute limit
 * (PG_PAGE_CLICKS_RATE_LIMIT), in the firewall's rate buckets.
 *
 * @return bool
 */
function pg_page_clicks_rate_exceeded()
{
    if (!function_exists('waf_rate_exceeded') || !function_exists('waf_client_ip')) {
        return false;
    }

    $ip = waf_client_ip();

    if (function_exists('waf_ip_subject')) {
        $ip = waf_ip_subject($ip);
    }

    return waf_rate_exceeded($ip, 'clicks', PG_PAGE_CLICKS_RATE_LIMIT, 60);
}

/**
 * Whether this request may count: no crawler or script, nobody signed in
 * (staff, or a member whose view can carry a name), and not a visitor who
 * refused statistics in the cookie notice (the one check of the anonymous
 * counts, pg_consent_statistics_not_refused()).
 *
 * @return bool
 */
function pg_page_clicks_allowed()
{
    if (function_exists('pg_request_is_automated') && pg_request_is_automated()) {
        return false;
    }

    if (defined('USER_LOGGED_IN') && USER_LOGGED_IN) {
        return false;
    }

    return !function_exists('pg_consent_statistics_not_refused') || pg_consent_statistics_not_refused();
}

/**
 * Counts one beacon into the site day: the views row, the tenth's row and a
 * row per element, past the bound the "other" row. Nothing before the step
 * has run.
 *
 * @param int   $page_id a page this site signed
 * @param array $beacon  pg_page_clicks_parse()
 * @return bool true when the views row was raised
 */
function pg_page_clicks_record($page_id, array $beacon)
{
    $page_id = (int) $page_id;

    if (($page_id <= 0) || !pg_page_clicks_ready()) {
        return false;
    }

    $day = date('Y-m-d');
    $rows = pg_page_clicks_rows($day, $page_id, $beacon);
    $sql = pg_page_clicks_upsert_sql();
    $counted = null;

    foreach ($rows as $row) {
        if ($row['kind'] === 'element') {
            if ($counted === null) {
                $views = array();

                foreach (array('desktop', 'mobile', 'tablet') as $device) {
                    $views[] = pg_page_clicks_key($day, $page_id, $device, 'view', 0, '', '', '', '');
                }

                $result = db_q('SELECT COALESCE(SUM(elements), 0) FROM page_click_daily WHERE id IN (?, ?, ?)', $views);
                $sum = ($result instanceof mysqli_result) ? mysqli_fetch_row($result) : null;
                $counted = $sum ? (int) $sum[0] : 0;
            }

            if (pg_page_clicks_route($counted, false) === 'other') {
                // Past the bound, an element with its row keeps counting;
                // a new one raises the "other" row.
                if ((db_q('UPDATE page_click_daily SET hits = hits + 1 WHERE id = ?', array($row['id'])) !== false)
                    && (mysqli_affected_rows(db::$con) > 0)) {
                    continue;
                }

                $row = pg_page_clicks_other_row($day, $page_id, $row['device']);
            }
        }

        $result = db_q($sql, array($row['id'], $row['day'], $row['page_id'], $row['device'], $row['kind'],
            $row['depth'], $row['area'], $row['role'], $row['url'], $row['label']));

        if ($result === false) {
            if ($row['kind'] === 'view') {
                return false;
            }

            continue;
        }

        // One affected row is a new row: the page's element count grows.
        if (($row['kind'] === 'element') && (mysqli_affected_rows(db::$con) === 1)) {
            db_q('UPDATE page_click_daily SET elements = elements + 1 WHERE id = ?', array($rows[0]['id']));
            $counted++;
        }
    }

    return true;
}

/**
 * page_view.php's clicks part: the rate limit, who may count, the day
 * signature and the shape; then the beacon is counted. Silent either way.
 *
 * @param array $post cp (page id), cs (signature), c (the JSON part)
 * @return bool true when counted
 */
function pg_page_clicks_beacon(array $post)
{
    if (pg_page_clicks_rate_exceeded() || !pg_page_clicks_allowed()) {
        return false;
    }

    $page_id = pg_page_clicks_verify($post['cp'] ?? '', $post['cs'] ?? '', defined('ENCRYPTION_KEY') ? (string) ENCRYPTION_KEY : '');
    $host = strtolower(preg_replace('/:\d+$/', '', (string) ($_SERVER['HTTP_HOST'] ?? '')));
    $beacon = pg_page_clicks_parse($post['c'] ?? '', $host);

    return ($page_id > 0) && ($beacon !== null) && pg_page_clicks_record($page_id, $beacon);
}

// ── The script on the page ─────────────────────────────────────────────────

/**
 * Whether a drawn page carries the clicks script (C11): the switch on, an
 * HTML page in a folder open to everyone, drawn for a visitor who is not
 * signed in (a member's view can carry a name, C4), and none of the speed
 * beacon's exclusions (the panel bar, the File Manager preview, a
 * background render, staff). The designer and the prototype draw through
 * other entry points and never reach this. Pure.
 *
 * @param array $draw      rendered_html_page, toolbar, panel_preview,
 *                         background, staff, signed_in, public
 * @param bool  $switch_on pg_page_clicks_switch_on()
 * @return bool
 */
function pg_page_clicks_beacon_wanted(array $draw, $switch_on)
{
    return (bool) $switch_on
        && !empty($draw['rendered_html_page'])
        && !empty($draw['public'])
        && empty($draw['signed_in'])
        && empty($draw['toolbar'])
        && empty($draw['panel_preview'])
        && empty($draw['background'])
        && empty($draw['staff']);
}

/**
 * The script tag: pg_clicks.min.js deferred, with the endpoint, the page id
 * and its day signature as data attributes. '' without a signature or a
 * script address. Pure.
 *
 * @param string $script_url
 * @param string $endpoint   page_view.php
 * @param int    $page_id
 * @param string $signature  pg_page_clicks_sign()
 * @return string
 */
function pg_page_clicks_beacon_tag($script_url, $endpoint, $page_id, $signature)
{
    if (((string) $script_url === '') || ((string) $signature === '') || ((int) $page_id <= 0)) {
        return '';
    }

    return '<script src="' . h($script_url) . '" defer data-u="' . h($endpoint) . '" data-p="' . (int) $page_id . '" data-s="' . h($signature) . '"></script>';
}

/**
 * Splices the clicks script into a drawn page before its last </body>; the
 * page unchanged when it is not wanted, the rollup is not there, the site
 * has no key or the page is no HTML document. Before the page cache, so the
 * stored copy counts its visits too.
 *
 * @param string $content
 * @param int    $page_id
 * @param array  $draw    pg_page_clicks_beacon_wanted()
 * @return string
 */
function pg_clicks_beacon_inject($content, $page_id, array $draw)
{
    $content = (string) $content;
    $position = strripos($content, '</body>');

    if (($position === false) || !pg_page_clicks_beacon_wanted($draw, pg_page_clicks_switch_on()) || !pg_page_clicks_ready()) {
        return $content;
    }

    $urls = pg_js_bundle_urls('assets/js/pg_clicks.min.js');

    $tag = pg_page_clicks_beacon_tag(
        $urls ? $urls[0] : '',
        (defined('PATH') ? PATH : '/') . (defined('SOFTWARE_DIRECTORY') ? SOFTWARE_DIRECTORY : 'pinegrap') . '/page_view.php',
        $page_id,
        pg_page_clicks_sign($page_id, defined('ENCRYPTION_KEY') ? (string) ENCRYPTION_KEY : '', date('Y-m-d')));

    if ($tag === '') {
        return $content;
    }

    return substr($content, 0, $position) . $tag . substr($content, $position);
}

// ── Sweep ──────────────────────────────────────────────────────────────────

/**
 * The first day the rollup keeps: PG_PAGE_CLICKS_DAYS before $now. Pure.
 *
 * @param int $now timestamp, 0 for now
 * @return string Y-m-d
 */
function pg_page_clicks_keep_from($now = 0)
{
    $now = ((int) $now > 0) ? (int) $now : time();

    return date('Y-m-d', strtotime('-' . PG_PAGE_CLICKS_DAYS . ' days', $now));
}

/**
 * Where the daily sweep keeps when it last finished.
 *
 * @return string
 */
function pg_page_clicks_sweep_file()
{
    return PG_FUNCTIONS_DIR . '/data/temp/' . PG_PAGE_CLICKS_SWEEP_STATE;
}

/**
 * Whether the daily sweep is owed: it has not run in the last day.
 *
 * @return bool
 */
function pg_page_clicks_sweep_due()
{
    if (!pg_page_clicks_ready()) {
        return false;
    }

    $state = @json_decode((string) @file_get_contents(pg_page_clicks_sweep_file()), true);
    $done = (is_array($state) && isset($state['done_at'])) ? (int) $state['done_at'] : 0;

    return $done < (time() - 86400);
}

/**
 * The daily pass (job.php): the days older than PG_PAGE_CLICKS_DAYS go, kind
 * by kind so idx_kind_day_page serves each delete, five thousand rows at a
 * time within the time budget. Writes the state file when through.
 *
 * @param int $seconds
 * @return int rows removed
 */
function pg_page_clicks_sweep($seconds = 5)
{
    if (!pg_page_clicks_ready()) {
        return 0;
    }

    $removed = 0;
    $deadline = microtime(true) + max(1, (int) $seconds);
    $keep_from = pg_page_clicks_keep_from();
    $kinds = array('view', 'depth', 'element', 'other');

    while ($kinds && (microtime(true) < $deadline)) {
        if (db_q('DELETE FROM page_click_daily WHERE kind = ? AND day < ? LIMIT 5000', array($kinds[0], $keep_from)) === false) {
            return $removed;
        }

        $affected = max(0, (int) mysqli_affected_rows(db::$con));
        $removed += $affected;

        if ($affected < 5000) {
            array_shift($kinds);
        }
    }

    if (!$kinds) {
        $file = pg_page_clicks_sweep_file();

        if (!is_dir(dirname($file))) {
            @mkdir(dirname($file), 0755, true);
        }

        @file_put_contents($file, json_encode(array('done_at' => time())), LOCK_EX);
    }

    return $removed;
}

// ── Figures ────────────────────────────────────────────────────────────────

/**
 * The WHERE of a period and, optionally, one device. Pure.
 *
 * @param string $from   Y-m-d, first day
 * @param string $to     Y-m-d, last day
 * @param string $device '', 'desktop', 'mobile' or 'tablet'
 * @return string '' when a day is not a date
 */
function pg_page_clicks_where_sql($from, $to, $device = '')
{
    $pattern = '/^\d{4}-\d{2}-\d{2}$/';

    if (!preg_match($pattern, (string) $from) || !preg_match($pattern, (string) $to)) {
        return '';
    }

    $where = "day BETWEEN '" . $from . "' AND '" . $to . "'";

    if (in_array($device, array('desktop', 'mobile', 'tablet'), true)) {
        $where .= " AND device = '" . $device . "'";
    }

    return $where;
}

/**
 * The share of measured views that reached each tenth, 0 to 10, from the
 * views counted per deepest tenth: a view whose deepest tenth was n reached
 * every tenth up to n. Null for each tenth without measurements. Pure.
 *
 * @param array $depths tenth => views
 * @return array 0..10 => float|null
 */
function pg_page_clicks_reach(array $depths)
{
    $total = 0;

    foreach ($depths as $views) {
        $total += max(0, (int) $views);
    }

    $reach = array();
    $deeper = $total;

    for ($tenth = 0; $tenth <= 10; $tenth++) {
        $reach[$tenth] = $total ? round($deeper / $total, 4) : null;
        $deeper -= max(0, (int) ($depths[$tenth] ?? 0));
    }

    return $reach;
}

/**
 * The deepest tenth at least half of the measured views reached. Pure.
 *
 * @param array $reach pg_page_clicks_reach()
 * @return int|null
 */
function pg_page_clicks_half_tenth(array $reach)
{
    $half = null;

    foreach ($reach as $tenth => $share) {
        if (($share !== null) && ($share >= 0.5)) {
            $half = (int) $tenth;
        }
    }

    return $half;
}

/**
 * An element's clicks as a share of the views (0-1); each element counts
 * once a view, so it never passes 1. Null without views. Pure.
 *
 * @param int $clicks
 * @param int $views
 * @return float|null
 */
function pg_page_clicks_share($clicks, $views)
{
    if ((int) $views <= 0) {
        return null;
    }

    return round(min(1, max(0, (int) $clicks) / (int) $views), 4);
}

/**
 * The pages of a period with counted views, most viewed first: views, the
 * tenth half of them reached, and the page's name. Pages no longer there are
 * left out.
 *
 * @param string $from
 * @param string $to
 * @param string $device
 * @param int    $limit
 * @return array each page_id, page_name, views, half_tenth
 */
function pg_page_clicks_pages($from, $to, $device = '', $limit = 50)
{
    $where = pg_page_clicks_where_sql($from, $to, $device);

    if (($where === '') || !pg_page_clicks_ready()) {
        return array();
    }

    $where = str_replace(array('day ', 'device '), array('c.day ', 'c.device '), $where);

    $rows = db_items("SELECT c.page_id, MAX(p.page_name) AS page_name, SUM(c.hits) AS views"
        . " FROM page_click_daily AS c INNER JOIN page AS p ON p.page_id = c.page_id"
        . " WHERE c.kind = 'view' AND " . $where
        . " GROUP BY c.page_id ORDER BY views DESC, c.page_id LIMIT " . max(1, (int) $limit));

    $pages = array();

    foreach (is_array($rows) ? $rows : array() as $row) {
        $pages[(int) $row['page_id']] = array(
            'page_id' => (int) $row['page_id'],
            'page_name' => (string) $row['page_name'],
            'views' => (int) $row['views'],
            'half_tenth' => null,
        );
    }

    if (!$pages) {
        return array();
    }

    $depths = db_items("SELECT c.page_id, c.depth, SUM(c.hits) AS views FROM page_click_daily AS c"
        . " WHERE c.kind = 'depth' AND " . $where . " AND c.page_id IN (" . implode(',', array_keys($pages)) . ")"
        . " GROUP BY c.page_id, c.depth");
    $by_page = array();

    foreach (is_array($depths) ? $depths : array() as $row) {
        $by_page[(int) $row['page_id']][(int) $row['depth']] = (int) $row['views'];
    }

    foreach ($by_page as $page_id => $page_depths) {
        $pages[$page_id]['half_tenth'] = pg_page_clicks_half_tenth(pg_page_clicks_reach($page_depths));
    }

    return array_values($pages);
}

/**
 * One page's figures for a period: its views, the share reaching each tenth,
 * its elements most clicked first (each with its share of the views) and the
 * clicks counted in "other".
 *
 * @param int    $page_id
 * @param string $from
 * @param string $to
 * @param string $device
 * @param int    $limit elements listed
 * @return array views, reach, elements (area, role, url, label, clicks, share), other
 */
function pg_page_clicks_page($page_id, $from, $to, $device = '', $limit = PG_PAGE_CLICKS_ELEMENTS)
{
    $out = array('views' => 0, 'reach' => pg_page_clicks_reach(array()), 'elements' => array(), 'other' => 0);
    $where = pg_page_clicks_where_sql($from, $to, $device);

    if (($where === '') || ((int) $page_id <= 0) || !pg_page_clicks_ready()) {
        return $out;
    }

    $where = 'page_id = ' . (int) $page_id . ' AND ' . $where;
    $totals = db_items("SELECT kind, depth, SUM(hits) AS hits FROM page_click_daily"
        . " WHERE kind IN ('view', 'depth', 'other') AND " . $where . " GROUP BY kind, depth");
    $depths = array();

    foreach (is_array($totals) ? $totals : array() as $row) {
        if ($row['kind'] === 'view') {
            $out['views'] += (int) $row['hits'];
        } elseif ($row['kind'] === 'depth') {
            $depths[(int) $row['depth']] = (int) $row['hits'];
        } else {
            $out['other'] += (int) $row['hits'];
        }
    }

    $out['reach'] = pg_page_clicks_reach($depths);

    $elements = db_items("SELECT area, role, url, label, SUM(hits) AS clicks FROM page_click_daily"
        . " WHERE kind = 'element' AND " . $where
        . " GROUP BY area, role, url, label ORDER BY clicks DESC LIMIT " . max(1, (int) $limit));

    foreach (is_array($elements) ? $elements : array() as $row) {
        $out['elements'][] = array(
            'area' => (string) $row['area'],
            'role' => (string) $row['role'],
            'url' => (string) $row['url'],
            'label' => (string) $row['label'],
            'clicks' => (int) $row['clicks'],
            'share' => pg_page_clicks_share((int) $row['clicks'], $out['views']),
        );
    }

    return $out;
}
