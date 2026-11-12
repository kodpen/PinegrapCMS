<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * The theme file of a visual design moved from a classic style.
 *
 * A classic style's head printed a theme at its <stylesheet></stylesheet>
 * tag: the style's own theme (style.theme_id), else the site's activated
 * theme, the mobile one on a mobile style. A visual design loads only the
 * Theme file chosen in its settings, so a moved style with no theme of its
 * own lost the activated theme and every page of it looked unstyled. A
 * design that came from a classic style keeps loading the activated theme
 * while it has no Theme file, in the place the page frame prints the theme,
 * unless one of its stylesheet rows already links that file.
 *
 * Loaded by get_page_content.php, the designer screen and the dry run of the
 * bulk move; light on purpose, as every page of a moved design reads it.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_FUNCTIONS_DIR')) {
    exit;
}

// The category that marks the shared component made from a classic style.
function pg_classic_style_component_category($style_id)
{
    return 'classic_style:' . (int)$style_id;
}

// A LIKE pattern (to be passed through e()) matching the categories of a
// classic style's split frame parts: "classic_style:<id>:<part>".
function pg_classic_style_part_category_like($style_id)
{
    return addcslashes(pg_classic_style_component_category($style_id) . ':', '\\%_') . '%';
}

/**
 * The file name a classic style's link to one of the site's own files
 * names ({path}custom.css, or the same under the site's output path), or ''
 * for any other reference: a CDN, a software file ({software_directory}), a
 * path below a folder (files are stored flat, by name).
 *
 * Pure: no query, no file.
 *
 * @param string $ref          the link's href or the script's src
 * @param string $output_path  OUTPUT_PATH ('' to skip that form)
 */
function pg_classic_style_site_file_name($ref, $output_path)
{
    $ref = trim((string)$ref);
    $rest = null;
    if (stripos($ref, '{path}') === 0) {
        $rest = (string)substr($ref, 6);
    } elseif ($output_path !== '' && $output_path !== '/' && strpos($ref, (string)$output_path) === 0) {
        $rest = (string)substr($ref, strlen($output_path));
    } elseif ($output_path === '/' && $ref !== '' && $ref[0] === '/' && substr($ref, 0, 2) !== '//') {
        $rest = (string)substr($ref, 1);
    }
    if ($rest === null || $rest === '' || stripos($rest, '{software_directory}') !== false) return '';
    $rest = (string)preg_replace('/[?#].*$/s', '', $rest);
    if ($rest === '' || strpos($rest, '/') !== false || strpos($rest, '\\') !== false) return '';
    return rawurldecode($rest);
}

/**
 * Whether one of a design's stylesheet rows links the site file $name: an
 * enabled linked stylesheet (not a framework row) whose address names it
 * ({path}name or the same under the output path, a query string ignored).
 * File names compare without case, as the files table does.
 *
 * Pure: no query, no file.
 *
 * @param array|string $rows         the design's CSS rows, decoded or as stored (JSON)
 * @param string       $name         the file name
 * @param string       $output_path  OUTPUT_PATH
 * @param int|null     $page_id      the page drawn: a row kept to other pages
 *                                   does not count; null counts every row
 */
function pg_legacy_design_rows_link($rows, $name, $output_path, $page_id = null)
{
    if (is_string($rows)) $rows = json_decode($rows, true);
    $want = strtolower(trim((string)$name));
    if (!is_array($rows) || $want === '') return false;
    foreach ($rows as $r) {
        if (!is_array($r) || (string)($r['type'] ?? '') !== 'external-css' || !empty($r['meta'])) continue;
        if (isset($r['enabled']) && $r['enabled'] === false) continue;
        if ($page_id !== null && !pg_design_asset_on_page($r, $page_id)) continue;
        if (strtolower(pg_classic_style_site_file_name((string)($r['content'] ?? ''), (string)$output_path)) === $want) return true;
    }
    return false;
}

/**
 * The theme file a design loads: its own Theme file; else, for a design
 * that came from a classic style, the site's activated theme as the classic
 * head chose it (the mobile one on a mobile style, the desktop one when no
 * mobile theme is activated); none when one of the design's stylesheet rows
 * already links that file, which would load it twice.
 *
 * Pure: no query, no file.
 *
 * @param array $d  'own'         the design's Theme file, array('id', 'name') or null
 *                  'classic'     bool, the design came from a classic style
 *                  'device'      'desktop' or 'mobile', the style drawn
 *                  'desktop'     the activated desktop theme, array('id', 'name') or null
 *                  'mobile'      the activated mobile theme, the same
 *                  'rows'        the design's CSS rows (pg_legacy_design_rows_link())
 *                  'output_path' OUTPUT_PATH
 *                  'page_id'     the page drawn, or null
 * @return array|null  array('id' => int, 'name' => string, 'source' => 'own'|'classic')
 */
function pg_legacy_design_theme($d)
{
    $named = function ($t) {
        return is_array($t) && trim((string)($t['name'] ?? '')) !== '';
    };
    $own = $d['own'] ?? null;
    if ($named($own)) {
        return array('id' => (int)($own['id'] ?? 0), 'name' => trim((string)$own['name']), 'source' => 'own');
    }
    if (empty($d['classic'])) return null;

    $pick = ((string)($d['device'] ?? '') === 'mobile') ? ($d['mobile'] ?? null) : null;
    if (!$named($pick)) $pick = $d['desktop'] ?? null;
    if (!$named($pick)) return null;

    $name = trim((string)$pick['name']);
    if (pg_legacy_design_rows_link($d['rows'] ?? array(), $name, (string)($d['output_path'] ?? ''), $d['page_id'] ?? null)) {
        return null;
    }
    return array('id' => (int)($pick['id'] ?? 0), 'name' => $name, 'source' => 'classic');
}

/**
 * Whether a classic style's head loads CSS (a stylesheet link or a style
 * block) after its <stylesheet></stylesheet> tag. The classic page printed
 * the theme at the tag, so that CSS came after the theme and won over it;
 * the moved design prints the theme after all of its stylesheets, so the
 * theme wins there instead. Commented-out markup and scripts load nothing.
 *
 * Pure: no query, no file.
 *
 * @param string $style_code
 */
function pg_legacy_style_css_after_theme($style_code)
{
    $code = (string)preg_replace('~<!--.*?-->~s', '', (string)$style_code);
    $code = (string)preg_replace('~<script\b[^>]*>.*?</script\s*>~is', '', $code);
    if (!preg_match('~<stylesheet></stylesheet>~i', $code, $m, PREG_OFFSET_CAPTURE)) return false;
    $rest = (string)substr($code, $m[0][1] + strlen($m[0][0]));
    if (preg_match('~</head\s*>|<body\b~i', $rest, $end, PREG_OFFSET_CAPTURE)) {
        $rest = (string)substr($rest, 0, $end[0][1]);
    }
    return (bool)preg_match('~<style\b|<link\b[^>]*\brel\s*=\s*["\']?\s*stylesheet\b~i', $rest);
}

/**
 * Whether a visual design came from a classic style: its framework key
 * carries the legacy flag. Saving a style opened in the visual designer and
 * the bulk move write that key; the 2026.11.0 step gave it to the styles
 * moved before it. A copy of such a design keeps the key, and its pages
 * keep the same frame, so it loads the same theme.
 *
 * @param int    $style_id
 * @param string $framework  the design's pg_design_frameworks() key
 */
function pg_legacy_design_from_classic($style_id, $framework)
{
    return (int)$style_id > 0 && pg_design_framework_has($framework, 'legacy');
}

/**
 * The site's activated themes, as the classic head looked them up.
 *
 * @return array  'desktop', 'mobile' => array('id', 'name') or null
 */
function pg_legacy_design_activated_themes()
{
    $one = function ($column) {
        $row = db_item("SELECT id, name FROM files WHERE $column = '1' LIMIT 1");
        return (is_array($row) && (string)($row['name'] ?? '') !== '') ? array('id' => (int)$row['id'], 'name' => (string)$row['name']) : null;
    };
    return array('desktop' => $one('activated_desktop_theme'), 'mobile' => $one('activated_mobile_theme'));
}

/**
 * The activated theme a design moved from a classic style loads while it
 * has no Theme file of its own, or null: not such a design, no activated
 * theme, or a stylesheet row of the design links it already.
 *
 * @param int          $style_id
 * @param string       $framework    the design's pg_design_frameworks() key
 * @param string       $device_type  'desktop' or 'mobile'
 * @param array|string $css_rows     the design's CSS rows (style_custom_css)
 * @param int|null     $page_id      the page drawn, or null (the editor)
 * @return array|null  array('id', 'name', 'source' => 'classic')
 */
function pg_legacy_design_classic_theme($style_id, $framework, $device_type, $css_rows, $page_id = null)
{
    if (!pg_legacy_design_from_classic($style_id, $framework)) return null;
    $active = pg_legacy_design_activated_themes();
    return pg_legacy_design_theme(array(
        'own'         => null,
        'classic'     => true,
        'device'      => (string)$device_type,
        'desktop'     => $active['desktop'],
        'mobile'      => $active['mobile'],
        'rows'        => $css_rows,
        'output_path' => defined('OUTPUT_PATH') ? (string)OUTPUT_PATH : '/',
        'page_id'     => $page_id,
    ));
}

/**
 * The theme a moved design loads, as the visual designer reads it
 * (sdDesign.themeFile): its id, its file name and its address, or null when
 * the design was not moved from a classic style, no theme is activated or a
 * stylesheet row links it already. The editor shows the desktop theme, and
 * a row kept to some pages counts as linking it, as the editor names one
 * theme for the whole design.
 *
 * @param int    $style_id
 * @param string $framework The design's framework key.
 * @param mixed  $css_rows  style_custom_css: JSON or the decoded list.
 * @return array|null array('id' => int, 'name' => string, 'url' => string)
 */
function pg_legacy_design_theme_js($style_id, $framework, $css_rows)
{
    $theme = pg_legacy_design_classic_theme($style_id, $framework, 'desktop', $css_rows, null);
    if (!$theme) return null;
    return array(
        'id'   => (int)$theme['id'],
        'name' => (string)$theme['name'],
        'url'  => (defined('OUTPUT_PATH') ? (string)OUTPUT_PATH : '/') . $theme['name'],
    );
}
