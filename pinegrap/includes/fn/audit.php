<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * The audit trail: who changed what, with the values before and after.
 *
 * audit_log (2026.11.0, 11.0.16) keeps one row per change a save path
 * reports through pg_audit(): the action, the record (type, id and its name
 * at that moment), and only the fields that changed, each with its old and
 * new value. Secrets are kept as "changed" without their value
 * (pg_audit_secret_key()) and long texts as their length, hash and opening
 * (pg_audit_compact()). The free-text site log (log_activity()) is separate
 * and stays; the ERP keeps its own trail in erp_audit_log.
 *
 * Lines written by one request share a request_id. Nothing here may stop the
 * action it records: a line that cannot be written is left out.
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
 * Size limits: a string longer than 'value' bytes is kept compacted with its
 * first 'start' bytes; one side's JSON longer than 'json' bytes keeps only
 * the names of the fields that changed.
 *
 * @return array  value, start, json
 */
function pg_audit_limits()
{
    return array('value' => 2000, 'start' => 200, 'json' => 65536);
}

/**
 * Whether the 11.0.16 table is there.
 *
 * @return bool
 */
function pg_audit_ready()
{
    return pg_schema_has('audit_log', 'after_json');
}

/**
 * One id for every line this request writes. Inside the external API it is
 * the API's own request id (X-Request-Id, api_request_log), inside the ERP the
 * ERP trail's, so the trails of one request can be read side by side.
 *
 * @return string  at most 32 hex characters
 */
function pg_audit_request_id()
{
    static $id = null;

    if ($id !== null) {
        return $id;
    }

    if (function_exists('api_request_id')) {
        $id = (string) api_request_id();
    } elseif (function_exists('erp_audit_request_id')) {
        $id = (string) erp_audit_request_id();
    } else {
        try {
            $id = bin2hex(random_bytes(16));
        } catch (Exception $e) {
            $id = md5(uniqid('', true) . mt_rand());
        }
    }

    $id = substr(preg_replace('/[^0-9a-f]/', '', strtolower($id)), 0, 32);

    return $id;
}

/**
 * Record the lines that follow as a scheduled job's, not as the work of
 * whoever's request is running. Housekeeping that piggybacks on a visit (the
 * Recycle Bin's daily purge runs on the first file manager or catalog visit of
 * the day) is the software's own doing; without the switch the trail would
 * name the operator who happened to open the screen as the one who deleted.
 *
 *   $was = pg_audit_as_job(true);
 *   // … work …
 *   pg_audit_as_job($was);
 *
 * @param bool|null $on  true or false to switch, null to ask
 * @return bool  the state before this call (the current one when asking)
 */
function pg_audit_as_job($on = null)
{
    static $as_job = false;

    $was = $as_job;

    if ($on !== null) {
        $as_job = (bool) $on;
    }

    return $was;
}

/**
 * Who is acting: an operator in the panel, an application through the API
 * (its owner and its name), or a scheduled job (also any work done while
 * pg_audit_as_job() is on).
 *
 * @return array  source (panel | api | job), user_id, username
 */
function pg_audit_actor()
{
    if (pg_audit_as_job()) {
        return array('source' => 'job', 'user_id' => 0, 'username' => '');
    }

    if (defined('PG_API_ENTRY') && function_exists('api_current_app')) {
        $app = api_current_app();

        if (is_array($app)) {
            return array(
                'source'   => 'api',
                'user_id'  => (int) ($app['owner']['id'] ?? ($app['owner_user_id'] ?? 0)),
                'username' => mb_substr((string) ($app['name'] ?? ''), 0, 100, 'UTF-8'),
            );
        }
    }

    $user_id = defined('USER_ID') ? (int) USER_ID : 0;
    $username = defined('USER_USERNAME') ? (string) USER_USERNAME : '';

    if (($username === '') && !empty($_SESSION['sessionusername'])) {
        $username = (string) $_SESSION['sessionusername'];
    }

    if ((PHP_SAPI === 'cli') || (($user_id <= 0) && ($username === ''))) {
        return array('source' => 'job', 'user_id' => 0, 'username' => '');
    }

    return array('source' => 'panel', 'user_id' => $user_id, 'username' => mb_substr($username, 0, 100, 'UTF-8'));
}

/**
 * Whether a field holds a secret, by its name: passwords, PINs (the
 * developer lock's user_devpasspin), keys, tokens, private keys
 * (push_vapid_private), licences, encrypted blobs, card data. Its value is
 * never written; that it changed is.
 *
 * @param mixed $key
 * @return bool
 */
function pg_audit_secret_key($key)
{
    $key = strtolower((string) $key);

    if (in_array($key, array('pass', 'passwd', 'pin', 'otp', 'cvv', 'cvc', 'secret', 'token', 'signature', 'card_number', 'card_verification_number', 'recovery_codes'), true)) {
        return true;
    }

    if ((strpos($key, 'password') !== false) || (strpos($key, 'secret') !== false)) {
        return true;
    }

    return preg_match('/(^|_)(api_?key|key|token|pass|pin|devpasspin|enc|hash|signature|credentials?|private|license)$/', $key) === 1;
}

/**
 * A value in the form two sides are compared in: scalars as strings (a row
 * read from the database and a posted form disagree only on type), true and
 * false as '1' and '0', null as null, arrays field by field.
 *
 * @param mixed $value
 * @return mixed  string, null or array
 */
function pg_audit_value($value)
{
    if ($value === null) {
        return null;
    }

    if (is_bool($value)) {
        return $value ? '1' : '0';
    }

    if (is_scalar($value)) {
        return (string) $value;
    }

    if (is_object($value)) {
        $value = get_object_vars($value);
    }

    if (is_array($value)) {
        $normal = array();

        foreach ($value as $key => $item) {
            $normal[$key] = pg_audit_value($item);
        }

        return $normal;
    }

    return null;
}

/**
 * The fields that differ between two versions of a record, each side holding
 * only those fields. A field present on one side only is null on the other.
 * Fields keep the order of $before, then the ones only $after has.
 *
 * @param array $before
 * @param array $after
 * @return array  array($before_changed, $after_changed)
 */
function pg_audit_diff(array $before, array $after)
{
    $old = array();
    $new = array();

    foreach (array_keys($before + $after) as $key) {
        $was = array_key_exists($key, $before) ? pg_audit_value($before[$key]) : null;
        $now = array_key_exists($key, $after) ? pg_audit_value($after[$key]) : null;

        if ($was !== $now) {
            $old[$key] = $was;
            $new[$key] = $now;
        }
    }

    return array($old, $new);
}

/**
 * A long string as its length, SHA-1 and opening, cut on a UTF-8 boundary;
 * anything else as it is.
 *
 * @param mixed $value
 * @return mixed
 */
function pg_audit_compact($value)
{
    $limits = pg_audit_limits();

    if (!is_string($value) || (strlen($value) <= $limits['value'])) {
        return $value;
    }

    return array(
        'truncated' => true,
        'length'    => strlen($value),
        'sha1'      => sha1($value),
        'start'     => mb_strcut($value, 0, $limits['start'], 'UTF-8'),
    );
}

/**
 * A text column that holds a JSON object or list, decoded; null for any
 * other value. Such columns (config.product_feeds) keep tokens and keys under
 * their own names inside the text.
 *
 * Text longer than a whole side may be (pg_audit_limits() 'json') is not
 * decoded: a designer tree (page.page_tree_json) can run to megabytes, and
 * decoding it costs about ten times its size in memory on a delete. Every
 * config column that holds a secret in JSON is far below that.
 *
 * @param mixed $value
 * @return array|null
 */
function pg_audit_json_text($value)
{
    $limits = pg_audit_limits();

    if (!is_string($value) || (strlen($value) < 2) || (strlen($value) > $limits['json']) || !in_array($value[0], array('{', '['), true)) {
        return null;
    }

    $decoded = json_decode($value, true);

    return is_array($decoded) ? $decoded : null;
}

/**
 * One side of a change as it is stored: secrets masked ('***' for a value,
 * an empty value left empty, so "set" and "cleared" still show), long texts
 * compacted, at any depth.
 *
 * JSON text is decoded so the secrets in it are masked by the same names,
 * then kept as the masked structure while its JSON is no longer than a
 * value may be, and otherwise as the masked text compacted like any long
 * string. Its stored form is therefore an object, a list or a compacted
 * text, never the original string; an empty object comes back as [].
 *
 * @param array $values
 * @return array
 */
function pg_audit_redact(array $values)
{
    $redacted = array();
    $limits = pg_audit_limits();

    foreach ($values as $key => $value) {
        $json = pg_audit_secret_key($key) ? null : pg_audit_json_text($value);

        if ($json !== null) {
            $masked = pg_audit_redact($json);
            $text = json_encode($masked, pg_audit_json_flags());

            $redacted[$key] = (is_string($text) && (strlen($text) > $limits['value'])) ? pg_audit_compact($text) : $masked;
        } elseif (pg_audit_secret_key($key)) {
            $redacted[$key] = (($value === null) || ($value === '') || ($value === array())) ? $value : '***';
        } elseif (is_array($value)) {
            $redacted[$key] = pg_audit_redact($value);
        } else {
            $redacted[$key] = pg_audit_compact($value);
        }
    }

    return $redacted;
}

/**
 * The flags every JSON the trail writes is encoded with: readable Unicode and
 * slashes, and invalid UTF-8 replaced rather than failing the whole side.
 *
 * @return int
 */
function pg_audit_json_flags()
{
    $flags = JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PARTIAL_OUTPUT_ON_ERROR;

    if (defined('JSON_INVALID_UTF8_SUBSTITUTE')) {
        $flags |= JSON_INVALID_UTF8_SUBSTITUTE; // phpcs:ignore PHPCompatibility.Constants.NewConstants.json_invalid_utf8_substituteFound -- guarded by defined() above
    }

    return $flags;
}

/**
 * One side as JSON for its column: null when it holds nothing, the field
 * names alone when the whole would pass the limit.
 *
 * @param array $values  redacted values
 * @return string|null
 */
function pg_audit_json(array $values)
{
    if (!$values) {
        return null;
    }

    $limits = pg_audit_limits();
    $flags = pg_audit_json_flags();

    $json = json_encode($values, $flags);

    if (!is_string($json) || (strlen($json) > $limits['json'])) {
        $json = json_encode(array('truncated' => true, 'fields' => array_map('strval', array_keys($values))), $flags);
    }

    return is_string($json) ? $json : null;
}

/**
 * The row a change becomes, without who and where (pg_audit() adds those).
 *
 * action is a verb (create, update, delete, restore, publish…) and
 * entity_type a noun (product, page, settings, user…), both lower-case
 * identifiers. A change whose fields all stayed the same writes nothing; an
 * event given no fields at all (a delete, a key revoked) is always written.
 *
 * @param string   $action
 * @param string   $entity_type
 * @param int      $entity_id    0 for a record without an id (a settings section)
 * @param array    $before       the record's fields before
 * @param array    $after        the record's fields after
 * @param string   $label        the record's name at that moment
 * @param int|null $now
 * @return array|null  the row (column => value), null when there is nothing
 *                     to write or the action or type is not an identifier
 */
function pg_audit_row($action, $entity_type, $entity_id, array $before, array $after, $label = '', $now = null)
{
    $action = (string) $action;
    $entity_type = (string) $entity_type;

    if ((preg_match('/^[a-z][a-z0-9_]{0,63}$/', $action) !== 1) || (preg_match('/^[a-z][a-z0-9_]{0,31}$/', $entity_type) !== 1)) {
        return null;
    }

    list($old, $new) = pg_audit_diff($before, $after);

    if (!$old && !$new && ($before || $after)) {
        return null;
    }

    // A side that was not given (before a create, after a delete) stays
    // empty rather than listing every field of the other side as null.
    if (!$before) {
        $old = array();
    }

    if (!$after) {
        $new = array();
    }

    $label = preg_replace('/[\x00-\x1F\x7F]+/', ' ', (string) $label);

    return array(
        'created_at'  => ($now === null) ? time() : (int) $now,
        'action'      => $action,
        'entity_type' => $entity_type,
        'entity_id'   => max(0, (int) $entity_id),
        'label'       => mb_substr(is_string($label) ? trim($label) : '', 0, 255, 'UTF-8'),
        'before_json' => pg_audit_json(pg_audit_redact($old)),
        'after_json'  => pg_audit_json(pg_audit_redact($new)),
    );
}

/**
 * Record a change in the audit trail.
 *
 * Pass the record's fields as they were and as they are; only the fields
 * that differ are kept. Leave out fields that change on every save (a
 * modification time, the last editor) so a save that changed nothing writes
 * nothing.
 *
 *   pg_audit('update', 'product', $id, $row_before, $row_after, $row_after['name']);
 *   pg_audit('delete', 'api_key', $key_id, array(), array(), $key_name);
 *
 * @param string $action       create | update | delete | …
 * @param string $entity_type  product | page | settings | …
 * @param int    $entity_id
 * @param array  $before
 * @param array  $after
 * @param string $label        the record's name, kept for when it is gone
 * @return bool  whether a line was written
 */
function pg_audit($action, $entity_type, $entity_id = 0, array $before = array(), array $after = array(), $label = '')
{
    $row = pg_audit_row($action, $entity_type, $entity_id, $before, $after, $label);

    if (($row === null) || !pg_audit_ready()) {
        return false;
    }

    $actor = pg_audit_actor();
    $ip = function_exists('waf_client_ip') ? waf_client_ip() : (isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : '');

    $row = array(
        'created_at'  => $row['created_at'],
        'request_id'  => pg_audit_request_id(),
        'source'      => $actor['source'],
        'user_id'     => max(0, (int) $actor['user_id']),
        'username'    => $actor['username'],
        'ip'          => substr((string) $ip, 0, 45),
        'action'      => $row['action'],
        'entity_type' => $row['entity_type'],
        'entity_id'   => $row['entity_id'],
        'label'       => $row['label'],
        'before_json' => $row['before_json'],
        'after_json'  => $row['after_json'],
    );

    $values = array();

    foreach ($row as $value) {
        if ($value === null) {
            $values[] = 'NULL';
        } elseif (is_int($value)) {
            $values[] = "'" . $value . "'";
        } else {
            $values[] = "'" . e($value) . "'";
        }
    }

    return (bool) @mysqli_query(db::$con, "INSERT INTO audit_log (" . implode(', ', array_keys($row)) . ") VALUES (" . implode(', ', $values) . ")");
}

/**
 * The tables whose rows the save paths hand to the trail by id: the record
 * type they become, the id column (none for the single config row), the
 * column that names the record, and the columns left out because every save
 * or a background task rewrites them (last editor, usage stamps, config
 * ciphertext encrypted again with a new IV on every save), by name
 * ('skip') or by prefix ('skip_prefix': the SEO scores, flags and check
 * times recalculated after every save of a page or product). Any column
 * ending in "timestamp" is left out as well (pg_audit_volatile()).
 *
 * @return array  table => type, id, label (column names, first non-empty
 *                wins), skip, skip_prefix
 */
function pg_audit_tables()
{
    $seo = array('seo_');

    return array(
        'config'   => array('type' => 'settings', 'id' => '', 'label' => array(), 'skip' => array('last_modified_user_id', 'last_sitemap_check_hash', 'software_update_available', 'mailchimp_sync_running', 'backup_remote_settings', 'parasut_credentials_enc'), 'skip_prefix' => array()),
        'user'     => array('type' => 'user', 'id' => 'user_id', 'label' => array('user_username'), 'skip' => array('user_user', 'token'), 'skip_prefix' => array()),
        'products' => array('type' => 'product', 'id' => 'id', 'label' => array('short_description', 'name'), 'skip' => array('user'), 'skip_prefix' => $seo),
        'page'     => array('type' => 'page', 'id' => 'page_id', 'label' => array('page_name'), 'skip' => array('page_user', 'layout_modified'), 'skip_prefix' => $seo),
        'orders'   => array('type' => 'order', 'id' => 'id', 'label' => array('order_number', 'reference_code'), 'skip' => array('mailchimp_sync_error'), 'skip_prefix' => array()),
        'files'    => array('type' => 'file', 'id' => 'id', 'label' => array('name'), 'skip' => array('user'), 'skip_prefix' => array()),
        'offers'   => array('type' => 'offer', 'id' => 'id', 'label' => array('code'), 'skip' => array('user'), 'skip_prefix' => array()),
        'api_apps' => array('type' => 'api_app', 'id' => 'id', 'label' => array('name'), 'skip' => array('last_used_ip'), 'skip_prefix' => array()),
    );
}

/**
 * A row without the columns that change on every save, so two reads around a
 * save differ only where the save changed something.
 *
 * @param string $table  a key of pg_audit_tables()
 * @param array  $row
 * @return array
 */
function pg_audit_volatile($table, array $row)
{
    $tables = pg_audit_tables();
    $skip = isset($tables[$table]) ? $tables[$table]['skip'] : array();
    $prefixes = isset($tables[$table]) ? $tables[$table]['skip_prefix'] : array();

    foreach (array_keys($row) as $column) {
        $column = (string) $column;
        $drop = in_array($column, $skip, true) || (preg_match('/(^|_)timestamp$/', $column) === 1);

        foreach ($prefixes as $prefix) {
            $drop = $drop || (strpos($column, $prefix) === 0);
        }

        if ($drop) {
            unset($row[$column]);
        }
    }

    return $row;
}

/**
 * The name a row is recorded under: the first non-empty label column.
 *
 * A file or page in the Recycle Bin is parked under "pgbin_<id>_<name>"
 * (pg_recycle_park_name()), and the lines for sending it to the bin and for
 * deleting it from there read that parked row. The label is the record's name
 * kept for when it is gone, so the prefix built from the row's own id is taken
 * off; before_json and after_json keep the parked name as it was.
 *
 * @param string $table
 * @param array  $row
 * @return string
 */
function pg_audit_label_of($table, array $row)
{
    $tables = pg_audit_tables();

    if (!isset($tables[$table])) {
        return '';
    }

    $key = $tables[$table]['id'];

    foreach ($tables[$table]['label'] as $column) {
        if (isset($row[$column]) && (trim((string) $row[$column]) !== '')) {
            $label = (string) $row[$column];

            if (($key !== '') && isset($row[$key])) {
                $prefix = (defined('PG_RECYCLE_PARK_PREFIX') ? PG_RECYCLE_PARK_PREFIX : 'pgbin_') . (int) $row[$key] . '_';

                if ((strpos($label, $prefix) === 0) && (strlen($label) > strlen($prefix))) {
                    $label = substr($label, strlen($prefix));
                }
            }

            return $label;
        }
    }

    return '';
}

/**
 * Rows of a pg_audit_tables() table as the trail compares them, keyed by id.
 * Read with the plain connection: a failed read gives no rows and stops
 * nothing. The config table has one row, returned under key 0.
 *
 * @param string $table
 * @param array  $ids
 * @return array  id => row
 */
function pg_audit_fetch_many($table, array $ids)
{
    $tables = pg_audit_tables();

    if (!isset($tables[$table]) || !pg_audit_ready()) {
        return array();
    }

    $key = $tables[$table]['id'];

    if ($key === '') {
        $sql = "SELECT * FROM `" . $table . "` LIMIT 1";
    } else {
        $ids = array_values(array_unique(array_filter(array_map('intval', $ids), function ($id) {
            return $id > 0;
        })));

        if (!$ids) {
            return array();
        }

        $sql = "SELECT * FROM `" . $table . "` WHERE `" . $key . "` IN (" . implode(',', $ids) . ")";
    }

    $result = @mysqli_query(db::$con, $sql);

    if (!$result) {
        return array();
    }

    $rows = array();

    while ($row = mysqli_fetch_assoc($result)) {
        $rows[($key === '') ? 0 : (int) $row[$key]] = pg_audit_volatile($table, $row);
    }

    return $rows;
}

/**
 * One row of a pg_audit_tables() table as the trail compares it; an empty
 * array when there is none.
 *
 * @param string $table
 * @param int    $id     ignored for config
 * @return array
 */
function pg_audit_fetch($table, $id = 0)
{
    $rows = pg_audit_fetch_many($table, array($id));

    return $rows ? reset($rows) : array();
}

/**
 * Record a change to one row of a pg_audit_tables() table. The caller reads
 * the row before the save (pg_audit_fetch()); the row after is read here,
 * except after a delete. A row found on neither side (a failed insert, a
 * delete of nothing) writes nothing.
 *
 *   $before = pg_audit_fetch('products', $id);
 *   // … UPDATE products …
 *   pg_audit_record('update', 'products', $id, $before);
 *
 * @param string $action
 * @param string $table
 * @param int    $id      0 for config
 * @param array  $before  empty for a create
 * @param string $label   the record's name; by default its label column
 * @return bool  whether a line was written
 */
function pg_audit_record($action, $table, $id, array $before = array(), $label = null)
{
    $tables = pg_audit_tables();

    if (!isset($tables[$table]) || !pg_audit_ready()) {
        return false;
    }

    $after = ($action === 'delete') ? array() : pg_audit_fetch($table, $id);

    if (!$before && !$after) {
        return false;
    }

    if ($label === null) {
        $label = pg_audit_label_of($table, $after ? $after : $before);
    }

    return pg_audit($action, $tables[$table]['type'], $id, $before, $after, $label);
}

/**
 * Record a change that one statement made to several rows (WHERE id IN …):
 * one line per row, each compared with its own row before. The caller reads
 * the rows before with pg_audit_fetch_many().
 *
 * @param string $action
 * @param string $table
 * @param array  $before_rows  id => row, as pg_audit_fetch_many() returned them
 * @return int  lines written
 */
function pg_audit_record_many($action, $table, array $before_rows)
{
    $tables = pg_audit_tables();

    if (!$before_rows || !isset($tables[$table]) || !pg_audit_ready()) {
        return 0;
    }

    $after_rows = ($action === 'delete') ? array() : pg_audit_fetch_many($table, array_keys($before_rows));
    $written = 0;

    foreach ($before_rows as $id => $before) {
        $after = isset($after_rows[$id]) ? $after_rows[$id] : array();

        if (pg_audit($action, $tables[$table]['type'], $id, $before, $after, pg_audit_label_of($table, $after ? $after : $before))) {
            $written++;
        }
    }

    return $written;
}
