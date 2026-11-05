<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * The content import framework (2026.11.0, 11.0.156): a WordPress export
 * (WXR), a WooCommerce or a Shopify CSV is uploaded on import.php, read once
 * as a dry run, mapped, and then written in resumable chunks.
 *
 * - imports: one row per run (kind, file, status, mapping, counts, report).
 * - import_items: every row a run made, by kind and source key. The source
 *   key lets a resumed or repeated run skip what is already there, and the
 *   undo walks these rows within 30 days; older rows are swept, the imports
 *   row stays as the report.
 *
 * A reader lives in includes/import/<kind>.php and defines
 * pg_import_<kind>_open($file) (the first cursor, an array that survives
 * json_encode()), pg_import_<kind>_next(&$cursor) (the next item or null at
 * the end) and pg_import_<kind>_plan($item) (the dry run's line for it).
 * Optional: pg_import_<kind>_mapping() lists the mapping rows the screen
 * asks for. An item is array('kind' => writer kind, 'source_key' => unique
 * within the export, 'title' => label, 'data' => what the writer needs).
 *
 * A writer lives in includes/import/write_<kind>.php and defines
 * pg_import_write_<kind>($item, $import) returning array('result' =>
 * 'made'|'skipped'|'failed', 'id' => the new row's id, 'reason' => text) and
 * pg_import_unwrite_<kind>($item_id) removing one made row through the
 * kind's own delete path.
 *
 * The place of a running import is data/temp/import_<id>.json, written after
 * every item, so a killed request carries on after the last item written.
 * Every reader asks pg_import_ready() first: files land before the upgrade.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_FUNCTIONS_DIR')) {
    exit;
}

// Days an import can be undone after it finished.
const PG_IMPORT_UNDO_DAYS = 30;

// Skipped and failed lines the report keeps in full; the rest are counted.
const PG_IMPORT_REPORT_LINES = 2000;

// Days an upload that was checked but never started keeps its file; the
// general job then removes the row and the file.
const PG_IMPORT_UNSTARTED_DAYS = 7;

/**
 * The two tables come with the 2026.11.0 upgrade (11.0.156).
 *
 * @return bool
 */
function pg_import_ready()
{
    return pg_schema_has('imports') && pg_schema_has('import_items');
}

/**
 * The export kinds, as the imports.kind ENUM lists them.
 *
 * @return array<string,string> kind => label
 */
function pg_import_kinds()
{
    return array(
        'wordpress'   => lang('WordPress export (WXR)'),
        'woocommerce' => lang('WooCommerce products (CSV)'),
        'shopify'     => lang('Shopify products or customers (CSV)'),
    );
}

/**
 * What an import writes, as the import_items.kind ENUM lists them.
 *
 * @return array<string,string> kind => label
 */
function pg_import_writer_kinds()
{
    return array(
        'record'   => lang('Records'),
        'page'     => lang('Pages'),
        'file'     => lang('Files'),
        'comment'  => lang('Comments'),
        'contact'  => lang('Contacts'),
        'product'  => lang('Products'),
        'redirect' => lang('Redirects'),
        'user'     => lang('Authors'),
    );
}

/**
 * The kind of an export from its first bytes, '' when none fits. A WXR file
 * is RSS carrying the WordPress export namespace; a CSV is told apart by its
 * header line, read case-insensitively. Pure.
 *
 * @param string $head the file's first few kilobytes
 * @return string
 */
function pg_import_detect_kind($head)
{
    $head = (string) $head;

    // A byte order mark before the first character.
    if (strncmp($head, "\xEF\xBB\xBF", 3) === 0) {
        $head = substr($head, 3);
    }

    if ((stripos($head, '<rss') !== false) && (stripos($head, 'wordpress.org/export/') !== false)) {
        return 'wordpress';
    }

    $line = strtok(str_replace("\r", "\n", ltrim($head)), "\n");

    if (!is_string($line) || ($line === '')) {
        return '';
    }

    $cells = array_map(function ($cell) {
        return strtolower(trim($cell, " \t\"'"));
    }, preg_split('/[,;\t]/', $line));

    $has = function ($names) use ($cells) {
        foreach ($names as $name) {
            if (!in_array($name, $cells, true)) {
                return false;
            }
        }

        return true;
    };

    // Shopify products: Handle with Body (HTML); customers: the name columns
    // with the marketing consent column (named "Accepts Marketing" before
    // 2023, "Accepts Email Marketing" after).
    if ($has(array('handle', 'body (html)'))) {
        return 'shopify';
    }

    if ($has(array('first name', 'last name', 'email')) && ($has(array('accepts marketing')) || $has(array('accepts email marketing')))) {
        return 'shopify';
    }

    // WooCommerce's product exporter: ID, Type, SKU and Regular price.
    if ($has(array('id', 'type', 'sku', 'regular price'))) {
        return 'woocommerce';
    }

    return '';
}

/**
 * The reader file of a kind, '' when the kind has none on this site yet.
 *
 * @param string $kind
 * @return string
 */
function pg_import_reader_file($kind)
{
    if (!array_key_exists((string) $kind, pg_import_kinds())) {
        return '';
    }

    $file = PG_FUNCTIONS_DIR . '/includes/import/' . $kind . '.php';

    return is_file($file) ? $file : '';
}

/**
 * Loads a kind's reader; false when it has none or a function is missing.
 *
 * @param string $kind
 * @return bool
 */
function pg_import_reader_load($kind)
{
    $file = pg_import_reader_file($kind);

    if ($file === '') {
        return false;
    }

    require_once($file);

    foreach (array('open', 'next', 'plan') as $part) {
        if (!function_exists('pg_import_' . $kind . '_' . $part)) {
            return false;
        }
    }

    return true;
}

/**
 * Loads a writer kind's functions; false when this site has none yet.
 *
 * @param string $kind
 * @return bool
 */
function pg_import_writer_load($kind)
{
    if (!array_key_exists((string) $kind, pg_import_writer_kinds())) {
        return false;
    }

    $file = PG_FUNCTIONS_DIR . '/includes/import/write_' . $kind . '.php';

    if (is_file($file)) {
        require_once($file);
    }

    return function_exists('pg_import_write_' . $kind);
}

/**
 * Why the writer of $kind would skip an item, '' when it would write it or
 * has no check of its own (pg_import_precheck_<kind>($item) in its file):
 * the dry run asks it so an item the run will refuse (an e-mail address or
 * a SKU the site holds already) is not counted as new.
 *
 * @param string $kind
 * @param array  $item
 * @return string
 */
function pg_import_precheck($kind, $item)
{
    $check = 'pg_import_precheck_' . $kind;

    if (!pg_import_writer_load($kind) || !function_exists($check)) {
        return '';
    }

    return trim((string) call_user_func($check, $item));
}

/**
 * A writer's answer in the one shape the run counts, whatever it returned.
 * Pure.
 *
 * @param mixed $answer
 * @return array{result:string,id:int,reason:string}
 */
function pg_import_writer_answer($answer)
{
    if (!is_array($answer) || !in_array($answer['result'] ?? '', array('made', 'skipped', 'failed'), true)) {
        return array('result' => 'failed', 'id' => 0, 'reason' => lang('The writer gave no answer.'));
    }

    $id = (int) ($answer['id'] ?? 0);

    // A made row without its id could never be undone.
    if (($answer['result'] === 'made') && ($id <= 0)) {
        return array('result' => 'failed', 'id' => 0, 'reason' => lang('The writer made a row but did not say which.'));
    }

    return array('result' => $answer['result'], 'id' => $id, 'reason' => trim((string) ($answer['reason'] ?? '')));
}

/**
 * An empty tally: made, skipped and failed per writer kind.
 *
 * @return array<string,array<string,int>>
 */
function pg_import_counts_empty()
{
    return array();
}

/**
 * Adds one result to the tally. Pure.
 *
 * @param array  $counts
 * @param string $kind
 * @param string $result made, skipped or failed
 * @return array
 */
function pg_import_tally($counts, $kind, $result)
{
    $counts = is_array($counts) ? $counts : array();
    $kind = (string) $kind;

    if (!isset($counts[$kind]) || !is_array($counts[$kind])) {
        $counts[$kind] = array('made' => 0, 'skipped' => 0, 'failed' => 0);
    }

    if (isset($counts[$kind][$result])) {
        $counts[$kind][$result]++;
    }

    return $counts;
}

/**
 * Totals over every kind of a tally. Pure.
 *
 * @param array $counts
 * @return array{made:int,skipped:int,failed:int}
 */
function pg_import_totals($counts)
{
    $totals = array('made' => 0, 'skipped' => 0, 'failed' => 0);

    foreach ((array) $counts as $row) {
        foreach ($totals as $key => $value) {
            $totals[$key] = $value + (int) ($row[$key] ?? 0);
        }
    }

    return $totals;
}

/**
 * Adds a skipped or failed line to the report: kept in full up to
 * PG_IMPORT_REPORT_LINES, counted beyond. Made rows are not listed, the
 * tally and import_items hold them, unless the writer gave a note (a
 * picture kept at its old address). Pure.
 *
 * @param array  $report array('lines' => array, 'more' => int)
 * @param string $result
 * @param array  $item
 * @param string $reason
 * @return array
 */
function pg_import_report_add($report, $result, $item, $reason)
{
    $report = is_array($report) ? $report : array();
    $report += array('lines' => array(), 'more' => 0);

    if (($result === 'made') && (trim((string) $reason) === '')) {
        return $report;
    }

    if (count($report['lines']) >= PG_IMPORT_REPORT_LINES) {
        $report['more']++;

        return $report;
    }

    $report['lines'][] = array(
        'result' => (string) $result,
        'kind'   => (string) ($item['kind'] ?? ''),
        'key'    => mb_substr((string) ($item['source_key'] ?? ''), 0, 191),
        'title'  => mb_substr((string) ($item['title'] ?? ''), 0, 200),
        'reason' => mb_substr((string) $reason, 0, 500),
    );

    return $report;
}

/**
 * Whether a finished import can still be undone at $now. Pure.
 *
 * @param string $status
 * @param int    $finished_at
 * @param int    $now
 * @return bool
 */
function pg_import_undo_open($status, $finished_at, $now)
{
    if (!in_array((string) $status, array('done', 'failed', 'running'), true)) {
        return false;
    }

    // A run stopped halfway has no end time; it can be undone at once.
    if ((int) $finished_at === 0) {
        return true;
    }

    return ((int) $now - (int) $finished_at) < (PG_IMPORT_UNDO_DAYS * 86400);
}

/**
 * The mapping the screen posted, kept to the rows the reader asked for and
 * the choices offered for each; anything else is dropped. Pure.
 *
 * @param array $rows    name => array('choices' => array(value => label), 'required' => bool)
 * @param array $posted  name => value
 * @return array{mapping:array<string,string>,missing:string[]}
 */
function pg_import_mapping_clean($rows, $posted)
{
    $mapping = array();
    $missing = array();

    foreach ((array) $rows as $name => $row) {
        $value = (isset($posted[$name]) && is_scalar($posted[$name])) ? (string) $posted[$name] : '';
        $choices = (array) ($row['choices'] ?? array());

        if (($value !== '') && array_key_exists($value, $choices)) {
            $mapping[$name] = $value;
        } elseif (!empty($row['required'])) {
            $missing[] = (string) $name;
        }
    }

    return array('mapping' => $mapping, 'missing' => $missing);
}

/**
 * Whether a form field of $type may take a mapping row whose writer fills
 * only $types (all when empty). An information field holds no value and
 * never does. Pure.
 *
 * @param string   $type
 * @param string[] $types
 * @return bool
 */
function pg_import_field_fits($type, $types)
{
    $type = (string) $type;

    if (($type === '') || ($type === 'information')) {
        return false;
    }

    return !$types || in_array($type, (array) $types, true);
}

/**
 * The choices of a tree picker (folders, product groups): id => label in
 * tree order, siblings in the order given, each label its path ("Shop ›
 * private"), so two folders of one name tell apart. A lone top node (the
 * root folder) leads every path, so it is left out of them. A node in
 * $left_out drops with everything below it. Pure.
 *
 * @param array $rows     id => array('parent' => id, 'name' => text), siblings in order
 * @param int[] $left_out ids whose subtree is not offered
 * @return array<string,string>
 */
function pg_import_tree_choices($rows, $left_out)
{
    $children = array();

    foreach ((array) $rows as $id => $row) {
        $children[(int) ($row['parent'] ?? 0)][] = (int) $id;
    }

    $left_out = array_flip(array_map('intval', (array) $left_out));
    $choices = array();
    $seen = array();

    $lone_top = (count($children[0] ?? array()) === 1);

    // Depth first from the top nodes; $path holds the names that lead the
    // labels below.
    $walk = function ($parent, $path) use (&$walk, &$choices, &$seen, $children, $left_out, $rows, $lone_top) {
        foreach ($children[$parent] ?? array() as $id) {
            if (isset($left_out[$id]) || isset($seen[$id])) {
                continue;
            }

            $seen[$id] = true;
            $name = (string) ($rows[$id]['name'] ?? '');
            $own = array_merge($path, array($name));
            $choices[(string) $id] = implode(' › ', $own);
            $walk($id, (($parent === 0) && $lone_top) ? array() : $own);
        }
    };

    $walk(0, array());

    return $choices;
}

/**
 * A weight in the unit an export names, in pounds: products.weight holds
 * pounds (the product screen converts typed kilograms the same way), so a
 * shop's kilograms or grams stored as written ship as less than half their
 * weight. Four places, the column's precision; '' for none, zero or less.
 * An unknown unit is taken as kilograms. Pure.
 *
 * @param float|int $figure
 * @param string    $unit kg, g, lb, lbs or oz
 * @return string
 */
function pg_import_weight_pounds($figure, $unit)
{
    $figure = (float) $figure;

    if (!($figure > 0)) {
        return '';
    }

    $per_pound = array('g' => 453.59237, 'kg' => 0.45359237, 'lb' => 1, 'lbs' => 1, 'oz' => 16);
    $unit = strtolower(trim((string) $unit));
    $pounds = round($figure / (isset($per_pound[$unit]) ? $per_pound[$unit] : $per_pound['kg']), 4);

    return ($pounds > 0) ? rtrim(rtrim(number_format($pounds, 4, '.', ''), '0'), '.') : '';
}

// ---- Files ---------------------------------------------------------------

// The uploaded export, kept until the import is undone or swept.
function pg_import_source_path($import_id, $file_name)
{
    $extension = strtolower(pathinfo(pg_basename((string) $file_name), PATHINFO_EXTENSION));

    if (!in_array($extension, array('xml', 'csv', 'txt', 'zip'), true)) {
        $extension = 'dat';
    }

    return PG_FUNCTIONS_DIR . '/data/temp/import_' . (int) $import_id . '_source.' . $extension;
}

// Where a running import keeps its place. Losing the file (the clean up
// tool empties data/temp) starts the reader from the first item again; the
// source keys already written are then skipped.
function pg_import_cursor_path($import_id)
{
    return PG_FUNCTIONS_DIR . '/data/temp/import_' . (int) $import_id . '.json';
}

/**
 * The saved place of an import, null when there is none.
 *
 * @param int $import_id
 * @return array|null
 */
function pg_import_cursor_read($import_id)
{
    $path = pg_import_cursor_path($import_id);

    if (!is_file($path)) {
        return null;
    }

    $state = json_decode((string) @file_get_contents($path), true);

    return (is_array($state) && isset($state['cursor']) && is_array($state['cursor'])) ? $state : null;
}

function pg_import_cursor_write($import_id, $state)
{
    $path = pg_import_cursor_path($import_id);

    // Written next to it and renamed, so a killed request never leaves half
    // a file that would start the reader over.
    if (@file_put_contents($path . '.tmp', json_encode($state)) !== false) {
        @rename($path . '.tmp', $path);
    }
}

// ---- Rows ----------------------------------------------------------------

/**
 * One imports row with its JSON columns decoded, null when not found.
 *
 * @param int $import_id
 * @return array|null
 */
function pg_import_get($import_id)
{
    $row = db_item("SELECT * FROM imports WHERE id = '" . (int) $import_id . "'");

    if (!is_array($row)) {
        return null;
    }

    foreach (array('mapping', 'counts', 'report') as $column) {
        $decoded = json_decode((string) $row[$column], true);
        $row[$column] = is_array($decoded) ? $decoded : array();
    }

    return $row;
}

/**
 * A new imports row for an uploaded file.
 *
 * @param string $kind
 * @param string $file_name the name the person gave
 * @param int    $user_id
 * @return int
 */
function pg_import_create($kind, $file_name, $user_id)
{
    db("INSERT INTO imports SET
        kind = '" . e($kind) . "',
        file_name = '" . e(mb_substr(pg_basename((string) $file_name), 0, 255)) . "',
        status = 'uploaded',
        user_id = '" . (int) $user_id . "',
        created_at = UNIX_TIMESTAMP()");

    return (int) mysqli_insert_id(db::$con);
}

function pg_import_save($import_id, $columns)
{
    $sets = array();

    foreach ($columns as $column => $value) {
        if (is_array($value)) {
            $value = json_encode($value);
        }

        $sets[] = "`" . $column . "` = '" . e((string) $value) . "'";
    }

    if ($sets) {
        db("UPDATE imports SET " . implode(', ', $sets) . " WHERE id = '" . (int) $import_id . "'");
    }
}

/**
 * The imports earlier runs of the same kind made that are not undone: a
 * second upload of the same export skips what the first one wrote.
 *
 * @param array $import
 * @return int[]
 */
function pg_import_related_ids($import)
{
    $ids = array_map('intval', (array) db_values("SELECT id FROM imports
        WHERE kind = '" . e($import['kind']) . "' AND status <> 'undone' AND id <= '" . (int) $import['id'] . "'"));

    return $ids ?: array((int) $import['id']);
}

/**
 * The row an earlier item of these imports made, 0 when none: writers use it
 * to find the record a comment belongs to, and the run to skip a source key
 * already written.
 *
 * @param int[]  $import_ids
 * @param string $kind
 * @param string $source_key
 * @return int
 */
function pg_import_item_id($import_ids, $kind, $source_key)
{
    $ids = implode(',', array_map('intval', (array) $import_ids));

    if ($ids === '') {
        return 0;
    }

    return (int) db_value("SELECT item_id FROM import_items
        WHERE import_id IN (" . $ids . ") AND kind = '" . e($kind) . "' AND source_key = '" . e(mb_substr((string) $source_key, 0, 191)) . "'
        LIMIT 1");
}

// ---- The run ---------------------------------------------------------------

/**
 * One item through its writer: skipped when these imports wrote its source
 * key already, else written and, when made, listed in import_items.
 *
 * @param array $import
 * @param int[] $related
 * @param array $item
 * @return array{result:string,id:int,reason:string}
 */
function pg_import_write($import, $related, $item)
{
    $kind = (string) ($item['kind'] ?? '');
    $key = mb_substr(trim((string) ($item['source_key'] ?? '')), 0, 191);

    if ($key === '') {
        return array('result' => 'failed', 'id' => 0, 'reason' => lang('The item has no source id.'));
    }

    if (!pg_import_writer_load($kind)) {
        return array('result' => 'failed', 'id' => 0, 'reason' => lang('This site cannot write this kind of item yet.'));
    }

    if (pg_import_item_id($related, $kind, $key) > 0) {
        return array('result' => 'skipped', 'id' => 0, 'reason' => lang('Imported already.'));
    }

    try {
        $answer = pg_import_writer_answer(call_user_func('pg_import_write_' . $kind, $item, $import));
    } catch (\Throwable $error) {
        $answer = array('result' => 'failed', 'id' => 0, 'reason' => $error->getMessage());
    }

    if ($answer['result'] === 'made') {
        db("INSERT IGNORE INTO import_items SET
            import_id = '" . (int) $import['id'] . "',
            kind = '" . e($kind) . "',
            item_id = '" . (int) $answer['id'] . "',
            source_key = '" . e($key) . "'");
    }

    return $answer;
}

/**
 * Works an import for at most $budget seconds from where it stopped. Only
 * one request works an import at a time (a named database lock), so the
 * screen's short requests and the general job never write the same item
 * twice.
 *
 * @param int $import_id
 * @param int $budget seconds
 * @return array{status:string,counts:array,position:int,busy:bool}
 */
function pg_import_run($import_id, $budget)
{
    $import_id = (int) $import_id;
    $lock = 'pg_import_' . $import_id;
    $answer = array('status' => '', 'counts' => array(), 'position' => 0, 'busy' => false);

    if ((int) db_value("SELECT GET_LOCK('" . e($lock) . "', 0)") !== 1) {
        $answer['busy'] = true;

        return $answer;
    }

    $import = pg_import_get($import_id);

    if (($import === null) || ($import['status'] !== 'running')) {
        db("SELECT RELEASE_LOCK('" . e($lock) . "')");
        $answer['status'] = ($import === null) ? '' : $import['status'];
        $answer['counts'] = ($import === null) ? array() : $import['counts'];

        return $answer;
    }

    $kind = $import['kind'];
    $source = pg_import_source_path($import_id, $import['file_name']);

    if (!pg_import_reader_load($kind) || !is_file($source)) {
        pg_import_save($import_id, array('status' => 'failed', 'finished_at' => time()));
        db("SELECT RELEASE_LOCK('" . e($lock) . "')");
        $answer['status'] = 'failed';
        $answer['counts'] = $import['counts'];

        return $answer;
    }

    $state = pg_import_cursor_read($import_id);

    if ($state === null) {
        $state = array(
            'cursor'   => (array) call_user_func('pg_import_' . $kind . '_open', $source),
            'position' => 0,
            'counts'   => pg_import_counts_empty(),
            'report'   => array('lines' => array(), 'more' => 0),
        );
    }

    $related = pg_import_related_ids($import);
    $next = 'pg_import_' . $kind . '_next';
    $stop = microtime(true) + max(1, (int) $budget);
    $finished = false;

    while (microtime(true) < $stop) {

        $cursor = $state['cursor'];
        $item = $next($cursor);
        $state['cursor'] = (array) $cursor;

        if ($item === null) {
            $finished = true;
            break;
        }

        $state['position']++;
        $result = pg_import_write($import, $related, (array) $item);
        $state['counts'] = pg_import_tally($state['counts'], (string) ($item['kind'] ?? ''), $result['result']);
        $state['report'] = pg_import_report_add($state['report'], $result['result'], (array) $item, $result['reason']);

        // What the old site showed at an address of its own gets a 301 from
        // that path (includes/import/write_redirect.php).
        if (($result['result'] === 'made') && pg_import_writer_load('redirect')) {
            $redirect = pg_import_redirect_after($import, (array) $item, (int) $result['id']);

            if ($redirect !== null) {
                $state['counts'] = pg_import_tally($state['counts'], 'redirect', $redirect['result']);
                $state['report'] = pg_import_report_add($state['report'], $redirect['result'], array('kind' => 'redirect') + (array) $item, $redirect['reason']);
            }
        }

        pg_import_cursor_write($import_id, $state);
    }

    $columns = array(
        'counts' => $state['counts'],
        'cursor' => (string) $state['position'],
    );

    if ($finished) {
        $columns['status'] = 'done';
        $columns['finished_at'] = time();
        $columns['report'] = $state['report'];
    }

    pg_import_save($import_id, $columns);

    if ($finished) {
        @unlink(pg_import_cursor_path($import_id));
    }

    db("SELECT RELEASE_LOCK('" . e($lock) . "')");

    return array(
        'status'   => $finished ? 'done' : 'running',
        'counts'   => $state['counts'],
        'position' => (int) $state['position'],
        'busy'     => false,
    );
}

/**
 * The dry run: the reader walks the file for at most $budget seconds and
 * every item is counted by kind and checked against what these imports
 * wrote already; the first $sample lines are kept for the screen. Nothing
 * is written but the imports row.
 *
 * @param int $import_id
 * @param int $budget
 * @param int $sample
 * @return array{counts:array,lines:array,complete:bool}
 */
function pg_import_dry_run($import_id, $budget, $sample = 50)
{
    $import = pg_import_get($import_id);
    $dry = array('counts' => array(), 'lines' => array(), 'complete' => false);

    if (($import === null) || !pg_import_reader_load($import['kind'])) {
        return $dry;
    }

    $kind = $import['kind'];
    $cursor = (array) call_user_func('pg_import_' . $kind . '_open', pg_import_source_path($import_id, $import['file_name']));
    $related = pg_import_related_ids($import);
    $next = 'pg_import_' . $kind . '_next';
    $stop = microtime(true) + max(1, (int) $budget);

    while (microtime(true) < $stop) {

        $item = $next($cursor);

        if ($item === null) {
            $dry['complete'] = true;
            break;
        }

        $item_kind = (string) ($item['kind'] ?? '');
        $seen = pg_import_item_id($related, $item_kind, (string) ($item['source_key'] ?? '')) > 0;
        $refusal = $seen ? '' : pg_import_precheck($item_kind, $item);

        // The dry run's tally: made is new, skipped imported already, and
        // failed what the writer will refuse (shown as "Will be skipped").
        $dry['counts'] = pg_import_tally($dry['counts'], $item_kind, $seen ? 'skipped' : (($refusal !== '') ? 'failed' : 'made'));

        if (count($dry['lines']) < $sample) {
            $line = (array) call_user_func('pg_import_' . $kind . '_plan', $item);
            $line['kind'] = $item_kind;
            $line['seen'] = $seen;
            $line['refusal'] = $refusal;
            $dry['lines'][] = $line;
        }
    }

    pg_import_save($import_id, array('status' => 'dry_run', 'report' => array('dry' => $dry)));

    return $dry;
}

/**
 * Starts a mapped import: the job and the screen's requests then work it.
 *
 * @param int   $import_id
 * @param array $mapping
 */
function pg_import_start($import_id, $mapping)
{
    @unlink(pg_import_cursor_path($import_id));

    pg_import_save($import_id, array(
        'status'     => 'running',
        'mapping'    => $mapping,
        'counts'     => array(),
        'cursor'     => '0',
        'report'     => array(),
        'started_at' => time(),
    ));
}

/**
 * Removes what an import made, through each kind's own delete path, for at
 * most $budget seconds; the screen calls it again until nothing is left.
 * A kind without a delete path keeps its rows, which are named in the
 * answer.
 *
 * closed: the import is missing or no longer undoable (undone already,
 * past the 30 days), so there is nothing to ask again. busy: another
 * request holds the import (a run step or a second undo); ask later.
 *
 * @param int $import_id
 * @param int $budget
 * @return array{removed:int,left:int,kept:int,done:bool,closed:bool,busy:bool}
 */
function pg_import_undo($import_id, $budget = 10)
{
    $import_id = (int) $import_id;
    $answer = array('removed' => 0, 'left' => 0, 'kept' => 0, 'done' => false, 'closed' => false, 'busy' => false);
    $import = pg_import_get($import_id);

    if (($import === null) || !pg_import_undo_open($import['status'], $import['finished_at'], time())) {
        $answer['done'] = true;
        $answer['closed'] = true;

        return $answer;
    }

    $lock = 'pg_import_' . $import_id;

    if ((int) db_value("SELECT GET_LOCK('" . e($lock) . "', 0)") !== 1) {
        $answer['busy'] = true;

        return $answer;
    }

    // A run still working stops here: the job does not take it up again.
    if ($import['status'] === 'running') {
        pg_import_save($import_id, array('status' => 'failed', 'finished_at' => time()));
    }

    $stop = microtime(true) + max(1, (int) $budget);
    $kept_kinds = array();
    $report = $import['report'];
    $kept_rows = 0;

    while (microtime(true) < $stop) {

        $not_kinds = $kept_kinds ? " AND kind NOT IN ('" . implode("','", array_map('e', $kept_kinds)) . "')" : '';
        $rows = db_items("SELECT id, kind, item_id FROM import_items WHERE import_id = '" . $import_id . "'" . $not_kinds . " ORDER BY id DESC LIMIT 100");

        if (!$rows) {
            break;
        }

        foreach ($rows as $row) {

            $kind = (string) $row['kind'];
            $unwrite = 'pg_import_unwrite_' . $kind;

            if (!pg_import_writer_load($kind) || !function_exists($unwrite)) {
                $kept_kinds[] = $kind;
                continue;
            }

            $unwritten = call_user_func($unwrite, (int) $row['item_id']);
            db("DELETE FROM import_items WHERE id = '" . (int) $row['id'] . "'");

            // A row someone took over after the import (an author enabled by
            // hand) is no longer the import's: it stays and the report names it.
            if (is_array($unwritten) && isset($unwritten['kept'])) {
                $report = pg_import_report_add($report, 'kept', array('kind' => $kind, 'title' => (string) $unwritten['kept']), lang('Taken over after the import, so it stays.'));
                $kept_rows++;
                continue;
            }

            $answer['removed']++;

            if (microtime(true) >= $stop) {
                break;
            }
        }
    }

    $answer['left'] = (int) db_value("SELECT COUNT(*) FROM import_items WHERE import_id = '" . $import_id . "'");

    if ($kept_kinds) {
        $answer['kept'] = (int) db_value("SELECT COUNT(*) FROM import_items WHERE import_id = '" . $import_id . "'
            AND kind IN ('" . implode("','", array_map('e', array_unique($kept_kinds))) . "')");
    }

    // Rows of a kind without a delete path stay listed, and the run keeps
    // its status, so Undo can finish once that kind's writer has one.
    $answer['done'] = ($answer['left'] === $answer['kept']);

    if ($kept_rows > 0) {
        pg_import_save($import_id, array('report' => $report));
    }

    if ($answer['left'] === 0) {
        pg_import_save($import_id, array('status' => 'undone'));
        @unlink(pg_import_cursor_path($import_id));
        @unlink(pg_import_source_path($import_id, $import['file_name']));
    }

    db("SELECT RELEASE_LOCK('" . e($lock) . "')");

    return $answer;
}

/**
 * The general job's part: a running import is worked for the tick's budget,
 * and import_items rows of imports finished more than 30 days ago go with
 * their source file. A site with no import reads one small table.
 *
 * @param int $budget
 * @return array{worked:int,swept:int}
 */
function pg_import_job_tick($budget)
{
    $answer = array('worked' => 0, 'swept' => 0);

    if (!pg_import_ready()) {
        return $answer;
    }

    $running = (int) db_value("SELECT id FROM imports WHERE status = 'running' ORDER BY id LIMIT 1");

    if ($running > 0) {
        pg_import_run($running, $budget);
        $answer['worked'] = $running;
    }

    $cutoff = time() - (PG_IMPORT_UNDO_DAYS * 86400);

    foreach ((array) db_items("SELECT id, file_name FROM imports
        WHERE status IN ('done', 'failed') AND finished_at > 0 AND finished_at < '" . (int) $cutoff . "'") as $old) {

        db("DELETE FROM import_items WHERE import_id = '" . (int) $old['id'] . "' LIMIT 5000");
        $answer['swept'] += (int) mysqli_affected_rows(db::$con);
        @unlink(pg_import_source_path($old['id'], $old['file_name']));
    }

    // An upload checked but never started made nothing; after a week its
    // row and its file (up to the upload limit in data/temp) go.
    foreach ((array) db_items("SELECT id FROM imports
        WHERE status IN ('uploaded', 'dry_run') AND created_at < '" . (int) (time() - (PG_IMPORT_UNSTARTED_DAYS * 86400)) . "'
        LIMIT 50") as $old) {
        pg_import_discard($old['id']);
    }

    return $answer;
}

/**
 * Removes an import that was never started (uploaded or checked) with its
 * file: it made nothing, so only the row and data/temp hold it. False when
 * the import is missing or was started.
 *
 * @param int $import_id
 * @return bool
 */
function pg_import_discard($import_id)
{
    $import = pg_import_get($import_id);

    if (($import === null) || !in_array($import['status'], array('uploaded', 'dry_run'), true)) {
        return false;
    }

    db("DELETE FROM imports WHERE id = '" . (int) $import['id'] . "' AND status IN ('uploaded', 'dry_run')");
    @unlink(pg_import_source_path($import['id'], $import['file_name']));
    @unlink(pg_import_cursor_path($import['id']));

    return true;
}
