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
 * The figures (pg_vitals_site(), pg_vitals_pages(), pg_vitals_trend()) read
 * the rollup with a 75th percentile estimated from the buckets and sums
 * (pg_vitals_p75()); GET /reports/vitals answers with them.
 *
 * The signature names the page and the day it was drawn (today and yesterday
 * are accepted), and one address may send PG_VITALS_RATE_LIMIT beacons a
 * minute. The general job removes days older than PG_VITALS_DAYS once a day.
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
 * Days the rollup keeps.
 */
define('PG_VITALS_DAYS', 400);

/**
 * Beacons one address may send in a minute; the rest are dropped.
 */
define('PG_VITALS_RATE_LIMIT', 30);

/**
 * The state file of the daily sweep, under data/temp.
 */
define('PG_VITALS_SWEEP_STATE', 'page_vitals_sweep.json');

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
 * The signature of a page's beacon for one day, with the site's key; '' without
 * a key or a page, and then the page carries no beacon. The day bounds how
 * long a signature copied out of a page can be replayed. Pure.
 *
 * @param int    $page_id
 * @param string $key ENCRYPTION_KEY
 * @param string $day Y-m-d, the day the page was drawn
 * @return string
 */
function pg_vitals_sign($page_id, $key, $day)
{
    $page_id = (int) $page_id;
    $key = (string) $key;

    if (($page_id <= 0) || ($key === '') || !preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $day)) {
        return '';
    }

    return hash_hmac('sha256', 'page-vitals:' . $page_id . '|' . $day, $key);
}

/**
 * Whether a beacon's page id carries the signature this site gave it today or
 * yesterday: a page drawn just before midnight, or kept by the page cache
 * (an hour at most), still reports the next day; an older signature does not.
 * Pure.
 *
 * @param mixed  $page_id
 * @param mixed  $signature
 * @param string $key
 * @param int    $now     timestamp, 0 for now
 * @return int the page id, 0 when it does not
 */
function pg_vitals_verify($page_id, $signature, $key, $now = 0)
{
    if (!is_string($page_id) || !ctype_digit($page_id) || (strlen($page_id) > 10) || !is_string($signature) || ($signature === '')) {
        return 0;
    }

    $now = ((int) $now > 0) ? (int) $now : time();

    foreach (array(date('Y-m-d', $now), date('Y-m-d', strtotime('-1 day', $now))) as $day) {
        $expected = pg_vitals_sign((int) $page_id, $key, $day);

        if (($expected !== '') && hash_equals($expected, $signature)) {
            return (int) $page_id;
        }
    }

    return 0;
}

/**
 * Whether one more beacon from this address would pass the per-minute limit
 * (PG_VITALS_RATE_LIMIT), counted in the firewall's rate buckets. A visitor
 * sends one beacon a page; a loop replaying a page's signature stops here.
 *
 * @return bool
 */
function pg_vitals_rate_exceeded()
{
    if (!function_exists('waf_rate_exceeded') || !function_exists('waf_client_ip')) {
        return false;
    }

    $ip = waf_client_ip();

    if (function_exists('waf_ip_subject')) {
        $ip = waf_ip_subject($ip);
    }

    return waf_rate_exceeded($ip, 'vitals', PG_VITALS_RATE_LIMIT, 60);
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
        pg_vitals_sign($page_id, defined('ENCRYPTION_KEY') ? (string) ENCRYPTION_KEY : '', date('Y-m-d')));

    if ($tag === '') {
        return $content;
    }

    return substr($content, 0, $position) . $tag . substr($content, $position);
}

/**
 * The first day the rollup keeps: PG_VITALS_DAYS before $now. Pure.
 *
 * @param int $now timestamp, 0 for now
 * @return string Y-m-d
 */
function pg_vitals_keep_from($now = 0)
{
    $now = ((int) $now > 0) ? (int) $now : time();

    return date('Y-m-d', strtotime('-' . PG_VITALS_DAYS . ' days', $now));
}

/**
 * Where the daily sweep keeps when it last finished.
 *
 * @return string
 */
function pg_vitals_sweep_file()
{
    return PG_FUNCTIONS_DIR . '/data/temp/' . PG_VITALS_SWEEP_STATE;
}

/**
 * Whether the daily sweep is owed: it has not run in the last day.
 *
 * @return bool
 */
function pg_vitals_sweep_due()
{
    if (!pg_vitals_ready()) {
        return false;
    }

    $state = @json_decode((string) @file_get_contents(pg_vitals_sweep_file()), true);
    $done = (is_array($state) && isset($state['done_at'])) ? (int) $state['done_at'] : 0;

    return $done < (time() - 86400);
}

/**
 * The daily pass (job.php): the days older than PG_VITALS_DAYS go, five
 * thousand rows at a time within the time budget. Writes the state file when
 * through.
 *
 * @param int $seconds
 * @return int rows removed
 */
function pg_vitals_sweep($seconds = 5)
{
    if (!pg_vitals_ready()) {
        return 0;
    }

    $removed = 0;
    $deadline = microtime(true) + max(1, (int) $seconds);
    $keep_from = pg_vitals_keep_from();

    while (microtime(true) < $deadline) {
        if (db_q("DELETE FROM page_vitals_hourly WHERE day < ? LIMIT 5000", array($keep_from)) === false) {
            return $removed;
        }

        $affected = max(0, (int) mysqli_affected_rows(db::$con));
        $removed += $affected;

        if ($affected < 5000) {
            $file = pg_vitals_sweep_file();

            if (!is_dir(dirname($file))) {
                @mkdir(dirname($file), 0755, true);
            }

            @file_put_contents($file, json_encode(array('done_at' => time())), LOCK_EX);
            break;
        }
    }

    return $removed;
}

// ── Figures ────────────────────────────────────────────────────────────────

/**
 * The 75th percentile of one figure, estimated from what a rollup row keeps:
 * the three bucket counts and the sum. The rank 0.75 × samples is placed in
 * its bucket and read off a uniform spread over that bucket's range. Good
 * runs from 0 and needs work over its thresholds; the open poor bucket gets
 * the mean the sum leaves for it (the other buckets taken at their middles)
 * as an exponential tail above its threshold. A bucket that holds every
 * sample takes its mean from the sum directly. Exact at the bucket edges,
 * close inside them.
 * Pure.
 *
 * @param string $metric 'lcp', 'inp' or 'cls'
 * @param int    $good
 * @param int    $needs
 * @param int    $poor
 * @param int    $sum
 * @return int|null ms, or CLS × 1000; null without samples
 */
function pg_vitals_p75($metric, $good, $needs, $poor, $sum)
{
    $thresholds = pg_vitals_thresholds();

    if (!isset($thresholds[$metric])) {
        return null;
    }

    list($t1, $t2) = $thresholds[$metric];
    $good = max(0, (int) $good);
    $needs = max(0, (int) $needs);
    $poor = max(0, (int) $poor);
    $sum = max(0, (float) $sum);
    $total = $good + $needs + $poor;

    if ($total === 0) {
        return null;
    }

    $rank = 0.75 * $total;

    // Where each bucket's values lie: [low, high].
    $good_high = $t1;
    $needs_low = $t1;
    $needs_high = $t2;

    if (($needs === 0) && ($poor === 0)) {
        $good_high = min($t1, 2 * ($sum / $good));
    } elseif (($good === 0) && ($poor === 0)) {
        $mean = min($t2, max($t1, $sum / $needs));
        $half = min($mean - $t1, $t2 - $mean);
        $needs_low = $mean - $half;
        $needs_high = $mean + $half;
    }

    if ($rank <= $good) {
        return (int) round($good_high * ($rank / $good));
    }

    if ($rank <= $good + $needs) {
        return (int) round($needs_low + ($needs_high - $needs_low) * (($rank - $good) / $needs));
    }

    // The poor tail as an exponential spread above the threshold, with the
    // mean the sum leaves for it: slow loads trail off rather than stop.
    $rest = $sum - ($good * $t1 / 2) - ($needs * ($t1 + $t2) / 2);
    $mean = max($t2, $rest / $poor);
    $share = min(0.999, ($rank - $good - $needs) / $poor);

    return (int) round($t2 + ($mean - $t2) * -log(1 - $share));
}

/**
 * The figures of one summed row: beacons, and per figure its samples, p75,
 * mean and the share of good samples (0-1, null without samples). Pure.
 *
 * @param array $row views and the <metric>_sum / _good / _needs / _poor sums
 * @return array views, lcp, inp, cls
 */
function pg_vitals_figures(array $row)
{
    $out = array('views' => (int) ($row['views'] ?? 0));

    foreach (array('lcp', 'inp', 'cls') as $metric) {
        $good = (int) ($row[$metric . '_good'] ?? 0);
        $needs = (int) ($row[$metric . '_needs'] ?? 0);
        $poor = (int) ($row[$metric . '_poor'] ?? 0);
        $sum = (float) ($row[$metric . '_sum'] ?? 0);
        $samples = $good + $needs + $poor;

        $out[$metric] = array(
            'samples' => $samples,
            'p75' => pg_vitals_p75($metric, $good, $needs, $poor, $sum),
            'mean' => $samples ? (int) round($sum / $samples) : null,
            'good_share' => $samples ? round($good / $samples, 4) : null,
            'needs_share' => $samples ? round($needs / $samples, 4) : null,
            'poor_share' => $samples ? round($poor / $samples, 4) : null,
        );
    }

    return $out;
}

/**
 * The SUM() columns of a figures query. Pure.
 *
 * @return string
 */
function pg_vitals_sum_columns_sql()
{
    $columns = array('SUM(views) AS views');

    foreach (array('lcp', 'inp', 'cls') as $metric) {
        foreach (array('sum', 'good', 'needs', 'poor') as $part) {
            $columns[] = 'SUM(' . $metric . '_' . $part . ') AS ' . $metric . '_' . $part;
        }
    }

    return implode(', ', $columns);
}

/**
 * The WHERE of a window and, optionally, one width class. Pure.
 *
 * @param string $from   Y-m-d, first day
 * @param string $to     Y-m-d, last day
 * @param string $device '', 'desktop', 'mobile' or 'tablet'
 * @return string '' when a day is not a date
 */
function pg_vitals_where_sql($from, $to, $device = '')
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
 * The site's figures over a window (pg_vitals_figures()).
 *
 * @param string $from   Y-m-d
 * @param string $to     Y-m-d
 * @param string $device '' for every width class
 * @return array
 */
function pg_vitals_site($from, $to, $device = '')
{
    $where = pg_vitals_where_sql($from, $to, $device);
    $row = (($where !== '') && pg_vitals_ready())
        ? db_item('SELECT ' . pg_vitals_sum_columns_sql() . ' FROM page_vitals_hourly WHERE ' . $where)
        : null;

    return pg_vitals_figures(is_array($row) ? $row : array());
}

/**
 * The orders a page list can be sorted by: the p75 of one figure, slowest
 * first, or the beacons, most first.
 *
 * @return array
 */
function pg_vitals_page_orders()
{
    return array('lcp', 'inp', 'cls', 'views');
}

/**
 * Sorts page figures for pg_vitals_pages(): by the p75 of the figure named,
 * highest first, pages without samples of it last; or by beacons. Ties go to
 * the page with more beacons, then the lower id. Pure.
 *
 * @param array  $pages each with page_id and pg_vitals_figures()
 * @param string $order a pg_vitals_page_orders() entry
 * @return array
 */
function pg_vitals_sort_pages(array $pages, $order)
{
    if (!in_array($order, pg_vitals_page_orders(), true)) {
        $order = 'lcp';
    }

    usort($pages, function ($a, $b) use ($order) {
        if ($order !== 'views') {
            $pa = $a[$order]['p75'];
            $pb = $b[$order]['p75'];

            if ($pa !== $pb) {
                if ($pa === null) {
                    return 1;
                }

                if ($pb === null) {
                    return -1;
                }

                return ($pb < $pa) ? -1 : 1;
            }
        }

        if ($a['views'] !== $b['views']) {
            return ($b['views'] < $a['views']) ? -1 : 1;
        }

        return ($a['page_id'] < $b['page_id']) ? -1 : 1;
    });

    return $pages;
}

/**
 * The pages of a window with their figures, the slowest first by $order, at
 * most $limit, with the page's name ('' for a page since deleted). Every page
 * of the window is summed in SQL; the p75 order is taken in PHP, since it is
 * no column.
 *
 * @param string $from
 * @param string $to
 * @param string $order  pg_vitals_page_orders()
 * @param int    $limit
 * @param string $device
 * @return array each page_id, page_name and pg_vitals_figures()
 */
function pg_vitals_pages($from, $to, $order = 'lcp', $limit = 10, $device = '')
{
    $where = pg_vitals_where_sql($from, $to, $device);

    if (($where === '') || !pg_vitals_ready()) {
        return array();
    }

    $rows = db_items('SELECT v.page_id, MAX(p.page_name) AS page_name, ' . str_replace('SUM(', 'SUM(v.', pg_vitals_sum_columns_sql())
        . ' FROM page_vitals_hourly AS v LEFT JOIN page AS p ON p.page_id = v.page_id'
        . ' WHERE ' . str_replace(array('day ', 'device '), array('v.day ', 'v.device '), $where)
        . ' GROUP BY v.page_id');

    $pages = array();

    foreach (is_array($rows) ? $rows : array() as $row) {
        $pages[] = array('page_id' => (int) $row['page_id'], 'page_name' => (string) $row['page_name']) + pg_vitals_figures($row);
    }

    return array_slice(pg_vitals_sort_pages($pages, $order), 0, max(1, (int) $limit));
}

/**
 * The site's figures per day of a window, days without beacons left out.
 *
 * @param string $from
 * @param string $to
 * @param string $device
 * @return array each day (Y-m-d) and pg_vitals_figures()
 */
function pg_vitals_trend($from, $to, $device = '')
{
    $where = pg_vitals_where_sql($from, $to, $device);

    if (($where === '') || !pg_vitals_ready()) {
        return array();
    }

    $rows = db_items('SELECT day, ' . pg_vitals_sum_columns_sql() . ' FROM page_vitals_hourly WHERE ' . $where . ' GROUP BY day ORDER BY day');
    $days = array();

    foreach (is_array($rows) ? $rows : array() as $row) {
        $days[] = array('day' => (string) $row['day']) + pg_vitals_figures($row);
    }

    return $days;
}
