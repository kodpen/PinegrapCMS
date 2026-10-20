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
 * Who is acting: an operator in the panel, an application through the API
 * (its owner and its name), or a scheduled job.
 *
 * @return array  source (panel | api | job), user_id, username
 */
function pg_audit_actor()
{
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
 * Whether a field holds a secret, by its name: passwords, keys, tokens,
 * encrypted blobs, card data. Its value is never written; that it changed is.
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

    return preg_match('/(^|_)(api_?key|key|token|pass|enc|hash|signature|credentials?)$/', $key) === 1;
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
 * One side of a change as it is stored: secrets masked ('***' for a value,
 * an empty value left empty, so "set" and "cleared" still show), long texts
 * compacted, at any depth.
 *
 * @param array $values
 * @return array
 */
function pg_audit_redact(array $values)
{
    $redacted = array();

    foreach ($values as $key => $value) {
        if (pg_audit_secret_key($key)) {
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
    $flags = JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PARTIAL_OUTPUT_ON_ERROR;

    if (defined('JSON_INVALID_UTF8_SUBSTITUTE')) {
        $flags |= JSON_INVALID_UTF8_SUBSTITUTE;
    }

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
