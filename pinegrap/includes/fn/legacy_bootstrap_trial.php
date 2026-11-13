<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * The trial design of the "Towards Bootstrap 5" card (legacy_to_designer.php):
 * a Legacy design that loads Bootstrap 3 copied to a design on Bootstrap
 * 5.3.8 that no page uses yet, a page of it previewed in the trial beside the
 * Legacy design, and the trial deleted while no page uses it.
 *
 * A trial design is made of:
 * - its style row: framework bootstrap5, the Legacy design's name with
 *   " (Bootstrap 5)", its settings and fonts, and its Theme file named;
 * - a copy of every shared part the Legacy design's pages place (parts
 *   inside parts too) with its tree converted (pg_lbs_tree()) and its places
 *   pointing at the copies;
 * - the Legacy design's asset rows with Bootstrap 5.3.8 in place of the
 *   classic framework sheet, Bootstrap 3's script kept switched off, a copy
 *   of each site stylesheet whose selectors the converter renames (the Theme
 *   file too) and of each site script that calls Bootstrap 3's tooltip or
 *   popover or names its data attributes, and the compatibility sheet
 *   (assets/css/themes/legacy-bootstrap3.css).
 *
 * The Legacy design, its pages, its parts and its files are never written.
 * Which trial belongs to which Legacy design, and the parts and files made
 * for it, is kept in data/legacy_to_bootstrap/trials.json, so that "Delete
 * the trial design" takes back what was made and nothing else.
 *
 * A page is previewed without being written: design_preview.php hands
 * get_page_content() the trial's row and the page's converted tree through
 * pg_lbs_preview_override().
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_FUNCTIONS_DIR')) {
    exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/fn/legacy_bootstrap_report.php');

// The compatibility sheet every trial design loads, under the software
// directory.
const PG_LBS_COMPAT_SHEET = 'assets/css/themes/legacy-bootstrap3.css';

// ── Pure helpers ────────────────────────────────────────────────────────

/**
 * The name of a trial design: the Legacy design's with " (Bootstrap 5)",
 * cut so that the whole fits style.style_name (100 characters).
 *
 * Pure: no query.
 *
 * @param string $name
 * @return string
 */
function pg_lbs_trial_name($name)
{
    $suffix = ' (Bootstrap 5)';
    return rtrim(mb_substr(trim((string)$name), 0, 100 - mb_strlen($suffix))) . $suffix;
}

/**
 * The name of a trial's copy of a site file: "-bs5" before its extension.
 *
 * Pure: no query.
 *
 * @param string $name
 * @return string
 */
function pg_lbs_trial_file_name($name)
{
    $name = (string)$name;
    $dot = strrpos($name, '.');
    if ($dot === false || $dot === 0) return $name . '-bs5';
    return substr($name, 0, $dot) . '-bs5' . substr($name, $dot);
}

/**
 * A script as a trial design runs it. A Bootstrap 3 tooltip or popover call
 * on a jQuery selection of a quoted selector becomes Bootstrap 5.3.8's
 * getOrCreateInstance() on each element the selector finds, its options
 * object passed on; the data attributes Bootstrap reads
 * (pg_lbs_data_names()) get their data-bs-* names, as the converter gives
 * them to the markup. Under 5.3.8 the old call throws ("tooltip is not a
 * function") and every theme script after it stops. Other plugin calls are
 * left as they are and listed.
 *
 * Pure: no query, no file. A second pass changes nothing.
 *
 * @param string $js
 * @return array  'js', 'calls' (calls changed), 'names' (data names
 *                renamed), 'left' (pg_lbs_script_calls() plugin calls still
 *                there, name => count)
 */
/* @pg-site-output */
function pg_lbs_trial_script($js)
{
    $js = (string)$js;
    $calls = 0;
    $names = 0;
    $classes = array('tooltip' => 'Tooltip', 'popover' => 'Popover');
    $out = preg_replace_callback(
        '~(?:\$|\bjQuery)\(\s*(["\'])((?:(?!\1)[^\\\\\n]|\\\\.)*)\1\s*\)\s*\.\s*(tooltip|popover)\s*\(\s*(\{[^{}()]*\})?\s*\)~',
        function ($m) use (&$calls, $classes) {
            $calls++;
            $options = (isset($m[4]) && $m[4] !== '') ? ', ' . $m[4] : '';
            return 'document.querySelectorAll(' . $m[1] . $m[2] . $m[1] . ').forEach(function (el) { bootstrap.'
                . $classes[$m[3]] . '.getOrCreateInstance(el' . $options . '); })';
        },
        $js
    );
    if ($out === null) $out = $js;
    $alternatives = implode('|', array_map(function ($n) {
        return preg_quote($n, '~');
    }, pg_lbs_data_names()));
    $renamed = preg_replace_callback('~(?<![\w-])data-(' . $alternatives . ')(?![\w-])~', function ($m) use (&$names) {
        $names++;
        return 'data-bs-' . $m[1];
    }, $out);
    if ($renamed !== null) $out = $renamed;

    $left = array();
    foreach (pg_lbs_script_calls($out) as $name => $n) {
        if (substr($name, -2) === '()') $left[$name] = $n;
    }
    return array('js' => $out, 'calls' => $calls, 'names' => $names, 'left' => $left);
}

/**
 * The framework rows of a trial design's asset lists (the rows the editor
 * keeps, _ensureBootstrapSentinels()) and its compatibility sheet's row.
 * get_page_content() prints Bootstrap 5.3.8 and Bootstrap Icons where their
 * rows stand and the bundle where its row stands; without them the
 * framework would come after every sheet of the design and win over the
 * theme.
 *
 * Pure: no query.
 *
 * @return array  'css', 'icons', 'js', 'compat' (one row each)
 */
function pg_lbs_trial_framework_rows()
{
    $fw = pg_design_framework('bootstrap5');
    return array(
        'css'    => array('id' => 'lbs-bootstrap-css', 'name' => 'bootstrap.min.css', 'type' => 'external-css',
                          'content' => $fw['css'], 'enabled' => true, 'locked' => true, 'meta' => 'bootstrap-css'),
        'icons'  => array('id' => 'lbs-bootstrap-icons', 'name' => 'bootstrap-icons.min.css', 'type' => 'external-css',
                          'content' => pg_design_icons_url(), 'enabled' => true, 'locked' => true, 'meta' => 'bootstrap-icons'),
        'js'     => array('id' => 'lbs-bootstrap-js', 'name' => 'bootstrap.bundle.min.js', 'type' => 'external-js',
                          'content' => $fw['js'], 'enabled' => true, 'locked' => true, 'meta' => 'bootstrap-js'),
        'compat' => array('id' => 'lbs-compat', 'name' => basename(PG_LBS_COMPAT_SHEET), 'type' => 'external-css',
                          'content' => '{path}{software_directory}/' . PG_LBS_COMPAT_SHEET, 'enabled' => true, 'locked' => false),
    );
}

/**
 * A trial design's asset lists, from its Legacy design's.
 *
 * Stylesheets: the classic framework sheet rows (bootstrap.css,
 * bootstrap.min.css) give way to Bootstrap 5.3.8, Bootstrap Icons and the
 * compatibility sheet, at the first one's place, so the theme's sheets
 * still come after them. Scripts: Bootstrap 3's script rows (bootstrap.js,
 * bootstrap.min.js) are kept switched off, the 5.3.8 bundle at the first
 * one's place, so it has run before the theme's scripts; with none it comes
 * first. A site file that has a copy ($copies) is linked as the copy, a
 * managed file entry as designer_file/create writes it; inline rows are
 * converted in place. Everything else, a row limited to some pages
 * included, is kept as it is.
 *
 * Pure: no query, no file.
 *
 * @param array|string $css_rows     the Legacy design's style_custom_css
 * @param array|string $js_rows      its style_custom_js
 * @param array        $copies       site file name => its copy's name
 * @param string       $output_path  OUTPUT_PATH
 * @return array  'css', 'js' (lists of rows)
 */
function pg_lbs_trial_assets($css_rows, $js_rows, $copies, $output_path)
{
    $fw = pg_lbs_trial_framework_rows();
    $lists = array('css' => $css_rows, 'js' => $js_rows);
    foreach ($lists as $kind => $rows) {
        if (is_string($rows)) $rows = json_decode($rows, true);
        $lists[$kind] = is_array($rows) ? array_values(array_filter($rows, 'is_array')) : array();
    }
    $framework_meta = array('bootstrap-css', 'bootstrap-icons', 'bootstrap-js');
    $copy_row = function ($row) use ($copies, $output_path) {
        $file = pg_lbs_site_file($row, $output_path);
        if ($file === '' || !isset($copies[$file])) return $row;
        $name = (string)$copies[$file];
        $row['id'] = 'lbs-' . substr(md5($name), 0, 10);
        $row['name'] = $name;
        $row['content'] = $output_path . $name;
        $row['_file'] = true;
        return $row;
    };

    $css = array();
    $placed = false;
    foreach ($lists['css'] as $row) {
        $meta = (string)($row['meta'] ?? '');
        if (in_array($meta, $framework_meta, true)) continue;
        if ($meta === '' && in_array(pg_lbs_asset_name($row), array('bootstrap.css', 'bootstrap.min.css'), true)) {
            if (!$placed) array_push($css, $fw['css'], $fw['icons'], $fw['compat']);
            $placed = true;
            continue;
        }
        if ((string)($row['type'] ?? '') === 'css') {
            $converted = pg_lbs_css((string)($row['content'] ?? ''));
            $row['content'] = $converted['css'];
        } else {
            $row = $copy_row($row);
        }
        $css[] = $row;
    }
    if (!$placed) array_unshift($css, $fw['css'], $fw['icons'], $fw['compat']);

    $js = array();
    $placed = false;
    foreach ($lists['js'] as $row) {
        $meta = (string)($row['meta'] ?? '');
        if (in_array($meta, $framework_meta, true)) continue;
        if ($meta === '' && in_array(pg_lbs_asset_name($row), array('bootstrap.js', 'bootstrap.min.js'), true)) {
            if (!$placed) $js[] = $fw['js'];
            $placed = true;
            $row['enabled'] = false;
            $js[] = $row;
            continue;
        }
        if ((string)($row['type'] ?? '') === 'js') {
            $converted = pg_lbs_trial_script((string)($row['content'] ?? ''));
            $row['content'] = $converted['js'];
        } else {
            $row = $copy_row($row);
        }
        $js[] = $row;
    }
    if (!$placed) array_unshift($js, $fw['js']);

    return array('css' => $css, 'js' => $js);
}

/**
 * A tree with each shared part it places pointing at the part's copy.
 * A part with no copy keeps its place.
 *
 * Pure: no query.
 *
 * @param array $tree
 * @param array $map  shared part id => its copy's id
 * @return array
 */
function pg_lbs_trial_refs($tree, $map)
{
    if (!is_array($tree)) return $tree;
    if (($tree['type'] ?? '') === 'shared_ref' && isset($tree['props']['sharedId'])) {
        $sid = (int)$tree['props']['sharedId'];
        if (isset($map[$sid])) {
            $tree['props']['sharedId'] = is_string($tree['props']['sharedId']) ? (string)$map[$sid] : (int)$map[$sid];
        }
    }
    if (!empty($tree['children']) && is_array($tree['children'])) {
        foreach ($tree['children'] as $i => $child) {
            $tree['children'][$i] = pg_lbs_trial_refs($child, $map);
        }
    }
    return $tree;
}

/**
 * The category a trial's copy of a shared part carries:
 * "legacy_bootstrap:<trial id>:<part id>". Never "classic_style:…": Back to
 * classic reads that one as the classic style the part came from.
 *
 * Pure: no query.
 */
function pg_lbs_trial_part_category($trial_id, $part_id)
{
    return 'legacy_bootstrap:' . (int)$trial_id . ':' . (int)$part_id;
}

// ── The record of the trials ────────────────────────────────────────────

function pg_lbs_trials_path()
{
    return PG_FUNCTIONS_DIR . '/data/legacy_to_bootstrap/trials.json';
}

/**
 * Every trial made: Legacy design id => 'trial' (its style id), 'name',
 * 'map' (shared part id => its copy's id), 'files' (ids of the files made),
 * 'made' (time), 'user'.
 *
 * @return array
 */
function pg_lbs_trials_read()
{
    $path = pg_lbs_trials_path();
    if (!is_file($path)) return array();
    $all = json_decode((string)@file_get_contents($path), true);
    return is_array($all) ? $all : array();
}

function pg_lbs_trials_write($trials)
{
    $path = pg_lbs_trials_path();
    if (!is_dir(dirname($path))) @mkdir(dirname($path), 0755, true);
    // Written beside it and renamed: a killed request never leaves half a file.
    if (@file_put_contents($path . '.tmp', json_encode((object)$trials)) === false) return false;
    return @rename($path . '.tmp', $path);
}

/**
 * The trial design of a Legacy design, null when it has none or its row is
 * gone (deleted, or in the recycle bin, from Visual Designs).
 *
 * @param int        $legacy_id
 * @param array|null $trials     pg_lbs_trials_read() when the caller has it
 * @return array|null  the record, its 'name' read again from the row
 */
function pg_lbs_trial_of($legacy_id, $trials = null)
{
    $trials = ($trials === null) ? pg_lbs_trials_read() : (array)$trials;
    $record = $trials[(string)(int)$legacy_id] ?? null;
    if (!is_array($record) || (int)($record['trial'] ?? 0) <= 0) return null;
    $row = db_item("SELECT style_name, style_framework FROM style WHERE style_id = '" . (int)$record['trial'] . "' LIMIT 1");
    if (!$row || !pg_design_framework_has((string)$row['style_framework'], 'bootstrap')) return null;
    $record['trial'] = (int)$record['trial'];
    $record['name'] = (string)$row['style_name'];
    $record['map'] = is_array($record['map'] ?? null) ? $record['map'] : array();
    $record['files'] = array_map('intval', is_array($record['files'] ?? null) ? $record['files'] : array());
    return $record;
}

/**
 * How many pages (the recycle bin's too) and folders use a design, as their
 * design or their mobile design.
 */
function pg_lbs_trial_users($style_id)
{
    $id = (int)$style_id;
    return (int)db_value("SELECT COUNT(*) FROM page WHERE page_style = '$id' OR mobile_style_id = '$id'")
        + (int)db_value("SELECT COUNT(*) FROM folder WHERE folder_style = '$id' OR mobile_style_id = '$id'");
}

// ── Making and deleting a trial ─────────────────────────────────────────

/**
 * The shared parts a Legacy design's pages (the recycle bin's left out) and
 * its own tree place, parts inside parts too, in the order they were found.
 *
 * @param int    $legacy_id
 * @param string $style_tree  the design's style_tree_json
 * @return array  part ids
 */
function pg_lbs_trial_part_ids($legacy_id, $style_tree)
{
    $found = array();
    $tree = json_decode((string)$style_tree, true);
    if (is_array($tree)) {
        foreach (pg_ltd_shared_ids($tree) as $sid) $found[(int)$sid] = true;
    }
    $pages = db_values("SELECT page_id FROM page WHERE page_style = '" . (int)$legacy_id . "'" . pg_designer_not_binned_sql('page_folder'));
    foreach (array_chunk(array_map('intval', is_array($pages) ? $pages : array()), 25) as $chunk) {
        $rows = db_items("SELECT " . pg_page_tree_sql_expr() . " AS tree_json FROM page LEFT JOIN style ON page.page_style = style.style_id
                          WHERE page.page_id IN (" . implode(',', $chunk) . ")");
        foreach ((is_array($rows) ? $rows : array()) as $r) {
            $tree = json_decode((string)$r['tree_json'], true);
            if (is_array($tree)) {
                foreach (pg_ltd_shared_ids($tree) as $sid) $found[(int)$sid] = true;
            }
        }
    }
    $seen = array();
    while ($want = array_diff(array_keys($found), array_keys($seen))) {
        foreach (array_chunk(array_map('intval', $want), 25) as $chunk) {
            foreach ($chunk as $sid) $seen[$sid] = true;
            $rows = db_items("SELECT tree_json FROM shared_components WHERE id IN (" . implode(',', $chunk) . ")");
            foreach ((is_array($rows) ? $rows : array()) as $r) {
                $tree = json_decode((string)$r['tree_json'], true);
                if (is_array($tree)) {
                    foreach (pg_ltd_shared_ids($tree) as $sid) $found[(int)$sid] = true;
                }
            }
        }
    }
    unset($found[0]);
    return array_keys($found);
}

/**
 * A files row copied under a new name with new text: the same folder,
 * design and theme flags, never activated. Returns the new row's id and
 * name, or null when the file could not be written.
 *
 * @param array  $source  files row: id, name, folder, design, theme
 * @param string $text
 * @param string $description
 * @param int    $user_id
 * @return array|null  'id', 'name'
 */
function pg_lbs_trial_copy_file($source, $text, $description, $user_id)
{
    if (!defined('FILE_DIRECTORY_PATH')) return null;
    $name = get_unique_name(array('name' => pg_lbs_trial_file_name((string)$source['name']), 'type' => 'file'));
    if ($name === '' || strpbrk($name, '/\\') !== false || file_exists(FILE_DIRECTORY_PATH . '/' . $name)) return null;
    if (!pg_file_write_whole(FILE_DIRECTORY_PATH . '/' . $name, (string)$text)) return null;
    $ext = strtolower((string)pathinfo($name, PATHINFO_EXTENSION));
    db("INSERT INTO files (name, folder, description, type, size, user, design, theme, timestamp)
        VALUES ('" . e($name) . "', '" . (int)$source['folder'] . "', '" . e(mb_substr((string)$description, 0, 255)) . "', '" . e($ext) . "',
                '" . (int)strlen((string)$text) . "', '" . (int)$user_id . "', '" . ((int)$source['design'] ? 1 : 0) . "', '" . ((int)$source['theme'] ? 1 : 0) . "', UNIX_TIMESTAMP())");
    $id = (int)mysqli_insert_id(db::$con);
    if ($id <= 0) {
        @unlink(FILE_DIRECTORY_PATH . '/' . $name);
        return null;
    }
    return array('id' => $id, 'name' => $name);
}

/**
 * Takes back what a trial made: its style row, the copies of its parts
 * that no other tree places (passes until one deletes nothing, as a part
 * inside another copy is free once that one is gone) and the files made for
 * it that no other design links.
 *
 * @param int   $trial_id
 * @param array $map       shared part id => its copy's id
 * @param array $file_ids
 * @return array  'parts', 'files' (how many were deleted)
 */
function pg_lbs_trial_take_back($trial_id, $map, $file_ids)
{
    $trial_id = (int)$trial_id;
    $out = array('parts' => 0, 'files' => 0);
    if ($trial_id > 0) {
        db("DELETE FROM style WHERE style_id = '$trial_id'");
        db("DELETE FROM system_style_cells WHERE style_id = '$trial_id'");
        db("DELETE FROM preview_styles WHERE style_id = '$trial_id'");
        require_once(PG_FUNCTIONS_DIR . '/includes/fn/prototype_links.php');
        pg_proto_links_revoke_design($trial_id, 0);
    }

    // Only the rows this trial made: its category names it.
    $copies = array();
    $ids = array_filter(array_map('intval', array_values((array)$map)));
    foreach (array_chunk($ids, 100) as $chunk) {
        $rows = db_items("SELECT id, category FROM shared_components WHERE id IN (" . implode(',', $chunk) . ")");
        foreach ((is_array($rows) ? $rows : array()) as $r) {
            if (strpos((string)$r['category'], 'legacy_bootstrap:' . $trial_id . ':') === 0) $copies[(int)$r['id']] = true;
        }
    }
    for ($pass = 0; $copies && $pass < 8; $pass++) {
        $used = _pg_tpl_used_rows();
        $before = $out['parts'];
        foreach (array_keys($copies) as $sid) {
            if (isset($used[$sid])) continue;
            db("DELETE FROM shared_components WHERE id = '$sid' LIMIT 1");
            unset($copies[$sid]);
            $out['parts']++;
        }
        if ($out['parts'] === $before) break;
    }

    require_once(PG_FUNCTIONS_DIR . '/includes/explorer/recycle_bin.php');
    foreach (array_map('intval', (array)$file_ids) as $fid) {
        $file = ($fid > 0) ? db_item("SELECT id, name FROM files WHERE id = '$fid' LIMIT 1") : null;
        if (!$file) continue;
        $like = e(str_replace(array('%', '_'), array('\\%', '\\_'), (string)$file['name']));
        $linked = (int)db_value("SELECT COUNT(*) FROM style WHERE theme_id = '$fid'
                                 OR style_custom_css LIKE '%" . $like . "%' OR style_custom_js LIKE '%" . $like . "%'");
        if ($linked > 0) continue;
        pg_delete_file_record($file);
        $out['files']++;
    }
    return $out;
}

/**
 * Makes a Legacy design's trial design (see the file's header). Refused for
 * a design that is not a Legacy design on Bootstrap 3 (the card's own test,
 * pg_lbs_design_assets()) and for one that has a trial. Whatever fails half
 * way is taken back.
 *
 * @param int   $legacy_id
 * @param array $user  the operator: id
 * @return array  'ok', 'error', 'trial' (style id), 'name', 'parts' (copies
 *                made), 'files' (names of the files made), 'notes' (site
 *                files that could not be read, script calls left as written)
 */
function pg_lbs_trial_make($legacy_id, $user)
{
    $legacy_id = (int)$legacy_id;
    $user_id = (int)($user['id'] ?? 0);
    $out = array('ok' => false, 'error' => '', 'trial' => 0, 'name' => '', 'parts' => 0, 'files' => array(), 'notes' => array());

    $design = null;
    foreach (pg_lbs_designs() as $d) {
        if ($d['id'] === $legacy_id) $design = $d;
    }
    if ($design === null) {
        $out['error'] = lang('The design could not be found.');
        return $out;
    }
    if (!$design['assets']['bootstrap3']) {
        $out['error'] = lang('This design does not use Bootstrap 3: there is no trial design.');
        return $out;
    }
    if (pg_lbs_trial_of($legacy_id) !== null) {
        $out['error'] = lang('This design has a trial design already.');
        return $out;
    }
    $style = db_item("SELECT * FROM style WHERE style_id = '$legacy_id' LIMIT 1");
    if (!$style) {
        $out['error'] = lang('The design could not be found.');
        return $out;
    }
    $output_path = (string)OUTPUT_PATH;
    $made_files = array();
    $map = array();
    $trial_id = 0;
    $fail = function ($text) use (&$out, &$trial_id, &$map, &$made_files) {
        pg_lbs_trial_take_back($trial_id, $map, $made_files);
        $out['error'] = $text;
        $out['ok'] = false;
        return $out;
    };

    // The Theme file: the one the design names, else the activated theme it
    // loads. A row that links it loads it where it stands; the trial then
    // names none, so it is not loaded twice.
    $theme_name = '';
    if ((int)$style['theme_id'] > 0) {
        $theme_name = (string)db_value("SELECT name FROM files WHERE id = '" . (int)$style['theme_id'] . "' LIMIT 1");
    } else {
        $classic = pg_legacy_design_classic_theme($legacy_id, 'legacy', 'desktop', (string)$style['style_custom_css'], null);
        if ($classic) $theme_name = (string)$classic['name'];
    }

    // The site files the trial may need a copy of.
    $sources = array();
    foreach (array('sheets' => 'css', 'scripts' => 'js') as $list => $kind) {
        foreach ($design['assets'][$list] as $row) {
            if (!in_array((string)$row['type'], array('external-css', 'external-js'), true)) continue;
            $file = pg_lbs_site_file($row, $output_path);
            if ($file !== '') $sources[$file] = array('kind' => $kind, 'theme' => false);
        }
    }
    $theme_linked = false;
    foreach (array_keys($sources) as $file) {
        if ($theme_name !== '' && strcasecmp($file, $theme_name) === 0) $theme_linked = true;
    }
    if ($theme_name !== '' && !$theme_linked) $sources[$theme_name] = array('kind' => 'css', 'theme' => true);

    $copies = array();
    $trial_theme_id = 0;
    foreach ($sources as $file => $s) {
        $row = db_item("SELECT id, name, folder, design, theme FROM files WHERE name = '" . e($file) . "' LIMIT 1");
        $text = $row ? pg_lbs_read_site_file((string)$row['name']) : null;
        if ($s['theme']) $trial_theme_id = $row ? (int)$row['id'] : 0;
        if ($text === null) {
            $out['notes'][] = lang(array('string' => '{var:1} could not be read: the trial design loads it as it is.', 'vars' => array($file)));
            continue;
        }
        if ($s['kind'] === 'css') {
            $converted = pg_lbs_css($text);
            $new = $converted['css'];
        } else {
            $converted = pg_lbs_trial_script($text);
            $new = $converted['js'];
            foreach ($converted['left'] as $call => $n) {
                $out['notes'][] = lang(array('string' => '{var:1} still calls {var:2} ({var:3}): a designer changes it in the trial\'s copy.', 'vars' => array($file, $call, $n)));
            }
        }
        if ($new === $text) continue;
        $copy = pg_lbs_trial_copy_file($row, $new, lang(array('string' => 'Bootstrap 5 copy of {var:1} for the trial design of {var:2}', 'vars' => array($row['name'], $design['name']))), $user_id);
        if ($copy === null) return $fail(lang(array('string' => '{var:1} could not be copied: no trial design was made.', 'vars' => array($file))));
        $made_files[] = $copy['id'];
        $out['files'][] = $copy['name'];
        $copies[$file] = $copy['name'];
        if ($s['theme']) $trial_theme_id = $copy['id'];
    }

    $assets = pg_lbs_trial_assets((string)$style['style_custom_css'], (string)$style['style_custom_js'], $copies, $output_path);
    $data = array(
        'style_id'                => 0,
        'user_id'                 => $user_id,
        'name'                    => get_unique_name(array('name' => pg_lbs_trial_name((string)$style['style_name']), 'type' => 'style')),
        'framework'               => 'bootstrap5',
        'theme_id'                => $theme_linked ? 0 : $trial_theme_id,
        'collection'              => (string)$style['collection'],
        'additional_body_classes' => (string)$style['additional_body_classes'],
        'style_head'              => (string)$style['style_head'],
        'style_empty_cell_width_percentage' => (string)($style['style_empty_cell_width_percentage'] ?? ''),
        'social_networking_position' => (string)$style['social_networking_position'],
        'style_custom_css'        => json_encode($assets['css'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        'style_custom_js'         => json_encode($assets['js'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        'style_custom_fonts'      => (string)$style['style_custom_fonts'],
    );
    if (array_key_exists('style_meta_tags', $style)) $data['style_meta_tags'] = (string)$style['style_meta_tags'];
    $trial_id = (int)save_system_style($data);
    if ($trial_id <= 0) return $fail(lang('The trial design could not be saved.'));

    // The parts, each copied with its tree converted, then their places
    // pointed at the copies once every copy has its id.
    $now = time();
    $trees = array();
    foreach (array_chunk(pg_lbs_trial_part_ids($legacy_id, (string)$style['style_tree_json']), 25) as $chunk) {
        $rows = db_items("SELECT id, name, description, tree_json, system_region_config FROM shared_components WHERE id IN (" . implode(',', $chunk) . ")");
        foreach ((is_array($rows) ? $rows : array()) as $r) {
            $tree = json_decode((string)$r['tree_json'], true);
            $converted = is_array($tree) ? pg_lbs_tree($tree) : null;
            $json = $converted ? pg_designer_tree_encode($converted['tree']) : (string)$r['tree_json'];
            $name = _sc_unique_name(mb_substr(pg_lbs_trial_name((string)$r['name']), 0, 255));
            $config = (string)$r['system_region_config'];
            db("INSERT INTO shared_components (name, description, tree_json, system_region_config, category, created_by, created_at, updated_at)
                VALUES ('" . e($name) . "', '" . e((string)$r['description']) . "', '" . e($json) . "', " . ($config === '' ? 'NULL' : "'" . e($config) . "'") . ",
                        '" . e(pg_lbs_trial_part_category($trial_id, (int)$r['id'])) . "', '$user_id', '$now', '$now')");
            $copy_id = (int)mysqli_insert_id(db::$con);
            if ($copy_id <= 0) return $fail(lang(array('string' => 'The shared part {var:1} could not be copied: no trial design was made.', 'vars' => array($r['name']))));
            $map[(int)$r['id']] = $copy_id;
            if ($converted && pg_ltd_shared_ids($converted['tree'])) $trees[$copy_id] = $converted['tree'];
        }
    }
    foreach ($trees as $copy_id => $tree) {
        db("UPDATE shared_components SET tree_json = '" . e(pg_designer_tree_encode(pg_lbs_trial_refs($tree, $map))) . "' WHERE id = '" . (int)$copy_id . "' LIMIT 1");
    }

    // A design moved alone keeps a tree of its own, the fallback of a page
    // that has none.
    $style_tree = json_decode((string)$style['style_tree_json'], true);
    if (is_array($style_tree)) {
        $converted = pg_lbs_tree($style_tree);
        $data['style_id'] = $trial_id;
        $data['style_tree_json'] = pg_designer_tree_encode(pg_lbs_trial_refs($converted['tree'], $map));
        save_system_style($data);
    }

    $trials = pg_lbs_trials_read();
    $trials[(string)$legacy_id] = array('trial' => $trial_id, 'map' => $map, 'files' => $made_files, 'made' => $now, 'user' => $user_id);
    if (!pg_lbs_trials_write($trials)) return $fail(lang('The trial design could not be recorded: no trial design was made.'));

    log_activity(lang(array('string' => 'the trial design ({var:1}) of the design ({var:2}) was made', 'vars' => array($data['name'], $design['name']))), (string)($_SESSION['sessionusername'] ?? ''));
    $out['ok'] = true;
    $out['trial'] = $trial_id;
    $out['name'] = $data['name'];
    $out['parts'] = count($map);
    return $out;
}

/**
 * Deletes a Legacy design's trial design, its parts' copies and the files
 * made for it; refused while a page or a folder uses it or the editor has
 * it open.
 *
 * @param int   $legacy_id
 * @param array $user
 * @return array  'ok', 'error', 'name', 'parts', 'files'
 */
function pg_lbs_trial_delete($legacy_id, $user)
{
    $out = array('ok' => false, 'error' => '', 'name' => '', 'parts' => 0, 'files' => 0);
    $trials = pg_lbs_trials_read();
    $record = pg_lbs_trial_of($legacy_id, $trials);
    if ($record === null) {
        $out['error'] = lang('This design has no trial design.');
        return $out;
    }
    $out['name'] = $record['name'];
    if (pg_lbs_trial_users($record['trial']) > 0) {
        $out['error'] = lang('Pages or folders use the trial design: it is kept.');
        return $out;
    }
    require_once(PG_FUNCTIONS_DIR . '/includes/designer_collab.php');
    if (pg_collab_peers('', $record['trial'])) {
        $out['error'] = lang('The trial design is open in the Page Design Studio: delete it once it is closed.');
        return $out;
    }
    $taken = pg_lbs_trial_take_back($record['trial'], $record['map'], $record['files']);
    unset($trials[(string)(int)$legacy_id]);
    pg_lbs_trials_write($trials);
    log_activity(lang(array('string' => 'the trial design ({var:1}) was deleted', 'vars' => array($record['name']))), (string)($_SESSION['sessionusername'] ?? ''));
    $out['ok'] = true;
    $out['parts'] = $taken['parts'];
    $out['files'] = $taken['files'];
    return $out;
}

// ── The preview ─────────────────────────────────────────────────────────

/**
 * The page this request draws in a trial design: design_preview.php sets it
 * before get_page_content() runs, which takes the trial's row and the
 * converted body for that page only. With no argument it is read.
 *
 * @param array|null $set  'page_id', 'style_id', 'code'; null clears it
 * @return array|null
 */
function pg_lbs_preview_override($set = null)
{
    static $override = null;
    if (func_num_args() > 0) $override = is_array($set) ? $set : null;
    return $override;
}

/**
 * A Legacy page's body as its trial design draws it: the page's tree
 * converted, its parts' places pointing at the trial's copies, written
 * with the trial's body classes. '' when the page has no tree.
 *
 * @param int   $page_id
 * @param array $record  pg_lbs_trial_of()
 * @return string
 */
function pg_lbs_trial_page_code($page_id, $record)
{
    $row = db_item("SELECT page.page_name, " . pg_page_tree_sql_expr() . " AS tree_json
                    FROM page LEFT JOIN style ON page.page_style = style.style_id
                    WHERE page.page_id = '" . (int)$page_id . "' LIMIT 1");
    $tree = $row ? json_decode((string)$row['tree_json'], true) : null;
    if (!is_array($tree)) return '';
    $converted = pg_lbs_tree($tree);
    $abc = (string)db_value("SELECT additional_body_classes FROM style WHERE style_id = '" . (int)$record['trial'] . "' LIMIT 1");
    return generate_style_code_from_tree(pg_lbs_trial_refs($converted['tree'], $record['map']), (string)$row['page_name'], $abc);
}

/**
 * The pages of a Legacy design a preview can show (the recycle bin's left
 * out), by name, the home page first.
 *
 * @return array  list of: id, name
 */
function pg_lbs_trial_pages($legacy_id)
{
    $rows = db_items("SELECT page_id, page_name, page_home FROM page
                      WHERE page_style = '" . (int)$legacy_id . "'" . pg_designer_not_binned_sql('page_folder') . "
                      ORDER BY (page_home = 'yes') DESC, page_name ASC, page_id ASC");
    $out = array();
    foreach ((is_array($rows) ? $rows : array()) as $r) {
        $out[] = array('id' => (int)$r['page_id'], 'name' => (string)$r['page_name']);
    }
    return $out;
}

// ── The card ────────────────────────────────────────────────────────────

/**
 * A Legacy design's trial on the "Towards Bootstrap 5" card: beside its
 * Report, "Make a trial design" (a form; switched off while a move runs);
 * under its line, once there is a trial, the trial's name with Preview and
 * "Delete the trial design" (a second button confirms; none while a page or
 * a folder uses it), and the preview asked for.
 *
 * @param int        $design_id
 * @param array|null $trial     pg_lbs_trial_of()
 * @param array      $info      'users' (pg_lbs_trial_users()), 'running'
 *                              (a move runs), 'preview' (null, or 'pages'
 *                              (pg_lbs_trial_pages()) and 'page' (the page
 *                              shown, 0 for none))
 * @return array  'actions', 'below' (HTML)
 */
function pg_lbs_trial_card_html($design_id, $trial, $info)
{
    $id = (int)$design_id;
    $running = !empty($info['running']);
    $button = 'btn btn-sm btn-outline theme-secondary rounded-pill px-4';
    if ($trial === null) {
        return array('below' => '', 'actions' => '<form method="post" action="legacy_to_designer.php" class="d-inline">' . get_token_field()
            . '<input type="hidden" name="bootstrap_trial" value="make"><input type="hidden" name="design" value="' . $id . '">'
            . '<button type="submit" class="btn btn-sm btn-solid theme-primary rounded-pill px-4"' . ($running ? ' disabled' : '') . '>'
            . '<i class="bi bi-bootstrap me-2" aria-hidden="true"></i>' . h(lang('Make a trial design')) . '</button></form>');
    }

    $users = (int)($info['users'] ?? 0);
    $preview = $info['preview'] ?? null;
    $actions = $preview !== null
        ? '<a class="' . $button . '" href="legacy_to_designer.php#ltd-bootstrap-' . $id . '">' . h(lang('Hide the preview')) . '</a>'
        : '<a class="' . $button . '" href="legacy_to_designer.php?preview=' . $id . '#ltd-trial-' . $id . '"><i class="bi bi-layout-split me-2" aria-hidden="true"></i>' . h(lang('Preview')) . '</a>';
    if ($users > 0) {
        $state = lang(array('string' => '{var:1} pages or folders use it: it cannot be deleted.', 'vars' => array(pg_format_number($users, 0))));
    } else {
        $state = lang('No page uses it yet: the Legacy design\'s pages are as they were.');
        $actions .= '<details class="d-inline-block"><summary class="' . $button . '">' . h(lang('Delete the trial design')) . '</summary>'
            . '<div class="small mt-2">' . h(lang('The trial design, its copies of the shared parts and the files made for it are deleted. The Legacy design keeps everything.'))
            . '<form method="post" action="legacy_to_designer.php" class="mt-2">' . get_token_field()
            . '<input type="hidden" name="bootstrap_trial" value="delete"><input type="hidden" name="design" value="' . $id . '">'
            . '<button type="submit" class="btn btn-sm btn-solid theme-danger rounded-pill px-4"' . ($running ? ' disabled' : '') . '>' . h(lang('Delete')) . '</button></form></div></details>';
    }

    $below = '<div class="mt-3" id="ltd-trial-' . $id . '"><div class="d-flex flex-wrap align-items-center gap-3">'
        . '<div class="flex-grow-1 small"><i class="bi bi-bootstrap me-2" aria-hidden="true"></i>'
        . h(lang('Trial design')) . ': <a href="edit_system_style.php?id=' . (int)$trial['trial'] . '">' . h($trial['name']) . '</a>'
        . '<div class="fg-secondary">' . h($state) . '</div></div>' . $actions . '</div>';
    if ($preview !== null) $below .= pg_lbs_trial_preview_html($id, $trial, $preview['pages'], (int)$preview['page']);
    return array('actions' => '', 'below' => $below . '</div>');
}

/**
 * One page of a Legacy design beside the same page in its trial design,
 * wide and on a phone, with a page picker. Live pictures run no script.
 *
 * @param int   $design_id
 * @param array $trial    pg_lbs_trial_of()
 * @param array $pages    pg_lbs_trial_pages()
 * @param int   $page_id  the page shown, 0 for the first
 * @return string  HTML
 */
function pg_lbs_trial_preview_html($design_id, $trial, $pages, $page_id)
{
    $id = (int)$design_id;
    if (!$pages) return '<p class="small fg-secondary mt-3 mb-0">' . h(lang('The design has no page to preview.')) . '</p>';
    $shown = $pages[0];
    foreach ($pages as $p) {
        if ($p['id'] === (int)$page_id) $shown = $p;
    }
    $options = '';
    foreach ($pages as $p) {
        $options .= '<option value="' . (int)$p['id'] . '"' . ($p['id'] === $shown['id'] ? ' selected' : '') . '>' . h($p['name']) . '</option>';
    }
    $base = (defined('PATH') && defined('SOFTWARE_DIRECTORY')) ? PATH . SOFTWARE_DIRECTORY . '/' : '';
    $legacy_src = $base . 'design_preview.php?page_id=' . (int)$shown['id'];
    $trial_src = $legacy_src . '&bootstrap5=' . (int)$trial['trial'];
    $picture = function ($src, $label, $width, $height) {
        return pg_design_live_picture($src, array('label' => $label, 'width' => $width, 'height' => $height,
            'fallback' => '<span class="small fg-secondary">' . h(lang('This page cannot be previewed.')) . '</span>'));
    };
    $legacy = lang(array('string' => '{var:1} on the Legacy design', 'vars' => array($shown['name'])));
    $trial_label = lang(array('string' => '{var:1} on the trial design', 'vars' => array($shown['name'])));

    return '
        <form method="get" action="legacy_to_designer.php#ltd-trial-' . $id . '" class="d-flex flex-wrap align-items-end gap-3 mt-5 mb-5">
            <input type="hidden" name="preview" value="' . $id . '">
            <div><label class="form-label small mb-1" for="ltd-trial-page-' . $id . '">' . h(lang('Page')) . '</label>
                <select class="form-control form-control-sm" id="ltd-trial-page-' . $id . '" name="page">' . $options . '</select></div>
            <button type="submit" class="btn btn-sm btn-outline theme-secondary rounded-pill px-4">' . h(lang('Show')) . '</button>
        </form>
        <div class="row g-5 justify-content-center">
            <div class="col-12 lg:col-6"><h4 class="h6 mb-2">' . h(lang('Legacy design')) . '</h4>' . $picture($legacy_src, $legacy, 1280, 800) . '</div>
            <div class="col-12 lg:col-6"><h4 class="h6 mb-2">' . h(lang('Trial design (Bootstrap 5.3.8)')) . '</h4>' . $picture($trial_src, $trial_label, 1280, 800) . '</div>
            <div class="col-6 lg:col-3">' . $picture($legacy_src, $legacy, 390, 844) . '</div>
            <div class="col-6 lg:col-3">' . $picture($trial_src, $trial_label, 390, 844) . '</div>
        </div>
        <p class="small fg-secondary mt-3 mb-0">' . h(lang('Drawn at 1280 and 390 pixels wide as a first-time visitor sees the page, on both sides without its scripts (a slider shows its slides one under another) and without the icon fonts the site serves from its own files.')) . '</p>';
}
