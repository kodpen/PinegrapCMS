<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * A page's custom layout: the PHP view template render_layout() includes
 * for a page whose layout type is 'custom'. While the page is unmodified the
 * layout is generated from the page type's template
 * (generate_layout_content()); once someone saves a text that differs from
 * the generated one it is written to data/layouts/<page id>.php and
 * page.layout_modified is set. A hosted site neither writes nor uses the
 * file. Read and saved by the classic page designer (get_layout,
 * update_layout), and through layout_code by the visual designer's layout
 * code tab and the layout editor screen (edit_page_layout.php).
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_FUNCTIONS_DIR')) {
    exit;
}

// The file name of a page's layout under LAYOUT_DIRECTORY_PATH, built from
// the page id alone so a request can never name another path; '' for an id
// that is not a positive number.
function pg_layout_file_name($page_id)
{
    if (is_int($page_id)) {
        $id = $page_id;
    } else if (is_string($page_id) && preg_match('/^[0-9]+$/', $page_id)) {
        $id = (int) $page_id;
    } else {
        return '';
    }

    return ($id > 0) ? $id . '.php' : '';
}

// Whether a layout text is the generated one. White space is ignored: the
// code editor and the generator do not agree on line endings and
// indentation, and a layout saved unchanged must stay unmodified, so it
// keeps following the form's fields.
function pg_layout_same_as_generated($content, $generated)
{
    return preg_replace('/\s+/', '', (string) $content) === preg_replace('/\s+/', '', (string) $generated);
}

function pg_layout_generated($page_id)
{
    require_once(PG_FUNCTIONS_DIR . '/generate_layout_content.php');

    return generate_layout_content($page_id);
}

// The page's layout as the site draws it: the saved file while the page is
// modified, else the generated text (always the generated text on a hosted
// site, which ignores the file). False when the page does not exist.
function pg_layout_read($page_id)
{
    $file = pg_layout_file_name($page_id);

    if ($file === '') {
        return false;
    }

    $layout = db_item(
        "SELECT
            page_id AS id,
            page_name AS name,
            page_type AS type,
            layout_type,
            layout_modified AS modified
        FROM page
        WHERE page_id = '" . (int) $page_id . "'"
    );

    if (!$layout) {
        return false;
    }

    if ($layout['modified'] && !pg_hosted()) {
        $layout['content'] = (string) @file_get_contents(LAYOUT_DIRECTORY_PATH . '/' . $file);
    } else {
        $layout['content'] = pg_layout_generated($layout['id']);
    }

    return $layout;
}

// Saves a page's layout: a text equal to the generated one (white space
// aside) removes the file and marks the page unmodified, any other text is
// written to the page's file and marks it modified. Returns
// array('ok' => bool, 'modified' => 0|1, 'message' => string).
function pg_layout_save($page_id, $content)
{
    if (pg_hosted()) {
        return array('ok' => false, 'modified' => 0, 'message' => lang('Custom layouts are not available on this site.'));
    }

    $file = pg_layout_file_name($page_id);
    $layout = ($file !== '')
        ? db_item("SELECT page_id AS id, page_name AS name FROM page WHERE page_id = '" . (int) $page_id . "'")
        : false;

    if (!$layout) {
        return array('ok' => false, 'modified' => 0, 'message' => 'Layout could not be found.');
    }

    $path = LAYOUT_DIRECTORY_PATH . '/' . $file;

    if (pg_layout_same_as_generated($content, pg_layout_generated($layout['id']))) {
        if (file_exists($path)) {
            unlink($path);
        }

        $modified = 0;
    } else {
        @file_put_contents($path, (string) $content);

        $modified = 1;
    }

    // Marked so the layout is no longer generated when the form's fields
    // change.
    db(
        "UPDATE page
        SET
            layout_modified = '$modified',
            page_user = '" . USER_ID . "',
            page_timestamp = UNIX_TIMESTAMP()
        WHERE page_id = '" . (int) $layout['id'] . "'"
    );

    log_activity('layout for page (' . $layout['name'] . ') was modified');

    return array('ok' => true, 'modified' => $modified, 'message' => '');
}

// Of the given pages, the ids of those that draw a custom layout (a page
// type with a layout and layout type 'custom'): the pages whose system
// region opens the layout code tab in the visual designer.
function pg_layout_custom_page_ids(array $page_ids)
{
    $ids = array();

    foreach ($page_ids as $id) {
        if ((int) $id > 0) {
            $ids[(int) $id] = true;
        }
    }

    if (!$ids) {
        return array();
    }

    $rows = db_items(
        "SELECT page_id, page_type
        FROM page
        WHERE
            page_id IN (" . implode(',', array_keys($ids)) . ")
            AND layout_type = 'custom'"
    );

    $out = array();

    foreach (is_array($rows) ? $rows : array() as $row) {
        if (check_if_page_type_supports_layout($row['page_type'])) {
            $out[] = (int) $row['page_id'];
        }
    }

    return $out;
}

// Of those pages (pg_layout_custom_page_ids()), the custom form pages: their
// layout converts into a custom_form widget (pg_layout_form_convert()).
function pg_layout_form_page_ids(array $page_ids)
{
    $ids = array();

    foreach ($page_ids as $id) {
        if ((int) $id > 0) {
            $ids[(int) $id] = true;
        }
    }

    if (!$ids) {
        return array();
    }

    $out = array();

    foreach ((array) db_values(
        "SELECT page_id FROM page
        WHERE page_id IN (" . implode(',', array_keys($ids)) . ") AND page_type = 'custom form'"
    ) as $id) {
        $out[] = (int) $id;
    }

    return $out;
}

// The parameter the toolbar's style designer button (Ctrl+G) adds so the
// visual designer opens on the page's layout tab, where the classic page
// designer used to open: for a page that draws a custom layout, for the
// layout editor's role (designers and administrators). Not on a hosted
// site, which draws the generated layout and keeps the tab read only; the
// button opens the canvas there.
function pg_layout_tab_param($page_id, array $layout_page_ids, $role, $hosted)
{
    $page_id = (int) $page_id;

    if (($page_id <= 0) || ((int) $role > 1) || $hosted) {
        return '';
    }

    return in_array($page_id, array_map('intval', $layout_page_ids), true) ? '&layout=1' : '';
}

// Where the form screen's "Edit Layout" link goes for a page that draws a
// custom layout. A page whose own style is a visual design opens the style
// designer on that page and, as Ctrl+G does (pg_layout_tab_param()), on its
// layout tab; any other page opens the layout-only editor screen
// (edit_page_layout.php), read only there on a hosted site. The URL is
// returned unescaped.
function pg_layout_edit_url($page_id, $style_id, $designed, $role, $hosted)
{
    $page_id = (int) $page_id;
    $style_id = (int) $style_id;

    if ($designed && ($style_id > 0)) {
        return 'edit_system_style.php?id=' . $style_id . '&page=' . $page_id
            . pg_layout_tab_param($page_id, array($page_id), $role, $hosted);
    }

    return 'edit_page_layout.php?id=' . $page_id;
}

// The page whose layout tab the designer opens on load (?layout=1): the page
// it lands on, when that page draws a custom layout; 0 otherwise.
function pg_layout_open_page_id($flag, $page_id, array $layout_page_ids)
{
    $page_id = (int) $page_id;

    if (!is_scalar($flag) || ((string) $flag === '') || ((string) $flag === '0') || ($page_id <= 0)) {
        return 0;
    }

    return in_array($page_id, array_map('intval', $layout_page_ids), true) ? $page_id : 0;
}

// While on, get_page_content() puts a marker before and after the output of
// the page's own system region, so the visual designer's canvas can cut the
// region out of a whole page render (pg_layout_region_preview()). Off in
// every ordinary request.
function pg_layout_region_marking($set = null)
{
    static $on = false;

    if ($set !== null) {
        $on = (bool) $set;
    }

    return $on;
}

// The system region's output between the markers pg_layout_region_marking()
// puts around it; '' when the page drew no system region of its own (its
// saved tree has none yet).
function pg_layout_region_cut($html)
{
    $html = (string) $html;

    if (!preg_match('/<!--pg-layout-region-start-->(.*?)<!--pg-layout-region-end-->/s', $html, $m)) {
        return '';
    }

    return trim($m[1]);
}

// The system region of a page that draws a custom layout, as the server draws
// it for the signed-in user, for the visual designer's canvas to show read
// only in place of the region's placeholder. The page's saved state is drawn
// (its stored tree, type settings and layout file; a hosted site's generated
// layout). A background render, as the editor's Preview: no status code, no
// redirect, no view counted, and an error comes back as '' instead of a
// screen.
function pg_layout_region_preview($page_id)
{
    $page_id = (int) $page_id;

    if (($page_id <= 0) || !pg_layout_custom_page_ids(array($page_id))) {
        return '';
    }

    $page_name = (string) db_value("SELECT page_name FROM page WHERE page_id = '" . $page_id . "' LIMIT 1");

    if ($page_name === '') {
        return '';
    }

    require_once(PG_FUNCTIONS_DIR . '/get_page_content.php');

    if (function_exists('pg_rendered_page')) {
        pg_rendered_page($page_id);
    }

    $saved_get = $_GET;
    $saved_seo = pg_seo_rendering();
    $saved_throw = pg_error_throws();
    $_GET = array('page' => $page_name);
    pg_seo_rendering(true);
    pg_error_throws(true);
    pg_layout_region_marking(true);

    $html = '';
    ob_start();

    try {
        $html = (string) get_page_content($page_id, '', '', 'preview', false, array(), false, 'desktop');
    } catch (Throwable $t) {
        $html = '';
    } finally {
        ob_end_clean();
        pg_layout_region_marking(false);
        pg_seo_rendering($saved_seo);
        pg_error_throws($saved_throw);
        $_GET = $saved_get;
    }

    return pg_layout_region_cut($html);
}

// The system widget kind that can stand in for a classic page type's custom
// layout, page type => kind: the types render_layout() draws that have a
// widget. A custom form page is left out: its layout converts into a widget
// of design elements instead (pg_layout_form_convert()). A type with no
// widget (order receipt) gets no offer. Pure.
function pg_layout_widget_kinds()
{
    return array(
        'catalog'                      => 'catalog_listing',
        'catalog detail'               => 'catalog_item_view',
        'form list view'               => 'form_list_view',
        'form item view'               => 'form_item_view',
        'my account'                   => 'my_account',
        'my account profile'           => 'account_profile',
        'email preferences'            => 'email_preferences',
        'update address book'          => 'address_book',
        'change password'              => 'change_password',
        'set password'                 => 'set_password',
        'login'                        => 'login_form',
        'forgot password'              => 'forgot_password',
        'registration entrance'        => 'registration',
        'membership entrance'          => 'membership',
        'search results'               => 'search_results',
        'shopping cart'                => 'shopping_cart',
        'express order'                => 'express_order',
        'shipping address and arrival' => 'shipping_address_and_arrival',
        'shipping method'              => 'shipping_method',
        'billing information'          => 'billing_information',
        'order preview'                => 'order_preview',
        'order form'                   => 'order_form',
        'photo gallery'                => 'photo_gallery',
        'view order'                   => 'order_view',
    );
}

// The widget settings that carry a classic page's type settings over, for
// the types whose widget keeps them in its own config: $row is the page's
// row of the type table, $field_names the custom form's field names by id
// (a list ordered by a form field). The other widgets read their page's type row themselves
// (checkout steps, order form, folder and gallery) or need nothing. What a
// widget has no setting for (columns, image sizes, featured items, the
// advanced search, a third sort) is not carried. Pure.
function pg_layout_widget_seed($page_type, array $row, array $field_names = array())
{
    $int = function ($key) use ($row) {
        return isset($row[$key]) ? (int) $row[$key] : 0;
    };
    $cfg = array();

    switch ((string) $page_type) {
        case 'catalog':
            $cfg['product_group_id'] = $int('product_group_id');
            $cfg['detail_page_id'] = $int('catalog_detail_page_id');
            $cfg['search_enabled'] = ($int('search') === 1);
            break;

        case 'catalog detail':
            if ($int('next_page_id') > 0) {
                $cfg['next_page_id'] = $int('next_page_id');
            }
            break;

        case 'form list view':
            $cfg['form_page_id'] = $int('custom_form_page_id');
            $cfg['detail_page_id'] = $int('form_item_view_page_id');
            $cfg['max_results'] = $int('maximum_number_of_results');
            if ($int('maximum_number_of_results_per_page') > 0) {
                $cfg['items_per_page'] = $int('maximum_number_of_results_per_page');
            }
            $cfg['search_enabled'] = ($int('search') === 1);
            if (isset($row['search_label']) && ((string) $row['search_label'] !== '')) {
                $cfg['search_label'] = (string) $row['search_label'];
            }
            foreach (array('viewer_filter', 'viewer_filter_submitter', 'viewer_filter_watcher', 'viewer_filter_editor') as $key) {
                $cfg[$key] = ($int($key) === 1);
            }
            foreach (array(1 => '', 2 => '_2') as $n => $suffix) {
                $field = pg_layout_widget_order_field($row, $n, $field_names);
                if ($field === '') {
                    continue;
                }
                $cfg['order_by' . $suffix . '_field'] = $field;
                $type = isset($row['order_by_' . $n . '_type']) ? (string) $row['order_by_' . $n . '_type'] : '';
                $cfg['order_by' . $suffix . '_direction'] = (strpos($type, 'descending') === 0) ? 'DESC' : 'ASC';
            }
            break;

        case 'form item view':
            $cfg['form_page_id'] = $int('custom_form_page_id');
            break;

    }

    return $cfg;
}

// The form list widget's name for sort $n (1 or 2) of a classic form list
// view row: the submitted date or a record statistic as it is, a form field
// by its name; '' when the widget has no such order (random, the reference
// code, the submitter...) and the widget's own order applies. Pure.
function pg_layout_widget_order_field(array $row, $n, array $field_names)
{
    $standard = isset($row['order_by_' . $n . '_standard_field']) ? (string) $row['order_by_' . $n . '_standard_field'] : '';

    if ($standard !== '') {
        $kept = array('submitted_date_and_time', 'number_of_views', 'number_of_comments', 'newest_activity_date_and_time');

        return in_array($standard, $kept, true) ? $standard : '';
    }

    $field_id = isset($row['order_by_' . $n . '_form_field_id']) ? (int) $row['order_by_' . $n . '_form_field_id'] : 0;

    return (($field_id > 0) && isset($field_names[$field_id])) ? (string) $field_names[$field_id] : '';
}

// Of the pages that draw a custom layout (pg_layout_custom_page_ids()), those
// whose type has a widget, page id => ['kind' => widget kind, 'cfg' =>
// settings carried from the page's type settings]: the visual designer offers
// to put that widget beside the page's system region, never on its own.
function pg_layout_widget_offers(array $layout_page_ids)
{
    $ids = array();

    foreach ($layout_page_ids as $id) {
        if ((int) $id > 0) {
            $ids[(int) $id] = true;
        }
    }

    if (!$ids) {
        return array();
    }

    $kinds = pg_layout_widget_kinds();
    $tables = array(
        'catalog'        => array('catalog_pages', ''),
        'catalog detail' => array('catalog_detail_pages', ''),
        'form list view' => array('form_list_view_pages', " AND collection = 'a'"),
        'form item view' => array('form_item_view_pages', " AND collection = 'a'"),
    );
    $out = array();

    foreach ((array) db_items("SELECT page_id, page_type FROM page WHERE page_id IN (" . implode(',', array_keys($ids)) . ")") as $page) {
        $page_id = (int) $page['page_id'];
        $type = (string) $page['page_type'];

        if (!isset($kinds[$type])) {
            continue;
        }

        $row = array();
        $field_names = array();

        if (isset($tables[$type])) {
            $found = db_item("SELECT * FROM " . $tables[$type][0] . " WHERE page_id = '" . $page_id . "'" . $tables[$type][1] . " LIMIT 1");
            $row = is_array($found) ? $found : array();
        }

        if (($type === 'form list view') && !empty($row['custom_form_page_id'])) {
            foreach ((array) db_items("SELECT id, name FROM form_fields WHERE page_id = '" . (int) $row['custom_form_page_id'] . "'") as $field) {
                $field_names[(int) $field['id']] = (string) $field['name'];
            }
        }

        $out[$page_id] = array(
            'kind' => $kinds[$type],
            'cfg'  => (object) pg_layout_widget_seed($type, $row, $field_names),
        );
    }

    return $out;
}
