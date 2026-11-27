<?php

/**
 *
 * Pinegrap
 *
 * @package Pinegrap
 * @author Kodpen
 * @link https://pinegrap.com
 * @copyright 2019-2026 Kodpen
 * @license https://opensource.org/licenses/mit-license.html MIT License
 *
 */

if (!defined('PG_FUNCTIONS_DIR')) {
    exit;
}

// ========================= DESIGN STYLE GUIDE ====================================================
// The model of a design's style guide sheet: what the design is made of, in
// the order the sheet shows it. Pure: the caller gives the design's
// variables (or null when it has none yet), the defaults of its look and
// palette, its own stylesheet and its shared component rows; nothing is read
// or written here.

// Own text classes the Type block lists at most.
if (!defined('PG_STYLE_GUIDE_OWN_STYLES_MAX')) {
    define('PG_STYLE_GUIDE_OWN_STYLES_MAX', 12);
}

// The most stylesheet text the sheet reads from the editor, in bytes.
if (!defined('PG_STYLE_GUIDE_CSS_MAX')) {
    define('PG_STYLE_GUIDE_CSS_MAX', 262144);
}

// The sheet: array(has_variables, offer_start, blocks). $variables is the
// design's variables model, or null for a design without a variables file:
// then $defaults (pg_design_variables_defaults() of its look and palette)
// fills the same blocks and the sheet offers the start questions. $css is the
// design's own stylesheet text, $components its shared_components rows.
function pg_style_guide_model($variables, $defaults, $css, $components)
{
    $has = is_array($variables);
    $model = pg_design_variables_normalize($has ? $variables : $defaults, $defaults);
    $type = pg_style_guide_type($model, is_string($css) ? $css : '');
    return array(
        'has_variables' => $has,
        'offer_start'   => !$has,
        'blocks'        => array(
            'colours'    => array('title' => lang('Colours'), 'rows' => pg_style_guide_colours($model), 'pairs' => pg_style_guide_contrast_pairs($model)),
            'type'       => array('title' => lang('Type')) + $type,
            'spacing'    => array('title' => lang('Spacing'), 'rows' => pg_style_guide_spacing($model)),
            'shape'      => array('title' => lang('Shape'), 'rows' => pg_style_guide_shape($model)),
            'controls'   => array('title' => lang('Controls'), 'rows' => pg_style_guide_controls(), 'states' => pg_style_guide_state_labels()),
            'components' => array('title' => lang('Components'), 'rows' => pg_style_guide_components(is_array($components) ? $components : array())),
        ),
    );
}

// Every colour: the built-in ones in the list's order, then the designer's
// own. Each row: key, name, light, dark, token, custom.
function pg_style_guide_colours($model)
{
    $rows = array();
    foreach (pg_design_variable_list() as $key => $def) {
        if ($def['kind'] !== 'color') continue;
        $rows[] = array(
            'key' => $key, 'name' => $def['label'],
            'light' => $model['values'][$key]['light'], 'dark' => $model['values'][$key]['dark'],
            'token' => pg_design_variable_token($key), 'custom' => false,
        );
    }
    foreach ($model['custom'] as $c) {
        if ($c['kind'] !== 'color') continue;
        $rows[] = array(
            'key' => $c['key'], 'name' => $c['name'], 'light' => $c['light'], 'dark' => $c['dark'],
            'token' => pg_design_variable_token($c['key'], true), 'custom' => true,
        );
    }
    return $rows;
}

// The contrast pairs, light then dark: every colour but the surface as text
// on the surface, and the surface as a label on the brand and accent colours
// (a button's text). Each row: text, surface (the keys), mode, ratio (two
// places), ok at 4.5:1 (body text), large_ok at 3:1 (large text), custom
// (the text is the designer's own colour).
function pg_style_guide_contrast_pairs($model)
{
    $colours = array();
    foreach (pg_style_guide_colours($model) as $row) $colours[$row['key']] = $row;
    $pairs = array();
    foreach ($colours as $key => $row) {
        if ($key !== 'surface') $pairs[] = array($key, 'surface');
    }
    $pairs[] = array('surface', 'brand');
    $pairs[] = array('surface', 'accent');
    $rows = array();
    foreach (array('light', 'dark') as $mode) {
        foreach ($pairs as $p) {
            if (!isset($colours[$p[0]], $colours[$p[1]])) continue;
            $fg = _pg_theme_rgb($colours[$p[0]][$mode]);
            $bg = _pg_theme_rgb($colours[$p[1]][$mode]);
            if (!$fg || !$bg) continue;
            $ratio = round(_pg_theme_contrast($fg, $bg), 2);
            $rows[] = array(
                'text' => $p[0], 'surface' => $p[1], 'mode' => $mode, 'ratio' => $ratio,
                'ok' => $ratio >= 4.5, 'large_ok' => $ratio >= 3, 'custom' => $colours[$p[0]]['custom'],
            );
        }
    }
    return $rows;
}

// The Type block: fonts (the two font variables), styles
// (pg_style_guide_text_styles()) and scale (the type scale's steps).
function pg_style_guide_type($model, $css)
{
    $list = pg_design_variable_list();
    $fonts = array();
    foreach (array('font_heading', 'font_body') as $key) {
        $fonts[] = array('key' => $key, 'name' => $list[$key]['label'], 'value' => $model['values'][$key]);
    }
    $base = _pg_style_guide_px($model['values']['size_base'], 16);
    $ratio = (float)$model['values']['scale'];
    $scale = array();
    $names = array(-2 => 'XS', -1 => 'S', 0 => lang('Base'), 1 => 'L', 2 => 'XL', 3 => '2XL', 4 => '3XL', 5 => '4XL', 6 => '5XL');
    foreach ($names as $step => $name) {
        $scale[] = array('name' => $name, 'step' => $step, 'px' => (int)round($base * pow($ratio > 0 ? $ratio : 1, $step)));
    }
    return array('fonts' => $fonts, 'styles' => pg_style_guide_text_styles($css, $model), 'scale' => $scale);
}

// Every heading and text style at its size, largest first, then the
// stylesheet's own text classes (a lone class rule with a font size, at most
// PG_STYLE_GUIDE_OWN_STYLES_MAX). Each row: key, name, selector, size, px,
// size_var, font (the font variable), family (the stylesheet's, '' for the
// variable's), weight, weight_var, line_height, source ('' built-in,
// 'stylesheet' a built-in the stylesheet changes, 'class' an own class).
function pg_style_guide_text_styles($css, $model)
{
    $v = $model['values'];
    $base = _pg_style_guide_px($v['size_base'], 16);
    $style = function ($key, $name, $selector, $size, $font, $weight_var = '', $size_var = '') use ($v) {
        return array(
            'key' => $key, 'name' => $name, 'selector' => $selector, 'size' => $size, 'px' => null,
            'size_var' => $size_var, 'font' => $font, 'family' => '',
            'weight' => $weight_var !== '' ? $v[$weight_var] : '', 'weight_var' => $weight_var,
            'line_height' => $key === 'body' ? $v['line_height'] : '', 'source' => '',
        );
    };
    $styles = array();
    foreach (array(5, 4.5, 4, 3.5, 3, 2.5) as $i => $rem) {
        $n = $i + 1;
        $styles['display-' . $n] = $style('display-' . $n, lang(array('string' => 'Display {var:1}', 'vars' => $n)), '.display-' . $n, _pg_design_variable_num($rem) . 'rem', 'font_heading');
    }
    foreach (array(2.5, 2, 1.75, 1.5, 1.25, 1) as $i => $rem) {
        $n = $i + 1;
        $styles['h' . $n] = $style('h' . $n, lang(array('string' => 'Heading {var:1}', 'vars' => $n)), 'h' . $n, _pg_design_variable_num($rem) . 'rem', 'font_heading', 'weight_heading');
    }
    $styles['lead']  = $style('lead', lang('Lead'), '.lead', '1.25rem', 'font_body');
    $styles['body']  = $style('body', lang('Text'), 'body', $v['size_base'], 'font_body', 'weight_body', 'size_base');
    $styles['small'] = $style('small', lang('Small'), 'small', '0.875em', 'font_body');

    $own = 0;
    foreach (_pg_style_guide_css_rules($css) as $rule) {
        $decl = _pg_style_guide_text_declarations($rule['body']);
        if (!$decl) continue;
        foreach ($rule['selectors'] as $sel) {
            $key = _pg_style_guide_selector_key($sel);
            if ($key === null) {
                if (!preg_match('/^\.([a-z][a-z0-9_-]{0,40})$/i', $sel, $m) || !isset($decl['size'])) continue;
                // Class names are case-sensitive in HTML: the sample is
                // drawn with the class as the stylesheet writes it.
                $key = $m[1];
                if (isset($styles[$key])) {
                    if ($styles[$key]['source'] !== 'class') continue;
                } else {
                    if ($own >= PG_STYLE_GUIDE_OWN_STYLES_MAX) continue;
                    $own++;
                    $styles[$key] = $style($key, '.' . $key, '.' . $key, $decl['size'], 'font_body');
                    $styles[$key]['source'] = 'class';
                }
            } elseif ($styles[$key]['source'] === '') {
                $styles[$key]['source'] = 'stylesheet';
            }
            if (isset($decl['size'])) {
                $styles[$key]['size'] = $decl['size'];
                $styles[$key]['size_var'] = '';
            }
            foreach (array('family', 'weight', 'line_height') as $f) {
                if (isset($decl[$f])) $styles[$key][$f] = $decl[$f];
            }
            if (isset($decl['weight'])) $styles[$key]['weight_var'] = '';
        }
    }
    foreach ($styles as $key => $s) {
        $px = _pg_style_guide_px($s['size'], $base);
        $styles[$key]['px'] = ($px === null) ? null : _pg_style_guide_round($px);
    }
    return array_values($styles);
}

// Spacing: the base unit and its multiples (the "units" of a spacing
// control), the section rhythm, then the designer's own sizes. Each row: key,
// name, size, px, multiple (units only), custom.
function pg_style_guide_spacing($model)
{
    $list = pg_design_variable_list();
    $unit = $model['values']['space_unit'];
    $unit_px = _pg_style_guide_px($unit, 16);
    $rows = array();
    foreach (array(1, 2, 3, 4, 6, 8) as $n) {
        $rows[] = array(
            'key' => 'space_unit',
            'name' => lang(array('string' => '{var:1} unit{suffix}', 'vars' => $n, 'suffix' => $n === 1 ? '' : 's')),
            'size' => $n === 1 ? $unit : _pg_style_guide_multiply($unit, $n),
            'px' => $unit_px === null ? null : _pg_style_guide_round($unit_px * $n),
            'multiple' => $n, 'custom' => false,
        );
    }
    $section = $model['values']['space_section'];
    $px = _pg_style_guide_px($section, 16);
    $rows[] = array('key' => 'space_section', 'name' => $list['space_section']['label'], 'size' => $section,
        'px' => $px === null ? null : _pg_style_guide_round($px), 'multiple' => null, 'custom' => false);
    foreach ($model['custom'] as $c) {
        if ($c['kind'] !== 'size') continue;
        $px = _pg_style_guide_px($c['value'], 16);
        $rows[] = array('key' => $c['key'], 'name' => $c['name'], 'size' => $c['value'],
            'px' => $px === null ? null : _pg_style_guide_round($px), 'multiple' => null, 'custom' => true);
    }
    return $rows;
}

// Shape: corners, line width and shadow. Each row: key, name, value, label
// (the value as the sheet writes it: a shadow by its option's name).
function pg_style_guide_shape($model)
{
    $list = pg_design_variable_list();
    $rows = array();
    foreach (array('radius', 'border_width', 'shadow') as $key) {
        $value = $model['values'][$key];
        $label = $value;
        if ($list[$key]['kind'] === 'choice') {
            $label = isset($list[$key]['options'][$value]) ? $list[$key]['options'][$value] : $value;
        }
        $rows[] = array('key' => $key, 'name' => $list[$key]['label'], 'value' => $value, 'label' => $label);
    }
    return $rows;
}

// The controls the sheet draws, each in its states, rest first and off last.
function pg_style_guide_controls()
{
    $all = array('rest', 'hover', 'focus', 'disabled');
    $field = array('rest', 'focus', 'disabled');
    return array(
        array('key' => 'button-primary',   'name' => lang('Main button'),    'states' => $all),
        array('key' => 'button-secondary', 'name' => lang('Second button'),  'states' => $all),
        array('key' => 'button-outline',   'name' => lang('Outline button'), 'states' => $all),
        array('key' => 'link',             'name' => lang('Link'),           'states' => $all),
        array('key' => 'input',            'name' => lang('Text field'),     'states' => $field),
        array('key' => 'select',           'name' => lang('Choice list'),    'states' => $field),
        array('key' => 'check',            'name' => lang('Tick box'),       'states' => array('rest', 'focus', 'checked', 'disabled')),
    );
}

// The name of each control state.
function pg_style_guide_state_labels()
{
    return array(
        'rest'     => lang('At rest'),
        'hover'    => lang('Hover'),
        'focus'    => lang('Focus'),
        'checked'  => lang('Ticked'),
        'disabled' => lang('Off'),
    );
}

// The design's shared components by name (case left as it is, sorted
// without it): array(id, name). A system widget (a row with a
// system_region_config) and a row without an id or a name are left out.
function pg_style_guide_components($rows)
{
    $out = array();
    foreach ($rows as $row) {
        if (!is_array($row) || !isset($row['id'], $row['name']) || !is_scalar($row['name'])) continue;
        if (!empty($row['system_region_config'])) continue;
        $id = (int)$row['id'];
        $name = trim((string)$row['name']);
        if ($id <= 0 || $name === '') continue;
        $out[] = array('id' => $id, 'name' => $name);
    }
    usort($out, function ($a, $b) {
        $c = strcasecmp($a['name'], $b['name']);
        return $c !== 0 ? $c : $a['id'] - $b['id'];
    });
    return $out;
}

// The Components block's rows with `pages`: how many pages place each one,
// from pg_shared_component_usage() (sid => list of places). A page counts
// once however often it places it; a page in the recycle bin does not.
function pg_style_guide_component_pages($rows, $usage)
{
    foreach ($rows as $i => $row) {
        $seen = array();
        $places = (is_array($usage) && isset($usage[$row['id']]) && is_array($usage[$row['id']])) ? $usage[$row['id']] : array();
        foreach ($places as $u) {
            if (!is_array($u) || !empty($u['binned'])) continue;
            $key = !empty($u['page_id']) ? 'p' . (int)$u['page_id'] : 's' . (isset($u['style_id']) ? (int)$u['style_id'] : 0);
            $seen[$key] = true;
        }
        $rows[$i]['pages'] = count($seen);
    }
    return $rows;
}

// The top-level rules of a stylesheet, comments and at-rule blocks (@media,
// @supports, @font-face...) left out: array of array(selectors, body).
function _pg_style_guide_css_rules($css)
{
    $css = preg_replace('~/\*.*?\*/~s', '', (string)$css);
    $rules = array();
    $len = strlen($css);
    $start = 0;
    $i = 0;
    while ($i < $len) {
        $open = strpos($css, '{', $i);
        if ($open === false) break;
        $prelude = trim(substr($css, $start, $open - $start));
        // An at-rule ending in ";" before the block (@import, @charset).
        if (($semi = strrpos($prelude, ';')) !== false) $prelude = trim(substr($prelude, $semi + 1));
        $depth = 1;
        $j = $open + 1;
        while ($j < $len && $depth > 0) {
            if ($css[$j] === '{') $depth++;
            elseif ($css[$j] === '}') $depth--;
            $j++;
        }
        $body = substr($css, $open + 1, max(0, $j - $open - 2));
        if ($prelude !== '' && $prelude[0] !== '@' && strpos($body, '{') === false) {
            $sels = array();
            foreach (explode(',', $prelude) as $s) {
                $s = preg_replace('/\s+/', ' ', trim($s));
                if ($s !== '') $sels[] = $s;
            }
            $rules[] = array('selectors' => $sels, 'body' => $body);
        }
        $start = $i = $j;
    }
    return $rules;
}

// The built-in text style a lone selector names, or null.
function _pg_style_guide_selector_key($sel)
{
    $sel = strtolower($sel);
    if (preg_match('/^\.?(h[1-6])$/', $sel, $m)) return $m[1];
    if (preg_match('/^\.(display-[1-6]|lead)$/', $sel, $m)) return $m[1];
    if ($sel === 'body' || $sel === 'p') return 'body';
    if ($sel === 'small' || $sel === '.small') return 'small';
    return null;
}

// The text declarations of a rule body the sheet can show, each checked:
// size (a plain rem, em or px), family (a font stack), weight (100 to 900),
// line_height (a plain number). A value it cannot show is left out.
function _pg_style_guide_text_declarations($body)
{
    $out = array();
    foreach (explode(';', $body) as $decl) {
        $at = strpos($decl, ':');
        if ($at === false) continue;
        $name = strtolower(trim(substr($decl, 0, $at)));
        $value = trim(preg_replace('/\s*!important\s*$/i', '', trim(substr($decl, $at + 1))));
        switch ($name) {
            case 'font-size':
                if (preg_match('/^(\d{1,3}(?:\.\d{1,4})?)(rem|em|px)$/i', $value, $m) && (float)$m[1] > 0) {
                    $out['size'] = _pg_design_variable_num($m[1]) . strtolower($m[2]);
                }
                break;
            case 'font-family':
                $ok = pg_design_variable_value(array('kind' => 'font'), $value);
                if ($ok !== null) $out['family'] = $ok;
                break;
            case 'font-weight':
                $w = strtolower($value);
                if ($w === 'bold') $w = '700';
                elseif ($w === 'normal') $w = '400';
                if (preg_match('/^[1-9]00$/', $w)) $out['weight'] = $w;
                break;
            case 'line-height':
                if (preg_match('/^\d(?:\.\d{1,3})?$/', $value)) $out['line_height'] = _pg_design_variable_num($value);
                break;
        }
    }
    return $out;
}

// A plain size in pixels: rem on a 16 px root, em on $em_px; null for any
// other value.
function _pg_style_guide_px($size, $em_px)
{
    if ($size === '0') return 0.0;
    if (!is_string($size) || !preg_match('/^(\d+(?:\.\d+)?)(rem|em|px)$/', $size, $m)) return null;
    $n = (float)$m[1];
    if ($m[2] === 'rem') return $n * 16;
    if ($m[2] === 'em') return $em_px === null ? null : $n * $em_px;
    return $n;
}

// A size $n times over, in its own unit.
function _pg_style_guide_multiply($size, $n)
{
    if (!preg_match('/^(\d+(?:\.\d+)?)(rem|px)$/', $size, $m)) return $size;
    return _pg_design_variable_num((float)$m[1] * $n) . $m[2];
}

// Pixels to two places, a whole number as an int.
function _pg_style_guide_round($px)
{
    $r = round($px, 2);
    return ($r == floor($r)) ? (int)$r : $r;
}
