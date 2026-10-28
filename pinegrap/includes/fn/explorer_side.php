<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

// A module of functions.php: the File Manager's and the Catalog Manager's tree in their main menu panels (explorer_side.js, the explorer_side action).
//
// Loaded by functions.php through require_once, never on its own. The tree
// data is answered by includes/panel/explorer_side.php; what is here decides
// who gets which part and draws the box the script fills.
if (!defined('PG_FUNCTIONS_DIR')) {
    exit;
}

/**
 * Whether a person may read an area's tree in the menu: exactly the people
 * its menu row and its screen admit. The files: role < 3, or a role 3 user
 * with an edit right on a folder (validate_area_access($user, 'user')). The
 * catalog: the store switched on and the commerce right (pg_catalog_access(),
 * the menu row's ECOMMERCE check).
 *
 * @param string $area             files or catalog
 * @param int    $role             0-3
 * @param bool   $manage_ecommerce the commerce right
 * @param bool   $has_edit_acl     no_acl_check() for a role 3 user
 * @param bool   $ecommerce_on     ECOMMERCE === true
 * @return bool
 */
function pg_explorer_side_allowed($area, $role, $manage_ecommerce, $has_edit_acl, $ecommerce_on)
{
    $role = (int) $role;

    if ($area === 'files') {
        return ($role < 3) || ($has_edit_acl === true);
    }

    if ($area === 'catalog') {
        return ($ecommerce_on === true) && (($role < 3) || ($manage_ecommerce === true));
    }

    return false;
}

/**
 * The branches a request asks for: whole numbers of 0 or more (0 is the top
 * of the tree), each once, at most $limit of them.
 *
 * @param mixed $raw   the request's node_ids
 * @param int   $limit
 * @return int[]
 */
function pg_explorer_side_ids($raw, $limit = 25)
{
    if (!is_array($raw)) {
        $raw = array($raw);
    }

    $ids = array();

    foreach ($raw as $value) {
        if (is_int($value) || (is_string($value) && ctype_digit($value))) {
            $id = (int) $value;

            if (!in_array($id, $ids, true)) {
                $ids[] = $id;
            }
        }

        if (count($ids) >= $limit) {
            break;
        }
    }

    return $ids;
}

/**
 * The fixed entries above an area's tree, under the gates of the explorer
 * screen that draws the same rows (view_folders.php): Short Links, Shared
 * Folders and Backups for role <= 2, ERP Files where the ERP section is.
 *
 * @param string $area files or catalog
 * @param int    $role 0-3
 * @param bool   $erp  the ERP section of the file manager is drawn
 * @return array of key, label (lang() source), icon, url, section (top,
 *               shared or erp)
 */
function pg_explorer_side_quick($area, $role, $erp)
{
    $role = (int) $role;

    if ($area === 'catalog') {
        return array(
            array('key' => 'products', 'label' => 'All Products', 'icon' => 'bi-box2-heart', 'url' => 'view_product_groups.php?view=products', 'section' => 'top'),
            array('key' => 'variants', 'label' => 'All Variant Sets', 'icon' => 'bi-collection-fill', 'url' => 'view_product_groups.php?view=variants', 'section' => 'top'),
        );
    }

    $quick = array(
        array('key' => 'files', 'label' => 'Files', 'icon' => 'bi-file-earmark-image', 'url' => 'view_folders.php?view=files', 'section' => 'top'),
        array('key' => 'images', 'label' => 'Pictures', 'icon' => 'bi-images', 'url' => 'view_folders.php?view=images', 'section' => 'top'),
        array('key' => 'pages', 'label' => 'Pages', 'icon' => 'bi-window-stack', 'url' => 'view_folders.php?view=pages', 'section' => 'top'),
    );

    if ($role <= 2) {
        $quick[] = array('key' => 'short_links', 'label' => 'Short Links', 'icon' => 'bi-link-45deg', 'url' => 'view_folders.php?view=short_links', 'section' => 'top');
        $quick[] = array('key' => 'shared', 'label' => 'Shared Folders', 'icon' => 'bi-shield-lock', 'url' => 'view_folders.php?view=shared', 'section' => 'shared');
        $quick[] = array('key' => 'backups', 'label' => 'Backups', 'icon' => 'bi-database', 'url' => 'view_folders.php?view=backups', 'section' => 'shared');
    }

    if ($erp === true) {
        $quick[] = array('key' => 'erp', 'label' => 'ERP Files', 'icon' => 'bi-safe2', 'url' => 'view_folders.php?view=erp', 'section' => 'erp');
    }

    return $quick;
}

/**
 * The area whose tree is the screen's own, so the tree is moved into the menu
 * there instead of drawn as links: the file manager, the catalog manager
 * (view_product_groups.php, the same explorer). None in the front-end
 * toolbar's frame.
 *
 * @param string $script  $_SERVER['SCRIPT_NAME']
 * @param bool   $toolbar the menu is the toolbar's
 * @return string files, catalog or ''
 */
function pg_explorer_side_screen($script, $toolbar = false)
{
    if ($toolbar) {
        return '';
    }

    switch (basename((string) $script)) {
        case 'view_folders.php':
            return 'files';

        case 'view_product_groups.php':
            return 'catalog';
    }

    return '';
}

/**
 * The box in an area's menu panel. On the area's own screen it is empty and
 * marked to open by itself: the screen moves its tree into it
 * (view_folders.php). Anywhere else it holds the fixed entries as links and
 * an empty tree the script fills from the explorer_side action when the
 * panel first opens (assets/js/explorer_side.js).
 *
 * @param array $args area, api, quick (pg_explorer_side_quick()), inplace,
 *                    target ('_parent' in the toolbar frame), loading,
 *                    error, retry (translated)
 * @return string
 */
function pg_explorer_side_markup($args)
{
    $area = ($args['area'] === 'catalog') ? 'catalog' : 'files';
    $id = 'explorer-side-' . $area;

    if (!empty($args['inplace'])) {
        return '<div id="' . $id . '" class="pg-explorer-side" data-explorer-side="' . $area . '" data-explorer-side-inplace data-pg-menu-auto></div>';
    }

    $base = isset($args['base']) ? (string) $args['base'] : '';
    $target = (isset($args['target']) && ($args['target'] !== '')) ? ' target="' . h($args['target']) . '"' : '';

    $row = function ($item) use ($base, $target) {
        return '<a class="tree-row" href="' . h($base . $item['url']) . '"' . $target . ' data-pg-menu-skip><span class="tree-toggle"></span><span class="bi ' . h($item['icon']) . ' fg-primary" aria-hidden="true"></span><span class="tree-label">' . h(lang($item['label'])) . '</span></a>';
    };

    $sections = array('top' => '', 'shared' => '', 'erp' => '');

    foreach ($args['quick'] as $item) {
        $sections[$item['section']] .= $row($item);
    }

    $html = '<div id="' . $id . '" class="pg-explorer-side pg-explorer-tree" data-explorer-side="' . $area . '"'
        . ' data-explorer-side-api="' . h($args['api']) . '"'
        . ' data-explorer-side-base="' . h($base) . '"'
        . ' data-explorer-side-loading="' . h($args['loading']) . '"'
        . ' data-explorer-side-error="' . h($args['error']) . '"'
        . ' data-explorer-side-retry="' . h($args['retry']) . '"'
        . (($target !== '') ? ' data-explorer-side-target="' . h($args['target']) . '"' : '')
        . '>';

    $html .= $sections['top'];
    $html .= '<div class="d-none" data-explorer-side-list="pinned"><div class="tree-section-label">' . h(lang('Pinned')) . '</div><div data-explorer-side-items></div></div>';
    $html .= '<div class="d-none" data-explorer-side-list="recent"><div class="tree-section-label">' . h(lang('Recent')) . '</div><div data-explorer-side-items></div></div>';
    $html .= '<div class="tree-section-label">' . h(lang(($area === 'catalog') ? 'Product Groups' : 'Folders')) . '</div>';
    $html .= '<ul data-explorer-side-root><li class="pg-explorer-side-wait">' . h($args['loading']) . '</li></ul>';

    if ($sections['shared'] !== '') {
        $html .= '<div class="tree-section-label">' . h(lang('Shared Items')) . '</div>' . $sections['shared'];
    }

    if ($sections['erp'] !== '') {
        $html .= '<div class="tree-section-label">' . h(lang('ERP')) . '</div>' . $sections['erp'];
    }

    return $html . '</div>';
}

/**
 * One folder as the menu's tree draws it.
 *
 * @param array  $row          a folder table row
 * @param string $access       get_access_control_type(); only a known type is
 *                             passed on, it becomes a class
 * @param bool   $has_children a folder this person sees is under it
 * @return array id, name, access, archived, children
 */
function pg_explorer_side_folder_row($row, $access, $has_children)
{
    return array(
        'id' => (int) $row['folder_id'],
        'name' => (string) $row['folder_name'],
        'access' => in_array($access, array('public', 'private', 'guest', 'registration', 'membership'), true) ? $access : '',
        'archived' => ((string) ($row['folder_archived'] ?? '') === '1'),
        'children' => ($has_children === true));
}

/**
 * One product group as the menu's tree draws it.
 *
 * @param array $row          a product_groups row (pg_catalog_group_map())
 * @param bool  $has_children a live group is under it
 * @return array id, name, variants, enabled, children
 */
function pg_explorer_side_group_row($row, $has_children)
{
    return array(
        'id' => (int) $row['id'],
        'name' => (string) $row['name'],
        'variants' => ((string) ($row['display_type'] ?? '') === 'select'),
        'enabled' => ((string) ($row['enabled'] ?? '') === '1'),
        'children' => ($has_children === true));
}
