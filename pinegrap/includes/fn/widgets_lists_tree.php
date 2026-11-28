<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

// A module of functions.php: the record tree sources of the lists inside a
// record (widgets_lists.php), drawn in the two form views.
//
// Loaded by functions.php through require_once, never on its own.
//
// A collection names another record with a reference field: a category its
// parent category, an article its category. Two lists follow those links:
//
//   form_record.children  the records that name the row's record in the
//                         field the list names, in this form or another: a
//                         category's sub-categories (Parent), its articles
//                         (Category). Each row carries its own id as the
//                         key, so a list of the same source inside its row
//                         lists the level below: the tree in one widget.
//   form_record.crumbs    the records above the record, root first, read
//                         through the field the list names (an article's
//                         Category, then that category's own field of the
//                         same name): the breadcrumbs of its page.
//
// Only records their form's public record page shows are listed, under
// that page's filters (pg_ap_record_page()): a draft or a form with no
// public page gives no row. Unlike the other form record sources these read
// the database, one query per form and level, never per row.
if (!defined('PG_FUNCTIONS_DIR')) {
    exit;
}

// The tree sources, in the shape of pg_sw_list_sources(). The title comes
// first: the A to Z order compares a row's first value.
function pg_sw_list_tree_sources()
{
    $tokens = array(
        array('__record_title', lang('Record title')),
        array('__record_url', lang('Record address')),
        array('__record_code', lang('Record reference code')),
    );
    return array(
        'form_record.children' => array(
            'family'       => 'form_record',
            'key'          => 'submitted_form_id',
            'label'        => lang('Records that point at the record'),
            'arg'          => 'field',
            'arg_required' => true,
            'tokens'       => $tokens,
        ),
        'form_record.crumbs' => array(
            'family'       => 'form_record',
            'key'          => 'submitted_form_id',
            'label'        => lang('Path to the record'),
            'arg'          => 'field',
            'arg_required' => true,
            'tokens'       => $tokens,
        ),
    );
}

// The most levels a path climbs, and the most records one level lists.
function pg_sw_list_tree_max_depth()
{
    return 10;
}

function pg_sw_list_tree_max_rows()
{
    return 500;
}

// One row of either source. $record: id, reference_code, address_name,
// title; $page: its record page as pg_ap_record_page() gives it, with
// 'pretty'. The id rides along as the key of a list inside the row. Raw
// values: the list pass escapes them once.
function pg_sw_list_tree_row($record, $page)
{
    $code  = isset($record['reference_code']) ? (string)$record['reference_code'] : '';
    $name  = isset($record['address_name']) ? (string)$record['address_name'] : '';
    $title = isset($record['title']) ? trim((string)$record['title']) : '';
    return array(
        '__record_title'    => ($title !== '') ? $title : $code,
        '__record_url'      => pg_sw_record_url($page['page_name'], $code, $name, !empty($page['pretty'])),
        '__record_code'     => $code,
        'submitted_form_id' => (string)(int)$record['id'],
    );
}

// The child rows of each parent, by parent id: $parents = id =>
// array(reference_code, address_name); $children = records with 'ref' (the
// value of the field that names a parent: codes or address names joined by
// ', ') and 'page' (their record page). A child naming its parent twice is
// listed once; a child is never its own child. Pure: the order is the
// children's.
function pg_sw_list_tree_children_of($parents, $children)
{
    $by_name = array();
    foreach ((array)$parents as $id => $p) {
        foreach (array('address_name', 'reference_code') as $k) {
            $v = isset($p[$k]) ? (string)$p[$k] : '';
            if ($v !== '' && !isset($by_name[$v])) $by_name[$v] = (int)$id;
        }
    }
    $out = array();
    foreach ((array)$children as $child) {
        $seen = array();
        foreach (pg_coll_ref_codes(isset($child['ref']) ? $child['ref'] : '') as $name) {
            if (!isset($by_name[$name])) continue;
            $pid = $by_name[$name];
            if (isset($seen[$pid]) || $pid === (int)$child['id']) continue;
            $seen[$pid] = true;
            $out[$pid][] = pg_sw_list_tree_row($child, $child['page']);
        }
    }
    return $out;
}

// The rows of a path, root first, from the steps a walk found nearest
// first: each a record with 'page'. A record met twice (a loop in the
// parents) ends the path there. Pure.
function pg_sw_list_tree_path_rows($steps, $self_id)
{
    $out  = array();
    $seen = array((int)$self_id => true);
    foreach ((array)$steps as $step) {
        $id = (int)$step['id'];
        if (isset($seen[$id])) break;
        $seen[$id] = true;
        $out[] = pg_sw_list_tree_row($step, $step['page']);
    }
    return array_reverse($out);
}

// The public record page of form page $form_page_id with whether its
// records are named in their addresses, null when it has none (the records
// are then never listed). Asked once per form per request.
function _pg_sw_list_tree_page($form_page_id)
{
    static $cache = array();
    $form_page_id = (int)$form_page_id;
    if ($form_page_id <= 0) return null;
    if (!array_key_exists($form_page_id, $cache)) {
        require_once(PG_FUNCTIONS_DIR . '/includes/fn/activitypub_outbox.php');
        $page = pg_ap_record_page($form_page_id);
        if ($page !== null) $page['pretty'] = pg_sw_form_pretty_urls($form_page_id);
        $cache[$form_page_id] = $page;
    }
    return $cache[$form_page_id];
}

// The reference fields $arg names (name, name with only a-z, 0-9 and _,
// or label, as a list's field is matched), not kept for office use: those
// of form $form_page_id when $in_form, else those of any form pointing
// into $form_page_id. Each: id, page_id (its form), reference_page_id.
function _pg_sw_list_tree_fields($form_page_id, $arg, $in_form)
{
    $form_page_id = (int)$form_page_id;
    if ($form_page_id <= 0 || !function_exists('pg_coll_ready') || !pg_coll_ready()) return array();
    $where = $in_form ? "page_id = '$form_page_id'" : "reference_page_id = '$form_page_id'";
    $out = array();
    foreach ((array)db_items(
        "SELECT id, page_id, name, label, type, collection_kind, reference_page_id
         FROM form_fields
         WHERE $where AND office_use_only = 0 AND reference_page_id > 0") as $f) {
        if (pg_coll_field_kind((string)$f['collection_kind'], (string)$f['type']) !== 'reference') continue;
        $name = mb_strtolower(trim((string)$f['name']), 'UTF-8');
        $row  = array('name' => $name, 'bare' => preg_replace('/[^a-z0-9_]/', '', $name), 'label' => (string)$f['label']);
        if (_pg_sw_list_names_field($row, $arg)) {
            $out[] = array('id' => (int)$f['id'], 'page_id' => (int)$f['page_id'], 'reference_page_id' => (int)$f['reference_page_id']);
        }
    }
    return $out;
}

// Records of form $form_page_id its public record page shows, with their
// value of field $field_id as 'ref' and their Title: $where narrows them
// (SQL on forms and the joined value fd). Each gets 'page'.
function _pg_sw_list_tree_records($form_page_id, $field_id, $where)
{
    $page = _pg_sw_list_tree_page($form_page_id);
    if ($page === null) return array();
    $filter = pg_sw_record_filter_clause((int)$form_page_id, pg_sw_record_view_filters($page['cfg']), $page['page_id'], true);
    if ($filter['closed']) return array();
    $rows = db_items(
        "SELECT forms.id, forms.reference_code, forms.address_name, fd.data AS ref,
                (SELECT t.data FROM form_data t JOIN form_fields tf ON t.form_field_id = tf.id
                  WHERE t.form_id = forms.id AND tf.rss_field = 'title' AND tf.office_use_only = 0 LIMIT 1) AS title
         FROM forms
         LEFT JOIN form_data fd ON fd.form_id = forms.id AND fd.form_field_id = '" . (int)$field_id . "'
         " . $filter['join'] . "
         WHERE forms.page_id = '" . (int)$form_page_id . "'
           AND forms.complete = 1
           AND forms.reference_code != ''
           AND ($where)
           " . $filter['where'] . "
         ORDER BY forms.id
         LIMIT " . pg_sw_list_tree_max_rows());
    $out = array();
    foreach ((array)$rows as $row) {
        $row['page'] = $page;
        $out[] = $row;
    }
    return $out;
}

// The rows of form_record.children or form_record.crumbs for every key a
// level asks for: $wanted = record id => arg => true; returns record id =>
// arg => rows.
function pg_sw_list_tree_source_rows($source, $wanted)
{
    $out = array();
    $ids = array();
    foreach ((array)$wanted as $key => $args) {
        if ((int)$key > 0) $ids[(int)$key] = (int)$key;
        foreach (array_keys((array)$args) as $arg) $out[$key][$arg] = array();
    }
    if (!$ids) return $out;
    $keys = array();
    foreach ((array)db_items("SELECT id, page_id, reference_code, address_name FROM forms WHERE id IN (" . implode(',', $ids) . ") AND complete = 1") as $r) {
        $keys[(int)$r['page_id']][(int)$r['id']] = $r;
    }

    foreach ($wanted as $key => $args) {
        foreach (array_keys((array)$args) as $arg) {
            foreach ($keys as $page_id => $records) {
                if (!isset($records[(int)$key])) continue;
                if ($source === 'form_record.children') {
                    $out[$key][$arg] = _pg_sw_list_tree_children($page_id, $records, (string)$arg, (int)$key);
                } else {
                    $out[$key][$arg] = _pg_sw_list_tree_path($page_id, (int)$key, (string)$arg);
                }
            }
        }
    }
    return $out;
}

// The records of any form that name record $key (of form $page_id, beside
// the other keys of that form in $records) in field $arg. One query per
// field and level, cached for the request, so every key of a level is read
// together.
function _pg_sw_list_tree_children($page_id, $records, $arg, $key)
{
    static $cache = array();
    $ck = $page_id . '|' . $arg . '|' . implode(',', array_keys($records));
    if (!isset($cache[$ck])) {
        $names = array();
        foreach ($records as $r) {
            foreach (array('reference_code', 'address_name') as $k) {
                if ((string)$r[$k] !== '') $names[] = "'" . e((string)$r[$k]) . "'";
            }
        }
        $children = array();
        foreach (_pg_sw_list_tree_fields($page_id, $arg, false) as $field) {
            // A value holds one name, or several joined by ', '.
            $like = array();
            foreach ($names as $n) $like[] = "FIND_IN_SET($n, REPLACE(IFNULL(fd.data, ''), ' ', '')) > 0";
            if (!$like) continue;
            $children = array_merge($children, _pg_sw_list_tree_records($field['page_id'], $field['id'], implode(' OR ', $like)));
        }
        $cache[$ck] = pg_sw_list_tree_children_of($records, $children);
    }
    return isset($cache[$ck][$key]) ? $cache[$ck][$key] : array();
}

// The records above record $key of form $page_id through field $arg, root
// first: its value names a record of the form the field points into, whose
// own field of that name names the next, at most
// pg_sw_list_tree_max_depth() levels up.
function _pg_sw_list_tree_path($page_id, $key, $arg)
{
    $steps   = array();
    $form    = (int)$page_id;
    $current = (int)$key;
    for ($level = 0; $level < pg_sw_list_tree_max_depth(); $level++) {
        $fields = _pg_sw_list_tree_fields($form, $arg, true);
        if (!$fields) break;
        $field = $fields[0];
        $value = db_value("SELECT data FROM form_data WHERE form_id = '$current' AND form_field_id = '" . (int)$field['id'] . "' LIMIT 1");
        $codes = pg_coll_ref_codes((string)$value);
        if (!$codes) break;
        $name = "'" . e($codes[0]) . "'";
        $found = _pg_sw_list_tree_records($field['reference_page_id'], 0,
            "CAST(forms.reference_code AS BINARY) = CAST($name AS BINARY) OR CAST(forms.address_name AS BINARY) = CAST($name AS BINARY)");
        if (!$found) break;
        $steps[]  = $found[0];
        $form     = (int)$field['reference_page_id'];
        $current  = (int)$found[0]['id'];
    }
    return pg_sw_list_tree_path_rows($steps, $key);
}
