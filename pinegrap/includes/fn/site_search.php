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
 * - A word or phrase with i, I, ı or İ is searched in each of its readings
 *   (pg_search_i_variants()): the index's utf8mb4_unicode_ci folds I and İ
 *   to i but keeps ı apart, so "KAHVALTI" and "kahvalti" found no
 *   "kahvaltı". With an operator the readings are grouped: "+(a b)".
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
    // Each term: array(prefix, text, wildcard, phrase).
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
                $terms[] = array($prefix, $core, $wild, false);
            } else {
                $terms[] = array($prefix, $core, '', true);
            }
        } else {
            $core = _pg_search_phrase_text($m[2]);
            if ($core === '') continue;
            $terms[] = array($m[1], $core, '', true);
        }
    }
    if (count($terms) === 1 && $terms[0][0] === '' && $terms[0][2] === '') {
        $terms[0][3] = true;
    }
    $out = array();
    foreach ($terms as $term) {
        list($prefix, $core, $wild, $phrase) = $term;
        $readings = array();
        foreach (pg_search_i_variants($core) as $variant) {
            $readings[] = $phrase ? '"' . $variant . '"' : $variant . $wild;
        }
        if (count($readings) > 1 && $prefix !== '') {
            $out[] = $prefix . '(' . implode(' ', $readings) . ')';
        } else {
            $out[] = $prefix . implode(' ', $readings);
        }
    }
    return implode(' ', $out);
}

/**
 * Every reading of a search word's i letters, the word as typed first:
 * i, I, ı and İ are one letter to a Turkish reader, typed without a Turkish
 * keyboard or in capitals. A word with such a letter is lower cased, I read
 * as ı and İ as i; with up to two of them each mix of i and ı is given,
 * past that the typed reading, all i and all ı. A word without one comes
 * back alone and unchanged. Pure.
 *
 * @param string $word
 * @return string[]
 */
function pg_search_i_variants($word)
{
    $word = (string) $word;
    if (!preg_match('/[iIıİ]/u', $word)) return array($word);
    // A dot written as U+0307 belongs to the letter before it.
    $word = str_replace(array("I\u{307}", "i\u{307}"), array('İ', 'i'), $word);
    $word = mb_strtolower(str_replace(array('İ', 'I'), array('i', 'ı'), $word), 'UTF-8');
    $letters = preg_split('//u', $word, -1, PREG_SPLIT_NO_EMPTY);
    if (!is_array($letters)) return array($word);
    $at = array();
    foreach ($letters as $n => $letter) {
        if ($letter === 'i' || $letter === 'ı') $at[] = $n;
    }
    $variants = array();
    if (count($at) <= 2) {
        for ($mask = 0; $mask < (1 << count($at)); $mask++) {
            $copy = $letters;
            foreach ($at as $bit => $n) {
                if ($mask & (1 << $bit)) $copy[$n] = ($copy[$n] === 'i') ? 'ı' : 'i';
            }
            $variants[] = implode('', $copy);
        }
    } else {
        $variants[] = $word;
        $variants[] = str_replace('ı', 'i', $word);
        $variants[] = str_replace('i', 'ı', $word);
    }
    return array_values(array_unique($variants));
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
    $text = str_replace(array('"', '*', '(', ')'), '', (string) $terms);
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
 * The closing sweep of a search index run: every row older than the run's
 * start goes. Older, not different: a run started later has rewritten its
 * rows with its own, newer time, and an earlier run that ends first must
 * leave them (before, the first run to finish deleted them all). Pure.
 *
 * @param int $timestamp The run's start time.
 * @return string
 */
function pg_search_index_sweep_sql($timestamp)
{
    return "DELETE FROM search_items WHERE timestamp < '" . (int) $timestamp . "'";
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

/**
 * The text the search index stores for a drawn page: the page's own content,
 * so a word that appears only in the shared regions does not find every page
 * of the site. Pure.
 *
 * - The head, scripts, styles, comments, SVG and the cookie window
 *   (div#pg-cc) never count.
 * - With a <main>, only its content counts (a <header> inside it, which
 *   holds the page title in visual designs, stays).
 * - Without one, every <header>, <nav> and <footer> goes, except one inside
 *   an <article> or <section>, which belongs to that content.
 * - A link gives its text, never its address; an image gives nothing.
 * - Blocks are kept apart by a line break, spaces are folded and entities
 *   read.
 *
 * @param string $html the page as get_page_content() draws it
 * @return string
 */
function pg_search_index_text($html)
{
    $html = (string) $html;

    $html = _pg_search_index_replace('/<!--.*?-->/s', ' ', $html);
    $html = _pg_search_index_replace('/<(head|script|style|noscript|template|svg|title|textarea|select)\b[^>]*>.*?<\/\1\s*>/is', ' ', $html);
    $html = _pg_search_index_drop_consent($html);

    $main = _pg_search_index_main($html);
    if ($main !== null) {
        $html = $main;
    } else {
        $html = _pg_search_index_drop_regions($html, array('header', 'nav', 'footer'), array('article', 'section'));
    }

    $html = _pg_search_index_replace('/<\/?(?:p|div|br|hr|li|ul|ol|dl|dt|dd|h[1-6]|table|thead|tbody|tfoot|tr|td|th|caption|section|article|aside|header|footer|nav|main|blockquote|pre|figure|figcaption|address|details|summary|form|fieldset|legend|label|button|option)\b[^>]*>/i', "\n", $html);
    $text = html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8');

    $text = str_replace("\xC2\xA0", ' ', $text);
    $text = _pg_search_index_replace('/[ \t\f\v\r]+/', ' ', $text);
    $text = _pg_search_index_replace('/ ?\n[ \n]*/', "\n", $text);

    return trim($text);
}

/**
 * preg_replace() that keeps the input when PCRE gives up (backtrack limit on
 * a very large page, invalid UTF-8) instead of returning null.
 *
 * @param string $pattern
 * @param string $replacement
 * @param string $subject
 * @return string
 */
function _pg_search_index_replace($pattern, $replacement, $subject)
{
    $result = preg_replace($pattern, $replacement, $subject);
    return ($result === null) ? $subject : $result;
}

/**
 * The offset just after the close tag that ends the element opened at
 * $start (same-named elements nested inside are counted); the end of the
 * text when it is never closed.
 *
 * @param string $html
 * @param string $name tag name, lower case
 * @param int $start offset of the element's "<"
 * @return int
 */
function _pg_search_index_element_end($html, $name, $start)
{
    $depth = 0;
    $offset = $start;
    while (preg_match('/<(\/?)' . $name . '\b[^>]*>/i', $html, $tag, PREG_OFFSET_CAPTURE, $offset)) {
        $depth += ($tag[1][0] === '/') ? -1 : 1;
        $offset = $tag[0][1] + strlen($tag[0][0]);
        if ($depth <= 0) {
            return $offset;
        }
    }

    return strlen($html);
}

/**
 * The inner HTML of every <main> that is not inside another one, joined; null
 * when the page has none.
 *
 * @param string $html
 * @return string|null
 */
function _pg_search_index_main($html)
{
    $parts = array();
    $offset = 0;
    while (preg_match('/<main\b[^>]*>/i', $html, $open, PREG_OFFSET_CAPTURE, $offset)) {
        $inner_start = $open[0][1] + strlen($open[0][0]);
        $end = _pg_search_index_element_end($html, 'main', $open[0][1]);
        $inner = substr($html, $inner_start, $end - $inner_start);
        $parts[] = (string) preg_replace('/<\/main\s*>$/i', '', $inner);
        $offset = $end;
    }

    return $parts ? implode("\n", $parts) : null;
}

/**
 * $html without the cookie window (div#pg-cc, pg_consent_notice_html()).
 *
 * @param string $html
 * @return string
 */
function _pg_search_index_drop_consent($html)
{
    while (preg_match('/<div\b[^>]*\bid\s*=\s*["\']?pg-cc["\'\s\/>]/i', $html, $match, PREG_OFFSET_CAPTURE)) {
        $at = $match[0][1];
        $html = substr($html, 0, $at) . ' ' . substr($html, _pg_search_index_element_end($html, 'div', $at));
    }

    return $html;
}

/**
 * $html without the $names elements, unless they sit inside one of the
 * $keep_inside elements.
 *
 * @param string $html
 * @param array $names tag names to drop, lower case
 * @param array $keep_inside tag names, lower case
 * @return string
 */
function _pg_search_index_drop_regions($html, array $names, array $keep_inside)
{
    $scan = '/<(\/?)(' . implode('|', array_merge($names, $keep_inside)) . ')\b[^>]*>/i';
    $depth = 0;
    $offset = 0;
    while (preg_match($scan, $html, $match, PREG_OFFSET_CAPTURE, $offset)) {
        $at = $match[0][1];
        $name = strtolower($match[2][0]);
        if (in_array($name, $keep_inside, true)) {
            $depth = max(0, $depth + (($match[1][0] === '/') ? -1 : 1));
            $offset = $at + strlen($match[0][0]);
        } elseif ($depth > 0 || $match[1][0] === '/') {
            $offset = $at + strlen($match[0][0]);
        } else {
            $html = substr($html, 0, $at) . ' ' . substr($html, _pg_search_index_element_end($html, $name, $at));
            $offset = $at + 1;
        }
    }

    return $html;
}

/**
 * One line naming an item that the search index run could not read, for
 * the run's Site Log line: "<kind> <page> (<reference code>): <message>",
 * the kind (already translated) and the reference code left out when empty
 * (a record list, a page, a file). The message is put on one line and cut to
 * $max characters (UTF-8 safe), so a long stack trace does not fill the log.
 * Pure.
 *
 * @param string $page_name page or file name
 * @param string $reference_code record reference code or event id; '' for none
 * @param string $message the error's message
 * @param int $max characters of the message kept
 * @param string $kind the item's kind ("Page", "Event", "File"); '' for none
 * @return string
 */
function pg_search_index_failure_note($page_name, $reference_code, $message, $max = 200, $kind = '')
{
    $message = trim(_pg_search_index_replace('/\s+/u', ' ', (string) $message));
    $max = max(1, (int) $max);

    if (preg_match('/^.{' . ($max + 1) . '}/su', $message) === 1) {
        $message = rtrim((string) preg_replace('/^(.{' . $max . '}).*$/su', '$1', $message)) . '...';
    } elseif ((preg_match('//u', $message) !== 1) && (strlen($message) > $max)) {
        // Not valid UTF-8: the pattern above cannot measure it.
        $message = substr($message, 0, $max) . '...';
    }

    $label = (string) $page_name;

    if ((string) $reference_code !== '') {
        $label .= ' (' . $reference_code . ')';
    }

    if ((string) $kind !== '') {
        $label = $kind . ' ' . $label;
    }

    return $label . ': ' . $message;
}

/**
 * Counts an item the search index run could not read and keeps its note
 * while fewer than $keep are kept, for the run's Site Log line. Pure.
 *
 * @param int $failures the run's count, raised by one
 * @param array $notes the run's notes
 * @param string $note pg_search_index_failure_note()
 * @param int $keep notes kept at most
 */
function pg_search_index_failed(&$failures, array &$notes, $note, $keep = 5)
{
    $failures++;

    if (count($notes) < $keep) {
        $notes[] = (string) $note;
    }
}

/**
 * Draws one page for the search index so that a bad page cannot end the
 * run: under pg_error_throws(true) a failed query throws instead of printing
 * an error screen and exiting, and any Throwable (an Error on PHP 8 too) is
 * caught. $_GET, the output buffer level (a PHP region that throws never
 * closes its buffer) and the flag are put back whatever happens.
 *
 * @param int $page_id
 * @param array $properties get_page_content()'s dynamic properties
 *                          (calendar_event_id, form_id)
 * @param callable $renderer get_page_content() unless a test passes its own
 * @return array 'content' => HTML, or null when the render failed;
 *               'error' => the failure's message, '' on success
 */
function pg_search_index_render($page_id, $properties = array(), $renderer = 'get_page_content')
{
    $saved_get = $_GET;
    $saved_error_throws = pg_error_throws();
    $saved_ob_level = ob_get_level();

    pg_error_throws(true);

    try {
        if ($renderer === 'get_page_content') {
            $content = get_page_content($page_id, '', '', 'preview', false, $properties);
        } else {
            $content = call_user_func($renderer, $page_id, $properties);
        }

        return array('content' => (string) $content, 'error' => '');
    } catch (Throwable $throwable) {
        return array('content' => null, 'error' => (string) $throwable->getMessage());
    } finally {
        while (ob_get_level() > $saved_ob_level) {
            ob_end_clean();
        }

        pg_error_throws($saved_error_throws);
        $_GET = $saved_get;
    }
}

/**
 * Keeps the search item of an address the run could not read: its timestamp
 * becomes the run's, so the sweep at the end spares it and the item keeps
 * its last indexed text until a later run reads it again. No row: nothing.
 *
 * @param string $url
 * @param int $timestamp the run's timestamp
 */
function pg_search_index_keep_item($url, $timestamp)
{
    db("UPDATE search_items SET timestamp = '" . (int) $timestamp . "' WHERE url = '" . e((string) $url) . "'");
}

/**
 * The bytes of a file for the old office readers, '' when the path is not a
 * readable file or holds nothing. fread() on a failed fopen() is a TypeError
 * on PHP 8, and on a length of 0 a ValueError.
 *
 * @param string $path
 * @return string
 */
function pg_search_index_file_bytes($path)
{
    $path = (string) $path;

    if (!is_file($path) || !is_readable($path)) {
        return '';
    }

    $size = (int) @filesize($path);

    if ($size <= 0) {
        return '';
    }

    $bytes = @file_get_contents($path, false, null, 0, $size);

    return is_string($bytes) ? $bytes : '';
}

/**
 * What a search index run that stopped before its end was doing, for its
 * Site Log line: "<stage>, <item>: <message> (<file>:<line>)". The error
 * part comes from error_get_last() and only for a fatal kind; a warning left
 * over from earlier in the run did not stop it. The message is on one line
 * and cut to 200 characters. Pure.
 *
 * @param string $stage pages, records, files ...
 * @param string $item the item last started, '' for none
 * @param array|null $error error_get_last()
 * @return string
 */
function pg_search_index_stop_note($stage, $item, $error)
{
    $note = (string) $stage;

    if ((string) $item !== '') {
        $note .= ', ' . $item;
    }

    $fatal = E_ERROR | E_PARSE | E_CORE_ERROR | E_COMPILE_ERROR | E_USER_ERROR | E_RECOVERABLE_ERROR;

    if (is_array($error) && isset($error['type']) && (((int) $error['type'] & $fatal) !== 0)) {
        $where = isset($error['file']) ? basename((string) $error['file']) . ':' . (isset($error['line']) ? (int) $error['line'] : 0) : '';
        $message = pg_search_index_failure_note('', '', isset($error['message']) ? $error['message'] : '');
        $note .= ': ' . substr($message, 2) . (($where !== '') ? ' (' . $where . ')' : '');
    }

    return $note;
}

/**
 * The stages of a search index pass, in order. Pure.
 *
 * @return string[]
 */
function pg_search_index_stages()
{
    // A page's calendar events and form records are read with the page, as
    // one item; the visual editor's records follow all pages, since the
    // page loop collects the lists they come from.
    return array('pages', 'designer', 'files', 'sweep');
}

/**
 * Where a search index pass stands, as read back from its cursor file: a
 * state that is missing, unreadable or names no known stage starts a new pass
 * at $now. Pure.
 *
 * The state: started (the pass's start time: every row it writes carries it
 * and the sweep removes the rows older than it), stage, after (the key of the
 * stage's last finished item, '' at its start), current (the item started and
 * not finished: a tick that ended inside it), skipped (notes of the items
 * skipped this pass), carry (what one stage hands the next: the linked names
 * the files stage reads, the record lists, the addresses already written),
 * done.
 *
 * @param mixed $state the decoded cursor file, or null
 * @param int   $now
 * @return array
 */
function pg_search_index_next($state, $now)
{
    if (!is_array($state) || !isset($state['stage'], $state['started'])
        || !in_array($state['stage'], pg_search_index_stages(), true)
        || ((int) $state['started'] <= 0) || !empty($state['done'])) {
        return array('started' => (int) $now, 'stage' => 'pages', 'after' => '', 'current' => '', 'skipped' => array(), 'carry' => array(), 'done' => false);
    }

    return array(
        'started' => (int) $state['started'],
        'stage'   => (string) $state['stage'],
        'after'   => (string) ($state['after'] ?? ''),
        'current' => (string) ($state['current'] ?? ''),
        'skipped' => array_values(array_slice((array) ($state['skipped'] ?? array()), 0, 50)),
        'carry'   => (isset($state['carry']) && is_array($state['carry'])) ? $state['carry'] : array(),
        'done'    => false,
    );
}

/**
 * A stage's name for the panel window.
 *
 * @param string $stage
 * @return string
 */
function pg_search_index_stage_label($stage)
{
    switch ((string) $stage) {
        case 'pages':
            return lang('pages');
        case 'designer':
            return lang('records');
        case 'files':
            return lang('files');
        default:
            return lang('removing old items');
    }
}

/**
 * Whether the next search index run starts a full pass: when none has
 * finished for a day. Between full passes a dispatched run only re-indexes
 * what changed since its row (the delta stage). Pure.
 *
 * @param int $last_done the end of the last full pass (the job's completion
 *                       marker), 0 for none
 * @param int $now
 * @param int $interval  seconds between full passes
 * @return bool
 */
function pg_search_index_full_due($last_done, $now, $interval = 86400)
{
    return ((int) $last_done <= 0) || (((int) $now - (int) $last_done) >= ((int) $interval - 300));
}

/**
 * The pages the delta stage re-indexes: those whose own row exists and is
 * older than the page's last change. A page with no row waits for the full
 * pass. Pure.
 *
 * @param array $pages [{id, url (its indexed address), page_timestamp}]
 * @param array $rows  url => the row's timestamp
 * @return int[]
 */
function pg_search_index_delta_pages($pages, $rows)
{
    $ids = array();

    foreach ((array) $pages as $page) {
        $url = (string) ($page['url'] ?? '');

        if (($url !== '') && isset($rows[$url]) && ((int) $page['page_timestamp'] > (int) $rows[$url])) {
            $ids[] = (int) $page['id'];
        }
    }

    return $ids;
}

/**
 * The reference code a record's indexed address carries in ?r=, '' when it
 * carries none (a record indexed at its address name waits for the full
 * pass). Pure.
 *
 * @param string $url
 * @return string
 */
function pg_search_index_record_code($url)
{
    $query = (string) parse_url('/' . ltrim((string) $url, '/'), PHP_URL_QUERY);

    if ($query === '') {
        return '';
    }

    parse_str($query, $params);

    return (isset($params['r']) && is_string($params['r'])) ? trim($params['r']) : '';
}

/**
 * The records the delta stage re-indexes: reference code => true for each
 * record changed after its row was written. Pure.
 *
 * @param array $rows  url => the row's timestamp (rows of record addresses)
 * @param array $forms reference_code => forms.last_modified_timestamp
 * @return array
 */
function pg_search_index_delta_records($rows, $forms)
{
    $codes = array();

    foreach ((array) $rows as $url => $timestamp) {
        $code = pg_search_index_record_code($url);

        if (($code !== '') && isset($forms[$code]) && ((int) $forms[$code] > (int) $timestamp)) {
            $codes[$code] = true;
        }
    }

    return $codes;
}

/**
 * Where an unfinished search index pass keeps its place. Losing the file (the
 * clean up tool empties data/temp) only starts the pass again.
 *
 * @return string
 */
function pg_search_index_state_path()
{
    return PG_FUNCTIONS_DIR . '/data/temp/search_index.json';
}

/**
 * The cursor file decoded, or null when there is none to read.
 *
 * @return array|null
 */
function pg_search_index_state_read()
{
    $state = json_decode((string) @file_get_contents(pg_search_index_state_path()), true);

    return is_array($state) ? $state : null;
}

/**
 * Write the cursor file; at the end of a pass, remove it.
 *
 * @param array $state
 */
function pg_search_index_state_write($state)
{
    if (!empty($state['done'])) {
        @unlink(pg_search_index_state_path());
        return;
    }

    @file_put_contents(pg_search_index_state_path(), json_encode($state), LOCK_EX);
}

/**
 * Start one item of the stage. When it is the item the state names as
 * started and not finished, a tick ended inside it (a fatal error, an exit,
 * a killed process): it is skipped, named in the state's notes, and counted
 * as finished, so the pass never stops at the same item twice. Pure.
 *
 * @param array  $state from pg_search_index_next()
 * @param string $key   the item's key in its stage
 * @return array state, skip (bool)
 */
function pg_search_index_begin($state, $key)
{
    $key = (string) $key;

    if (($state['current'] !== '') && ($state['current'] === $key)) {
        $state['current'] = '';
        $state['after'] = $key;

        if (count($state['skipped']) < 50) {
            $state['skipped'][] = $state['stage'] . ' ' . $key;
        }

        return array('state' => $state, 'skip' => true);
    }

    $state['current'] = $key;

    return array('state' => $state, 'skip' => false);
}

/**
 * Mark an item of the stage finished. Pure.
 *
 * @param array  $state
 * @param string $key
 * @return array
 */
function pg_search_index_finish($state, $key)
{
    $state['after'] = (string) $key;
    $state['current'] = '';

    return $state;
}

/**
 * Move to the next stage; after the sweep the pass is done. Pure.
 *
 * @param array $state
 * @return array
 */
function pg_search_index_advance($state)
{
    $stages = pg_search_index_stages();
    $at = array_search($state['stage'], $stages, true);

    $state['after'] = '';
    $state['current'] = '';

    if (($at === false) || ($at + 1 >= count($stages))) {
        $state['done'] = true;
    } else {
        $state['stage'] = $stages[$at + 1];
    }

    return $state;
}

/**
 * A visit's site search terms with one more search added, separated by "|"
 * as the visitor report and the visit detail split them. An empty term adds
 * nothing; a "|" in the term becomes a space, so it stays one term.
 *
 * The column is varchar(255) and the session's sql_mode is not strict, so
 * MySQL cut a longer value: half a term was stored and every later search of
 * the visit was lost. The value now stays within $max characters: a lone term
 * is cut to it, and a term that no longer fits after the earlier ones is not
 * added. Pure.
 *
 * @param string $existing visitors.site_search_terms as stored
 * @param string $term     what the visitor searched for
 * @param int    $max      characters the column holds
 * @return string
 */
function pg_search_visit_terms_append($existing, $term, $max = 255)
{
    $existing = (string) $existing;
    $max = max(1, (int) $max);
    $term = trim(_pg_search_index_replace('/[\s|]+/u', ' ', (string) $term));

    if ($term === '') {
        return $existing;
    }

    if ($existing === '') {
        return (mb_strlen($term, 'UTF-8') > $max) ? rtrim(mb_substr($term, 0, $max, 'UTF-8')) : $term;
    }

    $joined = $existing . '|' . $term;

    return (mb_strlen($joined, 'UTF-8') > $max) ? $existing : $joined;
}

/**
 * Add a site search to the current visit (visitors.site_search_terms), for
 * the visitor report's "Site Search Terms" summary and the visit detail.
 * Shared by the search widget and the legacy Search Results page; does
 * nothing when the term is empty, visitor tracking is off or the visit has
 * not been started.
 *
 * @param string $term
 * @return void
 */
function pg_search_record_visit_term($term)
{
    $term = trim((string) $term);

    if (
        ($term === '')
        || !defined('VISITOR_TRACKING') || (VISITOR_TRACKING != true)
        || empty($_SESSION['software']['visitor_id'])
    ) {
        return;
    }

    $visitor_id = (string) $_SESSION['software']['visitor_id'];

    $result = db_q("SELECT site_search_terms FROM visitors WHERE id = ?", array($visitor_id));
    $row = $result ? mysqli_fetch_assoc($result) : null;

    // The session can name a row that is gone: a restored backup carries no
    // visitor rows.
    if (!is_array($row)) {
        return;
    }

    db_q(
        "UPDATE visitors
        SET
            site_search_terms = ?,
            stop_timestamp = UNIX_TIMESTAMP()
        WHERE (id = ?)",
        array(pg_search_visit_terms_append((string) $row['site_search_terms'], $term), $visitor_id)
    );
}
