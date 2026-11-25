<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * My library's file: "Export" writes the chosen items of the site's library
 * into one file, "Import" reads such a file from another site. An imported
 * file passes the checks the HTML and ZIP import runs (no script, no event
 * attribute, no address outside the allowed kinds); one item that fails
 * refuses the whole file, and the reason names the item. A name the library
 * already has is kept beside it: nothing is replaced.
 *
 * Loaded by the two endpoints that use it (designer/library_export,
 * designer/library_import), on top of includes/fn/designer_library.php.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_FUNCTIONS_DIR')) {
    exit;
}

// An export file: its format line and the largest file an import reads.
define('PG_LIBRARY_EXPORT_FORMAT', 'pinegrap-library');
define('PG_LIBRARY_EXPORT_MAX', 1048576);

// ── The checks (pure) ──────────────────────────────────────────────────

// An address as a browser reads it: entities decoded, the blanks and
// control characters it skips taken out, so "java&#115;cript:" or
// "java\tscript:" is read as it would run.
function _pg_library_address_flat($url)
{
    $u = html_entity_decode((string)$url, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    return (string)preg_replace('/[\x00-\x20\x7f]+/', '', $u);
}

/**
 * The scheme of an address an imported item may not carry, with its colon
 * ("javascript:", "data:"), or '' when the address is allowed: a path on
 * the site (no scheme: "/x", "x.html", "#id", "?q", "//host"), http,
 * https, mailto, tel, sms, or a data: picture of a raster kind. A
 * {{token}} of a widget's record has no scheme, so it is kept.
 */
function pg_library_address_refused($url)
{
    $u = _pg_library_address_flat($url);
    if ($u === '' || !preg_match('/^([a-zA-Z][a-zA-Z0-9+.\-]*):/', $u, $m)) return '';
    $scheme = strtolower($m[1]);
    if (in_array($scheme, array('http', 'https', 'mailto', 'tel', 'sms'), true)) return '';
    if ($scheme === 'data' && preg_match('#^data:image/(png|jpe?g|gif|webp|avif)[;,]#i', $u)) return '';
    return $scheme . ':';
}

// The attribute and prop names that hold an address.
function pg_library_address_key($name)
{
    $name = strtolower((string)$name);
    return in_array($name, array('href', 'src', 'action', 'formaction', 'poster', 'xlink:href', 'data', 'cite',
                                 'background', 'url', 'link', 'data-src', 'data-bg', 'data-background',
                                 'srcset', 'data-srcset', 'imagesrcset'), true);
}

// The addresses of one attribute value: the first word of each srcset
// candidate, or the value itself.
function _pg_library_value_urls($key, $value)
{
    if (stripos((string)$key, 'srcset') === false) return array((string)$value);
    $out = array();
    foreach (explode(',', (string)$value) as $part) {
        $words = preg_split('/\s+/', trim($part), -1, PREG_SPLIT_NO_EMPTY);
        if ($words) $out[] = $words[0];
    }
    return $out;
}

// A problem as the checks give it: what is wrong and the part that says it
// (the attribute's name, the address's scheme).
function _pg_library_problem($code, $detail = '')
{
    return array('code' => $code, 'detail' => (string)$detail);
}

// The problem of one address value, or null.
function _pg_library_urls_problem($key, $value)
{
    foreach (_pg_library_value_urls($key, $value) as $u) {
        $scheme = pg_library_address_refused($u);
        if ($scheme !== '') return _pg_library_problem('address', $scheme);
    }
    return null;
}

/**
 * What is wrong with one text of an item, or null: a script, an event
 * attribute, or an address of a kind not allowed. Reads the text as markup
 * when it holds a tag and as a style when it holds url( or expression(;
 * $key names the prop or attribute it came from, so an address field is
 * checked whatever its text. A sentence like "Learn javascript: basics"
 * passes; a string that starts with a scheme that runs does not.
 */
function pg_library_text_problem($text, $key = '')
{
    if (!is_string($text) || $text === '') return null;
    if ($key !== '' && pg_library_address_key($key)) {
        $p = _pg_library_urls_problem($key, $text);
        if ($p) return $p;
    }
    $decoded = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    if (preg_match('/^[\x00-\x20]*(javascript|vbscript|livescript)\s*:/i', $decoded, $m)) {
        return _pg_library_problem('address', strtolower($m[1]) . ':');
    }
    if (strpos($text, '<') !== false) {
        if (preg_match('/<\s*\/?\s*script\b/i', $text)) return _pg_library_problem('script');
        if (preg_match_all('/<[a-zA-Z][^>]*>/', $text, $tags)) {
            foreach ($tags[0] as $tag) {
                if (preg_match('/[\s\/"\'](on[a-zA-Z]+)\s*=/', $tag, $ev)) return _pg_library_problem('event', strtolower($ev[1]));
                if (preg_match('/[\s\/"\']srcdoc\s*=/i', $tag)) return _pg_library_problem('script');
                if (!preg_match_all('/[\s\/"\']([a-zA-Z:-]+)\s*=\s*("([^"]*)"|\'([^\']*)\'|([^\s>]+))/', $tag, $attrs, PREG_SET_ORDER)) continue;
                foreach ($attrs as $a) {
                    if (!pg_library_address_key($a[1])) continue;
                    $value = (isset($a[5]) && $a[5] !== '') ? $a[5] : ((isset($a[4]) && $a[4] !== '') ? $a[4] : $a[3]);
                    $p = _pg_library_urls_problem($a[1], $value);
                    if ($p) return $p;
                }
            }
        }
    }
    return pg_library_css_problem($decoded);
}

// What is wrong with a style text, or null: a CSS expression, a binding,
// an @import (script), or a url() of a kind not allowed.
function pg_library_css_problem($css)
{
    $css = (string)$css;
    if ($css === '') return null;
    $flat = preg_replace('/\\\\|\/\*.*?\*\//s', '', $css);
    if (preg_match('/expression\s*\(|-moz-binding|behavior\s*:|@import/i', $flat)) return _pg_library_problem('script');
    if (preg_match_all('/url\(\s*(["\']?)([^"\')]*)\1\s*\)/i', $flat, $m)) {
        foreach ($m[2] as $u) {
            $scheme = pg_library_address_refused($u);
            if ($scheme !== '') return _pg_library_problem('address', $scheme);
        }
    }
    return null;
}

// The first problem of the strings of an array, nested ones included; each
// is read with the nearest named key above it.
function _pg_library_strings_problem($value, $key = '', $depth = 0)
{
    if ($depth > 40) return _pg_library_problem('deep');
    if (is_string($value)) return pg_library_text_problem($value, $key);
    if (!is_array($value)) return null;
    foreach ($value as $k => $v) {
        $p = _pg_library_strings_problem($v, is_int($k) ? $key : (string)$k, $depth + 1);
        if ($p) return $p;
    }
    return null;
}

// The problem of one node of a tree (its type, its attributes, its props),
// or null; the children are read by the walk.
function _pg_library_node_problem($n)
{
    $type = isset($n['type']) ? strtolower((string)$n['type']) : '';
    $tag = (isset($n['props']['tag']) && is_string($n['props']['tag'])) ? strtolower($n['props']['tag']) : '';
    if ($type === 'script' || $tag === 'script') return _pg_library_problem('script');
    if (!isset($n['props']) || !is_array($n['props'])) return null;
    $props = $n['props'];
    if (isset($props['_attrs']) && is_array($props['_attrs'])) {
        foreach ($props['_attrs'] as $a) {
            if (!is_array($a) || !isset($a['name'])) continue;
            $name = strtolower(trim((string)$a['name']));
            if (strpos($name, 'on') === 0) return _pg_library_problem('event', $name);
            if ($name === 'srcdoc') return _pg_library_problem('script');
            $p = pg_library_text_problem(isset($a['value']) ? (string)$a['value'] : '', $name);
            if ($p) return $p;
        }
        unset($props['_attrs']);
    }
    return _pg_library_strings_problem($props);
}

// Whether a tree is nested deeper than the editor draws.
function _pg_library_too_deep($node, $depth = 0)
{
    if ($depth > 80) return true;
    if (!isset($node['children']) || !is_array($node['children'])) return false;
    foreach ($node['children'] as $c) {
        if (is_array($c) && _pg_library_too_deep($c, $depth + 1)) return true;
    }
    return false;
}

/**
 * What an item read from a file carries that an import refuses, as the
 * HTML and ZIP import refuses it, or null: array('code', 'detail') with the
 * code 'script' (a script element or node, a srcdoc frame, a CSS expression
 * or binding), 'event' (an on… attribute, detail its name), 'address' (an
 * address outside the allowed kinds, detail its scheme) or 'deep' (a tree
 * nested past what the editor draws). Reads the tree, the trees of its
 * widgets and shared components, the widget settings, the rules and the
 * variables.
 */
function pg_library_item_problem($item)
{
    if (!is_array($item)) return _pg_library_problem('deep');
    $trees = array(isset($item['tree']) ? $item['tree'] : null);
    foreach (array('widgets', 'shared') as $bucket) {
        foreach ((isset($item[$bucket]) && is_array($item[$bucket]) ? $item[$bucket] : array()) as $entry) {
            if (!is_array($entry)) continue;
            if (isset($entry['tree']) && is_array($entry['tree'])) $trees[] = $entry['tree'];
            if (isset($entry['config'])) {
                $p = _pg_library_strings_problem($entry['config']);
                if ($p) return $p;
            }
        }
    }
    foreach ($trees as $tree) {
        if (!is_array($tree)) continue;
        // A tree deeper than the walk reads is refused rather than half read.
        if (_pg_library_too_deep($tree)) return _pg_library_problem('deep');
        $stack = array($tree);
        while ($stack) {
            $n = array_pop($stack);
            $p = _pg_library_node_problem($n);
            if ($p) return $p;
            if (isset($n['children']) && is_array($n['children'])) {
                foreach ($n['children'] as $c) {
                    if (is_array($c)) $stack[] = $c;
                }
            }
        }
    }
    foreach ((isset($item['rules']) ? $item['rules'] : array()) as $rule) {
        $p = pg_library_css_problem($rule['selector'] . '{' . $rule['body'] . '}');
        if ($p) return $p;
    }
    foreach ((isset($item['variables']) ? $item['variables'] : array()) as $value) {
        $p = pg_library_css_problem((string)$value);
        if ($p) return $p;
    }
    return null;
}

// ── The file (pure) ────────────────────────────────────────────────────

/**
 * The text of an export file: the chosen items, each as its own file holds
 * it (without the site's row id, file name and order), under one format
 * line. $at is the date it is written (Y-m-d).
 */
function pg_library_export_text($items, $at)
{
    $list = array();
    foreach ((array)$items as $item) {
        if (!is_array($item)) continue;
        unset($item['dropped'], $item['id'], $item['file'], $item['order']);
        $list[] = $item;
    }
    $doc = array('format' => PG_LIBRARY_EXPORT_FORMAT, 'version' => PG_LIBRARY_VERSION,
                 'exported' => (string)$at, 'items' => $list);
    return json_encode($doc, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . "\n";
}

// The name an export file is offered under: the site's name with
// "-library" ("Studio Calm" gives studio-calm-library.json).
function pg_library_export_file_name($site_name)
{
    $slug = strtolower(trim((string)preg_replace('/[^a-zA-Z0-9]+/', '-', (string)$site_name), '-'));
    if ($slug === '') return 'library.json';
    return substr($slug, 0, 60) . '-library.json';
}

/**
 * The items of an import file: an export file (format pinegrap-library) or
 * one item's own file. Every item passes pg_library_item_decode() and
 * pg_library_item_problem(); one that does not refuses the whole file.
 *
 * Returns array('items' => list) or array('error' => code, 'detail' => the
 * attribute or scheme, 'item' => the name of the item refused, '' for the
 * file). Codes: too_large, not_a_library, version, and the codes of
 * pg_library_item_problem().
 */
function pg_library_import_read($text)
{
    $refuse = function ($code, $item = '', $detail = '') {
        return array('error' => $code, 'detail' => (string)$detail, 'item' => (string)$item);
    };
    if (!is_string($text) || trim($text) === '') return $refuse('not_a_library');
    if (strlen($text) > PG_LIBRARY_EXPORT_MAX) return $refuse('too_large');
    $doc = json_decode($text, true);
    if (!is_array($doc) || !isset($doc['format'])) return $refuse('not_a_library');
    if ($doc['format'] === PG_LIBRARY_FORMAT) {
        $raw = array($doc);
    } elseif ($doc['format'] === PG_LIBRARY_EXPORT_FORMAT) {
        if (!isset($doc['version']) || (int)$doc['version'] < 1) return $refuse('not_a_library');
        if ((int)$doc['version'] > PG_LIBRARY_VERSION) return $refuse('version');
        $raw = (isset($doc['items']) && is_array($doc['items'])) ? array_values($doc['items']) : array();
    } else {
        return $refuse('not_a_library');
    }
    if (!$raw) return $refuse('not_a_library');
    $items = array();
    foreach ($raw as $i => $one) {
        $label = (is_array($one) && isset($one['name'])) ? pg_library_clean_label($one['name']) : '';
        if ($label === '') $label = '#' . ($i + 1);
        $json = is_array($one) ? json_encode($one, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) : '';
        $item = pg_library_item_decode(is_string($json) ? $json : '');
        if (!is_array($item)) {
            return $item === 'version' ? $refuse('version') : $refuse('not_a_library');
        }
        $problem = pg_library_item_problem($item);
        if ($problem) return $refuse($problem['code'], $label, $problem['detail']);
        unset($item['id'], $item['file'], $item['dropped']);
        $items[] = $item;
    }
    return array('items' => $items);
}

// A name or group folded for comparing (case does not make another name).
function _pg_library_fold($s)
{
    $s = (string)$s;
    return function_exists('mb_strtolower') ? mb_strtolower($s, 'UTF-8') : strtolower($s);
}

/**
 * The imported items in the order they are kept, after the library's last,
 * each with what the report says of it: 'name_kept' (the library, or an
 * item before it in the file, has an item of this name: both are kept),
 * 'group' (added to a group the library has), 'new_group' (added to a group
 * the import makes) or 'no_group'. Names are never changed.
 */
function pg_library_import_plan($items, $existing)
{
    $names = array();
    $groups = array();
    $last = 0;
    foreach ((array)$existing as $e) {
        $names[_pg_library_fold(isset($e['name']) ? $e['name'] : '')] = true;
        $g = isset($e['group']) ? (string)$e['group'] : '';
        if ($g !== '') $groups[_pg_library_fold($g)] = true;
        if (isset($e['order']) && (int)$e['order'] > $last) $last = (int)$e['order'];
    }
    $out = array();
    foreach ((array)$items as $item) {
        $name = _pg_library_fold($item['name']);
        $group = isset($item['group']) ? (string)$item['group'] : '';
        if (isset($names[$name])) {
            $note = 'name_kept';
        } elseif ($group === '') {
            $note = 'no_group';
        } else {
            $note = isset($groups[_pg_library_fold($group)]) ? 'group' : 'new_group';
        }
        $names[$name] = true;
        if ($group !== '') $groups[_pg_library_fold($group)] = true;
        $item['order'] = ++$last;
        $out[] = array('item' => $item, 'note' => $note);
    }
    return $out;
}

/**
 * The sentence a refused import shows, as the window words it: the file's
 * name, why, and which item.
 */
function pg_library_import_message($error, $file, $item, $detail = '')
{
    $file = pg_library_clean_label($file) !== '' ? (string)$file : 'library.json';
    $vars = array($file, (string)$item, (string)$detail);
    switch ((string)$error) {
        case 'too_large':
            return lang(array('string' => '“{var:1}” is larger than 1 MB.', 'vars' => $vars));
        case 'version':
            return lang(array('string' => '“{var:1}” was made by a newer Pinegrap. Update this site, then import it.', 'vars' => $vars));
        case 'script':
            return lang(array('string' => '“{var:1}” holds a script in the item “{var:2}”.', 'vars' => $vars));
        case 'event':
            return lang(array('string' => '“{var:1}” holds an event attribute ({var:3}) in the item “{var:2}”.', 'vars' => $vars));
        case 'address':
            return lang(array('string' => '“{var:1}” holds an address that is not allowed ({var:3}) in the item “{var:2}”.', 'vars' => $vars));
        case 'deep':
            return lang(array('string' => '“{var:1}” holds elements nested too deeply in the item “{var:2}”.', 'vars' => $vars));
    }
    return lang(array('string' => '“{var:1}” is not a library file.', 'vars' => $vars));
}

// ── The store ──────────────────────────────────────────────────────────

/**
 * Keeps the items of an import file (pg_library_import_read()) in the
 * library. Returns array('rows' => one row per item kept: name, group,
 * note) or a string error message; an item that could not be written stops
 * the import and the ones written before it stay.
 */
function pg_library_import_save($items, $user_id)
{
    $rows = array();
    foreach (pg_library_import_plan($items, pg_library_list()) as $entry) {
        $saved = pg_library_save($entry['item'], $user_id);
        if (!is_array($saved)) return $saved;
        $rows[] = array('name' => $entry['item']['name'], 'group' => $entry['item']['group'], 'note' => $entry['note']);
    }
    return array('rows' => $rows);
}

/**
 * The items an export writes: the chosen ones by row id, in the library's
 * order, or every item when none is chosen.
 */
function pg_library_export_items($ids)
{
    $want = array();
    foreach ((array)$ids as $id) {
        if ((int)$id > 0) $want[(int)$id] = true;
    }
    $out = array();
    foreach (pg_library_list() as $item) {
        if (!$want || isset($want[(int)$item['id']])) $out[] = $item;
    }
    return $out;
}
