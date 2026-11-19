<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Rows of a collection record: a field of kind 'rows' (pg_coll_kinds(), on a
 * text box) names a child collection (form_fields.reference_page_id). Each
 * row is a record of that child collection, made by the rows editor and
 * listed by one record only; the parent's field keeps the rows' reference
 * codes in order, joined as a reference text box joins several codes
 * ("a1, b2"). The order and the membership are one value, so the parent's
 * History records them and the child needs no link or order field.
 *
 * pg_coll_rows_spec() answers what a rows field can draw: the child form and
 * the child fields the table takes, or why nothing can be drawn.
 * pg_coll_rows_write() writes a posted list: every row checked first, then
 * the new rows created, the changed rows written, the parent's value set and
 * the removed rows deleted; a failure midway deletes the rows the save
 * created. The writes themselves are passed in (pg_coll_rows_db_ops()), so
 * the order and the undo are tested without a database.
 * pg_coll_rows_list_sql() and pg_coll_rows_shown_value() serve a form list's
 * dynamic value 'shown record rows': on a record page, the rows of the
 * record shown, in the field's order.
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
 * The most rows one field keeps.
 *
 * @return int
 */
function pg_coll_rows_max()
{
    return 100;
}

/**
 * The field types the rows table draws a column for. Rich text, a file, a
 * radio button group and the rest are filled on the row's own record screen.
 *
 * @return array
 */
function pg_coll_rows_table_types()
{
    return array('text box', 'text area', 'pick list', 'check box', 'date', 'time');
}

/**
 * What a rows field can draw (pure). $field is the rows field: 'page_id' (its
 * own form's page), 'kind', 'reference_page_id'. $child is the form on that
 * page as pg_coll_rows_child() reads it, null when the page holds no form:
 * 'page_id', 'collection' (bool), 'fields' (form_fields rows without
 * information fields, in form order, each with 'options').
 *
 * @param array      $field
 * @param array|null $child
 * @return array array('ok' => true, 'page_id', 'fields' => the fields the
 *               table takes, 'others' => the rest), or array('ok' => false,
 *               'reason' => no_form | own_form | gone | not_collection | no_fields)
 */
function pg_coll_rows_spec($field, $child)
{
    $kind    = isset($field['kind']) ? (string)$field['kind'] : '';
    $page_id = ($kind === 'rows' && isset($field['reference_page_id'])) ? (int)$field['reference_page_id'] : 0;
    $own     = isset($field['page_id']) ? (int)$field['page_id'] : 0;

    if ($page_id <= 0) return array('ok' => false, 'reason' => 'no_form');
    if ($page_id === $own) return array('ok' => false, 'reason' => 'own_form');
    if (!is_array($child) || (int)(isset($child['page_id']) ? $child['page_id'] : 0) !== $page_id) {
        return array('ok' => false, 'reason' => 'gone');
    }
    if (empty($child['collection'])) return array('ok' => false, 'reason' => 'not_collection');

    $taken = array();
    $others = array();
    foreach ((array)(isset($child['fields']) ? $child['fields'] : array()) as $f) {
        if (!is_array($f) || !isset($f['id'], $f['type'])) continue;
        if (pg_coll_rows_takes($f)) $taken[] = $f; else $others[] = $f;
    }
    if (!$taken) return array('ok' => false, 'reason' => 'no_fields');

    return array('ok' => true, 'page_id' => $page_id, 'fields' => $taken, 'others' => $others);
}

/**
 * Whether the rows table draws a column for a child field: a type of
 * pg_coll_rows_table_types(), a text area that is not rich text, and never
 * a rows field of its own (rows inside rows are left out).
 *
 * @param array $field form_fields row: type, wysiwyg, collection_kind
 * @return bool
 */
function pg_coll_rows_takes($field)
{
    $type = (string)$field['type'];
    if (!in_array($type, pg_coll_rows_table_types(), true)) return false;
    if ($type === 'text area' && !empty($field['wysiwyg'])) return false;
    $kind = pg_coll_field_kind(isset($field['collection_kind']) ? $field['collection_kind'] : '', $type);
    return $kind !== 'rows';
}

/**
 * Why a rows field draws nothing, in words (pg_coll_rows_spec()'s reason).
 *
 * @param string $reason
 * @return string
 */
function pg_coll_rows_spec_message($reason)
{
    switch ((string)$reason) {
        case 'no_form':
            return lang('Choose the collection the rows are kept in.');
        case 'own_form':
            return lang('The rows are kept in another collection, not in this form.');
        case 'gone':
            return lang('The collection the rows were kept in is gone.');
        case 'not_collection':
            return lang('The form the rows are kept in is no longer used as a collection.');
        case 'no_fields':
            return lang('The collection the rows are kept in has no field a row can fill here.');
    }
    return '';
}

/**
 * The reference codes a rows field's value holds, in order: split at
 * commas, trimmed, each a reference code (letters, digits, "_", "-", "[",
 * "]"), a blank or a repeat left out (pure).
 *
 * @param mixed $value
 * @return array
 */
function pg_coll_rows_codes($value)
{
    if (!is_string($value)) return array();
    $codes = array();
    foreach (explode(',', $value) as $part) {
        $part = trim($part);
        if ($part === '' || strlen($part) > 255 || !preg_match('/^[A-Za-z0-9_\-\[\]]+$/', $part)) continue;
        if (in_array($part, $codes, true)) continue;
        $codes[] = $part;
    }
    return $codes;
}

/**
 * The stored value of a rows field: the codes in order, joined as a
 * reference text box joins them (pure).
 *
 * @param array $codes
 * @return string
 */
function pg_coll_rows_value($codes)
{
    return implode(', ', array_map('strval', (array)$codes));
}

/**
 * The form on $page_id as pg_coll_rows_spec() reads it, null when the page
 * holds no form: whether it is a collection, its pretty URLs setting and its
 * fields (no information field) in form order, each with its kind, the
 * values of its options and their labels ('option_labels'); a reference, product or page field also carries
 * 'choices' (pg_coll_kind_options()). Cached per request.
 *
 * @param int $page_id
 * @return array|null
 */
function pg_coll_rows_child($page_id)
{
    static $cache = array();
    $page_id = (int)$page_id;
    if ($page_id <= 0 || !pg_coll_ready()) return null;
    if (array_key_exists($page_id, $cache)) return $cache[$page_id];

    $form = db_item("SELECT collection, pretty_urls FROM custom_form_pages WHERE page_id = '$page_id' LIMIT 1");
    if (!$form) return $cache[$page_id] = null;

    $fields = array();
    $ids = array();
    foreach ((array)db_items(
        "SELECT id, name, label, type, wysiwyg, multiple, required, office_use_only,
                collection_kind, reference_page_id
         FROM form_fields
         WHERE page_id = '$page_id' AND type != 'information'
         ORDER BY sort_order, id") as $row) {
        $row['collection_kind'] = pg_coll_field_kind($row['collection_kind'], $row['type']);
        $row['options'] = array();
        $choices = pg_coll_kind_options(array('kind' => $row['collection_kind'], 'reference_page_id' => (int)$row['reference_page_id']));
        if ($choices !== null) $row['choices'] = $choices;
        $fields[(int)$row['id']] = $row;
        $ids[] = (int)$row['id'];
    }
    if ($ids) {
        foreach ((array)db_items(
            "SELECT form_field_id, label, value FROM form_field_options
             WHERE form_field_id IN (" . implode(',', $ids) . ")
             ORDER BY sort_order, id") as $o) {
            $fid = (int)$o['form_field_id'];
            $fields[$fid]['options'][] = (string)$o['value'];
            $fields[$fid]['option_labels'][(string)$o['value']] = (string)$o['label'];
        }
    }

    return $cache[$page_id] = array(
        'page_id'     => $page_id,
        'collection'  => ((int)$form['collection'] === 1),
        'pretty_urls' => ((int)$form['pretty_urls'] === 1),
        'fields'      => array_values($fields),
    );
}

/**
 * The spec of a rows field read from the database (pg_coll_rows_spec()).
 *
 * @param array $field 'page_id', 'kind', 'reference_page_id'
 * @return array
 */
function pg_coll_rows_spec_for($field)
{
    $page_id = (isset($field['kind']) && $field['kind'] === 'rows') ? (int)$field['reference_page_id'] : 0;
    return pg_coll_rows_spec($field, ($page_id > 0) ? pg_coll_rows_child($page_id) : null);
}

/**
 * A record's rows in order: the child records whose codes the record's rows
 * field holds, each array('id', 'reference_code', 'values' => field id =>
 * value, a list for a field stored as several rows). A code that names no
 * record of the child form is left out.
 *
 * @param int $form_id       the parent record
 * @param int $field_id      its rows field
 * @param int $child_page_id the child collection
 * @return array
 */
function pg_coll_rows_of($form_id, $field_id, $child_page_id)
{
    $form_id = (int)$form_id;
    $field_id = (int)$field_id;
    $child_page_id = (int)$child_page_id;
    if ($form_id <= 0 || $field_id <= 0 || $child_page_id <= 0) return array();

    $codes = pg_coll_rows_codes((string)db_value(
        "SELECT data FROM form_data WHERE form_id = '$form_id' AND form_field_id = '$field_id' LIMIT 1"));
    if (!$codes) return array();

    $quoted = array();
    foreach ($codes as $code) $quoted[] = "'" . e($code) . "'";
    $records = array();
    foreach ((array)db_items(
        "SELECT id, reference_code FROM forms
         WHERE page_id = '$child_page_id' AND reference_code IN (" . implode(',', $quoted) . ")") as $row) {
        $records[(string)$row['reference_code']] = array('id' => (int)$row['id'], 'reference_code' => (string)$row['reference_code'], 'values' => array());
    }
    if (!$records) return array();

    $by_id = array();
    foreach ($records as $code => $record) $by_id[$record['id']] = $code;
    foreach ((array)db_items(
        "SELECT form_id, form_field_id, data FROM form_data
         WHERE form_id IN (" . implode(',', array_keys($by_id)) . ")
         ORDER BY id") as $row) {
        $code = $by_id[(int)$row['form_id']];
        $fid = (int)$row['form_field_id'];
        $values = &$records[$code]['values'];
        if (!isset($values[$fid])) {
            $values[$fid] = (string)$row['data'];
        } else {
            $values[$fid] = array_merge((array)$values[$fid], array((string)$row['data']));
        }
        unset($values);
    }

    $rows = array();
    foreach ($codes as $code) {
        if (isset($records[$code])) $rows[] = $records[$code];
    }
    return $rows;
}

/**
 * Whether a value posted for a rows field outside its editor names only
 * records of the child collection, at most pg_coll_rows_max() of them. A
 * field whose child form is gone takes no code.
 *
 * @param mixed $value
 * @param int   $child_page_id
 * @return bool
 */
function pg_coll_rows_value_ok($value, $child_page_id)
{
    if (is_array($value)) return false;
    $value = trim((string)$value);
    if ($value === '') return true;
    $codes = pg_coll_rows_codes($value);
    $parts = array_filter(array_map('trim', explode(',', $value)), 'strlen');
    if (count($codes) !== count($parts) || count($codes) > pg_coll_rows_max()) return false;
    $child_page_id = (int)$child_page_id;
    if ($child_page_id <= 0) return false;

    $quoted = array();
    foreach ($codes as $code) $quoted[] = "'" . e($code) . "'";
    $found = (int)db_value(
        "SELECT COUNT(*) FROM forms
         WHERE page_id = '$child_page_id' AND reference_code IN (" . implode(',', $quoted) . ")");
    return $found === count($codes);
}

/**
 * Checks one row's values with its child fields' own checks (pure): a
 * required field when the parent record is complete, a date, a time, a
 * choice of the field's options or of a reference's records, a number of a
 * number field. $values are field id => value as posted; only the fields
 * the table takes are read. A field a row already existing was not sent is
 * left as stored ($new false); a new row's missing field is empty.
 *
 * @param array $fields   pg_coll_rows_spec()'s 'fields'
 * @param mixed $values
 * @param bool  $complete the parent record is complete
 * @param bool  $new      the row is made by this save
 * @return array array('values' => field id => value to write, 'errors' => field id => message)
 */
function pg_coll_rows_check_row($fields, $values, $complete, $new)
{
    $values = is_array($values) ? $values : array();
    $out = array();
    $errors = array();

    foreach ((array)$fields as $field) {
        $id = (int)$field['id'];
        $sent = array_key_exists($id, $values) || array_key_exists((string)$id, $values);
        if (!$sent && !$new) continue;
        $value = $sent ? $values[$id] : '';
        $type = (string)$field['type'];
        $label = h(function_exists('pg_tr_label') ? pg_tr_label((string)$field['label']) : (string)$field['label']);
        $listed = ($type === 'check box') || ($type === 'pick list' && !empty($field['multiple']));

        if (is_array($value)) {
            if (!$listed) {
                $errors[$id] = lang(array('string' => 'Please enter a valid value for {var:1}', 'vars' => $label));
                continue;
            }
            $list = array();
            foreach ($value as $one) {
                if (!is_scalar($one)) { $list = null; break; }
                if ((string)$one !== '') $list[] = (string)$one;
            }
            if ($list === null) {
                $errors[$id] = lang(array('string' => 'Please enter a valid value for {var:1}', 'vars' => $label));
                continue;
            }
            $value = $list;
        } elseif (is_scalar($value) || $value === null) {
            $value = (string)$value;
        } else {
            $errors[$id] = lang(array('string' => 'Please enter a valid value for {var:1}', 'vars' => $label));
            continue;
        }

        $empty = is_array($value) ? !$value : (trim($value) === '');
        if ($empty) {
            if ($complete && !empty($field['required'])) {
                $errors[$id] = lang(array('string' => '{var:1} is required.', 'vars' => $label));
                continue;
            }
            $out[$id] = $value;
            continue;
        }

        $kind = pg_coll_field_kind(isset($field['collection_kind']) ? $field['collection_kind'] : '', $type);
        if ($kind === 'number') {
            $number = pg_coll_number_value($value);
            if ($number === null) {
                $errors[$id] = lang(array('string' => 'Please enter a number for {var:1}', 'vars' => $label));
                continue;
            }
            $value = $number;
        } elseif ($type === 'date' && !validate_date($value)) {
            $errors[$id] = lang(array('string' => 'Please enter a valid date for {var:1}', 'vars' => $label));
            continue;
        } elseif ($type === 'time' && !validate_time($value)) {
            $errors[$id] = lang(array('string' => 'Please enter a valid time for {var:1}', 'vars' => $label));
            continue;
        }

        if (isset($field['choices']) && is_array($field['choices'])) {
            if (!pg_coll_reference_ok($value, $field['choices'])) {
                $errors[$id] = lang(array('string' => 'Please choose one of the listed items for {var:1}', 'vars' => $label));
                continue;
            }
        } elseif (($type === 'pick list' || $type === 'check box') && !empty($field['options'])) {
            $options = array_map('strval', (array)$field['options']);
            foreach ((array)$value as $one) {
                if (!in_array((string)$one, $options, true)) {
                    $errors[$id] = lang(array('string' => 'Please choose one of the listed items for {var:1}', 'vars' => $label));
                    continue 2;
                }
            }
        }

        $out[$id] = $value;
    }

    return array('values' => $out, 'errors' => $errors);
}

/**
 * The first half of pg_coll_rows_write(), which writes nothing: whether a
 * posted list may be written (the limit, every code one the parent lists
 * and once only, every row's fields), and the rows as they would be
 * written. A screen calls it with its other checks, before the record is
 * saved, so a refused row keeps the whole save back.
 *
 * @param array  $spec         pg_coll_rows_spec()
 * @param string $stored_value the parent's stored value of the field
 * @param mixed  $posted       list of array('code', 'values')
 * @param bool   $complete     the parent record is complete
 * @return array array('ok' => bool, 'errors' => list of array('row',
 *               'field', 'message'), 'rows' => list of array('code', 'values'))
 */
function pg_coll_rows_check($spec, $stored_value, $posted, $complete)
{
    $refuse = function ($message, $row = null, $field = null) {
        return array('ok' => false, 'rows' => array(), 'errors' => array(array('row' => $row, 'field' => $field, 'message' => $message)));
    };

    if (!is_array($spec) || empty($spec['ok'])) {
        return $refuse(pg_coll_rows_spec_message(is_array($spec) && isset($spec['reason']) ? $spec['reason'] : 'no_form'));
    }
    if (!is_array($posted) || ($posted && array_keys($posted) !== range(0, count($posted) - 1))) {
        return $refuse(lang('The rows could not be read. Please try again.'));
    }
    if (count($posted) > pg_coll_rows_max()) {
        return $refuse(lang(array('string' => 'A record keeps at most {var:1} rows.', 'vars' => pg_coll_rows_max())));
    }

    $stored = pg_coll_rows_codes((string)$stored_value);
    $rows = array();
    $errors = array();
    $seen = array();

    foreach ($posted as $i => $row) {
        if (!is_array($row) || (isset($row['values']) && !is_array($row['values']))) {
            return $refuse(lang('The rows could not be read. Please try again.'), $i);
        }
        $code = (isset($row['code']) && is_scalar($row['code'])) ? trim((string)$row['code']) : '';
        if ($code !== '') {
            if (!in_array($code, $stored, true) || isset($seen[$code])) {
                $errors[] = array('row' => $i, 'field' => null, 'message' => lang('This row is not one of this record\'s rows.'));
                continue;
            }
            $seen[$code] = true;
        }
        $check = pg_coll_rows_check_row($spec['fields'], isset($row['values']) ? $row['values'] : array(), $complete, $code === '');
        foreach ($check['errors'] as $field_id => $message) {
            $errors[] = array('row' => $i, 'field' => $field_id, 'message' => $message);
        }
        $rows[] = array('code' => $code, 'values' => $check['values']);
    }

    return array('ok' => !$errors, 'errors' => $errors, 'rows' => $errors ? array() : $rows);
}

/**
 * Writes a posted list of rows for one rows field. $posted is a list of
 * rows, each array('code' => '' for a new row or the code of a row the
 * parent lists, 'values' => field id => value). Every row is checked first
 * (pg_coll_rows_check_row()); a refusal writes nothing. A code the parent
 * does not list ($stored_value) is refused, so a row of another record is
 * never taken over; so is a code listed twice and a list over
 * pg_coll_rows_max().
 *
 * Then, in this order: the new rows created, the rows already listed
 * written, the parent's value set to the codes in order, the rows no longer
 * listed deleted. When a create, a write or the parent's value fails, the
 * rows this save created are deleted and nothing more is written: the parent
 * never lists a row that does not exist and no new row is left unlisted.
 *
 * $ops are the writes: 'create' (values => new code, or false), 'update'
 * (code, values => bool), 'set' (value => bool), 'delete' (code => bool);
 * pg_coll_rows_db_ops() gives the real ones.
 *
 * @param array  $spec         pg_coll_rows_spec()
 * @param string $stored_value the parent's stored value of the field
 * @param mixed  $posted
 * @param bool   $complete     the parent record is complete
 * @param array  $ops
 * @return array array('ok' => bool, 'errors' => list of array('row' => line
 *               index or null, 'field' => field id or null, 'message'),
 *               and when ok: 'value', 'codes', 'created', 'deleted', 'left'
 *               (removed rows that could not be deleted))
 */
function pg_coll_rows_write($spec, $stored_value, $posted, $complete, $ops)
{
    $check = pg_coll_rows_check($spec, $stored_value, $posted, $complete);
    if (!$check['ok']) return array('ok' => false, 'errors' => $check['errors']);
    $rows = $check['rows'];
    $stored = pg_coll_rows_codes((string)$stored_value);

    $created = array();
    $undo = function ($message) use (&$created, $ops) {
        foreach ($created as $code) call_user_func($ops['delete'], $code);
        return array('ok' => false, 'errors' => array(array('row' => null, 'field' => null, 'message' => $message)));
    };
    $failed = lang('The rows could not be saved. Please try again.');

    foreach ($rows as $i => $row) {
        if ($row['code'] !== '') continue;
        $code = call_user_func($ops['create'], $row['values']);
        if (!is_string($code) || $code === '') return $undo($failed);
        $created[] = $code;
        $rows[$i]['code'] = $code;
    }

    foreach ($rows as $row) {
        if (in_array($row['code'], $created, true)) continue;
        if (!call_user_func($ops['update'], $row['code'], $row['values'])) return $undo($failed);
    }

    $codes = array();
    foreach ($rows as $row) $codes[] = $row['code'];
    $value = pg_coll_rows_value($codes);
    if (!call_user_func($ops['set'], $value)) return $undo($failed);

    $deleted = array();
    $left = array();
    foreach ($stored as $code) {
        if (in_array($code, $codes, true)) continue;
        if (call_user_func($ops['delete'], $code)) $deleted[] = $code; else $left[] = $code;
    }

    return array('ok' => true, 'errors' => array(), 'value' => $value, 'codes' => $codes,
        'created' => $created, 'deleted' => $deleted, 'left' => $left);
}

/**
 * The writes pg_coll_rows_write() makes on the database for one parent
 * record's rows field. A row is created complete, by $user_id, with a value
 * row for every child field (an empty one for a field the table does not
 * take, as a new record gets); a written row goes through its History as an
 * edit; the parent's value replaces the field's stored rows; a removed row
 * is deleted with everything that names it (pg_submitted_form_delete()).
 *
 * @param array $spec      pg_coll_rows_spec()
 * @param int   $form_id   the parent record
 * @param array $field     the rows field: id, name
 * @param int   $user_id
 * @return array
 */
function pg_coll_rows_db_ops($spec, $form_id, $field, $user_id)
{
    require_once(PG_FUNCTIONS_DIR . '/includes/fn/submitted_form_writes.php');

    $form_id = (int)$form_id;
    $user_id = (int)$user_id;
    $child_page_id = (int)$spec['page_id'];
    $child = pg_coll_rows_child($child_page_id);
    $all_fields = is_array($child) ? $child['fields'] : array();
    $pretty = is_array($child) && !empty($child['pretty_urls']);

    $insert_values = function ($row_id, $f, $value) {
        $type = (string)$f['type'];
        $data_type = in_array($type, array('date', 'date and time', 'time'), true) ? $type : 'standard';
        $ok = true;
        foreach ((is_array($value) ? $value : array($value)) as $one) {
            $ok = db_q(
                "INSERT INTO form_data (form_id, form_field_id, data, name, type) VALUES (?, ?, ?, ?, ?)",
                array((string)$row_id, (string)$f['id'], (string)prepare_form_data_for_input($one, $type), (string)$f['name'], $data_type)) && $ok;
        }
        return $ok;
    };
    $row_id = function ($code) use ($child_page_id) {
        return (int)db_value("SELECT id FROM forms WHERE page_id = '$child_page_id' AND reference_code = '" . e((string)$code) . "' LIMIT 1");
    };

    return array(
        'create' => function ($values) use ($child_page_id, $user_id, $all_fields, $pretty, $insert_values) {
            $code = generate_form_reference_code();
            if (!db_q(
                "INSERT INTO forms (page_id, complete, user_id, reference_code, submitted_timestamp, last_modified_user_id, last_modified_timestamp)
                 VALUES (?, '1', ?, ?, UNIX_TIMESTAMP(), ?, UNIX_TIMESTAMP())",
                array((string)$child_page_id, (string)$user_id, (string)$code, (string)$user_id))) {
                return false;
            }
            $id = (int)mysqli_insert_id(db::$con);
            foreach ($all_fields as $f) {
                if ((string)$f['type'] === 'file upload' || (string)$f['type'] === 'signature') {
                    $ok = db_q("INSERT INTO form_data (form_id, form_field_id, file_id, name) VALUES (?, ?, '0', ?)",
                        array((string)$id, (string)$f['id'], (string)$f['name']));
                } else {
                    $ok = $insert_values($id, $f, isset($values[(int)$f['id']]) ? $values[(int)$f['id']] : '');
                }
                if (!$ok) {
                    pg_submitted_form_delete($id);
                    return false;
                }
            }
            if ($pretty) update_submitted_form_address_name($id);
            return (string)$code;
        },
        'update' => function ($code, $values) use ($spec, $pretty, $user_id, $row_id, $insert_values) {
            $id = $row_id($code);
            if ($id <= 0) return false;
            $stored = array();
            foreach ((array)db_items("SELECT form_field_id, data FROM form_data WHERE form_id = '$id' ORDER BY id") as $r) {
                $stored[(int)$r['form_field_id']][] = (string)$r['data'];
            }
            $before = null;
            $changed = false;
            foreach ($spec['fields'] as $f) {
                $fid = (int)$f['id'];
                if (!array_key_exists($fid, $values)) continue;
                $want = array();
                foreach ((is_array($values[$fid]) ? $values[$fid] : array($values[$fid])) as $one) {
                    $want[] = (string)prepare_form_data_for_input($one, (string)$f['type']);
                }
                if (isset($stored[$fid]) && $stored[$fid] === $want) continue;
                if (!$changed) {
                    $before = pg_record_revision_before($id);
                    $changed = true;
                }
                if (!db_q("DELETE FROM form_data WHERE (form_id = ?) AND (form_id != '0') AND (form_field_id = ?)", array((string)$id, (string)$fid))
                    || !$insert_values($id, $f, $values[$fid])) {
                    return false;
                }
            }
            if (!$changed) return true;
            db_q("UPDATE forms SET last_modified_user_id = ?, last_modified_timestamp = UNIX_TIMESTAMP() WHERE id = ?",
                array((string)$user_id, (string)$id));
            if ($pretty) update_submitted_form_address_name($id);
            pg_record_revision_after($id, $before, 'edit', $user_id);
            return true;
        },
        'set' => function ($value) use ($form_id, $field) {
            if (!db_q("DELETE FROM form_data WHERE (form_id = ?) AND (form_id != '0') AND (form_field_id = ?)",
                array((string)$form_id, (string)(int)$field['id']))) {
                return false;
            }
            return (bool)db_q("INSERT INTO form_data (form_id, form_field_id, data, name, type) VALUES (?, ?, ?, ?, 'standard')",
                array((string)$form_id, (string)(int)$field['id'], (string)$value, (string)$field['name']));
        },
        'delete' => function ($code) use ($row_id) {
            $id = $row_id($code);
            return ($id > 0) && pg_submitted_form_delete($id) !== false;
        },
    );
}

/**
 * A posted list of rows as the editor sends it (one JSON field, so PHP's
 * max_input_vars never cuts a long list): a list of array('code',
 * 'values' => field id => value). null when there is nothing to write: the
 * field was not sent (the editor's script did not run) or is not a list.
 *
 * @param mixed $json
 * @return array|null
 */
function pg_coll_rows_posted($json)
{
    if (!is_string($json) || trim($json) === '') return null;
    $list = json_decode($json, true);
    if (!is_array($list) || ($list && array_keys($list) !== range(0, count($list) - 1))) return null;
    return $list;
}

/**
 * The input the editor draws for a child field the table takes.
 *
 * @param array $field
 * @return string text | textarea | select | multiselect | checks | date | time
 */
function pg_coll_rows_input($field)
{
    $type = (string)$field['type'];
    if ($type === 'text area') return 'textarea';
    if ($type === 'date') return 'date';
    if ($type === 'time') return 'time';
    if ($type === 'check box') return 'checks';
    if ($type === 'pick list') return empty($field['multiple']) ? 'select' : 'multiselect';
    return 'text';
}

/**
 * What the rows editor's script draws (pure): the columns (one per field
 * the table takes, with its choices), the names of the fields only the
 * row's own screen fills, the rows with their values and the address of
 * their own screen ('open', only for a row that exists while such a field
 * is there), the refusals to mark and the limit.
 *
 * @param array $spec   pg_coll_rows_spec(), ok
 * @param array $rows   array('id', 'reference_code', 'values') each
 *                      (pg_coll_rows_of(), or a posted list with id 0)
 * @param array $errors pg_coll_rows_check()'s errors
 * @return array
 */
function pg_coll_rows_editor_config($spec, $rows, $errors)
{
    $columns = array();
    $lists = array();
    foreach ($spec['fields'] as $f) {
        $input = pg_coll_rows_input($f);
        $options = array();
        if (isset($f['choices']) && is_array($f['choices'])) {
            foreach ($f['choices'] as $value => $label) $options[] = array('value' => (string)$value, 'label' => (string)$label);
        } else {
            foreach ((array)(isset($f['options']) ? $f['options'] : array()) as $value) {
                $label = isset($f['option_labels'][$value]) ? (string)$f['option_labels'][$value] : (string)$value;
                $options[] = array('value' => (string)$value, 'label' => $label);
            }
        }
        $columns[] = array(
            'id'       => (int)$f['id'],
            'label'    => (string)$f['label'],
            'input'    => $input,
            // The panel's one date control, as the record's own field
            // (pg_submitted_form_fields_markup()): a text box with its hook.
            'hook'     => ($input === 'date') ? 'data-pg-date' : (($input === 'time') ? 'data-pg-time' : ''),
            'required' => !empty($f['required']),
            'options'  => $options,
        );
        $lists[(int)$f['id']] = in_array($input, array('checks', 'multiselect'), true);
    }

    $others = array();
    foreach ($spec['others'] as $f) $others[] = (string)$f['label'];

    $out_rows = array();
    foreach ((array)$rows as $row) {
        $values = array();
        foreach ($lists as $fid => $list) {
            $value = isset($row['values'][$fid]) ? $row['values'][$fid] : '';
            if ($list) {
                $value = array_values(array_filter(array_map('strval', (array)$value), 'strlen'));
            } elseif (is_array($value)) {
                $value = (string)reset($value);
            } else {
                $value = (string)$value;
            }
            $values[(string)$fid] = $value;
        }
        $id = isset($row['id']) ? (int)$row['id'] : 0;
        $out_rows[] = array(
            'code'   => isset($row['reference_code']) ? (string)$row['reference_code'] : '',
            'values' => $values,
            'open'   => ($id > 0 && $others) ? OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/edit_submitted_form.php?id=' . $id : '',
        );
    }

    $marks = array();
    foreach ((array)$errors as $e) {
        $marks[] = array('row' => isset($e['row']) ? $e['row'] : null, 'field' => isset($e['field']) ? $e['field'] : null, 'message' => (string)$e['message']);
    }

    return array('columns' => $columns, 'others' => $others, 'rows' => $out_rows, 'errors' => $marks, 'max' => pg_coll_rows_max());
}

/**
 * The picker kinds a rows field's table needs ('date', 'time'), so the
 * screen prints their options and the field script once (pure).
 *
 * @param array $spec pg_coll_rows_spec()
 * @return array
 */
function pg_coll_rows_picker_kinds($spec)
{
    $kinds = array();
    if (!is_array($spec) || empty($spec['ok'])) return $kinds;
    foreach ($spec['fields'] as $f) {
        $input = pg_coll_rows_input($f);
        if (($input === 'date' || $input === 'time') && !in_array($input, $kinds, true)) $kinds[] = $input;
    }
    sort($kinds);
    return $kinds;
}

/**
 * The words the rows editor's script shows; a script cannot call lang().
 *
 * @return array
 */
function pg_coll_rows_editor_words()
{
    return array(
        'add'      => lang('Add row'),
        'remove'   => lang('Remove'),
        'up'       => lang('Move up'),
        'down'     => lang('Move down'),
        'drag'     => lang('Drag to reorder'),
        'open'     => lang('Open the row'),
        'empty'    => lang('No rows yet.'),
        'full'     => lang(array('string' => 'A record keeps at most {var:1} rows.', 'vars' => pg_coll_rows_max())),
        'others'   => lang('Filled on the row\'s own screen:'),
        'position' => lang('Position {position} of {total}'),
        'row'      => lang('Row'),
        'actions'  => lang('Actions'),
        'choose'   => lang('— Choose —'),
    );
}

/**
 * The rows editor on the panel's record screens: the field's label, a box
 * the script fills with the table, and the one hidden field the list
 * travels in ("rows_<field id>"). A field that cannot draw says why and
 * posts nothing, so its rows stay as they are.
 *
 * @param array        $field  form_fields row: id, label (required marked)
 * @param array        $config pg_coll_rows_editor_config(), or a spec that is not ok
 * @param string|null  $posted the list as posted before a refusal, else ''
 * @return string HTML
 */
function pg_coll_rows_editor_markup($field, $config, $posted)
{
    $id = (int)$field['id'];
    $label = '<span class="form-label d-block" id="rows_label_' . $id . '">' . h((string)$field['label']) . '</span>';

    if (isset($config['ok']) && !$config['ok']) {
        return '
                    <div class="col-12 my-5">
                        ' . $label . '
                        <p class="fg-secondary mb-0">' . h(pg_coll_rows_spec_message($config['reason'])) . '</p>
                    </div>';
    }

    static $scripts = false;
    $tags = '';
    if (!$scripts) {
        $scripts = true;
        $tags = '
                        <script src="' . OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/assets/lib/sortablejs/Sortable.min.js"></script>
                        <script src="' . OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/assets/js/pg_collection_rows.js?v=' . @filemtime(PG_FUNCTIONS_DIR . '/assets/js/pg_collection_rows.js') . '"></script>';
    }

    $flags = JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE;
    return '
                    <div class="col-12 my-5">
                        ' . $label . '
                        <div class="pg-coll-rows" data-pg-rows="' . h((string)json_encode($config, $flags)) . '" data-pg-rows-words="' . h((string)json_encode(pg_coll_rows_editor_words(), $flags)) . '" aria-labelledby="rows_label_' . $id . '"></div>
                        <input type="hidden" name="rows_' . $id . '" value="' . h((string)$posted) . '" data-pg-rows-value />' . $tags . '
                    </div>';
}

/**
 * The rows fields of a form a record screen draws or writes: field id =>
 * array('field' => form_fields row (id, name, label, office_use_only),
 * 'spec' => pg_coll_rows_spec()).
 *
 * @param int $page_id
 * @return array
 */
function pg_coll_rows_fields($page_id)
{
    $page_id = (int)$page_id;
    $out = array();
    foreach (pg_coll_field_kinds($page_id) as $field_id => $kind) {
        if ($kind['kind'] !== 'rows') continue;
        $row = db_item("SELECT id, name, label, office_use_only FROM form_fields WHERE id = '" . (int)$field_id . "' LIMIT 1");
        if (!$row) continue;
        $out[(int)$field_id] = array(
            'field' => $row,
            'spec'  => pg_coll_rows_spec_for(array('page_id' => $page_id, 'kind' => 'rows', 'reference_page_id' => $kind['reference_page_id'])),
        );
    }
    return $out;
}

/**
 * The stored value of a record's rows field ('' for a new record).
 *
 * @param int|string $form_id
 * @param int        $field_id
 * @return string
 */
function pg_coll_rows_stored($form_id, $field_id)
{
    $form_id = (int)$form_id;
    if ($form_id <= 0) return '';
    return (string)db_value("SELECT data FROM form_data WHERE form_id = '$form_id' AND form_field_id = '" . (int)$field_id . "' LIMIT 1");
}

/**
 * The rows editor of one field on Add or Edit Submitted Form: the stored
 * rows, or the list as posted when the save came back refused, with the
 * refusals marked.
 *
 * @param object     $liveform
 * @param array      $field   form_fields row: id, label
 * @param int        $page_id the record's form
 * @param int|string $form_id '' while the record is created
 * @return string HTML
 */
function pg_coll_rows_field_markup($liveform, $field, $page_id, $form_id)
{
    $kinds = pg_coll_field_kinds((int)$page_id);
    $kind = isset($kinds[(int)$field['id']]) ? $kinds[(int)$field['id']] : array('reference_page_id' => 0);
    $spec = pg_coll_rows_spec_for(array('page_id' => (int)$page_id, 'kind' => 'rows', 'reference_page_id' => $kind['reference_page_id']));
    if (!$spec['ok']) return pg_coll_rows_editor_markup($field, $spec, '');

    $name = 'rows_' . (int)$field['id'];
    $posted_json = $liveform->field_in_session($name) ? (string)$liveform->get_field_value($name) : '';
    $posted = pg_coll_rows_posted($posted_json);
    $errors = array();

    if ($posted !== null) {
        $stored = pg_coll_rows_codes(pg_coll_rows_stored($form_id, $field['id']));
        $ids = array();
        foreach (pg_coll_rows_of((int)$form_id, (int)$field['id'], $spec['page_id']) as $r) $ids[$r['reference_code']] = $r['id'];
        $rows = array();
        foreach ($posted as $row) {
            $code = (is_array($row) && isset($row['code']) && is_scalar($row['code'])) ? (string)$row['code'] : '';
            $rows[] = array(
                'id' => (in_array($code, $stored, true) && isset($ids[$code])) ? $ids[$code] : 0,
                'reference_code' => $code,
                'values' => (is_array($row) && isset($row['values']) && is_array($row['values'])) ? $row['values'] : array(),
            );
        }
        $check = pg_coll_rows_check($spec, implode(',', $stored), $posted, true);
        $errors = $check['errors'];
    } else {
        $rows = pg_coll_rows_shown_rows($spec, pg_coll_rows_of((int)$form_id, (int)$field['id'], $spec['page_id']));
        $posted_json = '';
    }

    return pg_coll_rows_editor_markup($field, pg_coll_rows_editor_config($spec, $rows, $errors), $posted_json);
}

/**
 * Checks the posted rows of a record's rows fields before the record is
 * written (pg_coll_rows_check()): a refused list marks the field, so the
 * screen writes nothing and draws the list again with its refusals.
 *
 * @param object     $liveform
 * @param int        $page_id
 * @param int|string $form_id  '' while the record is created
 * @param bool       $complete the record is saved complete
 * @param bool       $office_use_only_access
 */
function pg_coll_rows_screen_check($liveform, $page_id, $form_id, $complete, $office_use_only_access)
{
    foreach (pg_coll_rows_fields($page_id) as $field_id => $item) {
        if (!empty($item['field']['office_use_only']) && !$office_use_only_access) continue;
        $posted = pg_coll_rows_posted($liveform->get_field_value('rows_' . $field_id));
        if ($posted === null || !$item['spec']['ok']) continue;
        $check = pg_coll_rows_check($item['spec'], pg_coll_rows_stored($form_id, $field_id), $posted, $complete);
        if (!$check['ok']) {
            $message = (count($check['errors']) === 1 && $check['errors'][0]['row'] === null)
                ? $check['errors'][0]['message']
                : lang(array('string' => 'Please correct the marked rows of {var:1}', 'vars' => h((string)$item['field']['label'])));
            $liveform->mark_error('rows_' . $field_id, $message);
        }
    }
}

/**
 * Writes the posted rows of a record's rows fields once the record is
 * written (pg_coll_rows_write()). A field whose list was not sent keeps its
 * rows. Returns the messages of the fields that could not be written.
 *
 * @param object $liveform
 * @param int    $page_id
 * @param int    $form_id
 * @param bool   $complete
 * @param int    $user_id
 * @param bool   $office_use_only_access
 * @return array
 */
function pg_coll_rows_screen_write($liveform, $page_id, $form_id, $complete, $user_id, $office_use_only_access)
{
    $failed = array();
    foreach (pg_coll_rows_fields($page_id) as $field_id => $item) {
        if (!empty($item['field']['office_use_only']) && !$office_use_only_access) continue;
        $posted = pg_coll_rows_posted($liveform->get_field_value('rows_' . $field_id));
        if ($posted === null || !$item['spec']['ok']) continue;
        $result = pg_coll_rows_write($item['spec'], pg_coll_rows_stored($form_id, $field_id), $posted, $complete,
            pg_coll_rows_db_ops($item['spec'], $form_id, $item['field'], $user_id));
        if (!$result['ok']) {
            $failed[] = $result['errors'][0]['message'];
        }
    }
    return $failed;
}

/**
 * The record whose rows field lists a row: array('id', 'reference_code')
 * of the first record holding the row's code in a rows field pointing at
 * the row's form (pg_coll_reference_holds_sql()), else null.
 *
 * @param int    $page_id the row's form
 * @param string $code    the row's reference code
 * @return array|null
 */
function pg_coll_rows_parent_of($page_id, $code)
{
    $page_id = (int)$page_id;
    if ($page_id <= 0 || (string)$code === '' || !pg_coll_ready()) return null;
    $row = db_item(
        "SELECT forms.id, forms.reference_code
         FROM form_fields
         JOIN form_data ON form_data.form_field_id = form_fields.id
         JOIN forms ON forms.id = form_data.form_id
         WHERE form_fields.collection_kind = 'rows'
            AND form_fields.reference_page_id = '$page_id'
            AND " . pg_coll_reference_holds_sql('form_data.data', $code) . "
         ORDER BY forms.id
         LIMIT 1");
    return $row ? array('id' => (int)$row['id'], 'reference_code' => (string)$row['reference_code']) : null;
}

/**
 * The Summary line of a row's own record screen naming its parent with a
 * link back, '' when the record is no row (pure).
 *
 * @param array|null $parent pg_coll_rows_parent_of()
 * @return string HTML
 */
function pg_coll_rows_parent_row($parent)
{
    if (!is_array($parent) || empty($parent['id'])) return '';
    // The line of pg_submitted_form_status_row(), which this module does not load.
    return '
                                            <div class="d-flex justify-content-between gap-5 py-3 border-bottom">
                                                <dt class="fw-normal">' . h(lang('Row of')) . '</dt>
                                                <dd class="mb-0 fw-bold text-end text-break"><a class="pg-link-secondary" href="' . OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/edit_submitted_form.php?id=' . (int)$parent['id'] . '">' . h((string)$parent['reference_code']) . '</a></dd>
                                            </div>';
}

/**
 * The condition and order of a list of the rows a rows field holds (pure):
 * the records of the list's form whose reference code is one of $codes
 * (pg_coll_rows_codes()), in that order. No code lists no record; a code
 * that names no record matches nothing and leaves the others' order as it
 * is.
 *
 * @param mixed $codes
 * @return array array('where' => " AND (…)", 'order' => ORDER BY part or '')
 */
function pg_coll_rows_list_sql($codes)
{
    if (!is_array($codes) || !$codes) return array('where' => ' AND (TRUE = FALSE)', 'order' => '');
    $quoted = array();
    foreach ($codes as $code) $quoted[] = "'" . e((string)$code) . "'";
    $list = implode(', ', $quoted);
    return array(
        'where' => " AND (forms.reference_code IN ($list))",
        'order' => "FIELD(forms.reference_code, $list) ASC",
    );
}

/**
 * The stored value of the rows field named $field_name of the complete
 * record whose reference code is $parent_code, when that field keeps its
 * rows in the form on $child_page_id; '' when there is no such record or
 * field.
 *
 * @param int    $child_page_id the list's form
 * @param string $parent_code   the shown record's code
 * @param string $field_name    the rows field's name
 * @return string
 */
function pg_coll_rows_shown_value($child_page_id, $parent_code, $field_name)
{
    $child_page_id = (int)$child_page_id;
    $parent_code = (string)$parent_code;
    $field_name = trim((string)$field_name);
    if ($child_page_id <= 0 || $parent_code === '' || $field_name === '' || !pg_coll_ready()) return '';

    return (string)db_value(
        "SELECT form_data.data FROM forms
         INNER JOIN form_fields ON form_fields.page_id = forms.page_id
         INNER JOIN form_data ON form_data.form_id = forms.id AND form_data.form_field_id = form_fields.id
         WHERE forms.reference_code = '" . e($parent_code) . "'
           AND forms.complete = 1
           AND form_fields.name = '" . e($field_name) . "'
           AND form_fields.type = 'text box'
           AND form_fields.collection_kind = 'rows'
           AND form_fields.reference_page_id = '$child_page_id'
         LIMIT 1");
}

/**
 * The SQL that reads the stored value of every rows list that keeps rows of
 * child collection $child_page_id and holds one of $codes, but for the list
 * of record $form_id's field $field_id (0 for a record not written yet):
 * the lists a code written from outside the editor must not stand in
 * (pure). A code is matched as a reference text box matches one
 * (pg_coll_reference_holds_sql()). '' when there is no code.
 *
 * @param array $codes         pg_coll_rows_codes()
 * @param int   $child_page_id
 * @param int   $form_id
 * @param int   $field_id
 * @return string
 */
function pg_coll_rows_held_sql($codes, $child_page_id, $form_id, $field_id)
{
    $held = array();
    foreach ((array)$codes as $code) $held[] = pg_coll_reference_holds_sql('form_data.data', (string)$code);
    if (!$held) return '';

    return "SELECT form_data.data FROM form_data
        INNER JOIN form_fields ON form_fields.id = form_data.form_field_id
        WHERE form_fields.collection_kind = 'rows'
            AND form_fields.reference_page_id = '" . (int)$child_page_id . "'
            AND NOT (form_data.form_id = '" . (int)$form_id . "' AND form_data.form_field_id = '" . (int)$field_id . "')
            AND (" . implode(' OR ', $held) . ")";
}

/**
 * Which of $codes another list already holds, in the order of $codes
 * (pure). $held are the stored values pg_coll_rows_held_sql() reads.
 *
 * @param array $codes
 * @param array $held
 * @return array
 */
function pg_coll_rows_taken($codes, $held)
{
    $listed = array();
    foreach ((array)$held as $value) {
        foreach (pg_coll_rows_codes($value) as $code) $listed[$code] = true;
    }
    $taken = array();
    foreach ((array)$codes as $code) {
        if (isset($listed[(string)$code])) $taken[] = (string)$code;
    }
    return $taken;
}

/**
 * Why a value written to a rows field from outside the rows editor (the
 * API, a Workspace change) cannot be kept, '' when it can: only reference
 * codes of the child collection's records, at most pg_coll_rows_max(), each
 * standing in no other record's list, so no record takes over another's
 * rows. The record's own list may keep its codes. Plain text.
 *
 * @param mixed  $value
 * @param int    $child_page_id the field's child collection
 * @param int    $form_id       the record written, 0 for a new one
 * @param int    $field_id      the rows field
 * @param string $name          the field's name, for the message
 * @return string
 */
function pg_coll_rows_outside_refusal($value, $child_page_id, $form_id, $field_id, $name)
{
    if (is_scalar($value) && trim((string)$value) === '') return '';

    if (!pg_coll_rows_value_ok($value, $child_page_id)) {
        return lang(array(
            'string' => 'The field {var:1} keeps rows: only reference codes of records of its rows collection, at most {var:2}, separated by commas.',
            'vars'   => array((string)$name, pg_coll_rows_max()),
        ));
    }

    $codes = pg_coll_rows_codes($value);
    $taken = pg_coll_rows_taken($codes, db_values(pg_coll_rows_held_sql($codes, $child_page_id, $form_id, $field_id)));
    if ($taken) {
        return lang(array(
            'string' => 'The field {var:1} names rows another record keeps: {var:2}.',
            'vars'   => array((string)$name, implode(', ', $taken)),
        ));
    }

    return '';
}

/**
 * Whether a field of a form is a rows field (pure). A writer from outside the
 * rows editor checks its value (pg_coll_rows_outside_refusal()); Import
 * Submitted Forms leaves its column out, since an imported code would make
 * the record take over another record's rows, and names the column.
 *
 * @param int   $field_id
 * @param array $kinds    pg_coll_field_kinds() of the field's form
 * @return bool
 */
function pg_coll_is_rows_field($field_id, $kinds)
{
    $field_id = (int)$field_id;
    return isset($kinds[$field_id]['kind']) && $kinds[$field_id]['kind'] === 'rows';
}

/**
 * The codes a record's rows fields hold, by child collection (pure): each
 * row of $data a value of the record with its field's type, collection_kind
 * and reference_page_id. A field that is not a rows field, or names no
 * collection, is passed over; a code held twice counts once.
 *
 * @param array $data rows: type, collection_kind, reference_page_id, data
 * @return array child page id => list of codes
 */
function pg_coll_rows_held_codes($data)
{
    $out = array();
    foreach ((array)$data as $row) {
        if (!is_array($row)) continue;
        $kind = pg_coll_field_kind(isset($row['collection_kind']) ? $row['collection_kind'] : '', isset($row['type']) ? $row['type'] : '');
        $page_id = isset($row['reference_page_id']) ? (int)$row['reference_page_id'] : 0;
        if ($kind !== 'rows' || $page_id <= 0) continue;
        foreach (pg_coll_rows_codes(isset($row['data']) ? $row['data'] : '') as $code) {
            if (!isset($out[$page_id]) || !in_array($code, $out[$page_id], true)) $out[$page_id][] = $code;
        }
    }
    return $out;
}

/**
 * The ids of the rows the records $form_ids list in their rows fields: the
 * child records their codes name. A row that is itself one of $form_ids is
 * left out, so a selection holding a record and its rows counts each once.
 *
 * @param array $form_ids
 * @return array|false list of ids, false when a read failed
 */
function pg_coll_rows_held_ids($form_ids)
{
    $ids = array();
    foreach ((array)$form_ids as $id) {
        if ((int)$id > 0) $ids[(int)$id] = true;
    }
    if (!$ids || !pg_coll_ready()) return array();

    $data = array();
    foreach (array_chunk(array_keys($ids), 500) as $chunk) {
        $rows = db_items(
            "SELECT form_fields.type, form_fields.collection_kind, form_fields.reference_page_id, form_data.data
             FROM form_data
             INNER JOIN form_fields ON form_fields.id = form_data.form_field_id
             WHERE form_data.form_id IN (" . implode(',', $chunk) . ") AND form_fields.collection_kind = 'rows'");
        if ($rows === false) return false;
        $data = array_merge($data, (array)$rows);
    }

    $out = array();
    foreach (pg_coll_rows_held_codes($data) as $page_id => $codes) {
        foreach (array_chunk($codes, 500) as $chunk) {
            $quoted = array();
            foreach ($chunk as $code) $quoted[] = "'" . e($code) . "'";
            $found = db_values("SELECT id FROM forms WHERE page_id = '" . (int)$page_id . "' AND reference_code IN (" . implode(',', $quoted) . ")");
            if ($found === false) return false;
            foreach ((array)$found as $id) {
                $id = (int)$id;
                if ($id > 0 && !isset($ids[$id])) $out[$id] = $id;
            }
        }
    }
    return array_values($out);
}

/**
 * Deletes the rows of a record deleted for good, each with everything that
 * names it through $delete (pg_submitted_form_delete()). A row that cannot
 * be deleted is passed over: its record is gone already.
 *
 * @param array    $row_ids pg_coll_rows_held_ids(), read before the record went
 * @param callable $delete  function (int $id): array|false, the deleted files
 * @return array the names of the files deleted with the rows
 */
function pg_coll_rows_delete_rows($row_ids, $delete)
{
    $files = array();
    foreach ((array)$row_ids as $id) {
        $result = call_user_func($delete, (int)$id);
        if (is_array($result)) $files = array_merge($files, $result);
    }
    return $files;
}

/**
 * A delete confirmation with the rows that go with the record or records
 * (pure): $question, then how many rows are deleted with them; $question
 * alone when none.
 *
 * @param string $question the confirmation as it was (plain text)
 * @param int    $count    pg_coll_rows_held_ids() counted
 * @param bool   $many     the question is about several records
 * @return string plain text
 */
function pg_coll_rows_delete_question($question, $count, $many)
{
    $count = (int)$count;
    if ($count <= 0) return (string)$question;
    if ($many) {
        $note = ($count === 1)
            ? lang('1 row of their Record rows fields is deleted with them.')
            : lang(array('string' => '{var:1} rows of their Record rows fields are deleted with them.', 'vars' => $count));
    } else {
        $note = ($count === 1)
            ? lang('Its 1 row of a Record rows field is deleted with it.')
            : lang(array('string' => 'Its {var:1} rows of Record rows fields are deleted with it.', 'vars' => $count));
    }
    return trim((string)$question . ' ' . $note);
}

/**
 * The rows of a loaded revision that come back (pure): the record's
 * current rows whose codes the revision's value lists, in the revision's
 * order. A row removed since was deleted with its removal, so it cannot be
 * brought back and is counted in 'lost'.
 *
 * @param mixed $revision_value the rows field's value in the revision
 * @param array $current        pg_coll_rows_of() of the record now
 * @return array array('rows' => list of current rows, 'lost' => int)
 */
function pg_coll_rows_revision_keep($revision_value, $current)
{
    $by_code = array();
    foreach ((array)$current as $row) {
        if (is_array($row) && isset($row['reference_code'])) $by_code[(string)$row['reference_code']] = $row;
    }
    $rows = array();
    $lost = 0;
    foreach (pg_coll_rows_codes($revision_value) as $code) {
        if (isset($by_code[$code])) $rows[] = $by_code[$code]; else $lost++;
    }
    return array('rows' => $rows, 'lost' => $lost);
}

/**
 * The list the rows editor posts for $rows (pure): each row's code and the
 * values of the fields the table takes, as pg_coll_rows_posted() reads it.
 *
 * @param array $spec pg_coll_rows_spec(), ok
 * @param array $rows array('reference_code', 'values') each
 * @return array
 */
function pg_coll_rows_as_posted($spec, $rows)
{
    $list = array();
    foreach ((array)$rows as $row) {
        $values = array();
        foreach ($spec['fields'] as $f) {
            $fid = (int)$f['id'];
            if (isset($row['values'][$fid])) $values[(string)$fid] = $row['values'][$fid];
        }
        $list[] = array('code' => (string)$row['reference_code'], 'values' => $values);
    }
    return $list;
}

/**
 * A stored date or time of $rows shown as the record's own field shows it
 * (prepare_form_data_for_output()), so the editor and the posted list hold
 * the value whichever screen wrote it.
 *
 * @param array $spec
 * @param array $rows pg_coll_rows_of()
 * @return array
 */
function pg_coll_rows_shown_rows($spec, $rows)
{
    foreach ($rows as $i => $row) {
        foreach ($spec['fields'] as $f) {
            $fid = (int)$f['id'];
            if (in_array((string)$f['type'], array('date', 'time'), true) && isset($row['values'][$fid]) && is_string($row['values'][$fid])) {
                $rows[$i]['values'][$fid] = (string)prepare_form_data_for_output($row['values'][$fid], (string)$f['type'], false);
            }
        }
    }
    return $rows;
}

/**
 * Loads a revision's rows into the rows editors of Edit Submitted Form: a
 * rows field's list becomes the revision's list as far as its rows still
 * exist (pg_coll_rows_revision_keep()), posted as the editor posts it, so
 * saving the loaded revision writes that order and removes the rows the
 * revision did not list. Returns a note per field that lost rows.
 *
 * @param object $liveform
 * @param int    $form_id
 * @param int    $page_id
 * @param array  $values pg_record_revision_decode() of the revision
 * @param bool   $fill   false when the form already holds posted values
 * @return array notes (HTML, the labels escaped)
 */
function pg_coll_rows_revision_load($liveform, $form_id, $page_id, $values, $fill)
{
    $notes = array();
    foreach (pg_coll_rows_fields($page_id) as $field_id => $item) {
        if (!$item['spec']['ok']) continue;
        $id = (string)(int)$field_id;
        $keep = pg_coll_rows_revision_keep(
            implode(',', array_filter((array)(isset($values[$id]) ? $values[$id] : array()), 'is_string')),
            pg_coll_rows_of((int)$form_id, (int)$field_id, $item['spec']['page_id']));
        if ($fill) {
            $liveform->assign_field_value('rows_' . $id,
                json_encode(pg_coll_rows_as_posted($item['spec'], pg_coll_rows_shown_rows($item['spec'], $keep['rows']))));
        }
        if ($keep['lost'] > 0) {
            $label = ((string)$item['field']['label'] !== '') ? (string)$item['field']['label'] : (string)$item['field']['name'];
            $notes[] = lang(array(
                'string' => '{var:1}: {var:2} rows of this revision have been deleted since and cannot be brought back.',
                'vars'   => array(h($label), $keep['lost']),
            ));
        }
    }
    return $notes;
}

/**
 * Restores a revision's rows without the edit form (the API's restore):
 * each rows field keeps the revision's list as far as its rows still exist,
 * and the rows it no longer lists are deleted, as the editor's save does.
 * A row's own values are not touched: they are in the row's History.
 *
 * @param int   $form_id
 * @param int   $page_id
 * @param array $values pg_record_revision_decode() of the revision
 * @return void
 */
function pg_coll_rows_revision_restore($form_id, $page_id, $values)
{
    foreach (pg_coll_rows_fields($page_id) as $field_id => $item) {
        if (!$item['spec']['ok']) continue;
        $id = (string)(int)$field_id;
        $stored = pg_coll_rows_codes(pg_coll_rows_stored($form_id, $field_id));
        $keep = pg_coll_rows_revision_keep(
            implode(',', array_filter((array)(isset($values[$id]) ? $values[$id] : array()), 'is_string')),
            pg_coll_rows_of((int)$form_id, (int)$field_id, $item['spec']['page_id']));
        $codes = array();
        foreach ($keep['rows'] as $row) $codes[] = (string)$row['reference_code'];
        $ops = pg_coll_rows_db_ops($item['spec'], $form_id, $item['field'], 0);
        if (!call_user_func($ops['set'], pg_coll_rows_value($codes))) continue;
        foreach ($stored as $code) {
            if (!in_array($code, $codes, true)) call_user_func($ops['delete'], $code);
        }
    }
}

/**
 * Whether any form has a Record rows field, so a list's delete confirmation
 * reads the rows of its records only on a site that keeps rows.
 *
 * @return bool
 */
function pg_coll_rows_in_use()
{
    static $in_use = null;
    if ($in_use === null) {
        $in_use = pg_coll_ready() && (bool)db_value("SELECT 1 FROM form_fields WHERE collection_kind = 'rows' LIMIT 1");
    }
    return $in_use;
}
