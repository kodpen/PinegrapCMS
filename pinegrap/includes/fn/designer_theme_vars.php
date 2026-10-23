<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * The rule blocks the visual designer's Themes panel writes its Bootstrap
 * variables into, on the server. A PHP twin of the panel's helpers in
 * assets/js/style_designer.js (_findRuleBlock(), _themeSelectorRe(),
 * _themeFindBlock(), _themeCanonicalSelector(), _themeDarkOwnVars(),
 * _parseThemeDecls(), _themeSetDecl(), _themeUpgradeLightSelector() and the
 * stylesheet _sdThemeCssFromUserStyles() saves as a theme file), so the
 * upgrade can split the old combined light block
 * (`:root, [data-bs-theme="light"]`) of designs nobody opens in the editor.
 *
 * Every function gives byte for byte what its JavaScript twin gives for the
 * same text (tests/designer_theme_vars_test.php runs both): the regular
 * expressions use the whitespace set of JavaScript's \s and trim() rather
 * than PCRE's, end-of-text anchors are \z (JavaScript's $ without the m flag
 * never matches before a final newline), and an empty string is the only
 * false value of a string, as in JavaScript ('0' is a value). Offsets are
 * bytes, where the editor counts UTF-16 units; they are only compared and
 * cut at, never shown, so the result is the same.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_FUNCTIONS_DIR')) {
    exit;
}

// The characters JavaScript's \s and String.prototype.trim() take for
// whitespace, as the inside of a PCRE character class (with the u modifier).
function pg_theme_vars_ws()
{
    return '\x{0009}-\x{000D}\x{0020}\x{00A0}\x{1680}\x{2000}-\x{200A}\x{2028}\x{2029}\x{202F}\x{205F}\x{3000}\x{FEFF}';
}

// JavaScript's trim().
function pg_theme_vars_trim($text)
{
    $ws = pg_theme_vars_ws();
    return (string) preg_replace('/^[' . $ws . ']+|[' . $ws . ']+\z/u', '', (string) $text);
}

// JavaScript's .replace(/\s+$/, '').
function pg_theme_vars_rtrim($text)
{
    return (string) preg_replace('/[' . pg_theme_vars_ws() . ']+\z/u', '', (string) $text);
}

// Whether the text holds anything but whitespace and `;` once its comments
// are taken out: /[^\s;]/.test(text.replace(/\/\*[\s\S]*?\*\//g, '')).
function pg_theme_vars_has_decls($text)
{
    $text = (string) preg_replace('~/\*[\s\S]*?\*/~u', '', (string) $text);
    return (bool) preg_match('/[^' . pg_theme_vars_ws() . ';]/u', $text);
}

// A text with a rule block cut out, tidied as the panel tidies it: no run of
// more than one empty line, no leading whitespace, one newline at the end.
function pg_theme_vars_tidy_cut($text)
{
    $text = (string) preg_replace('/\n{3,}/', "\n\n", (string) $text);
    $text = (string) preg_replace('/^[' . pg_theme_vars_ws() . ']+/u', '', $text);
    return (string) preg_replace('/\n{2,}\z/', "\n", $text, 1);
}

// Variables Bootstrap's own `[data-bs-theme=dark]` block (5.3.8) gives a
// dark value. A light value of one of these must never reach an <html> a
// theme switcher set to dark. The list is _themeDarkOwnVars() in the editor.
function pg_theme_vars_dark_own()
{
    return array(
        '--bs-body-color', '--bs-body-color-rgb', '--bs-body-bg', '--bs-body-bg-rgb',
        '--bs-emphasis-color', '--bs-emphasis-color-rgb',
        '--bs-secondary-color', '--bs-secondary-color-rgb', '--bs-secondary-bg', '--bs-secondary-bg-rgb',
        '--bs-tertiary-color', '--bs-tertiary-color-rgb', '--bs-tertiary-bg', '--bs-tertiary-bg-rgb',
        '--bs-primary-text-emphasis', '--bs-secondary-text-emphasis', '--bs-success-text-emphasis', '--bs-info-text-emphasis',
        '--bs-warning-text-emphasis', '--bs-danger-text-emphasis', '--bs-light-text-emphasis', '--bs-dark-text-emphasis',
        '--bs-primary-bg-subtle', '--bs-secondary-bg-subtle', '--bs-success-bg-subtle', '--bs-info-bg-subtle',
        '--bs-warning-bg-subtle', '--bs-danger-bg-subtle', '--bs-light-bg-subtle', '--bs-dark-bg-subtle',
        '--bs-primary-border-subtle', '--bs-secondary-border-subtle', '--bs-success-border-subtle', '--bs-info-border-subtle',
        '--bs-warning-border-subtle', '--bs-danger-border-subtle', '--bs-light-border-subtle', '--bs-dark-border-subtle',
        '--bs-heading-color', '--bs-link-color', '--bs-link-hover-color', '--bs-link-color-rgb', '--bs-link-hover-color-rgb',
        '--bs-code-color', '--bs-highlight-color', '--bs-highlight-bg',
        '--bs-border-color', '--bs-border-color-translucent',
        '--bs-form-valid-color', '--bs-form-valid-border-color', '--bs-form-invalid-color', '--bs-form-invalid-border-color',
    );
}

// The first rule block at or after byte $from whose selector $pattern
// matches, brace-matched: array(sel_start, open, body_start, body_end, body,
// full), or null. _findRuleBlock() in the editor.
function pg_theme_vars_find_rule_block($source, $pattern, $from = 0)
{
    $source = (string) $source;
    $from = (int) $from;

    if ($from > strlen($source) || !preg_match($pattern, $source, $m, PREG_OFFSET_CAPTURE, $from)) {
        return null;
    }

    $sel_start = $m[0][1];
    $open = strpos($source, '{', $sel_start + strlen($m[0][0]) - 1);

    if ($open === false) {
        return null;
    }

    $depth = 1;
    $i = $open + 1;
    $length = strlen($source);

    while ($i < $length && $depth > 0) {
        $ch = $source[$i];

        if ($ch === '{') {
            $depth++;
        } elseif ($ch === '}') {
            $depth--;
        }

        $i++;
    }

    if ($depth !== 0) {
        return null;
    }

    return array(
        'sel_start'  => $sel_start,
        'open'       => $open,
        'body_start' => $open + 1,
        'body_end'   => $i - 1,
        'body'       => (string) substr($source, $open + 1, $i - 2 - $open),
        'full'       => (string) substr($source, $sel_start, $i - $sel_start),
    );
}

// The selector pattern of a mode: 'dark' matches `[data-bs-theme="dark"]`
// right before `{`; any other mode matches every light form at its first
// selector (the light form, the old combined form, a bare `:root`, a bare
// `[data-bs-theme="light"]`), either quote style. _themeSelectorRe().
function pg_theme_vars_selector_re($mode)
{
    $ws = pg_theme_vars_ws();

    if ($mode === 'dark') {
        return '/\[data-bs-theme=["\']dark["\']\][' . $ws . ']*(?=\{)/u';
    }

    return '/(?::root:not\(\[data-bs-theme=["\']dark["\']\]\)[' . $ws . ']*,[' . $ws . ']*\[data-bs-theme=["\']light["\']\]'
        . '|:root[' . $ws . ']*,[' . $ws . ']*\[data-bs-theme=["\']light["\']\]'
        . '|\[data-bs-theme=["\']light["\']\]'
        . '|:root)[' . $ws . ']*(?=\{)/u';
}

// The first block of $kind ('light', 'shared' or 'dark'), or null. A light
// match is the light block when it starts with `:root:not(`, the shared
// block otherwise. _themeFindBlock().
function pg_theme_vars_find_block($content, $kind)
{
    if ($kind === 'dark') {
        return pg_theme_vars_find_rule_block($content, pg_theme_vars_selector_re('dark'));
    }

    $pattern = pg_theme_vars_selector_re('light');
    $from = 0;

    while (($block = pg_theme_vars_find_rule_block($content, $pattern, $from)) !== null) {
        $block_kind = preg_match('/^:root:not\(/', $block['full']) ? 'light' : 'shared';

        if ($block_kind === $kind) {
            return $block;
        }

        $from = $block['body_end'] + 1;
    }

    return null;
}

// The selector a new block of $kind is written with. _themeCanonicalSelector().
function pg_theme_vars_canonical_selector($kind)
{
    if ($kind === 'dark') {
        return '[data-bs-theme="dark"]';
    }

    if ($kind === 'shared') {
        return ":root,\n[data-bs-theme=\"light\"]";
    }

    return ":root:not([data-bs-theme=\"dark\"]),\n[data-bs-theme=\"light\"]";
}

// The custom properties of a rule body as name => value, split on `;` the
// way the panel reads them. _parseThemeDecls().
function pg_theme_vars_parse_decls($body)
{
    $out = array();
    $body = (string) $body;

    if ($body === '') {
        return $out;
    }

    foreach (explode(';', $body) as $line) {
        $colon = strpos($line, ':');

        if ($colon === false) {
            continue;
        }

        $name = pg_theme_vars_trim(substr($line, 0, $colon));

        if (strpos($name, '--') !== 0) {
            continue;
        }

        $out[$name] = pg_theme_vars_trim((string) substr($line, $colon + 1));
    }

    return $out;
}

// Sets (or, with an empty value, removes) one declaration in the $kind block
// of $content. A missing block is created: a shared one before the dark
// block, the others at the end. A block left without declarations is
// dropped. _themeSetDecl().
function pg_theme_vars_set_decl($content, $kind, $name, $value)
{
    $content = (string) $content;
    $value = (string) $value;
    $block = pg_theme_vars_find_block($content, $kind);

    if ($block === null) {
        if ($value === '') {
            return $content;
        }

        $new_block = pg_theme_vars_canonical_selector($kind) . " {\n  " . $name . ': ' . $value . ";\n}\n";
        $dark = ($kind === 'shared') ? pg_theme_vars_find_block($content, 'dark') : null;

        if ($dark !== null) {
            return substr($content, 0, $dark['sel_start']) . $new_block . "\n" . substr($content, $dark['sel_start']);
        }

        return (($content !== '') ? pg_theme_vars_rtrim($content) . "\n\n" : '') . $new_block;
    }

    $ws = pg_theme_vars_ws();
    $pattern = '/(^|[' . $ws . ']|;)[' . $ws . ']*(' . preg_quote($name, '/') . ')[' . $ws . ']*:[' . $ws . ']*([^;}\n]*)[' . $ws . ']*(;?)/u';
    $has_decl = false;
    $new_body = (string) preg_replace_callback($pattern, function ($m) use (&$has_decl, $value, $ws) {
        $has_decl = true;

        if ($value === '') {
            return (string) preg_replace('/[^' . $ws . ';]/u', '', $m[1]);
        }

        return $m[1] . $m[2] . ': ' . $value . (($m[4] !== '') ? $m[4] : ';');
    }, $block['body']);

    if (!$has_decl && $value === '') {
        return $content;
    }

    if (!$has_decl) {
        $trimmed = pg_theme_vars_rtrim($new_body);
        $new_body = (($trimmed !== '') ? $trimmed . "\n  " : "\n  ") . $name . ': ' . $value . ";\n";
    }

    if (!pg_theme_vars_has_decls($new_body)) {
        return pg_theme_vars_tidy_cut(substr($content, 0, $block['sel_start']) . substr($content, $block['body_end'] + 1));
    }

    return substr($content, 0, $block['body_start']) . $new_body . substr($content, $block['body_end']);
}

// Rewrites the light block the panel wrote before its light and shared
// blocks were apart (`:root, [data-bs-theme="light"]`, every variable
// together): the variables Bootstrap gives a dark value move to the light
// block (the whole block is renamed when nothing else is in it), the rest
// stay as the shared block, which moves before the dark block when it sits
// after it. A bare `:root` or `[data-bs-theme="light"]` block is left as
// written. A second pass changes nothing. _themeUpgradeLightSelector().
function pg_theme_vars_upgrade_light_selector($content)
{
    $content = (string) $content;

    if ($content === '') {
        return $content;
    }

    $ws = pg_theme_vars_ws();
    $pattern = pg_theme_vars_selector_re('light');
    $dark_own = array_flip(pg_theme_vars_dark_own());
    $from = 0;
    $guard = 0;

    while (($block = pg_theme_vars_find_rule_block($content, $pattern, $from)) !== null && $guard++ < 100) {
        $from = $block['body_end'] + 1;

        if (!preg_match('/^:root[' . $ws . ']*,/u', $block['full'])) {
            continue;
        }

        $kept = array();
        $taken = array();

        foreach (explode(';', $block['body']) as $segment) {
            if (preg_match('~^[' . $ws . ']*(?:/\*[\s\S]*?\*/[' . $ws . ']*)*(--[A-Za-z0-9_-]+)[' . $ws . ']*:([\s\S]*)\z~u', $segment, $m)
                && isset($dark_own[$m[1]])) {
                $taken[] = array('name' => $m[1], 'value' => pg_theme_vars_trim($m[2]), 'text' => pg_theme_vars_trim($segment));
            } else {
                $kept[] = $segment;
            }
        }

        $kept_body = implode(';', $kept);
        $kept_empty = !pg_theme_vars_has_decls($kept_body);
        $dark = pg_theme_vars_find_block($content, 'dark');
        $dark_before = ($dark !== null) && ($dark['sel_start'] < $block['sel_start']) && !$kept_empty;

        if (!$taken && !$dark_before) {
            continue;
        }

        $light = pg_theme_vars_find_block($content, 'light');
        $selector_text = substr($block['full'], 0, $block['open'] - $block['sel_start']);
        $shared_part = $kept_empty ? '' : ($taken ? $selector_text . '{' . $kept_body . '}' : $block['full']);
        $light_part = '';

        if ($taken && $light === null) {
            if ($kept_empty) {
                $light_part = pg_theme_vars_canonical_selector('light') . ' {' . $block['body'] . '}';
            } else {
                $lines = array();

                foreach ($taken as $t) {
                    $lines[] = '  ' . $t['text'];
                }

                $light_part = pg_theme_vars_canonical_selector('light') . " {\n" . implode(";\n", $lines) . ";\n}";
            }
        }

        $here = $dark_before ? $light_part : implode("\n\n", array_values(array_filter(array($shared_part, $light_part), 'strlen')));

        if ($here !== '') {
            $content = substr($content, 0, $block['sel_start']) . $here . substr($content, $block['body_end'] + 1);
        } else {
            $content = pg_theme_vars_tidy_cut(substr($content, 0, $block['sel_start']) . substr($content, $block['body_end'] + 1));
        }

        if ($dark_before) {
            $content = substr($content, 0, $dark['sel_start']) . $shared_part . "\n\n" . substr($content, $dark['sel_start']);
        }

        if ($taken && $light !== null) {
            $have = pg_theme_vars_parse_decls($light['body']);

            foreach ($taken as $t) {
                if (!array_key_exists($t['name'], $have)) {
                    $content = pg_theme_vars_set_decl($content, 'light', $t['name'], $t['value']);
                }
            }
        }

        $from = 0;
    }

    return $content;
}

// The stylesheet "Save these colours as a theme" writes from a User Styles
// text: its shared, light and dark blocks, split first, each under its
// selector with the body indented by four spaces. _sdThemeCssFromUserStyles().
function pg_theme_vars_theme_css($content)
{
    $content = pg_theme_vars_upgrade_light_selector($content);
    $out = '';

    foreach (array('shared', 'light', 'dark') as $kind) {
        $block = pg_theme_vars_find_block($content, $kind);

        if ($block !== null && pg_theme_vars_trim($block['body']) !== '') {
            $body = (string) preg_replace('/\n[' . pg_theme_vars_ws() . ']*/u', "\n    ", pg_theme_vars_trim($block['body']));
            $out .= pg_theme_vars_canonical_selector($kind) . " {\n    " . $body . "\n}\n\n";
        }
    }

    return pg_theme_vars_trim($out);
}

// Whether a stylesheet is a theme file "Save these colours as a theme" wrote
// before its light and shared blocks were apart: the old combined light
// block, then optionally the dark block, each body indented by four
// spaces, nothing else (trailing whitespace aside). Only such a file is
// rewritten by the upgrade; a stylesheet written or changed by hand is not.
function pg_theme_vars_is_old_theme_file($css)
{
    return (bool) preg_match(
        '/\A:root,\n\[data-bs-theme="light"\] \{\n    [^{}]*\n\}(?:\n\n\[data-bs-theme="dark"\] \{\n    [^{}]*\n\})?\s*\z/',
        (string) $css
    );
}

// JSON with no escaped slashes and characters beyond ASCII as they are. With
// $as_editor the line separators U+2028 and U+2029 are written as they are
// too, as JSON.stringify() writes them (the editor's asset list); without,
// they are escaped, as pg_design_template_custom_save() writes a template.
function pg_theme_vars_json_encode($value, $as_editor = true)
{
    $flags = JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES;

    if ($as_editor && defined('JSON_UNESCAPED_LINE_TERMINATORS')) {
        $flags |= JSON_UNESCAPED_LINE_TERMINATORS;
    }

    // Floats in the shortest form that reads back the same, as JavaScript
    // writes them, whatever serialize_precision the host sets.
    $precision = function_exists('ini_get') ? ini_get('serialize_precision') : false;

    if ($precision !== false && $precision !== '-1' && function_exists('ini_set')) {
        ini_set('serialize_precision', '-1');
    }

    $json = json_encode($value, $flags);

    if ($precision !== false && $precision !== '-1' && function_exists('ini_set')) {
        ini_set('serialize_precision', $precision);
    }

    return is_string($json) ? $json : null;
}

// A design's asset list (style.style_custom_css, the cssFiles JSON) with the
// light block of every User Styles entry (meta 'user-styles') split, as the
// editor splits it when the design is opened. Null when nothing changes:
// the text is no JSON list, has no User Styles entry, or holds no old block.
// Objects are decoded as objects, so an empty {} stays {}.
function pg_theme_vars_upgrade_assets_json($json)
{
    if (!is_string($json) || trim($json) === '') {
        return null;
    }

    $list = json_decode($json);

    if (!is_array($list)) {
        return null;
    }

    $changed = false;

    foreach ($list as $entry) {
        if (!is_object($entry) || !isset($entry->meta) || $entry->meta !== 'user-styles') {
            continue;
        }

        if (isset($entry->content) && !is_string($entry->content)) {
            continue;
        }

        $content = isset($entry->content) ? $entry->content : '';
        $upgraded = pg_theme_vars_upgrade_light_selector($content);

        if ($upgraded !== $content) {
            $entry->content = $upgraded;
            $changed = true;
        }
    }

    return $changed ? pg_theme_vars_json_encode($list) : null;
}

// A design template made from a design (design_template.template_json) with
// its asset list (assets.css) split as pg_theme_vars_upgrade_assets_json()
// splits a design's. Null when nothing changes or the document is not read.
function pg_theme_vars_upgrade_template_json($json)
{
    if (!is_string($json) || trim($json) === '') {
        return null;
    }

    $template = json_decode($json);

    if (!is_object($template) || !isset($template->assets) || !is_object($template->assets)
        || !isset($template->assets->css) || !is_string($template->assets->css)) {
        return null;
    }

    $css = pg_theme_vars_upgrade_assets_json($template->assets->css);

    if ($css === null) {
        return null;
    }

    $template->assets->css = $css;

    return pg_theme_vars_json_encode($template, false);
}
