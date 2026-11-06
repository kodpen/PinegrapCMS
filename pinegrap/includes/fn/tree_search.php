<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Search over visual designer trees: the texts of a page tree or a shared
 * component tree that site search counts and Find & Replace finds and
 * replaces. A converted classic region lives in such a tree, so the words a
 * region search found before are found here.
 *
 * The texts are the node fields the site translation reads
 * (pg_tr_node_fields()): words a visitor reads, never classes, ids, data
 * bindings, code or a condition's compared value. A replace therefore never
 * changes the structure or the wiring of a tree.
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
 * The searchable fields of one node: pg_tr_node_fields() without the
 * condition operands, which are wiring a replace must not change.
 *
 * @param string $type
 * @param array  $props
 * @return string[] field names for pg_tr_field_get() / pg_tr_field_set()
 */
function pg_tree_search_node_fields($type, $props)
{
    if (!function_exists('pg_tr_node_fields')) {
        require_once(PG_FUNCTIONS_DIR . '/includes/translate/extract.php');
    }

    $fields = array();

    foreach (pg_tr_node_fields((string) $type, is_array($props) ? $props : array()) as $spec) {
        if (strpos($spec[0], '_cond:') === 0) {
            continue;
        }
        $fields[$spec[0]] = $spec[0];
    }

    return array_values($fields);
}

/**
 * Every searchable text of a tree, in document order. A shared_ref node
 * keeps no children in a stored tree; its component is a tree of its own.
 *
 * @param mixed $tree  a decoded tree: one node, or a list of nodes
 * @return array of array('field' => string, 'value' => string)
 */
function pg_tree_search_texts($tree)
{
    $out = array();
    pg_tree_search_walk($tree, function ($field, $value) use (&$out) {
        $out[] = array('field' => $field, 'value' => $value);
        return null;
    });

    return $out;
}

/**
 * The shared component ids a tree places (shared_ref nodes), each once.
 *
 * @param mixed $tree
 * @return int[]
 */
function pg_tree_search_shared_ids($tree)
{
    $ids = array();
    $stack = array($tree);

    while ($stack) {
        $node = array_pop($stack);
        if (!is_array($node)) {
            continue;
        }
        if (!isset($node['type'])) {
            foreach ($node as $child) {
                $stack[] = $child;
            }
            continue;
        }
        if (($node['type'] === 'shared_ref') && isset($node['props']['sharedId'])) {
            $id = (int) $node['props']['sharedId'];
            if ($id > 0) {
                $ids[$id] = $id;
            }
        }
        if (!empty($node['children']) && is_array($node['children'])) {
            foreach ($node['children'] as $child) {
                $stack[] = $child;
            }
        }
    }

    ksort($ids);

    return array_values($ids);
}

/**
 * Replaces in every searchable text of a tree. $replace takes a text and
 * answers array(new text, number of replacements); a field whose count is 0
 * is left as it was.
 *
 * @param mixed    $tree
 * @param callable $replace
 * @return array array(tree, total replacements)
 */
function pg_tree_search_replace($tree, $replace)
{
    $total = 0;
    pg_tree_search_walk($tree, function ($field, $value) use ($replace, &$total) {
        list($new, $count) = call_user_func($replace, $value);
        if ((int) $count <= 0) {
            return null;
        }
        $total += (int) $count;
        return (string) $new;
    });

    return array($tree, $total);
}

/**
 * The words of a tree a visitor reads, one text per line: inline markup
 * removed and entities decoded, for counting a site search term. The
 * components the tree places are read from $components (id => decoded
 * tree), through nested ones, each at most once.
 *
 * @param mixed $tree
 * @param array $components
 * @return string
 */
function pg_tree_search_plain_text($tree, array $components = array())
{
    $lines = array();
    $queue = array($tree);
    $seen = array();

    while ($queue) {
        $current = array_shift($queue);
        foreach (pg_tree_search_texts($current) as $text) {
            $line = trim(html_entity_decode(strip_tags(str_replace('<', ' <', $text['value'])), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
            $line = preg_replace('/\s+/u', ' ', $line);
            if ($line !== '' && $line !== null) {
                $lines[] = $line;
            }
        }
        foreach (pg_tree_search_shared_ids($current) as $id) {
            if (!isset($seen[$id]) && isset($components[$id])) {
                $seen[$id] = true;
                $queue[] = $components[$id];
            }
        }
    }

    return implode("\n", $lines);
}

/**
 * The ids of the components whose words, with those of the components they
 * place, contain $needle (case folded), sorted. A page that places one of
 * them holds the words without its own tree carrying them.
 *
 * @param array  $components id => decoded tree (pg_tree_search_components())
 * @param string $needle
 * @return int[]
 */
function pg_tree_search_matching_components(array $components, $needle)
{
    $needle = mb_strtolower((string) $needle, 'UTF-8');
    $ids = array();

    if ($needle === '') {
        return $ids;
    }

    foreach ($components as $id => $tree) {
        $text = mb_strtolower(pg_tree_search_plain_text($tree, $components), 'UTF-8');
        if (mb_strpos($text, $needle, 0, 'UTF-8') !== false) {
            $ids[] = (int) $id;
        }
    }

    sort($ids);

    return $ids;
}

/**
 * A condition that a tree column or expression places one of the components
 * ('' for none): the id stored as a number or as a string.
 *
 * @param string $expr SQL expression, written into the query as it is
 * @param array  $ids
 * @return string
 */
function pg_tree_search_shared_ref_sql($expr, array $ids)
{
    $likes = array();

    foreach (array_unique(array_map('intval', $ids)) as $id) {
        if ($id <= 0) {
            continue;
        }
        $likes[] = $expr . " LIKE '%\"sharedId\":" . $id . ",%'";
        $likes[] = $expr . " LIKE '%\"sharedId\":" . $id . "}%'";
        $likes[] = $expr . " LIKE '%\"sharedId\":\"" . $id . "\"%'";
    }

    return $likes ? '(' . implode(' OR ', $likes) . ')' : '';
}

/**
 * Walks the searchable texts of a tree in document order, by reference.
 * $visit($field, $value) answers null to keep a text or a string to write
 * in its place.
 *
 * @param mixed    $node
 * @param callable $visit
 */
function pg_tree_search_walk(&$node, $visit)
{
    if (!is_array($node)) {
        return;
    }

    if (!isset($node['type'])) {
        foreach ($node as $key => $child) {
            if (is_array($child)) {
                pg_tree_search_walk($node[$key], $visit);
            }
        }
        return;
    }

    if (!empty($node['props']) && is_array($node['props'])) {
        foreach (pg_tree_search_node_fields($node['type'], $node['props']) as $field) {
            $value = pg_tr_field_get($node['props'], $field);
            if ($value === '') {
                continue;
            }
            $new = call_user_func($visit, $field, $value);
            if ($new !== null && $new !== $value) {
                pg_tr_field_set($node['props'], $field, $new);
            }
        }
    }

    if (($node['type'] !== 'shared_ref') && !empty($node['children']) && is_array($node['children'])) {
        foreach ($node['children'] as $key => $child) {
            pg_tree_search_walk($node['children'][$key], $visit);
        }
    }
}

/**
 * Whether a plain search term may pre-select tree rows with a LIKE over the
 * stored JSON: the JSON writes a text as typed but for a double quote, a
 * backslash and control characters, which it escapes. A regular expression
 * or such a term reads every tree and lets the PHP count decide.
 *
 * @param string $find
 * @param bool   $is_regex
 * @return bool
 */
function pg_tree_search_like_safe($find, $is_regex)
{
    return !$is_regex && !preg_match('/["\\\\\x00-\x1F]/', (string) $find);
}

/**
 * Writes a page tree a replace changed, as a designer save without other
 * changes would: the tree, the body drawn again, the modified time and
 * author, a stale SEO score, a revision (the state before seeded when the
 * page has none) and the translated texts marked. False when the page is
 * gone, has no tree of its own or the body could not be drawn.
 *
 * @param int   $page_id
 * @param array $tree
 * @param int   $user_id
 * @return bool
 */
function pg_tree_search_save_page($page_id, $tree, $user_id)
{
    $page_id = (int) $page_id;
    $row = db_item("SELECT page_id, page_name, page_style FROM page
                    WHERE page_id = '$page_id' AND page_tree_json IS NOT NULL AND page_tree_json <> '' LIMIT 1");
    $tree_json = function_exists('pg_designer_tree_encode') ? pg_designer_tree_encode($tree) : '';

    if (!is_array($row) || $tree_json === '') {
        return false;
    }

    $style_id = (int) $row['page_style'];
    $body_classes = (string) db_value("SELECT additional_body_classes FROM style WHERE style_id = '$style_id' LIMIT 1");

    // The messages node shows the session's pending form messages and uses
    // them up; they belong to the screen this request answers.
    $has_liveforms = isset($_SESSION['software']['liveforms']);
    $liveforms = $has_liveforms ? $_SESSION['software']['liveforms'] : null;
    unset($_SESSION['software']['liveforms']);
    $code = generate_style_code_from_tree($tree, (string) $row['page_name'], $body_classes);
    if ($has_liveforms) {
        $_SESSION['software']['liveforms'] = $liveforms;
    }

    if ($code === '') {
        return false;
    }

    if (function_exists('pg_page_revision_seed')) {
        pg_page_revision_seed($page_id);
    }

    db_q("UPDATE page SET page_tree_json = ?, page_tree_code = ?, page_timestamp = UNIX_TIMESTAMP(),
            page_user = ?, seo_analysis_current = '0'
          WHERE page_id = ?", array($tree_json, $code, (int) $user_id, $page_id));

    if (function_exists('pg_page_revision_record')) {
        pg_page_revision_record($page_id, $style_id, $tree_json, $code, (int) $user_id, 'publish');
    }

    if (function_exists('pg_tr_owner_saved')) {
        pg_tr_owner_saved('page', $page_id);
    }

    return true;
}

/**
 * Writes a shared component tree a replace changed; every page placing it
 * draws it from the row on its next request.
 *
 * @param int   $component_id
 * @param array $tree
 * @return bool
 */
function pg_tree_search_save_component($component_id, $tree)
{
    $component_id = (int) $component_id;
    $tree_json = function_exists('pg_designer_tree_encode') ? pg_designer_tree_encode($tree) : '';

    if ($component_id <= 0 || $tree_json === '') {
        return false;
    }

    db_q("UPDATE shared_components SET tree_json = ?, updated_at = ? WHERE id = ? LIMIT 1",
        array($tree_json, time(), $component_id));

    if (function_exists('pg_tr_owner_saved')) {
        pg_tr_owner_saved('shared', $component_id);
    }

    return true;
}

/**
 * The decoded trees of the shared components, id => tree, read once per
 * request for site search (system widgets left out: their words come from
 * their settings and records).
 *
 * @return array
 */
function pg_tree_search_components()
{
    static $trees = null;

    if ($trees !== null) {
        return $trees;
    }

    $trees = array();

    if (!function_exists('pg_schema_has') || !pg_schema_has('shared_components', 'system_region_config')) {
        return $trees;
    }

    $rows = db_items("SELECT id, tree_json FROM shared_components WHERE system_region_config IS NULL");

    foreach ((array) $rows as $row) {
        $tree = json_decode((string) $row['tree_json'], true);
        if (is_array($tree)) {
            $trees[(int) $row['id']] = $tree;
        }
    }

    return $trees;
}
