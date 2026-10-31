<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * The advanced site search over search_items, shared by the search widget
 * (_pg_sw_search_collect()) and the legacy Search Results page
 * (get_search_results.php): what a visitor's words become in
 * MATCH ... AGAINST, which items a search may still return, and the request
 * for a fresh index after a design template is installed.
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
 * A visitor's search text as a valid fulltext string, so no input reaches
 * MySQL's boolean parser as a syntax error (a lone "*" or "+*" did and ended
 * the page with "Query failed."). Pure.
 *
 * - A word keeps a leading + or - and one trailing * ("+foo", "-bar",
 *   "foo*"); other operators (< > ( ) ~ @ and inner *) are dropped.
 * - A word with an inner joining character ("multi-recipient", "v2.1",
 *   "Pinegrap'in") and a quoted phrase are searched as an exact phrase, in
 *   double quotes (a wildcard is dropped there).
 * - A single plain word is quoted too, as the search always did, so it is a
 *   boolean exact-phrase search.
 * - Anything without a letter or a digit is dropped; '' when nothing is left.
 *
 * @param string $query
 * @return string
 */
function pg_search_boolean_terms($query)
{
    $query = (string) $query;
    if (preg_match_all('/([+\-]?)"([^"]*)"|(\S+)/u', $query, $matches, PREG_SET_ORDER) === false) {
        return '';
    }
    $terms = array();
    foreach ($matches as $m) {
        if (isset($m[3]) && $m[3] !== '') {
            $chunk = $m[3];
            $prefix = '';
            if (preg_match('/^[+\-~<>(]+/u', $chunk, $lead)) {
                $chunk = (string) substr($chunk, strlen($lead[0]));
                $first = strpbrk($lead[0], '+-');
                if ($first !== false) $prefix = $first[0];
            }
            $wild = '';
            if (preg_match('/\*+\)*$/u', $chunk, $tail)) {
                $chunk = (string) substr($chunk, 0, -strlen($tail[0]));
                $wild = '*';
            }
            $core = _pg_search_phrase_text($chunk);
            if ($core === '') continue;
            if (preg_match('/^[\p{L}\p{N}_]+$/u', $core)) {
                $terms[] = $prefix . $core . $wild;
            } else {
                $terms[] = $prefix . '"' . $core . '"';
            }
        } else {
            $core = _pg_search_phrase_text($m[2]);
            if ($core === '') continue;
            $terms[] = $m[1] . '"' . $core . '"';
        }
    }
    if (count($terms) === 1 && preg_match('/^[\p{L}\p{N}_]+$/u', $terms[0])) {
        return '"' . $terms[0] . '"';
    }
    return implode(' ', $terms);
}

/**
 * The text of a phrase without boolean operators, its spaces collapsed; ''
 * when it holds no letter or digit.
 *
 * @param string $text
 * @return string
 */
function _pg_search_phrase_text($text)
{
    $text = preg_replace('/[*"()<>~@+]+/u', ' ', (string) $text);
    $text = trim((string) preg_replace('/\s+/u', ' ', (string) $text));
    // A joining character at either end joins nothing.
    $text = trim($text, " -.'_");
    if ($text === '' || !preg_match('/[\p{L}\p{N}]/u', $text)) return '';
    return $text;
}

/**
 * Whether a pg_search_boolean_terms() string needs IN BOOLEAN MODE (quotes,
 * a required or excluded word, a wildcard). Without them it is searched in
 * natural language mode, as before. Pure.
 *
 * @param string $terms
 * @return bool
 */
function pg_search_needs_boolean_mode($terms)
{
    return strpbrk((string) $terms, '"+-*') !== false;
}

/**
 * The words of a pg_search_boolean_terms() string for the relevance score,
 * which is computed in natural language mode. Pure.
 *
 * @param string $terms
 * @return string
 */
function pg_search_score_terms($terms)
{
    $text = str_replace(array('"', '*'), '', (string) $terms);
    return trim((string) preg_replace('/(^|\s)[+\-]/u', '$1', $text));
}

/**
 * SQL conditions (each starting with " AND ") that keep a search_items row
 * only while what it points to can still be opened: its page exists, sits in
 * a folder that exists, is not archived and is not in the Recycle Bin; an
 * item without a page (a file) is not in a binned folder. The index is
 * rebuilt once a day, so a page binned or removed after a run was still
 * found, with a link that answered 404. Pure.
 *
 * @param int[] $binned_folder_ids pg_recycle_bin_folder_ids()
 * @return string
 */
function pg_search_items_live_sql(array $binned_folder_ids)
{
    $ids = array();
    foreach ($binned_folder_ids as $id) {
        if ((int) $id > 0) $ids[(int) $id] = (int) $id;
    }
    $not_binned = $ids ? " AND page.page_folder NOT IN (" . implode(',', $ids) . ")" : '';
    $sql = " AND (search_items.page_id = 0 OR EXISTS (SELECT 1 FROM page"
         . " INNER JOIN folder ON folder.folder_id = page.page_folder"
         . " WHERE page.page_id = search_items.page_id AND folder.folder_archived = '0'" . $not_binned . "))";
    if ($ids) {
        $sql .= " AND search_items.folder_id NOT IN (" . implode(',', $ids) . ")";
    }
    return $sql;
}

/**
 * What a search_items row points to: 'file' (no page), 'page' (the page's own
 * address) or 'record' (an address the page shows: a form record by ?r= or
 * its pretty address, an event by ?id=). Pure.
 *
 * @param array $item url, page_id and page_name (null: the page is gone)
 * @return string
 */
function pg_search_item_type(array $item)
{
    if (empty($item['page_id'])) return 'file';
    if (!isset($item['page_name'])) return 'page';
    return ((string) $item['url'] === encode_url_path((string) $item['page_name'])) ? 'page' : 'record';
}

/**
 * Rows of a fulltext query over search_items; an empty array when the query
 * fails, so a search MySQL refuses lists no results instead of ending the
 * page with the database error.
 *
 * @param string $sql    with ? placeholders (db_q())
 * @param array  $params
 * @return array
 */
function pg_search_fulltext_items($sql, array $params)
{
    $result = db_q($sql, $params);
    if (!($result instanceof mysqli_result)) {
        if ($result === false) error_log('Pinegrap: site search query failed: ' . (isset(db::$con) && db::$con ? mysqli_error(db::$con) : ''));
        return array();
    }
    $items = array();
    while ($row = mysqli_fetch_assoc($result)) $items[] = $row;
    mysqli_free_result($result);
    return $items;
}

/**
 * Make the search index job due now, so the next scheduled tick rebuilds the
 * index instead of the next day's run. It moves the job's last run back by
 * its interval, no further: the maintenance panel still reads it as having
 * run recently. Silent without the cron_runs table.
 *
 * @return bool true when the job was made due
 */
function pg_search_index_refresh_soon()
{
    if (!function_exists('pg_cron_jobs') || !pg_schema_has('cron_runs')) return false;
    $jobs = pg_cron_jobs();
    $interval = isset($jobs['update_search_index']['interval']) ? (int) $jobs['update_search_index']['interval'] : 86400;
    // Without a row the job has never run and is due already.
    return db_q(
        "UPDATE cron_runs SET last_run_at = LEAST(last_run_at, UNIX_TIMESTAMP() - ?)
         WHERE job_name = 'update_search_index'",
        array($interval)) !== false;
}
