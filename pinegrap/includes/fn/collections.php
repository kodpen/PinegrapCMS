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
