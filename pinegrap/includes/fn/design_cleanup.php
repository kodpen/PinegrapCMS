<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

// A module of functions.php: the clean-up finder of a design, pure.
//
// Loaded with require_once by the screens that need it, never on its own.
//
// The classes a design uses are gathered into a state from every place that
// can name one: page, component and e-mail trees (classes, attributes,
// markup, interactions, conditions, component settings), markup of classic
// pages and widget layouts, and the words of scripts. A stylesheet's rules
// are then matched against that state. The finder only ever errs towards
// "used": whatever it cannot tell (a class written by a record's value, a
// class attribute made by code, a sheet it cannot read, a framework class,
// a rule under a "keep" comment) is never listed.
if (!defined('PG_FUNCTIONS_DIR')) {
    exit;
}

/**
 * A new, empty state. $opts['known'] lists classes that are never counted
 * as unused, such as the framework's own (a widget or a node setting draws
 * them without naming them in the tree).
 *
 * @param array $opts
 * @return array
 */
function pg_cleanup_state($opts = array())
{
    $known = array();
    if (isset($opts['known']) && is_array($opts['known'])) {
        foreach ($opts['known'] as $class) {
            $known[(string) $class] = true;
        }
    }
    return array(
        'used'       => array(),
        'prefixes'   => array(),
        'known'      => $known,
        'blocked'    => '',
        'blocked_at' => '',
    );
}

/**
 * Classes the browser or the site's own scripts set at run time, and the
 * families the site and the icon sheet own: never counted as unused.
 *
 * @param string $class
 * @return bool
 */
function pg_cleanup_class_is_state($class)
{
    static $states = array(
        'active' => 1, 'show' => 1, 'showing' => 1, 'hiding' => 1, 'fade' => 1,
        'collapse' => 1, 'collapsing' => 1, 'collapsed' => 1, 'disabled' => 1,
        'was-validated' => 1, 'is-valid' => 1, 'is-invalid' => 1, 'bi' => 1,
    );
    if (isset($states[$class])) {
        return true;
    }
    return strpos($class, 'pg-') === 0 || strpos($class, 'bi-') === 0;
}

/**
 * Whether $class counts as used: named somewhere, covered by a name built
 * at run time from a known start, a state class or a known one.
 *
 * @param array  $state
 * @param string $class
 * @return bool
 */
function pg_cleanup_class_used($state, $class)
{
    $class = (string) $class;
    if (isset($state['used'][$class]) || isset($state['known'][$class]) || pg_cleanup_class_is_state($class)) {
        return true;
    }
    foreach ($state['prefixes'] as $prefix => $unused) {
        if (strpos($class, (string) $prefix) === 0) {
            return true;
        }
    }
    return false;
}

/**
 * Marks the state as unable to tell anything; the first reason is kept.
 *
 * @param array  $state
 * @param string $reason
 * @param string $source
 * @return void
 */
function pg_cleanup_block(&$state, $reason, $source)
{
    if ($state['blocked'] === '') {
        $state['blocked'] = $reason;
        $state['blocked_at'] = (string) $source;
    }
}

/**
 * Adds classes as a list or as one space-separated text (a setting such as
 * the style's additional body classes).
 *
 * @param array        $state
 * @param array|string $classes
 * @return void
 */
function pg_cleanup_add_classes(&$state, $classes)
{
    if (!is_array($classes)) {
        $classes = preg_split('~\s+~', trim((string) $classes), -1, PREG_SPLIT_NO_EMPTY);
    }
    foreach ($classes as $class) {
        $class = trim((string) $class);
        if ($class !== '') {
            $state['used'][$class] = true;
        }
    }
}

/**
 * Adds every word of a text: a name a script or a setting may turn into a
 * class. Colons stay inside a word (md:flex).
 *
 * @param array  $state
 * @param string $text
 * @return void
 */
function pg_cleanup_add_words(&$state, $text)
{
    if (preg_match_all('~-?[A-Za-z_][A-Za-z0-9_:\-]*~', (string) $text, $m)) {
        foreach ($m[0] as $word) {
            $state['used'][$word] = true;
        }
    }
}

/**
 * Adds the value of a class attribute or a cssClass prop. A part written by
 * a token or by code ({...}, <?...?>) covers every name that starts with
 * the plain text before it; with no such text nothing can be told.
 *
 * @param array  $state
 * @param string $value
 * @param string $source
 * @return void
 */
function pg_cleanup_add_class_text(&$state, $value, $source = '')
{
    $value = (string) $value;
    if (strpos($value, '{') === false && strpos($value, '<?') === false) {
        pg_cleanup_add_classes($state, $value);
        return;
    }
    $value = preg_replace('~<\?.*?(\?>|$)~s', '{}', $value);
    foreach (preg_split('~\s+~', trim($value), -1, PREG_SPLIT_NO_EMPTY) as $part) {
        $brace = strpos($part, '{');
        if ($brace === false) {
            $state['used'][$part] = true;
        } elseif ($brace === 0) {
            pg_cleanup_block($state, 'dynamic_class', $source);
        } else {
            $state['prefixes'][substr($part, 0, $brace)] = true;
        }
    }
}

/**
 * Adds a script: every word of it, and the start of every name it builds
 * ('tone-' + tone, `size-${n}`).
 *
 * @param array  $state
 * @param string $js
 * @param string $source
 * @return void
 */
function pg_cleanup_add_script(&$state, $js, $source = '')
{
    $js = (string) $js;
    pg_cleanup_add_words($state, $js);
    if (preg_match_all('~([A-Za-z0-9_:\-]+)[\'"]\s*\+~', $js, $m)) {
        foreach ($m[1] as $prefix) {
            $state['prefixes'][$prefix] = true;
        }
    }
    if (preg_match_all('~([A-Za-z0-9_:\-]+)\$\{~', $js, $m)) {
        foreach ($m[1] as $prefix) {
            $state['prefixes'][$prefix] = true;
        }
    }
}

/**
 * Adds markup (a classic page, an e-mail body, a widget's layout, an HTML
 * block): class attributes as classes, the other attribute values as words
 * (data-bs-target=".panel-x"), inline scripts as scripts. Text between tags
 * names no class.
 *
 * @param array  $state
 * @param string $html
 * @param string $source
 * @return void
 */
function pg_cleanup_add_markup(&$state, $html, $source = '')
{
    $html = (string) $html;
    if (preg_match_all('~<script\b[^>]*>(.*?)</script\s*>~is', $html, $m)) {
        foreach ($m[1] as $js) {
            pg_cleanup_add_script($state, $js, $source);
        }
        $html = preg_replace('~<script\b[^>]*>.*?</script\s*>~is', '', $html);
    }
    $attr = '~([^\s=/>"\']+)\s*=\s*("([^"]*)"|\'([^\']*)\'|([^\s>]+))~';
    if (!preg_match_all('~<[A-Za-z][^\s/>]*((?:"[^"]*"|\'[^\']*\'|[^>"\'])*)>~', $html, $tags)) {
        return;
    }
    foreach ($tags[1] as $attrs) {
        if (!preg_match_all($attr, $attrs, $am, PREG_SET_ORDER)) {
            continue;
        }
        foreach ($am as $a) {
            $value = isset($a[5]) && $a[5] !== '' ? $a[5] : (isset($a[4]) && $a[4] !== '' ? $a[4] : $a[3]);
            if (strtolower($a[1]) === 'class') {
                pg_cleanup_add_class_text($state, $value, $source);
            } else {
                pg_cleanup_add_words($state, $value);
            }
        }
    }
}

/**
 * Prop keys whose text a visitor reads: their words name no class, only
 * markup written into them does.
 *
 * @return array
 */
function pg_cleanup_prose_keys()
{
    return array(
        'text' => 1, '_label' => 1, 'customName' => 1, 'searchKeywords' => 1, 'alt' => 1,
        'title' => 1, 'placeholder' => 1, 'caption' => 1, 'description' => 1, 'label' => 1,
    );
}

/**
 * Adds the values of a node's props, or a part of them: markup by its
 * attributes, prose only by markup in it, every other text by its words
 * (interactions, conditions, component settings).
 *
 * @param array  $state
 * @param array  $values
 * @param string $source
 * @return void
 */
function pg_cleanup_add_values(&$state, $values, $source)
{
    $prose = pg_cleanup_prose_keys();
    foreach ($values as $key => $value) {
        if (is_array($value)) {
            pg_cleanup_add_values($state, $value, $source);
            continue;
        }
        if (!is_string($value) || $value === '') {
            continue;
        }
        if (strpos($value, '<') !== false && preg_match('~<[A-Za-z]~', $value)) {
            pg_cleanup_add_markup($state, $value, $source);
        } elseif (!is_string($key) || !isset($prose[$key])) {
            pg_cleanup_add_words($state, $value);
        }
    }
}

/**
 * Adds a designer tree (a page's, a shared component's, an e-mail design's,
 * a system widget's default layout). A class bound to a value is written
 * by a record or a setting at draw time: nothing can be told.
 *
 * @param array  $state
 * @param array  $node
 * @param string $source
 * @return void
 */
function pg_cleanup_add_tree(&$state, $node, $source = '')
{
    if (!is_array($node)) {
        return;
    }
    $props = isset($node['props']) && is_array($node['props']) ? $node['props'] : array();
    if (isset($props['_bindings']) && is_array($props['_bindings'])) {
        foreach (array('cssClass', 'class') as $key) {
            if (!empty($props['_bindings'][$key])) {
                pg_cleanup_block($state, 'bound_class', $source);
            }
        }
        unset($props['_bindings']);
    }
    if (isset($props['cssClass'])) {
        pg_cleanup_add_class_text($state, $props['cssClass'], $source);
        unset($props['cssClass']);
    }
    if (isset($props['_attrs']) && is_array($props['_attrs'])) {
        foreach ($props['_attrs'] as $a) {
            if (!is_array($a) || !isset($a['name'])) {
                continue;
            }
            $value = isset($a['value']) ? (string) $a['value'] : '';
            if (strtolower(trim((string) $a['name'])) === 'class') {
                pg_cleanup_add_class_text($state, $value, $source);
            } else {
                pg_cleanup_add_words($state, $value);
            }
        }
        unset($props['_attrs']);
    }
    pg_cleanup_add_values($state, $props, $source);
    if (isset($node['children']) && is_array($node['children'])) {
        foreach ($node['children'] as $child) {
            pg_cleanup_add_tree($state, $child, $source);
        }
    }
}

/**
 * The index after a string that starts at $i, or -1 when it never closes.
 *
 * @param string $css
 * @param int    $i
 * @return int
 */
function pg_cleanup_css_string_end($css, $i)
{
    $quote = $css[$i];
    $len = strlen($css);
    for ($j = $i + 1; $j < $len; $j++) {
        if ($css[$j] === '\\') {
            $j++;
        } elseif ($css[$j] === $quote) {
            return $j + 1;
        } elseif ($css[$j] === "\n") {
            return -1;
        }
    }
    return -1;
}

/**
 * The index of the brace that closes the block opened at $open, past
 * strings, comments and nested blocks; -1 when it never closes.
 *
 * @param string $css
 * @param int    $open
 * @return int
 */
function pg_cleanup_css_block_end($css, $open)
{
    $len = strlen($css);
    $depth = 0;
    for ($i = $open; $i < $len; $i++) {
        $c = $css[$i];
        if ($c === '/' && isset($css[$i + 1]) && $css[$i + 1] === '*') {
            $end = strpos($css, '*/', $i + 2);
            if ($end === false) {
                return -1;
            }
            $i = $end + 1;
        } elseif ($c === '"' || $c === "'") {
            $end = pg_cleanup_css_string_end($css, $i);
            if ($end < 0) {
                return -1;
            }
            $i = $end - 1;
        } elseif ($c === '\\') {
            $i++;
        } elseif ($c === '{') {
            $depth++;
        } elseif ($c === '}') {
            $depth--;
            if ($depth === 0) {
                return $i;
            }
        }
    }
    return -1;
}

/**
 * The style rules of a stylesheet, each with its selector, its byte range
 * (start, end), the at-rules around it and whether a "keep" comment holds
 * it. Rules inside @media, @supports, @layer and @container are read; the
 * other at-rules (@keyframes, @font-face, @import) hold no class rule.
 * Returns false for a sheet it cannot read.
 *
 * @param string $css
 * @return array|false
 */
function pg_cleanup_css_rules($css)
{
    $css = (string) $css;
    $len = strlen($css);
    $groups = array('@media' => 1, '@supports' => 1, '@layer' => 1, '@container' => 1, '@document' => 1, '@-moz-document' => 1);
    $rules = array();
    $stack = array();
    $start = -1;
    $keep = false;
    $i = 0;
    while ($i < $len) {
        $c = $css[$i];
        if ($c === '/' && isset($css[$i + 1]) && $css[$i + 1] === '*') {
            $end = strpos($css, '*/', $i + 2);
            if ($end === false) {
                return false;
            }
            if ($start < 0 && preg_match('~\bkeep\b~i', substr($css, $i + 2, $end - $i - 2))) {
                $keep = true;
            }
            $i = $end + 2;
            continue;
        }
        if (ctype_space($c)) {
            $i++;
            continue;
        }
        if ($start < 0) {
            $start = $i;
        }
        if ($c === '"' || $c === "'") {
            $end = pg_cleanup_css_string_end($css, $i);
            if ($end < 0) {
                return false;
            }
            $i = $end;
            continue;
        }
        if ($c === '\\') {
            $i += 2;
            continue;
        }
        if ($c === ';') {
            $start = -1;
            $keep = false;
            $i++;
            continue;
        }
        if ($c === '}') {
            if (!$stack) {
                return false;
            }
            array_pop($stack);
            $start = -1;
            $keep = false;
            $i++;
            continue;
        }
        if ($c !== '{') {
            $i++;
            continue;
        }
        $prelude = trim(preg_replace('~\s+~', ' ', preg_replace('~/\*.*?\*/~s', ' ', substr($css, $start, $i - $start))));
        $parent = $stack ? $stack[count($stack) - 1] : array('media' => '', 'keep' => false);
        $held = $parent['keep'] || $keep;
        if ($prelude !== '' && $prelude[0] === '@') {
            $name = strtolower(preg_replace('~^(@[A-Za-z-]+).*$~s', '$1', $prelude));
            if (isset($groups[$name])) {
                $stack[] = array('media' => trim($parent['media'] . ' ' . $prelude), 'keep' => $held);
                $i++;
            } else {
                $close = pg_cleanup_css_block_end($css, $i);
                if ($close < 0) {
                    return false;
                }
                $i = $close + 1;
            }
        } else {
            $close = pg_cleanup_css_block_end($css, $i);
            if ($close < 0) {
                return false;
            }
            $rules[] = array(
                'selector' => $prelude,
                'start'    => $start,
                'end'      => $close + 1,
                'media'    => $parent['media'],
                'keep'     => $held,
            );
            $i = $close + 1;
        }
        $start = -1;
        $keep = false;
    }
    if ($stack) {
        return false;
    }
    return $rules;
}

/**
 * Turns a CSS-escaped class name back into its text (md\:flex, \31 23).
 *
 * @param string $name
 * @return string
 */
function pg_cleanup_css_unescape($name)
{
    return preg_replace_callback('~\\\\([0-9a-fA-F]{1,6}\s?|.)~s', function ($m) {
        $s = rtrim($m[1]);
        if (preg_match('~^[0-9a-fA-F]{1,6}$~', $s)) {
            $code = hexdec($s);
            return $code > 0 && $code < 0x110000 ? html_entity_decode('&#' . $code . ';', ENT_QUOTES, 'UTF-8') : '';
        }
        return $m[1];
    }, $name);
}

/**
 * The classes each alternative of a selector list needs. Classes inside
 * brackets, strings and functional pseudo-classes (:not(), :is(), :has())
 * are not counted: such an alternative needs only the classes outside them.
 *
 * @param string $selector
 * @return array List of class lists, one per alternative.
 */
function pg_cleanup_selector_classes($selector)
{
    $selector = (string) $selector;
    $parts = array();
    $depth = 0;
    $current = '';
    $len = strlen($selector);
    for ($i = 0; $i < $len; $i++) {
        $c = $selector[$i];
        if ($c === '\\' && $i + 1 < $len) {
            $current .= $c . $selector[++$i];
            continue;
        }
        if ($c === '"' || $c === "'") {
            $end = strpos($selector, $c, $i + 1);
            $end = $end === false ? $len - 1 : $end;
            $current .= ' ';
            $i = $end;
            continue;
        }
        if ($c === '(' || $c === '[') {
            $depth++;
        } elseif ($c === ')' || $c === ']') {
            $depth--;
        } elseif ($c === ',' && $depth === 0) {
            $parts[] = $current;
            $current = '';
            continue;
        }
        $current .= $depth > 0 || $c === ')' || $c === ']' ? ' ' : $c;
    }
    $parts[] = $current;
    $out = array();
    foreach ($parts as $part) {
        $classes = array();
        if (preg_match_all('~\.((?:\\\\[0-9a-fA-F]{1,6}\s?|\\\\[^\r\n]|[A-Za-z0-9_\-]|[\x80-\xFF])+)~', $part, $m)) {
            foreach ($m[1] as $raw) {
                $classes[pg_cleanup_css_unescape($raw)] = true;
            }
        }
        $out[] = array_keys($classes);
    }
    return $out;
}

/**
 * The rules of a design's own stylesheet that nothing in $state uses: every
 * alternative of the rule's selector needs a class nothing names. Each row
 * gives the selector, the missing classes, the at-rules around it and its
 * byte range and size; 'bytes' is the size all of them would free.
 * 'blocked' is the reason nothing is listed ('bound_class',
 * 'dynamic_class', 'unreadable'), else ''.
 *
 * @param string $css
 * @param array  $state
 * @return array
 */
function pg_cleanup_unused_rules($css, $state)
{
    $result = array('rows' => array(), 'bytes' => 0, 'blocked' => '');
    if ($state['blocked'] !== '') {
        $result['blocked'] = $state['blocked'];
        return $result;
    }
    $rules = pg_cleanup_css_rules($css);
    if ($rules === false) {
        $result['blocked'] = 'unreadable';
        return $result;
    }
    foreach ($rules as $rule) {
        if ($rule['keep']) {
            continue;
        }
        $missing = array();
        $unused = true;
        foreach (pg_cleanup_selector_classes($rule['selector']) as $classes) {
            $gone = array();
            foreach ($classes as $class) {
                if (!pg_cleanup_class_used($state, $class)) {
                    $gone[] = $class;
                }
            }
            if (!$gone) {
                $unused = false;
                break;
            }
            $missing = array_merge($missing, $gone);
        }
        if (!$unused) {
            continue;
        }
        $bytes = $rule['end'] - $rule['start'];
        $result['rows'][] = array(
            'selector' => $rule['selector'],
            'classes'  => array_values(array_unique($missing)),
            'media'    => $rule['media'],
            'start'    => $rule['start'],
            'end'      => $rule['end'],
            'bytes'    => $bytes,
        );
        $result['bytes'] += $bytes;
    }
    return $result;
}
