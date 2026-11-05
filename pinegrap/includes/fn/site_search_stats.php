<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * The site search rollup (2026.11.0, 11.0.147): how often each term was
 * searched on a day, how many of those searches found nothing, and how many
 * times a result of it was clicked, in site_search_daily.
 *
 * A busy counter aggregated at write time: one row per day and term, keyed by
 * sha1(day|term), raised with one INSERT ... ON DUPLICATE KEY UPDATE. The term
 * is the visitor's text folded to one form (pg_site_search_term()), so "Kahve"
 * and "kahve " share a row. A visit counts a term once a day (the session
 * keeps the keys it counted), and a result click once a day per term and
 * address. Crawlers and scripts are not counted (pg_request_is_automated()).
 * The rollup holds no visitor data, so it counts whether visitor tracking is
 * on or not; the visit's own term list (pg_search_record_visit_term()) still
 * follows the switch.
 *
 * Searches are counted by the search results widget and the legacy Search
 * Results page; clicks by the widget's results, through page_view.php with a
 * term this site signed. The general job removes days older than
 * PG_SITE_SEARCH_DAYS once a day.
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
define('PG_SITE_SEARCH_DAYS', 400);

/**
 * The longest term kept, in characters (the widget reads at most 100).
 */
define('PG_SITE_SEARCH_TERM_MAX', 100);

/**
 * Keys a session remembers as counted; the oldest go past it.
 */
define('PG_SITE_SEARCH_SEEN_MAX', 200);

/**
 * The state file of the daily sweep, under data/temp.
 */
define('PG_SITE_SEARCH_SWEEP_STATE', 'site_search_sweep.json');

/**
 * True when the 2026.11.0 step (11.0.147) has created site_search_daily.
 *
 * @return bool
 */
function pg_site_search_stats_ready()
{
    return pg_schema_has('site_search_daily', 'clicks');
}

/**
 * A search text in the form the rollup keeps it: lower case (a dotted
 * capital İ becomes i, not i with a combining dot), one space between words,
 * no space at either end, at most PG_SITE_SEARCH_TERM_MAX characters. '' for
 * a text without a letter or a digit. Pure.
 *
 * @param mixed $query
 * @return string
 */
function pg_site_search_term($query)
{
    if (!is_scalar($query)) {
        return '';
    }

    $term = str_replace("\u{0130}", 'i', (string) $query);
    $term = (string) preg_replace('/[\s\x{00a0}]+/u', ' ', $term);
    $term = trim(mb_strtolower($term, 'UTF-8'));

    if (!preg_match('/[\p{L}\p{N}]/u', $term)) {
        return '';
    }

    if (mb_strlen($term, 'UTF-8') > PG_SITE_SEARCH_TERM_MAX) {
        $term = rtrim(mb_substr($term, 0, PG_SITE_SEARCH_TERM_MAX, 'UTF-8'));
    }

    return $term;
}

/**
 * The row key of a day and a term: sha1("Y-m-d|term"). Pure.
 *
 * @param string $day  Y-m-d
 * @param string $term pg_site_search_term()
 * @return string
 */
function pg_site_search_key($day, $term)
{
    return sha1((string) $day . '|' . (string) $term);
}

/**
 * Adds a key to the list a session keeps of what it counted. Returns
 * array(already_counted, list): a key already there leaves the list as it
 * is; a new one goes at the end, and past $max the oldest go. Pure.
 *
 * @param mixed  $seen
 * @param string $key
 * @param int    $max
 * @return array
 */
function pg_site_search_seen_add($seen, $key, $max = PG_SITE_SEARCH_SEEN_MAX)
{
    $seen = is_array($seen) ? array_values(array_filter($seen, 'is_string')) : array();

    if (in_array((string) $key, $seen, true)) {
        return array(true, $seen);
    }

    $seen[] = (string) $key;

    $max = max(1, (int) $max);

    if (count($seen) > $max) {
        $seen = array_slice($seen, -$max);
    }

    return array(false, $seen);
}

/**
 * True when this session counted $key before; marks it counted. Without a
 * session nothing can be remembered, and the key counts.
 *
 * @param string $key
 * @return bool
 */
function pg_site_search_seen($key)
{
    if (!isset($_SESSION) || !is_array($_SESSION)) {
        return false;
    }

    $list = isset($_SESSION['software']['site_search_seen']) ? $_SESSION['software']['site_search_seen'] : array();
    list($already, $list) = pg_site_search_seen_add($list, $key);
    $_SESSION['software']['site_search_seen'] = $list;

    return $already;
}

/**
 * Whether this request may count: the table is there, it is no crawler or
 * script, and it is no render of a page for the SEO pass or the search index.
 *
 * @return bool
 */
function pg_site_search_countable()
{
    if ((defined('UPDATE_SEARCH_INDEX') && UPDATE_SEARCH_INDEX === true)
        || (function_exists('pg_seo_rendering') && pg_seo_rendering())
        || (function_exists('pg_request_is_automated') && pg_request_is_automated())) {
        return false;
    }

    return pg_site_search_stats_ready();
}

/**
 * The statement that raises a day's counters of a term, its row added when
 * missing: searches by $searches, zero_results by $zero, clicks by $clicks.
 * Pure; the values are bound by db_q().
 *
 * @param int $searches
 * @param int $zero
 * @param int $clicks
 * @return string
 */
function pg_site_search_upsert_sql($searches, $zero, $clicks)
{
    $searches = max(0, (int) $searches);
    $zero = max(0, (int) $zero);
    $clicks = max(0, (int) $clicks);

    return "INSERT INTO site_search_daily (id, day, term, searches, zero_results, clicks)"
        . " VALUES (?, ?, ?, " . $searches . ", " . $zero . ", " . $clicks . ")"
        . " ON DUPLICATE KEY UPDATE"
        . " searches = searches + " . $searches . ","
        . " zero_results = zero_results + " . $zero . ","
        . " clicks = clicks + " . $clicks;
}

/**
 * Counts a site search: once a day per visit and term, as found nothing when
 * $results is 0.
 *
 * @param mixed $query   what the visitor searched for
 * @param int   $results how many results the search showed
 * @return bool true when a row was raised
 */
function pg_site_search_count($query, $results)
{
    $term = pg_site_search_term($query);

    if (($term === '') || !pg_site_search_countable()) {
        return false;
    }

    $day = date('Y-m-d');
    $key = pg_site_search_key($day, $term);

    if (pg_site_search_seen($key)) {
        return false;
    }

    return db_q(pg_site_search_upsert_sql(1, ((int) $results === 0) ? 1 : 0, 0), array($key, $day, $term)) !== false;
}

/**
 * Counts a click on a result of a search: once a day per visit, term and
 * address. The term is the one the results page signed.
 *
 * @param mixed $term
 * @param mixed $url the address of the result clicked
 * @return bool true when a row was raised
 */
function pg_site_search_click($term, $url)
{
    $term = pg_site_search_term($term);
    $url = is_string($url) ? substr($url, 0, 2048) : '';

    if (($term === '') || ($url === '') || !pg_site_search_countable()) {
        return false;
    }

    $day = date('Y-m-d');

    if (pg_site_search_seen(sha1('click|' . $day . '|' . $term . '|' . $url))) {
        return false;
    }

    return db_q(pg_site_search_upsert_sql(0, 0, 1), array(pg_site_search_key($day, $term), $day, $term)) !== false;
}

/**
 * The signature of a term the results page lets its clicks report; '' for
 * an empty term or without a key. Pure.
 *
 * @param string $term pg_site_search_term()
 * @param string $key  ENCRYPTION_KEY
 * @return string
 */
function pg_site_search_click_sign($term, $key)
{
    $term = (string) $term;
    $key = (string) $key;

    if (($term === '') || ($key === '')) {
        return '';
    }

    return hash_hmac('sha256', 'search-click:' . $term, $key);
}

/**
 * Whether a posted term carries the signature this site gave it. Pure.
 *
 * @param mixed  $term
 * @param mixed  $signature
 * @param string $key
 * @return bool
 */
function pg_site_search_click_verify($term, $signature, $key)
{
    if (!is_string($term) || !is_string($signature) || ($signature === '')) {
        return false;
    }

    $expected = pg_site_search_click_sign($term, $key);

    return ($expected !== '') && hash_equals($expected, $signature);
}

/**
 * The script a results page carries so a click on one of its results is
 * counted: a click on a link whose href is one of $urls posts the term, its
 * signature and the href to page_view.php with navigator.sendBeacon, or
 * fetch with keepalive where that is missing; the link opens as always. ''
 * without a term, a signature or a result. Pure.
 *
 * @param string $term
 * @param string $signature
 * @param array  $urls     the hrefs of the results shown
 * @param string $endpoint page_view.php
 * @return string
 */
function pg_site_search_click_script($term, $signature, array $urls, $endpoint)
{
    $urls = array_values(array_unique(array_filter(array_map('strval', $urls), function ($u) {
        return ($u !== '') && ($u !== '#');
    })));

    if (((string) $term === '') || ((string) $signature === '') || !$urls) {
        return '';
    }

    // Every < > & ' " is written as \uXXXX, so nothing in the strings can end
    // the script or the page.
    $flags = JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_SLASHES;
    $values = array();

    foreach (array((string) $endpoint, (string) $term, (string) $signature, $urls) as $value) {
        $json = json_encode($value, $flags);

        if (!is_string($json)) {
            return '';
        }

        $values[] = $json;
    }

    return '<script>(function(){var u=' . $values[0] . ',t=' . $values[1] . ',s=' . $values[2] . ',l=' . $values[3] . ';'
        . 'document.addEventListener("click",function(e){try{'
        . 'var a=e.target&&e.target.closest?e.target.closest("a[href]"):null;if(!a){return;}'
        . 'var h=a.getAttribute("href");if(l.indexOf(h)<0){return;}'
        . 'var d=new FormData();d.append("sc",t);d.append("ss",s);d.append("u",h);'
        . 'if(navigator.sendBeacon&&navigator.sendBeacon(u,d)){return;}'
        . 'if(window.fetch){fetch(u,{method:"POST",body:d,keepalive:true,credentials:"same-origin"});}'
        . '}catch(x){}},true);})();</script>';
}

/**
 * The click script for the results a search widget shows, or '' when the
 * rollup is not there, nothing was searched or the site has no key.
 *
 * @param mixed $query
 * @param array $urls
 * @return string
 */
function pg_site_search_click_script_for($query, array $urls)
{
    $term = pg_site_search_term($query);

    if (($term === '') || !pg_site_search_stats_ready()) {
        return '';
    }

    return pg_site_search_click_script(
        $term,
        pg_site_search_click_sign($term, defined('ENCRYPTION_KEY') ? (string) ENCRYPTION_KEY : ''),
        $urls,
        (defined('PATH') ? PATH : '/') . (defined('SOFTWARE_DIRECTORY') ? SOFTWARE_DIRECTORY : 'pinegrap') . '/page_view.php'
    );
}

/**
 * The first day the rollup keeps (Y-m-d): older days are past the retention
 * period. Days are stepped with strtotime(), so a DST change moves nothing.
 * Pure.
 *
 * @param int $now
 * @return string
 */
function pg_site_search_keep_from($now = 0)
{
    $now = ((int) $now > 0) ? (int) $now : time();

    return date('Y-m-d', strtotime('-' . PG_SITE_SEARCH_DAYS . ' days', $now));
}

/**
 * Where the daily sweep keeps when it last finished.
 *
 * @return string
 */
function pg_site_search_sweep_file()
{
    return PG_FUNCTIONS_DIR . '/data/temp/' . PG_SITE_SEARCH_SWEEP_STATE;
}

/**
 * Whether the daily sweep is owed: it has not run in the last day.
 *
 * @return bool
 */
function pg_site_search_sweep_due()
{
    if (!pg_site_search_stats_ready()) {
        return false;
    }

    $state = @json_decode((string) @file_get_contents(pg_site_search_sweep_file()), true);
    $done = (is_array($state) && isset($state['done_at'])) ? (int) $state['done_at'] : 0;

    return $done < (time() - 86400);
}

/**
 * The daily pass (job.php): the days older than PG_SITE_SEARCH_DAYS go, five
 * thousand rows at a time within the time budget. Writes the state file when
 * through.
 *
 * @param int $seconds
 * @return int rows removed
 */
function pg_site_search_sweep($seconds = 5)
{
    if (!pg_site_search_stats_ready()) {
        return 0;
    }

    $removed = 0;
    $deadline = microtime(true) + max(1, (int) $seconds);
    $keep_from = pg_site_search_keep_from();

    while (microtime(true) < $deadline) {
        if (db_q("DELETE FROM site_search_daily WHERE day < ? LIMIT 5000", array($keep_from)) === false) {
            return $removed;
        }

        $affected = max(0, (int) mysqli_affected_rows(db::$con));
        $removed += $affected;

        if ($affected < 5000) {
            $file = pg_site_search_sweep_file();

            if (!is_dir(dirname($file))) {
                @mkdir(dirname($file), 0755, true);
            }

            @file_put_contents($file, json_encode(array('done_at' => time())), LOCK_EX);
            break;
        }
    }

    return $removed;
}

/**
 * The periods the former report screen offered, in days; its old links are
 * sent on to the overview (pg_site_search_overview_query()).
 *
 * @return array
 */
function pg_site_search_periods()
{
    return array(7, 30, 90, 365);
}

/**
 * The former report's period from the request: one of
 * pg_site_search_periods(), else 30. Pure.
 *
 * @param mixed $raw
 * @return int
 */
function pg_site_search_period($raw)
{
    $days = is_scalar($raw) ? (int) $raw : 0;

    return in_array($days, pg_site_search_periods(), true) ? $days : 30;
}

/**
 * The first day (Y-m-d) of a period of $days days that ends today. Pure.
 *
 * @param int $days
 * @param int $now
 * @return string
 */
function pg_site_search_period_start($days, $now = 0)
{
    $now = ((int) $now > 0) ? (int) $now : time();
    $days = max(1, (int) $days);

    return date('Y-m-d', strtotime('-' . ($days - 1) . ' days', $now));
}

/**
 * The Visitors overview query that shows the period of an old report link
 * (`days`): a preset where the overview has one, else the same days as a
 * custom range ending today. Pure.
 *
 * @param mixed $raw the old `days` value
 * @param int   $now
 * @return array query parameters for visitors.php
 */
function pg_site_search_overview_query($raw, $now = 0)
{
    $days = pg_site_search_period($raw);
    $presets = array(7 => '7d', 30 => '30d', 90 => '90d');

    if (isset($presets[$days])) {
        return array('p' => $presets[$days]);
    }

    $now = ((int) $now > 0) ? (int) $now : time();

    return array('p' => 'custom', 'from' => pg_site_search_period_start($days, $now), 'to' => date('Y-m-d', $now));
}

/**
 * The totals of a period: searches, zero_results, clicks and the number of
 * terms. Rows are summed, never counted as searches. Pure; the first and the
 * last day (Y-m-d, both included) are bound.
 *
 * @return string
 */
function pg_site_search_summary_sql()
{
    return "SELECT COALESCE(SUM(searches), 0) AS searches, COALESCE(SUM(zero_results), 0) AS zero_results,"
        . " COALESCE(SUM(clicks), 0) AS clicks, COUNT(DISTINCT term) AS terms"
        . " FROM site_search_daily WHERE day >= ? AND day <= ?";
}

/**
 * The terms of a period, summed: 'top' by searches, 'zero' only those that
 * found nothing at least once, by those searches. Pure; the first and the
 * last day are bound.
 *
 * @param string $kind 'top' or 'zero'
 * @param int    $limit
 * @return string
 */
function pg_site_search_terms_sql($kind, $limit = 100)
{
    $order = ($kind === 'zero') ? 'zero_results' : 'searches';

    return "SELECT term, SUM(searches) AS searches, SUM(zero_results) AS zero_results, SUM(clicks) AS clicks"
        . " FROM site_search_daily WHERE day >= ? AND day <= ?"
        . " GROUP BY term"
        . (($kind === 'zero') ? " HAVING SUM(zero_results) > 0" : "")
        . " ORDER BY " . $order . " DESC, term ASC"
        . " LIMIT " . max(1, min(1000, (int) $limit));
}

/**
 * Clicks per search as a percentage with one decimal, or null when nothing
 * was searched. Several results of one search can be clicked, so it can pass
 * 100. Pure.
 *
 * @param int $clicks
 * @param int $searches
 * @return float|null
 */
function pg_site_search_click_rate($clicks, $searches)
{
    $searches = (int) $searches;

    return ($searches > 0) ? round(100 * max(0, (int) $clicks) / $searches, 1) : null;
}

/**
 * The figures of a period: the totals, the 100 most searched terms and the
 * 100 terms that found nothing most often.
 *
 * @param string $from first day, Y-m-d
 * @param string $to   last day, Y-m-d, included
 * @return array summary, top, zero
 */
function pg_site_search_figures($from, $to)
{
    $read = function ($sql) use ($from, $to) {
        $rows = array();
        $result = db_q($sql, array((string) $from, (string) $to));

        while ($result && ($row = mysqli_fetch_assoc($result))) {
            $rows[] = $row;
        }

        return $rows;
    };

    $summary = $read(pg_site_search_summary_sql());

    return array(
        'summary' => $summary ? $summary[0] : array('searches' => 0, 'zero_results' => 0, 'clicks' => 0, 'terms' => 0),
        'top'     => $read(pg_site_search_terms_sql('top', 100)),
        'zero'    => $read(pg_site_search_terms_sql('zero', 100)),
    );
}

/**
 * The site search report: the period's tiles (searches, terms, no results,
 * result clicks, clicks per search), the top terms and the terms that found
 * nothing, each of these with a button that opens the Search Tuning card
 * with a line for it when $may_tune. Each list shows its first
 * PG_VISITORS_LIST_ROWS terms, the rest behind "Show all". Pure.
 *
 * @param array $figures  pg_site_search_figures()
 * @param bool  $may_tune the viewer may change site settings (role < 3)
 * @return string HTML
 */
function pg_site_search_report(array $figures, $may_tune)
{
    $summary = isset($figures['summary']) ? $figures['summary'] : array();
    $searches_total = isset($summary['searches']) ? (int) $summary['searches'] : 0;

    $rate_text = function ($clicks, $searches) {
        $rate = pg_site_search_click_rate($clicks, $searches);

        return ($rate === null) ? '-' : lang(array('string' => '{var:1}%', 'vars' => array(pg_format_number($rate, 1))));
    };

    $tiles = '';

    foreach (array(
        array(lang('Searches'), pg_format_number($searches_total, 0)),
        array(lang('Search terms'), pg_format_number(isset($summary['terms']) ? (int) $summary['terms'] : 0, 0)),
        array(lang('No results'), pg_format_number(isset($summary['zero_results']) ? (int) $summary['zero_results'] : 0, 0)),
        array(lang('Result clicks'), pg_format_number(isset($summary['clicks']) ? (int) $summary['clicks'] : 0, 0)),
        array(lang('Clicks per search'), $rate_text(isset($summary['clicks']) ? $summary['clicks'] : 0, $searches_total)),
    ) as $tile) {
        $tiles .= '
            <div class="pg-visitors-tile">
                <div class="pg-visitors-tile-label">' . h($tile[0]) . '</div>
                <div class="pg-visitors-tile-value">' . h($tile[1]) . '</div>
            </div>';
    }

    $count = function ($value) {
        return array('html' => pg_format_number((int) $value, 0), 'sort' => (int) $value);
    };

    $top_rows = array();

    foreach (isset($figures['top']) ? $figures['top'] : array() as $row) {
        $top_rows[] = array(
            (string) $row['term'],
            $count($row['searches']),
            $count($row['zero_results']),
            $count($row['clicks']),
            $rate_text($row['clicks'], (int) $row['searches']),
        );
    }

    $zero_rows = array();

    foreach (isset($figures['zero']) ? $figures['zero'] : array() as $row) {
        $cells = array((string) $row['term'], $count($row['zero_results']), $count($row['searches']));

        if ($may_tune) {
            $url = OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/settings_features.php?search_promote=' . rawurlencode((string) $row['term']) . '#pgset-search-tuning';
            $cells[] = array('html' => '<a class="btn btn-sm btn-text theme-primary" href="' . h($url) . '" title="' . h(lang('Promote a result')) . '" aria-label="' . h(lang('Promote a result')) . '"><i class="bi bi-pin-angle" aria-hidden="true"></i></a>');
        }

        $zero_rows[] = $cells;
    }

    // Drawn as the Visitors overview's blocks and tables
    // (includes/visitors/shell.php), each list cut to its first rows.
    $block = function ($title, array $headings, array $rows, $empty) {
        return pg_visitors_block($title, $rows
            ? pg_visitors_table($headings, $rows, array('caption' => $title, 'limit' => PG_VISITORS_LIST_ROWS))
            : pg_visitors_empty($empty), 'is-half');
    };

    $zero_headings = array(lang('Term'), array('label' => lang('No results'), 'end' => true), array('label' => lang('Searches'), 'end' => true));

    if ($may_tune) {
        $zero_headings[] = array('label' => lang('Action'), 'end' => true);
    }

    return '<div class="pg-visitors-tiles">' . $tiles . '</div>
    <div class="pg-visitors-grid is-split">
            ' . $block(lang('Most searched terms'),
                array(lang('Term'), array('label' => lang('Searches'), 'end' => true), array('label' => lang('No results'), 'end' => true), array('label' => lang('Result clicks'), 'end' => true), array('label' => lang('Clicks per search'), 'end' => true)),
                $top_rows, lang('Nothing was searched for in this period.')) . '
            ' . $block(lang('Searches without results'), $zero_headings, $zero_rows, lang('Every search in this period found something.')) . '
    </div>';
}
