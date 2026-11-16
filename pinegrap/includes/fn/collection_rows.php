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
 * fields (no information field) in form order, each with its kind and the
 * values of its options; a reference, product or page field also carries
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
            "SELECT form_field_id, value FROM form_field_options
             WHERE form_field_id IN (" . implode(',', $ids) . ")
             ORDER BY sort_order, id") as $o) {
            $fields[(int)$o['form_field_id']]['options'][] = (string)$o['value'];
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
    $refuse = function ($message, $row = null, $field = null) {
        return array('ok' => false, 'errors' => array(array('row' => $row, 'field' => $field, 'message' => $message)));
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
    if ($errors) return array('ok' => false, 'errors' => $errors);

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
