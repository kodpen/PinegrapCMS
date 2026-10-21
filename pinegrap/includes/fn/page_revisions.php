<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Page revisions: one row in page_revisions (2026.11.0, 11.0.15) for every
 * publish of a visual-designer page.
 *
 * A live page publishes directly, there is no working copy; a revision is the
 * record of what was published, not a draft. pg_designer_save_page() writes
 * one after each save that changed the page (a save carried unchanged because
 * only a shared component moved writes none), the importer and the AI editor
 * name their source, and a restore is a new publish with source 'restore'.
 * Nothing is written on the editor's autosave, which is browser-local.
 *
 * A row keeps the tree (page_tree_json, what the editor opens again), the
 * SHA-1 of the HTML drawn from it (the body is drawn again on restore, so
 * the hash is a record of what went out, not a copy of it) and the page's
 * settings as JSON (title, description, folder, flags, meta tags, structured
 * data), with who published and when. Two revisions are told apart by the
 * tree and the settings alone: the body is derived from the tree, and the
 * renderer is not deterministic (a navbar or carousel gets a fresh element
 * id on every draw), so the drawn hash differs between two draws of one tree.
 *
 * Retention is the larger of two rules, both settings on the config row: the
 * last PAGE_REVISIONS_KEEP revisions of a page are kept, and so is every
 * revision younger than PAGE_REVISIONS_DAYS days. pg_page_revisions_trim()
 * applies them to one page after its write; the general job (job.php) runs
 * the same trim over every page once a day and removes the rows of pages
 * that no longer exist, since a page is deleted on several paths and none of
 * them needs to know about this table.
 *
 * Recording never stops the save that caused it: the page is already written
 * when it runs, and a failed statement is noted in the error log.
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
 * The source labels a revision can carry: how the published tree came about.
 *
 * publish: a save from the editor or the external API; restore: a revision
 * loaded back into the editor and published again; import: the HTML, ZIP
 * or site importer, or a design template; ai: the AI editor's change.
 */
define('PG_PAGE_REVISION_SOURCES', 'publish,restore,import,ai');

/**
 * The retention settings' bounds: a count between 1 and 500 revisions, a
 * period between 1 and 3650 days.
 */
define('PG_PAGE_REVISIONS_KEEP_MAX', 500);
define('PG_PAGE_REVISIONS_DAYS_MAX', 3650);

/**
 * The daily sweep's state file (job.php).
 */
define('PG_PAGE_REVISIONS_SWEEP_STATE', 'page_revisions_sweep.json');

/**
 * True when the table is in the database (2026.11.0, 11.0.15).
 *
 * @return bool
 */
function pg_page_revisions_ready()
{
    return pg_schema_has('page_revisions', 'source');
}

/**
 * The source labels, as a list.
 *
 * @return array<int,string>
 */
function pg_page_revision_sources()
{
    return explode(',', PG_PAGE_REVISION_SOURCES);
}

/**
 * A source label as the table takes it: a known label, 'publish' otherwise.
 *
 * @param string $source
 * @return string
 */
function pg_page_revision_source($source)
{
    $source = strtolower(trim((string) $source));

    return in_array($source, pg_page_revision_sources(), true) ? $source : 'publish';
}

/**
 * The retention rules, clamped: how many revisions of a page are kept
 * whatever their age, and for how many days a revision is kept whatever its
 * place in the list. Read from the constants init.php defines from the
 * config row; the defaults stand where the columns are not there yet.
 *
 * @param int|null $keep override, for the pure tests
 * @param int|null $days override
 * @return array keep, days
 */
function pg_page_revisions_retention($keep = null, $days = null)
{
    if ($keep === null) {
        $keep = defined('PAGE_REVISIONS_KEEP') ? (int) PAGE_REVISIONS_KEEP : 30;
    }

    if ($days === null) {
        $days = defined('PAGE_REVISIONS_DAYS') ? (int) PAGE_REVISIONS_DAYS : 90;
    }

    return array(
        'keep' => max(1, min(PG_PAGE_REVISIONS_KEEP_MAX, (int) $keep)),
        'days' => max(1, min(PG_PAGE_REVISIONS_DAYS_MAX, (int) $days)),
    );
}

/**
 * The page settings a revision records, read from a page row: everything a
 * save writes besides the tree, so a revision says what the page was called,
 * where it lived and how it was flagged when that tree went live. Columns a
 * database does not have yet are left out, so the snapshot never asks for
 * more than the row carries.
 *
 * @param array $row a page row, as SELECT * returns it
 * @return array
 */
function pg_page_revision_settings_from_row($row)
{
    $columns = array(
        'page_name', 'page_folder', 'page_title', 'page_meta_description',
        'page_search', 'page_search_keywords', 'sitemap', 'noindex', 'nofollow', 'page_home',
        'comments', 'comments_label', 'comments_allow_new_comments', 'comments_rating',
        'comments_automatic_publish', 'comments_show_submitted_date_and_time',
        'comments_require_login_to_comment', 'comments_submitter_email_page_id',
        'comments_submitter_email_subject', 'comments_administrator_email_to_email_address',
        'page_meta_tags', 'custom_jsonld', 'canonical_url', 'noarchive', 'max_snippet',
    );

    $out = array();

    if (!is_array($row)) {
        return $out;
    }

    foreach ($columns as $column) {
        if (array_key_exists($column, $row)) {
            $out[$column] = ($row[$column] === null) ? '' : (string) $row[$column];
        }
    }

    return $out;
}

/**
 * The settings of a page now, for the revision being written.
 *
 * @param int $page_id
 * @return array
 */
function pg_page_revision_settings($page_id)
{
    $row = db_item("SELECT * FROM page WHERE page_id = '" . (int) $page_id . "' LIMIT 1");

    return pg_page_revision_settings_from_row($row);
}

/**
 * Whether the newest revision already records this state: the same tree and
 * the same settings. Such a save (a schedule-only save, or a settings save
 * that changed nothing the revision records) writes no row.
 *
 * The drawn HTML is not compared: it is derived from the tree, and the
 * renderer gives a navbar or carousel a fresh element id on every draw, so
 * two draws of one tree hash differently and would pass for two states.
 *
 * @param array|null $latest the newest row (tree_hash, settings_json)
 * @param string     $tree_hash
 * @param string     $settings_json
 * @return bool
 */
function pg_page_revision_identical($latest, $tree_hash, $settings_json)
{
    if (!is_array($latest)) {
        return false;
    }

    return (string) $latest['tree_hash'] === (string) $tree_hash
        && (string) $latest['settings_json'] === (string) $settings_json;
}

/**
 * Writes a revision of a page as it was just published. Returns the revision
 * number, or 0 when nothing was written (table not there, identical to the
 * newest revision, or a failed statement).
 *
 * @param int    $page_id
 * @param int    $style_id
 * @param string $tree_json the tree as saved (pg_designer_tree_encode())
 * @param string $tree_code the HTML drawn from it
 * @param int    $user_id
 * @param string $source    pg_page_revision_sources()
 * @return int
 */
function pg_page_revision_record($page_id, $style_id, $tree_json, $tree_code, $user_id, $source = 'publish')
{
    $page_id = (int) $page_id;

    if ($page_id <= 0 || !pg_page_revisions_ready()) {
        return 0;
    }

    $tree_json     = (string) $tree_json;
    $tree_hash     = sha1($tree_json);
    $code_hash     = sha1((string) $tree_code);
    $settings      = pg_page_revision_settings($page_id);
    $settings_json = (string) json_encode($settings, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    $source        = pg_page_revision_source($source);

    $latest = db_item(
        "SELECT revision_no, tree_hash, settings_json
         FROM page_revisions
         WHERE page_id = '$page_id'
         ORDER BY revision_no DESC
         LIMIT 1");

    if (pg_page_revision_identical($latest, $tree_hash, $settings_json)) {
        return 0;
    }

    // The number is taken in the INSERT itself, so two saves of one page at
    // the same moment cannot both read the same MAX(); the unique key refuses
    // the second, which then tries once more with the next number.
    for ($attempt = 0; $attempt < 2; $attempt++) {
        $ok = @mysqli_query(db::$con,
            "INSERT INTO page_revisions
                (page_id, style_id, revision_no, page_tree_json, tree_hash, tree_code_hash, settings_json, user_id, source, created_at)
             SELECT
                '$page_id',
                '" . (int) $style_id . "',
                COALESCE(MAX(revision_no), 0) + 1,
                '" . e($tree_json) . "',
                '" . e($tree_hash) . "',
                '" . e($code_hash) . "',
                '" . e($settings_json) . "',
                '" . (int) $user_id . "',
                '" . e($source) . "',
                '" . time() . "'
             FROM page_revisions
             WHERE page_id = '$page_id'");

        if ($ok) {
            // The row this INSERT wrote, by its own id: MAX() could already be
            // the number of another save of the same page.
            $revision_id = (int) mysqli_insert_id(db::$con);
            $revision_no = ($revision_id > 0)
                ? (int) db_value("SELECT revision_no FROM page_revisions WHERE revision_id = '$revision_id' LIMIT 1")
                : (int) db_value("SELECT MAX(revision_no) FROM page_revisions WHERE page_id = '$page_id'");

            pg_page_revisions_trim($page_id);

            return $revision_no;
        }

        if (mysqli_errno(db::$con) !== 1062) {
            break;
        }
    }

    pg_page_revisions_log_failure('writing the revision of page ' . $page_id);

    return 0;
}

/**
 * Put a failed statement in the error log without ending the request.
 *
 * @param string $step
 * @return void
 */
function pg_page_revisions_log_failure($step)
{
    if (!function_exists('pg_error_write') || !function_exists('pg_error_entry')) {
        return;
    }

    pg_error_write(pg_error_entry(
        'warning',
        'E_USER_WARNING',
        'Page revisions: ' . $step . ' failed: ' . mysqli_error(db::$con),
        __FILE__,
        __LINE__,
        array()));
}

/**
 * Which revisions of a page have expired: the ids among `$rows` that are
 * beyond the last `$keep` revisions AND older than `$days` days. A revision
 * that either rule keeps stays; so the newest `$keep` rows never go, nor does
 * any row younger than the period, whatever their count. `$keep` 0 means the
 * rows handed in are all past the count rule already.
 *
 * @param array $rows  the page's revisions newest first: revision_id, created_at
 * @param int   $keep
 * @param int   $days
 * @param int   $now
 * @return array<int,int> revision ids to delete
 */
function pg_page_revisions_expired($rows, $keep, $days, $now)
{
    $keep   = max(0, (int) $keep);
    $cutoff = (int) $now - max(1, (int) $days) * 86400;
    $out    = array();
    $index  = 0;

    foreach ((array) $rows as $row) {
        $index++;

        if ($index <= $keep) {
            continue;
        }

        if ((int) $row['created_at'] >= $cutoff) {
            continue;
        }

        $out[] = (int) $row['revision_id'];
    }

    return $out;
}

/**
 * Removes the expired revisions of one page (pg_page_revisions_expired()).
 *
 * @param int $page_id
 * @return int rows removed
 */
function pg_page_revisions_trim($page_id)
{
    $page_id = (int) $page_id;

    if ($page_id <= 0 || !pg_page_revisions_ready()) {
        return 0;
    }

    $retention = pg_page_revisions_retention();

    // Only the rows the count rule does not protect are read: the first
    // `keep` rows of a page can never expire.
    $rows = db_items(
        "SELECT revision_id, created_at
         FROM page_revisions
         WHERE page_id = '$page_id'
         ORDER BY revision_no DESC
         LIMIT " . (int) $retention['keep'] . ", 1000");

    if (!$rows) {
        return 0;
    }

    // The rows read start after the kept ones, so the count rule has been
    // applied by the OFFSET; what is left is judged by age alone.
    $expired = pg_page_revisions_expired($rows, 0, $retention['days'], time());

    if (!$expired) {
        return 0;
    }

    db("DELETE FROM page_revisions WHERE revision_id IN (" . implode(',', $expired) . ")");

    return count($expired);
}

/**
 * The revisions of a page, newest first, without their trees: for the
 * History panel and the API. `$before_no` pages backwards.
 *
 * @param int $page_id
 * @param int $limit
 * @param int $before_no only revisions below this number
 * @return array rows: revision_no, source, created_at, user_id, user_username, tree_code_hash, settings (decoded), size
 */
function pg_page_revisions_list($page_id, $limit = 50, $before_no = 0)
{
    $page_id = (int) $page_id;

    if ($page_id <= 0 || !pg_page_revisions_ready()) {
        return array();
    }

    $limit     = max(1, min(200, (int) $limit));
    $before_no = (int) $before_no;

    $rows = db_items(
        "SELECT
            r.revision_no, r.source, r.created_at, r.user_id, r.tree_code_hash,
            r.settings_json, LENGTH(r.page_tree_json) AS size,
            user.user_username
         FROM page_revisions AS r
         LEFT JOIN user ON user.user_id = r.user_id
         WHERE r.page_id = '$page_id'"
         . ($before_no > 0 ? " AND r.revision_no < '$before_no'" : '') . "
         ORDER BY r.revision_no DESC
         LIMIT $limit");

    $out = array();

    foreach ((array) $rows as $row) {
        $settings = json_decode((string) $row['settings_json'], true);

        $out[] = array(
            'revision_no'    => (int) $row['revision_no'],
            'source'         => (string) $row['source'],
            'created_at'     => (int) $row['created_at'],
            'user_id'        => (int) $row['user_id'],
            'user_username'  => (string) ($row['user_username'] ?? ''),
            'tree_code_hash' => (string) $row['tree_code_hash'],
            'settings'       => is_array($settings) ? $settings : array(),
            'size'           => (int) $row['size'],
        );
    }

    return $out;
}

/**
 * One revision of a page, tree included; null when there is none.
 *
 * @param int $page_id
 * @param int $revision_no
 * @return array|null
 */
function pg_page_revision_get($page_id, $revision_no)
{
    $page_id     = (int) $page_id;
    $revision_no = (int) $revision_no;

    if ($page_id <= 0 || $revision_no <= 0 || !pg_page_revisions_ready()) {
        return null;
    }

    $row = db_item(
        "SELECT
            r.revision_id, r.page_id, r.style_id, r.revision_no, r.page_tree_json, r.tree_hash,
            r.tree_code_hash, r.settings_json, r.user_id, r.source, r.created_at,
            user.user_username
         FROM page_revisions AS r
         LEFT JOIN user ON user.user_id = r.user_id
         WHERE r.page_id = '$page_id' AND r.revision_no = '$revision_no'
         LIMIT 1");

    if (!$row) {
        return null;
    }

    $settings = json_decode((string) $row['settings_json'], true);

    return array(
        'revision_id'    => (int) $row['revision_id'],
        'page_id'        => (int) $row['page_id'],
        'style_id'       => (int) $row['style_id'],
        'revision_no'    => (int) $row['revision_no'],
        'tree_json'      => (string) $row['page_tree_json'],
        'tree_hash'      => (string) $row['tree_hash'],
        'tree_code_hash' => (string) $row['tree_code_hash'],
        'settings'       => is_array($settings) ? $settings : array(),
        'user_id'        => (int) $row['user_id'],
        'user_username'  => (string) ($row['user_username'] ?? ''),
        'source'         => (string) $row['source'],
        'created_at'     => (int) $row['created_at'],
    );
}

/**
 * Where the daily sweep keeps when it last finished.
 *
 * @return string
 */
function pg_page_revisions_sweep_file()
{
    return PG_FUNCTIONS_DIR . '/data/temp/' . PG_PAGE_REVISIONS_SWEEP_STATE;
}

/**
 * Whether the daily sweep is owed: it has not run in the last day.
 *
 * @return bool
 */
function pg_page_revisions_sweep_due()
{
    if (!pg_page_revisions_ready()) {
        return false;
    }

    $state = @json_decode((string) @file_get_contents(pg_page_revisions_sweep_file()), true);
    $done  = (is_array($state) && isset($state['done_at'])) ? (int) $state['done_at'] : 0;

    return $done < (time() - 86400);
}

/**
 * The daily pass (job.php): the rows of pages that no longer exist go, then
 * every page that has more revisions than the count rule keeps is trimmed by
 * the age rule. Writes the state file when through.
 *
 * @return array orphans, trimmed (rows removed), pages (trimmed)
 */
function pg_page_revisions_sweep()
{
    $out = array('orphans' => 0, 'trimmed' => 0, 'pages' => 0);

    if (!pg_page_revisions_ready()) {
        return $out;
    }

    // A page's rows outlive it on every delete path (the bin's purge, the
    // page screens, a design's delete); this is where they are collected.
    db("DELETE FROM page_revisions
        WHERE NOT EXISTS (SELECT 1 FROM page WHERE page.page_id = page_revisions.page_id)
        LIMIT 5000");

    $out['orphans'] = max(0, (int) mysqli_affected_rows(db::$con));

    $retention = pg_page_revisions_retention();

    $pages = db_items(
        "SELECT page_id
         FROM page_revisions
         GROUP BY page_id
         HAVING COUNT(*) > " . (int) $retention['keep'] . "
         LIMIT 1000");

    foreach ((array) $pages as $row) {
        $removed = pg_page_revisions_trim((int) $row['page_id']);

        if ($removed > 0) {
            $out['trimmed'] += $removed;
            $out['pages']++;
        }
    }

    @file_put_contents(pg_page_revisions_sweep_file(), json_encode(array('done_at' => time())), LOCK_EX);

    return $out;
}
