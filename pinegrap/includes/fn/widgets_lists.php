<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

// A module of functions.php: lists inside a record of a system widget.
//
// Loaded by functions.php through require_once, never on its own.
//
// A widget has one main loop, its first loop_area without props.source:
// the renderer repeats it once per record. A loop_area WITH props.source is
// a list: rows the record holds (the values of a check box group, the parts
// of a comma-separated text, the record's attachments), drawn inside the
// record. It is drawn in two halves:
//
//   1. While the widget renders, _render_tree_node() keeps the list's row
//      template aside (pg_sw_list_span()) and writes a span of carriers for
//      the values the template needs from its record, the key first:
//
//        <!--pg-list:NONCE:N--><!--pg-lv:NONCE:N:0-->^^submitted_form_id^^<!--/pg-lv:NONCE:N:0-->...<!--/pg-list:NONCE:N-->
//
//      The renderer fills the carriers in every copy of the record with its
//      own fill, as it fills a condition's carrier (widgets_finish.php), and
//      publishes the rows it read for the records it showed
//      (pg_sw_list_publish()).
//   2. The finish pass (pg_sw_lists_expand()) draws every span from the
//      published rows: no query is added, and what a list shows is what the
//      renderer already decided the visitor may see.
//
// Only comments and token text stand in a span, so it sits where the
// loop_area sat, and outside a widget render nothing is drawn.
if (!defined('PG_FUNCTIONS_DIR')) {
    exit;
}

// The list sources: source => array(
//   'family'       => the data a renderer publishes for it,
//   'key'          => the token whose value names the record a span lists,
//   'label'        => the source's name in the editor,
//   'arg'          => what props.sourceArg names ('' when it takes none),
//   'arg_required' => whether a list without it draws nothing,
//   'tokens'       => the row tokens, each a pair of the token's name and
//                     its label (tools/check_bindings.php reads the pairs).
// ) Every provider row carries exactly these tokens as keys.
function pg_sw_list_sources()
{
    return array(
        'form_record.values' => array(
            'family'       => 'form_record',
            'key'          => 'submitted_form_id',
            'label'        => lang('Values of a field'),
            'arg'          => 'field',
            'arg_required' => true,
            'tokens'       => array(
                array('__value_text', lang('Value')),
                array('__value_param', lang('Value for an address')),
            ),
        ),
        'form_record.split' => array(
            'family'       => 'form_record',
            'key'          => 'submitted_form_id',
            'label'        => lang('Parts of a field, split at commas'),
            'arg'          => 'field',
            'arg_required' => true,
            'tokens'       => array(
                array('__value_text', lang('Value')),
                array('__value_param', lang('Value for an address')),
            ),
        ),
        'form_record.files' => array(
            'family'       => 'form_record',
            'key'          => 'submitted_form_id',
            'label'        => lang('Attachments'),
            'arg'          => 'upload_field',
            'arg_required' => false,
            'tokens'       => array(
                array('__file_name', lang('File Name')),
                array('__file_url', lang('File address')),
                array('__file_field', lang('Field')),
                array('__file_ext', lang('File type')),
            ),
        ),
    ) + pg_sw_list_product_sources() + pg_sw_list_crumb_sources() + pg_sw_list_calendar_sources() + pg_sw_list_order_sources()
        + pg_sw_list_cart_sources() + pg_sw_list_menu_sources() + pg_sw_list_security_sources()
        + pg_sw_list_paging_sources();
}

// The sources a widget kind offers (source => descriptor): the form record
// sources in the two form views, the product sources in the catalog detail
// view and, but for the products bought together (a cross-sell lookup per
// card), in the catalog listing, the crumbs of the catalog path in both
// (widgets_lists_product.php), the calendar
// grid sources in the calendar view (widgets_lists_calendar.php), the order
// sources in the order view and the member's orders in the my account
// widget (widgets_lists_order.php), the visitor's cart in the cart link and
// the cart widget (widgets_lists_cart.php), the sub-items of a menu item in
// the menu widget (widgets_lists_menu.php), the member's passkeys in the
// account security widget (widgets_account.php), and in the form list the
// page links too, as in the catalog listing and the search results
// (widgets_lists_paging.php), each kind publishing its
// data; none in any other kind.
function pg_sw_kind_lists($kind)
{
    $families = array('form_list_view' => array('form_record', 'paging'), 'form_item_view' => 'form_record',
                      'catalog_item_view' => array('product', 'crumbs'), 'catalog_listing' => array('product', 'crumbs', 'paging'), 'calendar_view' => 'calendar',
                      'order_view' => 'order', 'my_account' => 'account', 'cart_link' => 'cart', 'shopping_cart' => 'cart',
                      'menu_region' => 'menu', 'account_security' => 'security', 'search_results' => 'paging');
    $except   = array('catalog_listing' => array('product.related' => true));
    $kind = pg_sw_finish_kind($kind);
    if (!isset($families[$kind])) return array();
    $out = array();
    foreach (pg_sw_list_sources() as $source => $descriptor) {
        if (in_array($descriptor['family'], (array)$families[$kind], true) && !isset($except[$kind][$source])) $out[$source] = $descriptor;
    }
    return $out;
}

// The record value that tells a $family span its record is not one of the
// family's: array(token text, value), or null. A catalog listing draws
// product and group cards from one template, both with an ^^__id^^; a group
// card's ^^__row_type^^ is 'group', so a product list there reads no rows.
function pg_sw_list_key_guard($family)
{
    return ($family === 'product') ? array('^^__row_type^^', 'group') : null;
}

// Whether a tree node is a list: a loop_area whose props.source is a
// non-empty string. An unknown source is still a list (it draws nothing),
// never the main loop.
function pg_sw_list_is_sourced($node)
{
    return is_array($node) && isset($node['type']) && $node['type'] === 'loop_area'
        && isset($node['props']) && is_array($node['props'])
        && isset($node['props']['source']) && is_string($node['props']['source']) && $node['props']['source'] !== '';
}

// $nodes with the children of every list emptied, at any depth: what the
// rows of the main loop hold of their own.
function pg_sw_list_unsourced($nodes)
{
    if (!is_array($nodes)) return $nodes;
    foreach ($nodes as $i => $node) {
        if (!is_array($node)) continue;
        if (pg_sw_list_is_sourced($node)) {
            $nodes[$i]['children'] = array();
        } elseif (isset($node['children']) && is_array($node['children'])) {
            $nodes[$i]['children'] = pg_sw_list_unsourced($node['children']);
        }
    }
    return $nodes;
}

// A list's settings from its props: null when it has no source (not a
// list), false when they are invalid (the list draws nothing, as a
// condition that cannot be read), else array('source', 'arg' (sanitised as
// a binding is), 'limit' (0: every row; at most 100), 'order' ('' stored
// order, 'reverse', 'az', 'za')).
function pg_sw_list_spec($props)
{
    if (!is_array($props) || !isset($props['source']) || !is_string($props['source']) || $props['source'] === '') return null;
    $source  = $props['source'];
    $sources = pg_sw_list_sources();
    if (!preg_match('/^[a-z_]+\.[a-z_]+$/', $source) || !isset($sources[$source])) return false;

    $arg = (isset($props['sourceArg']) && is_scalar($props['sourceArg']) && !is_bool($props['sourceArg']))
        ? preg_replace('/[^a-z0-9_\-]/i', '', (string)$props['sourceArg']) : '';
    if ($arg === '' && $sources[$source]['arg_required']) return false;

    $limit = isset($props['limit']) ? $props['limit'] : 0;
    if ($limit === '' || $limit === null) {
        $limit = 0;
    } elseif (is_int($limit)) {
        // as written
    } elseif (is_float($limit) && is_finite($limit) && floor($limit) === $limit) {
        $limit = (int)$limit;
    } elseif (is_string($limit) && preg_match('/^\d{1,3}$/', $limit)) {
        $limit = (int)$limit;
    } else {
        return false;
    }
    if ($limit < 0 || $limit > 100) return false;

    $order = isset($props['order']) ? $props['order'] : '';
    if (!is_string($order) || !in_array($order, array('', 'reverse', 'az', 'za'), true)) return false;

    return array('source' => $source, 'arg' => $arg, 'limit' => $limit, 'order' => $order);
}

// The row templates of the lists drawn in this request: N => array('sid'
// (the widget), 'n', 'spec', 'html' (the template), 'parents' (the token
// texts its carriers hold, the key first, then the key guard's token),
// 'guard' (the guard's value, pg_sw_list_key_guard())). Kept for the request, so a
// widget drawn twice finds its templates.
function &_pg_sw_list_templates()
{
    static $templates = array();
    return $templates;
}

// What renderers published for their lists: sid => family => data, until
// the widget's finish pass takes it.
function &_pg_sw_list_published()
{
    static $published = array();
    return $published;
}

// The names of the six row position tokens.
function _pg_sw_list_position_names()
{
    return array_keys(pg_sw_finish_position_values(0, 1));
}

// The render half: the span a list draws where its loop_area stands, its
// row template rendered and kept aside. '' outside a widget render (no
// pass would expand it), for invalid settings, and for a source the
// widget's kind does not offer. Called by _render_tree_node().
function pg_sw_list_span($node, $indent = 0, $depth = 0)
{
    static $seq = 0;
    $scope = pg_sw_finish_scope();
    if ($scope === null || !is_array($node)) return '';
    $spec = pg_sw_list_spec(isset($node['props']) ? $node['props'] : null);
    if (!is_array($spec)) return '';
    $offered = pg_sw_kind_lists($scope['kind']);
    if (!isset($offered[$spec['source']])) return '';
    $descriptor = $offered[$spec['source']];

    // The row template, drawn inside the scope: a node with a condition is
    // wrapped, and a list in it writes its own span.
    $template = '';
    $children = (isset($node['children']) && is_array($node['children'])) ? $node['children'] : array();
    foreach ($children as $child) {
        $template .= _render_tree_node($child, $indent, $depth);
    }
    if (trim($template) === '') return '';

    // The row tokens and the position tokens are the list's own. A row
    // token keeps its format suffix, read when the row is filled
    // (pg_sw_lists_fill_row()); a position token's is dropped. Every other
    // token reads its record's value through a carrier, its suffix kept so
    // the renderer formats it (a date field's %%d.m.Y%%).
    $own = array_flip(_pg_sw_list_position_names());
    $row = array();
    foreach ($descriptor['tokens'] as $pair) {
        $own[$pair[0]] = true;
        $row[$pair[0]] = true;
    }
    $key     = '^^' . $descriptor['key'] . '^^';
    $parents = array($key);
    $seen    = array($key => true);
    // The guard's value is read through the second carrier.
    $guard = pg_sw_list_key_guard($descriptor['family']);
    if ($guard !== null) {
        $parents[] = $guard[0];
        $seen[$guard[0]] = true;
    }
    $out = preg_replace_callback('/\^\^([^\^]+?)\^\^(%%.*?%%)?/', function ($m) use ($own, $row, &$parents, &$seen) {
        $name = strtolower($m[1]);
        if (isset($own[$name])) return '^^' . $name . '^^' . ((isset($row[$name]) && !empty($m[2])) ? $m[2] : '');
        if (!isset($seen[$m[0]])) {
            $seen[$m[0]] = true;
            $parents[]   = $m[0];
        }
        return $m[0];
    }, $template);
    if ($out === null) return '';

    // The links whose address takes a value of this list's row
    // (pg_sw_page_link_bindings()).
    $links = array();
    if (isset($node['props']['_pgRowLinks']) && is_array($node['props']['_pgRowLinks'])) {
        foreach ($node['props']['_pgRowLinks'] as $token) {
            if (is_string($token) && preg_match('/^__page_link_\d+$/', $token)) $links[] = '^^' . $token . '^^';
        }
    }

    $n = ++$seq;
    $templates = &_pg_sw_list_templates();
    $templates[$n] = array(
        'sid'     => (int)$scope['sid'],
        'n'       => $n,
        'spec'    => $spec,
        'html'    => $out,
        'parents' => $parents,
        'guard'   => ($guard !== null) ? $guard[1] : null,
        'links'   => $links,
    );

    $nonce = pg_sw_marker_nonce();
    $html  = '<!--pg-list:' . $nonce . ':' . $n . '-->';
    foreach ($parents as $i => $text) {
        $html .= '<!--pg-lv:' . $nonce . ':' . $n . ':' . $i . '-->' . $text . '<!--/pg-lv:' . $nonce . ':' . $n . ':' . $i . '-->';
    }
    return $html . '<!--/pg-list:' . $nonce . ':' . $n . '-->';
}

// The sources of $family the open widget drew a list of (source => true):
// empty outside a scope. A renderer that reads its rows itself asks first,
// so a tree without lists reads nothing.
function pg_sw_list_wanted($family)
{
    $scope = pg_sw_finish_scope();
    if ($scope === null) return array();
    $sid     = (int)$scope['sid'];
    $sources = pg_sw_list_sources();
    $out     = array();
    foreach (_pg_sw_list_templates() as $template) {
        $source = $template['spec']['source'];
        if ($template['sid'] === $sid && isset($sources[$source]) && $sources[$source]['family'] === $family) {
            $out[$source] = true;
        }
    }
    return $out;
}

// The publish half: a renderer files the rows it read for the records it
// shows, under the open widget's scope, replacing what the widget held for
// $family. Nothing is kept outside a scope or when the widget drew no list
// of that family, so a tree without lists pays nothing.
// 'form_record': $data_rows as the form views read form_data (data_id and
// field_office_use included); $options: 'translate' (the widget includes
// its records in the translation).
// 'product': $data_rows = source => product id => rows, as
// pg_sw_list_product_read() builds them.
// 'calendar': $data_rows = source => key => rows, as
// pg_sw_list_calendar_rows() builds them; 'order' and 'account' as
// pg_sw_list_order_rows() and pg_sw_list_account_rows() build them,
// 'security' as pg_sw_list_passkey_rows() does, 'paging' as
// pg_sw_list_paging_rows() does. Every
// family but form_record publishes its rows so.
function pg_sw_list_publish($family, $data_rows, $options = array())
{
    $scope = pg_sw_finish_scope();
    if ($scope === null || !pg_sw_list_wanted($family)) return;
    $sid = (int)$scope['sid'];

    $published = &_pg_sw_list_published();
    if ($family === 'form_record') {
        $published[$sid][$family] = array(
            'records' => pg_sw_list_form_records($data_rows),
            'options' => array('translate' => is_array($options) && !empty($options['translate'])),
        );
    } else {
        $published[$sid][$family] = array('rows' => is_array($data_rows) ? $data_rows : array());
    }
}

// What was published for the widget of $scope, forgotten here: the finish
// pass takes it first, so nothing outlives its pass.
function pg_sw_list_take($scope)
{
    $sid = (is_array($scope) && isset($scope['sid'])) ? (int)$scope['sid'] : 0;
    $published = &_pg_sw_list_published();
    if (!isset($published[$sid])) return array();
    $out = $published[$sid];
    unset($published[$sid]);
    return $out;
}

// A form's data rows as the lists read them: form id => rows, each
// array('id' => form_data.id, 'name' => the field name in lower case,
// 'bare' => that name with only [a-z0-9_] left, 'label', 'type', 'data',
// 'file' => the uploaded file's name or '', 'office' => 1 for a field kept
// for office use, 'wysiwyg' => 1 for a rich text area, 'translate' => as
// pg_sw_index_form_data() reads it), sorted by data id (stored order). A row
// without a field name is left out.
function pg_sw_list_form_records($data_rows)
{
    $out = array();
    if (!is_array($data_rows)) return $out;
    foreach ($data_rows as $i => $row) {
        if (!is_array($row) || !isset($row['field_name']) || trim((string)$row['field_name']) === '') continue;
        $name = mb_strtolower(trim((string)$row['field_name']), 'UTF-8');
        $type = isset($row['field_type']) ? (string)$row['field_type'] : '';
        $out[isset($row['form_id']) ? (int)$row['form_id'] : 0][] = array(
            'id'        => isset($row['data_id']) ? (int)$row['data_id'] : 0,
            'name'      => $name,
            'bare'      => preg_replace('/[^a-z0-9_]/', '', $name),
            'label'     => isset($row['field_label']) ? (string)$row['field_label'] : '',
            'type'      => $type,
            'data'      => isset($row['data']) ? (string)$row['data'] : '',
            'file'      => isset($row['file_name']) ? (string)$row['file_name'] : '',
            'office'    => !empty($row['field_office_use']) ? 1 : 0,
            'wysiwyg'   => (!empty($row['field_wysiwyg']) && $type === 'text area') ? 1 : 0,
            'translate' => (isset($row['field_translate']) && (string)$row['field_translate'] === '0') ? 0 : 1,
            '_at'       => $i,
        );
    }
    ksort($out);
    foreach ($out as $form_id => $rows) {
        usort($rows, function ($a, $b) {
            if ($a['id'] !== $b['id']) return ($a['id'] < $b['id']) ? -1 : 1;
            return ($a['_at'] < $b['_at']) ? -1 : (($a['_at'] > $b['_at']) ? 1 : 0);
        });
        foreach ($rows as $j => $row) unset($rows[$j]['_at']);
        $out[$form_id] = $rows;
    }
    return $out;
}

// Whether a record's row is the field $arg names: its name, its name
// without hyphens, or its label, in any case. get_file.php reads an
// attachments list's field the same way (includes/record_files.php).
function _pg_sw_list_names_field($row, $arg)
{
    $arg = mb_strtolower((string)$arg, 'UTF-8');
    if ($arg === '') return false;
    return $row['name'] === $arg || $row['bare'] === $arg
        || mb_strtolower(trim($row['label']), 'UTF-8') === $arg;
}

// $s without white space at either end, the no-break space included.
function _pg_sw_list_trim($s)
{
    $out = preg_replace('/^[\s\x{00A0}]+|[\s\x{00A0}]+$/u', '', (string)$s);
    return ($out === null) ? trim((string)$s) : $out;
}

// $s cut at commas, each part trimmed, empty parts left out.
function _pg_sw_list_split($s)
{
    $parts = array();
    foreach (explode(',', (string)$s) as $part) {
        $part = _pg_sw_list_trim($part);
        if ($part !== '') $parts[] = $part;
    }
    return $parts;
}

// The rows of a values list ($split false: one per stored value; a check
// box group or a multi-select stores one per ticked value) or a split list
// ($split true: the value's text cut at commas) over the field $arg of one
// record's rows (pg_sw_list_form_records()). The text is what the field's
// token prints, as plain text: translated when $options['translate'] (text
// boxes and text areas), in the field's display shape (dates). The address
// value is the stored text, never a translation: a filter in an address
// compares stored values. A rich text area and a file upload give none (an
// attachments list lists files); neither does an empty value. Raw values:
// the pass escapes them once.
function pg_sw_list_value_rows($records, $arg, $options = array(), $split = false)
{
    $out = array();
    if (!is_array($records)) return $out;
    foreach ($records as $row) {
        if (!is_array($row) || !_pg_sw_list_names_field($row, $arg)) continue;
        if ($row['wysiwyg'] || $row['type'] === 'file upload') continue;
        $stored = $row['data'];
        if (_pg_sw_list_trim($stored) === '') continue;

        $data = $stored;
        if (is_array($options) && !empty($options['translate'])) {
            $tr   = pg_tr_record_fields(array('v' => array('data' => $data, 'type' => $row['type'], 'wysiwyg' => $row['wysiwyg'], 'translate' => $row['translate'])));
            $data = (is_array($tr) && isset($tr['v']['data'])) ? (string)$tr['v']['data'] : $data;
        }
        $text = (string)prepare_form_data_for_output($data, $row['type'], false, pg_sw_default_date_format($row['type']));

        if (!$split) {
            if (_pg_sw_list_trim($text) === '') continue;
            $out[] = array(
                '__value_text'  => $text,
                '__value_param' => rawurlencode($stored),
            );
            continue;
        }

        // A translation cut into another number of parts cannot be paired
        // with the stored parts: the stored parts are shown then, so every
        // part keeps its address value.
        $stored_parts = _pg_sw_list_split($stored);
        $text_parts   = _pg_sw_list_split($text);
        if (count($text_parts) !== count($stored_parts)) $text_parts = $stored_parts;
        foreach ($stored_parts as $i => $part) {
            $out[] = array(
                '__value_text'  => $text_parts[$i],
                '__value_param' => rawurlencode($part),
            );
        }
    }
    return $out;
}

// The rows of an attachments list over one record's rows: every file
// upload field that holds a file, in stored order; with no $arg those not
// kept for office use, with an $arg that field alone. The address is what
// the field's token prints (pg_sw_index_form_data()); the type is read from
// the file's name, never from files.type (four characters wide).
function pg_sw_list_file_rows($records, $arg)
{
    $out = array();
    if (!is_array($records)) return $out;
    $arg = (string)$arg;
    foreach ($records as $row) {
        if (!is_array($row) || $row['type'] !== 'file upload' || $row['file'] === '') continue;
        if ($arg === '' ? $row['office'] : !_pg_sw_list_names_field($row, $arg)) continue;
        $dot = strrpos($row['file'], '.');
        $out[] = array(
            '__file_name'  => $row['file'],
            '__file_url'   => (defined('OUTPUT_PATH') ? OUTPUT_PATH : '/') . $row['file'],
            '__file_field' => ($row['label'] !== '') ? $row['label'] : $row['name'],
            '__file_ext'   => ($dot === false) ? '' : strtolower(substr($row['file'], $dot + 1)),
        );
    }
    return $out;
}

// The raw rows of $source for every record and arg a level of the pass
// asks for: $wanted = key => arg => true; returns key => arg => rows. One
// call per source per level, so a provider that reads the database later
// asks once for all of them. The form record providers read what the
// renderer published; a list of any other family its rows as published,
// whatever the arg.
function pg_sw_list_source_rows($source, $wanted, $published)
{
    $sources = pg_sw_list_sources();
    if (!isset($sources[$source]) || !is_array($wanted)) return array();
    $family = $sources[$source]['family'];
    $data   = (is_array($published) && isset($published[$family]) && is_array($published[$family])) ? $published[$family] : null;
    if ($data === null) return array();

    $out = array();
    if ($family !== 'form_record') {
        $rows = (isset($data['rows'][$source]) && is_array($data['rows'][$source])) ? $data['rows'][$source] : array();
        foreach ($wanted as $key => $args) {
            foreach (array_keys((array)$args) as $arg) {
                $out[$key][$arg] = (isset($rows[(int)$key]) && is_array($rows[(int)$key])) ? $rows[(int)$key] : array();
            }
        }
    } else {
        $records = (isset($data['records']) && is_array($data['records'])) ? $data['records'] : array();
        $options = (isset($data['options']) && is_array($data['options'])) ? $data['options'] : array();
        foreach ($wanted as $key => $args) {
            $rows = isset($records[(int)$key]) ? $records[(int)$key] : array();
            foreach (array_keys((array)$args) as $arg) {
                $out[$key][$arg] = ($source === 'form_record.files')
                    ? pg_sw_list_file_rows($rows, (string)$arg)
                    : pg_sw_list_value_rows($rows, (string)$arg, $options, $source === 'form_record.split');
            }
        }
    }
    return $out;
}

// $rows in the list's order, then cut to $limit (0: all). 'az' and 'za'
// compare the first token's value folded as a condition folds it
// (pg_sw_cond_fold()), in natural order; equal values keep their stored
// order either way.
function pg_sw_list_order($rows, $order, $limit)
{
    $rows = is_array($rows) ? array_values($rows) : array();
    if ($order === 'reverse') {
        $rows = array_reverse($rows);
    } elseif ($order === 'az' || $order === 'za') {
        $keyed = array();
        foreach ($rows as $i => $row) {
            $first   = is_array($row) ? reset($row) : '';
            $keyed[] = array(pg_sw_cond_fold((string)$first), $i, $row);
        }
        $sign = ($order === 'za') ? -1 : 1;
        usort($keyed, function ($a, $b) use ($sign) {
            $c = strnatcmp($a[0], $b[0]);
            if ($c !== 0) return $sign * $c;
            return ($a[1] < $b[1]) ? -1 : (($a[1] > $b[1]) ? 1 : 0);
        });
        $rows = array();
        foreach ($keyed as $k) $rows[] = $k[2];
    }
    $limit = (int)$limit;
    return ($limit > 0) ? array_slice($rows, 0, $limit) : $rows;
}

// The list spans of $nonce in $html, in the order they stand: array(at,
// body start, body end, end, n) each. Spans never nest in the output (a
// list in a list's row is in that list's template until the row is drawn),
// so a span is its open marker and the next close marker of its number.
function _pg_sw_lists_find($html, $nonce)
{
    $open  = '<!--pg-list:' . $nonce . ':';
    $spans = array();
    $pos   = 0;
    while (($at = strpos($html, $open, $pos)) !== false) {
        $head_end = strpos($html, '-->', $at);
        if ($head_end === false) break;
        $n = substr($html, $at + strlen($open), $head_end - $at - strlen($open));
        if (!preg_match('/^\d+$/', $n)) {
            $pos = $head_end + 3;
            continue;
        }
        $close = '<!--/pg-list:' . $nonce . ':' . $n . '-->';
        $c     = strpos($html, $close, $head_end + 3);
        if ($c === false) {
            $pos = $head_end + 3;
            continue;
        }
        $spans[] = array($at, $head_end + 3, $c, $c + strlen($close), (int)$n);
        $pos     = $c + strlen($close);
    }
    return $spans;
}

// The values of a span's $count carriers, as the renderer filled them; an
// identifier it neither filled nor swept reads as empty, a missing carrier
// too.
function _pg_sw_lists_carriers($body, $nonce, $n, $count)
{
    $values = array();
    for ($i = 0; $i < $count; $i++) {
        $open  = '<!--pg-lv:' . $nonce . ':' . $n . ':' . $i . '-->';
        $close = '<!--/pg-lv:' . $nonce . ':' . $n . ':' . $i . '-->';
        $a = strpos($body, $open);
        $b = ($a === false) ? false : strpos($body, $close, $a + strlen($open));
        $values[$i] = ($b === false) ? '' : (string)pg_sw_sweep_tokens(substr($body, $a + strlen($open), $b - $a - strlen($open)));
    }
    return $values;
}

// $html without the spans, carriers and markers of $nonce still standing.
function _pg_sw_lists_strip($html, $nonce)
{
    if (strpos($html, 'pg-l') === false) return $html;
    $out = '';
    $pos = 0;
    foreach (_pg_sw_lists_find($html, $nonce) as $span) {
        $out .= substr($html, $pos, $span[0] - $pos);
        $pos  = $span[3];
    }
    $html = $out . substr($html, $pos);

    $open = '<!--pg-lv:' . $nonce . ':';
    $out  = '';
    $pos  = 0;
    while (($at = strpos($html, $open, $pos)) !== false) {
        $head_end = strpos($html, '-->', $at);
        if ($head_end === false) break;
        $head  = substr($html, $at + strlen($open), $head_end - $at - strlen($open));
        $close = '<!--/pg-lv:' . $nonce . ':' . $head . '-->';
        $c     = preg_match('/^\d+:\d+$/', $head) ? strpos($html, $close, $head_end + 3) : false;
        $out  .= substr($html, $pos, $at - $pos);
        $pos   = ($c === false) ? $head_end + 3 : $c + strlen($close);
    }
    $html = $out . substr($html, $pos);

    $left = preg_replace('/<!--\/?pg-(?:list|lv):' . preg_quote($nonce, '/') . ':[^>]*?-->/', '', $html);
    return ($left === null) ? $html : $left;
}

// Fill a list row from $map (token text => value, as strtr() takes it):
// one strtr() as before when no format follows a token. Otherwise one pass
// that never rescans what it wrote: a text $map holds whole (a carrier's
// token with its suffix) takes its value as it is, a row token with a
// suffix takes its value formatted (pg_sw_fill_value()), any other token
// stays for the sweep.
function pg_sw_lists_fill_row($html, $map)
{
    if (strpos($html, '^^%%') === false) return strtr($html, $map);
    $out = preg_replace_callback('/\^\^([^\^]+?)\^\^(?:%%(.*?)%%)?/', function ($m) use ($map) {
        if (isset($map[$m[0]])) return $map[$m[0]];
        $bare = '^^' . $m[1] . '^^';
        if (!isset($map[$bare])) return $m[0];
        if (!isset($m[2])) return $map[$bare];
        return pg_sw_fill_value($map[$bare], unhtmlspecialchars($m[2]));
    }, $html);
    return ($out === null) ? strtr($html, $map) : $out;
}

// The pass half (stage 2 of pg_sw_finish_widget_html()): every list span
// of $nonce drawn from $published (pg_sw_list_take()), level by level, at
// most four levels of lists in lists. Each row is the template filled in
// one pass (pg_sw_lists_fill_row()): the row's values escaped once, its position among the
// list's rows (after order and limit), and each carrier's value (escaped by
// the renderer) for its token text, the longest text first. Row ids are
// made the row's own, then the span's (a list drawn for several records).
// A span with no rows leaves a cut point and sets $cut, so the
// pg-hide-if-empty rerun drops a box the list alone filled. A span no
// template of this widget answers, one left after the last level, and any
// stray marker of $nonce go with their carriers; another nonce's markers
// are text.
function pg_sw_lists_expand($html, $nonce, $scope, $published, &$cut)
{
    if (!is_string($html) || $html === '') return $html;
    $open = '<!--pg-list:' . $nonce . ':';
    $sid  = (is_array($scope) && isset($scope['sid'])) ? (int)$scope['sid'] : 0;
    $templates = &_pg_sw_list_templates();

    for ($level = 0; $level < 4 && strpos($html, $open) !== false; $level++) {
        $spans = _pg_sw_lists_find($html, $nonce);
        if (!$spans) break;

        // Each span's template, carriers and record; the rows asked for.
        $wanted = array();
        foreach ($spans as $k => $span) {
            $t = isset($templates[$span[4]]) ? $templates[$span[4]] : null;
            if ($t === null || $t['sid'] !== $sid) {
                $spans[$k][5] = null;
                continue;
            }
            $values = _pg_sw_lists_carriers(substr($html, $span[1], $span[2] - $span[1]), $nonce, $span[4], count($t['parents']));
            $key    = trim(html_entity_decode(strip_tags($values[0]), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
            if (!preg_match('/^\d{1,18}$/', $key)) $key = '';
            if ($t['guard'] !== null && isset($values[1])
                && trim(html_entity_decode(strip_tags($values[1]), ENT_QUOTES | ENT_HTML5, 'UTF-8')) === $t['guard']) {
                $key = '';
            }
            $spans[$k][5] = $t;
            $spans[$k][6] = $values;
            $spans[$k][7] = $key;
            if ($key !== '') $wanted[$t['spec']['source']][$key][$t['spec']['arg']] = true;
        }
        $rows = array();
        foreach ($wanted as $source => $keys) {
            $rows[$source] = pg_sw_list_source_rows($source, $keys, $published);
        }

        $out    = '';
        $pos    = 0;
        $copies = array();
        foreach ($spans as $span) {
            $out .= substr($html, $pos, $span[0] - $pos);
            $pos  = $span[3];
            $t    = $span[5];
            if ($t === null) continue;
            $copy = isset($copies[$t['n']]) ? $copies[$t['n']] : 0;
            $copies[$t['n']] = $copy + 1;

            $spec = $t['spec'];
            $key  = $span[7];
            $list = ($key !== '' && isset($rows[$spec['source']][$key][$spec['arg']])) ? $rows[$spec['source']][$key][$spec['arg']] : array();
            $list = pg_sw_list_order($list, $spec['order'], $spec['limit']);
            if (!$list) {
                $cut  = true;
                $out .= pg_sw_finish_cut_mark($nonce);
                continue;
            }

            $count = count($list);
            foreach ($list as $i => $row) {
                $map = array();
                foreach (pg_sw_escape_token_values($row) + pg_sw_finish_position_values($i, $count) as $name => $value) {
                    $map['^^' . $name . '^^'] = (string)$value;
                }
                foreach ($t['parents'] as $p => $text) {
                    if (!isset($map[$text])) $map[$text] = $span[6][$p];
                }
                // A link with a value of this row: its address, from the
                // placeholder its record's carrier holds.
                foreach ($t['links'] as $text) {
                    if (!isset($map[$text])) continue;
                    $url = pg_sw_page_link_row_value($map[$text], $nonce, $row);
                    if ($url !== '') $map[$text] = $url;
                }
                $filled = pg_sw_sweep_tokens(pg_sw_lists_fill_row($t['html'], $map));
                $out   .= pg_sw_uniquify_row_ids(pg_sw_uniquify_row_ids($filled, $t['n'], $i + 1), $sid, $copy + 1);
            }
        }
        $html = $out . substr($html, $pos);
    }

    return _pg_sw_lists_strip($html, $nonce);
}
