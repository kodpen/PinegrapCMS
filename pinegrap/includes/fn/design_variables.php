<?php

/**
 *
 * Pinegrap
 *
 * @package Pinegrap
 * @author Camelback Web Architects, Kodpen
 * @link https://pinegrap.com
 * @copyright 2001-2019 Camelback Consulting, Inc. (DBA Camelback Web Architects)
 * @copyright 2019-2026 Kodpen
 * @license https://opensource.org/licenses/mit-license.html MIT License
 *
 */

if (!defined('PG_FUNCTIONS_DIR')) {
    exit;
}

// ========================= DESIGN VARIABLES ======================================================
//
// A design's variables: named colours, type, spacing and shape values that
// the design's nodes refer to instead of literals. They are kept as one more
// design CSS file, loaded after the palette, that opens with the same
// one-line head as a custom look or palette:
//
//   /*! pg-theme {"kind":"variables","version":1,"look":...,"palette":...,
//                 "values":{...},"custom":[...]} */
//
// The model (an array) has: name, look and palette (the choice the defaults
// were taken from), values (key => value; a colour is array(light, dark)) and
// custom (the designer's own colours, sizes and easing curves), and page
// (the design's page transition) only when one is chosen.
//
// Every variable writes its own --pg-var-<key> token. A value that differs
// from the default it was made from also writes the theme tokens it drives
// (--bs-primary, --pg-font-body, --pg-radius ...), so a file of untouched
// defaults changes nothing a page already reads.
//
// Everything here is pure: no database, no request.

// The head a writer may produce at most: the editor reads only the start of
// a design CSS file for it.
if (!defined('PG_DESIGN_VARIABLES_HEAD_MAX')) {
    define('PG_DESIGN_VARIABLES_HEAD_MAX', 8192);
}

// The groups of the Variables tab, in order.
function pg_design_variable_groups()
{
    return array(
        'color'   => lang('Colour'),
        'type'    => lang('Type'),
        'spacing' => lang('Spacing'),
        'shape'   => lang('Shape'),
    );
}

// Every built-in variable, in order. kind: color (a light and a dark value),
// font (a font stack), length (rem or px within min/max per unit), number
// (min/max), weight (100 to 900), choice (one of options).
function pg_design_variable_list()
{
    $length = function ($group, $label, $rem, $px) {
        return array('group' => $group, 'label' => $label, 'kind' => 'length', 'rem' => $rem, 'px' => $px);
    };
    $shadows = array('' => lang('As the look'));
    foreach (pg_design_look_controls()['shadow']['options'] as $key => $o) {
        $shadows[$key] = $o['label'];
    }
    return array(
        'brand'          => array('group' => 'color', 'label' => lang('Brand'),   'kind' => 'color'),
        'accent'         => array('group' => 'color', 'label' => lang('Accent'),  'kind' => 'color'),
        'neutral'        => array('group' => 'color', 'label' => lang('Neutral'), 'kind' => 'color'),
        'surface'        => array('group' => 'color', 'label' => lang('Surface'), 'kind' => 'color'),
        'text'           => array('group' => 'color', 'label' => lang('Text'),    'kind' => 'color'),
        'success'        => array('group' => 'color', 'label' => lang('Success'), 'kind' => 'color'),
        'warning'        => array('group' => 'color', 'label' => lang('Warning'), 'kind' => 'color'),
        'danger'         => array('group' => 'color', 'label' => lang('Danger'),  'kind' => 'color'),
        'font_heading'   => array('group' => 'type', 'label' => lang('Heading font'), 'kind' => 'font'),
        'font_body'      => array('group' => 'type', 'label' => lang('Text font'),    'kind' => 'font'),
        'size_base'      => $length('type', lang('Base size'), array(0.75, 1.5), array(12, 24)),
        'scale'          => array('group' => 'type', 'label' => lang('Type scale'), 'kind' => 'number', 'min' => 1, 'max' => 2),
        'weight_heading' => array('group' => 'type', 'label' => lang('Heading weight'), 'kind' => 'weight'),
        'weight_body'    => array('group' => 'type', 'label' => lang('Text weight'),    'kind' => 'weight'),
        'line_height'    => array('group' => 'type', 'label' => lang('Line height'), 'kind' => 'number', 'min' => 1, 'max' => 2.5),
        'space_unit'     => $length('spacing', lang('Base unit'),      array(0.25, 2), array(4, 32)),
        'space_section'  => $length('spacing', lang('Section rhythm'), array(1, 12),   array(16, 192)),
        'radius'         => $length('shape', lang('Corners'), array(0, 3), array(0, 48)),
        'border_width'   => array('group' => 'shape', 'label' => lang('Line width'), 'kind' => 'length', 'px' => array(0, 8)),
        'shadow'         => array('group' => 'shape', 'label' => lang('Shadows'), 'kind' => 'choice', 'options' => $shadows),
    );
}

// The model a design starts with: the colours of its palette and the type
// and shape of its look, Bootstrap's own where there is none. $look and
// $palette are style.style_look / style.style_palette values; a file-<id>
// look reads as none (its tokens live in the file manager).
function pg_design_variables_defaults($look, $palette)
{
    $look    = pg_design_look_key($look);
    $palette = pg_design_palette_key($palette);
    $colors  = pg_design_palette_colors($palette);
    $t = pg_design_look_tokens($look);
    $tok = function ($name, $fallback) use ($t) {
        return (isset($t[$name]) && strpos($t[$name], 'var(') === false) ? $t[$name] : $fallback;
    };
    $pair = function ($light, $dark = null) {
        return array('light' => $light, 'dark' => $dark === null ? $light : $dark);
    };
    $sans = 'system-ui, -apple-system, "Segoe UI", Roboto, "Helvetica Neue", "Noto Sans", Arial, sans-serif';
    $body = $tok('--pg-font-body', $sans);
    $model = array(
        'name'    => '',
        'look'    => $look,
        'palette' => $palette,
        'values'  => array(
            'brand'          => $pair(strtolower($colors['primary'])),
            'accent'         => $pair(strtolower($colors['secondary'])),
            'neutral'        => $pair('#6c757d', '#adb5bd'),
            'surface'        => $pair('#ffffff', '#212529'),
            'text'           => $pair('#212529', '#dee2e6'),
            'success'        => $pair('#198754'),
            'warning'        => $pair('#ffc107'),
            'danger'         => $pair('#dc3545'),
            'font_heading'   => $tok('--pg-font-heading', $body),
            'font_body'      => $body,
            'size_base'      => $tok('--pg-font-size', '1rem'),
            'scale'          => '1.25',
            'weight_heading' => $tok('--pg-heading-weight', '500'),
            'weight_body'    => '400',
            'line_height'    => $tok('--pg-line-height', '1.5'),
            'space_unit'     => '1rem',
            'space_section'  => '4rem',
            'radius'         => $tok('--pg-radius', '0.375rem'),
            'border_width'   => $tok('--pg-border-width', '1px'),
            'shadow'         => '',
        ),
        'custom'  => array(),
    );
    // A look token the checks below refuse (a calc(), an em size) gives way
    // to Bootstrap's value rather than to an empty field.
    $plain = array('values' => array(
        'font_heading' => $body, 'size_base' => '1rem', 'weight_heading' => '500',
        'line_height' => '1.5', 'radius' => '0.375rem', 'border_width' => '1px',
    ));
    $plain['values'] += $model['values'];
    return pg_design_variables_normalize($model, $plain);
}

// One value checked against its definition: the value in its written form,
// or null when it is refused.
function pg_design_variable_value($def, $value)
{
    if ($def['kind'] === 'color') {
        $rgb = _pg_theme_rgb(is_scalar($value) ? $value : '');
        return $rgb ? _pg_theme_hex($rgb) : null;
    }
    if (!is_scalar($value)) return null;
    $value = trim((string)$value);
    switch ($def['kind']) {
        case 'font':
            // A font stack: names, quotes and commas only, so nothing can end
            // the declaration or the rule.
            return (preg_match('/^[A-Za-z0-9 ,"\'\-]{1,200}$/', $value) && strpos($value, '""') === false) ? $value : null;
        case 'length':
            if ($value === '0') return '0';
            if (!preg_match('/^(\d{1,3}(?:\.\d{1,4})?)(rem|px)$/', $value, $m) || !isset($def[$m[2]])) return null;
            $n = (float)$m[1];
            return ($n >= $def[$m[2]][0] && $n <= $def[$m[2]][1]) ? _pg_design_variable_num($n) . $m[2] : null;
        case 'number':
            if (!preg_match('/^\d(?:\.\d{1,3})?$/', $value)) return null;
            $n = (float)$value;
            return ($n >= $def['min'] && $n <= $def['max']) ? _pg_design_variable_num($n) : null;
        case 'weight':
            return preg_match('/^[1-9]00$/', $value) ? $value : null;
        case 'choice':
            return array_key_exists($value, $def['options']) ? $value : null;
    }
    return null;
}

// A number in its shortest form: 1.0 -> "1", 0.50 -> "0.5".
function _pg_design_variable_num($n)
{
    $s = rtrim(rtrim(sprintf('%.4F', round((float)$n, 4)), '0'), '.');
    return ($s === '' || $s === '-0') ? '0' : $s;
}

// A model made safe: every built-in variable present and valid (a refused
// value takes its default from $defaults), unknown keys dropped, custom
// variables checked, their keys made slugs and kept once.
function pg_design_variables_normalize($model, $defaults)
{
    $model = is_array($model) ? $model : array();
    $in    = (isset($model['values']) && is_array($model['values'])) ? $model['values'] : array();
    $out = array(
        'name'    => isset($model['name']) && is_scalar($model['name']) ? mb_substr(trim((string)$model['name']), 0, 60) : '',
        'look'    => pg_design_look_key(isset($model['look']) ? $model['look'] : ''),
        'palette' => pg_design_palette_key(isset($model['palette']) ? $model['palette'] : ''),
        'values'  => array(),
        'custom'  => array(),
    );
    foreach (pg_design_variable_list() as $key => $def) {
        $fallback = isset($defaults['values'][$key]) ? $defaults['values'][$key] : null;
        $v = isset($in[$key]) ? $in[$key] : $fallback;
        if ($def['kind'] === 'color') {
            $v = is_array($v) ? $v : array();
            $pair = array();
            foreach (array('light', 'dark') as $mode) {
                $ok = pg_design_variable_value($def, isset($v[$mode]) ? $v[$mode] : '');
                if ($ok === null && isset($fallback[$mode])) $ok = pg_design_variable_value($def, $fallback[$mode]);
                $pair[$mode] = ($ok === null) ? '#000000' : $ok;
            }
            $out['values'][$key] = $pair;
            continue;
        }
        $ok = pg_design_variable_value($def, $v);
        if ($ok === null && $fallback !== null) $ok = pg_design_variable_value($def, $fallback);
        $out['values'][$key] = ($ok === null) ? '' : $ok;
    }
    $size = array('kind' => 'length', 'rem' => array(0, 20), 'px' => array(0, 320));
    $color = array('kind' => 'color');
    $custom = (isset($model['custom']) && is_array($model['custom'])) ? $model['custom'] : array();
    foreach ($custom as $c) {
        if (count($out['custom']) >= 24) break;
        if (!is_array($c) || !isset($c['key'], $c['kind']) || !is_scalar($c['key'])) continue;
        $slug = trim(preg_replace('/[^a-z0-9]+/', '-', strtolower((string)$c['key'])), '-');
        $slug = trim(substr($slug, 0, 30), '-');
        if ($slug === '') continue;
        foreach ($out['custom'] as $seen) {
            if ($seen['key'] === $slug) continue 2;
        }
        $name = isset($c['name']) && is_scalar($c['name']) ? mb_substr(trim((string)$c['name']), 0, 40) : '';
        $row = array('key' => $slug, 'name' => $name !== '' ? $name : $slug, 'kind' => (string)$c['kind']);
        if ($row['kind'] === 'color') {
            $light = pg_design_variable_value($color, isset($c['light']) ? $c['light'] : '');
            if ($light === null) continue;
            $dark = pg_design_variable_value($color, isset($c['dark']) ? $c['dark'] : '');
            $row['light'] = $light;
            $row['dark']  = ($dark === null) ? $light : $dark;
        } elseif ($row['kind'] === 'curve') {
            // An easing curve: its four numbers (pg_fx_curve_numbers()).
            $nums = pg_fx_curve_numbers(isset($c['value']) ? $c['value'] : '');
            if ($nums === null) continue;
            $row['value'] = implode(', ', $nums);
        } elseif ($row['kind'] === 'size') {
            $row['value'] = pg_design_variable_value($size, isset($c['value']) ? $c['value'] : '');
            if ($row['value'] === null) continue;
        } else {
            continue;
        }
        $out['custom'][] = $row;
    }
    $page = pg_design_page_transition_clean(isset($model['page']) ? $model['page'] : null);
    if ($page !== null) $out['page'] = $page;
    return $out;
}

// The easing curves among a model's own variables, for the editor's
// Easing lists: array of array(key, name, value) with value "x1, y1, x2,
// y2". A stored model is read as written, so each row is checked here.
function pg_design_variables_curves($model)
{
    $out = array();
    $custom = (is_array($model) && isset($model['custom']) && is_array($model['custom'])) ? $model['custom'] : array();
    foreach ($custom as $c) {
        if (!is_array($c) || !isset($c['kind'], $c['key'], $c['value']) || $c['kind'] !== 'curve') continue;
        if (!is_string($c['key']) || !preg_match('/^[a-z0-9][a-z0-9-]{0,29}$/', $c['key'])) continue;
        $nums = pg_fx_curve_numbers($c['value']);
        if ($nums === null) continue;
        $name = isset($c['name']) && is_scalar($c['name']) ? mb_substr(trim((string)$c['name']), 0, 40) : '';
        $out[] = array('key' => $c['key'], 'name' => $name !== '' ? $name : $c['key'], 'value' => implode(', ', $nums));
    }
    return $out;
}

// The CSS name of a variable's own token.
function pg_design_variable_token($key, $custom = false)
{
    return '--pg-var-' . ($custom ? 'c-' : '') . str_replace('_', '-', $key);
}

// The stylesheet of a model, with its pg-theme head. $defaults is the model
// the values are compared with (pg_design_variables_defaults() of the
// design's look and palette): only a changed value writes theme tokens.
function pg_design_variables_css($model, $defaults)
{
    $defaults = pg_design_variables_normalize($defaults, null);
    $m = pg_design_variables_normalize($model, $defaults);
    $list = pg_design_variable_list();
    $all = $light = $dark = array();

    foreach ($list as $key => $def) {
        $v = $m['values'][$key];
        $changed = ($v !== $defaults['values'][$key]);
        if ($def['kind'] === 'color') {
            $light[pg_design_variable_token($key)] = $v['light'];
            $dark[pg_design_variable_token($key)]  = $v['dark'];
            if ($changed) {
                list($l, $d) = _pg_design_variable_color_tokens($key, $v['light'], $v['dark']);
                $light += $l;
                $dark  += $d;
            }
            continue;
        }
        if ($v !== '') $all[pg_design_variable_token($key)] = $v;
        if ($changed && $v !== '') $all += _pg_design_variable_tokens($key, $v);
    }
    foreach ($m['custom'] as $c) {
        if ($c['kind'] === 'color') {
            $light[pg_design_variable_token($c['key'], true)] = $c['light'];
            $dark[pg_design_variable_token($c['key'], true)]  = $c['dark'];
        } elseif ($c['kind'] === 'curve') {
            $all[pg_design_variable_token($c['key'], true)] = 'cubic-bezier(' . $c['value'] . ')';
        } else {
            $all[pg_design_variable_token($c['key'], true)] = $c['value'];
        }
    }

    $block = function ($selector, $vars) {
        $css = $selector . " {\n";
        foreach ($vars as $k => $v) $css .= '    ' . $k . ': ' . $v . ";\n";
        return $css . "}\n";
    };
    $head = array('kind' => 'variables', 'version' => 1, 'name' => $m['name'], 'look' => $m['look'],
                  'palette' => $m['palette'], 'values' => $m['values'], 'custom' => $m['custom']);
    $page = isset($m['page']) ? $m['page'] : null;
    if ($page !== null) $head['page'] = $page;
    return pg_design_theme_header($head) . "\n"
        . '/* Pinegrap design variables' . ($m['name'] !== '' ? ': ' . pg_design_theme_comment_text($m['name']) : '') . ". */\n"
        . $block(":root,\n[data-bs-theme]", $all)
        . "\n"
        . $block(":root,\n[data-bs-theme=light]", $light)
        . "\n"
        . $block('[data-bs-theme=dark]', $dark)
        . ($page !== null ? "\n" . pg_design_page_transition_css($page) : '');
}

// ── Page transition ─────────────────────────────────────────────────────
//
// One transition for every page of the design (Settings, Theme, "Page
// transition"): None, Fade or Slide, kept as the model's "page" entry and
// written into the variables file as a cross-document view transition.
// None keeps no entry, so the file stays what it was. A browser without
// view transitions ignores the rules, and everything sits inside the
// no-preference block: a visitor who asks for less motion gets none.

// The sides Slide can come from.
function pg_design_page_transition_sides()
{
    return array('right', 'left', 'below');
}

// A page transition made safe: array(kind, [from], [duration], [easing])
// or null for None and anything refused. duration is ms (0 to 1,000) and,
// like easing, left out to follow the look (--pg-duration, --pg-ease).
function pg_design_page_transition_clean($page)
{
    if (!is_array($page) || !isset($page['kind']) || !is_string($page['kind'])) return null;
    $kind = strtolower(trim($page['kind']));
    if ($kind !== 'fade' && $kind !== 'slide') return null;
    $out = array('kind' => $kind);
    if ($kind === 'slide') {
        $from = isset($page['from']) && is_string($page['from']) ? strtolower(trim($page['from'])) : '';
        $out['from'] = in_array($from, pg_design_page_transition_sides(), true) ? $from : 'right';
    }
    if (isset($page['duration']) && is_numeric($page['duration'])) {
        $out['duration'] = max(0, min(1000, (int)round((float)$page['duration'])));
    }
    if (isset($page['easing'])) {
        $easing = pg_fx_easing_clean($page['easing']);
        if ($easing !== '') $out['easing'] = $easing;
    }
    return $out;
}

// The stylesheet of a page transition, '' for None. Fade is the browser's
// own cross-fade with the design's timing; Slide moves the new page in
// from its side while the old one leaves the other way. The timing is set
// on the old and new pictures too: they inherit the group's duration but
// not its easing.
function pg_design_page_transition_css($page)
{
    $page = pg_design_page_transition_clean($page);
    if ($page === null) return '';
    $duration = isset($page['duration']) ? $page['duration'] . 'ms' : 'var(--pg-duration, 0.25s)';
    $easing = isset($page['easing']) ? $page['easing'] : 'var(--pg-ease, ease)';
    $css = "/* Page transition. */\n"
        . "@media (prefers-reduced-motion: no-preference) {\n"
        . "    @view-transition {\n        navigation: auto;\n    }\n"
        . "    ::view-transition-group(*),\n    ::view-transition-old(*),\n    ::view-transition-new(*) {\n"
        . '        animation-duration: ' . $duration . ";\n"
        . '        animation-timing-function: ' . $easing . ";\n"
        . "    }\n";
    if ($page['kind'] === 'slide') {
        $moves = array('right' => array('100%, 0', '-100%, 0'), 'left' => array('-100%, 0', '100%, 0'), 'below' => array('0, 100%', '0, -100%'));
        list($in, $out) = $moves[$page['from']];
        $css .= "    ::view-transition-old(root) {\n        animation-name: pg-page-out;\n        mix-blend-mode: normal;\n    }\n"
            . "    ::view-transition-new(root) {\n        animation-name: pg-page-in;\n        mix-blend-mode: normal;\n    }\n"
            . "    @keyframes pg-page-out {\n        to { transform: translate(" . $out . "); }\n    }\n"
            . "    @keyframes pg-page-in {\n        from { transform: translate(" . $in . "); }\n    }\n";
    }
    return $css . "}\n";
}

// The page transition a variables file holds, null for none.
function pg_design_variables_page($css)
{
    if (!preg_match('~^/\*! pg-theme (\{.*?\}) \*/~s', (string)$css, $hit)) return null;
    $meta = json_decode($hit[1], true);
    if (!is_array($meta) || !isset($meta['kind'], $meta['page']) || $meta['kind'] !== 'variables') return null;
    return pg_design_page_transition_clean($meta['page']);
}

// Whether a posted model carries the page transition alone: the Look tab
// sets it while the Variables tab never ran, so the stored values must be
// kept rather than reset to the defaults.
function pg_design_variables_page_only($model)
{
    return is_array($model) && !empty($model['page_only']);
}

// The stored model (null: no file) with a page-only post's transition.
// null when nothing is to be written: no file and None.
function pg_design_variables_with_page($stored, $post)
{
    $page = pg_design_page_transition_clean(is_array($post) && isset($post['page']) ? $post['page'] : null);
    if (!is_array($stored)) {
        return $page === null ? null : array('page' => $page);
    }
    unset($stored['page']);
    if ($page !== null) $stored['page'] = $page;
    return $stored;
}

// The name that keeps an element across pages ("Keep across pages"):
// small letters, digits and hyphens, at most 40, starting with a letter
// (a digit first takes "n-"). '' for none, and for the CSS keywords none
// and auto, which name nothing.
function pg_design_view_transition_name_clean($name)
{
    if (!is_string($name)) return '';
    $s = trim(preg_replace('/[^a-z0-9]+/', '-', strtolower(trim($name))), '-');
    if ($s === '' || $s === 'none' || $s === 'auto') return '';
    if (!preg_match('/^[a-z]/', $s)) $s = 'n-' . $s;
    return rtrim(substr($s, 0, 40), '-');
}

// The model of a variables stylesheet, normalized against $defaults; null
// for any other file.
function pg_design_variables_read($css, $defaults)
{
    if (!preg_match('~^/\*! pg-theme (\{.*?\}) \*/~s', (string)$css, $hit)) return null;
    $meta = json_decode($hit[1], true);
    if (!is_array($meta) || !isset($meta['kind']) || $meta['kind'] !== 'variables') return null;
    return pg_design_variables_normalize($meta, $defaults);
}

// The theme tokens a changed colour drives: array(light, dark).
function _pg_design_variable_color_tokens($key, $light_hex, $dark_hex)
{
    $lc = _pg_theme_rgb($light_hex);
    $dc = _pg_theme_rgb($dark_hex);
    $roles = array('brand' => 'primary', 'accent' => 'secondary', 'success' => 'success', 'warning' => 'warning', 'danger' => 'danger');
    if (isset($roles[$key])) {
        $role = $roles[$key];
        list($l, $d) = _pg_theme_role($role, $lc);
        // A line or a mark the palette set for a pale colour would outlive
        // it: the colour itself is the line unless the role says otherwise.
        $l += array('--pg-' . $role . '-line' => _pg_theme_hex($lc), '--pg-' . $role . '-line-rgb' => _pg_theme_rgb_list($lc));
        if ($role === 'primary' && !isset($l['--pg-check-mark'])) {
            foreach (_pg_theme_control_marks('#ffffff') as $name => $image) $l['--pg-' . $name . '-mark'] = $image;
        }
        if ($dark_hex !== $light_hex) {
            list($dl, $d) = _pg_theme_role($role, $dc);
            foreach (array('--bs-' . $role, '--bs-' . $role . '-rgb', '--pg-on-' . $role, '--pg-' . $role . '-hover', '--pg-' . $role . '-active') as $k) {
                $d[$k] = $dl[$k];
            }
        }
        $d += array('--pg-' . $role . '-line' => _pg_theme_hex($dc), '--pg-' . $role . '-line-rgb' => _pg_theme_rgb_list($dc));
        return array($l, $d);
    }
    $names = array('neutral' => '--bs-secondary-color', 'surface' => '--bs-body-bg', 'text' => '--bs-body-color');
    $n = $names[$key];
    return array(
        array($n => _pg_theme_hex($lc), $n . '-rgb' => _pg_theme_rgb_list($lc)),
        array($n => _pg_theme_hex($dc), $n . '-rgb' => _pg_theme_rgb_list($dc)),
    );
}

// The theme tokens a changed type, spacing or shape value drives. The type
// scale and the spacing are read only through their own tokens.
function _pg_design_variable_tokens($key, $v)
{
    switch ($key) {
        case 'font_heading':   return array('--pg-font-heading' => $v);
        case 'font_body':      return array('--pg-font-body' => $v);
        case 'size_base':      return array('--pg-font-size' => $v);
        case 'weight_heading': return array('--pg-heading-weight' => $v);
        case 'weight_body':    return array('--bs-body-font-weight' => $v);
        case 'line_height':    return array('--pg-line-height' => $v);
        case 'border_width':   return array('--pg-border-width' => $v);
        case 'radius':
            // The look's ladder of corners, in the steps of a custom look.
            preg_match('/^([0-9.]+)(rem|px)?$/', $v, $m);
            $r = (float)$m[1];
            $u = ($r > 0 && isset($m[2])) ? $m[2] : '';
            $sm = _pg_design_variable_num($r * 2 / 3) . $u;
            $md = _pg_design_variable_num($r) . $u;
            $lg = _pg_design_variable_num($r * 4 / 3) . $u;
            $xl = _pg_design_variable_num($r * 2) . $u;
            return array(
                '--pg-radius-sm' => $sm, '--pg-radius' => $md, '--pg-radius-lg' => $lg, '--pg-radius-xl' => $xl,
                '--pg-radius-xxl' => _pg_design_variable_num($r * 8 / 3) . $u,
                '--pg-radius-input' => $md, '--pg-radius-card' => $lg, '--pg-radius-badge' => $sm,
                '--pg-radius-menu' => $md, '--pg-radius-modal' => $xl,
                '--pg-radius-btn' => $md, '--pg-radius-btn-sm' => $sm, '--pg-radius-btn-lg' => $lg,
            );
        case 'shadow':
            $all = _pg_design_look_shadows();
            if (!isset($all[$v])) return array();
            return array(
                '--pg-shadow-sm' => $all[$v][0], '--pg-shadow' => $all[$v][1], '--pg-shadow-lg' => $all[$v][2],
                '--pg-card-shadow' => $all[$v][3], '--pg-card-shadow-hover' => $all[$v][4],
            );
    }
    return array();
}

// Text and muted text on the surface, light and dark: array of
// array(text, mode, ratio, ok), ok at 4.5:1 or more.
function pg_design_variables_contrast($model)
{
    $values = (isset($model['values']) && is_array($model['values'])) ? $model['values'] : array();
    $rows = array();
    foreach (array('text', 'neutral') as $key) {
        foreach (array('light', 'dark') as $mode) {
            $fg = isset($values[$key][$mode]) ? _pg_theme_rgb($values[$key][$mode]) : null;
            $bg = isset($values['surface'][$mode]) ? _pg_theme_rgb($values['surface'][$mode]) : null;
            if (!$fg || !$bg) continue;
            $ratio = round(_pg_theme_contrast($fg, $bg), 2);
            $rows[] = array('text' => $key, 'mode' => $mode, 'ratio' => $ratio, 'ok' => $ratio >= 4.5);
        }
    }
    return $rows;
}

// A model carried over to another look or palette: a built-in value still
// equal to what $old_defaults gave takes what $new_defaults gives, so a
// brand colour never chosen follows the new palette, while a value the
// designer changed stays. The look and palette become the new ones.
function pg_design_variables_rebase($model, $old_defaults, $new_defaults)
{
    $old = pg_design_variables_normalize($old_defaults, null);
    $new = pg_design_variables_normalize($new_defaults, null);
    $m = pg_design_variables_normalize($model, $old);
    foreach ($m['values'] as $key => $v) {
        if ($v === $old['values'][$key]) $m['values'][$key] = $new['values'][$key];
    }
    $m['look'] = $new['look'];
    $m['palette'] = $new['palette'];
    return pg_design_variables_normalize($m, $new);
}

// What the Variables tab draws and the canvas tries on, for a model ($model
// null: the defaults) on the design's current look and palette: the model
// rebased when it was made from another choice, the defaults, the
// stylesheet and the contrast rows.
function pg_design_variables_state($model, $look, $palette)
{
    $defaults = pg_design_variables_defaults($look, $palette);
    if (!is_array($model)) {
        $m = $defaults;
    } else {
        $m_look    = pg_design_look_key(isset($model['look']) ? $model['look'] : '');
        $m_palette = pg_design_palette_key(isset($model['palette']) ? $model['palette'] : '');
        $m = ($m_look === $defaults['look'] && $m_palette === $defaults['palette'])
            ? pg_design_variables_normalize($model, $defaults)
            : pg_design_variables_rebase($model, pg_design_variables_defaults($m_look, $m_palette), $defaults);
    }
    return array(
        'model'    => $m,
        'defaults' => $defaults,
        'css'      => pg_design_variables_css($m, $defaults),
        'contrast' => pg_design_variables_contrast($m),
    );
}

// The rows of the Variables tab, for the editor: key, group, label, kind,
// the bounds of a length or number and the options of a choice, in order.
function pg_design_variables_client_list()
{
    $rows = array();
    foreach (pg_design_variable_list() as $key => $def) {
        $row = array('key' => $key, 'group' => $def['group'], 'label' => $def['label'], 'kind' => $def['kind']);
        foreach (array('rem', 'px', 'min', 'max') as $k) {
            if (isset($def[$k])) $row[$k] = $def[$k];
        }
        if (isset($def['options'])) {
            $row['options'] = array();
            foreach ($def['options'] as $value => $label) $row['options'][] = array('value' => (string)$value, 'label' => $label);
        }
        $rows[] = $row;
    }
    $groups = array();
    foreach (pg_design_variable_groups() as $key => $label) $groups[] = array('key' => $key, 'label' => $label);
    return array('groups' => $groups, 'rows' => $rows);
}

// ── The start questions ─────────────────────────────────────────────────
//
// Five questions, each can be skipped: the brand colour, a second colour (or
// "choose for me"), the feel (a look), the type feel and roomy or compact.
// Asked by the New design window after a start's own form and again from
// the Variables tab's "Ask me again" (assets/js/designer_questions.js). The
// answers only fill the model: a skipped one keeps the look's and the
// palette's default, so skipping all five opens the design as before.

// What the questions part draws: the words, the swatches, the looks (the
// Theme pane's names and lines, the first `shown` on show), the type feels
// with the variables each sets, and the two rooms.
function pg_design_variables_questions()
{
    $c = pg_design_look_controls();
    $font = function ($group, $key) use ($c) { return $c[$group]['options'][$key]['value']; };
    $looks = array();
    foreach (pg_design_looks() as $key => $l) {
        $p = $l['preview'];
        $looks[] = array('key' => $key, 'name' => $l['name'], 'line' => $l['description'],
            'radius' => (int)$p['btn_radius'], 'caps' => !empty($p['caps']));
    }
    return array(
        'steps' => array(
            'brand'  => array('label' => lang('Brand colour'), 'ask' => lang('What is your brand colour?'),
                              'why' => lang('Buttons, links and highlights take it. A logo\'s main colour is a good answer.')),
            'accent' => array('label' => lang('Second colour'), 'ask' => lang('A second colour?'),
                              'why' => lang('It carries the quieter buttons and the accents beside the brand colour.')),
            'look'   => array('label' => lang('Feel'), 'ask' => lang('How should it feel?'),
                              'why' => lang('The feel sets the look: corners, shadows, lines and how buttons behave.')),
            'type'   => array('label' => lang('Fonts'), 'ask' => lang('Which type feel?'),
                              'why' => lang('It chooses the heading and text fonts from the fonts the site ships, and their weights.')),
            'room'   => array('label' => lang('Space'), 'ask' => lang('Roomy or compact?'),
                              'why' => lang('It sets the base unit and the distance between sections.')),
        ),
        'swatches' => array('#2563eb', '#0f766e', '#047857', '#4d7c0f', '#c2410c', '#e11d48', '#7c3aed', '#1e40af', '#be123c', '#d97706'),
        'looks' => $looks,
        'shown' => 6,
        'types' => array(
            'clean'     => array('name' => lang('Clean'), 'line' => lang('One sans serif for headings and text.'),
                                 'values' => array('font_heading' => $font('font_heading', 'modern'), 'font_body' => $font('font_body', 'modern'), 'weight_heading' => '700', 'size_base' => '1rem')),
            'classic'   => array('name' => lang('Classic'), 'line' => lang('Serif headings over a sans serif text.'),
                                 'values' => array('font_heading' => $font('font_heading', 'serif'), 'font_body' => $font('font_body', 'system'), 'weight_heading' => '600', 'size_base' => '1rem')),
            'friendly'  => array('name' => lang('Friendly'), 'line' => lang('Round letters, a little larger.'),
                                 'values' => array('font_heading' => $font('font_heading', 'rounded-4'), 'font_body' => $font('font_body', 'rounded-4'), 'weight_heading' => '800', 'size_base' => '1.0625rem')),
            'technical' => array('name' => lang('Technical'), 'line' => lang('Narrow headings, figures in one width.'),
                                 'values' => array('font_heading' => $font('font_heading', 'grotesk'), 'font_body' => $font('font_body', 'system'), 'weight_heading' => '600', 'size_base' => '0.9375rem')),
        ),
        'rooms' => array(
            'roomy'   => array('name' => lang('Roomy'), 'line' => lang('More air around every block. Good for a brand or a story.'),
                               'values' => array('space_unit' => '1.25rem', 'space_section' => '6rem')),
            'compact' => array('name' => lang('Compact'), 'line' => lang('More on one screen. Good for a catalogue or a lot to read.'),
                               'values' => array('space_unit' => '0.75rem', 'space_section' => '2.5rem')),
        ),
        't' => array(
            'pick'      => lang('Pick a colour'),
            'auto'      => lang('Choose for me'),
            'autoLine'  => lang('A colour that sits well beside {var:1} is worked out.'),
            'own'       => lang('I will pick'),
            'ownLine'   => lang('Choose it as you chose the brand colour.'),
            'allLooks'  => lang(array('string' => 'Show all {var:1} looks', 'vars' => count($looks))),
            'button'    => lang('Button'),
            'link'      => lang('A link'),
            'highlight' => lang('Highlight'),
            'back'      => lang('Back'),
            'skip'      => lang('Skip'),
            'next'      => lang('Next'),
            'cancel'    => lang('Cancel'),
            'create'    => lang('Create design'),
            'use'       => lang('Use these answers'),
            'hint'      => lang('Every answer can be changed later in Settings, Theme, Variables.'),
            'replace'   => lang('Your answers replace the values of the four groups. Your own variables and every bound control stay.'),
            'colour'    => lang('Brand colour'),
        ),
    );
}

// The answers of a request (an array or its JSON), each checked: a brand
// and a picked second colour are hex colours, the second colour may be
// 'auto', the look a built-in look, the type and room their keys. A refused
// or missing answer reads as '' (skipped).
function pg_design_variables_answers($raw)
{
    if (is_string($raw)) $raw = json_decode($raw, true);
    $raw = is_array($raw) ? $raw : array();
    $get = function ($k) use ($raw) { return (isset($raw[$k]) && is_string($raw[$k])) ? trim($raw[$k]) : ''; };
    $hex = function ($v) { return preg_match('/^#[0-9a-fA-F]{6}$/', $v) ? strtolower($v) : ''; };
    $q = pg_design_variables_questions();
    $looks = pg_design_looks();
    $accent = $get('accent');
    return array(
        'brand'  => $hex($get('brand')),
        'accent' => ($accent === 'auto') ? 'auto' : $hex($accent),
        'look'   => isset($looks[$get('look')]) ? $get('look') : '',
        'type'   => isset($q['types'][$get('type')]) ? $get('type') : '',
        'room'   => isset($q['rooms'][$get('room')]) ? $get('room') : '',
    );
}

// A colour that sits beside $hex: its hue turned by 40 degrees, the
// saturation kept within a range a button can carry, the lightness kept.
function pg_design_variables_companion($hex)
{
    $rgb = _pg_theme_rgb($hex);
    if (!$rgb) return '#6c757d';
    list($r, $g, $b) = array($rgb[0] / 255, $rgb[1] / 255, $rgb[2] / 255);
    $max = max($r, $g, $b);
    $min = min($r, $g, $b);
    $l = ($max + $min) / 2;
    $d = $max - $min;
    $h = 0.0;
    $s = 0.0;
    if ($d > 0) {
        $s = $d / (1 - abs(2 * $l - 1));
        if ($max === $r)     $h = fmod(($g - $b) / $d, 6);
        elseif ($max === $g) $h = ($b - $r) / $d + 2;
        else                 $h = ($r - $g) / $d + 4;
        $h *= 60;
    }
    $h = fmod($h + 40 + 360, 360);
    $s = min(0.85, max(0.35, $s));
    $l = min(0.55, max(0.3, $l));
    $c = (1 - abs(2 * $l - 1)) * $s;
    $x = $c * (1 - abs(fmod($h / 60, 2) - 1));
    $m = $l - $c / 2;
    $seg = array(array($c, $x, 0), array($x, $c, 0), array(0, $c, $x), array(0, $x, $c), array($x, 0, $c), array($c, 0, $x));
    $p = $seg[(int)floor($h / 60) % 6];
    return _pg_theme_hex(array((int)round(($p[0] + $m) * 255), (int)round(($p[1] + $m) * 255), (int)round(($p[2] + $m) * 255)));
}

// The model the answers give: $model (null: the defaults) with the four
// groups set back to $defaults, then each answered question's variables
// set. The designer's own variables stay.
function pg_design_variables_from_answers($model, $answers, $defaults)
{
    $m = pg_design_variables_normalize(is_array($model) ? $model : $defaults, $defaults);
    $m['values'] = $defaults['values'];
    $q = pg_design_variables_questions();
    if ($answers['brand'] !== '') {
        $m['values']['brand'] = array('light' => $answers['brand'], 'dark' => $answers['brand']);
    }
    $accent = $answers['accent'];
    if ($accent === 'auto') {
        $accent = pg_design_variables_companion($answers['brand'] !== '' ? $answers['brand'] : $m['values']['brand']['light']);
    }
    if ($accent !== '') {
        $m['values']['accent'] = array('light' => $accent, 'dark' => $accent);
    }
    if ($answers['type'] !== '') {
        $m['values'] = array_merge($m['values'], $q['types'][$answers['type']]['values']);
    }
    if ($answers['room'] !== '') {
        $m['values'] = array_merge($m['values'], $q['rooms'][$answers['room']]['values']);
    }
    return pg_design_variables_normalize($m, $defaults);
}

// A new design started from the answers ($raw: the window's JSON): the look
// the feel chose ($look when it was skipped), and, when a variable was
// answered, the model and the stylesheet the editor tries on and its first
// save writes. Nothing answered: the design opens as before.
function pg_design_variables_start($raw, $look, $palette)
{
    $a = pg_design_variables_answers($raw);
    $look = ($a['look'] !== '') ? $a['look'] : $look;
    if ($a['brand'] === '' && $a['accent'] === '' && $a['type'] === '' && $a['room'] === '') {
        return array('look' => $look, 'model' => null, 'css' => '');
    }
    $defaults = pg_design_variables_defaults($look, $palette);
    $model = pg_design_variables_from_answers(null, $a, $defaults);
    return array('look' => $look, 'model' => $model, 'css' => pg_design_variables_css($model, $defaults));
}

// ── Export and Import: a Design Tokens file ─────────────────────────────
//
// The Variables tab's "Export" writes the model as a JSON file in the Design
// Tokens community format (a tree of groups whose leaves carry "$value" and
// "$type"); "Import" reads such a file, from this editor or another tool,
// into the model. A colour's dark value rides in the token's
// "$extensions"."com.pinegrap".dark; the shadow preset in the shape group's
// own "$extensions". Nothing is written here: the tab tries the imported
// model on and the design's save writes the file.

// The largest file Import reads, in bytes.
if (!defined('PG_DESIGN_TOKENS_MAX')) {
    define('PG_DESIGN_TOKENS_MAX', 262144);
}

// The name of an exported file: the design's name made a slug.
function pg_design_variables_tokens_file_name($design_name)
{
    $slug = strtolower(trim((string)preg_replace('/[^a-zA-Z0-9]+/', '-', (string)$design_name), '-'));
    return ($slug === '' ? 'design' : trim(substr($slug, 0, 60), '-')) . '.tokens.json';
}

// One value as a token, or null when it has no token form.
function _pg_design_token_out($kind, $v)
{
    switch ($kind) {
        case 'color':
            return array('$type' => 'color', '$value' => $v['light'],
                         '$extensions' => array('com.pinegrap' => array('dark' => $v['dark'])));
        case 'font':
            $names = array();
            foreach (explode(',', (string)$v) as $n) {
                $n = trim($n, " \t\"'");
                if ($n !== '') $names[] = $n;
            }
            if (!$names) return null;
            return array('$type' => 'fontFamily', '$value' => count($names) === 1 ? $names[0] : $names);
        case 'length':
        case 'size':
            if ((string)$v === '0') return array('$type' => 'dimension', '$value' => array('value' => 0, 'unit' => 'px'));
            if (!preg_match('/^([\d.]+)(rem|px)$/', (string)$v, $m)) return null;
            return array('$type' => 'dimension', '$value' => array('value' => $m[1] + 0, 'unit' => $m[2]));
        case 'number':
            return ((string)$v === '') ? null : array('$type' => 'number', '$value' => (float)$v + 0);
        case 'weight':
            return ((string)$v === '') ? null : array('$type' => 'fontWeight', '$value' => (int)$v);
        case 'curve':
            $nums = pg_fx_curve_numbers((string)$v);
            return $nums === null ? null : array('$type' => 'cubicBezier', '$value' => array_map('floatval', $nums));
    }
    return null;
}

// The tokens of a model: array('tokens' => the tree, 'count' => variables
// written). The groups are the tab's (color, type, spacing, shape) and "own".
function pg_design_variables_tokens_export($model)
{
    $tree  = array('$description' => 'Design variables of a Pinegrap design');
    $count = 0;
    foreach (pg_design_variable_list() as $key => $def) {
        $v = isset($model['values'][$key]) ? $model['values'][$key] : null;
        if ($v === null) continue;
        if ($key === 'shadow') {
            if ($v === '') continue;
            $tree['shape']['$extensions']['com.pinegrap']['shadow'] = $v;
            $count++;
            continue;
        }
        $t = _pg_design_token_out($def['kind'], $v);
        if ($t === null) continue;
        $tree[$def['group']][str_replace('_', '-', $key)] = $t;
        $count++;
    }
    foreach ((isset($model['custom']) && is_array($model['custom'])) ? $model['custom'] : array() as $c) {
        $t = _pg_design_token_out($c['kind'], $c['kind'] === 'color' ? $c : $c['value']);
        if ($t === null) continue;
        $t['$description'] = $c['name'];
        $tree['own'][$c['key']] = $t;
        $count++;
    }
    return array('tokens' => $tree, 'count' => $count);
}

// The text of an exported file.
function pg_design_variables_tokens_text($model)
{
    $e = pg_design_variables_tokens_export($model);
    return json_encode($e['tokens'], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n";
}

// Every token of a parsed file, in file order: path (list of names), type
// (its own or the nearest group's "$type"), value, description, extensions.
function _pg_design_tokens_walk($node, $path, $type, &$out, $depth)
{
    if (!is_array($node) || $depth > 12 || count($out) >= 2000) return;
    if (isset($node['$type']) && is_string($node['$type'])) $type = $node['$type'];
    if (array_key_exists('$value', $node)) {
        $out[] = array(
            'path'  => $path,
            'type'  => $type,
            'value' => $node['$value'],
            'desc'  => isset($node['$description']) && is_string($node['$description']) ? $node['$description'] : '',
            'ext'   => isset($node['$extensions']['com.pinegrap']) && is_array($node['$extensions']['com.pinegrap'])
                ? $node['$extensions']['com.pinegrap'] : array(),
        );
        return;
    }
    foreach ($node as $name => $child) {
        if ((string)$name === '' || $name[0] === '$') continue;
        $p = $path;
        $p[] = (string)$name;
        _pg_design_tokens_walk($child, $p, $type, $out, $depth + 1);
    }
}

// A "{group.token}" reference followed to its value (at most eight steps),
// or the value itself; null when it leads nowhere.
function _pg_design_tokens_resolve($value, $index)
{
    for ($i = 0; $i < 8; $i++) {
        if (!is_string($value) || !preg_match('/^\{([^{}]+)\}$/', trim($value), $m)) return $value;
        if (!isset($index[$m[1]])) return null;
        $value = $index[$m[1]]['value'];
    }
    return null;
}

// A colour value (a hex string, or the 2025 object with "hex" or sRGB
// "components") as #rrggbb, or null.
function _pg_design_tokens_color($v)
{
    if (is_array($v)) {
        if (isset($v['hex']) && is_string($v['hex'])) {
            $v = $v['hex'];
        } elseif (isset($v['components']) && is_array($v['components']) && count($v['components']) === 3
            && (!isset($v['colorSpace']) || $v['colorSpace'] === 'srgb')) {
            $rgb = array();
            foreach ($v['components'] as $c) {
                if (!is_numeric($c)) return null;
                $rgb[] = max(0, min(1, (float)$c)) * 255;
            }
            return _pg_theme_hex($rgb);
        } else {
            return null;
        }
    }
    if (!is_string($v)) return null;
    $v = trim($v);
    // #rrggbbaa: the alpha is dropped, a variable is an opaque colour.
    if (preg_match('/^#([0-9a-f]{6})[0-9a-f]{2}$/i', $v, $m)) $v = '#' . $m[1];
    $rgb = _pg_theme_rgb($v);
    return $rgb ? _pg_theme_hex($rgb) : null;
}

// A dimension ("16px", or the 2025 object {value, unit}) as written in a
// model ("16px", "1rem"), or null.
function _pg_design_tokens_dimension($v)
{
    if (is_array($v) && isset($v['value'], $v['unit']) && is_numeric($v['value']) && is_string($v['unit'])) {
        $v = _pg_design_variable_num($v['value']) . $v['unit'];
    }
    if (is_int($v) || is_float($v)) return ((float)$v == 0) ? '0' : null;
    if (!is_string($v)) return null;
    $v = strtolower(trim($v));
    if ($v === '0') return '0';
    if (!preg_match('/^(\d{1,3}(?:\.\d{1,4})?)(rem|px)$/', $v, $m)) return null;
    // Zero has one form, so a file read back compares equal.
    return ((float)$m[1] == 0) ? '0' : $v;
}

// The built-in variable a token's name stands for: its key in
// pg_design_variable_list(), or ''. Our own export's names and a few common
// names of other tools.
function _pg_design_tokens_key($path)
{
    $name = strtolower((string)preg_replace('/[^a-zA-Z0-9]+/', '_', (string)end($path)));
    $name = trim($name, '_');
    $aliases = array(
        'primary' => 'brand', 'secondary' => 'accent', 'background' => 'surface', 'foreground' => 'text',
        'error' => 'danger', 'muted' => 'neutral', 'gray' => 'neutral', 'grey' => 'neutral',
        'font_headings' => 'font_heading', 'font_text' => 'font_body', 'border_radius' => 'radius', 'corners' => 'radius',
    );
    if (isset($aliases[$name])) $name = $aliases[$name];
    $list = pg_design_variable_list();
    return (isset($list[$name]) && $name !== 'shadow') ? $name : '';
}

// The kind of a token the model would take: color, size, font, number or
// weight; otherwise the token's own type (gradient, duration ...).
function _pg_design_tokens_kind($type, $value)
{
    $map = array('color' => 'color', 'dimension' => 'size', 'fontfamily' => 'font', 'number' => 'number', 'fontweight' => 'weight');
    $t = strtolower((string)$type);
    if (isset($map[$t])) return $map[$t];
    if ($t !== '') return $t;
    if (_pg_design_tokens_color($value) !== null) return 'color';
    if (_pg_design_tokens_dimension($value) !== null) return 'size';
    return 'other';
}

// Reads the text of a tokens file into a model ($model the tab's, $defaults
// its look and palette's).
// Refused: array('ok' => false, 'reason' => the line the tab shows); nothing
// changes. Read: array('ok' => true, 'model', 'tokens' (all in the file),
// 'changed' (values of this design that change), 'groups' (changed per
// group, 'own' for own variables the file rewrites), 'own' (new own variables), 'own_kinds' (color, size), 'left' and
// 'left_kinds' (kind => count of the tokens without a place)).
function pg_design_variables_tokens_import($text, $model, $defaults)
{
    $text = (string)$text;
    if (strlen($text) > PG_DESIGN_TOKENS_MAX) {
        return array('ok' => false, 'reason' => lang('It is larger than a tokens file can be.'));
    }
    if (strncmp($text, "\xEF\xBB\xBF", 3) === 0) $text = substr($text, 3);
    $data = json_decode($text, true);
    if (!is_array($data)) {
        return array('ok' => false, 'reason' => lang('It is not a JSON file.'));
    }
    $tokens = array();
    _pg_design_tokens_walk($data, array(), '', $tokens, 0);
    $shadow = isset($data['shape']['$extensions']['com.pinegrap']['shadow']) ? $data['shape']['$extensions']['com.pinegrap']['shadow'] : null;
    if (!$tokens && $shadow === null) {
        return array('ok' => false, 'reason' => lang('It is not a Design Tokens file: no entry in it has a "$value".'));
    }
    $index = array();
    foreach ($tokens as $t) $index[implode('.', $t['path'])] = $t;

    $m = pg_design_variables_normalize($model, $defaults);
    $old = $m;
    $list = pg_design_variable_list();
    $size_def = array('kind' => 'length', 'rem' => array(0, 20), 'px' => array(0, 320));
    $color_def = array('kind' => 'color');
    $used = 0;
    $own = array('color' => 0, 'size' => 0);
    $left = array();
    $own_keys = array();
    foreach ($m['custom'] as $i => $c) $own_keys[$c['key']] = $i;

    foreach ($tokens as $t) {
        $value = _pg_design_tokens_resolve($t['value'], $index);
        $kind  = _pg_design_tokens_kind($t['type'], $value);
        if ($value === null) {
            $left['reference'] = (isset($left['reference']) ? $left['reference'] : 0) + 1;
            continue;
        }
        $key = _pg_design_tokens_key($t['path']);
        $ok = null;
        if ($key !== '') {
            $def = $list[$key];
            $want = $def['kind'] === 'length' ? 'size' : $def['kind'];
            if ($want === $kind) {
                if ($kind === 'color') {
                    $light = _pg_design_tokens_color($value);
                    if ($light !== null) {
                        $dark = isset($t['ext']['dark']) ? _pg_design_tokens_color($t['ext']['dark']) : null;
                        $m['values'][$key] = array('light' => $light, 'dark' => $dark !== null ? $dark : $m['values'][$key]['dark']);
                        $ok = true;
                    }
                } else {
                    if ($kind === 'font' && is_array($value)) {
                        $names = array();
                        foreach ($value as $n) {
                            if (is_string($n)) $names[] = (preg_match('/\s/', trim($n)) ? '"' . trim($n) . '"' : trim($n));
                        }
                        $value = implode(', ', $names);
                    } elseif ($kind === 'size') {
                        $value = _pg_design_tokens_dimension($value);
                    } elseif (is_int($value) || is_float($value)) {
                        $value = _pg_design_variable_num($value);
                    }
                    $v = ($value === null) ? null : pg_design_variable_value($def, $value);
                    if ($v !== null) {
                        $m['values'][$key] = $v;
                        $ok = true;
                    }
                }
            }
        } elseif ($kind === 'color' || $kind === 'size') {
            $slug = trim(substr(trim(strtolower((string)preg_replace('/[^a-zA-Z0-9]+/', '-', implode('-', $t['path'] && $t['path'][0] === 'own' ? array_slice($t['path'], 1) : $t['path']))), '-'), 0, 30), '-');
            $row = null;
            if ($slug !== '' && $kind === 'color') {
                $light = _pg_design_tokens_color($value);
                $dark = isset($t['ext']['dark']) ? _pg_design_tokens_color($t['ext']['dark']) : null;
                if ($light !== null) $row = array('kind' => 'color', 'light' => $light, 'dark' => $dark !== null ? $dark : $light);
            } elseif ($slug !== '') {
                $v = _pg_design_tokens_dimension($value);
                $v = ($v === null) ? null : pg_design_variable_value($size_def, $v);
                if ($v !== null) $row = array('kind' => 'size', 'value' => $v);
            }
            if ($row !== null) {
                $name = $t['desc'] !== '' ? $t['desc'] : implode(' ', $t['path'] && $t['path'][0] === 'own' ? array_slice($t['path'], 1) : $t['path']);
                $row = array('key' => $slug, 'name' => mb_substr($name, 0, 40)) + $row;
                if (isset($own_keys[$slug])) {
                    $m['custom'][$own_keys[$slug]] = $row;
                    $ok = true;
                } elseif (count($m['custom']) < 24) {
                    $own_keys[$slug] = count($m['custom']);
                    $m['custom'][] = $row;
                    $own[$row['kind']]++;
                    $ok = true;
                } else {
                    $left['limit'] = (isset($left['limit']) ? $left['limit'] : 0) + 1;
                    continue;
                }
            }
        }
        if ($ok) {
            $used++;
        } else {
            $left[$kind] = (isset($left[$kind]) ? $left[$kind] : 0) + 1;
        }
    }
    if ($shadow !== null) {
        $v = is_scalar($shadow) ? pg_design_variable_value($list['shadow'], $shadow) : null;
        if ($v !== null) {
            $m['values']['shadow'] = $v;
            $used++;
        } else {
            $left['shadow'] = (isset($left['shadow']) ? $left['shadow'] : 0) + 1;
        }
    }
    if ($used === 0) {
        return array('ok' => false, 'reason' => lang('None of its tokens is a colour, a size, a font or a number.'));
    }
    $m = pg_design_variables_normalize($m, $defaults);

    $groups = array('color' => 0, 'type' => 0, 'spacing' => 0, 'shape' => 0, 'own' => 0);
    foreach ($list as $key => $def) {
        if ($m['values'][$key] !== $old['values'][$key]) $groups[$def['group']]++;
    }
    foreach ($old['custom'] as $c) {
        foreach ($m['custom'] as $n) {
            if ($n['key'] === $c['key'] && $n !== $c) $groups['own']++;
        }
    }
    $changed = array_sum($groups);
    return array(
        'ok'         => true,
        'model'      => $m,
        'tokens'     => count($tokens) + ($shadow !== null ? 1 : 0),
        'changed'    => $changed,
        'groups'     => $groups,
        'own'        => $own['color'] + $own['size'],
        'own_kinds'  => $own,
        'left'       => array_sum($left),
        'left_kinds' => $left,
    );
}
