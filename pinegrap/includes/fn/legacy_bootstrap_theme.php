<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * The Theme file of a Legacy design read as a colour palette and a look for
 * its Bootstrap 5.3.8 trial design (legacy_bootstrap_trial.php): the theme's
 * main colour, its dark colour and its fonts, saved as design CSS files
 * (file-<id>, design_themes.php) that the trial wears and every design can
 * choose. Once the trial wears them, the Theme file can be taken off in the
 * Page Design Studio (Settings, Theme file).
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_FUNCTIONS_DIR')) {
    exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/fn/legacy_bootstrap_trial.php');

// ── Pure helpers ────────────────────────────────────────────────────────

/**
 * The CSS colour names (CSS Color Module Level 4) as hex: the classic
 * theme's colour sheets are named after them and use them as values
 * (Blue.css: `a { color: Blue }`).
 *
 * Pure: no query.
 *
 * @return array  lower-case name => #rrggbb
 */
function pg_lbs_theme_named_colors()
{
    static $map = null;
    if ($map !== null) return $map;
    $list = 'aliceblue f0f8ff antiquewhite faebd7 aqua 00ffff aquamarine 7fffd4 azure f0ffff beige f5f5dc bisque ffe4c4 black 000000 blanchedalmond ffebcd '
        . 'blue 0000ff blueviolet 8a2be2 brown a52a2a burlywood deb887 cadetblue 5f9ea0 chartreuse 7fff00 chocolate d2691e coral ff7f50 cornflowerblue 6495ed '
        . 'cornsilk fff8dc crimson dc143c cyan 00ffff darkblue 00008b darkcyan 008b8b darkgoldenrod b8860b darkgray a9a9a9 darkgreen 006400 darkgrey a9a9a9 '
        . 'darkkhaki bdb76b darkmagenta 8b008b darkolivegreen 556b2f darkorange ff8c00 darkorchid 9932cc darkred 8b0000 darksalmon e9967a darkseagreen 8fbc8f '
        . 'darkslateblue 483d8b darkslategray 2f4f4f darkslategrey 2f4f4f darkturquoise 00ced1 darkviolet 9400d3 deeppink ff1493 deepskyblue 00bfff dimgray 696969 '
        . 'dimgrey 696969 dodgerblue 1e90ff firebrick b22222 floralwhite fffaf0 forestgreen 228b22 fuchsia ff00ff gainsboro dcdcdc ghostwhite f8f8ff gold ffd700 '
        . 'goldenrod daa520 gray 808080 green 008000 greenyellow adff2f grey 808080 honeydew f0fff0 hotpink ff69b4 indianred cd5c5c indigo 4b0082 ivory fffff0 '
        . 'khaki f0e68c lavender e6e6fa lavenderblush fff0f5 lawngreen 7cfc00 lemonchiffon fffacd lightblue add8e6 lightcoral f08080 lightcyan e0ffff '
        . 'lightgoldenrodyellow fafad2 lightgray d3d3d3 lightgreen 90ee90 lightgrey d3d3d3 lightpink ffb6c1 lightsalmon ffa07a lightseagreen 20b2aa '
        . 'lightskyblue 87cefa lightslategray 778899 lightslategrey 778899 lightsteelblue b0c4de lightyellow ffffe0 lime 00ff00 limegreen 32cd32 linen faf0e6 '
        . 'magenta ff00ff maroon 800000 mediumaquamarine 66cdaa mediumblue 0000cd mediumorchid ba55d3 mediumpurple 9370db mediumseagreen 3cb371 '
        . 'mediumslateblue 7b68ee mediumspringgreen 00fa9a mediumturquoise 48d1cc mediumvioletred c71585 midnightblue 191970 mintcream f5fffa mistyrose ffe4e1 '
        . 'moccasin ffe4b5 navajowhite ffdead navy 000080 oldlace fdf5e6 olive 808000 olivedrab 6b8e23 orange ffa500 orangered ff4500 orchid da70d6 '
        . 'palegoldenrod eee8aa palegreen 98fb98 paleturquoise afeeee palevioletred db7093 papayawhip ffefd5 peachpuff ffdab9 peru cd853f pink ffc0cb plum dda0dd '
        . 'powderblue b0e0e6 purple 800080 rebeccapurple 663399 red ff0000 rosybrown bc8f8f royalblue 4169e1 saddlebrown 8b4513 salmon fa8072 sandybrown f4a460 '
        . 'seagreen 2e8b57 seashell fff5ee sienna a0522d silver c0c0c0 skyblue 87ceeb slateblue 6a5acd slategray 708090 slategrey 708090 snow fffafa '
        . 'springgreen 00ff7f steelblue 4682b4 tan d2b48c teal 008080 thistle d8bfd8 tomato ff6347 turquoise 40e0d0 violet ee82ee wheat f5deb3 white ffffff '
        . 'whitesmoke f5f5f5 yellow ffff00 yellowgreen 9acd32';
    $parts = explode(' ', $list);
    $map = array();
    for ($i = 0; $i + 1 < count($parts); $i += 2) $map[$parts[$i]] = '#' . $parts[$i + 1];
    return $map;
}

/**
 * A CSS colour value as #rrggbb: #rgb, #rrggbb, rgb()/rgba() (opaque
 * enough) or a colour name. Null for anything else (a gradient, a variable,
 * a transparent colour, inherit).
 *
 * Pure: no query.
 *
 * @param string $value
 * @return string|null
 */
function pg_lbs_theme_hex($value)
{
    $v = strtolower(trim(str_replace('!important', '', (string)$value)));
    if (preg_match('/^#([0-9a-f]{3})$/', $v, $m)) {
        return '#' . $m[1][0] . $m[1][0] . $m[1][1] . $m[1][1] . $m[1][2] . $m[1][2];
    }
    if (preg_match('/^#[0-9a-f]{6}$/', $v)) return $v;
    if (preg_match('/^rgba?\(\s*(\d{1,3})\s*,\s*(\d{1,3})\s*,\s*(\d{1,3})\s*(?:,\s*([0-9.]+)\s*)?\)$/', $v, $m)) {
        if (isset($m[4]) && $m[4] !== '' && (float)$m[4] < 0.6) return null;
        return sprintf('#%02x%02x%02x', min(255, (int)$m[1]), min(255, (int)$m[2]), min(255, (int)$m[3]));
    }
    $named = pg_lbs_theme_named_colors();
    return $named[$v] ?? null;
}

/**
 * How coloured a colour is (0 grey, 1 pure) and how light (0 black, 1
 * white), the HSL saturation and lightness.
 *
 * Pure: no query.
 *
 * @return array  's', 'l'
 */
function pg_lbs_theme_hsl($hex)
{
    $r = hexdec(substr($hex, 1, 2)) / 255;
    $g = hexdec(substr($hex, 3, 2)) / 255;
    $b = hexdec(substr($hex, 5, 2)) / 255;
    $max = max($r, $g, $b);
    $min = min($r, $g, $b);
    $l = ($max + $min) / 2;
    $d = $max - $min;
    $s = ($d == 0) ? 0 : $d / (1 - abs(2 * $l - 1));
    return array('s' => $s, 'l' => $l);
}

/**
 * The rules of a stylesheet: selector list => declarations (property =>
 * value), comments left out, the rules inside @media read too.
 *
 * Pure: no query.
 *
 * @return array  list of: 'selectors' (list), 'decl' (property => value)
 */
function pg_lbs_theme_rules($css)
{
    $css = preg_replace('#/\*.*?\*/#s', '', (string)$css);
    $out = array();
    if (!preg_match_all('/([^{}@;]+)\{([^{}]*)\}/', (string)$css, $m, PREG_SET_ORDER)) return $out;
    foreach ($m as $rule) {
        $decl = array();
        foreach (explode(';', $rule[2]) as $d) {
            $at = strpos($d, ':');
            if ($at === false) continue;
            $decl[strtolower(trim(substr($d, 0, $at)))] = trim(substr($d, $at + 1));
        }
        $selectors = array_values(array_filter(array_map(function ($s) {
            return preg_replace('/\s+/', ' ', trim($s));
        }, explode(',', $rule[1])), 'strlen'));
        if ($selectors) $out[] = array('selectors' => $selectors, 'decl' => $decl);
    }
    return $out;
}

/**
 * A theme's colours and fonts, read from its stylesheets in order (the
 * Theme file first): the first sheet that names one wins.
 *
 * - primary: the background of .bg-primary or .btn-primary, else the colour
 *   of .color-primary or of links, else the most used colour that is not a
 *   grey;
 * - secondary: the background of .bg-dark, else the text colour of body,
 *   else a dark grey;
 * - body and heading fonts: the font-family of body and of h1.
 *
 * Pure: no query.
 *
 * @param array $sheets  CSS texts, the Theme file first
 * @return array  'primary', 'secondary' (#rrggbb or ''), 'body', 'heading'
 *                (font-family lists or '')
 */
function pg_lbs_theme_read($sheets)
{
    $out = array('primary' => '', 'secondary' => '', 'body' => '', 'heading' => '');
    $count = array();
    $pick = function ($rules, $selector, $props) {
        foreach ($rules as $rule) {
            if (!in_array($selector, $rule['selectors'], true)) continue;
            foreach ($props as $p) {
                if (!isset($rule['decl'][$p])) continue;
                $value = $rule['decl'][$p];
                if ($p === 'font-family') return $value;
                foreach (preg_split('/\s+(?![^(]*\))/', $value) as $token) {
                    $hex = pg_lbs_theme_hex($token);
                    if ($hex !== null) return $hex;
                }
            }
        }
        return '';
    };
    foreach ((array)$sheets as $css) {
        $rules = pg_lbs_theme_rules($css);
        if ($out['primary'] === '') {
            foreach (array(array('.bg-primary', array('background', 'background-color')), array('.btn-primary', array('background', 'background-color')),
                           array('.color-primary', array('color')), array('a', array('color'))) as $try) {
                $hex = $pick($rules, $try[0], $try[1]);
                if ($hex !== '' && pg_lbs_theme_hsl($hex)['s'] >= 0.2) {
                    $out['primary'] = $hex;
                    break;
                }
            }
        }
        if ($out['secondary'] === '') {
            $hex = $pick($rules, '.bg-dark', array('background', 'background-color'));
            if ($hex === '') $hex = $pick($rules, 'body', array('color'));
            if ($hex !== '' && pg_lbs_theme_hsl($hex)['l'] < 0.5) $out['secondary'] = $hex;
        }
        if ($out['body'] === '') $out['body'] = pg_lbs_theme_font($pick($rules, 'body', array('font-family')));
        if ($out['heading'] === '') $out['heading'] = pg_lbs_theme_font($pick($rules, 'h1', array('font-family')));
        foreach ($rules as $rule) {
            foreach (array('color', 'background', 'background-color', 'border-color') as $p) {
                if (!isset($rule['decl'][$p])) continue;
                foreach (preg_split('/\s+(?![^(]*\))/', $rule['decl'][$p]) as $token) {
                    $hex = pg_lbs_theme_hex($token);
                    if ($hex !== null && pg_lbs_theme_hsl($hex)['s'] >= 0.25) $count[$hex] = ($count[$hex] ?? 0) + 1;
                }
            }
        }
    }
    if ($out['primary'] === '' && $count) {
        arsort($count);
        $out['primary'] = (string)key($count);
    }
    if ($out['secondary'] === '' && $out['primary'] !== '') $out['secondary'] = '#292929';
    return $out;
}

/**
 * A font-family list fit for a look's token: the names and generic families
 * only, an icon font refused. '' for none.
 *
 * Pure: no query.
 */
function pg_lbs_theme_font($value)
{
    $value = trim(str_replace('!important', '', (string)$value));
    if ($value === '' || !preg_match('/^[A-Za-z0-9 ,"\'_-]+$/', $value)) return '';
    if (preg_match('/themify|icon|glyph|awesome/i', $value)) return '';
    if (preg_match('/^(inherit|initial|unset)$/i', $value)) return '';
    return preg_replace('/\s*,\s*/', ', ', $value);
}

/**
 * The look of a theme: the default look's tokens with the theme's fonts.
 *
 * Pure apart from reading the default look's file.
 *
 * @param string $name
 * @param string $body     font-family list, '' to keep the look's
 * @param string $heading  font-family list, '' to follow the text
 * @return string  CSS with the pg-theme header
 */
function pg_lbs_theme_look_css($name, $body, $heading)
{
    $base = pg_design_default_look();
    $t = pg_design_look_tokens($base);
    if ($body !== '') $t['--pg-font-body'] = $body;
    if ($heading !== '') {
        $t['--pg-font-heading'] = $heading;
    } elseif ($body !== '') {
        $t['--pg-font-heading'] = 'var(--pg-font-body)';
    }
    $css = pg_design_theme_header(array('kind' => 'look', 'name' => $name, 'base' => $base, 'controls' => (object)array())) . "\n"
        . '/* Pinegrap look "' . pg_design_theme_comment_text($name) . "\", read from a Legacy design's Theme file. */\n"
        . ":root,\n[data-bs-theme] {\n";
    foreach ($t as $k => $v) {
        if (strpbrk((string)$v, '{};<>') !== false) continue;
        $css .= '    ' . $k . ': ' . $v . ";\n";
    }
    return $css . "}\n";
}

// ── Making them ─────────────────────────────────────────────────────────

/**
 * The stylesheets a trial design's theme is read from, the Theme file
 * first: the trial's Theme file (its own copy), else the Legacy design's
 * (its theme_id, else the activated theme it loads), then the trial's site
 * stylesheets in their order.
 *
 * @param int   $legacy_id
 * @param array $record  pg_lbs_trial_of()
 * @return array  CSS texts
 */
function pg_lbs_theme_sheets($legacy_id, $record)
{
    $trial = db_item("SELECT theme_id, style_custom_css FROM style WHERE style_id = '" . (int)$record['trial'] . "' LIMIT 1");
    if (!$trial) return array();
    $name = '';
    if ((int)$trial['theme_id'] > 0) {
        $name = (string)db_value("SELECT name FROM files WHERE id = '" . (int)$trial['theme_id'] . "' LIMIT 1");
    } else {
        $legacy = db_item("SELECT theme_id, style_custom_css FROM style WHERE style_id = '" . (int)$legacy_id . "' LIMIT 1");
        if ($legacy && (int)$legacy['theme_id'] > 0) {
            $name = (string)db_value("SELECT name FROM files WHERE id = '" . (int)$legacy['theme_id'] . "' LIMIT 1");
        } elseif ($legacy) {
            $classic = pg_legacy_design_classic_theme((int)$legacy_id, 'legacy', 'desktop', (string)$legacy['style_custom_css'], null);
            if ($classic) $name = (string)$classic['name'];
        }
    }
    $sheets = array();
    $names = ($name !== '') ? array($name) : array();
    $rows = json_decode((string)$trial['style_custom_css'], true);
    foreach ((is_array($rows) ? $rows : array()) as $row) {
        if (!is_array($row) || (isset($row['enabled']) && $row['enabled'] === false)) continue;
        if ((string)($row['type'] ?? '') === 'css') {
            $sheets[] = (string)($row['content'] ?? '');
            continue;
        }
        $file = pg_lbs_site_file($row, (string)OUTPUT_PATH);
        if ($file !== '' && !in_array($file, $names, true)) $names[] = $file;
    }
    $read = array();
    foreach ($names as $file) {
        $text = pg_lbs_read_site_file($file);
        if ($text !== null) $read[] = $text;
    }
    return array_merge($read, $sheets);
}

/**
 * Makes a trial design's palette and look from its theme and puts them on
 * the trial (style_palette, style_look). Each is a design CSS file in the
 * file manager every design can choose; they stay when the trial is
 * deleted.
 *
 * @param int   $legacy_id
 * @param array $user  id, username
 * @return array  'ok', 'error', 'palette', 'look' (file names), 'read'
 *                (pg_lbs_theme_read())
 */
function pg_lbs_theme_make($legacy_id, $user)
{
    $out = array('ok' => false, 'error' => '', 'palette' => '', 'look' => '', 'read' => array());
    $trials = pg_lbs_trials_read();
    $record = pg_lbs_trial_of($legacy_id, $trials);
    if ($record === null) {
        $out['error'] = lang('This design has no trial design.');
        return $out;
    }
    if (!pg_design_look_ready()) {
        $out['error'] = lang('Looks and palettes need the database update of 2026.4.5.');
        return $out;
    }
    $read = pg_lbs_theme_read(pg_lbs_theme_sheets($legacy_id, $record));
    $out['read'] = $read;
    if ($read['primary'] === '') {
        $out['error'] = lang('The Theme file names no colour to make a palette from.');
        return $out;
    }
    $legacy_name = (string)db_value("SELECT style_name FROM style WHERE style_id = '" . (int)$legacy_id . "' LIMIT 1");
    $name = mb_substr($legacy_name !== '' ? $legacy_name : 'Legacy', 0, 60);

    $css = pg_design_palette_css($read['primary'], $read['secondary'], $name);
    if ($css === false) {
        $out['error'] = lang('The palette could not be made.');
        return $out;
    }
    $css = pg_design_theme_header(array('kind' => 'palette', 'name' => $name, 'primary' => $read['primary'], 'secondary' => $read['secondary'])) . "\n" . $css;
    $palette = pg_design_theme_save_file('palette', $name, $css, (int)$user['id']);
    if (!is_array($palette)) {
        $out['error'] = $palette;
        return $out;
    }
    $look = pg_design_theme_save_file('look', $name, pg_lbs_theme_look_css($name, $read['body'], $read['heading']), (int)$user['id']);
    if (!is_array($look)) {
        $out['error'] = $look;
        return $out;
    }

    db("UPDATE style SET style_palette = 'file-" . (int)$palette['id'] . "', style_look = 'file-" . (int)$look['id'] . "' WHERE style_id = '" . (int)$record['trial'] . "' LIMIT 1");
    $trials[(string)(int)$legacy_id]['theme'] = array('palette' => (int)$palette['id'], 'look' => (int)$look['id']) + $read;
    pg_lbs_trials_write($trials);
    log_activity(lang(array('string' => 'the trial design ({var:1}) wears a palette and a look read from its Theme file', 'vars' => array($record['name']))), (string)($user['username'] ?? ''));
    $out['ok'] = true;
    $out['palette'] = $palette['file'];
    $out['look'] = $look['file'];
    return $out;
}

/**
 * Under a trial design on the "Towards Bootstrap 5" card: "Make a palette
 * and a look from the Theme file", or the ones made, with their colours and
 * fonts and whether the trial still wears them.
 *
 * @param int        $legacy_id
 * @param array      $record  pg_lbs_trial_of()
 * @param array|null $wears   the trial's style_palette and style_look, null
 *                            when the columns are not there
 * @return string  HTML
 */
function pg_lbs_theme_card_html($legacy_id, $record, $wears)
{
    if ($wears === null) return '';
    $id = (int)$legacy_id;
    $theme = is_array($record['theme'] ?? null) ? $record['theme'] : null;
    $out = '<div class="mt-5" id="ltd-theme-' . $id . '"><h4 class="h6 mb-2">' . h(lang('Palette and look')) . '</h4>';
    $form = '<form method="post" action="legacy_to_designer.php" class="d-inline">' . get_token_field()
        . '<input type="hidden" name="bootstrap_theme" value="make"><input type="hidden" name="design" value="' . $id . '">';
    if ($theme === null) {
        return $out . '<div class="d-flex flex-wrap align-items-center gap-3">' . $form
            . '<button type="submit" class="btn btn-sm btn-outline theme-secondary rounded-pill px-4"><i class="bi bi-palette me-2" aria-hidden="true"></i>'
            . h(lang('Make a palette and a look from the Theme file')) . '</button></form>'
            . '<span class="small fg-secondary">' . h(lang('Its main colour, its dark colour and its fonts become a palette and a look the trial design wears.')) . '</span></div></div>';
    }
    $swatch = function ($hex) {
        return '<span class="d-inline-block rounded-circle border align-middle me-1" style="width: 1rem; height: 1rem; background: ' . h($hex) . '"></span>' . h($hex);
    };
    $worn = ((string)$wears['palette'] === 'file-' . (int)$theme['palette']) && ((string)$wears['look'] === 'file-' . (int)$theme['look']);
    $fonts = array_filter(array((string)($theme['body'] ?? ''), (string)($theme['heading'] ?? '')));
    $out .= '<div class="small">' . $swatch((string)$theme['primary']) . ' <span class="ms-3">' . $swatch((string)$theme['secondary']) . '</span>'
        . ($fonts ? '<span class="ms-3">' . h(implode(' / ', array_unique($fonts))) . '</span>' : '') . '</div>'
        . '<div class="small fg-secondary mt-1">' . h($worn
            ? lang('The trial design wears them. In the Page Design Studio, Settings, the Theme file can now be taken off.')
            : lang('The trial design wears another palette or look now; both stay in the Themes tab.')) . '</div>';
    return $out . '</div>';
}
