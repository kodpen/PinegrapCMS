<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Workspace - the bin of workflow tables (2026.11.0, 11.0.178). A deleted
 * row leaves ws_wtable_rows for ws_wtable_row_bin, as it was, with its
 * ws_refs tags in links; a deleted table only gets ws_wtables.deleted_at, so
 * its id stays taken and the flows that name it work again after a restore.
 * Both stay for the Recycle Bin's days (config.recycle_retention_days), then
 * ws_wtable_bin_purge(), run by the workspace's recurring job, deletes
 * them for good, a few at a time (workspace_recurring_job.php).
 *
 * Whoever may edit a table sees its binned rows, restores one or deletes it
 * for good; the owner alone restores a binned table. A binned table is out
 * of every reader: ws_wtable() does not give it, so no screen, flow box or
 * API call reaches it, and it does not count in its owner's limit.
 *
 * Before the step has run nothing here is ready and a delete stays final.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_FUNCTIONS_DIR')) {
    exit;
}

/** The most binned rows the purge deletes in one run. */
define('WS_WTABLE_BIN_PURGE_ROWS', 200);

/** The most binned tables the purge deletes in one run. */
define('WS_WTABLE_BIN_PURGE_TABLES', 5);

/* ---------------------------------------------------------------------------
   Rules (pure)
   --------------------------------------------------------------------------- */

/**
 * What a binned row keeps of its links, as stored in links: the ws_refs tags
 * of its cells (type, id). Pure.
 *
 * @param array $refs each with type and id
 * @return string JSON
 */
function ws_wtable_bin_links_encode($refs)
{
    $out = array();

    foreach ((array) $refs as $ref) {
        if (is_array($ref) && isset($ref['type'], $ref['id']) && ((string) $ref['type'] !== '') && ((int) $ref['id'] > 0)) {
            $out[] = array('type' => (string) $ref['type'], 'id' => (int) $ref['id']);
        }
    }

    $json = json_encode(array('refs' => $out), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

    return ($json === false) ? '{"refs":[]}' : $json;
}

/**
 * The links of a binned row as stored, read back. Anything that is not the
 * shape written gives no link. Pure.
 *
 * @param mixed $json
 * @return array refs (each type, id)
 */
function ws_wtable_bin_links_decode($json)
{
    $data = is_string($json) ? json_decode($json, true) : null;
    $refs = array();

    foreach ((is_array($data) && isset($data['refs']) && is_array($data['refs'])) ? $data['refs'] : array() as $ref) {
        if (is_array($ref) && isset($ref['type'], $ref['id']) && is_string($ref['type']) && ($ref['type'] !== '') && ((int) $ref['id'] > 0)) {
            $refs[] = array('type' => $ref['type'], 'id' => (int) $ref['id']);
        }
    }

    return array('refs' => $refs);
}

/**
 * When a binned row or table is deleted for good. Pure.
 *
 * @param int $deleted_at
 * @param int $days the Recycle Bin's days
 * @return int
 */
function ws_wtable_bin_expires_at($deleted_at, $days)
{
    return (int) $deleted_at + (max(1, (int) $days) * 86400);
}

/**
 * Before when a binned row or table is deleted for good: those binned
 * earlier are due. Pure.
 *
 * @param int $now
 * @param int $days
 * @return int
 */
function ws_wtable_bin_cutoff($now, $days)
{
    return (int) $now - (max(1, (int) $days) * 86400);
}

/* ---------------------------------------------------------------------------
   Readiness and settings
   --------------------------------------------------------------------------- */

/**
 * Has the step that brings the bin run?
 *
 * @return bool
 */
function ws_wtable_bin_ready()
{
    static $ready = null;

    if ($ready === null) {
        $ready = ws_wtables_ready()
            && pg_schema_has('ws_wtables', 'deleted_at')
            && pg_schema_has('ws_wtables', 'deleted_by')
            && pg_schema_has('ws_wtable_row_bin', 'links');
    }

    return $ready;
}

/**
 * The condition that leaves binned tables out, to add to a WHERE on
 * ws_wtables; empty before the step has run.
 *
 * @param string $alias the table's alias, '' for none
 * @return string
 */
function ws_wtable_live_sql($alias = '')
{
    if (!ws_wtable_bin_ready()) {
        return '';
    }

    return " AND " . (($alias !== '') ? $alias . '.' : '') . "deleted_at = 0";
}

/**
 * How many days the bin keeps: the panel Recycle Bin's setting, 30 when the
 * site has none.
 *
 * @return int
 */
function ws_wtable_bin_days()
{
    static $days = null;

    if ($days === null) {
        $days = pg_schema_has('config', 'recycle_retention_days') ? (int) db_value("SELECT recycle_retention_days FROM config") : 0;
        $days = ($days > 0) ? $days : 30;
    }

    return $days;
}

/* ---------------------------------------------------------------------------
   Rows
   --------------------------------------------------------------------------- */

/**
 * Put a copy of a row into the bin, with its ws_refs tags. The row itself is
 * deleted by the caller after; a copy whose row was not deleted is taken out
 * again (ws_wtable_row_bin_drop()).
 *
 * @param array $row      the ws_wtable_rows row as stored
 * @param int   $actor_id who deleted it
 * @return int the bin id, 0 when it was not written
 */
function ws_wtable_row_bin_put($row, $actor_id)
{
    if (!ws_wtable_bin_ready() || !is_array($row) || ((int) ($row['id'] ?? 0) <= 0)) {
        return 0;
    }

    $refs = array();

    if (function_exists('ws_ref_sources_ready') && ws_ref_sources_ready()) {
        foreach ((array) db_items("SELECT ref_type, ref_id FROM ws_refs WHERE source_type = 'wtable_row' AND source_id = '" . (int) $row['id'] . "' ORDER BY id") as $ref) {
            $refs[] = array('type' => (string) $ref['ref_type'], 'id' => (int) $ref['ref_id']);
        }
    }

    // The row keeps its stable number in the bin, for a restore.
    db("INSERT INTO ws_wtable_row_bin (row_id, table_id, position, cells, revision, created_by, created_at, updated_by, updated_at, links, deleted_by, deleted_at" . ws_wtable_number_field(0) . ")
        VALUES ('" . (int) $row['id'] . "', '" . (int) $row['table_id'] . "', '" . (int) $row['position'] . "', '" . e((string) ($row['cells'] ?? '{}')) . "',
            '" . (int) $row['revision'] . "', '" . (int) $row['created_by'] . "', '" . (int) $row['created_at'] . "',
            '" . (int) $row['updated_by'] . "', '" . (int) $row['updated_at'] . "', '" . e(ws_wtable_bin_links_encode($refs)) . "',
            '" . (int) $actor_id . "', '" . time() . "'" . ws_wtable_number_value((int) ($row['number'] ?? 0)) . ")");

    return (int) mysqli_insert_id(db::$con);
}

/**
 * Take one entry out of the bin for good.
 *
 * @param int $bin_id
 * @return bool whether this request removed it
 */
function ws_wtable_row_bin_drop($bin_id)
{
    if (!ws_wtable_bin_ready() || ((int) $bin_id <= 0)) {
        return false;
    }

    db("DELETE FROM ws_wtable_row_bin WHERE id = '" . (int) $bin_id . "'");

    return ((int) mysqli_affected_rows(db::$con) === 1);
}

/**
 * One binned row of a table as stored, or null.
 *
 * @param int $table_id
 * @param int $bin_id
 * @return array|null
 */
function ws_wtable_bin_row($table_id, $bin_id)
{
    if (!ws_wtable_bin_ready() || ((int) $bin_id <= 0)) {
        return null;
    }

    $row = db_item("SELECT * FROM ws_wtable_row_bin WHERE id = '" . (int) $bin_id . "' AND table_id = '" . (int) $table_id . "'");

    return is_array($row) ? $row : null;
}

/**
 * The binned rows of a table, the latest deleted first, for whoever may
 * edit it: each with its bin id, the row's id, who deleted it and when, when
 * it goes for good, and its cells as a row is read.
 *
 * @param array      $viewer
 * @param array|null $table
 * @param int        $limit
 * @return array|null null when the person may not see the bin
 */
function ws_wtable_bin_rows($viewer, $table, $limit = 200)
{
    if (!ws_wtable_bin_ready() || !ws_wtable_may(ws_wtable_access($viewer, $table), 'write')) {
        return null;
    }

    $columns = ws_wtable_columns((int) $table['id']);
    $bound = ws_wtable_formulas_bound($columns);
    $days = ws_wtable_bin_days();
    $out = array();

    foreach ((array) db_items("SELECT * FROM ws_wtable_row_bin WHERE table_id = '" . (int) $table['id'] . "'
        ORDER BY deleted_at DESC, id DESC LIMIT " . max(1, min(1000, (int) $limit))) as $record) {
        $row = ws_wtable_row_out(array('id' => (int) $record['row_id']) + $record, $columns, $bound);
        $row['bin_id'] = (int) $record['id'];
        $row['deleted_by'] = (int) $record['deleted_by'];
        $row['deleted_at'] = (int) $record['deleted_at'];
        $row['expires_at'] = ws_wtable_bin_expires_at((int) $record['deleted_at'], $days);
        $out[] = $row;
    }

    return $out;
}

/**
 * May the person bring a binned row back, and does the table have room?
 *
 * @param array      $viewer
 * @param array|null $table
 * @param array|null $bin ws_wtable_bin_row()
 * @return array ok, error, field, status
 */
function ws_wtable_row_restore_check($viewer, $table, $bin)
{
    if (!ws_wtable_bin_ready()) {
        return ws_wtable_refusal('not_ready', '', 503);
    }

    if (!ws_wtable_may(ws_wtable_access($viewer, $table), 'write') || !is_array($bin) || ((int) $bin['table_id'] !== (int) $table['id'])) {
        return ws_wtable_refusal('not_found', '', 404);
    }

    if (!ws_wtable_limit_allows('rows', (int) $table['row_count'])) {
        return ws_wtable_refusal('too_many_rows', '', 409);
    }

    return array('ok' => true, 'error' => '', 'field' => '', 'status' => 200);
}

/**
 * Bring a binned row back at the end of its table, with its id, cells and
 * links to site records. The bin entry is taken first, so that two requests
 * never both restore it; an id given meanwhile to another row (an old
 * database may hand a deleted last id out again after a restart) makes the
 * row a new one, and id_changed says so.
 *
 * @param array      $viewer
 * @param array|null $table
 * @param array|null $bin
 * @return array ok, error, field, status, row_id, revision, id_changed
 */
function ws_wtable_row_restore($viewer, $table, $bin)
{
    $none = array('row_id' => 0, 'revision' => 0, 'id_changed' => false);
    $check = ws_wtable_row_restore_check($viewer, $table, $bin);

    if (!$check['ok']) {
        return $check + $none;
    }

    $table_id = (int) $table['id'];
    $now = time();

    db("UPDATE ws_wtables SET row_count = row_count + 1, updated_at = '" . $now . "'
        WHERE id = '" . $table_id . "' AND row_count < '" . (int) ws_wtable_limits()['rows'] . "'");

    if ((int) mysqli_affected_rows(db::$con) !== 1) {
        return ws_wtable_refusal('too_many_rows', '', 409) + $none;
    }

    $uncount = "UPDATE ws_wtables SET row_count = IF(row_count > 0, row_count - 1, 0) WHERE id = '" . $table_id . "'";

    if (!ws_wtable_row_bin_drop((int) $bin['id'])) {
        db($uncount);

        return ws_wtable_refusal('not_found', '', 404) + $none;
    }

    $row_id = (int) $bin['row_id'];
    $revision = (int) $bin['revision'] + 1;
    $position = (int) db_value("SELECT COALESCE(MAX(position), 0) FROM ws_wtable_rows WHERE table_id = '" . $table_id . "'") + 1;

    // A row keeps the number it had; one binned before the numbers were
    // there (0) takes the table's next one.
    $number = ((int) ($bin['number'] ?? 0) > 0) ? (int) $bin['number'] : ws_wtable_number_take($table_id);
    $values = "'" . $table_id . "', '" . $position . "', '" . e((string) ($bin['cells'] ?? '{}')) . "', '" . $revision . "',
        '" . (int) $bin['created_by'] . "', '" . (int) $bin['created_at'] . "', '" . (int) $viewer['id'] . "', '" . $now . "'" . ws_wtable_number_value($number);
    $columns = "table_id, position, cells, revision, created_by, created_at, updated_by, updated_at" . ws_wtable_number_field($number);
    $restored = 0;

    if (($row_id > 0) && !db_value("SELECT id FROM ws_wtable_rows WHERE id = '" . $row_id . "'")) {
        db("INSERT INTO ws_wtable_rows (id, " . $columns . ") VALUES ('" . $row_id . "', " . $values . ")");
        $restored = ((int) mysqli_affected_rows(db::$con) === 1) ? $row_id : 0;
    }

    if ($restored === 0) {
        db("INSERT INTO ws_wtable_rows (" . $columns . ") VALUES (" . $values . ")");
        $restored = (int) mysqli_insert_id(db::$con);
    }

    if ($restored <= 0) {
        // The row could not be written: it goes back into the bin as it was.
        db("INSERT INTO ws_wtable_row_bin (id, row_id, table_id, position, cells, revision, created_by, created_at, updated_by, updated_at, links, deleted_by, deleted_at" . ws_wtable_number_field($number) . ")
            VALUES ('" . (int) $bin['id'] . "', '" . $row_id . "', '" . $table_id . "', '" . (int) $bin['position'] . "', '" . e((string) ($bin['cells'] ?? '{}')) . "',
                '" . (int) $bin['revision'] . "', '" . (int) $bin['created_by'] . "', '" . (int) $bin['created_at'] . "', '" . (int) $bin['updated_by'] . "',
                '" . (int) $bin['updated_at'] . "', '" . e((string) ($bin['links'] ?? '')) . "', '" . (int) $bin['deleted_by'] . "', '" . (int) $bin['deleted_at'] . "'" . ws_wtable_number_value($number) . ")");
        db($uncount);

        return ws_wtable_refusal('not_saved', '', 500) + $none;
    }

    $links = ws_wtable_bin_links_decode($bin['links'] ?? null);
    ws_ref_source_write('wtable_row', array($restored => $links['refs']));
    ws_wtable_row_announce('restored', $table, $restored, (int) $viewer['id']);

    return array('ok' => true, 'error' => '', 'field' => '', 'status' => 200,
        'row_id' => $restored, 'revision' => $revision, 'id_changed' => ($restored !== $row_id));
}

/**
 * May the person delete a binned row for good? Whoever may edit the table.
 *
 * @param array      $viewer
 * @param array|null $table
 * @param array|null $bin
 * @return array ok, error, field, status
 */
function ws_wtable_bin_row_delete_check($viewer, $table, $bin)
{
    if (!ws_wtable_bin_ready()) {
        return ws_wtable_refusal('not_ready', '', 503);
    }

    if (!ws_wtable_may(ws_wtable_access($viewer, $table), 'write') || !is_array($bin) || ((int) $bin['table_id'] !== (int) $table['id'])) {
        return ws_wtable_refusal('not_found', '', 404);
    }

    return array('ok' => true, 'error' => '', 'field' => '', 'status' => 200);
}

/**
 * Delete a binned row for good.
 *
 * @param array      $viewer
 * @param array|null $table
 * @param array|null $bin
 * @return array ok, error, field, status
 */
function ws_wtable_bin_row_delete($viewer, $table, $bin)
{
    $check = ws_wtable_bin_row_delete_check($viewer, $table, $bin);

    if (!$check['ok']) {
        return $check;
    }

    if (!ws_wtable_row_bin_drop((int) $bin['id'])) {
        return ws_wtable_refusal('not_found', '', 404);
    }

    return $check;
}

/* ---------------------------------------------------------------------------
   Tables
   --------------------------------------------------------------------------- */

/**
 * One binned table, or null.
 *
 * @param int $table_id
 * @return array|null
 */
function ws_wtable_binned($table_id)
{
    if (!ws_wtable_bin_ready() || ((int) $table_id <= 0)) {
        return null;
    }

    $row = db_item("SELECT * FROM ws_wtables WHERE id = '" . (int) $table_id . "' AND deleted_at > 0");

    return is_array($row) ? $row : null;
}

/**
 * The person's own binned tables, the latest deleted first, each with when
 * it goes for good.
 *
 * @param array $viewer
 * @return array[]
 */
function ws_wtables_binned($viewer)
{
    if (!ws_wtable_bin_ready() || empty($viewer['member'])) {
        return array();
    }

    $days = ws_wtable_bin_days();
    $rows = (array) db_items("SELECT id, user_id, name, description, row_count, revision, created_at, updated_at, deleted_at, deleted_by
        FROM ws_wtables WHERE user_id = '" . (int) $viewer['id'] . "' AND deleted_at > 0 ORDER BY deleted_at DESC, id DESC");

    foreach ($rows as $i => $row) {
        $rows[$i]['access'] = 'owner';
        $rows[$i]['expires_at'] = ws_wtable_bin_expires_at((int) $row['deleted_at'], $days);
    }

    return $rows;
}

/**
 * Put a table into the bin: it keeps its columns, rows and shares, and
 * nobody reaches it until it is restored.
 *
 * @param array $table
 * @param int   $actor_id
 * @return bool whether this request binned it
 */
function ws_wtable_bin_put($table, $actor_id)
{
    if (!ws_wtable_bin_ready() || !is_array($table)) {
        return false;
    }

    db("UPDATE ws_wtables SET deleted_at = '" . time() . "', deleted_by = '" . (int) $actor_id . "'
        WHERE id = '" . (int) $table['id'] . "' AND deleted_at = 0");

    return ((int) mysqli_affected_rows(db::$con) === 1);
}

/**
 * May the person bring a binned table back? Its owner, while under the
 * limit of tables.
 *
 * @param array      $viewer
 * @param array|null $table ws_wtable_binned()
 * @return array ok, error, field, status
 */
function ws_wtable_restore_check($viewer, $table)
{
    if (!ws_wtable_bin_ready()) {
        return ws_wtable_refusal('not_ready', '', 503);
    }

    if (!is_array($table) || ((int) ($table['deleted_at'] ?? 0) <= 0) || !ws_wtable_may(ws_wtable_access($viewer, $table), 'manage')) {
        return ws_wtable_refusal('not_found', '', 404);
    }

    $count = (int) db_value("SELECT COUNT(*) FROM ws_wtables WHERE user_id = '" . (int) $viewer['id'] . "'" . ws_wtable_live_sql());

    if (!ws_wtable_limit_allows('tables', $count)) {
        return ws_wtable_refusal('too_many_tables', '', 409);
    }

    return array('ok' => true, 'error' => '', 'field' => '', 'status' => 200);
}

/**
 * Bring a binned table back whole, with its columns, rows and shares.
 *
 * @param array      $viewer
 * @param array|null $table
 * @return array ok, error, field, status
 */
function ws_wtable_restore($viewer, $table)
{
    $check = ws_wtable_restore_check($viewer, $table);

    if (!$check['ok']) {
        return $check;
    }

    db("UPDATE ws_wtables SET deleted_at = 0, deleted_by = 0 WHERE id = '" . (int) $table['id'] . "' AND deleted_at > 0");

    if ((int) mysqli_affected_rows(db::$con) !== 1) {
        return ws_wtable_refusal('not_found', '', 404);
    }

    return $check;
}

/* ---------------------------------------------------------------------------
   The purge
   --------------------------------------------------------------------------- */

/**
 * Delete for good what has been in the bin longer than the Recycle Bin's
 * days: a bounded number of rows and tables a run, the oldest first. Run
 * by the workspace's recurring job.
 *
 * @param int $rows   the most rows
 * @param int $tables the most tables
 * @return int how many rows and tables were deleted
 */
function ws_wtable_bin_purge($rows = WS_WTABLE_BIN_PURGE_ROWS, $tables = WS_WTABLE_BIN_PURGE_TABLES)
{
    if (!ws_wtable_bin_ready()) {
        return 0;
    }

    $cutoff = ws_wtable_bin_cutoff(time(), ws_wtable_bin_days());

    db("DELETE FROM ws_wtable_row_bin WHERE deleted_at < '" . $cutoff . "' ORDER BY deleted_at LIMIT " . max(1, (int) $rows));
    $purged = max(0, (int) mysqli_affected_rows(db::$con));

    foreach ((array) db_values("SELECT id FROM ws_wtables WHERE deleted_at > 0 AND deleted_at < '" . $cutoff . "' ORDER BY deleted_at LIMIT " . max(1, (int) $tables)) as $table_id) {
        ws_wtable_delete_final((int) $table_id);
        $purged++;
    }

    return $purged;
}
