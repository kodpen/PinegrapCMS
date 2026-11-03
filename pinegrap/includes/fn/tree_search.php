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
