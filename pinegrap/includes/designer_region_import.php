<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * A classic designer region (a cregion row with cregion_designer_type
 * 'yes') as the tree of a designer-only shared component.
 *
 * The content goes through the HTML converter of includes/designer_import.php,
 * with three kinds of block taken out before it is parsed, because
 * DOMDocument would mangle them:
 *   • PHP blocks. Each self-contained block becomes a custom_php node. When a
 *     block is not one (a loop opened in one block and closed in another, a
 *     value one block sets and another reads, PHP inside a tag, a script or
 *     another raw block), the whole region becomes one custom_php node that
 *     prints the content as it is, so nothing is cut apart.
 *   • raw blocks (comments, script, style, noscript, pre, textarea, link,
 *     meta): kept verbatim as custom_html nodes; libxml would move a leading
 *     one into <head> and the converter drops scripts and styles.
 *   • the classic placeholders (<pregion>, <system>, <cregion>, <dregion>,
 *     <menu>, ...): region nodes printing the same tag. A designer region
 *     named inside becomes a shared_ref to its own component.
 *
 * Who may run it: a designer-only shared component is written by designers
 * (role 0-1), the right edit_designer_region.php asks for, so PHP stays with
 * the people who could write it before.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_FUNCTIONS_DIR')) {
    exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/designer_import.php');

/**
 * The tree of a shared component drawing a designer region's content.
 *
 * @param string   $content  cregion_content
 * @param callable $lookup   function ($name) for a <cregion> tag: array('sharedId' => int, 'sharedName' => string)
 *                           for a designer region placed as its component, array('regionType' => 'designer'|'common')
 *                           for a tag that stays a region node, null for an unknown name (stays a common region node)
 * @param array    $opts     'php' => true when PHP in the region runs today (PHP_REGIONS on)
 * @return array  'tree' (root node), 'php' ('none', 'blocks', 'whole' or 'text'), 'warnings'
 */
function pg_di_designer_region_tree($content, $lookup, $opts = array())
{
    $content = (string)$content;
    $out = array('tree' => pg_di_root(array()), 'php' => 'none', 'warnings' => array());
    if (trim($content) === '') return $out;

    $parts = pg_di_php_parts($content);
    $has_php = ($parts === null);
    foreach ((array)$parts as $p) {
        if ($p['kind'] === 'php') $has_php = true;
    }

    // PHP that does not run today is text on the page: it stays text.
    if ($has_php && empty($opts['php'])) {
        $out['php'] = 'text';
        $out['tree'] = pg_di_root(array(pg_di_mk('content', array('contentType' => 'custom_html', 'html' => $content, 'cssClass' => ''))));
        return $out;
    }

    $ctx = new stdClass();
    $ctx->user_id   = 0;
    $ctx->files     = array();
    $ctx->folders   = array();
    $ctx->warnings  = array();
    $ctx->root_id   = 0;
    $ctx->project   = '';
    $ctx->base_dir  = '';
    $ctx->page_slug = 'region';
    $ctx->fragment  = true;
    $ctx->lifted    = array();
    $ctx->consumed  = array();
    $ctx->svg_raw   = array();

    $html = $content;
    if ($has_php) {
        $code = ($parts === null) ? null : pg_di_php_lift_sources($parts);
        if ($code === null) return pg_di_region_whole($content, true, $out);
        $html = '';
        $n = 0;
        foreach ($parts as $p) {
            if ($p['kind'] === 'html') { $html .= $p['text']; continue; }
            $ctx->lifted['php:' . $n] = pg_di_mk('content', array('contentType' => 'custom_php', 'php' => $code[$n], 'cssClass' => ''));
            $html .= '<pg-php data-i="' . $n . '"></pg-php>';
            $n++;
        }
        $out['php'] = 'blocks';
        $out['warnings'] = array_merge($out['warnings'], pg_di_php_scope_warning($parts));
    }

    // Raw blocks first: a tag written inside a script or a comment stays
    // there as text.
    $html = pg_di_lift_raw_blocks($html, $ctx);
    $html = pg_di_lift_region_tags($html, $lookup, $ctx);
    $html = pg_di_lift_svgs($html, $ctx);

    $doc = new DOMDocument('1.0', 'UTF-8');
    libxml_use_internal_errors(true);
    $loaded = $doc->loadHTML('<?xml encoding="UTF-8">' . '<body>' . $html . '</body>', LIBXML_NONET);
    libxml_clear_errors();
    if (!$loaded) return pg_di_region_whole($content, $has_php, $out);

    $children = array();
    $body = (new DOMXPath($doc))->query('//body')->item(0);
    if ($body) {
        foreach ($body->childNodes as $cn) {
            $node = pg_di_node($cn, $ctx);
            if ($node !== null) $children[] = $node;
        }
    }

    // Every lifted block must come back exactly once as a node of its own;
    // one left inside an attribute, a script or a raw block would print its
    // placeholder instead of itself.
    foreach (array_keys($ctx->lifted) as $key) {
        if (!isset($ctx->consumed[$key]) || $ctx->consumed[$key] !== 1) {
            return pg_di_region_whole($content, $has_php, $out);
        }
    }

    $out['tree'] = pg_di_root($children);
    $out['warnings'] = array_merge($out['warnings'], $ctx->warnings);
    return $out;
}

// The region as one node printing its content unchanged: custom_php when it
// holds PHP (the code runs as one piece, as the page eval ran it), else
// custom_html.
function pg_di_region_whole($content, $has_php, $out)
{
    $props = $has_php
        ? array('contentType' => 'custom_php', 'php' => '?>' . $content, 'cssClass' => '')
        : array('contentType' => 'custom_html', 'html' => $content, 'cssClass' => '');
    $out['tree'] = pg_di_root(array(pg_di_mk('content', $props)));
    $out['php'] = $has_php ? 'whole' : 'none';
    $out['warnings'] = $has_php ? pg_di_php_scope_warning(pg_di_php_parts($content)) : array();
    return $out;
}

/**
 * The content cut into HTML and PHP parts the way PHP reads it
 * (token_get_all()): array('kind' => 'html', 'text' => ...) or
 * array('kind' => 'php', 'echo' => bool, 'tokens' => [...]). null when PHP is
 * present but cannot be tokenised here.
 */
function pg_di_php_parts($content)
{
    if (strpos($content, '<?') === false) return array(array('kind' => 'html', 'text' => $content));
    if (!function_exists('token_get_all')) return null;

    $parts = array();
    $cur = null;
    foreach (token_get_all($content) as $t) {
        $id = is_array($t) ? $t[0] : null;
        $text = is_array($t) ? $t[1] : $t;
        if ($id === T_INLINE_HTML) {
            $parts[] = array('kind' => 'html', 'text' => $text);
        } elseif ($id === T_OPEN_TAG || $id === T_OPEN_TAG_WITH_ECHO) {
            $cur = array('kind' => 'php', 'echo' => ($id === T_OPEN_TAG_WITH_ECHO), 'tokens' => array());
        } elseif ($id === T_CLOSE_TAG) {
            if ($cur !== null) $parts[] = $cur;
            $cur = null;
        } elseif ($cur !== null) {
            $cur['tokens'][] = $t;
        }
    }
    if ($cur !== null) $parts[] = $cur;
    return $parts;
}

/**
 * The source of each PHP part as a custom_php node runs it (eval() of bare
 * code), or null when the parts cannot run one by one: a part that is not a
 * whole statement list, or a variable two parts share (each node runs in a
 * scope of its own).
 */
function pg_di_php_lift_sources($parts)
{
    $code = array();
    $owner = array();
    $n = 0;
    foreach ($parts as $p) {
        if ($p['kind'] !== 'php') continue;
        if (!pg_di_php_block_whole($p['tokens'])) return null;
        foreach (pg_di_php_vars($p['tokens']) as $var) {
            if (isset($owner[$var]) && $owner[$var] !== $n) return null;
            $owner[$var] = $n;
        }
        $code[$n] = pg_di_php_block_source($p);
        $n++;
    }
    return $code;
}

// Whether a block's tokens are complete statements: braces and parentheses
// close inside it, and no alternative syntax (if (...): ... endif;) is
// opened or closed in it.
function pg_di_php_block_whole($tokens)
{
    $braces = 0;
    $parens = 0;
    $control = 0;       // parenthesis depth a control keyword's condition opened at, + 1
    $after_control = false;
    foreach ($tokens as $t) {
        $id = is_array($t) ? $t[0] : null;
        $text = is_array($t) ? $t[1] : $t;
        if (in_array($id, array(T_WHITESPACE, T_COMMENT, T_DOC_COMMENT), true)) continue;
        if (in_array($id, array(T_ENDIF, T_ENDFOREACH, T_ENDFOR, T_ENDWHILE, T_ENDSWITCH, T_ENDDECLARE), true)) return false;
        if ($after_control) {
            $after_control = false;
            if ($text === ':') return false;
        }
        if (in_array($id, array(T_IF, T_ELSEIF, T_FOREACH, T_FOR, T_WHILE, T_SWITCH, T_DECLARE), true)) {
            $control = $parens + 1;
            continue;
        }
        if ($id === T_ELSE) { $after_control = true; continue; }
        if ($text === '(') { $parens++; continue; }
        if ($text === ')') {
            $parens--;
            if ($parens < 0) return false;
            if ($control && $parens === $control - 1) { $control = 0; $after_control = true; }
            continue;
        }
        if ($text === '{' || $id === T_CURLY_OPEN || $id === T_DOLLAR_OPEN_CURLY_BRACES) { $braces++; continue; }
        if ($text === '}') {
            $braces--;
            if ($braces < 0) return false;
        }
    }
    return ($braces === 0) && ($parens === 0);
}

// The variables a block names, superglobals and $this left out.
function pg_di_php_vars($tokens)
{
    static $skip = array('$GLOBALS' => 1, '$_SERVER' => 1, '$_GET' => 1, '$_POST' => 1, '$_FILES' => 1,
                         '$_COOKIE' => 1, '$_SESSION' => 1, '$_REQUEST' => 1, '$_ENV' => 1, '$this' => 1);
    $vars = array();
    foreach ($tokens as $t) {
        if (is_array($t) && $t[0] === T_VARIABLE && !isset($skip[$t[1]])) $vars[$t[1]] = 1;
    }
    return array_keys($vars);
}

// A block as bare code: a short echo tag is "echo x;", and the statement the
// closing tag ended gets its semicolon.
function pg_di_php_block_source($part)
{
    $tokens = $part['tokens'];
    $last = -1;
    foreach ($tokens as $i => $t) {
        $id = is_array($t) ? $t[0] : null;
        if (!in_array($id, array(T_WHITESPACE, T_COMMENT, T_DOC_COMMENT), true)) $last = $i;
    }
    $code = '';
    foreach ($tokens as $i => $t) {
        $code .= is_array($t) ? $t[1] : $t;
        if ($i === $last && $t !== ';' && $t !== '}') $code .= ';';
    }
    $code = trim($code);
    if ($part['echo'] && $code !== '') $code = 'echo ' . $code;
    return $code;
}

/**
 * The warning for code that reads a variable it never sets: the page eval
 * ran the region inside get_page_content.php, where such a name may have
 * had a value; a custom_php node runs in a scope of its own. Approximate:
 * set means assigned (=), a foreach value, global or static.
 */
function pg_di_php_scope_warning($parts)
{
    $set = array();
    $read = array();
    foreach ((array)$parts as $p) {
        if ($p['kind'] !== 'php') continue;
        $tokens = array_values(array_filter($p['tokens'], function ($t) {
            return !is_array($t) || !in_array($t[0], array(T_WHITESPACE, T_COMMENT, T_DOC_COMMENT), true);
        }));
        $decl = false;
        foreach ($tokens as $i => $t) {
            $id = is_array($t) ? $t[0] : $t;
            if ($id === T_GLOBAL || $id === T_STATIC) $decl = true;
            if ($id === ';') $decl = false;
            if ($id !== T_VARIABLE) continue;
            $prev = ($i > 0) ? (is_array($tokens[$i - 1]) ? $tokens[$i - 1][0] : $tokens[$i - 1]) : null;
            $next = isset($tokens[$i + 1]) ? $tokens[$i + 1] : null;
            if ($decl || $next === '=' || $prev === T_AS || $prev === T_DOUBLE_ARROW) $set[$t[1]] = 1;
            else $read[$t[1]] = 1;
        }
    }
    $names = array();
    foreach (array_keys($read) as $v) {
        if (!isset($set[$v]) && in_array($v, pg_di_php_vars(array(array(T_VARIABLE, $v))), true)) $names[] = $v;
    }
    if (!$names) return array();
    return array(lang(array(
        'string' => 'The PHP of this region reads {var:1} without setting it. A PHP block runs on its own: a value the page set before is not there.',
        'vars'   => implode(', ', $names),
    )));
}

// The classic placeholders as <pg-region> elements, their region or
// shared_ref node kept in $ctx->lifted. <menu>'s clearing div is part of
// the tag a menu region node prints, so it goes with it.
function pg_di_lift_region_tags($html, $lookup, $ctx)
{
    static $types = array('pregion' => 'page', 'system' => 'system', 'cregion' => 'common', 'dregion' => 'dynamic',
                          'menu' => 'menu', 'menu_sequence' => 'menu_sequence', 'login' => 'login', 'ad' => 'ad',
                          'cart' => 'cart', 'tcloud' => 'tag_cloud', 'mobile_switch' => 'mobile_switch', 'pdf' => 'pdf');
    $place = function ($node) use ($ctx) {
        $i = count($ctx->lifted);
        $ctx->lifted['region:' . $i] = $node;
        return '<pg-region data-i="' . $i . '"></pg-region>';
    };
    $html = preg_replace_callback('/<!--pg-comments-block-->/', function () use ($place) {
        return $place(pg_di_mk('region', array('regionType' => 'comments_block', 'regionName' => '')));
    }, $html);
    return preg_replace_callback(
        '~<(pregion|system|cregion|dregion|menu_sequence|menu|login|ad|cart|tcloud|mobile_switch|pdf)(\s+"[^"<>]*")?\s*>([^<]*)</\1\s*>(\s*<div style="clear: both"></div>)?~i',
        function ($m) use ($types, $lookup, $place) {
            $tag = strtolower($m[1]);
            $clear = isset($m[4]) ? $m[4] : '';
            $rest = ($tag === 'menu') ? '' : $clear;
            if ($m[2] !== '' && $tag !== 'dregion') return $m[0];
            $name = $m[3];
            $props = array('regionType' => $types[$tag], 'regionName' => $name);
            if ($tag === 'dregion' && $m[2] !== '') $props['regionAttr'] = trim(trim($m[2]), '"');
            if ($tag === 'cregion') {
                $hit = is_callable($lookup) ? call_user_func($lookup, $name) : null;
                if (is_array($hit) && !empty($hit['sharedId'])) {
                    return $place(pg_di_mk('shared_ref', array('sharedId' => (int)$hit['sharedId'], 'sharedName' => (string)$hit['sharedName']))) . $rest;
                }
                if (is_array($hit) && isset($hit['regionType'])) $props['regionType'] = $hit['regionType'];
            }
            return $place(pg_di_mk('region', $props)) . $rest;
        },
        $html
    );
}

// Blocks kept verbatim: comments, raw-text elements and head-only tags.
function pg_di_lift_raw_blocks($html, $ctx)
{
    return preg_replace_callback(
        '~<!--.*?-->|<(script|style|noscript|pre|textarea)\b[^>]*>.*?</\1\s*>|<(?:link|meta)\b[^>]*>~is',
        function ($m) use ($ctx) {
            // The comments block is a region (pg_di_lift_region_tags()).
            if ($m[0] === '<!--pg-comments-block-->') return $m[0];
            $i = count($ctx->lifted);
            $ctx->lifted['raw:' . $i] = pg_di_mk('content', array('contentType' => 'custom_html', 'html' => $m[0], 'cssClass' => ''));
            return '<pg-raw data-i="' . $i . '"></pg-raw>';
        },
        $html
    );
}

// Whether an element holds a lifted block.
function pg_di_holds_lifted($node)
{
    foreach (array('pg-php', 'pg-raw', 'pg-region') as $tag) {
        if ($node->getElementsByTagName($tag)->length > 0) return true;
    }
    return false;
}

/**
 * The shared component drawing a designer region: the one made from it
 * before (category "cregion:<id>"), else a new designer-only row. Designer
 * regions named inside become components of their own, placed as nested
 * shared_refs; a region that names one of its own ancestors keeps that tag
 * as a region node, so no loop is written.
 *
 * @param string $name      cregion_name of a designer region
 * @param array  $user      the designer making it
 * @param array  &$warnings what the operator is told, per region
 * @param array  $stack     cregion ids being made above this one
 * @return array|null  array('id' => int, 'name' => string, 'created' => bool), null when no such region
 */
function pg_designer_region_component($name, $user, &$warnings, $stack = array())
{
    $row = db_item("SELECT cregion_id, cregion_name, cregion_content FROM cregion
                    WHERE cregion_name = '" . e($name) . "' AND cregion_designer_type = 'yes' LIMIT 1");
    if (!is_array($row)) return null;
    $rid = (int)$row['cregion_id'];
    $marker = 'cregion:' . $rid;

    $have = db_item("SELECT id, name FROM shared_components
                     WHERE category = '" . e($marker) . "' AND (system_region_config IS NULL OR system_region_config = '')
                     ORDER BY id ASC LIMIT 1");
    if (is_array($have)) return array('id' => (int)$have['id'], 'name' => (string)$have['name'], 'created' => false);

    $stack[$rid] = true;
    $lookup = function ($inner) use ($user, &$warnings, $stack) {
        $hit = db_item("SELECT cregion_id, cregion_designer_type FROM cregion WHERE cregion_name = '" . e($inner) . "' LIMIT 1");
        if (!is_array($hit)) return null;
        if ($hit['cregion_designer_type'] !== 'yes') return array('regionType' => 'common');
        if (isset($stack[(int)$hit['cregion_id']])) return array('regionType' => 'designer');
        $made = pg_designer_region_component($inner, $user, $warnings, $stack);
        return is_array($made) ? array('sharedId' => $made['id'], 'sharedName' => $made['name']) : array('regionType' => 'designer');
    };

    $res = pg_di_designer_region_tree($row['cregion_content'], $lookup, array('php' => defined('PHP_REGIONS') && PHP_REGIONS));
    $notes = $res['warnings'];
    if ($res['php'] === 'whole') {
        $notes[] = lang('Its PHP could not be cut into separate blocks, so the region is one PHP block that prints it as before.');
    }
    if (($res['php'] === 'blocks' || $res['php'] === 'whole') && pg_hosted()) {
        $notes[] = lang('A hosted site runs no PHP written in the designer: its PHP blocks print nothing.');
    }
    foreach ($notes as $note) {
        $warnings[] = $row['cregion_name'] . ': ' . $note;
    }

    $tree_json = pg_designer_tree_encode($res['tree']);
    if ($tree_json === '') return null;
    $unique = _sc_unique_name((string)$row['cregion_name']);
    $now = time();
    db("INSERT INTO shared_components (name, description, tree_json, category, created_by, created_at, updated_at)
        VALUES ('" . e($unique) . "', '', '" . e($tree_json) . "', '" . e($marker) . "', '" . (int)$user['id'] . "', '$now', '$now')");
    $sid = (int)mysqli_insert_id(db::$con);
    if ($sid <= 0) return null;
    log_activity(lang(array('string' => 'designer region ({var:1}) was made the shared component ({var:2})',
        'vars' => array($row['cregion_name'], $unique))), isset($_SESSION['sessionusername']) ? $_SESSION['sessionusername'] : '');
    return array('id' => $sid, 'name' => $unique, 'created' => true);
}

/**
 * The tree of a shared component drawing a common region's content: the
 * content converted as a designer region's is, inside one block marked
 * `_editable`, so the managers and the users granted the region keep editing
 * all of it at content level. Code nodes in it stay as written
 * (pg_designer_merge_restricted_tree() never takes them from a content save).
 *
 * @param string   $content  cregion_content
 * @param callable $lookup   as pg_di_designer_region_tree()
 * @param array    $opts     as pg_di_designer_region_tree()
 * @return array  as pg_di_designer_region_tree()
 */
function pg_di_common_region_tree($content, $lookup, $opts = array())
{
    $res = pg_di_designer_region_tree($content, $lookup, $opts);
    $block = pg_di_mk('semantic', array('tag' => 'div', 'cssClass' => '', '_editable' => 1), $res['tree']['children']);
    $res['tree'] = pg_di_root(array($block));
    return $res;
}

/**
 * The shared component drawing a common region (category
 * "common_region:<id>"): the one made before, else a new row opened to
 * content editors, with the users granted the region granted the component.
 * From then on the region's <cregion> tag draws the component on every page
 * (get_page_content.php); the row stays, its content no longer read.
 * Placeholders inside stay region nodes: a classic common region expands
 * none of them, and a component made here holds no other component.
 *
 * Needs the content-editing schema (pg_shared_component_content_ready()):
 * without it the people who edit the region would lose it.
 *
 * @param int   $cregion_id  id of a common region
 * @param array $user        the designer converting it
 * @param array &$warnings   what the operator is told
 * @return array|null  array('id' => int, 'name' => string, 'created' => bool), null when no such region
 */
function pg_common_region_component($cregion_id, $user, &$warnings)
{
    $rid = (int)$cregion_id;
    $row = db_item("SELECT cregion_id, cregion_name, cregion_content FROM cregion
                    WHERE cregion_id = '$rid' AND cregion_designer_type = 'no' LIMIT 1");
    if (!is_array($row)) return null;
    $marker = pg_common_region_component_category($rid);

    $have = db_item("SELECT id, name FROM shared_components
                     WHERE category = '" . e($marker) . "' AND (system_region_config IS NULL OR system_region_config = '')
                     ORDER BY id ASC LIMIT 1");
    if (is_array($have)) return array('id' => (int)$have['id'], 'name' => (string)$have['name'], 'created' => false);

    $lookup = function ($inner) {
        $hit = db_item("SELECT cregion_designer_type FROM cregion WHERE cregion_name = '" . e($inner) . "' LIMIT 1");
        return array('regionType' => (is_array($hit) && $hit['cregion_designer_type'] === 'yes') ? 'designer' : 'common');
    };

    $res = pg_di_common_region_tree($row['cregion_content'], $lookup, array('php' => defined('PHP_REGIONS') && PHP_REGIONS));
    $notes = $res['warnings'];
    if ($res['php'] === 'whole') {
        $notes[] = lang('Its PHP could not be cut into separate blocks, so the region is one PHP block that prints it as before.');
    }
    if (($res['php'] === 'blocks' || $res['php'] === 'whole') && pg_hosted()) {
        $notes[] = lang('A hosted site runs no PHP written in the designer: its PHP blocks print nothing.');
    }
    foreach ($notes as $note) {
        $warnings[] = $row['cregion_name'] . ': ' . $note;
    }

    $tree_json = pg_designer_tree_encode($res['tree']);
    if ($tree_json === '') return null;
    $unique = _sc_unique_name((string)$row['cregion_name']);
    $now = time();
    db("INSERT INTO shared_components (name, description, tree_json, category, content_editable, created_by, created_at, updated_at)
        VALUES ('" . e($unique) . "', '', '" . e($tree_json) . "', '" . e($marker) . "', 1, '" . (int)$user['id'] . "', '$now', '$now')");
    $sid = (int)mysqli_insert_id(db::$con);
    if ($sid <= 0) return null;
    db("INSERT IGNORE INTO users_shared_components_xref (user_id, shared_component_id)
        SELECT user_id, '$sid' FROM users_common_regions_xref WHERE common_region_id = '$rid'");
    log_activity(lang(array('string' => 'common region ({var:1}) was made the shared component ({var:2})',
        'vars' => array($row['cregion_name'], $unique))), isset($_SESSION['sessionusername']) ? $_SESSION['sessionusername'] : '');
    return array('id' => $sid, 'name' => $unique, 'created' => true);
}

/**
 * The blocks a page's own regions become in its tree: each page region's
 * content converted as a common region's is, in one block marked
 * `_editable`, so the people who edit the page today (managers, users with
 * the folder's edit right) keep editing all of it at content level. Block N
 * takes the place of the page's Nth page region node, the one
 * get_page_content.php fills with the Nth row; a node with no row becomes an
 * empty block, as the page drew it.
 *
 * @param array    $contents  pregion_content values in pregion_order
 * @param int      $count     page region nodes on the page
 * @param callable $lookup    as pg_di_designer_region_tree()
 * @param array    $opts      as pg_di_designer_region_tree()
 * @return array  'blocks' (one semantic node each), 'warnings' (prefixed with the region's number)
 */
function pg_di_page_region_blocks($contents, $count, $lookup, $opts = array())
{
    $contents = array_values((array)$contents);
    $out = array('blocks' => array(), 'warnings' => array());
    for ($i = 0; $i < (int)$count; $i++) {
        $res = pg_di_common_region_tree(isset($contents[$i]) ? $contents[$i] : '', $lookup, $opts);
        $out['blocks'][] = $res['tree']['children'][0];
        $notes = $res['warnings'];
        if ($res['php'] === 'whole') {
            $notes[] = lang('Its PHP could not be cut into separate blocks, so the region is one PHP block that prints it as before.');
        }
        if (($res['php'] === 'blocks' || $res['php'] === 'whole') && !empty($opts['hosted'])) {
            $notes[] = lang('A hosted site runs no PHP written in the designer: its PHP blocks print nothing.');
        }
        foreach ($notes as $note) {
            $out['warnings'][] = lang(array('string' => 'Page region {var:1}', 'vars' => array($i + 1))) . ': ' . $note;
        }
    }
    return $out;
}

/**
 * The blocks of a page's own regions (pg_di_page_region_blocks()), read in
 * the collection of the page's style as the page draws them. The rows stay;
 * once the page is saved without page region nodes nothing reads them.
 *
 * @param int $page_id
 * @param int $count  page region nodes on the page
 * @return array  as pg_di_page_region_blocks()
 */
function pg_page_region_blocks($page_id, $count)
{
    $pid = (int)$page_id;
    $count = max(0, min(100, (int)$count));
    $collection = (string)db_value("SELECT style.collection FROM page
                                    LEFT JOIN style ON style.style_id = page.page_style
                                    WHERE page.page_id = '$pid' LIMIT 1");
    $contents = ($count > 0) ? db_values("SELECT pregion_content FROM pregion
                                          WHERE pregion_page = '$pid' AND collection = '" . e($collection) . "'
                                          ORDER BY pregion_order ASC LIMIT $count") : array();
    $lookup = function ($inner) {
        $hit = db_item("SELECT cregion_designer_type FROM cregion WHERE cregion_name = '" . e($inner) . "' LIMIT 1");
        return array('regionType' => (is_array($hit) && $hit['cregion_designer_type'] === 'yes') ? 'designer' : 'common');
    };
    return pg_di_page_region_blocks($contents, $count, $lookup,
        array('php' => defined('PHP_REGIONS') && PHP_REGIONS, 'hosted' => pg_hosted()));
}
