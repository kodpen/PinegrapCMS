<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Front-end translation - extraction: the texts a page draws, read out of its
 * layout tree and written to the store as segments.
 *
 * The same walk serves the page body, a shared component and a system
 * widget's tree. What counts as a text, and in which format, is decided once
 * here (pg_tr_node_fields()) and read by the renderer too, so the hash the
 * renderer looks up is the hash the extraction wrote.
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
 * The texts of one node: which props carry words, and in which format.
 *
 * A content node's text is inline markup the renderer emits raw; everything
 * else is plain text the renderer escapes. A prop that is bound to data is
 * not a text, it is a sample the binding replaces. Code samples, scripts and
 * form values are left alone: a <pre> holds code, an <option>'s value is what
 * the form posts.
 *
 * @param string $type  node type
 * @param array  $props
 * @return array of array(field, format) where field is a prop name or "_attrs:<name>"
 */
function pg_tr_node_fields($type, $props)
{
    $fields = array();
    $bound = (!empty($props['_bindings']) && is_array($props['_bindings'])) ? $props['_bindings'] : array();

    $add = function ($field, $format) use (&$fields, $props, $bound) {
        if (isset($bound[$field]) && ($bound[$field] !== '')) {
            return;
        }

        if (!isset($props[$field]) || !is_string($props[$field]) || (trim($props[$field]) === '')) {
            return;
        }

        $fields[] = array($field, $format);
    };

    switch ($type) {
        case 'content':
            $content_type = isset($props['contentType']) ? (string) $props['contentType'] : '';

            if (in_array($content_type, array('heading', 'paragraph', 'span', 'link', 'text'), true)) {
                $add('text', 'inline');
            } elseif ($content_type === 'image') {
                $add('alt', 'text');
            }
            break;

        case 'semantic':
            $tag = isset($props['tag']) ? strtolower((string) $props['tag']) : 'div';

            if (!in_array($tag, array('pre', 'code', 'kbd', 'samp', 'var', 'script', 'style', 'textarea', 'noscript'), true)) {
                $add('text', 'text');
            }

            if ($tag === 'img') {
                $add('alt', 'text');
            }
            break;

        case 'component':
            $component_type = isset($props['componentType']) ? (string) $props['componentType'] : '';

            switch ($component_type) {
                case 'btn':
                case 'alert_box':
                case 'badge':
                    $add('text', 'text');
                    break;
                case 'hero':
                    $add('title', 'text');
                    $add('subtitle', 'text');
                    $add('btnText', 'text');
                    break;
                case 'card':
                    $add('headerText', 'text');
                    $add('title', 'text');
                    $add('text', 'text');
                    $add('footerText', 'text');
                    break;
                case 'navbar':
                    $add('brand', 'text');
                    break;
            }
            break;
    }

    // Attributes with words in them, on any node. A button's value is its
    // label; an input's value is what the form posts unless the input is a
    // button itself.
    if (!empty($props['_attrs']) && is_array($props['_attrs'])) {
        $tag = isset($props['tag']) ? strtolower((string) $props['tag']) : '';
        $input_type = '';

        foreach ($props['_attrs'] as $attr) {
            if (is_array($attr) && isset($attr['name']) && (strtolower((string) $attr['name']) === 'type')) {
                $input_type = strtolower(trim((string) (isset($attr['value']) ? $attr['value'] : '')));
            }
        }

        $worded = array('title' => 1, 'placeholder' => 1, 'aria-label' => 1, 'data-bs-title' => 1, 'data-bs-content' => 1, 'aria-description' => 1);
        $value_is_label = ($tag === 'button') || (($tag === 'input') && in_array($input_type, array('submit', 'button', 'reset'), true));

        foreach ($props['_attrs'] as $attr) {
            if (!is_array($attr) || empty($attr['name']) || !isset($attr['value']) || !is_string($attr['value'])) {
                continue;
            }

            $name = strtolower((string) $attr['name']);

            if ((isset($worded[$name]) || (($name === 'value') && $value_is_label)) && (trim($attr['value']) !== '')) {
                $fields[] = array('_attrs:' . $name, 'text');
            }
        }
    }

    return $fields;
}

/**
 * Reads a field named by pg_tr_node_fields() from a props array.
 */
function pg_tr_field_get($props, $field)
{
    if (strpos($field, '_attrs:') === 0) {
        $name = substr($field, 7);

        if (!empty($props['_attrs']) && is_array($props['_attrs'])) {
            foreach ($props['_attrs'] as $attr) {
                if (is_array($attr) && isset($attr['name']) && (strtolower((string) $attr['name']) === $name)) {
                    return isset($attr['value']) ? (string) $attr['value'] : '';
                }
            }
        }

        return '';
    }

    return isset($props[$field]) ? (string) $props[$field] : '';
}

/**
 * Writes a field named by pg_tr_node_fields() into a props array.
 */
function pg_tr_field_set(&$props, $field, $value)
{
    if (strpos($field, '_attrs:') === 0) {
        $name = substr($field, 7);

        if (!empty($props['_attrs']) && is_array($props['_attrs'])) {
            foreach ($props['_attrs'] as $i => $attr) {
                if (is_array($attr) && isset($attr['name']) && (strtolower((string) $attr['name']) === $name)) {
                    $props['_attrs'][$i]['value'] = $value;
                }
            }
        }

        return;
    }

    $props[$field] = $value;
}

/**
 * Walks a tree and collects its texts.
 *
 * @param array  $node
 * @param array  $segments  out: array(hash, text, format, node_id, field, position)
 * @param array  $refs      out: array('shared' => array(id => true), 'menu' => array(name => true))
 * @param int    $position  running counter
 * @param string $path      the node's place, for a node without an id
 */
function pg_tr_walk_tree($node, &$segments, &$refs, &$position, $path = '0')
{
    if (!is_array($node) || !isset($node['type'])) {
        return;
    }

    $type = (string) $node['type'];
    $props = (isset($node['props']) && is_array($node['props'])) ? $node['props'] : array();
    $node_id = (isset($node['_id']) && is_string($node['_id']) && preg_match('/^[A-Za-z0-9_-]{1,64}$/', $node['_id'])) ? $node['_id'] : ('p' . $path);

    if ($type === 'shared_ref') {
        $shared_id = isset($props['sharedId']) ? (int) $props['sharedId'] : 0;

        if ($shared_id > 0) {
            $refs['shared'][$shared_id] = true;
        }
    } elseif ($type === 'region') {
        $region_type = isset($props['regionType']) ? (string) $props['regionType'] : '';
        $region_name = isset($props['regionName']) ? trim((string) $props['regionName']) : '';

        if (in_array($region_type, array('menu', 'menu_sequence'), true) && ($region_name !== '')) {
            $refs['menu'][$region_name] = true;
        }
    } elseif (($type === 'content') && isset($props['contentType']) && ($props['contentType'] === 'custom_html') && isset($props['html']) && is_string($props['html'])) {
        // A custom HTML block: its leaf blocks, drawn by the finished-page
        // pass (includes/translate/content.php).
        $segments = array_merge($segments, pg_tr_custom_html_segments($props['html'], $node_id, $position));
    } else {
        foreach (pg_tr_node_fields($type, $props) as $spec) {
            list($field, $format) = $spec;
            $raw = pg_tr_field_get($props, $field);
            $normalized = pg_tr_normalize($raw, $format);

            if (($normalized === '') || !pg_tr_translatable($normalized)) {
                continue;
            }

            $segments[] = array(
                'hash'     => pg_tr_hash($normalized, $format),
                'text'     => $normalized,
                'format'   => $format,
                'node_id'  => $node_id,
                'field'    => $field,
                'position' => $position++,
            );
        }
    }

    if (!empty($node['children']) && is_array($node['children'])) {
        foreach ($node['children'] as $i => $child) {
            pg_tr_walk_tree($child, $segments, $refs, $position, $path . '.' . $i);
        }
    }
}

/**
 * The texts of one node as extraction stores them, its children left out:
 * what the visual editor lists for the selected element. The node is the
 * editor's own, so it may hold wording that is not saved yet.
 *
 * @param array $node array('type' => ..., 'props' => array(...))
 * @return array of array(hash, text, format, field)
 */
function pg_tr_node_segments($node)
{
    if (!is_array($node) || !isset($node['type']) || !is_string($node['type'])) {
        return array();
    }

    $type = $node['type'];
    $props = (isset($node['props']) && is_array($node['props'])) ? $node['props'] : array();
    $position = 0;

    if (($type === 'content') && isset($props['contentType']) && ($props['contentType'] === 'custom_html') && isset($props['html']) && is_string($props['html'])) {
        return pg_tr_custom_html_segments($props['html'], 'node', $position);
    }

    $segments = array();

    foreach (pg_tr_node_fields($type, $props) as $spec) {
        list($field, $format) = $spec;
        $normalized = pg_tr_normalize(pg_tr_field_get($props, $field), $format);

        if (($normalized === '') || !pg_tr_translatable($normalized)) {
            continue;
        }

        $segments[] = array(
            'hash'   => pg_tr_hash($normalized, $format),
            'text'   => $normalized,
            'format' => $format,
            'field'  => $field,
        );
    }

    return $segments;
}

/**
 * Writes a set of walked segments for an owner: the strings first, then the
 * uses. Returns the string ids in order.
 *
 * @param string $owner_type
 * @param int    $owner_id
 * @param array  $segments  from pg_tr_walk_tree() (or shaped like it)
 * @param string $kind      content | seo | ui | option
 * @return array string ids
 */
function pg_tr_store_segments($owner_type, $owner_id, $segments, $kind = 'content')
{
    $uses = array();
    $ids = array();
    $by_hash = array();

    foreach ($segments as $segment) {
        if (!isset($by_hash[$segment['hash']])) {
            $by_hash[$segment['hash']] = pg_tr_string_upsert($segment['text'], $segment['format'], $kind);
        }

        $string_id = $by_hash[$segment['hash']];
        $ids[] = $string_id;
        $uses[] = array(
            'string_id' => $string_id,
            'node_id'   => $segment['node_id'],
            'field'     => $segment['field'],
            'position'  => $segment['position'],
        );
    }

    pg_tr_uses_rewrite($owner_type, $owner_id, $uses);

    return array_values(array_unique($ids));
}

/**
 * Extracts one page: its tree, its title and description, and - through the
 * references the tree holds - the shared components and menus it shows.
 *
 * @param int $page_id
 * @return array('ok' => bool, 'segments' => int, 'shared' => int, 'menus' => int)
 */
function pg_tr_extract_page($page_id)
{
    $page_id = (int) $page_id;
    $result = array('ok' => false, 'segments' => 0, 'shared' => 0, 'menus' => 0);

    $page = db_item("SELECT page_id, page_name, page_title, page_meta_description FROM page WHERE page_id = '$page_id' LIMIT 1");

    if (!is_array($page)) {
        return $result;
    }

    $tree_json = pg_page_tree_json($page_id);
    $tree = ($tree_json !== '') ? json_decode($tree_json, true) : null;

    if (!is_array($tree)) {
        return $result;
    }

    $segments = array();
    $refs = array('shared' => array(), 'menu' => array());
    $position = 0;

    pg_tr_walk_tree($tree, $segments, $refs, $position);
    pg_tr_store_segments('page', $page_id, $segments);
    $result['segments'] = count($segments);

    // Title and description: plain text, their own kind so the SEO fields can
    // be listed apart from the body.
    $seo = array();
    $seo_position = 0;

    foreach (array('page_title', 'page_meta_description') as $field) {
        $normalized = pg_tr_normalize((string) $page[$field], 'text');

        if (($normalized !== '') && pg_tr_translatable($normalized)) {
            $seo[] = array(
                'hash'     => pg_tr_hash($normalized, 'text'),
                'text'     => $normalized,
                'format'   => 'text',
                'node_id'  => 'page',
                'field'    => $field,
                'position' => $seo_position++,
            );
        }
    }

    pg_tr_store_segments('page_seo', $page_id, $seo, 'seo');
    $result['segments'] += count($seo);

    // What the page references, so "update this page" can reach the texts
    // drawn into it from elsewhere.
    $ref_rows = array();

    foreach (array_keys($refs['shared']) as $shared_id) {
        $extracted = pg_tr_extract_shared($shared_id);

        if ($extracted['ok']) {
            $result['shared']++;
            $ref_rows[] = array('string_id' => 0, 'node_id' => 'shared', 'field' => (string) $shared_id, 'position' => 0);
        }
    }

    foreach (array_keys($refs['menu']) as $menu_name) {
        $menu_id = (int) db_value("SELECT id FROM menus WHERE name = '" . e($menu_name) . "' LIMIT 1");

        if ($menu_id > 0) {
            pg_tr_extract_menu($menu_id);
            $result['menus']++;
            $ref_rows[] = array('string_id' => 0, 'node_id' => 'menu', 'field' => (string) $menu_id, 'position' => 0);
        }
    }

    pg_tr_uses_rewrite('page_ref', $page_id, $ref_rows);

    $result['ok'] = true;

    return $result;
}

/**
 * Extracts a shared component (or a system widget) from its stored tree.
 *
 * @param int $shared_id
 * @return array('ok' => bool, 'segments' => int)
 */
function pg_tr_extract_shared($shared_id)
{
    $shared_id = (int) $shared_id;
    $result = array('ok' => false, 'segments' => 0);

    $row = db_item("SELECT tree_json, system_region_config FROM shared_components WHERE id = '$shared_id' LIMIT 1");
    $tree_json = is_array($row) ? $row['tree_json'] : null;
    $tree = ($tree_json !== null && $tree_json !== '') ? json_decode((string) $tree_json, true) : null;

    if (!is_array($tree)) {
        return $result;
    }

    $segments = array();
    $refs = array('shared' => array(), 'menu' => array());
    $position = 0;

    pg_tr_walk_tree($tree, $segments, $refs, $position);

    // A system widget's settings: the messages and labels it prints.
    $segments = array_merge($segments, pg_tr_widget_config_segments($row['system_region_config'], $position));

    pg_tr_store_segments('shared', $shared_id, $segments);

    $result['ok'] = true;
    $result['segments'] = count($segments);

    return $result;
}

/**
 * Extracts the item names of a menu. One owner per menu; the item id is the
 * node.
 *
 * @param int $menu_id
 * @return int segments
 */
function pg_tr_extract_menu($menu_id)
{
    $menu_id = (int) $menu_id;
    $rows = db_items("SELECT id, name FROM menu_items WHERE menu_id = '$menu_id' ORDER BY parent_id, sort_order");
    $segments = array();
    $position = 0;

    if (is_array($rows)) {
        foreach ($rows as $row) {
            $normalized = pg_tr_normalize((string) $row['name'], 'text');

            if (($normalized === '') || !pg_tr_translatable($normalized)) {
                continue;
            }

            $segments[] = array(
                'hash'     => pg_tr_hash($normalized, 'text'),
                'text'     => $normalized,
                'format'   => 'text',
                'node_id'  => (string) $row['id'],
                'field'    => 'name',
                'position' => $position++,
            );
        }
    }

    pg_tr_store_segments('menu', $menu_id, $segments);

    return count($segments);
}

/**
 * The pages that can be translated: pages of a visual design that have a
 * tree, outside the recycle bin.
 *
 * @return array rows (page_id, page_name, page_title, page_home, page_timestamp)
 */
function pg_tr_translatable_pages()
{
    if (!pg_multi_page_design_ready()) {
        return array();
    }

    $rows = db_items("SELECT page.page_id, page.page_name, page.page_title, page.page_home, page.page_timestamp, page.noindex
                      FROM page
                      INNER JOIN style ON page.page_style = style.style_id
                      WHERE COALESCE(NULLIF(page.page_tree_json, ''), style.style_tree_json) IS NOT NULL
                        AND COALESCE(NULLIF(page.page_tree_json, ''), style.style_tree_json) != ''"
                      . pg_designer_not_binned_sql() . "
                      ORDER BY page.page_home DESC, page.page_name");

    return is_array($rows) ? $rows : array();
}

/**
 * Extracts every translatable page.
 *
 * @return array('pages' => int, 'segments' => int)
 */
function pg_tr_extract_all()
{
    $summary = array('pages' => 0, 'segments' => 0);

    foreach (pg_tr_translatable_pages() as $page) {
        $result = pg_tr_extract_page($page['page_id']);

        if ($result['ok']) {
            $summary['pages']++;
            $summary['segments'] += $result['segments'];
        }
    }

    // What the pages draw from the database: the catalog and the forms.
    foreach (array('catalog', 'forms') as $group) {
        $result = pg_tr_extract_group($group);
        $summary['segments'] += $result['segments'];
    }

    return $summary;
}

/**
 * Extracts one of the groups the Translations screen lists below the pages.
 * The interface texts are recorded as pages draw them and have nothing to
 * extract.
 *
 * @param string $group catalog | forms | ui
 * @return array('owners' => int, 'segments' => int)
 */
function pg_tr_extract_group($group)
{
    switch ($group) {
        case 'catalog':
            return pg_tr_extract_catalog();
        case 'forms':
            return pg_tr_extract_forms();
    }

    return array('owners' => 0, 'segments' => 0);
}
