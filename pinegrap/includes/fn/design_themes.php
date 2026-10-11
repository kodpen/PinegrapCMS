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

// ========================= DESIGN THEMES =========================================================
//
// A visual-editor design built on Bootstrap wears two independent things:
//
//   the look     shape, depth and type: corners, shadows, lines, fonts, how
//                buttons and cards behave (assets/css/themes/looks/<key>.css,
//                --pg-* tokens read by assets/css/themes/base.css)
//   the palette  colour: a primary and a secondary colour worked out into
//                every shade Bootstrap needs, light and dark mode
//                (assets/css/themes/palettes/<key>.css)
//
// Both are stylesheets: nothing is written into the pages. A design stores
// its choice on the style row (style.style_look, style.style_palette,
// 2026.4.5) as '' (none: plain Bootstrap), a built-in key, or file-<id> for
// a look or palette the operator made in the editor, saved as a design CSS
// file in the file manager so other designs can wear it too.
//
// The stylesheets load right after bootstrap.css, in this order:
//   bridge.css   Bootstrap's literal component colours pointed at variables
//   base.css     the look tokens applied to every component (with a look)
//   the look     its tokens
//   the palette  its colours
// so the design's own stylesheets, User Styles and the theme file still win.

// The columns exist (2026.4.5).
function pg_design_look_ready($recheck = false)
{
    static $cached = null;
    if ($cached !== null && !$recheck) return $cached;
    $cached = false;
    if (!isset(db::$con) || !db::$con) return false;
    $result = @mysqli_query(db::$con, "SHOW COLUMNS FROM style WHERE Field IN ('style_look', 'style_palette')");
    $cached = ($result && @mysqli_num_rows($result) == 2);
    return $cached;
}

// Every built-in look, in the order they are offered. `preview` feeds the
// small sample the editor draws on the look's card.
function pg_design_looks()
{
    return array(
        'modern-soft' => array(
            'name'        => lang('Modern Soft'),
            'description' => lang('Rounded corners, soft shadows and roomy controls.'),
            'preview'     => array('radius' => 12, 'btn_radius' => 10, 'shadow' => 'soft', 'border' => 1, 'heading' => 'sans', 'weight' => 700, 'caps' => false),
        ),
        'modern-sharp' => array(
            'name'        => lang('Modern Sharp'),
            'description' => lang('Square corners, crisp lines and capital letters on the buttons.'),
            'preview'     => array('radius' => 0, 'btn_radius' => 0, 'shadow' => 'none', 'border' => 1, 'heading' => 'sans', 'weight' => 800, 'caps' => true),
        ),
        'minimal' => array(
            'name'        => lang('Minimal'),
            'description' => lang('Hairlines, small corners and plenty of air.'),
            'preview'     => array('radius' => 6, 'btn_radius' => 5, 'shadow' => 'none', 'border' => 1, 'heading' => 'sans', 'weight' => 500, 'caps' => false),
        ),
        'corporate' => array(
            'name'        => lang('Corporate'),
            'description' => lang('Measured corners, compact controls, made for information.'),
            'preview'     => array('radius' => 6, 'btn_radius' => 5, 'shadow' => 'crisp', 'border' => 1, 'heading' => 'sans', 'weight' => 600, 'caps' => false),
        ),
        'elegant' => array(
            'name'        => lang('Elegant'),
            'description' => lang('Serif headings, fine lines and spaced capitals.'),
            'preview'     => array('radius' => 2, 'btn_radius' => 0, 'shadow' => 'none', 'border' => 1, 'heading' => 'serif', 'weight' => 400, 'caps' => true),
        ),
        'playful' => array(
            'name'        => lang('Playful'),
            'description' => lang('Pill buttons, big round corners and coloured shadows.'),
            'preview'     => array('radius' => 20, 'btn_radius' => 99, 'shadow' => 'glow', 'border' => 0, 'heading' => 'rounded', 'weight' => 800, 'caps' => false),
        ),
        'bold' => array(
            'name'        => lang('Brutalist'),
            'description' => lang('Thick outlines, hard offset shadows and heavy type.'),
            'preview'     => array('radius' => 8, 'btn_radius' => 8, 'shadow' => 'hard', 'border' => 2, 'heading' => 'sans', 'weight' => 900, 'caps' => false),
        ),
        'editorial' => array(
            'name'        => lang('Editorial'),
            'description' => lang('Magazine page: serif text, heavy headings, hairlines, square corners.'),
            'preview'     => array('radius' => 2, 'btn_radius' => 2, 'shadow' => 'none', 'border' => 1, 'heading' => 'serif', 'weight' => 800, 'caps' => false),
        ),
        'technical' => array(
            'name'        => lang('Technical'),
            'description' => lang('Monospace headings, square corners, thin outlines, capitals on the buttons.'),
            'preview'     => array('radius' => 0, 'btn_radius' => 0, 'shadow' => 'crisp', 'border' => 1, 'heading' => 'mono', 'weight' => 600, 'caps' => true),
        ),
        'organic' => array(
            'name'        => lang('Organic'),
            'description' => lang('Rounded type, big soft corners, soft layered shadows, no card outline.'),
            'preview'     => array('radius' => 16, 'btn_radius' => 14, 'shadow' => 'soft', 'border' => 0, 'heading' => 'rounded', 'weight' => 700, 'caps' => false),
        ),
        'luxe' => array(
            'name'        => lang('Luxe'),
            'description' => lang('Light serif headings, widely spaced capitals, deep shadows, slim lines.'),
            'preview'     => array('radius' => 4, 'btn_radius' => 0, 'shadow' => 'deep', 'border' => 1, 'heading' => 'serif', 'weight' => 300, 'caps' => true),
        ),
        'glass' => array(
            'name'        => lang('Glass'),
            'description' => lang('Large corners, pill buttons, no outlines, deep layered shadows, grotesque headings.'),
            'preview'     => array('radius' => 14, 'btn_radius' => 99, 'shadow' => 'deep', 'border' => 0, 'heading' => 'grotesk', 'weight' => 600, 'caps' => false),
        ),
    );
}

// The look new designs start with.
function pg_design_default_look()
{
    return 'modern-soft';
}

// Every built-in palette: a primary and a secondary colour. Chosen so white
// text on either reads at 4.5:1 or better, except where the generator turns
// the text dark (amber).
function pg_design_palettes()
{
    return array(
        'classic'    => array('name' => lang('Classic Blue'),    'primary' => '#2563eb', 'secondary' => '#475569'),
        'ocean'      => array('name' => lang('Ocean'),           'primary' => '#0f766e', 'secondary' => '#1e3a8a'),
        'emerald'    => array('name' => lang('Emerald'),         'primary' => '#047857', 'secondary' => '#1f2937'),
        'forest'     => array('name' => lang('Forest'),          'primary' => '#3f6212', 'secondary' => '#78350f'),
        'sunset'     => array('name' => lang('Sunset'),          'primary' => '#c2410c', 'secondary' => '#6b21a8'),
        'coral'      => array('name' => lang('Coral'),           'primary' => '#e11d48', 'secondary' => '#0f766e'),
        'lavender'   => array('name' => lang('Lavender'),        'primary' => '#7c3aed', 'secondary' => '#be185d'),
        'midnight'   => array('name' => lang('Midnight'),        'primary' => '#1e3a8a', 'secondary' => '#b45309'),
        'burgundy'   => array('name' => lang('Burgundy'),        'primary' => '#9f1239', 'secondary' => '#57534e'),
        'amber'      => array('name' => lang('Amber'),           'primary' => '#d97706', 'secondary' => '#1c1917'),
        'sky'        => array('name' => lang('Sky'),             'primary' => '#0369a1', 'secondary' => '#4338ca'),
        'fuchsia'    => array('name' => lang('Fuchsia'),         'primary' => '#a21caf', 'secondary' => '#312e81'),
        'graphite'   => array('name' => lang('Graphite'),        'primary' => '#18181b', 'secondary' => '#71717a'),
        'aurora'     => array('name' => lang('Northern Lights'), 'primary' => '#4f46e5', 'secondary' => '#0e7490'),
        'cobalt'     => array('name' => lang('Cobalt'),          'primary' => '#0047ab', 'secondary' => '#0f172a'),
        'crimson'    => array('name' => lang('Crimson'),         'primary' => '#b91c1c', 'secondary' => '#1f2937'),
        'sage'       => array('name' => lang('Sage'),            'primary' => '#4a6b53', 'secondary' => '#9a3412'),
        'cocoa'      => array('name' => lang('Cocoa'),           'primary' => '#6f4e37', 'secondary' => '#0f766e'),
        'steel'      => array('name' => lang('Steel'),           'primary' => '#3e5c76', 'secondary' => '#0f766e'),
    );
}

// Bootstrap's own colours, for the "no palette" swatch.
function pg_design_bootstrap_colors()
{
    return array('primary' => '#0d6efd', 'secondary' => '#6c757d');
}

// '' | a built-in key | file-<id>; anything else reads as ''.
function pg_design_look_key($value)
{
    $value = is_scalar($value) ? strtolower(trim((string)$value)) : '';
    if ($value === '') return '';
    $looks = pg_design_looks();
    if (isset($looks[$value])) return $value;
    if (preg_match('/^file-([1-9][0-9]{0,9})$/', $value)) return $value;
    return '';
}

function pg_design_palette_key($value)
{
    $value = is_scalar($value) ? strtolower(trim((string)$value)) : '';
    if ($value === '') return '';
    $palettes = pg_design_palettes();
    if (isset($palettes[$value])) return $value;
    if (preg_match('/^file-([1-9][0-9]{0,9})$/', $value)) return $value;
    return '';
}

// The file id of a file-<id> key, 0 for anything else.
function pg_design_theme_file_id($key)
{
    return preg_match('/^file-([1-9][0-9]{0,9})$/', (string)$key, $m) ? (int)$m[1] : 0;
}

// Where the shipped stylesheets are, on disk and on the site.
function pg_design_theme_dir()
{
    return PG_FUNCTIONS_DIR . '/assets/css/themes';
}

function pg_design_theme_url($relative)
{
    $path = pg_design_theme_dir() . '/' . $relative;
    return (defined('OUTPUT_PATH') ? OUTPUT_PATH : '/') . (defined('OUTPUT_SOFTWARE_DIRECTORY') ? OUTPUT_SOFTWARE_DIRECTORY : '')
        . '/assets/css/themes/' . $relative . '?v=' . (int)@filemtime($path);
}

// ── Custom looks and palettes (design CSS files) ───────────────────────────

// A saved look or palette opens with a one-line header the editor reads back
// to offer the file again and to reopen it for changes:
//   /*! pg-theme {"kind":"look","base":"modern-soft","controls":{...}} */
function pg_design_theme_header($meta)
{
    // Slashes stay escaped, so nothing in a name can close the comment.
    return '/*! pg-theme ' . json_encode($meta, JSON_UNESCAPED_UNICODE) . ' */';
}

function pg_design_theme_read_header($file_name)
{
    $path = FILE_DIRECTORY_PATH . '/' . $file_name;
    if ($file_name === '' || strpos($file_name, '/') !== false || !is_file($path)) return null;
    $fh = @fopen($path, 'rb');
    if (!$fh) return null;
    $head = (string)fread($fh, 4096);
    fclose($fh);
    if (!preg_match('~^/\*! pg-theme (\{.*?\}) \*/~s', $head, $m)) return null;
    $meta = json_decode($m[1], true);
    return is_array($meta) && isset($meta['kind']) ? $meta : null;
}

// Every custom look or palette in the file manager: array of
// array(id, name, file, url, meta). Read once per request.
function pg_design_theme_files($kind = null)
{
    static $all = null;
    if ($all === null) {
        $all = array('look' => array(), 'palette' => array());
        $rows = db_items("SELECT id, name, timestamp FROM files WHERE type = 'css' AND design = '1' ORDER BY name ASC");
        foreach ((is_array($rows) ? $rows : array()) as $r) {
            $meta = pg_design_theme_read_header((string)$r['name']);
            if (!$meta || !isset($all[$meta['kind']])) continue;
            $all[$meta['kind']][(int)$r['id']] = array(
                'id'   => (int)$r['id'],
                'name' => isset($meta['name']) && $meta['name'] !== '' ? (string)$meta['name'] : preg_replace('/\.css$/i', '', (string)$r['name']),
                'file' => (string)$r['name'],
                'url'  => (defined('OUTPUT_PATH') ? OUTPUT_PATH : '/') . (string)$r['name'] . '?v=' . (int)$r['timestamp'],
                'meta' => $meta,
            );
        }
    }
    if ($kind === null) return $all;
    return isset($all[$kind]) ? $all[$kind] : array();
}

// ── What a design loads ────────────────────────────────────────────────────

// The stylesheets of a look and a palette, in load order: array of
// array(id, url). Empty for a design without a framework, and for a choice
// whose file has gone.
function pg_design_theme_assets($look, $palette, $framework = 'bootstrap5')
{
    $out = array();
    if (function_exists('pg_design_framework')) {
        $fw = pg_design_framework($framework);
        if (empty($fw['bootstrap'])) return $out;
    }
    $look    = pg_design_look_key($look);
    $palette = pg_design_palette_key($palette);

    $look_url = '';
    if ($look !== '') {
        $fid = pg_design_theme_file_id($look);
        if ($fid > 0) {
            $files = pg_design_theme_files('look');
            if (isset($files[$fid])) $look_url = $files[$fid]['url'];
        } else {
            $look_url = pg_design_theme_url('looks/' . $look . '.css');
        }
    }
    $palette_url = '';
    if ($palette !== '') {
        $fid = pg_design_theme_file_id($palette);
        if ($fid > 0) {
            $files = pg_design_theme_files('palette');
            if (isset($files[$fid])) $palette_url = $files[$fid]['url'];
        } else {
            $palette_url = pg_design_theme_url('palettes/' . $palette . '.css');
        }
    }

    if ($look_url === '' && $palette_url === '') return $out;
    $out[] = array('id' => 'pg-theme-bridge', 'url' => pg_design_theme_url('bridge.css'));
    if ($look_url !== '') {
        $out[] = array('id' => 'pg-theme-base', 'url' => pg_design_theme_url('base.css'));
        $out[] = array('id' => 'pg-theme-look', 'url' => $look_url);
    }
    if ($palette_url !== '') {
        $out[] = array('id' => 'pg-theme-palette', 'url' => $palette_url);
    }
    return $out;
}

// The same, as <link> tags for the page head.
function pg_design_theme_links($look, $palette, $framework = 'bootstrap5')
{
    $html = '';
    foreach (pg_design_theme_assets($look, $palette, $framework) as $a) {
        $html .= '<link rel="stylesheet" id="' . h($a['id']) . '" href="' . h($a['url']) . '">';
    }
    return $html;
}

// A look's or palette's display name, for lists; '' for none.
function pg_design_look_label($look)
{
    $look = pg_design_look_key($look);
    if ($look === '') return '';
    $fid = pg_design_theme_file_id($look);
    if ($fid > 0) {
        $files = pg_design_theme_files('look');
        return isset($files[$fid]) ? $files[$fid]['name'] : '';
    }
    $looks = pg_design_looks();
    return $looks[$look]['name'];
}

// The two colours of a palette choice: array(primary, secondary). Bootstrap's
// own for none or a palette whose file has gone.
function pg_design_palette_colors($palette)
{
    $palette = pg_design_palette_key($palette);
    $fid = pg_design_theme_file_id($palette);
    if ($fid > 0) {
        $files = pg_design_theme_files('palette');
        if (isset($files[$fid]['meta']['primary'], $files[$fid]['meta']['secondary'])) {
            return array('primary' => (string)$files[$fid]['meta']['primary'], 'secondary' => (string)$files[$fid]['meta']['secondary']);
        }
    } elseif ($palette !== '') {
        $all = pg_design_palettes();
        return array('primary' => $all[$palette]['primary'], 'secondary' => $all[$palette]['secondary']);
    }
    return pg_design_bootstrap_colors();
}

// What the editor needs to offer every choice without another request.
function pg_design_theme_catalog()
{
    $looks = array();
    foreach (pg_design_looks() as $key => $l) {
        $looks[] = array('key' => $key, 'name' => $l['name'], 'description' => $l['description'],
                         'preview' => $l['preview'], 'url' => pg_design_theme_url('looks/' . $key . '.css'));
    }
    $palettes = array();
    foreach (pg_design_palettes() as $key => $p) {
        $palettes[] = array('key' => $key, 'name' => $p['name'], 'primary' => $p['primary'], 'secondary' => $p['secondary'],
                            'url' => pg_design_theme_url('palettes/' . $key . '.css'));
    }
    $custom_looks = array();
    foreach (pg_design_theme_files('look') as $f) {
        $custom_looks[] = array('key' => 'file-' . $f['id'], 'name' => $f['name'], 'url' => $f['url'],
                                'base' => isset($f['meta']['base']) ? (string)$f['meta']['base'] : '',
                                'controls' => isset($f['meta']['controls']) && is_array($f['meta']['controls']) ? $f['meta']['controls'] : new stdClass());
    }
    $custom_palettes = array();
    foreach (pg_design_theme_files('palette') as $f) {
        $custom_palettes[] = array('key' => 'file-' . $f['id'], 'name' => $f['name'], 'url' => $f['url'],
                                   'primary' => isset($f['meta']['primary']) ? (string)$f['meta']['primary'] : '',
                                   'secondary' => isset($f['meta']['secondary']) ? (string)$f['meta']['secondary'] : '');
    }
    return array(
        'ready'          => pg_design_look_ready(),
        'bridge'         => pg_design_theme_url('bridge.css'),
        'base'           => pg_design_theme_url('base.css'),
        'looks'          => $looks,
        'palettes'       => $palettes,
        'customLooks'    => $custom_looks,
        'customPalettes' => $custom_palettes,
        'bootstrap'      => pg_design_bootstrap_colors(),
        'defaultLook'    => pg_design_default_look(),
        'lookControls'   => pg_design_look_controls(),
    );
}

// ── Colour arithmetic ──────────────────────────────────────────────────────

function _pg_theme_rgb($hex)
{
    $hex = ltrim(trim((string)$hex), '#');
    if (strlen($hex) === 3) $hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
    if (!preg_match('/^[0-9a-f]{6}$/i', $hex)) return null;
    return array(hexdec(substr($hex, 0, 2)), hexdec(substr($hex, 2, 2)), hexdec(substr($hex, 4, 2)));
}

function _pg_theme_hex($rgb)
{
    return sprintf('#%02x%02x%02x', max(0, min(255, (int)round($rgb[0]))), max(0, min(255, (int)round($rgb[1]))), max(0, min(255, (int)round($rgb[2]))));
}

// $weight of $b mixed into $a (Sass mix($b, $a, $weight)).
function _pg_theme_mix($a, $b, $weight)
{
    return array($a[0] + ($b[0] - $a[0]) * $weight, $a[1] + ($b[1] - $a[1]) * $weight, $a[2] + ($b[2] - $a[2]) * $weight);
}

function _pg_theme_tint($c, $w)  { return _pg_theme_mix($c, array(255, 255, 255), $w); }
function _pg_theme_shade($c, $w) { return _pg_theme_mix($c, array(0, 0, 0), $w); }

function _pg_theme_luminance($c)
{
    $l = array();
    foreach ($c as $v) {
        $v = max(0, min(255, $v)) / 255;
        $l[] = ($v <= 0.03928) ? $v / 12.92 : pow(($v + 0.055) / 1.055, 2.4);
    }
    return 0.2126 * $l[0] + 0.7152 * $l[1] + 0.0722 * $l[2];
}

function _pg_theme_contrast($a, $b)
{
    $la = _pg_theme_luminance($a);
    $lb = _pg_theme_luminance($b);
    return (max($la, $lb) + 0.05) / (min($la, $lb) + 0.05);
}

// $c moved toward white (or black) until it reads at $ratio on $bg.
function _pg_theme_readable($c, $bg, $ratio, $toward_white)
{
    for ($i = 0; $i <= 20; $i++) {
        $try = $toward_white ? _pg_theme_tint($c, $i * 0.05) : _pg_theme_shade($c, $i * 0.05);
        if (_pg_theme_contrast($try, $bg) >= $ratio) return $try;
    }
    return $toward_white ? array(255, 255, 255) : array(0, 0, 0);
}

function _pg_theme_rgb_list($c)
{
    return (int)round($c[0]) . ', ' . (int)round($c[1]) . ', ' . (int)round($c[2]);
}

// Every variable one colour role needs, light and dark.
function _pg_theme_role($role, $c)
{
    $white   = array(255, 255, 255);
    $ink     = array(17, 24, 39);
    $dark_bg = array(33, 37, 41);   // Bootstrap's dark body

    // Text on the colour: white wherever it reads at 4.5:1, else whichever
    // of white and ink reads better.
    $on_white = _pg_theme_contrast($c, $white) >= 4.5 || _pg_theme_contrast($c, $white) >= _pg_theme_contrast($c, $ink);
    $on = $on_white ? $white : $ink;
    // Buttons darken under the pointer when their text is white, and lighten
    // when it is dark - as Bootstrap's own .btn-warning does.
    // A near-black colour has nowhere darker to go; it lightens instead.
    $very_dark = _pg_theme_luminance($c) < 0.02;
    $hover  = ($on_white && !$very_dark) ? _pg_theme_shade($c, 0.15) : _pg_theme_tint($c, $very_dark ? 0.12 : 0.15);
    $active = ($on_white && !$very_dark) ? _pg_theme_shade($c, 0.2)  : _pg_theme_tint($c, $very_dark ? 0.18 : 0.2);
    // The dark-mode shades of a near-black colour are worked out from a
    // lifted copy, or they vanish into the dark background.
    $cd = $very_dark ? _pg_theme_tint($c, 0.3) : $c;

    $fg_light = _pg_theme_readable($c, $white, 4.5, false);
    $fg_dark  = _pg_theme_readable(_pg_theme_tint($c, 0.3), $dark_bg, 4.5, true);

    $light = array(
        '--bs-' . $role                     => _pg_theme_hex($c),
        '--bs-' . $role . '-rgb'            => _pg_theme_rgb_list($c),
        '--bs-' . $role . '-text-emphasis'  => _pg_theme_hex(_pg_theme_shade($c, 0.6)),
        '--bs-' . $role . '-bg-subtle'      => _pg_theme_hex(_pg_theme_tint($c, 0.8)),
        '--bs-' . $role . '-border-subtle'  => _pg_theme_hex(_pg_theme_tint($c, 0.6)),
        '--pg-on-' . $role                  => _pg_theme_hex($on),
        '--pg-' . $role . '-fg'             => _pg_theme_hex($fg_light),
        '--pg-' . $role . '-fg-rgb'         => _pg_theme_rgb_list($fg_light),
        '--pg-' . $role . '-hover'          => _pg_theme_hex($hover),
        '--pg-' . $role . '-hover-rgb'      => _pg_theme_rgb_list(_pg_theme_shade($fg_light, 0.2)),
        '--pg-' . $role . '-active'         => _pg_theme_hex($active),
    );
    $dark = array(
        '--bs-' . $role . '-text-emphasis'  => _pg_theme_hex(_pg_theme_tint($cd, 0.4)),
        '--bs-' . $role . '-bg-subtle'      => _pg_theme_hex(_pg_theme_shade($cd, 0.8)),
        '--bs-' . $role . '-border-subtle'  => _pg_theme_hex(_pg_theme_shade($cd, 0.4)),
        '--pg-' . $role . '-fg'             => _pg_theme_hex($fg_dark),
        '--pg-' . $role . '-fg-rgb'         => _pg_theme_rgb_list($fg_dark),
        '--pg-' . $role . '-hover-rgb'      => _pg_theme_rgb_list(_pg_theme_tint($fg_dark, 0.2)),
    );
    if ($role === 'primary') {
        $light['--bs-link-color']            = _pg_theme_hex($fg_light);
        $light['--bs-link-color-rgb']        = _pg_theme_rgb_list($fg_light);
        $light['--bs-link-hover-color']      = _pg_theme_hex(_pg_theme_shade($fg_light, 0.2));
        $light['--bs-link-hover-color-rgb']  = _pg_theme_rgb_list(_pg_theme_shade($fg_light, 0.2));
        $light['--bs-focus-ring-color']      = 'rgba(' . _pg_theme_rgb_list($c) . ', 0.25)';
        $light['--pg-primary-focus-border']  = _pg_theme_hex(_pg_theme_tint($c, 0.5));
        $dark['--bs-link-color']             = _pg_theme_hex($fg_dark);
        $dark['--bs-link-color-rgb']         = _pg_theme_rgb_list($fg_dark);
        $dark['--bs-link-hover-color']       = _pg_theme_hex(_pg_theme_tint($fg_dark, 0.2));
        $dark['--bs-link-hover-color-rgb']   = _pg_theme_rgb_list(_pg_theme_tint($fg_dark, 0.2));
        $dark['--bs-focus-ring-color']       = 'rgba(' . _pg_theme_rgb_list(_pg_theme_tint($c, 0.2)) . ', 0.3)';
        $dark['--pg-primary-focus-border']   = _pg_theme_hex(_pg_theme_tint($c, 0.3));
    }
    return array($light, $dark);
}

// The stylesheet of a palette. False for a colour that is not #rgb/#rrggbb.
function pg_design_palette_css($primary, $secondary, $title = '')
{
    $p = _pg_theme_rgb($primary);
    $s = _pg_theme_rgb($secondary);
    if (!$p || !$s) return false;
    list($pl, $pd) = _pg_theme_role('primary', $p);
    list($sl, $sd) = _pg_theme_role('secondary', $s);
    $block = function ($selector, $vars) {
        $css = $selector . " {\n";
        foreach ($vars as $k => $v) $css .= '    ' . $k . ': ' . $v . ";\n";
        return $css . "}\n";
    };
    return "/* Pinegrap colour palette" . ($title !== '' ? ': ' . str_replace('*/', '', $title) : '') . ". Generated from "
        . _pg_theme_hex($p) . ' and ' . _pg_theme_hex($s) . ". */\n"
        . $block(":root,\n[data-bs-theme=light]", array_merge($pl, $sl))
        . "\n"
        . $block('[data-bs-theme=dark]', array_merge($pd, $sd));
}

// ── Custom looks ───────────────────────────────────────────────────────────

// The controls the look builder offers and the tokens each choice writes.
// The builder sends choices, never CSS: whatever arrives is looked up here.
function pg_design_look_controls()
{
    $sans    = 'system-ui, -apple-system, "Segoe UI", Roboto, "Helvetica Neue", "Noto Sans", Arial, sans-serif';
    return array(
        'font_body' => array(
            'label'   => lang('Text font'),
            'options' => array(
                'system'   => array('label' => lang('System sans-serif'), 'value' => $sans),
                'modern'   => array('label' => lang('Modern sans-serif'), 'value' => '"Inter", "Segoe UI Variable Text", ' . $sans),
                'humanist' => array('label' => lang('Humanist'),          'value' => '"Avenir Next", Avenir, "Segoe UI", ' . $sans),
                'rounded'  => array('label' => lang('Rounded'),           'value' => '"Nunito", "Quicksand", ui-rounded, "SF Pro Rounded", ' . $sans),
                'serif'    => array('label' => lang('Serif'),             'value' => '"Iowan Old Style", "Palatino Linotype", Palatino, Georgia, "Times New Roman", serif'),
                'mono'     => array('label' => lang('Monospace'),         'value' => 'ui-monospace, SFMono-Regular, Menlo, Consolas, "Liberation Mono", monospace'),
            ),
        ),
        'font_heading' => array(
            'label'   => lang('Heading font'),
            'options' => array(
                'body'     => array('label' => lang('Same as the text'),  'value' => 'var(--pg-font-body)'),
                'modern'   => array('label' => lang('Modern sans-serif'), 'value' => '"Inter", "Segoe UI Variable Text", ' . $sans),
                'grotesk'  => array('label' => lang('Grotesque'),         'value' => '"Space Grotesk", "Archivo", "Helvetica Neue", Helvetica, Arial, sans-serif'),
                'rounded'  => array('label' => lang('Rounded'),           'value' => '"Nunito", "Quicksand", ui-rounded, "SF Pro Rounded", ' . $sans),
                'serif'    => array('label' => lang('Serif'),             'value' => '"Iowan Old Style", "Palatino Linotype", Palatino, Georgia, "Times New Roman", serif'),
                'mono'     => array('label' => lang('Monospace'),         'value' => 'ui-monospace, SFMono-Regular, Menlo, Consolas, monospace'),
            ),
        ),
        'heading_weight' => array(
            'label'   => lang('Heading weight'),
            'options' => array(
                '400' => array('label' => lang('Regular'),    'value' => '400'),
                '500' => array('label' => lang('Medium'),     'value' => '500'),
                '600' => array('label' => lang('Semibold'),   'value' => '600'),
                '700' => array('label' => lang('Bold'),       'value' => '700'),
                '800' => array('label' => lang('Extra bold'), 'value' => '800'),
                '900' => array('label' => lang('Black'),      'value' => '900'),
            ),
        ),
        'radius' => array(
            'label'   => lang('Corners'),
            'options' => array(
                'square'  => array('label' => lang('Square'),       'value' => array(0, 0, 0, 0)),
                'subtle'  => array('label' => lang('Subtle'),       'value' => array(0.125, 0.25, 0.375, 0.5)),
                'normal'  => array('label' => lang('Normal'),       'value' => array(0.25, 0.375, 0.5, 1)),
                'rounded' => array('label' => lang('Rounded'),      'value' => array(0.5, 0.75, 1, 1.5)),
                'round'   => array('label' => lang('Very rounded'), 'value' => array(0.75, 1, 1.5, 2)),
            ),
        ),
        'button_shape' => array(
            'label'   => lang('Buttons'),
            'options' => array(
                'corners' => array('label' => lang('Follow the corners'), 'value' => ''),
                'square'  => array('label' => lang('Square'),             'value' => '0'),
                'pill'    => array('label' => lang('Pill'),               'value' => '50rem'),
            ),
        ),
        'button_case' => array(
            'label'   => lang('Button text'),
            'options' => array(
                'normal' => array('label' => lang('As written'), 'value' => 'none'),
                'upper'  => array('label' => lang('Capitals'),   'value' => 'uppercase'),
            ),
        ),
        'shadow' => array(
            'label'   => lang('Shadows'),
            'options' => array(
                'none'   => array('label' => lang('None'),       'value' => 'none'),
                'soft'   => array('label' => lang('Soft'),       'value' => 'soft'),
                'crisp'  => array('label' => lang('Crisp'),      'value' => 'crisp'),
                'deep'   => array('label' => lang('Deep'),       'value' => 'deep'),
                'glow'   => array('label' => lang('Coloured'),   'value' => 'glow'),
                'hard'   => array('label' => lang('Hard offset'), 'value' => 'hard'),
            ),
        ),
        'border' => array(
            'label'   => lang('Lines'),
            'options' => array(
                '0' => array('label' => lang('No card outline'), 'value' => '0'),
                '1' => array('label' => lang('Thin'),            'value' => '1'),
                '2' => array('label' => lang('Thick'),           'value' => '2'),
            ),
        ),
        'density' => array(
            'label'   => lang('Spacing'),
            'options' => array(
                'compact'     => array('label' => lang('Compact'),     'value' => 0.8),
                'normal'      => array('label' => lang('Normal'),      'value' => 1),
                'comfortable' => array('label' => lang('Comfortable'), 'value' => 1.25),
            ),
        ),
    );
}

// The --pg-* tokens of a built-in look, read out of its stylesheet.
function pg_design_look_tokens($key)
{
    $looks = pg_design_looks();
    if (!isset($looks[$key])) return array();
    $css = @file_get_contents(pg_design_theme_dir() . '/looks/' . $key . '.css');
    $out = array();
    if ($css && preg_match_all('/(--pg-[a-z0-9-]+)\s*:\s*([^;]+);/i', $css, $m, PREG_SET_ORDER)) {
        foreach ($m as $hit) $out[strtolower($hit[1])] = trim($hit[2]);
    }
    return $out;
}

// The stylesheet of a custom look: the base look's tokens with the builder's
// choices laid over them. False when the base is not a built-in look.
function pg_design_custom_look_css($base, $controls, $name)
{
    $looks = pg_design_looks();
    if (!isset($looks[$base])) return false;
    $defs  = pg_design_look_controls();
    $pick  = array();
    foreach ($defs as $ctl => $def) {
        $v = isset($controls[$ctl]) ? (string)$controls[$ctl] : '';
        if ($v !== '' && isset($def['options'][$v])) $pick[$ctl] = $v;
    }
    $t = pg_design_look_tokens($base);

    if (isset($pick['font_body']))      $t['--pg-font-body'] = $defs['font_body']['options'][$pick['font_body']]['value'];
    if (isset($pick['font_heading']))   $t['--pg-font-heading'] = $defs['font_heading']['options'][$pick['font_heading']]['value'];
    if (isset($pick['heading_weight'])) {
        $w = $defs['heading_weight']['options'][$pick['heading_weight']]['value'];
        $t['--pg-heading-weight'] = $w;
        $t['--pg-display-weight'] = $w;
    }
    if (isset($pick['radius'])) {
        list($sm, $md, $lg, $xl) = $defs['radius']['options'][$pick['radius']]['value'];
        $t['--pg-radius-sm']     = $sm . 'rem';
        $t['--pg-radius']        = $md . 'rem';
        $t['--pg-radius-lg']     = $lg . 'rem';
        $t['--pg-radius-xl']     = $xl . 'rem';
        $t['--pg-radius-xxl']    = ($xl * 1.5) . 'rem';
        $t['--pg-radius-input']  = $md . 'rem';
        $t['--pg-radius-card']   = $lg . 'rem';
        $t['--pg-radius-badge']  = $sm . 'rem';
        $t['--pg-radius-menu']   = $md . 'rem';
        $t['--pg-radius-modal']  = $xl . 'rem';
        $t['--pg-radius-btn']    = $md . 'rem';
        $t['--pg-radius-btn-sm'] = $sm . 'rem';
        $t['--pg-radius-btn-lg'] = $lg . 'rem';
        $t['--pg-radius-check']  = $md > 0 ? '0.3em' : '0';
    }
    if (isset($pick['button_shape']) && $pick['button_shape'] !== 'corners') {
        $r = $defs['button_shape']['options'][$pick['button_shape']]['value'];
        $t['--pg-radius-btn'] = $t['--pg-radius-btn-sm'] = $t['--pg-radius-btn-lg'] = $r;
        if ($pick['button_shape'] === 'pill') $t['--pg-radius-badge'] = '50rem';
    }
    if (isset($pick['button_case'])) {
        $upper = ($pick['button_case'] === 'upper');
        $t['--pg-btn-transform'] = $upper ? 'uppercase' : 'none';
        $t['--pg-btn-tracking']  = $upper ? '0.08em' : 'normal';
        $t['--pg-btn-font-size'] = $upper ? '0.8125rem' : '1rem';
    }
    if (isset($pick['border'])) {
        $b = (int)$defs['border']['options'][$pick['border']]['value'];
        $t['--pg-border-width']      = max(1, $b) . 'px';
        $t['--pg-card-border-width'] = $b . 'px';
    }
    if (isset($pick['shadow'])) {
        $shadows = array(
            'none'  => array('none', '0 6px 18px rgba(0, 0, 0, 0.08)', '0 16px 40px rgba(0, 0, 0, 0.12)', 'none', 'none', 'none', 'none', 'none'),
            'soft'  => array('0 1px 2px rgba(16, 24, 40, 0.05), 0 1px 3px rgba(16, 24, 40, 0.07)', '0 4px 10px -2px rgba(16, 24, 40, 0.08), 0 2px 4px -2px rgba(16, 24, 40, 0.05)', '0 20px 36px -10px rgba(16, 24, 40, 0.16), 0 8px 14px -6px rgba(16, 24, 40, 0.06)', 'var(--pg-shadow-sm)', 'var(--pg-shadow-lg)', 'translateY(-3px)', '0 1px 2px rgba(16, 24, 40, 0.08)', '0 6px 14px -4px rgba(16, 24, 40, 0.2)'),
            'crisp' => array('0 1px 2px rgba(0, 0, 0, 0.06)', '0 3px 8px rgba(0, 0, 0, 0.1)', '0 10px 26px rgba(0, 0, 0, 0.14)', '0 1px 2px rgba(0, 0, 0, 0.05)', 'var(--pg-shadow)', 'none', 'none', 'none'),
            'deep'  => array('0 2px 6px rgba(0, 0, 0, 0.1)', '0 12px 28px -8px rgba(0, 0, 0, 0.25)', '0 30px 60px -18px rgba(0, 0, 0, 0.35)', 'var(--pg-shadow)', 'var(--pg-shadow-lg)', 'translateY(-4px)', '0 4px 10px -4px rgba(0, 0, 0, 0.3)', '0 10px 20px -6px rgba(0, 0, 0, 0.35)'),
            'glow'  => array('0 2px 8px -2px rgba(var(--bs-primary-rgb), 0.18)', '0 10px 26px -10px rgba(var(--bs-primary-rgb), 0.3)', '0 24px 48px -16px rgba(var(--bs-primary-rgb), 0.38)', '0 8px 24px -10px rgba(var(--bs-primary-rgb), 0.28)', '0 20px 40px -14px rgba(var(--bs-primary-rgb), 0.4)', 'translateY(-5px)', '0 4px 12px -6px rgba(var(--bs-primary-rgb), 0.5)', '0 10px 20px -8px rgba(var(--bs-primary-rgb), 0.55)'),
            'hard'  => array('3px 3px 0 0 var(--bs-emphasis-color)', '5px 5px 0 0 var(--bs-emphasis-color)', '8px 8px 0 0 var(--bs-emphasis-color)', '5px 5px 0 0 var(--bs-emphasis-color)', '8px 8px 0 0 var(--bs-emphasis-color)', 'translate(-3px, -3px)', '3px 3px 0 0 var(--bs-emphasis-color)', '5px 5px 0 0 var(--bs-emphasis-color)'),
        );
        list($s_sm, $s_md, $s_lg, $card, $card_h, $card_t, $btn, $btn_h) = $shadows[$pick['shadow']];
        $t['--pg-shadow-sm']          = $s_sm;
        $t['--pg-shadow']             = $s_md;
        $t['--pg-shadow-lg']          = $s_lg;
        $t['--pg-card-shadow']        = $card;
        $t['--pg-card-shadow-hover']  = $card_h;
        $t['--pg-card-hover-transform'] = $card_t;
        $t['--pg-btn-shadow']         = $btn;
        $t['--pg-btn-shadow-hover']   = $btn_h;
        $t['--pg-btn-shadow-active']  = ($pick['shadow'] === 'hard') ? '0 0 0 0 var(--bs-emphasis-color)' : $btn;
        $t['--pg-btn-hover-transform']  = ($pick['shadow'] === 'hard') ? 'translate(-2px, -2px)' : (($pick['shadow'] === 'none' || $pick['shadow'] === 'crisp') ? 'none' : 'translateY(-1px)');
        $t['--pg-btn-active-transform'] = ($pick['shadow'] === 'hard') ? 'translate(3px, 3px)' : 'none';
        $t['--pg-menu-shadow']        = ($pick['shadow'] === 'hard') ? 'var(--pg-shadow)' : 'var(--pg-shadow-lg)';
    }
    if (isset($pick['density'])) {
        $k = (float)$defs['density']['options'][$pick['density']]['value'];
        $t['--pg-btn-py']   = round(0.5 * $k, 4) . 'rem';
        $t['--pg-btn-px']   = round(1.05 * $k, 4) . 'rem';
        $t['--pg-input-py'] = round(0.5 * $k, 4) . 'rem';
        $t['--pg-input-px'] = round(0.8 * $k, 4) . 'rem';
        $t['--pg-card-py']  = round(1.25 * $k, 4) . 'rem';
        $t['--pg-card-px']  = round(1.25 * $k, 4) . 'rem';
    }

    $name = trim((string)$name);
    $css  = pg_design_theme_header(array('kind' => 'look', 'name' => $name, 'base' => $base, 'controls' => (object)$pick)) . "\n"
          . '/* Pinegrap look "' . str_replace('*/', '', $name) . '", made in the visual editor from ' . $looks[$base]['name'] . ". */\n"
          . ":root,\n[data-bs-theme] {\n";
    foreach ($t as $k => $v) {
        // Every value comes from a look file or the table above; the check
        // keeps a hand-edited base file from carrying a rule break-out along.
        if (strpbrk($v, '{};<>') !== false) continue;
        $css .= '    ' . $k . ': ' . $v . ";\n";
    }
    return $css . "}\n";
}

// Writes a custom look or palette to the file manager as a design CSS file.
// Returns array(id, name, file, url) or a string error message.
function pg_design_theme_save_file($kind, $name, $css, $user_id)
{
    $name = trim((string)$name);
    if ($name === '' || mb_strlen($name) > 60) return lang('Please enter a name of up to 60 characters.');
    $slug = prepare_file_name(($kind === 'look' ? 'look-' : 'palette-') . $name);
    $slug = preg_replace('/\.css$/i', '', $slug);
    if ($slug === '' || $slug === 'look-' || $slug === 'palette-') return lang('Please enter a valid theme name.');
    $file_name = $slug . '.css';
    for ($i = 2; check_name_availability(array('name' => $file_name)) == false && $i < 100; $i++) {
        $file_name = $slug . '-' . $i . '.css';
    }
    if (check_name_availability(array('name' => $file_name)) == false) {
        return lang(array('string' => 'The Theme ({var:1}) already exists.', 'vars' => $file_name));
    }
    if (@file_put_contents(FILE_DIRECTORY_PATH . '/' . $file_name, $css) === false) {
        return lang(array('string' => '{var:1} could not be written to disk.', 'vars' => $file_name));
    }
    $folder_id = (int)db_value("SELECT folder_id FROM folder WHERE folder_parent = '0' ORDER BY folder_id LIMIT 1");
    db("INSERT INTO files (name, folder, description, type, size, user, design, theme, timestamp)
        VALUES ('" . e($file_name) . "', '$folder_id', '', 'css', '" . (int)strlen($css) . "', '" . (int)$user_id . "', '1', '0', UNIX_TIMESTAMP())");
    $id = (int)mysqli_insert_id(db::$con);
    if ($id <= 0) return lang('The theme could not be saved.');
    return array('id' => $id, 'name' => $name, 'file' => $file_name,
                 'url' => (defined('OUTPUT_PATH') ? OUTPUT_PATH : '/') . $file_name . '?v=' . time());
}

// ── Thumbnails ─────────────────────────────────────────────────────────────

// The CSS variables a design thumbnail is drawn with: the palette's colours
// and the look's corners, outline and shadow. The template dialog repeats
// this in JavaScript to redraw the thumbnails as the choice changes.
function pg_design_thumb_vars($look, $palette)
{
    $look = pg_design_look_key($look);
    $pv = array('radius' => 6, 'btn_radius' => 6, 'shadow' => 'none', 'border' => 1);
    $looks = pg_design_looks();
    $fid = pg_design_theme_file_id($look);
    if ($fid > 0) {
        $files = pg_design_theme_files('look');
        $base = isset($files[$fid]['meta']['base']) ? (string)$files[$fid]['meta']['base'] : '';
        if (isset($looks[$base])) $pv = $looks[$base]['preview'];
    } elseif ($look !== '') {
        $pv = $looks[$look]['preview'];
    }
    $c = pg_design_palette_colors($palette);
    $p = _pg_theme_rgb($c['primary']);
    $shadows = array(
        'none'  => 'none',
        'soft'  => 'drop-shadow(0 2px 3px rgba(16, 24, 40, 0.16))',
        'crisp' => 'drop-shadow(0 1px 1px rgba(0, 0, 0, 0.18))',
        'glow'  => 'drop-shadow(0 3px 4px rgba(' . ($p ? _pg_theme_rgb_list($p) : '13, 110, 253') . ', 0.35))',
        'hard'  => 'drop-shadow(2px 2px 0 #111827)',
        'deep'  => 'drop-shadow(0 4px 6px rgba(0, 0, 0, 0.28))',
    );
    $r  = min((float)$pv['radius'], 14) * 0.5;
    $br = ((float)$pv['btn_radius'] >= 50) ? 5.5 : min((float)$pv['btn_radius'], 14) * 0.5;
    $bw = (int)$pv['border'];
    return '--tp:' . $c['primary'] . ';--ts:' . $c['secondary'] . ';--tr:' . $r . 'px;--tbr:' . $br . 'px;'
         . '--tsh:' . (isset($shadows[$pv['shadow']]) ? $shadows[$pv['shadow']] : 'none') . ';'
         . '--tbw:' . ($bw * 0.75) . 'px;--tbc:' . ($pv['shadow'] === 'hard' ? '#111827' : '#e5e7eb');
}

// A small drawing of a design's home page in its look and colours: the
// picture a template is known by, in the template dialog and on the list of
// designs. Inline SVG, coloured through CSS variables (pg_design_thumb_vars()).
// $kind is the template's 'thumb': a drawing of its own when a function
// _pg_design_thumb_<kind>() exists (dashes in the kind become underscores:
// 'store' draws a shop front with _pg_design_thumb_store()), the starter
// site's home page for anything else. Such a function returns the inside of
// a 320x200 <svg>, drawn with the same variables. The kind comes from a
// template file, so it is checked before it names a function.
function pg_design_thumb_svg($look, $palette, $class = '', $label = '', $kind = '')
{
    $vars = pg_design_thumb_vars($look, $palette);
    $a11y = ($label !== '') ? ' role="img" aria-label="' . h($label) . '"' : ' aria-hidden="true" focusable="false"';
    $draw = (is_string($kind) && preg_match('/^[a-z0-9-]+$/', $kind)) ? '_pg_design_thumb_' . str_replace('-', '_', $kind) : '';
    if ($draw !== '' && function_exists($draw)) {
        return '<svg class="' . h(trim('pg-design-thumb ' . $class)) . '" viewBox="0 0 320 200" xmlns="http://www.w3.org/2000/svg" style="' . h($vars) . '"' . $a11y . '>'
            . $draw() . '</svg>';
    }
    $card = function ($x, $y) {
        return '<g style="filter:var(--tsh)">'
             . '<rect x="' . $x . '" y="' . $y . '" width="84" height="36" rx="3" style="rx:var(--tr);fill:#fff;stroke:var(--tbc);stroke-width:var(--tbw)"/>'
             . '</g>'
             . '<rect x="' . ($x + 7) . '" y="' . ($y + 7) . '" width="11" height="11" rx="3" style="rx:var(--tr);fill:var(--tp);opacity:.18"/>'
             . '<rect x="' . ($x + 9.5) . '" y="' . ($y + 9.5) . '" width="6" height="6" rx="1.5" style="fill:var(--tp)"/>'
             . '<rect x="' . ($x + 7) . '" y="' . ($y + 22) . '" width="48" height="4" rx="2" fill="#1f2937"/>'
             . '<rect x="' . ($x + 7) . '" y="' . ($y + 29) . '" width="64" height="3" rx="1.5" fill="#cbd5e1"/>';
    };
    return '<svg class="' . h(trim('pg-design-thumb ' . $class)) . '" viewBox="0 0 320 200" xmlns="http://www.w3.org/2000/svg" style="' . h($vars) . '"' . $a11y . '>'
        // page and navigation
        . '<rect width="320" height="200" fill="#fff"/>'
        . '<rect width="320" height="20" fill="#fff"/><rect y="19.5" width="320" height="0.75" fill="#e5e7eb"/>'
        . '<circle cx="14" cy="10" r="4" style="fill:var(--tp)"/><rect x="22" y="7" width="30" height="6" rx="2" fill="#111827"/>'
        . '<rect x="150" y="8.5" width="18" height="3" rx="1.5" fill="#94a3b8"/><rect x="174" y="8.5" width="18" height="3" rx="1.5" fill="#94a3b8"/>'
        . '<rect x="198" y="8.5" width="18" height="3" rx="1.5" fill="#94a3b8"/><rect x="222" y="8.5" width="18" height="3" rx="1.5" fill="#94a3b8"/>'
        . '<rect x="262" y="4.5" width="46" height="11" rx="3" style="rx:var(--tbr);fill:var(--tp)"/>'
        // hero: words, two buttons, a picture
        . '<rect y="20" width="320" height="74" style="fill:var(--tp);opacity:.07"/>'
        . '<rect x="18" y="31" width="34" height="6" rx="3" style="fill:var(--tp);opacity:.25"/>'
        . '<rect x="18" y="42" width="128" height="9" rx="2" fill="#111827"/><rect x="18" y="54" width="96" height="9" rx="2" fill="#111827"/>'
        . '<rect x="18" y="67" width="130" height="3.5" rx="1.75" fill="#94a3b8"/><rect x="18" y="73" width="108" height="3.5" rx="1.75" fill="#94a3b8"/>'
        . '<rect x="18" y="80" width="44" height="10" rx="3" style="rx:var(--tbr);fill:var(--tp)"/>'
        . '<rect x="67" y="80" width="42" height="10" rx="3" style="rx:var(--tbr);fill:#fff;stroke:var(--ts);stroke-width:1"/>'
        . '<g style="filter:var(--tsh)"><rect x="176" y="29" width="126" height="58" rx="4" style="rx:var(--tr);fill:var(--ts)"/></g>'
        . '<rect x="176" y="29" width="126" height="58" rx="4" style="rx:var(--tr);fill:var(--tp);opacity:.55"/>'
        . '<path d="M188 80 L214 56 L232 72 L246 62 L290 80 Z" fill="#fff" opacity=".55"/><circle cx="276" cy="44" r="6" fill="#fff" opacity=".7"/>'
        // three feature cards
        . '<rect x="128" y="102" width="64" height="5" rx="2" fill="#111827"/>'
        . $card(18, 113) . $card(118, 113) . $card(218, 113)
        // numbers band in the second colour
        . '<rect y="158" width="320" height="22" style="fill:var(--ts)"/>'
        . '<rect x="34" y="164" width="30" height="6" rx="1.5" fill="#fff"/><rect x="34" y="173" width="40" height="2.5" rx="1.25" fill="#fff" opacity=".6"/>'
        . '<rect x="112" y="164" width="30" height="6" rx="1.5" fill="#fff"/><rect x="112" y="173" width="40" height="2.5" rx="1.25" fill="#fff" opacity=".6"/>'
        . '<rect x="190" y="164" width="30" height="6" rx="1.5" fill="#fff"/><rect x="190" y="173" width="40" height="2.5" rx="1.25" fill="#fff" opacity=".6"/>'
        . '<rect x="268" y="164" width="30" height="6" rx="1.5" fill="#fff"/><rect x="268" y="173" width="30" height="2.5" rx="1.25" fill="#fff" opacity=".6"/>'
        // footer
        . '<rect y="180" width="320" height="20" fill="#f8fafc"/><rect y="180" width="320" height="0.75" fill="#e5e7eb"/>'
        . '<rect x="18" y="188.5" width="50" height="3" rx="1.5" fill="#94a3b8"/><rect x="220" y="188.5" width="82" height="3" rx="1.5" fill="#94a3b8"/>'
        . '</svg>';
}

// The shop front: a header with search and cart, the category links, an
// offer band, four products with their prices and cart buttons, the
// footer with the payment marks. Same variables as the home page drawing.
function _pg_design_thumb_store()
{
    $products = array(
        // a shirt
        '<path d="M%1$s %2$s l5 -2.5 q3 3 6 0 l5 2.5 l3.5 5.5 l-4.5 2.2 v13.3 h-14 v-13.3 l-4.5 -2.2 z" style="fill:var(--tp);opacity:.6"/>',
        // a bag
        '<rect x="%3$s" y="%4$s" width="18" height="15" rx="2" style="fill:var(--ts);opacity:.75"/><path d="M%5$s %4$s v-3 a4 4 0 0 1 8 0 v3" fill="none" style="stroke:var(--ts)" stroke-width="1.4"/>',
        // a watch
        '<rect x="%6$s" y="%7$s" width="8" height="26" rx="2" fill="#cbd5e1"/><circle cx="%8$s" cy="%9$s" r="7.5" style="fill:var(--tp)"/><circle cx="%8$s" cy="%9$s" r="5.2" fill="#fff"/><path d="M%8$s %10$s v3.5 h2.5" fill="none" stroke="#1f2937" stroke-width="1" stroke-linecap="round"/>',
        // headphones
        '<path d="M%11$s %12$s v-4 a10 10 0 0 1 20 0 v4" fill="none" style="stroke:var(--ts)" stroke-width="2.2"/><rect x="%13$s" y="%14$s" width="6" height="10" rx="2" style="fill:var(--tp)"/><rect x="%15$s" y="%14$s" width="6" height="10" rx="2" style="fill:var(--tp)"/>',
    );
    $cards = '';
    foreach (array(12, 88, 164, 240) as $i => $x) {
        $y  = 103;
        $cx = $x + 34;
        $cy = $y + 20;
        $cards .= '<g style="filter:var(--tsh)"><rect x="' . $x . '" y="' . $y . '" width="68" height="66" rx="3" style="rx:var(--tr);fill:#fff;stroke:var(--tbc);stroke-width:var(--tbw)"/></g>'
            . '<rect x="' . ($x + 4) . '" y="' . ($y + 4) . '" width="60" height="32" rx="2" style="rx:var(--tr);fill:var(--tp);opacity:.08"/>'
            . sprintf($products[$i],
                $cx - 8, $cy - 9,           // shirt: the left shoulder
                $cx - 9, $cy - 5, $cx - 4,  // bag: body, handle
                $cx - 4, $cy - 13, $cx, $cy, $cy - 3.5, // watch: band, face, hands
                $cx - 10, $cy + 4, $cx - 13, $cy + 1, $cx + 7) // headphones: band, cups
            . '<rect x="' . ($x + 5) . '" y="' . ($y + 41) . '" width="' . ($i === 1 ? 38 : 46) . '" height="3.5" rx="1.75" fill="#1f2937"/>'
            . '<rect x="' . ($x + 5) . '" y="' . ($y + 47.5) . '" width="30" height="2.5" rx="1.25" fill="#cbd5e1"/>'
            . '<rect x="' . ($x + 5) . '" y="' . ($y + 54) . '" width="20" height="5" rx="1.5" style="fill:var(--tp)"/>'
            . ($i === 1 ? '<rect x="' . ($x + 28) . '" y="' . ($y + 55.25) . '" width="11" height="2.5" rx="1.25" fill="#cbd5e1"/>'
                        . '<rect x="' . ($x + 7) . '" y="' . ($y + 7) . '" width="16" height="6" rx="3" style="fill:var(--ts)"/><rect x="' . ($x + 10) . '" y="' . ($y + 9.25) . '" width="10" height="1.5" rx=".75" fill="#fff"/>' : '')
            . '<rect x="' . ($x + 45) . '" y="' . ($y + 52) . '" width="18" height="9" rx="3" style="rx:var(--tbr);fill:var(--tp)"/>'
            . '<path d="M' . ($x + 51) . ' ' . ($y + 56.5) . ' h6 M' . ($x + 54) . ' ' . ($y + 53.5) . ' v6" stroke="#fff" stroke-width="1.1" stroke-linecap="round"/>';
    }
    return '<rect width="320" height="200" fill="#fff"/>'
        // the offer line above the header
        . '<rect width="320" height="8" style="fill:var(--ts)"/><rect x="120" y="3" width="80" height="2" rx="1" fill="#fff" opacity=".75"/>'
        // header: name, search, account, cart with its count
        . '<circle cx="16" cy="18" r="4.5" style="fill:var(--tp)"/><rect x="24" y="15" width="28" height="6" rx="2" fill="#111827"/>'
        . '<rect x="96" y="12.5" width="128" height="11" rx="5.5" style="fill:#f8fafc;stroke:var(--tbc);stroke-width:.75"/>'
        . '<circle cx="104" cy="17.6" r="2.4" fill="none" stroke="#94a3b8" stroke-width="1"/><path d="M105.8 19.4 l1.8 1.8" stroke="#94a3b8" stroke-width="1" stroke-linecap="round"/>'
        . '<rect x="112" y="16.8" width="44" height="2.4" rx="1.2" fill="#cbd5e1"/>'
        . '<circle cx="262" cy="16" r="2.4" fill="none" stroke="#475569" stroke-width="1.1"/><path d="M257.6 23 q4.4 -4.6 8.8 0" fill="none" stroke="#475569" stroke-width="1.1" stroke-linecap="round"/>'
        . '<path d="M279 15 h10 l-1 9 h-8 z" fill="none" stroke="#475569" stroke-width="1.1" stroke-linejoin="round"/><path d="M281.5 15 v-1.2 a2.5 2.5 0 0 1 5 0 v1.2" fill="none" stroke="#475569" stroke-width="1.1"/>'
        . '<circle cx="290.5" cy="13" r="3.6" style="fill:var(--tp)"/><rect x="289.6" y="11.6" width="1.8" height="2.8" rx=".5" fill="#fff"/>'
        . '<rect y="27.5" width="320" height="0.75" fill="#e5e7eb"/>'
        // the categories
        . '<rect x="18" y="31.5" width="22" height="3" rx="1.5" style="fill:var(--tp)"/><rect x="18" y="36.5" width="22" height="1" style="fill:var(--tp)"/>'
        . '<rect x="50" y="31.5" width="26" height="3" rx="1.5" fill="#94a3b8"/><rect x="86" y="31.5" width="20" height="3" rx="1.5" fill="#94a3b8"/>'
        . '<rect x="116" y="31.5" width="28" height="3" rx="1.5" fill="#94a3b8"/><rect x="154" y="31.5" width="18" height="3" rx="1.5" fill="#94a3b8"/>'
        . '<rect y="37.5" width="320" height="0.75" fill="#e5e7eb"/>'
        // the offer band: words, a button, a bag and a discount
        . '<g style="filter:var(--tsh)"><rect x="12" y="44" width="296" height="42" rx="4" style="rx:var(--tr);fill:var(--tp)"/></g>'
        . '<rect x="26" y="51" width="30" height="4" rx="2" fill="#fff" opacity=".6"/><rect x="26" y="59" width="96" height="7" rx="2" fill="#fff"/>'
        . '<rect x="26" y="69" width="70" height="3" rx="1.5" fill="#fff" opacity=".7"/>'
        . '<rect x="26" y="75" width="36" height="7" rx="3" style="rx:var(--tbr)" fill="#fff"/><rect x="32" y="77.75" width="24" height="1.5" rx=".75" style="fill:var(--tp)"/>'
        . '<rect x="206" y="57" width="22" height="22" rx="2" fill="#fff" opacity=".35"/><path d="M211.5 57 v-3 a5.5 5.5 0 0 1 11 0 v3" fill="none" stroke="#fff" stroke-width="1.4" opacity=".6"/>'
        . '<circle cx="268" cy="65" r="14" fill="#fff" opacity=".92"/>'
        . '<circle cx="263.6" cy="60.6" r="2.2" fill="none" style="stroke:var(--tp)" stroke-width="1.4"/><circle cx="272.4" cy="69.4" r="2.2" fill="none" style="stroke:var(--tp)" stroke-width="1.4"/>'
        . '<path d="M273.2 59 l-10.4 12" style="stroke:var(--tp)" stroke-width="1.6" stroke-linecap="round"/>'
        // the products
        . '<rect x="12" y="92" width="52" height="5" rx="2" fill="#111827"/><rect x="276" y="93" width="32" height="3" rx="1.5" style="fill:var(--tp)"/>'
        . $cards
        // footer: links, the payment marks
        . '<rect y="176" width="320" height="24" fill="#f8fafc"/><rect y="176" width="320" height="0.75" fill="#e5e7eb"/>'
        . '<rect x="12" y="184" width="50" height="3" rx="1.5" fill="#94a3b8"/><rect x="12" y="190" width="34" height="2.5" rx="1.25" fill="#cbd5e1"/>'
        . '<rect x="236" y="183.5" width="14" height="9" rx="1.5" fill="#fff" stroke="#e5e7eb" stroke-width=".75"/><rect x="254" y="183.5" width="14" height="9" rx="1.5" fill="#fff" stroke="#e5e7eb" stroke-width=".75"/>'
        . '<rect x="272" y="183.5" width="14" height="9" rx="1.5" fill="#fff" stroke="#e5e7eb" stroke-width=".75"/><rect x="290" y="183.5" width="14" height="9" rx="1.5" fill="#fff" stroke="#e5e7eb" stroke-width=".75"/>'
        . '<circle cx="241" cy="188" r="2.2" style="fill:var(--tp)" opacity=".7"/><circle cx="244" cy="188" r="2.2" style="fill:var(--ts)" opacity=".7"/>'
        . '<rect x="257" y="186.5" width="8" height="3" rx="1" fill="#94a3b8"/><rect x="275" y="186.5" width="8" height="3" rx="1" fill="#94a3b8"/><rect x="293" y="186.5" width="8" height="3" rx="1" fill="#94a3b8"/>';
}

// The playful personal site: a header with a round avatar and a sticker, a
// big "Hi" with three coloured stickers beside a round photo ringed by icon
// badges, four "currently" cards, three colourful project cards, the say-hi
// band and the footer. Same variables as the home page drawing.
function _pg_design_thumb_playground()
{
    $ink = '#111827';
    // A rounded block with the thick dark outline every sticker wears.
    $block = function ($x, $y, $w, $h, $fill) use ($ink) {
        return '<g style="filter:var(--tsh)"><rect x="' . $x . '" y="' . $y . '" width="' . $w . '" height="' . $h . '" rx="6" style="rx:var(--tr);fill:' . $fill . '" stroke="' . $ink . '" stroke-width="1.2"/></g>';
    };
    $pill = function ($x, $y, $w, $fill) use ($ink) {
        return '<rect x="' . $x . '" y="' . $y . '" width="' . $w . '" height="7" rx="3.5" fill="' . $fill . '" stroke="' . $ink . '" stroke-width=".8"/>'
             . '<rect x="' . ($x + 3) . '" y="' . ($y + 2.75) . '" width="' . ($w - 6) . '" height="1.5" rx=".75" fill="' . $ink . '" opacity=".7"/>';
    };
    $badge = function ($cx, $cy, $fill) use ($ink) {
        return '<circle cx="' . $cx . '" cy="' . $cy . '" r="7" fill="' . $fill . '" stroke="' . $ink . '" stroke-width="1.2"/>'
             . '<path d="M' . $cx . ' ' . ($cy - 3.5) . ' l1 2.5 l2.5 1 l-2.5 1 l-1 2.5 l-1 -2.5 l-2.5 -1 l2.5 -1 z" fill="#fff"/>';
    };

    // four "currently" cards: a soft colour each, an icon, a label, a value
    $currently = '';
    foreach (array('#fef3c7', '#cffafe', '#d1fae5', '#fee2e2') as $i => $fill) {
        $x = 12 + $i * 76;
        $currently .= $block($x, 103, 68, 24, $fill)
            . '<circle cx="' . ($x + 9) . '" cy="' . 111 . '" r="3.5" style="fill:var(--tp)"/>'
            . '<rect x="' . ($x + 6) . '" y="117" width="22" height="2.5" rx="1.25" fill="' . $ink . '" opacity=".75"/>'
            . '<rect x="' . ($x + 6) . '" y="121.5" width="' . (40 - $i * 4) . '" height="2.5" rx="1.25" fill="#64748b"/>';
    }

    // three project cards: a round icon sticker, a tag, the title, a line, "See it"
    $projects = '';
    foreach (array('#fde68a', '#a5f3fc', '#bbf7d0') as $i => $fill) {
        $x = 12 + $i * 101;
        $projects .= $block($x, 132, 93, 28, $fill)
            . '<circle cx="' . ($x + 11) . '" cy="143" r="6" fill="#fff" stroke="' . $ink . '" stroke-width="1"/>'
            . '<circle cx="' . ($x + 11) . '" cy="143" r="2.6" style="fill:var(--tp)"/>'
            . '<rect x="' . ($x + 66) . '" y="137" width="21" height="6" rx="3" fill="#fff" stroke="' . $ink . '" stroke-width=".7"/>'
            . '<rect x="' . ($x + 22) . '" y="139.5" width="' . (30 + $i * 4) . '" height="4" rx="2" fill="' . $ink . '"/>'
            . '<rect x="' . ($x + 7) . '" y="151" width="62" height="2.5" rx="1.25" fill="#475569"/>'
            . '<rect x="' . ($x + 7) . '" y="155" width="16" height="2.5" rx="1.25" fill="' . $ink . '"/>';
    }

    return '<rect width="320" height="200" fill="#fff"/>'
        // header: round avatar, name with its sticker, menu, a thick line under it
        . '<circle cx="17" cy="13" r="7" style="fill:var(--tp)" stroke="' . $ink . '" stroke-width="1.4"/>'
        . '<rect x="13.5" y="11.6" width="7" height="2.8" rx="1.4" fill="#fff"/>'
        . '<rect x="28" y="7" width="34" height="5" rx="2" fill="' . $ink . '"/>'
        . '<rect x="28" y="14.5" width="30" height="5" rx="2.5" fill="#22c55e" stroke="' . $ink . '" stroke-width=".7"/>'
        . '<rect x="178" y="11.5" width="16" height="3" rx="1.5" fill="#64748b"/><rect x="200" y="11.5" width="24" height="3" rx="1.5" fill="#64748b"/>'
        . '<rect x="230" y="11.5" width="12" height="3" rx="1.5" fill="#64748b"/><rect x="248" y="11.5" width="12" height="3" rx="1.5" fill="#64748b"/>'
        . '<rect x="266" y="11.5" width="20" height="3" rx="1.5" fill="#64748b"/><rect x="292" y="11.5" width="16" height="3" rx="1.5" style="fill:var(--tp)"/>'
        . '<rect y="25.5" width="320" height="1.6" fill="' . $ink . '"/>'
        // hero: a big "Hi", three stickers, two lines, two pill buttons
        . '<text x="16" y="62" font-family="system-ui,-apple-system,Segoe UI,Roboto,sans-serif" font-size="30" font-weight="900" fill="' . $ink . '">Hi!</text>'
        . $pill(18, 68, 24, '#fbbf24') . $pill(46, 68, 24, '#22d3ee') . $pill(74, 68, 46, '#f87171')
        . '<rect x="18" y="79" width="120" height="3" rx="1.5" fill="#94a3b8"/><rect x="18" y="84.5" width="96" height="3" rx="1.5" fill="#94a3b8"/>'
        . '<rect x="18" y="90" width="44" height="9" rx="4.5" style="fill:var(--tp)" stroke="' . $ink . '" stroke-width="1"/>'
        . '<rect x="25" y="93.5" width="30" height="2" rx="1" fill="#fff"/>'
        . '<rect x="66" y="90" width="28" height="9" rx="4.5" fill="#fff" stroke="' . $ink . '" stroke-width="1"/>'
        . '<rect x="72" y="93.5" width="16" height="2" rx="1" fill="' . $ink . '"/>'
        // the round photo: sky, sun and a hill inside the circle
        . '<g style="filter:var(--tsh)"><circle cx="246" cy="62" r="31" style="fill:var(--ts)"/></g>'
        . '<circle cx="246" cy="62" r="31" style="fill:var(--tp)" opacity=".45"/>'
        . '<circle cx="256" cy="50" r="6" fill="#fff" opacity=".8"/>'
        . '<path d="M216.5 71 Q234 56 250 68 Q262 60 275.5 71 A31 31 0 0 1 216.5 71 Z" fill="#fff" opacity=".6"/>'
        . '<circle cx="246" cy="62" r="31" fill="none" stroke="' . $ink . '" stroke-width="2"/>'
        . $badge(272, 37, '#fbbf24') . $badge(215, 62, '#22d3ee') . $badge(272, 87, '#22c55e')
        // what is on the desk right now, and the things made
        . $currently
        . $projects
        // the say-hi band in the palette colour, a white pill button
        . '<rect y="165" width="320" height="18" style="fill:var(--tp)"/>'
        . '<rect x="14" y="170" width="92" height="4.5" rx="2" fill="#fff"/><rect x="14" y="177" width="64" height="2.5" rx="1.25" fill="#fff" opacity=".65"/>'
        . '<rect x="270" y="169" width="36" height="10" rx="5" fill="#fff" stroke="' . $ink . '" stroke-width="1"/>'
        . '<rect x="278" y="173" width="20" height="2" rx="1" fill="' . $ink . '"/>'
        // footer: a thick line on top, the coffee note, social circles, the copyright
        . '<rect y="183" width="320" height="17" fill="#f8fafc"/><rect y="183" width="320" height="2" style="fill:var(--tp)"/>'
        . '<path d="M14 189 h6 v4 a3 3 0 0 1 -6 0 z" style="fill:var(--tp)"/><rect x="24" y="190.5" width="48" height="2.5" rx="1.25" fill="' . $ink . '"/>'
        . '<circle cx="146" cy="192" r="3.6" fill="#fff" stroke="' . $ink . '" stroke-width=".8"/><circle cx="155" cy="192" r="3.6" fill="#fff" stroke="' . $ink . '" stroke-width=".8"/>'
        . '<circle cx="164" cy="192" r="3.6" fill="#fff" stroke="' . $ink . '" stroke-width=".8"/><circle cx="173" cy="192" r="3.6" fill="#fff" stroke="' . $ink . '" stroke-width=".8"/>'
        . '<rect x="262" y="191" width="46" height="2.5" rx="1.25" fill="#94a3b8"/>';
}

// The boutique: a thin dark line, the name centred over a centred menu, a
// two-picture hero (a large picture with a small square laid over its
// corner), a strip of new arrivals, a dark band with a quote and three
// numbers, and a light footer. Same variables as the home page drawing.
function _pg_design_thumb_boutique()
{
    // A cut gem, the boutique's mark, drawn in the palette's colour.
    $gem = function ($cx, $cy, $s, $style) {
        $w = 18 * $s; $h = 16 * $s; $top = $cy - $h / 2; $mid = $top + 5 * $s;
        return '<path d="M' . ($cx - $w / 2 + 4 * $s) . ' ' . $top . ' h' . ($w - 8 * $s) . ' l' . (4 * $s) . ' ' . (5 * $s)
            . ' l' . (-$w / 2) . ' ' . ($h - 5 * $s) . ' l' . (-$w / 2) . ' ' . (-$h + 5 * $s) . ' z'
            . ' M' . ($cx - $w / 2) . ' ' . $mid . ' h' . $w
            . ' M' . ($cx - 3 * $s) . ' ' . $top . ' l' . (-2 * $s) . ' ' . (5 * $s) . ' l' . (5 * $s) . ' ' . ($h - 5 * $s)
            . ' M' . ($cx + 3 * $s) . ' ' . $top . ' l' . (2 * $s) . ' ' . (5 * $s) . ' l' . (-5 * $s) . ' ' . ($h - 5 * $s) . '"'
            . ' fill="none" stroke-width="' . (1.4 * $s) . '" stroke-linejoin="round" style="' . $style . '"/>';
    };
    // The new arrivals: square pictures with a "new" mark, the name and the
    // price under each; the fourth runs off the edge, as the strip scrolls.
    $cards = '';
    $tints = array('.10', '.18', '.07', '.14');
    foreach (array(18, 96, 174, 252) as $i => $x) {
        $cards .= '<g style="filter:var(--tsh)"><rect x="' . $x . '" y="124" width="70" height="28" rx="2" style="rx:var(--tr);fill:#fff;stroke:var(--tbc);stroke-width:var(--tbw)"/></g>'
            . '<rect x="' . $x . '" y="124" width="70" height="28" rx="2" style="rx:var(--tr);fill:var(--tp);opacity:' . $tints[$i] . '"/>'
            . '<rect x="' . ($x + 3) . '" y="127" width="12" height="4" fill="#fff"/><rect x="' . ($x + 5) . '" y="128.5" width="8" height="1" rx=".5" fill="#475569"/>'
            . '<circle cx="' . ($x + 35) . '" cy="138" r="7" fill="#fff" opacity=".55"/>'
            . '<rect x="' . ($x + 29) . '" y="134" width="12" height="8" rx="1.5" fill="none" style="stroke:var(--tp)" stroke-width="1.1"/>'
            . '<path d="M' . ($x + 32) . ' 134 v-1.5 a3 3 0 0 1 6 0 v1.5" fill="none" style="stroke:var(--tp)" stroke-width="1.1"/>'
            . '<rect x="' . $x . '" y="155" width="' . ($i % 2 ? 30 : 38) . '" height="2.6" rx="1.3" fill="#1f2937"/>'
            . '<rect x="' . $x . '" y="159.5" width="16" height="2" rx="1" fill="#94a3b8"/>';
    }
    // The name in widely spaced thin capitals.
    $brand = '';
    for ($i = 0; $i < 9; $i++) {
        $brand .= '<rect x="' . (132 + $i * 6.5) . '" y="12" width="3.4" height="4.2" rx=".3" fill="#111827" opacity="' . ($i === 4 ? '0' : '.7') . '"/>';
    }
    return '<rect width="320" height="200" fill="#fff"/>'
        // the thin dark line: the announcement and the account links
        . '<rect width="320" height="7" fill="#1f2937"/><rect x="18" y="2.75" width="54" height="1.5" rx=".75" fill="#fff" opacity=".7"/>'
        . '<rect x="262" y="2.75" width="14" height="1.5" rx=".75" fill="#fff" opacity=".7"/><rect x="280" y="2.75" width="22" height="1.5" rx=".75" fill="#fff" opacity=".7"/>'
        // the header: a pin on the left, the name in the middle, the search on the right
        . '<path d="M20 12.5 a2.6 2.6 0 0 1 5.2 0 c0 2 -2.6 4.6 -2.6 4.6 s-2.6 -2.6 -2.6 -4.6 z" fill="none" stroke="#475569" stroke-width=".9"/>'
        . '<rect x="27.5" y="13.2" width="20" height="2" rx="1" fill="#94a3b8"/>'
        . $brand
        . '<circle cx="298" cy="13.4" r="2.6" fill="none" stroke="#475569" stroke-width="1"/><path d="M300 15.4 l2 2" stroke="#475569" stroke-width="1" stroke-linecap="round"/>'
        // the menu, centred, the current page underlined
        . '<rect x="98" y="22.5" width="16" height="2" rx="1" fill="#475569"/><rect x="98" y="26.2" width="16" height=".8" fill="#111827"/>'
        . '<rect x="120" y="22.5" width="10" height="2" rx="1" fill="#94a3b8"/><rect x="136" y="22.5" width="26" height="2" rx="1" fill="#94a3b8"/>'
        . '<rect x="168" y="22.5" width="10" height="2" rx="1" fill="#94a3b8"/><rect x="184" y="22.5" width="16" height="2" rx="1" fill="#94a3b8"/>'
        . '<rect x="206" y="22.5" width="16" height="2" rx="1" fill="#94a3b8"/>'
        . '<rect y="30" width="320" height=".75" fill="#e5e7eb"/>'
        // the hero, left: a small line, a serif title on three lines, a sentence, a dark button and a link
        . '<rect x="18" y="44" width="30" height="2" rx="1" fill="#94a3b8"/>'
        . '<rect x="18" y="50" width="104" height="7" rx="1" fill="#111827"/><rect x="18" y="60" width="118" height="7" rx="1" fill="#111827"/>'
        . '<rect x="18" y="70" width="62" height="7" rx="1" fill="#111827"/>'
        . '<rect x="18" y="82" width="120" height="2.6" rx="1.3" fill="#94a3b8"/><rect x="18" y="87" width="96" height="2.6" rx="1.3" fill="#94a3b8"/>'
        . '<rect x="18" y="95" width="40" height="10" rx="0" style="rx:var(--tbr)" fill="#1f2937"/><rect x="25" y="99.25" width="26" height="1.5" rx=".75" fill="#fff"/>'
        . '<rect x="66" y="99.25" width="30" height="1.5" rx=".75" fill="#111827"/><rect x="66" y="101.8" width="30" height=".6" fill="#111827"/>'
        // the hero, right: the large picture and the small square over its corner
        . '<g style="filter:var(--tsh)"><rect x="172" y="38" width="130" height="66" rx="2" style="rx:var(--tr);fill:#fff"/></g>'
        . '<rect x="172" y="38" width="130" height="66" rx="2" style="rx:var(--tr);fill:var(--tp);opacity:.12"/>'
        . '<circle cx="240" cy="71" r="21" fill="#fff" opacity=".5"/>'
        . $gem(243, 74, 1.7, 'stroke:var(--tp);opacity:.35')
        . $gem(240, 71, 1.7, 'stroke:var(--tp)')
        . '<g style="filter:drop-shadow(0 2px 3px rgba(16,24,40,.22))"><rect x="156" y="90" width="28" height="28" fill="#fff"/></g>'
        . '<rect x="158.5" y="92.5" width="23" height="23" style="fill:var(--ts);opacity:.35"/>'
        . '<rect x="164" y="102" width="12" height="9" fill="none" style="stroke:var(--tp)" stroke-width="1.1"/><rect x="163" y="99" width="14" height="3" fill="none" style="stroke:var(--tp)" stroke-width="1.1"/>'
        . '<path d="M170 99 v12 M170 99 q-4 -4 -5 -1 M170 99 q4 -4 5 -1" fill="none" style="stroke:var(--tp)" stroke-width="1.1"/>'
        // the new arrivals
        . $cards
        // the dark band: a quote and three numbers
        . '<rect y="166" width="320" height="20" fill="#1f2937"/>'
        . '<rect x="18" y="171" width="120" height="2.6" rx="1.3" fill="#fff" opacity=".85"/><rect x="18" y="176" width="96" height="2.6" rx="1.3" fill="#fff" opacity=".85"/>'
        . '<rect x="18" y="181" width="40" height="1.6" rx=".8" fill="#fff" opacity=".45"/>'
        . '<rect x="214" y="171" width="10" height="5" rx="1" fill="#fff"/><rect x="211" y="179" width="16" height="1.6" rx=".8" fill="#fff" opacity=".5"/>'
        . '<rect x="244" y="171" width="12" height="5" rx="1" fill="#fff"/><rect x="242" y="179" width="16" height="1.6" rx=".8" fill="#fff" opacity=".5"/>'
        . '<rect x="276" y="171" width="10" height="5" rx="1" fill="#fff"/><rect x="273" y="179" width="16" height="1.6" rx=".8" fill="#fff" opacity=".5"/>'
        // the footer: the name centred, small links under it
        . '<rect y="186" width="320" height="14" fill="#f8fafc"/>'
        . '<rect x="140" y="189" width="40" height="3" rx=".5" fill="#475569"/>'
        . '<rect x="112" y="195" width="18" height="1.6" rx=".8" fill="#cbd5e1"/><rect x="136" y="195" width="18" height="1.6" rx=".8" fill="#cbd5e1"/>'
        . '<rect x="160" y="195" width="22" height="1.6" rx=".8" fill="#cbd5e1"/><rect x="188" y="195" width="18" height="1.6" rx=".8" fill="#cbd5e1"/>';
}

// The bookshop: a header with a wide search and a category row, the staff
// picks (a large dark panel beside three small picks), the bestsellers as
// horizontal cards, and the dark footer with the opening hours. Same
// variables as the home page drawing.
function _pg_design_thumb_bookshop()
{
    // A book standing on its own: cover, spine and a title band.
    $book = function ($x, $y, $w, $h, $fill) {
        return '<rect x="' . $x . '" y="' . $y . '" width="' . $w . '" height="' . $h . '" rx="1" style="' . $fill . '"/>'
            . '<rect x="' . $x . '" y="' . $y . '" width="' . max(1.5, $w * 0.14) . '" height="' . $h . '" rx=".75" fill="#000" opacity=".18"/>'
            . '<rect x="' . ($x + $w * 0.3) . '" y="' . ($y + $h * 0.22) . '" width="' . ($w * 0.55) . '" height="' . max(1.5, $h * 0.08) . '" rx=".75" fill="#fff" opacity=".8"/>';
    };

    // The three small picks on the right: a square cover and two lines.
    $picks = '';
    foreach (array(0, 1, 2) as $i) {
        $y = 42 + $i * 23.5;
        $picks .= '<g style="filter:var(--tsh)"><rect x="186" y="' . $y . '" width="124" height="20" rx="3" style="rx:var(--tr);fill:#fff;stroke:var(--tbc);stroke-width:var(--tbw)"/></g>'
            . '<rect x="188" y="' . ($y + 2) . '" width="16" height="16" rx="2" style="fill:var(--ts);opacity:.14"/>'
            . $book(192, $y + 4.5, 8, 11, $i === 1 ? 'fill:var(--tp)' : 'fill:var(--ts)')
            . '<rect x="209" y="' . ($y + 4) . '" width="24" height="2" rx="1" style="fill:var(--tp)"/>'
            . '<rect x="209" y="' . ($y + 8.5) . '" width="' . (52 - $i * 6) . '" height="3" rx="1.5" fill="#1f2937"/>'
            . '<rect x="209" y="' . ($y + 14) . '" width="' . (76 - $i * 8) . '" height="2" rx="1" fill="#cbd5e1"/>';
    }

    // The bestsellers: horizontal cards, a cover on the left, the badge,
    // the name, the price and the button on the right.
    $best = '';
    foreach (array(10, 162) as $i => $x) {
        $y = 128;
        $best .= '<g style="filter:var(--tsh)"><rect x="' . $x . '" y="' . $y . '" width="148" height="34" rx="3" style="rx:var(--tr);fill:#fff;stroke:var(--tbc);stroke-width:var(--tbw)"/></g>'
            . '<rect x="' . ($x + 1) . '" y="' . ($y + 1) . '" width="40" height="32" rx="2" style="fill:var(--tp);opacity:.08"/>'
            . $book($x + 12, $y + 6, 17, 22, $i === 0 ? 'fill:var(--tp)' : 'fill:var(--ts)')
            . '<rect x="' . ($x + 48) . '" y="' . ($y + 5) . '" width="24" height="5" rx="2.5" style="fill:var(--ts);opacity:.22"/>'
            . '<path d="M' . ($x + 51.5) . ' ' . ($y + 6.2) . ' l.8 1.5 1.6 .2 -1.2 1.1 .3 1.6 -1.5 -.8 -1.5 .8 .3 -1.6 -1.2 -1.1 1.6 -.2 z" style="fill:var(--ts)"/>'
            . '<rect x="' . ($x + 56) . '" y="' . ($y + 6.75) . '" width="13" height="1.5" rx=".75" style="fill:var(--ts)"/>'
            . '<rect x="' . ($x + 48) . '" y="' . ($y + 14) . '" width="' . ($i === 0 ? 58 : 48) . '" height="3.5" rx="1.75" fill="#1f2937"/>'
            . '<rect x="' . ($x + 48) . '" y="' . ($y + 25) . '" width="20" height="3.5" rx="1.75" fill="#1f2937"/>'
            . '<rect x="' . ($x + 108) . '" y="' . ($y + 22.5) . '" width="34" height="8" rx="2.5" style="rx:var(--tbr);fill:var(--tp)"/>'
            . '<rect x="' . ($x + 115) . '" y="' . ($y + 25.75) . '" width="20" height="1.5" rx=".75" fill="#fff"/>';
    }

    return '<rect width="320" height="200" fill="#fff"/>'
        // header: the brand mark and name, the wide search with its button,
        // the two thin links on the right
        . '<rect x="10" y="6" width="12" height="12" rx="2.5" style="fill:var(--tp)"/>'
        . '<path d="M12.6 9.6 q1.7 -.9 3.4 0 v5.4 q-1.7 -.9 -3.4 0 z M16 9.6 q1.7 -.9 3.4 0 v5.4 q-1.7 -.9 -3.4 0 z" fill="#fff"/>'
        . '<rect x="26" y="9.5" width="30" height="5" rx="2" fill="#111827"/>'
        . '<rect x="66" y="6" width="170" height="12" rx="3" style="rx:var(--tbr);fill:#fff;stroke:var(--tbc);stroke-width:.75"/>'
        . '<circle cx="73" cy="11.6" r="2.3" fill="none" stroke="#94a3b8" stroke-width="1"/><path d="M74.7 13.3 l1.6 1.6" stroke="#94a3b8" stroke-width="1" stroke-linecap="round"/>'
        . '<rect x="80" y="10.8" width="62" height="2.4" rx="1.2" fill="#cbd5e1"/>'
        . '<path d="M212 6 h21 a3 3 0 0 1 3 3 v6 a3 3 0 0 1 -3 3 h-21 z" style="fill:var(--tp)"/>'
        . '<rect x="217" y="11" width="14" height="2" rx="1" fill="#fff"/>'
        . '<circle cx="250" cy="11" r="2" fill="none" style="stroke:var(--tp)" stroke-width="1"/><rect x="254" y="10" width="20" height="2.4" rx="1.2" fill="#94a3b8"/>'
        . '<path d="M281 9.2 q0 4.4 4 4.4" fill="none" style="stroke:var(--tp)" stroke-width="1.2" stroke-linecap="round"/><rect x="288" y="10" width="22" height="2.4" rx="1.2" fill="#94a3b8"/>'
        // the category row: Browse, the four categories, events on the right
        . '<rect y="24" width="320" height="11" fill="#f8fafc"/><rect y="24" width="320" height=".75" fill="#e5e7eb"/><rect y="34.25" width="320" height=".75" fill="#e5e7eb"/>'
        . '<rect x="12" y="27.5" width="1.6" height="1.6" fill="#111827"/><rect x="14.5" y="27.5" width="1.6" height="1.6" fill="#111827"/><rect x="12" y="30" width="1.6" height="1.6" fill="#111827"/><rect x="14.5" y="30" width="1.6" height="1.6" fill="#111827"/>'
        . '<rect x="19" y="28.3" width="20" height="2.6" rx="1.3" fill="#111827"/>'
        . '<rect x="48" y="28.3" width="16" height="2.6" rx="1.3" fill="#94a3b8"/><rect x="72" y="28.3" width="34" height="2.6" rx="1.3" fill="#94a3b8"/>'
        . '<rect x="114" y="28.3" width="20" height="2.6" rx="1.3" fill="#94a3b8"/><rect x="142" y="28.3" width="24" height="2.6" rx="1.3" fill="#94a3b8"/>'
        . '<rect x="240" y="28.3" width="18" height="2.6" rx="1.3" fill="#94a3b8"/><rect x="264" y="28.3" width="22" height="2.6" rx="1.3" fill="#94a3b8"/><rect x="292" y="28.3" width="16" height="2.6" rx="1.3" fill="#94a3b8"/>'
        // the staff picks: a large dark panel with the book of the week
        . '<g style="filter:var(--tsh)"><rect x="10" y="42" width="170" height="68" rx="4" style="rx:var(--tr);fill:var(--tp)"/></g>'
        . '<rect x="10" y="42" width="170" height="68" rx="4" style="rx:var(--tr)" fill="#000" opacity=".22"/>'
        . '<rect x="20" y="49" width="42" height="5" rx="2.5" fill="#fff"/><rect x="25" y="50.75" width="33" height="1.5" rx=".75" style="fill:var(--tp)"/>'
        . '<rect x="20" y="58" width="112" height="7" rx="2" fill="#fff"/><rect x="20" y="67" width="70" height="7" rx="2" fill="#fff"/>'
        . '<rect x="20" y="78" width="96" height="14" rx="2.5" fill="#fff" opacity=".12"/>'
        . $book(24, 80, 8, 10, 'fill:#fff;opacity:.9')
        . '<rect x="37" y="82" width="40" height="3" rx="1.5" fill="#fff"/><rect x="37" y="87" width="58" height="2" rx="1" fill="#fff" opacity=".6"/>'
        . '<rect x="20" y="97" width="36" height="8" rx="2.5" style="rx:var(--tbr)" fill="#fff"/><rect x="25" y="100.25" width="26" height="1.5" rx=".75" style="fill:var(--tp)"/>'
        . '<rect x="60" y="97" width="36" height="8" rx="2.5" style="rx:var(--tbr)" fill="none" stroke="#fff" stroke-width=".9"/><rect x="65" y="100.25" width="26" height="1.5" rx=".75" fill="#fff"/>'
        // a stack of books on the panel
        . $book(140, 70, 12, 34, 'fill:#fff;opacity:.28') . $book(154, 64, 14, 40, 'fill:var(--ts)')
        . '<rect x="134" y="104" width="40" height="1.5" rx=".75" fill="#fff" opacity=".5"/>'
        . $picks
        // the bestsellers heading, with its rule, and the cards
        . '<rect x="10" y="115" width="44" height="2" rx="1" style="fill:var(--tp)"/><rect x="10" y="119" width="70" height="4.5" rx="2" fill="#111827"/>'
        . '<rect x="280" y="120" width="30" height="2.5" rx="1.25" style="fill:var(--tp)"/>'
        . '<rect x="10" y="125.5" width="300" height=".6" fill="#e5e7eb"/>'
        . $best
        // the dark footer: the opening hours, the links, the address, the payment marks
        . '<rect y="168" width="320" height="32" fill="#212529"/>'
        . '<rect x="10" y="174" width="30" height="2.4" rx="1.2" fill="#f8fafc"/>'
        . '<rect x="10" y="180" width="24" height="2" rx="1" fill="#6c757d"/><rect x="46" y="180" width="22" height="2" rx="1" fill="#adb5bd"/>'
        . '<rect x="10" y="185" width="18" height="2" rx="1" fill="#6c757d"/><rect x="46" y="185" width="22" height="2" rx="1" fill="#adb5bd"/>'
        . '<rect x="10" y="190" width="16" height="2" rx="1" fill="#6c757d"/><rect x="46" y="190" width="22" height="2" rx="1" fill="#adb5bd"/>'
        . '<rect x="112" y="174" width="22" height="2.4" rx="1.2" fill="#f8fafc"/>'
        . '<rect x="112" y="180" width="26" height="2" rx="1" fill="#adb5bd"/><rect x="112" y="185" width="34" height="2" rx="1" fill="#adb5bd"/><rect x="112" y="190" width="22" height="2" rx="1" fill="#adb5bd"/>'
        . '<rect x="158" y="180" width="22" height="2" rx="1" fill="#adb5bd"/><rect x="158" y="185" width="26" height="2" rx="1" fill="#adb5bd"/><rect x="158" y="190" width="18" height="2" rx="1" fill="#adb5bd"/>'
        . '<rect x="214" y="174" width="24" height="2.4" rx="1.2" fill="#f8fafc"/>'
        . '<circle cx="216" cy="181" r="1.3" style="fill:var(--tp)"/><rect x="220" y="180" width="44" height="2" rx="1" fill="#adb5bd"/>'
        . '<circle cx="216" cy="186" r="1.3" style="fill:var(--tp)"/><rect x="220" y="185" width="32" height="2" rx="1" fill="#adb5bd"/>'
        . '<rect x="274" y="186" width="10" height="7" rx="1.2" fill="none" stroke="#6c757d" stroke-width=".7"/><rect x="287" y="186" width="10" height="7" rx="1.2" fill="none" stroke="#6c757d" stroke-width=".7"/>'
        . '<rect x="300" y="186" width="10" height="7" rx="1.2" fill="none" stroke="#6c757d" stroke-width=".7"/>';
}
