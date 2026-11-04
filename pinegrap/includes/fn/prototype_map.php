<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * The server read behind the visual designer's Prototype view: what the
 * links of a design lead to. The editor sends the hrefs its trees hold and
 * the names of the classic menus and common or designer regions its trees
 * place; the answer carries the site values and name maps the browser twin
 * of the link resolver (assets/js/pg_link_resolve.js) reads them with, the
 * draft and Recycle Bin folders, each menu's items and each region's links.
 *
 * A content-level operator is told nothing about a page of a folder hidden
 * from them (pg_designer_page_access() 'hidden'): such a page is left out of
 * the maps, the item names that reach it come back in `hidden` (names the
 * editor sent, so nothing new is told), and a menu item that opens it loses
 * its page name. The read writes nothing.
 *
 * Reached through api.php, action designer, sub_action prototype_map
 * (includes/panel/designer.php), which loads it with includes/fn/prototype.php
 * (pg_proto_html_hrefs()) and includes/designer_access.php.
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
 * The most a request may ask about: hrefs, menu names, region names; and
 * the longest href or name read.
 *
 * @return array hrefs, menus, regions, length
 */
function pg_proto_map_limits()
{
    return array('hrefs' => 2000, 'menus' => 50, 'regions' => 100, 'length' => 2048);
}

/**
 * A list of strings from the request: strings only, trimmed of nothing,
 * each kept once, empty ones and over-long ones left out, at most $max.
 *
 * @param mixed $value
 * @param int   $max
 * @param int   $length
 * @return string[]
 */
function pg_proto_map_strings($value, $max, $length)
{
    $out = array();

    foreach ((is_array($value) ? $value : array()) as $item) {
        if (!is_string($item) || ($item === '') || (strlen($item) > $length) || isset($out[$item])) {
            continue;
        }

        $out[$item] = true;

        if (count($out) >= $max) {
            break;
        }
    }

    return array_map('strval', array_keys($out));
}

/**
 * A list of page ids from the request: whole numbers above 0 (an int or
 * digits), each kept once, at most $max.
 *
 * @param mixed $value
 * @param int   $max
 * @return int[]
 */
function pg_proto_map_ids($value, $max)
{
    $out = array();

    foreach ((is_array($value) ? $value : array()) as $item) {
        if (is_string($item) && preg_match('/^[1-9][0-9]{0,9}$/', $item)) {
            $item = (int) $item;
        }

        if (!is_int($item) || ($item <= 0) || isset($out[$item])) {
            continue;
        }

        $out[$item] = true;

        if (count($out) >= $max) {
            break;
        }
    }

    return array_keys($out);
}

/**
 * What a request asks about.
 *
 * @param array $request hrefs, menus, regions (lists of strings), page_ids
 *                       (the pages links kept by page id open)
 * @return array hrefs, menus, regions, page_ids
 */
function pg_proto_map_request($request)
{
    $limits = pg_proto_map_limits();
    $request = is_array($request) ? $request : array();

    return array(
        'hrefs' => pg_proto_map_strings(isset($request['hrefs']) ? $request['hrefs'] : null, $limits['hrefs'], $limits['length']),
        'menus' => pg_proto_map_strings(isset($request['menus']) ? $request['menus'] : null, $limits['menus'], $limits['length']),
        'regions' => pg_proto_map_strings(isset($request['regions']) ? $request['regions'] : null, $limits['regions'], $limits['length']),
        'page_ids' => pg_proto_map_ids(isset($request['page_ids']) ? $request['page_ids'] : null, $limits['hrefs']),
    );
}

/**
 * The ids asked for whose page is left out of the visible maps: the
 * operator may not know about it. Ids of no page are not listed (broken).
 *
 * @param int[] $ids   page ids asked for
 * @param array $names page id => page name, of the pages that exist
 * @param array $maps  pg_proto_map_visible() maps
 * @return int[]
 */
function pg_proto_map_hidden_ids($ids, $names, $maps)
{
    $out = array();

    foreach ((array) $ids as $id) {
        $id = (int) $id;

        if (isset($names[$id]) && !isset($maps['pages'][(string) $names[$id]])) {
            $out[] = $id;
        }
    }

    return $out;
}

/**
 * The items of the hrefs that name something of this site (pg_link_parse()),
 * each once, in first-seen order.
 *
 * @param string[] $hrefs
 * @param array    $env pg_link_resolve_env()
 * @return string[]
 */
function pg_proto_map_items($hrefs, $env)
{
    $items = array();

    foreach ((array) $hrefs as $href) {
        $parsed = pg_link_parse($href, $env);

        if ($parsed['result'] === null) {
            $items[(string) $parsed['item']] = true;
        }
    }

    return array_map('strval', array_keys($items));
}

/**
 * A menu's items in printing order (pg_menu_tree()), each with the href it
 * opens: its page's address when it links a page, else its own URL. A page
 * the operator may not know about gives no address (hidden).
 *
 * @param array    $tree   pg_menu_tree(): children (parent id => rows)
 * @param array    $env    pg_link_resolve_env()
 * @param callable $hidden page id, folder id => whether the page is hidden
 * @return array[] id, parent, name, href, target, page_id, hidden
 */
function pg_proto_map_menu_links($tree, $env, $hidden)
{
    $children = (is_array($tree) && isset($tree['children'])) ? $tree['children'] : array();
    $out = array();
    $path = isset($env['path']) ? (string) $env['path'] : '/';
    $walk = function ($parent) use (&$walk, &$out, $children, $path, $hidden) {
        foreach ((isset($children[$parent]) ? $children[$parent] : array()) as $row) {
            $page_id = (int) $row['link_page_id'];
            $href = '';
            $is_hidden = false;

            if ($page_id > 0) {
                $is_hidden = (bool) call_user_func($hidden, $page_id, (int) $row['link_page_folder_id']);
                $name = (string) $row['link_page_name'];
                $href = ($is_hidden || ($name === '')) ? '' : $path . $name;
            } else {
                $href = (string) $row['link_url'];
            }

            $out[] = array(
                'id' => (int) $row['id'],
                'parent' => (int) $row['parent_id'],
                'name' => (string) $row['name'],
                'href' => $href,
                'target' => (string) $row['link_target'],
                'page_id' => $is_hidden ? 0 : $page_id,
                'hidden' => $is_hidden,
            );

            $walk((int) $row['id']);
        }
    };

    $walk(0);

    return $out;
}

/**
 * The maps without the pages the operator may not know about, and the item
 * names that reach one of them: the name itself, or a pretty address whose
 * page (before the slash) is hidden.
 *
 * @param array    $maps   pg_link_maps_for_items() (pages: name => id, folder)
 * @param string[] $items  the item names asked about
 * @param callable $hidden page id, folder id => whether the page is hidden
 * @return array maps (folded again), hidden (item names)
 */
function pg_proto_map_visible($maps, $items, $hidden)
{
    $gone = array();

    foreach ((isset($maps['pages']) ? $maps['pages'] : array()) as $name => $page) {
        if (call_user_func($hidden, (int) $page['id'], (int) $page['folder'])) {
            $gone[(string) $name] = true;
            unset($maps['pages'][$name]);
        }
    }

    if (!$gone) {
        return array('maps' => $maps, 'hidden' => array());
    }

    // The folded index still names the pages left out.
    unset($maps['folded']);

    $folded = array();

    foreach (array_keys($gone) as $name) {
        $folded[mb_strtolower($name, 'UTF-8')] = true;
    }

    $hidden_items = array();

    foreach ((array) $items as $item) {
        $item = (string) $item;
        $prefix = (mb_strpos($item, '/') !== false) ? mb_substr($item, 0, mb_strpos($item, '/')) : $item;

        if (isset($folded[mb_strtolower($prefix, 'UTF-8')]) && (pg_link_map_key($maps, 'pages', $prefix) === null)) {
            $hidden_items[] = $item;
        }
    }

    return array('maps' => pg_link_maps_fold($maps), 'hidden' => $hidden_items);
}

/**
 * The links of the common and designer regions asked for, by the name the
 * tree writes: kind (common, designer, or '' for none by that name) and the
 * hrefs of its content (pg_proto_html_hrefs()).
 *
 * @param array $regions pg_common_regions(): name => row or null
 * @return array name => kind, hrefs
 */
function pg_proto_map_region_links($regions)
{
    $out = array();

    foreach ((array) $regions as $name => $row) {
        if (!is_array($row)) {
            $out[(string) $name] = array('kind' => '', 'hrefs' => array());
            continue;
        }

        $out[(string) $name] = array(
            'kind' => (((string) $row['cregion_designer_type']) === 'yes') ? 'designer' : 'common',
            'hrefs' => array_values(array_unique(pg_proto_html_hrefs((string) $row['cregion_content']))),
        );
    }

    return $out;
}

/**
 * The answer of the read for one operator.
 *
 * @param array $request hrefs, menus, regions, page_ids
 * @param array $user    validate_user()
 * @return array status, env, maps, hidden, hidden_ids, draft_folder, bin_folders,
 *               menus (name => links), regions (name => kind, hrefs)
 */
function pg_proto_map($request, $user)
{
    $asked = pg_proto_map_request($request);
    $env = pg_link_resolve_env();
    $full = pg_designer_is_full($user);
    $seen = array();
    $hidden = function ($page_id, $folder_id) use ($full, $user, &$seen) {
        if ($full) {
            return false;
        }

        if (!isset($seen[$page_id])) {
            $seen[$page_id] = (pg_designer_page_access(array('page_id' => $page_id, 'page_folder' => $folder_id), $user) === 'hidden');
        }

        return $seen[$page_id];
    };

    $menus = array();

    foreach ($asked['menus'] as $name) {
        $menu_id = (int) db_value("SELECT id FROM menus WHERE name = '" . e($name) . "' LIMIT 1");
        $menus[$name] = ($menu_id > 0) ? pg_proto_map_menu_links(pg_menu_tree($menu_id), $env, $hidden) : null;
    }

    $regions = pg_proto_map_region_links($asked['regions'] ? pg_common_regions($asked['regions']) : array());

    $hrefs = $asked['hrefs'];

    foreach ($menus as $links) {
        foreach ((array) $links as $link) {
            $hrefs[] = $link['href'];
        }
    }

    foreach ($regions as $region) {
        $hrefs = array_merge($hrefs, $region['hrefs']);
    }

    $items = pg_proto_map_items($hrefs, $env);

    // The pages links kept by page id open are read by their current name,
    // through the same maps; a hidden one is answered by its id alone (its
    // name is not one the editor sent, so it stays out of `hidden`).
    $names = array();

    if ($asked['page_ids']) {
        foreach (db_items("SELECT page_id, page_name FROM page WHERE page_id IN (" . implode(',', array_map('intval', $asked['page_ids'])) . ")") as $row) {
            $names[(int) $row['page_id']] = (string) $row['page_name'];
        }
    }

    $maps = pg_link_maps_for_items(array_values(array_unique(array_merge($items, array_values($names)))));
    $forms = isset($maps['form_names']) ? $maps['form_names'] : array();
    unset($maps['form_names']);
    $visible = pg_proto_map_visible($maps, $items, $hidden);

    return array(
        'status' => 'success',
        'env' => $env,
        'maps' => $visible['maps'],
        'form_names' => (object) $forms,
        'hidden' => $visible['hidden'],
        'hidden_ids' => pg_proto_map_hidden_ids($asked['page_ids'], $names, $visible['maps']),
        'draft_folder' => (int) pg_page_draft_folder_id(),
        'bin_folders' => array_values(array_map('intval', pg_recycle_bin_folder_ids())),
        'menus' => (object) $menus,
        'regions' => (object) $regions,
    );
}
