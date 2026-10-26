<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

// A module of functions.php: the menu source of the lists inside a record
// (widgets_lists.php), drawn in the menu widget (widgets_menu.php).
//
// Loaded by functions.php through require_once, never on its own.
//
// A menu's items are kept in the menu screen (menu_items, edited by the
// users of users_menus_xref); the widget only draws them. Its main loop
// repeats the top-level items; a list of the sub-items of a menu item,
// keyed by ^^__menu_item_id^^, repeats the items under the row it stands
// in, and a list inside that list the level below. Outside the main loop
// the key reads 0: the top-level items. The rows are built here from the
// menu's rows (pg_menu_tree()), so the row shape is tested without a
// database.
if (!defined('PG_FUNCTIONS_DIR')) {
    exit;
}

// The menu source, in the shape of pg_sw_list_sources(). The name comes
// first: the A to Z order compares a row's first value.
function pg_sw_list_menu_sources()
{
    return array(
        'menu.children' => array(
            'family'       => 'menu',
            'key'          => '__menu_item_id',
            'label'        => lang('Sub-items of a menu item'),
            'arg'          => '',
            'arg_required' => false,
            'tokens'       => array(
                array('__menu_item_name', lang('Menu item name')),
                array('__menu_item_id', lang('Menu item ID')),
                array('__menu_item_url', lang('Menu item address')),
                array('__menu_item_target', lang('Menu item window (_blank or empty)')),
                array('__menu_item_has_children', lang('Has sub-items (1 or empty)')),
                array('__menu_item_is_current', lang('Links to the page shown (1 or empty)')),
                array('__menu_item_holds_current', lang('Holds the item of the page shown (1 or empty)')),
            ),
        ),
    );
}

// The rows of a menu, by the id of the item they sit under (0: the top
// level), in the menu's order: what the menu widget's main loop and its
// lists draw. $items: the menu's rows as pg_menu_tree() reads them (id,
// parent_id, name, link_page_id, link_page_name, link_page_folder_id,
// link_url, link_target, security), in sort order. As the classic menu
// draws them: an item with security on that links to a page the visitor
// may not view is left out with everything under it ($can_view(folder id),
// null: every page may be viewed); the address is the page's, else the
// URL, else '#'; a new window only for an item that links somewhere. The
// item of the page shown is the first by id that links to $page_id; the
// items above it hold it. $name_of translates a name (null: as stored).
// Raw values: the renderer and the list pass escape them once.
function pg_sw_list_menu_rows($items, $page_id, $can_view = null, $name_of = null)
{
    $base      = defined('OUTPUT_PATH') ? OUTPUT_PATH : '/';
    $page_id   = (int)$page_id;
    $children  = array();
    $parent_of = array();
    $current   = 0;
    foreach (is_array($items) ? $items : array() as $item) {
        if (!is_array($item) || empty($item['id'])) continue;
        $id = (int)$item['id'];
        $parent_of[$id] = isset($item['parent_id']) ? (int)$item['parent_id'] : 0;
        $children[$parent_of[$id]][] = $item;
        if ($page_id > 0 && isset($item['link_page_id']) && (int)$item['link_page_id'] === $page_id
            && ($current === 0 || $id < $current)) {
            $current = $id;
        }
    }

    // The items above the current one; a loop in the stored parents ends
    // the walk.
    $holds = array();
    $at = $current;
    while ($at > 0 && isset($parent_of[$at]) && $parent_of[$at] > 0 && !isset($holds[$parent_of[$at]])) {
        $at = $parent_of[$at];
        $holds[$at] = true;
    }

    $out  = array();
    $seen = array();
    $walk = function ($parent) use (&$walk, &$out, &$seen, $children, $can_view, $name_of, $current, $holds, $base) {
        if (empty($children[$parent])) return;
        foreach ($children[$parent] as $item) {
            $id = (int)$item['id'];
            if (isset($seen[$id])) continue;
            $seen[$id] = true;
            $page = isset($item['link_page_name']) ? (string)$item['link_page_name'] : '';
            if (!empty($item['security']) && $page !== '' && $can_view !== null
                && !call_user_func($can_view, isset($item['link_page_folder_id']) ? $item['link_page_folder_id'] : 0)) {
                continue;
            }
            $walk($id);
            $url  = isset($item['link_url']) ? (string)$item['link_url'] : '';
            $href = ($page !== '') ? $base . $page : (($url !== '') ? $url : '');
            $name = isset($item['name']) ? (string)$item['name'] : '';
            if ($name_of !== null) $name = (string)call_user_func($name_of, $name);
            $out[$parent][] = array(
                '__menu_item_name'          => $name,
                '__menu_item_id'            => (string)$id,
                '__menu_item_url'           => ($href !== '') ? $href : '#',
                '__menu_item_target'        => ($href !== '' && isset($item['link_target']) && $item['link_target'] === 'New Window') ? '_blank' : '',
                '__menu_item_has_children'  => !empty($out[$id]) ? '1' : '',
                '__menu_item_is_current'    => ($id === $current) ? '1' : '',
                '__menu_item_holds_current' => isset($holds[$id]) ? '1' : '',
            );
        }
    };
    $walk(0);
    return $out;
}
