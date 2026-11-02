<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Real-user page speed (2026.11.0, 11.0.149): the Largest Contentful Paint,
 * Interaction to Next Paint and Cumulative Layout Shift a visitor's browser
 * measured on a page, in the hourly rollup page_vitals_hourly.
 *
 * A public page drawn for a visitor carries pg_vitals.min.js
 * (pg_vitals_beacon_tag(), printed by get_page.php), which sends the three
 * figures once to page_view.php with the page id and the page's signature.
 * page_view.php checks the signature, validates the figures
 * (pg_vitals_parse()) and raises one row per hour, page and width class with
 * one INSERT ... ON DUPLICATE KEY UPDATE: a count, the sums and the
 * good / needs-work / poor buckets of each figure by Google's thresholds.
 * Nothing about the visitor is kept, so the beacon runs whether visitor
 * tracking is on or not. Crawlers and scripts are not counted.
 *
 * The rollup is derived data: a wrong bucket is truncated and refilled by the
 * hours that follow, never fixed in place.
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
 * True when the 2026.11.0 step (11.0.149) has created page_vitals_hourly.
 *
 * @return bool
 */
function pg_vitals_ready()
{
    return pg_schema_has('page_vitals_hourly', 'cls_poor');
}

/**
 * The thresholds of each figure: at most the first is good, above the second
 * is poor, between them needs work. LCP and INP in milliseconds, CLS × 1000.
 *
 * @return array 'lcp'|'inp'|'cls' => array(good, poor)
 */
function pg_vitals_thresholds()
{
    return array(
        'lcp' => array(2500, 4000),
        'inp' => array(200, 500),
        'cls' => array(100, 250),
    );
}

/**
 * The largest value each figure may take: LCP 60 s, INP 10 s, CLS 10 (× 1000).
 * A value above it is no measurement and drops the whole beacon.
 *
 * @return array 'lcp'|'inp'|'cls' => int
 */
function pg_vitals_limits()
{
    return array('lcp' => 60000, 'inp' => 10000, 'cls' => 10000);
}

/**
 * The bucket a figure falls into. Pure.
 *
 * @param string $metric 'lcp', 'inp' or 'cls'
 * @param int    $value  ms, or CLS × 1000
 * @return string 'good', 'needs' or 'poor'; '' for an unknown metric
 */
function pg_vitals_bucket($metric, $value)
{
    $thresholds = pg_vitals_thresholds();

    if (!isset($thresholds[$metric])) {
        return '';
    }

    $value = (int) $value;

    if ($value <= $thresholds[$metric][0]) {
        return 'good';
    }

    return ($value <= $thresholds[$metric][1]) ? 'needs' : 'poor';
}

/**
 * The beacon's v= part read back: "lcp,inp,cls,device", each figure a whole
 * number or empty (not measured). Null when anything is out of shape or out
 * of range, or when no figure was measured. Pure.
 *
 * @param mixed $value
 * @return array|null 'lcp', 'inp', 'cls' (int, or null when not measured), 'device'
 */
function pg_vitals_parse($value)
{
    if (!is_string($value) || (strlen($value) > 64)) {
        return null;
    }

    $parts = explode(',', $value);

    if (count($parts) !== 4) {
        return null;
    }

    $device = $parts[3];

    if (!in_array($device, array('desktop', 'mobile', 'tablet'), true)) {
        return null;
    }

    $out = array('device' => $device);
    $limits = pg_vitals_limits();
    $measured = false;

    foreach (array('lcp', 'inp', 'cls') as $i => $metric) {
        $figure = $parts[$i];

        if ($figure === '') {
            $out[$metric] = null;
            continue;
        }

        if (!ctype_digit($figure) || (strlen($figure) > 6) || ((int) $figure > $limits[$metric])) {
            return null;
        }

        $out[$metric] = (int) $figure;
        $measured = true;
    }

    return $measured ? $out : null;
}

/**
 * The row key of an hour, page and width class: sha1("Y-m-d|H|page_id|device").
 * Pure.
 *
 * @param string $day    Y-m-d
 * @param int    $hour   0-23
 * @param int    $page_id
 * @param string $device
 * @return string 40 hex characters
 */
function pg_vitals_key($day, $hour, $page_id, $device)
{
    return sha1((string) $day . '|' . (int) $hour . '|' . (int) $page_id . '|' . (string) $device);
}

/**
 * The signature of a page's beacon with the site's key; '' without a key or
 * a page, and then the page carries no beacon. Pure.
 *
 * @param int    $page_id
 * @param string $key ENCRYPTION_KEY
 * @return string
 */
function pg_vitals_sign($page_id, $key)
{
    $page_id = (int) $page_id;
    $key = (string) $key;

    if (($page_id <= 0) || ($key === '')) {
        return '';
    }

    return hash_hmac('sha256', 'page-vitals:' . $page_id, $key);
}

/**
 * Whether a beacon's page id carries the signature this site gave it. Pure.
 *
 * @param mixed  $page_id
 * @param mixed  $signature
 * @param string $key
 * @return int the page id, 0 when it does not
 */
function pg_vitals_verify($page_id, $signature, $key)
{
    if (!is_string($page_id) || !ctype_digit($page_id) || (strlen($page_id) > 10) || !is_string($signature) || ($signature === '')) {
        return 0;
    }

    $expected = pg_vitals_sign((int) $page_id, $key);

    return (($expected !== '') && hash_equals($expected, $signature)) ? (int) $page_id : 0;
}

/**
 * The upsert of one beacon, with its figures written into the statement as
 * whole numbers; the id, day, hour, page and device are bound in that order.
 * A figure not measured adds nothing to its sum or buckets. Pure.
 *
 * @param array $vitals pg_vitals_parse()
 * @return string
 */
function pg_vitals_upsert_sql(array $vitals)
{
    $columns = array('views');
    $values = array(1);

    foreach (array('lcp', 'inp', 'cls') as $metric) {
        $figure = isset($vitals[$metric]) ? $vitals[$metric] : null;
        $bucket = ($figure === null) ? '' : pg_vitals_bucket($metric, $figure);

        $columns[] = $metric . '_sum';
        $values[] = ($figure === null) ? 0 : max(0, (int) $figure);

        foreach (array('good', 'needs', 'poor') as $name) {
            $columns[] = $metric . '_' . $name;
            $values[] = ($bucket === $name) ? 1 : 0;
        }
    }

    $updates = array();

    foreach ($columns as $i => $column) {
        if ($values[$i] !== 0) {
            $updates[] = $column . ' = ' . $column . ' + ' . $values[$i];
        }
    }

    return 'INSERT INTO page_vitals_hourly (id, day, hour, page_id, device, ' . implode(', ', $columns) . ')'
        . ' VALUES (?, ?, ?, ?, ?, ' . implode(', ', $values) . ')'
        . ' ON DUPLICATE KEY UPDATE ' . implode(', ', $updates);
}

/**
 * Counts one beacon into the current hour of the site's time zone. Nothing
 * before the step has run, or for crawlers and scripts.
 *
 * @param int   $page_id a page this site signed
 * @param array $vitals  pg_vitals_parse()
 * @return bool true when a row was raised
 */
function pg_vitals_record($page_id, array $vitals)
{
    $page_id = (int) $page_id;

    if (($page_id <= 0) || !pg_vitals_ready() || pg_request_is_automated()) {
        return false;
    }

    $day = date('Y-m-d');
    $hour = (int) date('G');

    return db_q(pg_vitals_upsert_sql($vitals),
        array(pg_vitals_key($day, $hour, $page_id, $vitals['device']), $day, $hour, $page_id, $vitals['device'])) !== false;
}

/**
 * Whether a drawn page gets the beacon: an HTML page drawn for a visitor, not
 * the panel's preview, a background render (the designer's and prototype's
 * frames, the SEO pass) or a page shown to staff (it carries the panel bar,
 * or the user may edit the site). Pure.
 *
 * @param array $draw rendered_html_page, toolbar, panel_preview, background, staff
 * @return bool
 */
function pg_vitals_beacon_wanted(array $draw)
{
    return !empty($draw['rendered_html_page'])
        && empty($draw['toolbar'])
        && empty($draw['panel_preview'])
        && empty($draw['background'])
        && empty($draw['staff']);
}

/**
 * The script tag of the beacon: pg_vitals.min.js deferred, with the endpoint,
 * the page id and its signature as data attributes. '' without a signature or
 * a script address. Pure.
 *
 * @param string $script_url
 * @param string $endpoint   page_view.php
 * @param int    $page_id
 * @param string $signature  pg_vitals_sign()
 * @return string
 */
function pg_vitals_beacon_tag($script_url, $endpoint, $page_id, $signature)
{
    if (((string) $script_url === '') || ((string) $signature === '') || ((int) $page_id <= 0)) {
        return '';
    }

    return '<script src="' . h($script_url) . '" defer data-u="' . h($endpoint) . '" data-p="' . (int) $page_id . '" data-s="' . h($signature) . '"></script>';
}

/**
 * Splices the beacon into a drawn page before its last </body>; the page
 * unchanged when it is not wanted, the rollup is not there, the site has no
 * key or the page is no HTML document. The page cache stores the page with
 * it, so a visit answered from the cache is measured too.
 *
 * @param string $content
 * @param int    $page_id
 * @param array  $draw    pg_vitals_beacon_wanted()
 * @return string
 */
function pg_vitals_beacon_inject($content, $page_id, array $draw)
{
    $content = (string) $content;
    $position = strripos($content, '</body>');

    if (($position === false) || !pg_vitals_beacon_wanted($draw) || !pg_vitals_ready()) {
        return $content;
    }

    $urls = pg_js_bundle_urls('assets/js/pg_vitals.min.js');

    $tag = pg_vitals_beacon_tag(
        $urls ? $urls[0] : '',
        (defined('PATH') ? PATH : '/') . (defined('SOFTWARE_DIRECTORY') ? SOFTWARE_DIRECTORY : 'pinegrap') . '/page_view.php',
        $page_id,
        pg_vitals_sign($page_id, defined('ENCRYPTION_KEY') ? (string) ENCRYPTION_KEY : ''));

    if ($tag === '') {
        return $content;
    }

    return substr($content, 0, $position) . $tag . substr($content, $position);
}
