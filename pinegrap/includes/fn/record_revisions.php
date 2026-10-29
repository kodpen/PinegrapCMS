<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Collection item revisions: one row in record_revisions (2026.11.0,
 * 11.0.123) for every save that changed an item of a collection form
 * (custom_form_pages.collection = 1). Records of other forms (enquiries,
 * applications, orders' product forms) get none.
 *
 * Like a page revision, a row records what was saved, never a draft, and a
 * restore is a new save with source 'restore'. A writer takes the item's
 * state before its save (pg_record_revision_before()) and hands it back
 * after (pg_record_revision_after()): a save that changed the field values
 * or the address name writes one row; one that only marked the item
 * complete or incomplete writes none. An item that has no row when its
 * first changing save comes (saved before the table arrived, or before its
 * form became a collection) is first recorded as it was stored, so the
 * state before the first change can still be brought back; there is no
 * migration backfill.
 *
 * A row keeps the values by field id as JSON (a file by its file_id only:
 * the file itself is never copied, so a restore whose file has gone since
 * brings no file back), the record's own columns as JSON, and the SHA-1 of
 * the values and the address name that tells two states apart.
 *
 * Retention is the page revisions' (pg_page_revisions_retention()): the
 * last `keep` revisions of an item are kept, and so is every revision
 * younger than `days` days. pg_record_revisions_trim() applies them after
 * each write; the general job (job.php) sweeps daily, removing the rows of
 * deleted items and forms as well, since no delete path knows this table.
 *
 * Recording never stops the save that caused it: the item is already
 * written when it runs, and a failed or oversized write is noted in the
 * error log.
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
 * The source labels a revision can carry: which path saved the item.
 *
 * edit: the panel's edit screen; restore: a revision loaded back and saved;
 * api: the external API; workspace: a change carried out in the Workspace.
 */
define('PG_RECORD_REVISION_SOURCES', 'edit,restore,api,workspace');

/**
 * The largest values_json (MEDIUMTEXT) and record_json (TEXT) in bytes.
 */
define('PG_RECORD_REVISION_VALUES_MAX', 16777215);
define('PG_RECORD_REVISION_RECORD_MAX', 65535);

/**
 * The daily sweep's state file (job.php).
 */
define('PG_RECORD_REVISIONS_SWEEP_STATE', 'record_revisions_sweep.json');

/**
 * True when the table is in the database (2026.11.0, 11.0.123) and
 * collections exist (11.0.43).
 *
 * @return bool
 */
function pg_record_revisions_ready()
{
    return pg_schema_has('record_revisions', 'source') && function_exists('pg_coll_ready') && pg_coll_ready();
}

/**
 * The source labels, as a list.
 *
 * @return array<int,string>
 */
function pg_record_revision_sources()
{
    return explode(',', PG_RECORD_REVISION_SOURCES);
}

/**
 * A source label as the table takes it: a known label, 'edit' otherwise.
 *
 * @param string $source
 * @return string
 */
function pg_record_revision_source($source)
{
    $source = strtolower(trim((string) $source));

    return in_array($source, pg_record_revision_sources(), true) ? $source : 'edit';
}

/**
 * The values of an item by field id, from its form_data rows in id order: a
 * list per field, a file as array('file_id' => n). Empty values are left
 * out and a field left with none is dropped, so a save that writes a blank
 * row where there was no row (or the other way round) reads as unchanged.
 * Fields are sorted by id, so the same values always encode alike.
 *
 * @param array $rows form_data rows: form_field_id, data, file_id
 * @return array field id (string) => list
 */
function pg_record_revision_values($rows)
{
    $out = array();

    foreach ((array) $rows as $row) {
        $field_id = isset($row['form_field_id']) ? (int) $row['form_field_id'] : 0;

        if ($field_id <= 0) {
            continue;
        }

        $file_id = isset($row['file_id']) ? (int) $row['file_id'] : 0;
        $data    = isset($row['data']) ? (string) $row['data'] : '';

        if ($file_id > 0) {
            $value = array('file_id' => $file_id);
        } elseif ($data !== '') {
            $value = $data;
        } else {
            continue;
        }

        $out[(string) $field_id][] = $value;
    }

    ksort($out, SORT_NUMERIC);

    return $out;
}

/**
 * JSON as the table stores it.
 *
 * @param array $value
 * @return string
 */
function pg_record_revision_json($value)
{
    $json = json_encode($value ? $value : new stdClass(), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

    return is_string($json) ? $json : '{}';
}

/**
 * The record's own columns a revision keeps: the address name (part of
 * what a restore brings back) and the complete flag (shown, not compared).
 *
 * @param array|null $row a forms row
 * @return array address_name, complete
 */
function pg_record_revision_record_fields($row)
{
    return array(
        'address_name' => is_array($row) && isset($row['address_name']) ? (string) $row['address_name'] : '',
        'complete'     => is_array($row) && !empty($row['complete']) ? 1 : 0,
    );
}

/**
 * What tells two states of an item apart: the values and the address name.
 * The complete flag is left out, so a save that only marks the item
 * complete or incomplete writes no revision.
 *
 * @param string $values_json
 * @param string $address_name
 * @return string SHA-1
 */
function pg_record_revision_hash($values_json, $address_name)
{
    return sha1((string) $values_json . "\n" . (string) $address_name);
}

/**
 * What a save writes, from the hashes around it. An item that has rows
 * gets one when the new state differs from the newest row. One without
 * rows is first recorded as it was before the save (the seed), then the
 * new state, but only when the save changed it: an unchanged save of an
 * item without history writes nothing, and nor does one whose state before
 * is not known.
 *
 * @param string|null $latest_hash the newest row's hash, null when none
 * @param string|null $before_hash the state before the save, null when unknown
 * @param string      $after_hash  the state after the save
 * @return array seed, write (bool each)
 */
function pg_record_revision_plan($latest_hash, $before_hash, $after_hash)
{
    if ($latest_hash !== null) {
        return array('seed' => false, 'write' => ((string) $latest_hash !== (string) $after_hash));
    }

    $changed = ($before_hash !== null) && ((string) $before_hash !== (string) $after_hash);

    return array('seed' => $changed, 'write' => $changed);
}

/**
 * Whether a revision fits its columns: values_json in a MEDIUMTEXT,
 * record_json in a TEXT. A larger one is skipped and logged.
 *
 * @param string $values_json
 * @param string $record_json
 * @return bool
 */
function pg_record_revision_fits($values_json, $record_json)
{
    return strlen((string) $values_json) <= PG_RECORD_REVISION_VALUES_MAX
        && strlen((string) $record_json) <= PG_RECORD_REVISION_RECORD_MAX;
}

/**
 * An item as it is stored now, if it is an item of a collection: its form,
 * values, record columns, hash, and who saved it last and when. Null when
 * the table is not there, the record is gone or its form is no collection.
 *
 * @param int $form_id forms.id
 * @return array|null form_id, page_id, values_json, record_json, hash, user_id, created_at
 */
function pg_record_revision_snapshot($form_id)
{
    $form_id = (int) $form_id;

    if ($form_id <= 0 || !pg_record_revisions_ready()) {
        return null;
    }

    $row = db_item(
        "SELECT
            forms.page_id, forms.address_name, forms.complete, forms.user_id,
            forms.last_modified_user_id, forms.submitted_timestamp, forms.last_modified_timestamp,
            custom_form_pages.collection
         FROM forms
         LEFT JOIN custom_form_pages ON custom_form_pages.page_id = forms.page_id
         WHERE forms.id = '$form_id'
         LIMIT 1");

    if (!$row || (int) $row['collection'] !== 1) {
        return null;
    }

    $values = pg_record_revision_values(db_items(
        "SELECT form_field_id, data, file_id
         FROM form_data
         WHERE form_id = '$form_id'
         ORDER BY id ASC"));

    $record      = pg_record_revision_record_fields($row);
    $values_json = pg_record_revision_json($values);
    $user_id     = (int) $row['last_modified_user_id'] > 0 ? (int) $row['last_modified_user_id'] : (int) $row['user_id'];
    $created_at  = (int) $row['last_modified_timestamp'] > 0 ? (int) $row['last_modified_timestamp'] : (int) $row['submitted_timestamp'];

    return array(
        'form_id'     => $form_id,
        'page_id'     => (int) $row['page_id'],
        'values_json' => $values_json,
        'record_json' => pg_record_revision_json($record),
        'hash'        => pg_record_revision_hash($values_json, $record['address_name']),
        'user_id'     => $user_id,
        'created_at'  => ($created_at > 0) ? $created_at : time(),
    );
}

/**
 * The item's state before a save, for pg_record_revision_after(). Null for
 * a record that gets no revisions, which makes the after call a no-op.
 *
 * @param int $form_id forms.id
 * @return array|null
 */
function pg_record_revision_before($form_id)
{
    return pg_record_revision_snapshot($form_id);
}

/**
 * Records an item after a save: the seed first when it has no row and the
 * save changed it, then the new state when it differs from the newest row.
 * Returns the new revision number, or 0 when nothing was written.
 *
 * @param int        $form_id forms.id
 * @param array|null $before  pg_record_revision_before(), taken before the save
 * @param string     $source  pg_record_revision_sources()
 * @param int        $user_id who saved
 * @return int
 */
function pg_record_revision_after($form_id, $before, $source, $user_id)
{
    if (!is_array($before)) {
        return 0;
    }

    $after = pg_record_revision_snapshot($form_id);

    if ($after === null) {
        return 0;
    }

    $form_id = (int) $after['form_id'];

    $latest = (string) db_value(
        "SELECT values_hash
         FROM record_revisions
         WHERE form_id = '$form_id'
         ORDER BY revision_no DESC
         LIMIT 1");

    $plan = pg_record_revision_plan(($latest === '') ? null : $latest, $before['hash'], $after['hash']);

    if ($plan['seed']) {
        pg_record_revision_insert($before, 'edit', (int) $before['user_id'], (int) $before['created_at'], true);
    }

    if (!$plan['write']) {
        return 0;
    }

    $revision_no = pg_record_revision_insert($after, pg_record_revision_source($source), (int) $user_id, time(), false);

    if ($revision_no > 0) {
        pg_record_revisions_trim($form_id);
    }

    return $revision_no;
}

/**
 * Writes one row. The number is taken in the INSERT itself, so two saves of
 * one item at the same moment cannot both read the same MAX(); the second
 * fails on the unique key or the first one's locks and is tried again by
 * the page revisions' rule (pg_page_revision_retry_delay()). For the seed
 * ($first) the number is 1 and a duplicate means another save wrote it.
 *
 * @param array  $state      pg_record_revision_snapshot()
 * @param string $source
 * @param int    $user_id
 * @param int    $created_at
 * @param bool   $first      true for the seed
 * @return int revision number, 0 when nothing was written
 */
function pg_record_revision_insert($state, $source, $user_id, $created_at, $first)
{
    $form_id = (int) $state['form_id'];

    if (!pg_record_revision_fits($state['values_json'], $state['record_json'])) {
        pg_record_revisions_log_failure('the revision of item ' . $form_id . ' is larger than its column; skipping it', false);

        return 0;
    }

    $number = $first ? "1" : "COALESCE(MAX(revision_no), 0) + 1";
    $from   = $first ? "" : " FROM record_revisions WHERE form_id = '$form_id'";
    $errno  = 0;

    for ($attempt = 1; ; $attempt++) {
        $ok = db_q(
            "INSERT INTO record_revisions
                (form_id, page_id, revision_no, values_json, record_json, values_hash, user_id, source, created_at)
             SELECT ?, ?, $number, ?, ?, ?, ?, ?, ?$from",
            array(
                (string) $form_id,
                (string) (int) $state['page_id'],
                (string) $state['values_json'],
                (string) $state['record_json'],
                (string) $state['hash'],
                (string) (int) $user_id,
                (string) $source,
                (string) (int) $created_at,
            ));

        if ($ok) {
            $revision_id = (int) mysqli_insert_id(db::$con);

            return $first ? 1 : (int) db_value("SELECT revision_no FROM record_revisions WHERE revision_id = '$revision_id' LIMIT 1");
        }

        $errno = mysqli_errno(db::$con);
        $delay = pg_page_revision_retry_delay($errno, $attempt, $first);

        if ($delay < 0) {
            break;
        }

        // A random share on top keeps two writers that collided from
        // meeting again on the next try.
        usleep($delay + mt_rand(0, $delay));
    }

    if (!($first && $errno === 1062)) {
        pg_record_revisions_log_failure('writing the revision of item ' . $form_id);
    }

    return 0;
}

/**
 * Put a failed or skipped write in the error log without ending the request.
 *
 * @param string $step
 * @param bool   $with_error true to add the database's last error
 * @return void
 */
function pg_record_revisions_log_failure($step, $with_error = true)
{
    if (!function_exists('pg_error_write') || !function_exists('pg_error_entry')) {
        return;
    }

    pg_error_write(pg_error_entry(
        'warning',
        'E_USER_WARNING',
        'Item revisions: ' . $step . ($with_error ? ' failed: ' . mysqli_error(db::$con) : ''),
        __FILE__,
        __LINE__,
        array()));
}

/**
 * Removes the expired revisions of one item, by the page revisions' rules
 * (pg_page_revisions_retention(), pg_page_revisions_expired()).
 *
 * @param int $form_id
 * @return int rows removed
 */
function pg_record_revisions_trim($form_id)
{
    $form_id = (int) $form_id;

    if ($form_id <= 0 || !pg_schema_has('record_revisions', 'source')) {
        return 0;
    }

    $retention = pg_page_revisions_retention();

    // Only the rows the count rule does not protect are read; what is left
    // is judged by age alone.
    $rows = db_items(
        "SELECT revision_id, created_at
         FROM record_revisions
         WHERE form_id = '$form_id'
         ORDER BY revision_no DESC
         LIMIT " . (int) $retention['keep'] . ", 1000");

    $expired = $rows ? pg_page_revisions_expired($rows, 0, $retention['days'], time()) : array();

    if (!$expired) {
        return 0;
    }

    db("DELETE FROM record_revisions WHERE revision_id IN (" . implode(',', $expired) . ")");

    return count($expired);
}

/**
 * Where the daily sweep keeps when it last finished.
 *
 * @return string
 */
function pg_record_revisions_sweep_file()
{
    return PG_FUNCTIONS_DIR . '/data/temp/' . PG_RECORD_REVISIONS_SWEEP_STATE;
}

/**
 * Whether the daily sweep is owed: it has not run in the last day.
 *
 * @return bool
 */
function pg_record_revisions_sweep_due()
{
    if (!pg_schema_has('record_revisions', 'source')) {
        return false;
    }

    $state = @json_decode((string) @file_get_contents(pg_record_revisions_sweep_file()), true);
    $done  = (is_array($state) && isset($state['done_at'])) ? (int) $state['done_at'] : 0;

    return $done < (time() - 86400);
}

/**
 * The daily pass (job.php): the rows of items and forms that no longer
 * exist go, then every item holding more revisions than the count rule
 * keeps is trimmed by the age rule. Writes the state file when through.
 *
 * @return array orphans, trimmed (rows removed), items (trimmed)
 */
function pg_record_revisions_sweep()
{
    $out = array('orphans' => 0, 'trimmed' => 0, 'items' => 0);

    if (!pg_schema_has('record_revisions', 'source')) {
        return $out;
    }

    // An item's rows outlive it on every delete path (the edit screen, the
    // list's bulk delete, the API, a deleted form); this is where they go.
    db("DELETE FROM record_revisions
        WHERE NOT EXISTS (SELECT 1 FROM forms WHERE forms.id = record_revisions.form_id)
        LIMIT 5000");

    $out['orphans'] += max(0, (int) mysqli_affected_rows(db::$con));

    db("DELETE FROM record_revisions
        WHERE NOT EXISTS (SELECT 1 FROM custom_form_pages WHERE custom_form_pages.page_id = record_revisions.page_id)
        LIMIT 5000");

    $out['orphans'] += max(0, (int) mysqli_affected_rows(db::$con));

    $retention = pg_page_revisions_retention();

    $items = db_items(
        "SELECT form_id
         FROM record_revisions
         GROUP BY form_id
         HAVING COUNT(*) > " . (int) $retention['keep'] . "
         LIMIT 1000");

    foreach ((array) $items as $row) {
        $removed = pg_record_revisions_trim((int) $row['form_id']);

        if ($removed > 0) {
            $out['trimmed'] += $removed;
            $out['items']++;
        }
    }

    @file_put_contents(pg_record_revisions_sweep_file(), json_encode(array('done_at' => time())), LOCK_EX);

    return $out;
}

/**
 * A stored values_json read back: field id (string) => list. Broken JSON,
 * a field id that is not a number and a lone value (not a list) are
 * handled, so a row written by hand cannot break the History section.
 *
 * @param string $values_json
 * @return array field id (string) => list
 */
function pg_record_revision_decode($values_json)
{
    $decoded = json_decode((string) $values_json, true);
    $out     = array();

    if (!is_array($decoded)) {
        return $out;
    }

    foreach ($decoded as $field_id => $list) {
        if (!ctype_digit((string) $field_id) || (int) $field_id <= 0) {
            continue;
        }

        $out[(string) (int) $field_id] = is_array($list) && !isset($list['file_id']) ? array_values($list) : array($list);
    }

    return $out;
}

/**
 * The field-by-field comparison of a revision with the current values, in
 * form order; information fields hold no value and are left out.
 *
 * @param array $fields   form_fields rows: id, label, type
 * @param array $revision pg_record_revision_decode() of the revision
 * @param array $current  pg_record_revision_values() of the stored item
 * @return array list of field_id, label, type, revision, current, changed
 */
function pg_record_revision_compare($fields, $revision, $current)
{
    $rows = array();

    foreach ((array) $fields as $field) {
        if ($field['type'] === 'information') {
            continue;
        }

        $id  = (string) (int) $field['id'];
        $old = isset($revision[$id]) ? $revision[$id] : array();
        $new = isset($current[$id]) ? $current[$id] : array();

        $rows[] = array(
            'field_id' => $id,
            'label'    => (string) $field['label'],
            'type'     => (string) $field['type'],
            'revision' => $old,
            'current'  => $new,
            'changed'  => ($old != $new),
        );
    }

    return $rows;
}

/**
 * The file id a field's value list holds, 0 when none.
 *
 * @param array $list
 * @return int
 */
function pg_record_revision_file_id($list)
{
    foreach ((array) $list as $value) {
        if (is_array($value) && !empty($value['file_id'])) {
            return (int) $value['file_id'];
        }
    }

    return 0;
}

/**
 * What restoring a revision does with each field: 'value' (the edit form is
 * filled with it), 'file' (the revision's file is pointed at again on save),
 * 'gone' (its file record has been deleted since: not restored), 'kept' (a
 * signature, never redrawn, or a revision without a file: the current one
 * stays), 'unchanged'.
 *
 * @param array $fields   form_fields rows: id, type
 * @param array $revision pg_record_revision_decode() of the revision
 * @param array $current  pg_record_revision_values() of the stored item
 * @param array $file_ids ids of the revision's files that still exist
 * @return array field id (string) => status
 */
function pg_record_revision_restore_plan($fields, $revision, $current, $file_ids)
{
    $existing = array_map('intval', (array) $file_ids);
    $plan     = array();

    foreach ((array) $fields as $field) {
        $id   = (string) (int) $field['id'];
        $type = (string) $field['type'];

        if ($type === 'information') {
            continue;
        }

        if ($type !== 'file upload' && $type !== 'signature') {
            $plan[$id] = 'value';
            continue;
        }

        $old = pg_record_revision_file_id(isset($revision[$id]) ? $revision[$id] : array());
        $new = pg_record_revision_file_id(isset($current[$id]) ? $current[$id] : array());

        if ($old === $new) {
            $plan[$id] = 'unchanged';
        } elseif ($type === 'signature' || $old === 0) {
            $plan[$id] = 'kept';
        } else {
            $plan[$id] = in_array($old, $existing, true) ? 'file' : 'gone';
        }
    }

    return $plan;
}

/**
 * A revision's value as the edit form takes it: one value as text, several
 * as a list (a check box or multiple pick list), none (or a file) as ''.
 *
 * @param array $list
 * @return string|array
 */
function pg_record_revision_form_value($list)
{
    $values = array();

    foreach ((array) $list as $value) {
        if (!is_array($value)) {
            $values[] = (string) $value;
        }
    }

    if (count($values) > 1) {
        return $values;
    }

    return $values ? $values[0] : '';
}

/**
 * The text the History section shows for a value: tags dropped, white
 * space folded, several values joined, a file by its name (by its id when
 * its record is gone), cut at 300 characters.
 *
 * @param array $list
 * @param array $file_names file id => name
 * @return string plain text, to be escaped where printed
 */
function pg_record_revision_value_text($list, $file_names)
{
    $parts = array();

    foreach ((array) $list as $value) {
        if (is_array($value)) {
            $file_id = isset($value['file_id']) ? (int) $value['file_id'] : 0;
            $parts[] = isset($file_names[$file_id]) ? (string) $file_names[$file_id] : '#' . $file_id;
            continue;
        }

        $text = trim(preg_replace('/\s+/u', ' ', html_entity_decode(strip_tags((string) $value), ENT_QUOTES, 'UTF-8')));

        if ($text !== '') {
            $parts[] = $text;
        }
    }

    $text = implode(', ', $parts);

    return (mb_strlen($text) > 300) ? mb_substr($text, 0, 300) . '…' : $text;
}
