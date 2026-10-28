<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * The referring address and the browser (user agent) of a visit, each kept
 * once in visitor_texts and named in the visit by its number.
 *
 * The two were up to 255 characters each in every `visitors` row and most of
 * the row's size, while a site sees the same few hundred browsers and the
 * same handful of search engine addresses again and again. A row written
 * since the 2026.4.10 step (10.6) carries visitors.http_referer_id and
 * visitors.user_agent_id and leaves the text columns empty; a row written
 * before keeps its text there and is read as it always was. The retention
 * period removes the older rows in time, so they are not rewritten.
 *
 * A value nobody visits with any more is removed from the list once no
 * remaining visit can name it (pg_visitor_texts_prune()), which the visitor
 * cleanup runs after its removal.
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
 * What a visitor_texts row holds: visitor_texts.kind.
 */
define('PG_VISITOR_TEXT_REFERER', 1);
define('PG_VISITOR_TEXT_AGENT', 2);

/**
 * How long a value's last_used may lag behind the visits that name it. A
 * visit refreshes it only when it is older than this, so a busy value costs
 * one UPDATE a day and not one per visit.
 */
define('PG_VISITOR_TEXT_REFRESH_SECONDS', 86400);

/**
 * The text columns of `visitors` kept in the list: column => the column with
 * its number, the alias its join takes, its kind.
 *
 * @return array
 */
function pg_visitor_text_columns()
{
    return array(
        'http_referer' => array('id' => 'http_referer_id', 'alias' => 'vt_referer', 'kind' => PG_VISITOR_TEXT_REFERER),
        'user_agent' => array('id' => 'user_agent_id', 'alias' => 'vt_agent', 'kind' => PG_VISITOR_TEXT_AGENT),
    );
}

/**
 * True when visits are written with numbers: the list and both number
 * columns are there. Before the step, and where it could not add the columns
 * yet, visits keep their texts in the row.
 *
 * @return bool
 */
function pg_visitor_texts_ready()
{
    return pg_schema_has('visitor_texts')
        && pg_schema_has('visitors', 'http_referer_id')
        && pg_schema_has('visitors', 'user_agent_id');
}

/**
 * A text as the list keeps it: valid UTF-8 and at most 255 characters, the
 * length of the columns it replaces. The hash is taken of exactly this, so a
 * visit with an address longer than that finds the value the last one left.
 *
 * @param mixed $text
 * @return string
 */
function pg_visitor_text_clean($text)
{
    $text = is_scalar($text) ? (string) $text : '';

    if ($text === '') {
        return '';
    }

    if (!mb_check_encoding($text, 'UTF-8')) {
        $text = mb_convert_encoding($text, 'UTF-8', 'UTF-8');
    }

    return mb_substr($text, 0, 255, 'UTF-8');
}

/**
 * The number of a text in the list, adding it when it is not there yet.
 *
 * 0 for an empty text (no referring address, no user agent): no row is kept
 * for it. false when the list cannot be read or written; the caller then
 * keeps the text in the visit's own row.
 *
 * A value found with a last_used older than a day is brought up to now in
 * the same step. The UPDATE also tells whether the row is still there: the
 * prune may have taken it a moment ago, and then it is added again rather
 * than named by a number that no longer exists.
 *
 * @param int    $kind PG_VISITOR_TEXT_*
 * @param string $text
 * @return int|false
 */
function pg_visitor_text_id($kind, $text)
{
    $kind = (int) $kind;
    $text = pg_visitor_text_clean($text);

    if ($text === '') {
        return 0;
    }

    $hash = md5($text);

    $find = "SELECT text_id, (last_used < UNIX_TIMESTAMP() - " . PG_VISITOR_TEXT_REFRESH_SECONDS . ") AS stale
        FROM visitor_texts
        WHERE (kind = ?) AND (text_hash = UNHEX(?))";

    $found = db_q($find, array($kind, $hash));

    if (!$found) {
        return false;
    }

    $row = mysqli_fetch_assoc($found);

    if (is_array($row)) {

        $id = (int) $row['text_id'];

        if ((int) $row['stale'] !== 1) {
            return $id;
        }

        if (db_q("UPDATE visitor_texts SET last_used = UNIX_TIMESTAMP() WHERE text_id = ?", array($id))
            && (mysqli_affected_rows(db::$con) > 0)) {
            return $id;
        }
    }

    // INSERT IGNORE, as another visit may be adding the same value at this
    // moment: the one that loses finds the winner's row below.
    $added = db_q(
        "INSERT IGNORE INTO visitor_texts (kind, text_hash, text_value, last_used)
        VALUES (?, UNHEX(?), ?, UNIX_TIMESTAMP())", array($kind, $hash, (string) $text));

    if (!$added) {
        return false;
    }

    $id = (int) mysqli_insert_id(db::$con);

    if ($id > 0) {
        return $id;
    }

    $found = db_q($find, array($kind, $hash));
    $row = $found ? mysqli_fetch_assoc($found) : null;

    return is_array($row) ? (int) $row['text_id'] : false;
}

/**
 * A text column of `visitors` as SQL that reads both kinds of row: the
 * list's value for a visit that names one, otherwise the column itself.
 *
 * The query must take pg_visitor_text_joins() for it. Before the step the
 * column alone.
 *
 * @param string $column 'http_referer' or 'user_agent'
 * @param string $table  The name or alias `visitors` has in the query
 * @return string
 */
function pg_visitor_text_sql($column, $table = 'visitors')
{
    $columns = pg_visitor_text_columns();

    if (!isset($columns[$column]) || !pg_visitor_texts_ready()) {
        return $table . '.' . $column;
    }

    return "COALESCE(" . $columns[$column]['alias'] . ".text_value, " . $table . "." . $column . ")";
}

/**
 * The joins the expressions in $sql need, to put after FROM visitors: one
 * LEFT JOIN per list column $sql names through pg_visitor_text_sql(), none
 * when it names none. A visit whose number is 0 finds no row and reads its
 * own column.
 *
 * @param string $sql   Any part of the query: select list, WHERE, GROUP BY
 * @param string $table The name or alias `visitors` has in the query
 * @return string
 */
function pg_visitor_text_joins($sql, $table = 'visitors')
{
    $joins = '';

    foreach (pg_visitor_text_columns() as $column) {
        if (strpos((string) $sql, $column['alias'] . '.') !== false) {
            $joins .= " LEFT JOIN visitor_texts AS " . $column['alias']
                . " ON (" . $column['alias'] . ".text_id = " . $table . "." . $column['id'] . ")";
        }
    }

    return $joins;
}

/**
 * The last moment a value may have been used and still be removed: two days
 * before the oldest visit left (a value can lag a day behind its visits), or
 * two days ago when no visit is left.
 *
 * @param int $oldest_start MIN(visitors.start_timestamp), 0 when there is none
 * @param int $now
 * @return int
 */
function pg_visitor_texts_prune_before($oldest_start, $now)
{
    $base = ((int) $oldest_start > 0) ? min((int) $oldest_start, (int) $now) : (int) $now;

    return max(0, $base - 2 * PG_VISITOR_TEXT_REFRESH_SECONDS);
}

/**
 * Remove the values of the list no remaining visit can name.
 *
 * A visit names a value it found or added; finding one brings its last_used
 * to within a day of the visit's start. A value last used two days before
 * the oldest visit is therefore named by none. Removed by primary-key
 * windows, each window checked again in its DELETE, so a visit refreshing a
 * value at that moment keeps it.
 *
 * Runs inside the cleanup's lock (pg_visitor_cleanup_run()).
 *
 * @param float $deadline microtime(true) after which no new window starts
 * @param int   $chunk    Values per DELETE
 * @return int Values removed
 */
function pg_visitor_texts_prune($deadline, $chunk = 5000)
{
    if (!pg_visitor_texts_ready()) {
        return 0;
    }

    $chunk = max(100, min(50000, (int) $chunk));

    $oldest = (int) db_value("SELECT MIN(start_timestamp) FROM visitors WHERE start_timestamp > 0");
    $before = pg_visitor_texts_prune_before($oldest, (int) db_value("SELECT UNIX_TIMESTAMP()"));

    $removed = 0;
    $cursor = 0;

    while (microtime(true) < $deadline) {

        $ids = db_values(
            "SELECT text_id
            FROM visitor_texts
            WHERE (text_id > " . $cursor . ") AND (last_used < " . $before . ")
            ORDER BY text_id
            LIMIT " . $chunk);

        if (!is_array($ids) || (count($ids) === 0)) {
            break;
        }

        $ids = array_map('intval', $ids);

        if (!@mysqli_query(db::$con,
            "DELETE FROM visitor_texts
            WHERE (text_id IN (" . implode(',', $ids) . ")) AND (last_used < " . $before . ")")) {
            break;
        }

        $removed += (int) mysqli_affected_rows(db::$con);
        $cursor = max($ids);

        if (count($ids) < $chunk) {
            break;
        }

        usleep(20000);
    }

    return $removed;
}

/**
 * How many values the list holds, for the Database Engine card; null before
 * the step has added it or its columns.
 *
 * @return int|null
 */
function pg_visitor_texts_count()
{
    if (!pg_visitor_texts_ready()) {
        return null;
    }

    return (int) db_value("SELECT COUNT(*) FROM visitor_texts");
}
