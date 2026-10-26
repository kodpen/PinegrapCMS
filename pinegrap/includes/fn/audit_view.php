<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Reading the audit trail back: the filters of the audit screen
 * (view_audit_log.php) and its CSV export, the WHERE clause they become, and
 * how a stored side of a change is shown.
 *
 * A side (before_json, after_json) holds only the fields that changed, each
 * as a string, null, '***' for a secret, an object or list (JSON text
 * decoded and masked), or a compacted long text (truncated, length, sha1,
 * start). A side too large as a whole keeps only the names of its fields
 * (truncated, fields). See includes/fn/audit.php for how they are written.
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
 * Lines on one page of the screen, and the most lines one CSV export holds.
 *
 * @return array  page, export
 */
function pg_audit_view_limits()
{
    return array('page' => 50, 'export' => 50000);
}

/**
 * The filters as the screen and the export read them, each checked: a value
 * that does not fit its field is dropped, never passed on. Dates are ISO
 * (YYYY-MM-DD); the screen turns the picker's text into ISO first.
 *
 * @param array $input  from, to, source, user, action, type, id, q, request, page
 * @return array  the same keys, '' (0 for id, 1 for page) when not set
 */
function pg_audit_view_filters(array $input)
{
    $text = function ($key, $max) use ($input) {
        $value = (isset($input[$key]) && is_scalar($input[$key])) ? trim((string) $input[$key]) : '';

        return (mb_strlen($value, 'UTF-8') <= $max) ? $value : '';
    };

    $date = function ($key) use ($text) {
        $value = $text($key, 10);

        return ((preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $value, $parts) === 1) && checkdate((int) $parts[2], (int) $parts[3], (int) $parts[1])) ? $value : '';
    };

    $word = function ($key, $max) use ($text) {
        $value = $text($key, $max);

        return (preg_match('/^[a-z][a-z0-9_]*$/', $value) === 1) ? $value : '';
    };

    $source = $text('source', 8);
    $request = $text('request', 32);
    $id = $text('id', 10);
    $page = $text('page', 6);

    $filters = array(
        'from'    => $date('from'),
        'to'      => $date('to'),
        'source'  => in_array($source, array('panel', 'api', 'job'), true) ? $source : '',
        'user'    => $text('user', 100),
        'action'  => $word('action', 64),
        'type'    => $word('type', 32),
        'id'      => (ctype_digit($id) && ((int) $id > 0)) ? (int) $id : 0,
        'q'       => $text('q', 255),
        'request' => (preg_match('/^[A-Za-z0-9._:-]+$/', $request) === 1) ? $request : '',
        'page'    => (ctype_digit($page) && ((int) $page > 0)) ? (int) $page : 1,
    );

    // A range given the wrong way round is read the right way round.
    if (($filters['from'] !== '') && ($filters['to'] !== '') && ($filters['from'] > $filters['to'])) {
        list($filters['from'], $filters['to']) = array($filters['to'], $filters['from']);
    }

    return $filters;
}

/**
 * The filters that are set, for a link or a form that keeps them (the page
 * number left out).
 *
 * @param array $filters  from pg_audit_view_filters()
 * @return array
 */
function pg_audit_view_query(array $filters)
{
    $query = array();

    foreach ($filters as $key => $value) {
        if (($key !== 'page') && ($value !== '') && ($value !== 0)) {
            $query[$key] = $value;
        }
    }

    return $query;
}

/**
 * The WHERE clause of the filters. The dates are whole days in the site's
 * time zone; the user is a user id when it is a number, otherwise part of
 * the name the line was written under (an API line carries the app's name);
 * the text search looks in the record's name.
 *
 * @param array    $filters  from pg_audit_view_filters()
 * @param callable $escape   escapes a value for a quoted SQL string
 * @return string  '1' when nothing is filtered
 */
function pg_audit_view_where(array $filters, $escape = 'e')
{
    $where = array();

    if ($filters['from'] !== '') {
        $where[] = "created_at >= '" . (int) strtotime($filters['from'] . ' 00:00:00') . "'";
    }

    if ($filters['to'] !== '') {
        $where[] = "created_at < '" . (int) strtotime($filters['to'] . ' 00:00:00 +1 day') . "'";
    }

    foreach (array('source' => 'source', 'action' => 'action', 'type' => 'entity_type', 'request' => 'request_id') as $key => $column) {
        if ($filters[$key] !== '') {
            $where[] = $column . " = '" . call_user_func($escape, $filters[$key]) . "'";
        }
    }

    if ($filters['id'] > 0) {
        $where[] = "entity_id = '" . (int) $filters['id'] . "'";
    }

    if ($filters['user'] !== '') {
        $where[] = ctype_digit($filters['user'])
            ? "user_id = '" . (int) $filters['user'] . "'"
            : "username LIKE '%" . call_user_func($escape, pg_audit_view_like($filters['user'])) . "%'";
    }

    if ($filters['q'] !== '') {
        $where[] = "label LIKE '%" . call_user_func($escape, pg_audit_view_like($filters['q'])) . "%'";
    }

    return $where ? '(' . implode(') AND (', $where) . ')' : '1';
}

/**
 * A text searched for as it is inside LIKE: its wildcards and the escape
 * character taken literally.
 *
 * @param string $text
 * @return string
 */
function pg_audit_view_like($text)
{
    return addcslashes((string) $text, '\\%_');
}

/**
 * A stored side, decoded: the changed fields with their values, and whether
 * the side was too large and kept only the names.
 *
 * @param mixed $json  before_json or after_json as stored
 * @return array  fields (name => value), names_only (bool)
 */
function pg_audit_view_side($json)
{
    $values = (is_string($json) && ($json !== '')) ? json_decode($json, true) : null;

    if (!is_array($values)) {
        return array('fields' => array(), 'names_only' => false);
    }

    if ((count($values) === 2) && (($values['truncated'] ?? null) === true) && is_array($values['fields'] ?? null)) {
        return array('fields' => array_fill_keys(array_map('strval', $values['fields']), null), 'names_only' => true);
    }

    return array('fields' => $values, 'names_only' => false);
}

/**
 * One stored value as it is shown: its kind and its text.
 *
 * none:    the field had no value on this side (null)
 * secret:  a secret's value, never kept ('***')
 * cut:     a long text kept compacted; text is its opening, with length and sha1
 * json:    an object or list, as indented JSON
 * text:    anything else, as it is
 *
 * @param mixed $value
 * @return array  kind, text, length, sha1
 */
function pg_audit_view_value($value)
{
    $shown = array('kind' => 'text', 'text' => '', 'length' => 0, 'sha1' => '');

    if ($value === null) {
        $shown['kind'] = 'none';
    } elseif ($value === '***') {
        $shown['kind'] = 'secret';
        $shown['text'] = '***';
    } elseif (is_array($value) && (($value['truncated'] ?? null) === true) && isset($value['length'], $value['start'])) {
        $shown['kind'] = 'cut';
        $shown['text'] = (string) $value['start'];
        $shown['length'] = (int) $value['length'];
        $shown['sha1'] = (string) ($value['sha1'] ?? '');
    } elseif (is_array($value)) {
        $text = json_encode($value, pg_audit_json_flags() | JSON_PRETTY_PRINT);
        $shown['kind'] = 'json';
        $shown['text'] = is_string($text) ? $text : '';
    } elseif (is_bool($value)) {
        $shown['text'] = $value ? '1' : '0';
    } else {
        $shown['text'] = (string) $value;
    }

    return $shown;
}

/**
 * The changes of one line, field by field, in the order the fields were
 * written (the after side first, then fields only the before side has).
 *
 * @param mixed $before_json
 * @param mixed $after_json
 * @return array  list of field, before, after (pg_audit_view_value()), and
 *                names_only when either side kept only the names
 */
function pg_audit_view_changes($before_json, $after_json)
{
    $before = pg_audit_view_side($before_json);
    $after = pg_audit_view_side($after_json);

    $fields = array_keys($after['fields'] + $before['fields']);
    $changes = array();

    foreach ($fields as $field) {
        $changes[] = array(
            'field'      => (string) $field,
            'before'     => pg_audit_view_value(array_key_exists($field, $before['fields']) ? $before['fields'][$field] : null),
            'after'      => pg_audit_view_value(array_key_exists($field, $after['fields']) ? $after['fields'][$field] : null),
            'names_only' => $before['names_only'] || $after['names_only'],
        );
    }

    return $changes;
}

/**
 * The columns of the CSV export, in order.
 *
 * @return array  key => heading
 */
function pg_audit_view_csv_columns()
{
    return array(
        'id'          => 'ID',
        'time'        => lang('Date'),
        'source'      => lang('Source'),
        'user_id'     => lang('User ID'),
        'username'    => lang('Username'),
        'ip'          => lang('IP Address'),
        'action'      => lang('Action'),
        'entity_type' => lang('Type'),
        'entity_id'   => lang('Record ID'),
        'label'       => lang('Name'),
        'request_id'  => lang('Request ID'),
        'fields'      => lang('Fields changed'),
        'before_json' => lang('Before'),
        'after_json'  => lang('After'),
    );
}

/**
 * One audit_log row as a CSV line: the time in the site's time zone, the
 * names of the changed fields, and both sides as they are stored (secrets
 * masked when they were written). A value that would start a formula in a
 * spreadsheet carries a leading apostrophe.
 *
 * @param array $row  an audit_log row
 * @return array  values in the order of pg_audit_view_csv_columns()
 */
function pg_audit_view_csv_line(array $row)
{
    $fields = array();

    foreach (pg_audit_view_changes($row['before_json'] ?? null, $row['after_json'] ?? null) as $change) {
        $fields[] = $change['field'];
    }

    $line = array();

    foreach (array_keys(pg_audit_view_csv_columns()) as $key) {
        if ($key === 'time') {
            $line[] = date('Y-m-d H:i:s', (int) ($row['created_at'] ?? 0));
        } elseif ($key === 'fields') {
            $line[] = implode(', ', $fields);
        } else {
            $line[] = (string) ($row[$key] ?? '');
        }
    }

    // A value spreadsheet software would run as a formula (a name or label
    // starting with =, +, -, @) is shown as text.
    foreach ($line as $index => $value) {
        if (($value !== '') && (strpos("=+-@\t\r", $value[0]) !== false)) {
            $line[$index] = "'" . $value;
        }
    }

    return $line;
}

/**
 * The screen that opens a record of a type, for a link from its lines; ''
 * for a type without one.
 *
 * @param string $type
 * @param int    $id
 * @return string  a path relative to the panel
 */
function pg_audit_view_record_url($type, $id)
{
    $id = (int) $id;

    $screens = array(
        'product' => 'edit_product.php?id=',
        'page'    => 'edit_page.php?id=',
        'file'    => 'edit_file.php?id=',
        'user'    => 'edit_user.php?id=',
        'order'   => 'view_order.php?id=',
        'offer'   => 'edit_offer.php?id=',
    );

    if (isset($screens[$type]) && ($id > 0)) {
        return $screens[$type] . $id;
    }

    return in_array($type, array('api_app', 'api_key', 'api_device', 'oauth_client'), true) ? 'api_settings.php' : '';
}

/**
 * The name a record type or an action is shown under; the stored word for
 * one written later than this list.
 *
 * @param string $kind  type | action | source
 * @param string $word
 * @return string
 */
function pg_audit_view_label($kind, $word)
{
    $labels = array(
        'type' => array(
            'settings'     => lang('Settings'),
            'user'         => lang('User'),
            'product'      => lang('Product'),
            'page'         => lang('Page'),
            'order'        => lang('Order'),
            'order_return' => lang('Return'),
            'file'         => lang('File'),
            'offer'        => lang('Offer'),
            'api_app'      => lang('Application'),
            'api_key'      => lang('API key'),
            'api_device'   => lang('Device'),
            'oauth_client' => lang('OAuth client'),
            'person'       => lang('Person'),
            'audit'        => lang('Audit trail'),
        ),
        'action' => array(
            'create'  => lang('Created'),
            'update'  => lang('Updated'),
            'delete'  => lang('Deleted'),
            'recycle' => lang('Moved to the Recycle Bin'),
            'restore' => lang('Restored'),
            'rotate'  => lang('Rotated'),
            'revoke'  => lang('Revoked'),
            'cancel'  => lang('Cancelled'),
            'refund'  => lang('Refunded'),
            'export'  => lang('Exported'),
            'erase'   => lang('Erased'),
        ),
        'source' => array(
            'panel' => lang('Panel'),
            'api'   => lang('API'),
            'job'   => lang('Scheduled job'),
        ),
    );

    return $labels[$kind][$word] ?? (string) $word;
}
