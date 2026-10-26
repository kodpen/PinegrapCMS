<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Visual Page Editor - the Menu system widget (kind 'menu_region'): one of
 * the site's menus, the classic <menu> region drawn by the designer. The
 * items stay in the menu screen (view_menu_items.php) and with the users
 * who may edit that menu (users_menus_xref); the widget reads them on
 * every request, so a change there shows on every page at once.
 *
 * The main loop repeats the top-level items; the sub-items of a row are a
 * list (source menu.children, widgets_lists_menu.php), nested for deeper
 * levels.
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
 * Give every link whose address is a menu item's the item's window: its
 * target prop, unless the designer set one. The token is a row value of
 * the main loop and of the sub-item lists alike.
 *
 * @param array $node a widget tree node, changed in place
 */
function pg_mw_stamp_targets(&$node)
{
    if (!is_array($node)) return;
    if (isset($node['props']['_bindings']['href']) && $node['props']['_bindings']['href'] === '__menu_item_url'
        && empty($node['props']['target'])) {
        $node['props']['target'] = '^^__menu_item_target^^';
    }
    if (!empty($node['children']) && is_array($node['children'])) {
        foreach ($node['children'] as &$child) pg_mw_stamp_targets($child);
        unset($child);
    }
}

/**
 * Whether the user may edit the items of menu $menu_id, as the classic
 * region's edit button asks: a manager or above, or a user granted the
 * menu.
 *
 * @param int $menu_id
 * @return bool
 */
function pg_mw_can_edit_menu($menu_id)
{
    if (!pg_sw_edit_chips_on()) return false;
    if (defined('USER_ROLE') && (int)USER_ROLE < 3) return true;
    return defined('USER_ID') && in_array((string)(int)$menu_id, array_map('strval', (array)get_items_user_can_edit('menus', USER_ID)), true);
}

// ============================================================================
// menu_region
// ============================================================================

// Render a 'menu_region' system widget.
//
// loop_area: one row per top-level item the visitor may see. Row tokens
// (the main loop and the menu.children lists alike, pg_sw_list_menu_rows()):
// ^^__menu_item_name^^, ^^__menu_item_id^^, ^^__menu_item_url^^,
// ^^__menu_item_target^^ (stamped on the links of an item's address),
// ^^__menu_item_has_children^^, ^^__menu_item_is_current^^,
// ^^__menu_item_holds_current^^ ('1' or empty, for conditions).
// Tokens: ^^__menu_name^^; ^^__menu_item_id^^ reads 0 outside the main
// loop, so a list there lists the top level.
// Flags: has_menu_items, no_menu_items. Messages: 'menu_region' (nothing
// posts to it; a header block must not take the page's alerts).
// cfg: menu_name (the menu, by its name as the classic region names it).
function _render_system_widget_menu_region($tree_json, $widget_id, $cfg = array(), $mode = 'preview')
{
    static $placement = 0;
    $widget_id = (int)$widget_id;
    if ($tree_json === '' || $tree_json === null) return '';
    if (!is_array($cfg)) $cfg = array();
    $tree = json_decode($tree_json, true);
    if (!is_array($tree)) return '';

    $menu_name = isset($cfg['menu_name']) && is_string($cfg['menu_name']) ? trim($cfg['menu_name']) : '';
    $menu = ($menu_name !== '') ? db_item("SELECT id, name FROM menus WHERE name = '" . e($menu_name) . "'") : null;
    $menu_id = is_array($menu) ? (int)$menu['id'] : 0;

    $by_parent = array();
    if ($menu_id > 0) {
        $menu_tree = pg_menu_tree($menu_id);
        $items = array();
        foreach ((array)$menu_tree['children'] as $level) {
            foreach ($level as $item) $items[] = $item;
        }
        $by_parent = pg_sw_list_menu_rows($items, _pg_sa_page_id(),
            function ($folder_id) { return check_view_access($folder_id) == true; },
            defined('FRONTEND_LANGUAGE') ? function ($name) { return pg_tr_text($name, 'text'); } : null);
    }
    $top = isset($by_parent[0]) ? $by_parent[0] : array();

    _eo_apply_visibility_bindings($tree, array('has_menu_items' => (bool)$top, 'no_menu_items' => !$top));
    pg_mw_stamp_targets($tree);
    _pg_inject_messages_node($tree, 'menu_region');

    $split = _split_widget_tree($tree);
    $loop_template = '';
    if ($split['loop_children'] === null) {
        $static_html = trim(_render_tree_node($tree, 0, 0));
    } else {
        foreach ($split['loop_children'] as $child) $loop_template .= _render_tree_node($child, 0, 0);
        $loop_template = trim($loop_template);
        $static_html = trim(_render_tree_node($split['static_tree'], 0, 0));
    }
    // The sub-item lists' rows, filed once the tree is drawn: only a widget
    // that drew such a list keeps them.
    pg_sw_list_publish('menu', array('menu.children' => $by_parent));

    $loop_rendered = '';
    if ($loop_template !== '') {
        foreach ($top as $row) {
            $row_html = pg_sw_sweep_tokens(_pg_sw_fill_tokens($loop_template, pg_sw_escape_token_values($row)));
            $loop_rendered .= pg_sw_uniquify_row_ids($row_html, $widget_id, (int)$row['__menu_item_id']);
        }
    }

    $static_values = pg_sw_escape_token_values(array(
        '__menu_name'    => is_array($menu) ? (string)$menu['name'] : '',
        // The key a sub-item list reads outside the main loop: the top level.
        '__menu_item_id' => '0',
    ));
    // A header and an offcanvas menu may both carry it.
    $static_html = pg_sw_uniquify_row_ids(pg_sw_sweep_tokens(_pg_sw_fill_tokens($static_html, $static_values)), $widget_id, 1000 + (++$placement));
    $html = str_replace(' target=""', '', str_replace('<!--pg-loop-slot-->', $loop_rendered, $static_html));

    if ($menu_id > 0 && $mode !== 'background' && pg_mw_can_edit_menu($menu_id)) {
        $html = pg_sw_add_edit_chip($html, pg_sw_backend_edit_url('view_menu_items.php', $menu_id, array('from' => 'pages')),
            lang('Menu') . ': ' . (string)$menu['name'], 'page');
    }
    return $html;
}
