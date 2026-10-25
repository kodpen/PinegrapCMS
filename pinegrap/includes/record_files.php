<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Whether a page built in the visual editor shows a file uploaded with a
 * submitted form to the visitor asking for it.
 *
 * get_file.php serves such a file (files.attachment = 1) to a visitor who may
 * edit its folder or the form's records, and to a visitor whom a classic
 * 'form list view' page or form item view layout shows it to. A designed page
 * shows a record through a form_item_view or form_list_view widget instead,
 * so every picture uploaded through a designed form stayed closed to the
 * readers of the page that printed it: a visitor was sent to sign in, a member
 * got 403, og:image and the RSS enclosure named a closed file.
 * pg_record_file_designed_access() gives get_file.php the missing answer.
 *
 * Loaded by get_file.php alone, on the path where it would otherwise refuse
 * the file; tests/record_files_test.php loads it for the tests. get_file.php
 * never loads functions.php (router.php dispatches it that way on purpose),
 * so the widget helpers of includes/fn/widgets.php are not there. What the
 * answer needs from them is carried here, and the test file holds each copy
 * to its original:
 *
 *   pg_record_file_widget_public()    pg_sw_form_widget_public()
 *   pg_record_file_sql_operation()    prepare_sql_operation()
 *   pg_record_file_filter_sql()       pg_sw_form_list_filter_sql() and
 *                                     pg_sw_filter_sql_closed(), for the
 *                                     filters it can build (see there)
 *   pg_record_file_tree_shared_ids()  pg_sw_page_widgets()' walk
 *   pg_record_file_loop_children()    _split_widget_tree()'s loop_area search
 *
 * CONTRACT - the functions in this file may only call each other, escape()
 * (both includers define it), check_view_access() and pg_auth_column_exists()
 * of includes/authentication.php, get_file.php's initialize_timezone() behind
 * function_exists(), and PHP itself. Queries go through
 * pg_record_file_rows(), which answers a failed query with no rows, so a
 * database without the visual editor's tables keeps the file closed instead
 * of ending the request.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_FUNCTIONS_DIR') && !defined('PG_GET_FILE')) {
    exit;
}

/**
 * The widget types that show a form's records, as their renderer knows them
 * (_expand_system_widgets()): the record view, and the list under its current
 * and older names.
 *
 * @return array regionType => 'form_item_view' | 'form_list_view'
 */
function pg_record_file_widget_types()
{
    return array(
        'form_item_view'       => 'form_item_view',
        'form_list_view'       => 'form_list_view',
        'submitted_forms_list' => 'form_list_view',
        'form_list'            => 'form_list_view',
        'blog_list'            => 'form_list_view',
    );
}

/**
 * Whether a form widget shows the same records to every visitor: its access
 * setting is public and, for a list, the viewer filter is off. The rule of
 * pg_sw_form_widget_public().
 *
 * @param mixed $cfg the widget's system_region_config
 * @return bool
 */
function pg_record_file_widget_public($cfg)
{
    if (!is_array($cfg)) return false;
    $access = isset($cfg['access_control']) ? (string)$cfg['access_control'] : 'public';
    if ($access !== '' && $access !== 'public') return false;
    if (isset($cfg['regionType']) && $cfg['regionType'] === 'form_item_view') return true;
    return empty($cfg['viewer_filter']);
}

/**
 * Whether a widget's config names the custom form $form_page_id, read the way
 * pg_sw_form_widget_form_page_id() reads it: custom_form_page_id, the older
 * form_page_id, or the source_page name (page names compare without case, as
 * the page table does).
 *
 * @param mixed  $cfg
 * @param int    $form_page_id
 * @param string $form_page_name
 * @return bool
 */
function pg_record_file_widget_form_matches($cfg, $form_page_id, $form_page_name)
{
    $form_page_id = (int)$form_page_id;
    if (!is_array($cfg) || $form_page_id <= 0) return false;
    if (!empty($cfg['custom_form_page_id'])) return (int)$cfg['custom_form_page_id'] === $form_page_id;
    if (!empty($cfg['form_page_id'])) return (int)$cfg['form_page_id'] === $form_page_id;
    if (!empty($cfg['source_page']) && is_scalar($cfg['source_page'])) {
        $name = (string)$form_page_name;
        return $name !== '' && mb_strtolower((string)$cfg['source_page'], 'UTF-8') === mb_strtolower($name, 'UTF-8');
    }
    return false;
}

/**
 * The identifiers a widget may name the attachment's field by, lower-cased:
 * the field name, the name without the characters a binding drops, and the
 * label. pg_sw_index_form_data() answers the same identifiers (and the
 * slugified label, which is left out here: a token written that way keeps
 * the file closed).
 *
 * @param string $name  form_fields.name
 * @param string $label form_fields.label
 * @return array identifier => true
 */
function pg_record_file_field_identifiers($name, $label = '')
{
    $out = array();
    $name = (string)$name;
    $candidates = array($name, preg_replace('/[^a-z0-9_]/i', '', $name), (string)$label);
    foreach ($candidates as $candidate) {
        $key = mb_strtolower(trim((string)$candidate), 'UTF-8');
        if ($key !== '') $out[$key] = true;
    }
    return $out;
}

/**
 * Whether a widget's tree prints the field: a text, html, src, alt, href or
 * value binding naming it (_apply_bindings() turns each into its token), or
 * its ^^token^^ written anywhere in the tree, with or without a %%format%%
 * suffix. The classic rule is the same: the field's variable in the layout.
 * An attachments list (a loop_area with props.source 'form_record.files',
 * includes/fn/widgets_lists.php) prints the field its sourceArg names, and,
 * naming none, every upload field not kept for office use: $listed says
 * the field is one of those.
 *
 * @param mixed $tree        the decoded tree, or its JSON
 * @param array $identifiers pg_record_file_field_identifiers()
 * @param bool  $listed      a file upload field not kept for office use
 * @return bool
 */
function pg_record_file_tree_binds($tree, $identifiers, $listed = false)
{
    if (is_string($tree)) $tree = json_decode($tree, true);
    if (!is_array($tree) || !is_array($identifiers) || !$identifiers) return false;

    $renderable = array('text' => true, 'html' => true, 'src' => true, 'alt' => true, 'href' => true, 'value' => true);
    $stack = array($tree);
    while ($stack) {
        $node = array_pop($stack);
        if (isset($node['type'], $node['props']['source']) && $node['type'] === 'loop_area'
            && $node['props']['source'] === 'form_record.files') {
            $arg = (isset($node['props']['sourceArg']) && is_scalar($node['props']['sourceArg']) && !is_bool($node['props']['sourceArg']))
                ? mb_strtolower(preg_replace('/[^a-z0-9_\-]/i', '', (string)$node['props']['sourceArg']), 'UTF-8') : '';
            if ($arg === '' ? $listed : isset($identifiers[$arg])) return true;
        }
        foreach ($node as $key => $value) {
            if ($key === '_bindings' && is_array($value)) {
                foreach ($value as $prop => $field) {
                    if (!isset($renderable[$prop]) || !is_scalar($field)) continue;
                    $safe = mb_strtolower(preg_replace('/[^a-z0-9_\-]/i', '', (string)$field), 'UTF-8');
                    if ($safe !== '' && isset($identifiers[$safe])) return true;
                }
                continue;
            }
            if (is_array($value)) {
                $stack[] = $value;
            } elseif (is_string($value) && strpos($value, '^^') !== false
                && preg_match_all('/\^\^(.*?)\^\^/', $value, $matches)) {
                foreach ($matches[1] as $token) {
                    if (isset($identifiers[mb_strtolower(trim($token), 'UTF-8')])) return true;
                }
            }
        }
    }
    return false;
}

/**
 * The children of a tree's first loop_area that is not a list (a list has a
 * non-empty string props.source and holds no main loop, so it is skipped
 * with everything in it), found depth first in child order from the root's
 * children: the record template _split_widget_tree() cuts out. Null when
 * the tree has none.
 *
 * @param mixed $tree the decoded tree, or its JSON
 * @return array|null
 */
function pg_record_file_loop_children($tree)
{
    if (is_string($tree)) $tree = json_decode($tree, true);
    if (!is_array($tree) || empty($tree['children']) || !is_array($tree['children'])) return null;
    // Pre-order, the first child walked first; the root itself is not a
    // candidate.
    $stack = array_reverse($tree['children']);
    while ($stack) {
        $node = array_pop($stack);
        if (!is_array($node)) continue;
        if (isset($node['type']) && $node['type'] === 'loop_area' && isset($node['props']) && is_array($node['props'])
            && isset($node['props']['source']) && is_string($node['props']['source']) && $node['props']['source'] !== '') {
            continue;
        }
        if (isset($node['type']) && $node['type'] === 'loop_area') {
            return (isset($node['children']) && is_array($node['children'])) ? $node['children'] : array();
        }
        if (!empty($node['children']) && is_array($node['children'])) {
            foreach (array_reverse($node['children']) as $child) $stack[] = $child;
        }
    }
    return null;
}

/**
 * Whether a form widget prints the field for a record
 * (pg_record_file_tree_binds()) in the part of its tree that receives the
 * record's values. A list draws its surround once, with no record, and sweeps
 * the identifiers left there, so only its loop_area counts. A record view
 * fills its surround with the record too, and a tree with no loop_area is
 * the record template as a whole: there the whole tree counts.
 *
 * @param mixed $cfg         the widget's system_region_config
 * @param mixed $tree        the decoded tree, or its JSON
 * @param array $identifiers pg_record_file_field_identifiers()
 * @param bool  $listed      a file upload field not kept for office use
 * @return bool
 */
function pg_record_file_widget_binds($cfg, $tree, $identifiers, $listed = false)
{
    if (is_string($tree)) $tree = json_decode($tree, true);
    $types = pg_record_file_widget_types();
    $type  = (is_array($cfg) && isset($cfg['regionType']) && is_string($cfg['regionType'])) ? $cfg['regionType'] : '';
    if (isset($types[$type]) && $types[$type] === 'form_list_view') {
        $loop = pg_record_file_loop_children($tree);
        if ($loop !== null) $tree = array('children' => $loop);
    }
    return pg_record_file_tree_binds($tree, $identifiers, $listed);
}

/**
 * The shared components a page tree places, in tree order, without looking
 * inside them: the walk pg_sw_page_widgets() makes. A widget nested in
 * another shared component is not found there either.
 *
 * @param mixed $tree the decoded tree, or its JSON
 * @return int[]
 */
function pg_record_file_tree_shared_ids($tree)
{
    if (is_string($tree)) $tree = json_decode($tree, true);
    $ids = array();
    if (!is_array($tree)) return $ids;
    $stack = array($tree);
    while ($stack) {
        $node = array_pop($stack);
        if (!is_array($node)) continue;
        if (isset($node['type']) && $node['type'] === 'shared_ref') {
            $sid = (int)(isset($node['props']['sharedId']) ? $node['props']['sharedId'] : 0);
            if ($sid > 0 && !in_array($sid, $ids, true)) $ids[] = $sid;
            continue;
        }
        if (!empty($node['children']) && is_array($node['children'])) {
            // Pushed in reverse so the first child is walked first.
            foreach (array_reverse($node['children']) as $child) $stack[] = $child;
        }
    }
    return $ids;
}

/**
 * Standard field names: pg_sw_standard_fields() and the names the list
 * template answers itself. A filter on one of them reads joined tables this
 * file does not build, so pg_record_file_filter_sql() gives no answer for it.
 * The test keeps this list a superset of pg_sw_standard_fields().
 *
 * @return string[]
 */
function pg_record_file_standard_names()
{
    return array(
        'reference_code', 'complete', 'address_name', 'tracking_code', 'affiliate_code', 'referring_url',
        'submitter', 'submitted_date_and_time', 'last_modifier', 'last_modified_date_and_time',
        'number_of_views', 'number_of_comments', 'recent_views', 'newest_comment_name', 'newest_comment',
        'newest_comment_date_and_time', 'newest_comment_id', 'newest_activity_date_and_time',
        'comment_attachments', 'form_item_view', 'submitted_form_id', 'submitter_user_id', 'submitter_name',
    );
}

/**
 * One filter comparison, as prepare_sql_operation() writes it.
 *
 * @param string $operator
 * @param string $operand_1 SQL
 * @param string $operand_2 value
 * @return string
 */
function pg_record_file_sql_operation($operator, $operand_1, $operand_2)
{
    $operand_2 = (string)$operand_2;
    $size  = array('is less than', 'is less than or equal to', 'is greater than', 'is greater than or equal to');
    $quote = (in_array($operator, $size, true) && !is_numeric($operand_2)) ? "'" : '';
    $like  = str_replace('_', '\_', str_replace('%', '\%', $operand_2));
    switch ($operator) {
        case 'does not contain':
            return "IFNULL(" . $operand_1 . ", '') NOT LIKE '%" . escape($like) . "%'";
        case 'is equal to':
            return "IFNULL(" . $operand_1 . ", '') = '" . escape($operand_2) . "'";
        case 'is not equal to':
            return "IFNULL(" . $operand_1 . ", '') != '" . escape($operand_2) . "'";
        case 'is less than':
            return "IFNULL(" . $operand_1 . ", '') < " . $quote . escape($operand_2) . $quote;
        case 'is less than or equal to':
            return "IFNULL(" . $operand_1 . ", '') <= " . $quote . escape($operand_2) . $quote;
        case 'is greater than':
            return "IFNULL(" . $operand_1 . ", '') > " . $quote . escape($operand_2) . $quote;
        case 'is greater than or equal to':
            return "IFNULL(" . $operand_1 . ", '') >= " . $quote . escape($operand_2) . $quote;
        case 'contains':
        default:
            return "IFNULL(" . $operand_1 . ", '') LIKE '%" . escape($like) . "%'";
    }
}

/**
 * The WHERE that holds a form's records to a list of filters, for a query
 * over `forms`, with no address: the SQL pg_sw_form_list_filter_sql() writes
 * for the same filters, and its fail-closed rule (pg_sw_filter_sql_closed()).
 *
 * `closed` is true when a filter names an unknown operator, comes after the
 * fiftieth, or names a field the form does not have; with $strict (a record
 * view) also for a filter valued from the address, which a list leaves out.
 * It is also true for a filter this file does not build: one on a standard
 * field, one compared with the viewer or the viewer's e-mail address, and a
 * date typed in the site's own format. Those keep the file closed, never open.
 *
 * @param mixed $filters the widget's `filters`
 * @param array $fields  the form's fields keyed by lower-case name (id, name,
 *                       type, multiple), information blocks and nameless
 *                       fields left out (pg_sw_filter_form_fields())
 * @param bool  $strict
 * @return array where (string), closed (bool)
 */
function pg_record_file_filter_sql($filters, $fields, $strict = false)
{
    $out    = array('where' => '', 'closed' => false);
    $closed = array('where' => ' AND (0 = 1)', 'closed' => true);
    if (!is_array($filters) || !$filters) return $out;
    if (!is_array($fields)) $fields = array();

    $operators = array(
        'contains', 'does not contain', 'is equal to', 'is not equal to',
        'is less than', 'is less than or equal to', 'is greater than', 'is greater than or equal to',
    );
    $dynamic_values = array(
        'current date', 'current date and time', 'current time', 'days ago',
        'viewer', 'viewers email address', 'url parameter',
    );
    $standard = array_flip(pg_record_file_standard_names());

    foreach (array_values($filters) as $position => $filter) {
        if (!is_array($filter)) continue;
        // A field or value that is not text names nothing the form has.
        if ((isset($filter['field']) && !is_scalar($filter['field']))
            || (isset($filter['value']) && !is_scalar($filter['value']))) return $closed;
        $field    = isset($filter['field']) ? trim((string)$filter['field']) : '';
        $operator = (isset($filter['operator']) && is_scalar($filter['operator'])) ? (string)$filter['operator'] : '';
        if ($field === '') continue;
        if ($position >= 50 || !in_array($operator, $operators, true)) return $closed;

        $value   = isset($filter['value']) ? (string)$filter['value'] : '';
        $dynamic = (isset($filter['dynamic_value']) && is_scalar($filter['dynamic_value'])) ? (string)$filter['dynamic_value'] : '';
        if (!in_array($dynamic, $dynamic_values, true)) $dynamic = '';

        // A value from the address: a fixed value wins over it; without one
        // a list leaves the filter out and a record view shows nothing.
        if ($dynamic === 'url parameter') {
            $dynamic = '';
            if ($value === '') {
                if ($strict) return $closed;
                continue;
            }
        }

        $key = mb_strtolower($field, 'UTF-8');
        if (isset($standard[$key]) || !isset($fields[$key]) || !is_array($fields[$key])) return $closed;

        $field_id = (int)$fields[$key]['id'];
        $type     = isset($fields[$key]['type']) ? (string)$fields[$key]['type'] : '';
        $multiple = isset($fields[$key]['multiple']) ? (int)$fields[$key]['multiple'] : 0;

        if (($type === 'pick list' && $multiple === 1) || $type === 'check box') {
            if ($operator === 'is equal to' || $operator === 'is not equal to') {
                $operand_1 = "(SELECT COUNT(*) FROM form_data WHERE (form_data.form_id = forms.id) AND (form_data.form_field_id = '$field_id') AND (form_data.data = '" . escape($value) . "'))";
                $operator  = ($operator === 'is equal to') ? 'is greater than' : 'is equal to';
                $value     = '0';
                $dynamic   = '';
            } else {
                $operand_1 = "(SELECT GROUP_CONCAT(form_data.data ORDER BY form_data.id ASC SEPARATOR ', ') FROM form_data WHERE (form_data.form_id = forms.id) AND (form_data.form_field_id = '$field_id'))";
            }
        } elseif ($type === 'file upload') {
            $operand_1 = "(SELECT files.name FROM form_data LEFT JOIN files ON form_data.file_id = files.id WHERE (form_data.form_id = forms.id) AND (form_data.form_field_id = '$field_id') LIMIT 1)";
        } else {
            $operand_1 = "(SELECT form_data.data FROM form_data WHERE (form_data.form_id = forms.id) AND (form_data.form_field_id = '$field_id') LIMIT 1)";
        }

        if ($value !== '') {
            // A date typed in the site's format is converted by the forms
            // module, which is not here.
            if ($type === 'date' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) && preg_match('/^\d{1,2}[-\/.]\d{1,2}[-\/.]\d{4}$/', $value)) {
                return $closed;
            }
            if ($type === 'date and time') {
                $value = str_replace('T', ' ', $value);
                if (preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}$/', $value)) $value .= ':00';
            }
            $operand_2 = $value;
        } elseif ($dynamic !== '') {
            $attribute = isset($filter['dynamic_value_attribute']) && is_scalar($filter['dynamic_value_attribute'])
                ? (int)$filter['dynamic_value_attribute'] : 0;
            switch ($dynamic) {
                case 'current date':
                    $operand_2 = date('Y-m-d');
                    break;
                case 'current date and time':
                    $operand_2 = date('Y-m-d H:i:s');
                    break;
                case 'current time':
                    $operand_2 = date('H:i:s');
                    break;
                case 'days ago':
                    $operand_2 = date('Y-m-d H:i:s', time() - (86400 * $attribute));
                    break;
                default:
                    // The viewer and the viewer's e-mail address.
                    return $closed;
            }
        } else {
            $operand_2 = '';
        }

        $out['where'] .= ' AND (' . pg_record_file_sql_operation($operator, $operand_1, $operand_2) . ')';
    }

    return $out;
}

/**
 * The filters a list's records go through when each is addressed on its
 * detail page: the record view's first, then the list's
 * (pg_sw_merge_record_filters()). Rows that are not arrays are dropped, as
 * pg_sw_record_view_filters() drops them.
 *
 * @param mixed $list_cfg
 * @param mixed $view_cfg
 * @return array
 */
function pg_record_file_merge_filters($list_cfg, $view_cfg)
{
    $view = array();
    if (is_array($view_cfg) && isset($view_cfg['filters']) && is_array($view_cfg['filters'])) {
        foreach ($view_cfg['filters'] as $filter) {
            if (is_array($filter)) $view[] = $filter;
        }
    }
    $list = (is_array($list_cfg) && isset($list_cfg['filters']) && is_array($list_cfg['filters']))
        ? array_values($list_cfg['filters']) : array();
    return array_merge($view, $list);
}

/**
 * Whether one form widget shows the record's file to this visitor - the
 * decision itself, over facts gathered beforehand:
 *
 *   cfg, tree                      the widget's config and tree
 *   form_page_id, form_page_name   the record's custom form
 *   identifiers                    pg_record_file_field_identifiers()
 *   listed                         the field is an upload not kept for
 *                                  office use (pg_record_file_tree_binds())
 *   pages                          the pages carrying the widget, each
 *                                  array('binned', 'draft', 'viewable')
 *   filters                        'pass', 'fail' or 'closed' for the record
 *
 * It does: a record view or a list of that form, public
 * (pg_record_file_widget_public()), whose tree prints the field, on at least
 * one page that is neither in the recycle bin nor among the drafts and that
 * the visitor may view, and whose filters the record passes.
 *
 * @param array $facts
 * @return bool
 */
function pg_record_file_view_grants($facts)
{
    if (!is_array($facts) || !isset($facts['cfg']) || !is_array($facts['cfg'])) return false;
    $cfg   = $facts['cfg'];
    $types = pg_record_file_widget_types();
    $type  = isset($cfg['regionType']) && is_string($cfg['regionType']) ? $cfg['regionType'] : '';
    if (!isset($types[$type])) return false;
    if (!pg_record_file_widget_form_matches($cfg,
            isset($facts['form_page_id']) ? $facts['form_page_id'] : 0,
            isset($facts['form_page_name']) ? $facts['form_page_name'] : '')) return false;
    if (!pg_record_file_widget_public($cfg)) return false;
    if (!pg_record_file_widget_binds($cfg, isset($facts['tree']) ? $facts['tree'] : null,
            isset($facts['identifiers']) ? $facts['identifiers'] : array(), !empty($facts['listed']))) return false;
    if (!isset($facts['filters']) || $facts['filters'] !== 'pass') return false;
    return pg_record_file_open_page(isset($facts['pages']) ? $facts['pages'] : array());
}

/**
 * Whether one of a widget's pages shows it to this visitor: neither in the
 * recycle bin nor among the drafts, and viewable.
 *
 * @param mixed $pages list of array('binned', 'draft', 'viewable')
 * @return bool
 */
function pg_record_file_open_page($pages)
{
    if (!is_array($pages)) return false;
    foreach ($pages as $page) {
        if (!is_array($page)) continue;
        if (!empty($page['binned']) || !empty($page['draft'])) continue;
        if (!empty($page['viewable'])) return true;
    }
    return false;
}

/**
 * Rows of a query, or none when it fails: this file runs inside a file
 * request, where a failed query must keep the file closed, not end the
 * request.
 *
 * @param string $query
 * @return array
 */
function pg_record_file_rows($query)
{
    if (!class_exists('db', false) || !isset(db::$con) || !db::$con) return array();
    $result = @mysqli_query(db::$con, $query);
    if (!$result || $result === true) return array();
    $rows = array();
    while ($row = mysqli_fetch_assoc($result)) $rows[] = $row;
    return $rows;
}

/**
 * The SQL expression of a page's layout tree: its own, else its style's
 * (pg_page_tree_sql_expr()).
 *
 * @return string
 */
function pg_record_file_tree_sql()
{
    static $expr = null;
    if ($expr === null) {
        $expr = pg_auth_column_exists('page', 'page_tree_json')
            ? "COALESCE(NULLIF(page.page_tree_json, ''), style.style_tree_json)"
            : 'style.style_tree_json';
    }
    return $expr;
}

/**
 * The folders a page may not be shown from: the recycle bin with every
 * folder under it, and the drafts folder.
 *
 * @return array 'binned' => int[], 'draft' => int
 */
function pg_record_file_closed_folders()
{
    static $out = null;
    if ($out !== null) return $out;
    $out = array('binned' => array(), 'draft' => 0);

    $columns = array();
    foreach (array('recycle_folder_id', 'draft_folder_id') as $column) {
        if (pg_auth_column_exists('config', $column)) $columns[] = $column;
    }
    if (!$columns) return $out;
    $config = pg_record_file_rows("SELECT " . implode(', ', $columns) . " FROM config LIMIT 1");
    $config = $config ? $config[0] : array();

    $bin = isset($config['recycle_folder_id']) ? (int)$config['recycle_folder_id'] : 0;
    if ($bin > 0) {
        $children = array();
        foreach (pg_record_file_rows("SELECT folder_id, folder_parent FROM folder") as $row) {
            $children[(int)$row['folder_parent']][] = (int)$row['folder_id'];
        }
        $out['binned'] = array($bin);
        $stack = array($bin);
        while ($stack) {
            $folder = array_pop($stack);
            if (empty($children[$folder])) continue;
            foreach ($children[$folder] as $child) {
                if (in_array($child, $out['binned'], true)) continue;
                $out['binned'][] = $child;
                $stack[] = $child;
            }
        }
    }
    $out['draft'] = isset($config['draft_folder_id']) ? (int)$config['draft_folder_id'] : 0;
    return $out;
}

/**
 * The query that finds the pages carrying any of the shared components: one
 * column per component (s<id>) telling whether the page's tree places it.
 * With $page_ids (an array) it reads only those pages and the home page; a
 * list's detail page and the home page are where record views and lists
 * usually sit. Without it, it scans every page tree with one LIKE per
 * component, a prefix of the exact pair the columns test, so the trees are
 * read once per component; the exact pair runs only on the rows found.
 *
 * @param int[]      $sids     component ids, each > 0
 * @param string     $expr     pg_record_file_tree_sql()
 * @param int[]|null $page_ids
 * @return string '' when there is no component
 */
function pg_record_file_pages_sql($sids, $expr, $page_ids = null)
{
    $columns = array();
    $likes   = array();
    foreach ((array)$sids as $sid) {
        $sid = (int)$sid;
        if ($sid <= 0) continue;
        $columns[] = "($expr LIKE '%\"sharedId\":" . $sid . ",%' OR $expr LIKE '%\"sharedId\":" . $sid . "}%') AS s" . $sid;
        $likes[]   = "$expr LIKE '%\"sharedId\":" . $sid . "%'";
    }
    if (!$columns) return '';
    if (is_array($page_ids)) {
        $ids = array();
        foreach ($page_ids as $page_id) {
            if ((int)$page_id > 0) $ids[(int)$page_id] = (int)$page_id;
        }
        $where = ($ids ? "page.page_id IN (" . implode(',', $ids) . ") OR " : '') . "page.page_home = 'yes'";
    } else {
        $where = implode(' OR ', $likes);
    }
    return "SELECT page.page_id, page.page_folder, " . implode(', ', $columns) . "
         FROM page
         INNER JOIN style ON page.page_style = style.style_id
         WHERE " . $where;
}

/**
 * The pages carrying any of the shared components, each with the facts
 * pg_record_file_view_grants() reads, keyed by component id: those of
 * $page_ids and the home page, or with null every page, in one scan of the
 * page trees (pg_record_file_pages_sql()).
 *
 * @param int[]      $sids
 * @param int[]|null $page_ids
 * @return array sid => list of array('page_id', 'binned', 'draft', 'viewable')
 */
function pg_record_file_widget_pages($sids, $page_ids = null)
{
    static $views = array();
    $out = array();
    foreach ((array)$sids as $sid) {
        if ((int)$sid > 0) $out[(int)$sid] = array();
    }
    $sql = pg_record_file_pages_sql(array_keys($out), pg_record_file_tree_sql(), $page_ids);
    if ($sql === '') return $out;
    $closed = pg_record_file_closed_folders();
    foreach (pg_record_file_rows($sql) as $row) {
        $folder = (int)$row['page_folder'];
        $binned = in_array($folder, $closed['binned'], true);
        $draft  = $closed['draft'] > 0 && $folder === $closed['draft'];
        $page   = null;
        foreach (array_keys($out) as $sid) {
            // A row the prefix found for another id (12 for 123) places none.
            if (empty($row['s' . $sid])) continue;
            if ($page === null) {
                if (!$binned && !$draft && !isset($views[$folder])) {
                    // The folder check of the classic branch; skipped for a
                    // page that is closed anyway.
                    $views[$folder] = (check_view_access($folder) == true);
                }
                $page = array(
                    'page_id'  => (int)$row['page_id'],
                    'binned'   => $binned,
                    'draft'    => $draft,
                    'viewable' => (!$binned && !$draft) ? $views[$folder] : false,
                );
            }
            $out[$sid][] = $page;
        }
    }
    return $out;
}

/**
 * The record view a list's records are addressed on: the first form_item_view
 * on the list's detail page that shows the same form, as the list's feed
 * picks it. Its config, or an empty array.
 *
 * @param int    $detail_page_id
 * @param int    $form_page_id
 * @param string $form_page_name
 * @return array
 */
function pg_record_file_detail_view($detail_page_id, $form_page_id, $form_page_name)
{
    static $cache = array();
    $detail_page_id = (int)$detail_page_id;
    if ($detail_page_id <= 0) return array();
    $key = $detail_page_id . '|' . (int)$form_page_id;
    if (!isset($cache[$key])) $cache[$key] = pg_record_file_find_detail_view($detail_page_id, $form_page_id, $form_page_name);
    return $cache[$key];
}

/**
 * pg_record_file_detail_view() without its memo.
 *
 * @param int    $detail_page_id
 * @param int    $form_page_id
 * @param string $form_page_name
 * @return array
 */
function pg_record_file_find_detail_view($detail_page_id, $form_page_id, $form_page_name)
{
    $tree = pg_record_file_rows(
        "SELECT " . pg_record_file_tree_sql() . " AS tree
         FROM page
         INNER JOIN style ON page.page_style = style.style_id
         WHERE page.page_id = '" . $detail_page_id . "' LIMIT 1");
    $sids = $tree ? pg_record_file_tree_shared_ids((string)$tree[0]['tree']) : array();
    if (!$sids) return array();
    $configs = array();
    foreach (pg_record_file_rows(
        "SELECT id, system_region_config FROM shared_components
         WHERE id IN (" . implode(',', $sids) . ")
           AND system_region_config IS NOT NULL AND system_region_config != ''") as $row) {
        $configs[(int)$row['id']] = json_decode((string)$row['system_region_config'], true);
    }
    foreach ($sids as $sid) {
        $cfg = isset($configs[$sid]) ? $configs[$sid] : null;
        if (!is_array($cfg) || !isset($cfg['regionType']) || $cfg['regionType'] !== 'form_item_view') continue;
        if (pg_record_file_widget_form_matches($cfg, $form_page_id, $form_page_name)) return $cfg;
    }
    return array();
}

/**
 * The query that tells whether the record passes a widget's filter clause
 * (pg_record_file_filter_sql()), or '' when nothing needs asking. A record
 * view shows a record by its address whether it is complete or not, so with
 * no filter it passes. A list shows only complete records (its own query,
 * its feed and the sitemap ask for forms.complete = 1): a record saved for
 * later or set to incomplete is in no list, so the list always asks.
 *
 * @param bool  $is_list
 * @param array $clause       where (string), closed (bool)
 * @param int   $form_page_id
 * @param int   $form_id
 * @return string
 */
function pg_record_file_filter_count_sql($is_list, $clause, $form_page_id, $form_id)
{
    $where = (is_array($clause) && isset($clause['where'])) ? (string)$clause['where'] : '';
    if (!$is_list && $where === '') return '';
    return "SELECT COUNT(*) AS matches FROM forms
         WHERE forms.id = '" . (int)$form_id . "'
           AND forms.page_id = '" . (int)$form_page_id . "'"
           . ($is_list ? "
           AND forms.complete = 1" : '') . "
           " . $where;
}

/**
 * Whether the record passes a widget's filters: 'pass', 'fail' or 'closed'.
 * A record view applies its own (strict); a list applies its detail view's
 * strictly first, then those together with its own, its address filters left
 * out (pg_sw_list_record_filter_clause() with no request), and shows only
 * complete records. A failed query counts as 'fail'. Widgets with the same
 * filters share one answer for the request.
 *
 * @param array  $cfg
 * @param int    $form_page_id
 * @param string $form_page_name
 * @param int    $form_id
 * @param array  $fields pg_record_file_filter_sql()'s $fields
 * @return string
 */
function pg_record_file_filter_verdict($cfg, $form_page_id, $form_page_name, $form_id, $fields)
{
    static $cache = array();
    $is_list = !(isset($cfg['regionType']) && $cfg['regionType'] === 'form_item_view');
    $key = json_encode(array(
        (int)$form_page_id, (int)$form_id, $is_list,
        isset($cfg['filters']) ? $cfg['filters'] : null,
        ($is_list && isset($cfg['detail_page_id'])) ? (int)$cfg['detail_page_id'] : 0,
    ));
    if ($key !== false && isset($cache[$key])) return $cache[$key];

    if (!$is_list) {
        $clause = pg_record_file_filter_sql(pg_record_file_merge_filters(array(), $cfg), $fields, true);
    } else {
        $view   = pg_record_file_detail_view(isset($cfg['detail_page_id']) ? $cfg['detail_page_id'] : 0, $form_page_id, $form_page_name);
        $clause = pg_record_file_filter_sql(pg_record_file_merge_filters(array(), $view), $fields, true);
        if (!$clause['closed']) {
            $clause = pg_record_file_filter_sql(pg_record_file_merge_filters($cfg, $view), $fields, false);
        }
    }
    if ($clause['closed']) {
        $verdict = 'closed';
    } else {
        $sql = pg_record_file_filter_count_sql($is_list, $clause, $form_page_id, $form_id);
        if ($sql === '') {
            $verdict = 'pass';
        } else {
            $count   = pg_record_file_rows($sql);
            $verdict = ($count && (int)$count[0]['matches'] > 0) ? 'pass' : 'fail';
        }
    }
    if ($key !== false) $cache[$key] = $verdict;
    return $verdict;
}

/**
 * Whether a page built in the visual editor shows this visitor the file a
 * submitted form holds in one of its fields: a public record view or list of
 * the form, placed on a page the visitor may view (not binned, not a draft),
 * printing that field, for a record that passes the widget's filters
 * (pg_record_file_view_grants()). get_file.php asks after its classic checks
 * have refused the file.
 *
 * The widgets and their bindings come first, in two small queries. Then the
 * pages: those the form's lists name as their detail page and the home page,
 * read by id; one scan of every page tree only for the widgets not found
 * there on a page the visitor may view. The record's filters are asked only
 * for a widget on such a page, so how long a refusal takes never tells
 * whether a record on a page closed to the visitor passes its filters. The
 * first widget that shows the file ends the search; the answer is kept for
 * the request.
 *
 * A file opened here depends on the visitor (the page's folder) and on the
 * record as it is now (its filters), so get_file.php sends it as private:
 * no shared cache may keep it.
 *
 * @param int    $custom_form_page_id the record's custom form (page id)
 * @param int    $form_id             the record (forms.id)
 * @param string $field_name          the field holding the file
 * @return bool
 */
function pg_record_file_designed_access($custom_form_page_id, $form_id, $field_name)
{
    static $cache = array();
    $custom_form_page_id = (int)$custom_form_page_id;
    $form_id             = (int)$form_id;
    $field_name          = (string)$field_name;
    if ($custom_form_page_id <= 0 || $form_id <= 0 || $field_name === '') return false;
    $key = $custom_form_page_id . '|' . $form_id . '|' . $field_name;
    if (isset($cache[$key])) return $cache[$key];
    $cache[$key] = false;

    $form = pg_record_file_rows(
        "SELECT page.page_name, form_fields.label, form_fields.type, form_fields.office_use_only
         FROM page
         LEFT JOIN form_fields ON (form_fields.page_id = page.page_id) AND (form_fields.name = '" . escape($field_name) . "')
         WHERE page.page_id = '" . $custom_form_page_id . "'
         LIMIT 1");
    if (!$form) return false;
    $form_page_name = (string)$form[0]['page_name'];
    // A filter compared with today's date reads the site's time zone, which
    // get_file.php sets only when it needs a date.
    if (function_exists('initialize_timezone')) initialize_timezone();
    $identifiers    = pg_record_file_field_identifiers($field_name, (string)$form[0]['label']);
    // An attachments list that names no field prints this one when it is
    // an upload field not kept for office use.
    $listed         = isset($form[0]['type'], $form[0]['office_use_only'])
        && (string)$form[0]['type'] === 'file upload' && (string)$form[0]['office_use_only'] === '0';

    // The widgets of this form that show its records to everyone, and the
    // detail pages its lists name.
    $likes = array();
    foreach (array_keys(pg_record_file_widget_types()) as $type) {
        $likes[] = "system_region_config LIKE '%\"regionType\":\"" . $type . "\"%'";
    }
    $candidates = array();
    $known      = array();
    foreach (pg_record_file_rows(
        "SELECT id, system_region_config FROM shared_components
         WHERE " . implode(' OR ', $likes) . "
         ORDER BY id ASC") as $widget) {
        $cfg = json_decode((string)$widget['system_region_config'], true);
        if (!is_array($cfg) || !pg_record_file_widget_form_matches($cfg, $custom_form_page_id, $form_page_name)) continue;
        if (!empty($cfg['detail_page_id']) && is_scalar($cfg['detail_page_id'])) $known[] = (int)$cfg['detail_page_id'];
        if (!pg_record_file_widget_public($cfg)) continue;
        $candidates[(int)$widget['id']] = array(
            'cfg'            => $cfg,
            'tree'           => '',
            'form_page_id'   => $custom_form_page_id,
            'form_page_name' => $form_page_name,
            'identifiers'    => $identifiers,
            'listed'         => $listed,
            'pages'          => array(),
            'filters'        => 'fail',
        );
    }
    if (!$candidates) return false;

    // Those that print the field, their trees read in one query.
    foreach (pg_record_file_rows(
        "SELECT id, tree_json FROM shared_components
         WHERE id IN (" . implode(',', array_keys($candidates)) . ")") as $row) {
        $sid = (int)$row['id'];
        if (isset($candidates[$sid])) $candidates[$sid]['tree'] = (string)$row['tree_json'];
    }
    foreach ($candidates as $sid => $facts) {
        if (!pg_record_file_widget_binds($facts['cfg'], $facts['tree'], $identifiers, $listed)) unset($candidates[$sid]);
    }
    if (!$candidates) return false;

    $fields = array();
    foreach (pg_record_file_rows(
        "SELECT id, name, type, multiple FROM form_fields
         WHERE page_id = '" . $custom_form_page_id . "'
           AND type != 'information'
           AND name != ''") as $row) {
        $fields[mb_strtolower((string)$row['name'], 'UTF-8')] = $row;
    }

    // The pages the widgets usually sit on, read by id; the widgets not
    // found there on a page the visitor may view go to one scan of every
    // page tree. A widget's filters are asked only once it is known to be on
    // such a page.
    $rest = $candidates;
    foreach (array(pg_record_file_unique_ids($known), null) as $page_ids) {
        if (!$rest) break;
        $pages = pg_record_file_widget_pages(array_keys($rest), $page_ids);
        foreach ($rest as $sid => $facts) {
            $facts['pages'] = isset($pages[$sid]) ? $pages[$sid] : array();
            if (!pg_record_file_open_page($facts['pages'])) continue;
            unset($rest[$sid]);
            $facts['filters'] = pg_record_file_filter_verdict($facts['cfg'], $custom_form_page_id, $form_page_name, $form_id, $fields);
            if (pg_record_file_view_grants($facts)) {
                return $cache[$key] = true;
            }
        }
    }
    return false;
}

/**
 * Positive ids, each once, in their first order.
 *
 * @param mixed $ids
 * @return int[]
 */
function pg_record_file_unique_ids($ids)
{
    $out = array();
    foreach ((array)$ids as $id) {
        $id = (int)$id;
        if ($id > 0 && !in_array($id, $out, true)) $out[] = $id;
    }
    return $out;
}
