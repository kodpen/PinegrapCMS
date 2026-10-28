<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Collections: designer forms whose records point at the records of another
 * form. A record keeps the reference as a value of one of its own fields:
 * the other record's reference code, or its address name when the other
 * form has pretty URLs. Several references sit in one value joined by ", ",
 * as a multiple pick list stores its choices.
 *
 * In a form list or form detail widget an element prints a field of the
 * record a reference points at. The element is bound to the sentinel
 * '__ref' and keeps beside it which own field holds the reference, which
 * form the reference points into and which of that form's fields to print:
 *   props._bindings.text   = '__ref'
 *   props._bindParams.text = array('field' => 'category', 'page_id' => 12, 'show' => 'title')
 * The renderer gives each such element a token of its own (__ref_<n>, the
 * way links to another page are expanded), reads the records every element
 * points at in one query per form and request, and puts the value under the
 * element's token beside the record's own fields, so pg_sw_apply_tokens()
 * escapes, formats and filters it once like any field. The editor's twin is
 * _swRefSpec() in style_designer.js.
 *
 * A form is marked as a collection (custom_form_pages.collection, 11.0.43)
 * and each field may carry a kind beyond its type (form_fields.collection_kind)
 * and, for a reference, the form it points into (reference_page_id). The
 * canvas is the record: the kind sits on the control (props._cf.kind,
 * props._cf.ref) and the design save writes the columns
 * (pg_cf_sync_page_form()).
 *
 * Only complete records are read, and never a field kept for staff only.
 * The values print as stored: the translation extracts a widget's own
 * records only, so a lookup of another form's text would find nothing and
 * count against the page under the "reviewed" policy.
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
 * The most references one value is read for; the rest are left out.
 */
function pg_coll_ref_max()
{
    return 50;
}

/**
 * The record fields an element may print besides the other form's own
 * fields: the record's reference code and its address name.
 */
function pg_coll_ref_standard_shows()
{
    return array('reference_code', 'address_name');
}

/**
 * A stored reference spec in the shape the renderer reads: `field` and
 * `show` cut to the characters a binding token keeps and lowercased, as the
 * record index is keyed; `page_id` a positive id, else 0 (a tab of the
 * design not published yet, 'tab:<key>', is 0 here until Publish resolves
 * it).
 *
 * @param mixed $spec
 * @return array
 */
function pg_coll_ref_spec($spec)
{
    $out = array('field' => '', 'page_id' => 0, 'show' => '');
    if (!is_array($spec)) return $out;

    foreach (array('field', 'show') as $key) {
        if (isset($spec[$key]) && is_string($spec[$key])) {
            $out[$key] = strtolower(preg_replace('/[^a-z0-9_\-]/i', '', $spec[$key]));
        }
    }

    $page_id = isset($spec['page_id']) ? $spec['page_id'] : 0;
    if (is_int($page_id) && $page_id > 0) {
        $out['page_id'] = $page_id;
    } elseif (is_string($page_id) && $page_id !== '' && ctype_digit($page_id) && (int)$page_id > 0) {
        $out['page_id'] = (int)$page_id;
    }

    return $out;
}

/**
 * The pre-pass over a widget tree: every binding to '__ref' becomes a token
 * of its own (__ref_0, __ref_1, ... in walk order) and its spec is collected
 * under that token.
 *
 * @param array $tree the widget tree, changed in place
 * @return array token => spec
 */
function pg_coll_ref_bindings(&$tree)
{
    $refs = array();
    _pg_coll_ref_walk($tree, $refs, 0);
    return $refs;
}

function _pg_coll_ref_walk(&$node, &$refs, $depth)
{
    if (!is_array($node) || $depth > 80) return;
    if (isset($node['props']['_bindings']) && is_array($node['props']['_bindings'])) {
        foreach ($node['props']['_bindings'] as $prop => $value) {
            if ($value !== '__ref') continue;
            $token = '__ref_' . count($refs);
            $node['props']['_bindings'][$prop] = $token;
            $refs[$token] = pg_coll_ref_spec(isset($node['props']['_bindParams'][$prop]) ? $node['props']['_bindParams'][$prop] : null);
        }
    }
    if (empty($node['children']) || !is_array($node['children'])) return;
    foreach ($node['children'] as &$child) _pg_coll_ref_walk($child, $refs, $depth + 1);
    unset($child);
}

/**
 * The references a stored value holds: split at commas, trimmed, each a
 * reference code or an address name (letters, digits, "_", "-", "[", "]"),
 * a repeat left out, at most pg_coll_ref_max().
 *
 * @param mixed $value
 * @return array
 */
function pg_coll_ref_codes($value)
{
    if (!is_scalar($value)) return array();
    $codes = array();
    foreach (explode(',', (string)$value) as $part) {
        $part = trim($part);
        if ($part === '' || strlen($part) > 255 || !preg_match('/^[A-Za-z0-9_\-\[\]]+$/', $part)) continue;
        if (in_array($part, $codes, true)) continue;
        $codes[] = $part;
        if (count($codes) >= pg_coll_ref_max()) break;
    }
    return $codes;
}

/**
 * What the references of a page's records ask for: the other form's page id
 * => its references, each listed once.
 *
 * @param array $refs    token => spec (pg_coll_ref_bindings())
 * @param array $records record id => fields as stored (pg_sw_index_form_data())
 * @return array
 */
function pg_coll_ref_wanted($refs, $records)
{
    $wanted = array();
    foreach ((array)$refs as $spec) {
        if (empty($spec['page_id']) || $spec['field'] === '' || $spec['show'] === '') continue;
        foreach ((array)$records as $fields) {
            if (!isset($fields[$spec['field']]['data'])) continue;
            foreach (pg_coll_ref_codes($fields[$spec['field']]['data']) as $code) {
                $wanted[$spec['page_id']][$code] = true;
            }
        }
    }
    return array_map('array_keys', $wanted);
}

/**
 * The value an element prints for one record: the `show` field of each
 * record its references point at, in the order the references are stored,
 * joined by ", "; a reference that points at nothing is left out. The value
 * has the shape of an indexed field (data, type, wysiwyg, translate) and
 * takes the type of the first record that answers; null when none does.
 *
 * @param array $spec   pg_coll_ref_spec()
 * @param array $fields the record's own fields as stored
 * @param array $loaded pg_coll_ref_load() of the spec's page
 * @return array|null
 */
function pg_coll_ref_value($spec, $fields, $loaded)
{
    if (empty($spec['page_id']) || $spec['field'] === '' || $spec['show'] === '') return null;
    if (!isset($fields[$spec['field']]['data']) || !is_array($loaded) || empty($loaded['codes'])) return null;

    $out = null;
    foreach (pg_coll_ref_codes($fields[$spec['field']]['data']) as $code) {
        if (!isset($loaded['codes'][$code])) continue;
        $id = $loaded['codes'][$code];
        if (in_array($spec['show'], pg_coll_ref_standard_shows(), true)) {
            $value = array('data' => isset($loaded['rows'][$id][$spec['show']]) ? (string)$loaded['rows'][$id][$spec['show']] : '', 'type' => '', 'wysiwyg' => 0, 'translate' => 0);
        } elseif (isset($loaded['records'][$id][$spec['show']])) {
            $value = $loaded['records'][$id][$spec['show']];
        } else {
            continue;
        }
        if (!isset($value['data']) || (string)$value['data'] === '') continue;
        if ($out === null) {
            $out = $value;
        } else {
            $out['data'] .= ', ' . $value['data'];
        }
    }
    return $out;
}

/**
 * The records of form page $page_id that $codes name by reference code or
 * by address name, complete ones only, with their fields minus the staff
 * only ones: array('codes' => reference => record id, 'rows' => record id
 * => reference_code/address_name, 'records' => record id => fields as
 * pg_sw_index_form_data() indexes them). Read once per form, reference and
 * request.
 *
 * @param int   $page_id
 * @param array $codes
 * @return array
 */
function pg_coll_ref_load($page_id, $codes)
{
    static $cache = array();
    $page_id = (int)$page_id;
    if (!isset($cache[$page_id])) $cache[$page_id] = array('codes' => array(), 'rows' => array(), 'records' => array(), 'asked' => array());
    $have = &$cache[$page_id];

    $ask = array();
    foreach ((array)$codes as $code) {
        $code = (string)$code;
        if ($code === '' || isset($have['asked'][$code])) continue;
        $have['asked'][$code] = true;
        $ask[] = "'" . e($code) . "'";
    }

    if ($page_id > 0 && $ask) {
        $in = implode(',', $ask);
        $rows = db_items(
            "SELECT id, reference_code, address_name
             FROM forms
             WHERE page_id = '$page_id'
               AND complete = 1
               AND (reference_code IN ($in) OR address_name IN ($in))"
        );
        $ids = array();
        foreach ((array)$rows as $row) {
            $id = (int)$row['id'];
            $ids[$id] = $id;
            $have['rows'][$id] = array('reference_code' => (string)$row['reference_code'], 'address_name' => (string)$row['address_name']);
            if ($row['reference_code'] !== '') $have['codes'][(string)$row['reference_code']] = $id;
            if ($row['address_name'] !== '' && !isset($have['codes'][(string)$row['address_name']])) {
                $have['codes'][(string)$row['address_name']] = $id;
            }
        }
        if ($ids) {
            $data_rows = db_items(
                "SELECT
                    form_data.form_id,
                    form_data.data,
                    form_data.name      AS data_name,
                    form_fields.name    AS field_name,
                    form_fields.label   AS field_label,
                    form_fields.type    AS field_type,
                    form_fields.wysiwyg AS field_wysiwyg,"
                    . (pg_schema_has('form_fields', 'translate_values') ? " form_fields.translate_values AS field_translate," : "") . "
                    files.name          AS file_name
                 FROM form_data
                 LEFT JOIN form_fields ON form_data.form_field_id = form_fields.id
                 LEFT JOIN files       ON form_data.file_id       = files.id
                 WHERE form_data.form_id IN (" . implode(',', $ids) . ")
                   AND form_fields.office_use_only = 0"
            );
            $have['records'] = pg_sw_index_form_data($data_rows) + $have['records'];
        }
    }

    return array('codes' => $have['codes'], 'rows' => $have['rows'], 'records' => $have['records']);
}

/**
 * Put each '__ref' element's value beside the fields of every record it is
 * drawn for: $by_form[record id]['__ref_<n>']. The references are read from
 * the values as stored ($by_form_source).
 *
 * @param array $refs           token => spec (pg_coll_ref_bindings())
 * @param array $by_form        record id => fields, changed in place
 * @param array $by_form_source record id => fields as stored
 */
function pg_coll_ref_fill($refs, &$by_form, $by_form_source)
{
    if (!$refs || !is_array($by_form_source)) return;

    $loaded = array();
    foreach (pg_coll_ref_wanted($refs, $by_form_source) as $page_id => $codes) {
        $loaded[$page_id] = pg_coll_ref_load($page_id, $codes);
    }

    foreach ($by_form_source as $id => $fields) {
        foreach ($refs as $token => $spec) {
            $value = pg_coll_ref_value($spec, $fields, isset($loaded[$spec['page_id']]) ? $loaded[$spec['page_id']] : array());
            if ($value !== null) $by_form[$id][$token] = $value;
        }
    }
}

/**
 * Whether the collections columns are there (2026.11.0, 11.0.43):
 * custom_form_pages.collection, form_fields.collection_kind and
 * form_fields.reference_page_id. Before the upgrade runs, nothing reads or
 * writes them and every form behaves as before.
 *
 * @return bool
 */
function pg_coll_ready()
{
    static $ready = null;
    if ($ready === null) {
        $ready = pg_schema_has('custom_form_pages', 'collection')
            && pg_schema_has('form_fields', 'collection_kind')
            && pg_schema_has('form_fields', 'reference_page_id');
    }
    return $ready;
}

/**
 * The kinds a field of a collection may have beyond its type, and the field
 * types each one is given to. A text, a rich text, a date, a file or an
 * option set is the field's type itself; these say what the type cannot:
 * a number, an image, a reference to another form's records, a product or a
 * page of the site (its id). The editor's twin is _CF_KINDS in
 * style_designer.js.
 *
 * @return array kind => field types
 */
function pg_coll_kinds()
{
    return array(
        'number'    => array('text box'),
        'image'     => array('file upload'),
        'reference' => array('text box', 'pick list', 'radio button', 'check box'),
        'product'   => array('pick list'),
        'page'      => array('pick list'),
    );
}

/**
 * The kind a field is written with: $kind when it is one of pg_coll_kinds()
 * given to the field's $type, else ''. A field that changes type loses a
 * kind its new type cannot have.
 *
 * @param mixed  $kind
 * @param string $type
 * @return string
 */
function pg_coll_field_kind($kind, $type)
{
    $kinds = pg_coll_kinds();
    $kind  = is_string($kind) ? strtolower(trim($kind)) : '';
    if (!isset($kinds[$kind]) || !in_array((string)$type, $kinds[$kind], true)) return '';
    return $kind;
}

/**
 * The form a reference field points into, as written: a positive page id
 * for a field of kind 'reference', else 0. Whether that page holds a form
 * is the caller's check (pg_coll_reference_target()).
 *
 * @param string $kind pg_coll_field_kind()
 * @param mixed  $page_id
 * @return int
 */
function pg_coll_field_reference($kind, $page_id)
{
    if ($kind !== 'reference') return 0;
    if (is_int($page_id)) return max(0, $page_id);
    if (is_string($page_id) && ctype_digit($page_id)) return (int)$page_id;
    return 0;
}

/**
 * $page_id when it holds a form (a custom_form_pages row), else 0. A
 * reference that points nowhere is written as none.
 *
 * @param int $page_id
 * @return int
 */
function pg_coll_reference_target($page_id)
{
    $page_id = (int)$page_id;
    if ($page_id <= 0) return 0;
    return db_value("SELECT COUNT(*) FROM custom_form_pages WHERE page_id = '$page_id'") ? $page_id : 0;
}

/**
 * A number as a collection's number field stores it: trimmed, a decimal
 * comma read as a point, a plus sign and a leading point's missing zero
 * settled ('+.5' is '0.5'); '' for an empty value (the required check is the
 * form's). null when it is not a plain decimal number: a thousands
 * separator, an exponent and anything longer than 30 characters are
 * refused, so the stored text sorts and compares as the number it shows.
 *
 * @param mixed $value
 * @return string|null
 */
function pg_coll_number_value($value)
{
    if (!is_string($value)) return null;
    $value = trim($value);
    if ($value === '') return '';
    if (strlen($value) > 30) return null;

    $value = str_replace(',', '.', $value);
    if (!preg_match('/^([+-]?)(\d*)(?:\.(\d*))?$/', $value, $m)) return null;
    $int  = $m[2];
    $frac = isset($m[3]) ? $m[3] : '';
    if ($int === '' && $frac === '') return null;
    if ($int === '') $int = '0';

    return ($m[1] === '-' ? '-' : '') . $int . ($frac !== '' ? '.' . $frac : '');
}

/**
 * The image types a collection's image field takes, by extension. SVG is
 * left out: it is a document that can carry script.
 *
 * @return array extension => media type
 */
function pg_coll_image_types()
{
    return array(
        'jpg'  => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'png'  => 'image/png',
        'gif'  => 'image/gif',
        'webp' => 'image/webp',
    );
}

/**
 * Whether an uploaded file's name is one of pg_coll_image_types().
 *
 * @param mixed $name
 * @return bool
 */
function pg_coll_image_name_ok($name)
{
    if (!is_string($name) || !preg_match('/\.([A-Za-z0-9]+)$/', $name, $m)) return false;
    $types = pg_coll_image_types();
    return isset($types[strtolower($m[1])]);
}

/**
 * Whether every reference in a posted value is a record of the form the
 * field points into. $value is one code, several joined by commas (a text
 * box) or a list of them (a check box group or a multiple pick list); an
 * empty one is left out, and an empty value passes (the required check is
 * the form's).
 *
 * @param mixed $value
 * @param array $codes reference code => label (pg_coll_reference_options())
 * @return bool
 */
function pg_coll_reference_ok($value, $codes)
{
    $parts = is_array($value) ? $value : explode(',', (string)$value);
    foreach ($parts as $part) {
        if (!is_scalar($part)) return false;
        $part = trim((string)$part);
        if ($part === '') continue;
        if (!isset($codes[$part])) return false;
    }
    return true;
}

/**
 * The attributes a control of a field's kind is drawn with: a number field
 * takes decimal numbers, an image field offers pg_coll_image_types() and
 * shows the chosen picture (pg_cf_validation_script()). A
 * reference is drawn by its choices instead (pg_coll_reference_options()).
 *
 * @param string $kind
 * @param string $type
 * @return array attribute => value
 */
function pg_coll_input_attrs($kind, $type)
{
    $kind = pg_coll_field_kind($kind, $type);
    if ($kind === 'number') {
        return array('type' => 'number', 'step' => 'any', 'inputmode' => 'decimal');
    }
    if ($kind === 'image') {
        return array('accept' => implode(',', array_values(array_unique(pg_coll_image_types()))), 'data-pg-image-preview' => '');
    }
    return array();
}

/**
 * The records a reference field may point at: the complete records of the
 * form on $page_id, reference code => label (pg_coll_reference_labels()).
 * The label is the record's Title field, else its reference code. At most 500 are read,
 * the most recent; a form with more is better pointed at by a text box.
 *
 * @param int $page_id
 * @return array
 */
function pg_coll_reference_options($page_id)
{
    static $cache = array();
    $page_id = (int)$page_id;
    if ($page_id <= 0) return array();
    if (isset($cache[$page_id])) return $cache[$page_id];

    $options = array();
    foreach ((array)db_items(
        "SELECT
            forms.reference_code,
            (SELECT form_data.data
             FROM form_data
             JOIN form_fields ON form_data.form_field_id = form_fields.id
             WHERE form_data.form_id = forms.id
               AND form_fields.rss_field = 'title'
               AND form_fields.office_use_only = 0
             LIMIT 1) AS title
         FROM forms
         WHERE forms.page_id = '$page_id'
           AND forms.complete = 1
           AND forms.reference_code != ''
         ORDER BY forms.id DESC
         LIMIT 500") as $row) {
        $code  = (string)$row['reference_code'];
        $title = trim((string)$row['title']);
        $options[$code] = ($title !== '') ? $title : $code;
    }

    return $cache[$page_id] = pg_coll_reference_labels($options);
}

/**
 * Reference choices as they are offered: a label two records share gets
 * each record's code after it, so every choice reads differently (a pick
 * list is keyed by its labels), then sorted by label.
 *
 * @param array $options reference code => label
 * @return array
 */
function pg_coll_reference_labels($options)
{
    $count = array_count_values(array_map('strval', $options));
    foreach ($options as $code => $label) {
        if ($count[(string)$label] > 1 && $label !== (string)$code) $options[$code] = $label . ' (' . $code . ')';
    }
    natcasesort($options);
    return $options;
}

/**
 * The kinds of a form's fields: field id => array('kind', 'reference_page_id'),
 * only the fields that have one. Empty before step 11.0.43 has run.
 *
 * @param int $page_id
 * @return array
 */
function pg_coll_field_kinds($page_id)
{
    static $cache = array();
    $page_id = (int)$page_id;
    if ($page_id <= 0 || !pg_coll_ready()) return array();
    if (isset($cache[$page_id])) return $cache[$page_id];

    $kinds = array();
    foreach ((array)db_items(
        "SELECT id, type, collection_kind, reference_page_id
         FROM form_fields
         WHERE page_id = '$page_id' AND collection_kind != ''") as $row) {
        $kind = pg_coll_field_kind($row['collection_kind'], $row['type']);
        if ($kind === '') continue;
        $kinds[(int)$row['id']] = array('kind' => $kind, 'reference_page_id' => (int)$row['reference_page_id']);
    }

    return $cache[$page_id] = $kinds;
}

/**
 * Marks the kind errors of one posted field: a number that is not one (a
 * valid one is stored as pg_coll_number_value() writes it), an image field
 * given another file, a reference to a record the form it points into does
 * not have. Run after the field's own checks; a field already marked is
 * left alone.
 *
 * @param liveform $liveform
 * @param array    $field  form_fields row: id, label, type
 * @param array    $kinds  pg_coll_field_kinds()
 */
function pg_coll_check_field($liveform, $field, $kinds)
{
    $id = (int)$field['id'];
    if (!isset($kinds[$id]) || $liveform->check_field_error($field['id'])) return;
    $kind  = $kinds[$id]['kind'];
    $label = h(function_exists('pg_tr_label') ? pg_tr_label($field['label']) : $field['label']);

    if ($kind === 'image') {
        $name = isset($_FILES[$field['id']]['name']) ? $_FILES[$field['id']]['name'] : '';
        if (is_string($name) && $name !== '' && !pg_coll_image_name_ok($name)) {
            $liveform->mark_error($field['id'], lang(array('string' => 'Please choose an image (JPG, PNG, GIF or WebP) for {var:1}', 'vars' => $label)));
        }
        return;
    }

    $value = $liveform->get_field_value($field['id']);

    if ($kind === 'number') {
        $number = pg_coll_number_value(is_array($value) ? '' : (string)$value);
        if ($number === null) {
            $liveform->mark_error($field['id'], lang(array('string' => 'Please enter a number for {var:1}', 'vars' => $label)));
        } elseif ($number !== (string)$value) {
            $liveform->assign_field_value($field['id'], $number);
        }
        return;
    }

    // A reference whose form is gone points nowhere and is not checked.
    if ($kind === 'reference' && $kinds[$id]['reference_page_id'] > 0
        && !pg_coll_reference_ok($value, pg_coll_reference_options($kinds[$id]['reference_page_id']))) {
        $liveform->mark_error($field['id'], lang(array('string' => 'Please choose one of the listed items for {var:1}', 'vars' => $label)));
    }
}

/**
 * The ORDER BY part that sorts a number field's stored text (joined as
 * $alias, pg_coll_number_value() wrote it) as the number it shows; a
 * record without a value comes last in either direction.
 *
 * @param string $alias     the joined form_data alias
 * @param string $direction ASC or DESC
 * @return string
 */
function pg_coll_number_order_sql($alias, $direction)
{
    $alias     = preg_replace('/[^a-z0-9_]/i', '', (string)$alias);
    $direction = (strtoupper((string)$direction) === 'ASC') ? 'ASC' : 'DESC';
    return "($alias.data IS NULL OR $alias.data = '') ASC, CAST($alias.data AS DECIMAL(65,15)) $direction";
}

/**
 * The comparison a filter on a reference text box makes: whether the
 * comma-joined codes of $data_sql hold $code. Codes never hold a comma.
 *
 * @param string $data_sql SQL giving the stored value
 * @param string $code
 * @return string SQL, 1 when held, else 0
 */
function pg_coll_reference_holds_sql($data_sql, $code)
{
    return "(FIND_IN_SET('" . e((string)$code) . "', REPLACE(IFNULL($data_sql, ''), ', ', ',')) > 0)";
}

/**
 * The record of form page $page_id a filter value names, by address name
 * or reference code, byte for byte: array('code' => its reference code,
 * 'label' => its Title field, else the code), complete records only; null
 * when none has it. A list filtered by ?category=news compares the code
 * and prints the label.
 *
 * @param int    $page_id
 * @param string $value
 * @return array|null
 */
function pg_coll_reference_lookup($page_id, $value)
{
    $page_id = (int)$page_id;
    $value   = (string)$value;
    if ($page_id <= 0 || $value === '' || strlen($value) > 255) return null;

    $row = db_item(
        "SELECT
            forms.reference_code,
            (SELECT form_data.data
             FROM form_data
             JOIN form_fields ON form_data.form_field_id = form_fields.id
             WHERE form_data.form_id = forms.id
               AND form_fields.rss_field = 'title'
               AND form_fields.office_use_only = 0
             LIMIT 1) AS title
         FROM forms
         WHERE forms.page_id = '$page_id'
           AND forms.complete = 1
           AND forms.reference_code != ''
           AND (CAST(forms.address_name AS BINARY) = CAST('" . e($value) . "' AS BINARY)
                OR CAST(forms.reference_code AS BINARY) = CAST('" . e($value) . "' AS BINARY))
         ORDER BY (CAST(forms.address_name AS BINARY) = CAST('" . e($value) . "' AS BINARY)) DESC, forms.id ASC
         LIMIT 1");
    if (!$row) return null;

    $code  = (string)$row['reference_code'];
    $title = trim((string)$row['title']);
    return array('code' => $code, 'label' => ($title !== '') ? $title : $code);
}
