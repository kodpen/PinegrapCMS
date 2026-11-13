<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * The "Towards Bootstrap 5" card of Move to the Page Design Studio
 * (legacy_to_designer.php): the Legacy designs, whether each loads
 * Bootstrap 3, and one design's report: what the converter
 * (includes/fn/legacy_bootstrap.php) would rename in its pages and shared
 * parts, what it keeps and what it leaves for a designer, the stylesheets
 * whose selectors it would rename, the scripts that call Bootstrap 3's
 * plugins, and the files a design on Bootstrap 5.3.8 leaves out.
 *
 * Nothing is written: the rows and files are read, converted in memory and
 * counted.
 *
 * Bootstrap 3 is found by the design's classic framework sheet row
 * (bootstrap.css, bootstrap.min.css), never by class names: Materialize
 * shares row, container, btn, badge, active and right with it.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_FUNCTIONS_DIR')) {
    exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/fn/legacy_bootstrap.php');
require_once(PG_FUNCTIONS_DIR . '/includes/fn/legacy_design_theme.php');
require_once(PG_FUNCTIONS_DIR . '/includes/fn/legacy_to_designer_run.php');

// The largest site file the report reads; a bigger one is listed unread.
const PG_LBS_READ_LIMIT = 4194304;

/**
 * The file name a linked asset row loads, lower case, a query string and
 * the {path} and {software_directory} placeholders left out; '' for an
 * inline row or a row with no address.
 *
 * Pure: no query, no file.
 *
 * @param array $row  an asset row (style_custom_css / style_custom_js)
 * @return string
 */
function pg_lbs_asset_name($row)
{
    if (!is_array($row) || !in_array((string)($row['type'] ?? ''), array('external-css', 'external-js'), true)) return '';
    $address = (string)preg_replace('/[?#].*$/s', '', trim((string)($row['content'] ?? '')));
    $address = (string)preg_replace('/\{[a-z_]+\}/i', '/', $address);
    $slash = strrpos($address, '/');
    return strtolower(rawurldecode($slash === false ? $address : (string)substr($address, $slash + 1)));
}

/**
 * The major version a Bootstrap file's address names (a CDN's
 * "bootstrap/3.3.7/", "bootstrap@5.3.8", a folder "bootstrap-3.4.1-dist"),
 * 0 when it names none.
 *
 * Pure: no query, no file.
 *
 * @param string $address
 * @return int
 */
function pg_lbs_address_version($address)
{
    return preg_match('~bootstrap(?:@|/|-)v?([0-9]+)\.[0-9]+~i', (string)$address, $m) ? (int)$m[1] : 0;
}

/**
 * The major version a Bootstrap file's banner names ("Bootstrap v3.3.5",
 * 5.x writes two spaces before the v), 0 when there is none.
 *
 * Pure: no query, no file.
 *
 * @param string $head  the file's first bytes
 * @return int
 */
function pg_lbs_sheet_version($head)
{
    return preg_match('~Bootstrap\s+v([0-9]+)\.[0-9]+~', (string)$head, $m) ? (int)$m[1] : 0;
}

/**
 * A Legacy design's asset rows as the helper reads them: whether it loads
 * Bootstrap 3, the rows a design on Bootstrap 5.3.8 leaves out (the classic
 * framework sheet and Bootstrap 3's script: the framework brings 5.3.8's
 * own), and the stylesheets and scripts it keeps.
 *
 * The framework sheet is an enabled linked stylesheet row named
 * bootstrap.css or bootstrap.min.css. A version its address names, else
 * the one its banner names ($version_of), other than 3 is another
 * Bootstrap; no version at all reads as 3. A framework row of the editor
 * (meta) and a disabled row load nothing of the theme and are not listed.
 *
 * Pure apart from $version_of.
 *
 * @param array|string $css_rows    style_custom_css, decoded or as stored (JSON)
 * @param array|string $js_rows     style_custom_js, the same
 * @param callable     $version_of  function (array $row): the major version
 *                                  the file's banner names, 0 when unknown
 * @return array  'bootstrap3' (bool), 'framework' (the sheet's file name, ''
 *                when none), 'version' (its major version, 0 unknown),
 *                'left_out', 'sheets', 'scripts' (asset rows each)
 */
function pg_lbs_design_assets($css_rows, $js_rows, $version_of)
{
    $out = array('bootstrap3' => false, 'framework' => '', 'version' => 0,
                 'left_out' => array(), 'sheets' => array(), 'scripts' => array());
    $lists = array('css' => $css_rows, 'js' => $js_rows);
    foreach ($lists as $kind => $rows) {
        if (is_string($rows)) $rows = json_decode($rows, true);
        $lists[$kind] = array();
        foreach ((is_array($rows) ? $rows : array()) as $r) {
            if (!is_array($r) || !empty($r['meta'])) continue;
            if (isset($r['enabled']) && ($r['enabled'] === false || $r['enabled'] === 0 || $r['enabled'] === '0')) continue;
            $lists[$kind][] = $r;
        }
    }

    $sheet_names = array('bootstrap.css', 'bootstrap.min.css');
    foreach ($lists['css'] as $r) {
        if ((string)$r['type'] !== 'external-css' || !in_array(pg_lbs_asset_name($r), $sheet_names, true)) continue;
        $out['framework'] = pg_lbs_asset_name($r);
        $out['version'] = pg_lbs_address_version((string)($r['content'] ?? ''));
        if ($out['version'] === 0) $out['version'] = (int)call_user_func($version_of, $r);
        break;
    }
    $out['bootstrap3'] = $out['framework'] !== '' && in_array($out['version'], array(0, 3), true);
    if (!$out['bootstrap3']) return $out;

    $script_names = array('bootstrap.js', 'bootstrap.min.js');
    foreach ($lists['css'] as $r) {
        if (in_array(pg_lbs_asset_name($r), $sheet_names, true)) $out['left_out'][] = $r;
        else $out['sheets'][] = $r;
    }
    foreach ($lists['js'] as $r) {
        if (in_array(pg_lbs_asset_name($r), $script_names, true)) $out['left_out'][] = $r;
        else $out['scripts'][] = $r;
    }
    return $out;
}

/**
 * The calls of Bootstrap 3's jQuery plugins in a script ("tooltip()" for a
 * tooltip call on a jQuery selection) and the data attributes it names
 * that 5.3.8 renames (data-toggle in a selector), each with its count, most
 * first. Lines that are a // comment are not read. Under 5.3.8 neither works: the plugins
 * are not jQuery's, and the markup's data-* names have become data-bs-*.
 *
 * Pure: no query, no file.
 *
 * @param string $js
 * @return array  name => count
 */
function pg_lbs_script_calls($js)
{
    $js = (string)preg_replace('~/\*.*?\*/~s', '', (string)$js);
    $js = (string)preg_replace('~^[ \t]*//.*$~m', '', $js);
    $out = array();
    if (preg_match_all('~\)\s*\.\s*(affix|alert|button|carousel|collapse|dropdown|modal|popover|scrollspy|tab|tooltip)\s*\(~', $js, $m)) {
        foreach ($m[1] as $name) {
            $out[$name . '()'] = (isset($out[$name . '()']) ? $out[$name . '()'] : 0) + 1;
        }
    }
    if (preg_match_all('~(?<![a-zA-Z0-9_-])data-[a-zA-Z-]+~', $js, $m)) {
        foreach ($m[0] as $name) {
            $name = strtolower($name);
            if (pg_lbs_data_attribute($name) === $name) continue;
            $out[$name] = (isset($out[$name]) ? $out[$name] : 0) + 1;
        }
    }
    arsort($out);
    return $out;
}

/**
 * The trees of a design (its pages and the shared parts they place)
 * converted in memory and summed: pg_lbs_report() of them all, how many
 * would change, and where each kept and by-hand name is.
 *
 * Pure: no query, no file.
 *
 * @param array $trees  list of array('label' => string, 'tree' => array)
 * @return array  pg_lbs_report()'s keys, 'trees' (read), 'changed' (trees
 *                the converter changes), 'where' ('kept', 'by_hand': name =>
 *                labels, in the order met)
 */
function pg_lbs_trees_report($trees)
{
    $results = array();
    $where = array('kept' => array(), 'by_hand' => array());
    $changed = 0;
    foreach ((array)$trees as $t) {
        if (!is_array($t) || !is_array($t['tree'] ?? null)) continue;
        $r = pg_lbs_tree($t['tree']);
        if ($r['tree'] !== $t['tree']) ++$changed;
        unset($r['tree']);
        $results[] = $r;
        $label = (string)($t['label'] ?? '');
        foreach (array('kept', 'by_hand') as $k) {
            foreach (array_keys($r[$k]) as $name) {
                if (!isset($where[$k][$name])) $where[$k][$name] = array();
                if (!in_array($label, $where[$k][$name], true)) $where[$k][$name][] = $label;
            }
        }
    }
    $out = pg_lbs_report($results);
    $out['trees'] = count($results);
    $out['changed'] = $changed;
    $out['where'] = $where;
    return $out;
}

/**
 * One stylesheet of the report: how many of its selectors' class names the
 * converter would rename, and how many it keeps. A sheet that was not read
 * (not one of the site's files, or missing) has null for its text.
 *
 * Pure: no query, no file.
 *
 * @param string      $label  its file name, or the inline row's label
 * @param string|null $css    its text, null when it was not read
 * @param bool        $theme  the design's Theme file
 * @return array  'label', 'theme', 'read', 'renamed', 'kept', 'names'
 *                (renamed class => count, most first)
 */
function pg_lbs_sheet_result($label, $css, $theme)
{
    $out = array('label' => (string)$label, 'theme' => (bool)$theme, 'read' => $css !== null,
                 'renamed' => 0, 'kept' => 0, 'names' => array());
    if ($css === null) return $out;
    $r = pg_lbs_css((string)$css);
    arsort($r['converted']);
    $out['renamed'] = array_sum($r['converted']);
    $out['kept'] = array_sum($r['kept']);
    $out['names'] = $r['converted'];
    return $out;
}

/**
 * The site file an asset row links (a managed file entry's name, or
 * {path}name and the same under the output path), '' for anything else.
 *
 * Pure: no query, no file.
 *
 * @param array  $row
 * @param string $output_path  OUTPUT_PATH
 * @return string
 */
function pg_lbs_site_file($row, $output_path)
{
    if (!is_array($row) || !in_array((string)($row['type'] ?? ''), array('external-css', 'external-js'), true)) return '';
    if (!empty($row['_file']) && is_string($row['name'] ?? null) && $row['name'] !== '' && strpbrk($row['name'], '/\\') === false) {
        return (string)$row['name'];
    }
    return pg_classic_style_site_file_name((string)($row['content'] ?? ''), (string)$output_path);
}

/**
 * The name an asset row is shown by: the name the assets panel gives it,
 * else its address.
 *
 * Pure: no query, no file.
 *
 * @param array $row
 * @return string
 */
function pg_lbs_row_label($row)
{
    $name = trim((string)($row['name'] ?? ''));
    return $name !== '' ? $name : trim((string)($row['content'] ?? ''));
}

/**
 * A site file's text, or null when it is not there or is bigger than the
 * report reads. $bytes > 0 reads only the first bytes.
 *
 * @param string $name   a file name of the files table (stored flat)
 * @param int    $bytes
 * @return string|null
 */
function pg_lbs_read_site_file($name, $bytes = 0)
{
    $name = (string)$name;
    if ($name === '' || strpbrk($name, '/\\') !== false || !defined('FILE_DIRECTORY_PATH')) return null;
    $path = FILE_DIRECTORY_PATH . '/' . $name;
    if (!is_file($path)) return null;
    $size = (int)@filesize($path);
    if ($bytes <= 0 && $size > PG_LBS_READ_LIMIT) return null;
    $text = ($bytes > 0) ? @file_get_contents($path, false, null, 0, (int)$bytes) : @file_get_contents($path);
    return ($text === false) ? null : (string)$text;
}

/**
 * The Legacy designs, by name, each with its number of pages (binned ones
 * left out) and its asset rows read (pg_lbs_design_assets()).
 *
 * @return array  list of: id, name, pages, assets
 */
function pg_lbs_designs()
{
    if (!pg_multi_page_design_ready() || !pg_style_framework_ready()) return array();
    $rows = db_items("SELECT style_id, style_name, style_custom_css, style_custom_js FROM style
                      WHERE style_layout = 'visual_designer' AND style_framework = 'legacy'
                      ORDER BY style_name ASC, style_id ASC");
    if (!is_array($rows) || !$rows) return array();

    $ids = array_map('intval', array_column($rows, 'style_id'));
    $counts = array();
    $found = db_items("SELECT page_style, COUNT(*) AS n FROM page
                       WHERE page_style IN (" . implode(',', $ids) . ")" . pg_designer_not_binned_sql('page_folder') . "
                       GROUP BY page_style");
    foreach ((is_array($found) ? $found : array()) as $r) $counts[(int)$r['page_style']] = (int)$r['n'];

    $version_of = function ($row) {
        return pg_lbs_sheet_version((string)pg_lbs_read_site_file(pg_lbs_site_file($row, (string)OUTPUT_PATH), 1024));
    };
    $out = array();
    foreach ($rows as $r) {
        $id = (int)$r['style_id'];
        $out[] = array(
            'id'     => $id,
            'name'   => (string)$r['style_name'],
            'pages'  => (int)($counts[$id] ?? 0),
            'assets' => pg_lbs_design_assets((string)$r['style_custom_css'], (string)$r['style_custom_js'], $version_of),
        );
    }
    return $out;
}

/**
 * One Legacy design's report, null when it is not a Legacy design or does
 * not load Bootstrap 3. Its pages' trees and every shared part they place
 * (parts inside parts too) are converted in memory; its own stylesheets
 * and its Theme file are read from the site's files; its own scripts are
 * searched for Bootstrap 3's plugins. Nothing is written.
 *
 * @param int        $design_id
 * @param array|null $designs    pg_lbs_designs() when the caller has it
 * @return array|null  'id', 'name', 'pages', 'parts', 'trees'
 *                     (pg_lbs_trees_report()), 'sheets' (pg_lbs_sheet_result()
 *                     each), 'scripts' (list of: label, calls), 'left_out'
 *                     (list of: kind, label)
 */
function pg_lbs_design_report($design_id, $designs = null)
{
    $design_id = (int)$design_id;
    $design = null;
    foreach (($designs === null ? pg_lbs_designs() : (array)$designs) as $d) {
        if ($d['id'] === $design_id) $design = $d;
    }
    if ($design === null || !$design['assets']['bootstrap3']) return null;
    $output_path = (string)OUTPUT_PATH;

    // The pages, then the parts they place, read in batches: only one
    // decoded tree is held at a time.
    $trees = array();
    $part_ids = array();
    $pages = db_items("SELECT page_id, page_name FROM page
                       WHERE page_style = '$design_id'" . pg_designer_not_binned_sql('page_folder') . "
                       ORDER BY page_name ASC, page_id ASC");
    $pages = is_array($pages) ? $pages : array();
    $names = array_column($pages, 'page_name', 'page_id');
    foreach (array_chunk(array_map('intval', array_column($pages, 'page_id')), 25) as $chunk) {
        $rows = db_items("SELECT page.page_id, " . pg_page_tree_sql_expr() . " AS tree_json
                          FROM page LEFT JOIN style ON page.page_style = style.style_id
                          WHERE page.page_id IN (" . implode(',', $chunk) . ")");
        foreach ((is_array($rows) ? $rows : array()) as $r) {
            $tree = json_decode((string)$r['tree_json'], true);
            if (!is_array($tree)) continue;
            foreach (pg_ltd_shared_ids($tree) as $sid) $part_ids[$sid] = true;
            $trees[] = array('label' => (string)($names[$r['page_id']] ?? ''), 'tree' => $tree);
        }
    }
    $page_count = count($trees);
    $seen = array();
    while ($want = array_diff(array_keys($part_ids), array_keys($seen))) {
        foreach (array_chunk(array_map('intval', $want), 25) as $chunk) {
            foreach ($chunk as $sid) $seen[$sid] = true;
            $rows = db_items("SELECT id, name, tree_json FROM shared_components WHERE id IN (" . implode(',', $chunk) . ")");
            foreach ((is_array($rows) ? $rows : array()) as $r) {
                $tree = json_decode((string)$r['tree_json'], true);
                if (!is_array($tree)) continue;
                foreach (pg_ltd_shared_ids($tree) as $sid) $part_ids[$sid] = true;
                $trees[] = array('label' => lang(array('string' => '{var:1} (shared part)', 'vars' => array((string)$r['name']))), 'tree' => $tree);
            }
        }
    }
    $tree_report = pg_lbs_trees_report($trees);
    unset($trees);

    // The stylesheets the design keeps, then its Theme file when no row
    // links it: the one it names, else the activated theme it loads.
    $sheets = array();
    $theme = '';
    $style = db_item("SELECT theme_id, style_custom_css FROM style WHERE style_id = '$design_id' LIMIT 1");
    $theme_id = (int)($style['theme_id'] ?? 0);
    if ($theme_id > 0) {
        $theme = (string)db_value("SELECT name FROM files WHERE id = '$theme_id' LIMIT 1");
    } else {
        $classic = pg_legacy_design_classic_theme($design_id, 'legacy', 'desktop', (string)($style['style_custom_css'] ?? ''), null);
        if ($classic) $theme = (string)$classic['name'];
    }
    $theme_listed = false;
    foreach ($design['assets']['sheets'] as $row) {
        if ((string)$row['type'] === 'css') {
            $sheets[] = pg_lbs_sheet_result(lang('Inline styles'), (string)($row['content'] ?? ''), false);
            continue;
        }
        $file = pg_lbs_site_file($row, $output_path);
        $is_theme = $file !== '' && strcasecmp($file, $theme) === 0;
        if ($is_theme) $theme_listed = true;
        $sheets[] = pg_lbs_sheet_result($file !== '' ? $file : pg_lbs_row_label($row), $file !== '' ? pg_lbs_read_site_file($file) : null, $is_theme);
    }
    if ($theme !== '' && !$theme_listed) {
        $sheets[] = pg_lbs_sheet_result($theme, pg_lbs_read_site_file($theme), true);
    }

    // The scripts it keeps that call Bootstrap 3's plugins.
    $scripts = array();
    foreach ($design['assets']['scripts'] as $row) {
        $file = pg_lbs_site_file($row, $output_path);
        if ((string)$row['type'] === 'js') {
            $label = lang('Inline script');
            $js = (string)($row['content'] ?? '');
        } else {
            $label = $file;
            $js = ($file !== '') ? pg_lbs_read_site_file($file) : null;
        }
        if ($js === null) continue;
        $calls = pg_lbs_script_calls($js);
        if ($calls) $scripts[] = array('label' => $label, 'calls' => $calls);
    }

    $left_out = array();
    foreach ($design['assets']['left_out'] as $row) {
        $file = pg_lbs_site_file($row, $output_path);
        $left_out[] = array(
            'kind'  => ((string)$row['type'] === 'external-js') ? 'js' : 'css',
            'label' => $file !== '' ? $file : pg_lbs_row_label($row),
        );
    }

    return array(
        'id'       => $design_id,
        'name'     => $design['name'],
        'pages'    => $page_count,
        'parts'    => $tree_report['trees'] - $page_count,
        'trees'    => $tree_report,
        'sheets'   => $sheets,
        'scripts'  => $scripts,
        'left_out' => $left_out,
    );
}

/**
 * Names with their counts as a list, at most $limit, then how many more.
 *
 * @param array $names  name => count
 * @param array $where  name => labels, or none
 * @param int   $limit
 * @return string  HTML
 */
function pg_lbs_names_html($names, $where = array(), $limit = 40)
{
    $items = '';
    $i = 0;
    foreach ((array)$names as $name => $n) {
        if (++$i > $limit) break;
        $at = '';
        if (!empty($where[$name])) {
            $labels = array_slice($where[$name], 0, 5);
            $more = count($where[$name]) - count($labels);
            $at = ' <span class="fg-secondary">' . h(implode(', ', $labels)
                . ($more > 0 ? ' ' . lang(array('string' => 'and {var:1} more', 'vars' => array(pg_format_number($more, 0)))) : '')) . '</span>';
        }
        $items .= '<li><code>' . h((string)$name) . '</code> ' . pg_format_number((int)$n, 0) . $at . '</li>';
    }
    $rest = count((array)$names) - $limit;
    if ($rest > 0) {
        $items .= '<li class="fg-secondary">' . h(lang(array('string' => '{var:1} more names', 'vars' => array(pg_format_number($rest, 0))))) . '</li>';
    }
    return '<ul class="mb-0 mt-1 ps-12">' . $items . '</ul>';
}

/**
 * The report of one design, under its line on the card.
 *
 * @param array $r  pg_lbs_design_report()
 * @return string  HTML
 */
function pg_lbs_report_html($r)
{
    $t = $r['trees'];
    $out = '<p class="small mb-3">' . h(lang(array('string' => 'Read: {var:1} pages and {var:2} shared parts, {var:3} of them with a name to convert.',
        'vars' => array(pg_format_number($r['pages'], 0), pg_format_number($r['parts'], 0), pg_format_number($t['changed'], 0))))) . '</p>';

    $groups = array(
        array('converted', lang('Converted'), lang('Bootstrap 3 names and data attributes renamed to their Bootstrap 5.3.8 names.')),
        array('by_hand', lang('By hand'), lang('Names with no Bootstrap 5.3.8 twin: a designer finishes them in the editor.')),
        array('kept', lang('Kept'), lang('Glyphicons with no Bootstrap Icons twin: they stay as they are and show nothing once Bootstrap 3\'s sheet is left out.')),
    );
    foreach ($groups as $g) {
        $n = (int)$t['totals'][$g[0]];
        $summary = lang(array('string' => '{var:1}: {var:2} uses of {var:3} names', 'vars' => array($g[1], pg_format_number($n, 0), pg_format_number(count($t[$g[0]]), 0))));
        if ($n === 0) {
            $out .= '<div class="small mb-2">' . h($summary) . '</div>';
            continue;
        }
        $out .= '<details class="small mb-2"><summary>' . h($summary) . '</summary>'
            . '<div class="fg-secondary mt-1">' . h($g[2]) . '</div>'
            . pg_lbs_names_html($t[$g[0]], (array)($t['where'][$g[0]] ?? array())) . '</details>';
    }

    $items = '';
    foreach ($r['sheets'] as $s) {
        $label = h($s['label']) . ($s['theme'] ? ' <span class="fg-secondary">(' . h(lang('Theme file')) . ')</span>' : '');
        if (!$s['read']) {
            $text = lang('Not read: not one of the site\'s files, it stays as it is.');
        } elseif ($s['renamed'] > 0) {
            $text = lang(array('string' => '{var:1} class names in its selectors renamed: a design on Bootstrap 5.3.8 gets a copy of its own.', 'vars' => array(pg_format_number($s['renamed'], 0))));
        } else {
            $text = lang('No selector to rename: loaded as it is.');
        }
        $items .= '<li><span class="text-break">' . $label . '</span>: ' . h($text) . '</li>';
    }
    $out .= '<h4 class="h6 mt-5 mb-1">' . h(lang('Stylesheets')) . '</h4>'
        . ($items !== '' ? '<ul class="small mb-0 ps-12">' . $items . '</ul>' : '<p class="small mb-0">' . h(lang('The design keeps no stylesheet of its own.')) . '</p>');

    $items = '';
    foreach ($r['scripts'] as $s) {
        $calls = array();
        foreach ($s['calls'] as $name => $n) $calls[] = $name . ' ' . pg_format_number((int)$n, 0);
        $items .= '<li><span class="text-break">' . h($s['label']) . '</span>: <code>' . h(implode(', ', $calls)) . '</code></li>';
    }
    $out .= '<h4 class="h6 mt-5 mb-1">' . h(lang('Scripts')) . '</h4>'
        . ($items !== ''
            ? '<div class="small fg-secondary mb-1">' . h(lang('These call Bootstrap 3\'s jQuery plugins or name its data attributes: under Bootstrap 5.3.8 a plugin call stops the script, and a data-* name finds nothing.')) . '</div><ul class="small mb-0 ps-12">' . $items . '</ul>'
            : '<p class="small mb-0">' . h(lang('No script of the site\'s files calls Bootstrap 3\'s plugins.')) . '</p>');

    $items = '';
    foreach ($r['left_out'] as $f) {
        $items .= '<li><span class="text-break">' . h($f['label']) . '</span>: ' . h($f['kind'] === 'js'
            ? lang('Bootstrap 3\'s script; the framework brings the Bootstrap 5.3.8 bundle.')
            : lang('Bootstrap 3\'s sheet; the framework brings Bootstrap 5.3.8.')) . '</li>';
    }
    $out .= '<h4 class="h6 mt-5 mb-1">' . h(lang('Files left out')) . '</h4><ul class="small mb-0 ps-12">' . $items . '</ul>';

    return $out . '<p class="small fg-secondary mt-5 mb-0">' . h(lang('Nothing has been written: the Legacy design, its pages and its files are as they were.')) . '</p>';
}

/**
 * The "Towards Bootstrap 5" card: each Legacy design with its Report, or
 * the line that it does not use Bootstrap 3; the asked design's report
 * under its line. '' when the site has no Legacy design.
 *
 * @param array      $designs  pg_lbs_designs()
 * @param int        $open     the design whose report was asked, 0 for none
 * @param array|null $report   pg_lbs_design_report() of it
 * @return string  HTML
 */
function pg_lbs_card_html($designs, $open, $report)
{
    if (!$designs) return '';
    $open = (int)$open;
    $body = '';
    foreach ($designs as $d) {
        $id = (int)$d['id'];
        $a = $d['assets'];
        $line = lang(array('string' => '{var:1} pages', 'vars' => array(pg_format_number($d['pages'], 0))));
        if ($a['bootstrap3']) {
            $line .= ' · ' . lang(array('string' => 'Bootstrap 3 ({var:1})', 'vars' => array($a['framework'])));
            $shown = ($open === $id && $report !== null);
            $action = $shown
                ? '<a class="btn btn-sm btn-outline theme-secondary rounded-pill px-4" href="legacy_to_designer.php#ltd-bootstrap">' . h(lang('Hide the report')) . '</a>'
                : '<a class="btn btn-sm btn-outline theme-secondary rounded-pill px-4" href="legacy_to_designer.php?bootstrap=' . $id . '#ltd-bootstrap-' . $id . '">'
                    . '<i class="bi bi-clipboard-data me-2" aria-hidden="true"></i>' . h(lang('Report')) . '</a>';
            $more = $shown ? '<div class="mt-3">' . pg_lbs_report_html($report) . '</div>' : '';
        } else {
            $action = '';
            $more = '<div class="small fg-secondary mt-1">' . h(($a['framework'] !== '' && $a['version'] > 0)
                ? lang(array('string' => 'Its {var:1} is Bootstrap {var:2}, not Bootstrap 3: there is no report and no trial design.', 'vars' => array($a['framework'], $a['version'])))
                : lang('This design does not use Bootstrap 3 (no bootstrap.css or bootstrap.min.css among its stylesheets): there is no report and no trial design.')) . '</div>';
        }
        $body .= '
        <div class="mb-7" id="ltd-bootstrap-' . $id . '">
            <div class="d-flex flex-wrap align-items-center gap-3">
                <div class="flex-grow-1"><h3 class="h6 mb-0">' . h($d['name']) . '</h3>
                    <div class="small fg-secondary">' . h($line) . '</div></div>
                ' . $action . '
            </div>' . $more . '
        </div>';
    }
    return '
    <div class="card my-7" id="ltd-bootstrap">
        <div class="card-header"><h2 class="h5 mb-0">' . h(lang('Towards Bootstrap 5')) . '</h2>
            <div class="small fg-secondary">' . h(lang('A Legacy design\'s report: what turning it towards Bootstrap 5.3.8 would convert in its pages and parts, what it keeps, what a designer finishes by hand and which files it leaves out. Nothing is written.')) . '</div></div>
        <div class="card-body">' . $body . '</div>
    </div>';
}
