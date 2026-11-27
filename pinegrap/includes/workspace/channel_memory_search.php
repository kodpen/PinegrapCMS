<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Workspace - the search in a channel's memory and in the general memory,
 * and the projects their records are filed under (ws_channel_memory,
 * includes/workspace/channel_memory.php). Every word of a search must be in
 * a record's title, text or tags; a hit carries the text around its first
 * word. LIKE scans the rows of one memory: 5000 records of 4000 characters
 * are read in about 120 ms (200 ms for a Turkish word with an I letter),
 * under the 300 ms the search is allowed, so no index is kept.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_FUNCTIONS_DIR')) {
    exit;
}

/** Records of a search answered in one page. */
define('WS_MEMORY_SEARCH_PAGE', 50);

/** Characters of a record's text a hit carries around its first word. */
define('WS_MEMORY_SEARCH_SNIPPET', 200);

/**
 * The words of a search: split at spaces, a "quoted phrase" one word, each
 * once, ten at most (pg_list_search_terms()). Pure.
 *
 * @param mixed $search
 * @return string[]
 */
function ws_memory_search_terms($search)
{
    return pg_list_search_terms($search);
}

/**
 * The word match as SQL over ws_channel_memory (alias m): every word in the
 * title, the text or the tags, the LIKE wildcards in a word standing for
 * themselves, the words bound as values. In a Turkish site a word holding
 * an I letter is matched folded on both sides, I, İ, ı and i as one letter
 * (pg_list_search_fold() and REPLACE): under the case-insensitive collation
 * that finds whatever the plain LIKE finds, and one pass per column keeps a
 * scan of 5000 records of 4000 characters under 300 ms, where the three
 * passes of pg_list_search_where() took over 400. Pure.
 *
 * @param string[]    $terms   ws_memory_search_terms()
 * @param string|null $turkish the connection charset (utf8mb4 or utf8) when
 *                             Turkish case rules apply, '' when not, null to
 *                             ask the site (pg_list_search_turkish_charset())
 * @return array sql ('' for no word), params
 */
function ws_memory_search_where($terms, $turkish = null)
{
    if ($turkish === null) {
        $turkish = pg_list_search_turkish_charset();
    }

    $turkish = in_array($turkish, array('utf8', 'utf8mb4'), true) ? $turkish : '';
    $parts = array();
    $params = array();

    foreach ((array) $terms as $term) {
        $term = (string) $term;

        if ($term === '') {
            continue;
        }

        $fold = ($turkish !== '') && preg_match('/[iIıİ]/u', $term);
        $alternatives = array();

        foreach (array('m.title', 'm.text', 'm.tags') as $column) {
            if ($fold) {
                $alternatives[] = 'REPLACE(REPLACE(CONVERT(' . $column . ' USING ' . $turkish . "), 'İ', 'i'), 'ı', 'i') LIKE ?";
                $params[] = pg_list_like_pattern(pg_list_search_fold($term));
            } else {
                $alternatives[] = $column . ' LIKE ?';
                $params[] = pg_list_like_pattern($term);
            }
        }

        $parts[] = '(' . implode(' OR ', $alternatives) . ')';
    }

    return array('sql' => implode(' AND ', $parts), 'params' => $params);
}

/**
 * The text a hit carries: the record's text on one line (runs of spaces and
 * line breaks folded to one space), $max characters around the first place
 * any word is found, with an ellipsis where it is cut. A word found only in
 * the title or the tags gives the start of the text. Case and the Turkish
 * I letters are left aside when looking (pg_list_search_fold()). Pure.
 *
 * @param string   $text
 * @param string[] $terms
 * @param int      $max
 * @return string
 */
function ws_memory_search_snippet($text, $terms, $max = WS_MEMORY_SEARCH_SNIPPET)
{
    $flat = trim((string) preg_replace('/\s+/u', ' ', (string) $text));
    $length = mb_strlen($flat, 'UTF-8');
    $max = max(1, (int) $max);

    if ($length <= $max) {
        return $flat;
    }

    $folded = pg_list_search_fold($flat);
    $same = (mb_strlen($folded, 'UTF-8') === $length);
    $hit = null;
    $hit_length = 0;

    foreach ((array) $terms as $term) {
        // A quoted phrase's spaces folded as the text's are.
        $term = trim((string) preg_replace('/\s+/u', ' ', (string) $term));

        if ($term === '') {
            continue;
        }

        $at = $same
            ? mb_strpos($folded, pg_list_search_fold($term), 0, 'UTF-8')
            : mb_stripos($flat, $term, 0, 'UTF-8');

        if (($at !== false) && (($hit === null) || ($at < $hit))) {
            $hit = $at;
            $hit_length = mb_strlen($term, 'UTF-8');
        }
    }

    $start = ($hit === null) ? 0 : $hit - (int) floor(max(0, $max - $hit_length) / 2);
    $start = max(0, min($start, $length - $max));

    return (($start > 0) ? '…' : '')
        . mb_substr($flat, $start, $max, 'UTF-8')
        . (($start + $max < $length) ? '…' : '');
}

/**
 * Projects with their record counts, as the projects read answers them: a
 * project written in another case or with other spaces counted with the
 * first one met, in alphabetical order (case left aside), the records
 * without a project last, as the Memory tab's headings fold them
 * (ws_memory_groups()). Pure.
 *
 * @param array[] $rows project, count
 * @return array[] project, count
 */
function ws_memory_project_counts($rows)
{
    $projects = array();

    foreach ((array) $rows as $row) {
        $project = ws_memory_project($row['project'] ?? '');
        $key = mb_strtolower($project);

        if (!isset($projects[$key])) {
            $projects[$key] = array('project' => $project, 'count' => 0);
        }

        $projects[$key]['count'] += (int) ($row['count'] ?? 0);
    }

    uksort($projects, function ($a, $b) {
        if (($a === '') || ($b === '')) {
            return ($a === '') - ($b === '');
        }

        return strcmp((string) $a, (string) $b);
    });

    return array_values($projects);
}

/**
 * The rows of a query bound with db_q().
 *
 * @param string $sql
 * @param array  $params
 * @return array[]
 */
function ws_memory_search_query($sql, $params)
{
    $result = db_q($sql, $params);
    $rows = array();

    if ($result instanceof mysqli_result) {
        while ($row = mysqli_fetch_assoc($result)) {
            $rows[] = $row;
        }

        mysqli_free_result($result);
    }

    return $rows;
}

/**
 * Which records of a memory a list or a search reads, as SQL over
 * ws_channel_memory (alias m): the memory, the live ones unless expired, a
 * tag and a project. The project is compared by the column's
 * case-insensitive collation, as ws_memory_projects() counts it. The
 * narrowing is in the query, before the list's limit. Pure.
 *
 * @param int   $channel_id 0 for the general memory
 * @param array $options    expired (bool: the expired ones too), tag
 *                          (ws_memory_tag()), project (ws_memory_project())
 * @param int   $now
 * @return array sql, params
 */
function ws_memory_filter_where($channel_id, $options, $now)
{
    $sql = 'm.channel_id = ?';
    $params = array((int) $channel_id);

    if (empty($options['expired'])) {
        $sql .= ' AND (m.expires_at = 0 OR m.expires_at > ?)';
        $params[] = (int) $now;
    }

    $tag = (string) ($options['tag'] ?? '');

    if ($tag !== '') {
        $sql .= ' AND FIND_IN_SET(?, m.tags) > 0';
        $params[] = $tag;
    }

    $project = (string) ($options['project'] ?? '');

    if ($project !== '') {
        $sql .= ' AND m.project = ?';
        $params[] = $project;
    }

    return array('sql' => $sql, 'params' => $params);
}

/**
 * A memory's index narrowed by tag and project: the records the list routes
 * answer without a search, pinned first and then the newest, 500 at most.
 *
 * @param int   $channel_id 0 for the general memory
 * @param array $options    expired, tag, project (ws_memory_filter_where())
 * @return array[] ws_channel_memory rows
 */
function ws_memory_index_rows($channel_id, $options = array())
{
    if (!ws_memory_ready() || (((string) ($options['project'] ?? '') !== '') && !ws_memory_project_ready())) {
        return array();
    }

    $filter = ws_memory_filter_where($channel_id, $options, time());

    return ws_memory_search_query('SELECT m.* FROM ws_channel_memory m WHERE ' . $filter['sql'] . ' ORDER BY m.pinned DESC, m.updated_at DESC, m.id DESC LIMIT 500', $filter['params']);
}

/**
 * One page of a memory's records holding every word, pinned first and then
 * the newest, as ws_memory_rows() orders them.
 *
 * @param int      $channel_id 0 for the general memory
 * @param string[] $terms      ws_memory_search_terms(), at least one
 * @param array    $options    expired (bool: the expired ones too), tag
 *                             (ws_memory_tag()), project (ws_memory_project()),
 *                             offset, limit
 * @return array rows, more (another page follows)
 */
function ws_memory_search_page($channel_id, $terms, $options = array())
{
    $where = ws_memory_search_where($terms);

    if (!ws_memory_ready() || ($where['sql'] === '')) {
        return array('rows' => array(), 'more' => false);
    }

    if (((string) ($options['project'] ?? '') !== '') && !ws_memory_project_ready()) {
        return array('rows' => array(), 'more' => false);
    }

    $filter = ws_memory_filter_where($channel_id, $options, time());
    $offset = max(0, (int) ($options['offset'] ?? 0));
    $limit = max(1, (int) ($options['limit'] ?? WS_MEMORY_SEARCH_PAGE));

    $sql = 'SELECT m.* FROM ws_channel_memory m WHERE ' . $filter['sql'] . ' AND ' . $where['sql'] . ' ORDER BY m.pinned DESC, m.updated_at DESC, m.id DESC LIMIT ' . $offset . ', ' . ($limit + 1);
    $rows = ws_memory_search_query($sql, array_merge($filter['params'], $where['params']));
    $more = (count($rows) > $limit);

    return array('rows' => array_slice($rows, 0, $limit), 'more' => $more);
}

/**
 * A memory's projects with their record counts (ws_memory_project_counts()).
 *
 * @param int  $channel_id 0 for the general memory
 * @param bool $expired    the expired records counted too
 * @return array[] project, count
 */
function ws_memory_projects($channel_id, $expired = false)
{
    if (!ws_memory_ready()) {
        return array();
    }

    $sql = 'SELECT ' . (ws_memory_project_ready() ? 'project' : "'' AS project") . ', COUNT(*) AS count FROM ws_channel_memory WHERE channel_id = ?';
    $params = array((int) $channel_id);

    if (!$expired) {
        $sql .= ' AND (expires_at = 0 OR expires_at > ?)';
        $params[] = time();
    }

    $sql .= ' GROUP BY 1';

    return ws_memory_project_counts(ws_memory_search_query($sql, $params));
}
