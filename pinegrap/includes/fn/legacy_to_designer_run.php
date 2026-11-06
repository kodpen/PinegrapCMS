<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * The bulk move of classic styles to the visual designer as a resumable
 * pass: the chosen styles, one after another, through the existing
 * converters in the order of the dry run (legacy_to_designer_plan.php).
 *
 *   1. regions       the style's common regions made shared components
 *   2. design        the frame component and the trees of the style's own
 *                    pages, saved as the editor's Save does (a held page is
 *                    skipped and listed), the folder pages, the style made a
 *                    visual design
 *   3. page_regions  the page regions of pages that already had a tree of
 *                    their own moved into it
 *   4. frame         the frame's designer regions made components and its
 *                    menu, menu sequence, ad and tag cloud regions widgets
 *
 * The cursor is data/temp/legacy_to_designer.json, written after every
 * step; a lost one ends the run (every step skips what an earlier run
 * wrote, so starting again is safe). The report screen's short requests and
 * the general job's tick (pg_ltd_job_tick()) work the same pass, one at a
 * time under GET_LOCK('pg_ltd', 0). A finished run is kept as one JSON
 * file in data/legacy_to_designer/.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2026 Erdal Güral. All rights reserved.
 * @license     Proprietary, see license-editors.txt
 */

if (!defined('PG_FUNCTIONS_DIR')) {
    exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/fn/legacy_to_designer_plan.php');

// Report lines kept per run; the rest are counted.
define('PG_LTD_REPORT_LINES', 2000);

/**
 * The steps of one style, in order.
 *
 * @return array
 */
function pg_ltd_steps()
{
    return array('regions', 'design', 'page_regions', 'frame');
}

/**
 * A new run's cursor.
 *
 * Pure: no query.
 *
 * @param array $style_ids  chosen styles, in the order to work them
 * @param array $user       the designer starting it (id, username)
 * @param int   $now
 * @return array
 */
function pg_ltd_state_new($style_ids, $user, $now)
{
    $ids = array();
    foreach ((array)$style_ids as $id) {
        if ((int)$id > 0 && !in_array((int)$id, $ids, true)) $ids[] = (int)$id;
    }
    return array(
        'id'       => gmdate('Ymd-His', (int)$now) . '-' . substr(md5(implode(',', $ids) . '|' . (int)$now), 0, 6),
        'started'  => (int)$now,
        'user_id'  => (int)($user['id'] ?? 0),
        'username' => (string)($user['username'] ?? ''),
        'styles'   => $ids,
        'index'    => 0,
        'step'     => 'regions',
        'names'    => array(),
        'counts'   => array('styles' => 0, 'pages' => 0, 'components' => 0, 'widgets' => 0, 'skipped' => 0, 'failed' => 0),
        'lines'    => array(),
        'more'     => 0,
    );
}

/**
 * The cursor moved past the step just done: the next step of the style,
 * else the first step of the next style. 'index' equal to the number of
 * styles means the run is over.
 *
 * Pure: no query.
 */
function pg_ltd_state_advance($state, $skip_style = false)
{
    $steps = pg_ltd_steps();
    $at = array_search((string)$state['step'], $steps, true);
    if ($skip_style || $at === false || $at + 1 >= count($steps)) {
        $state['index'] = (int)$state['index'] + 1;
        $state['step'] = $steps[0];
    } else {
        $state['step'] = $steps[$at + 1];
    }
    return $state;
}

/**
 * Whether every chosen style has been worked.
 *
 * Pure: no query.
 */
function pg_ltd_state_done($state)
{
    return (int)$state['index'] >= count((array)$state['styles']);
}

/**
 * How far a run is: styles finished, styles chosen, the step under way.
 *
 * Pure: no query.
 *
 * @return array  'done', 'total', 'step', 'style' (the id being worked, 0 at the end)
 */
function pg_ltd_state_progress($state)
{
    $total = count((array)$state['styles']);
    $done = min($total, (int)$state['index']);
    return array('done' => $done, 'total' => $total, 'step' => (string)$state['step'],
                 'style' => ($done < $total) ? (int)$state['styles'][$done] : 0);
}

/**
 * A line of the run's report, with the count it adds to. Lines past
 * PG_LTD_REPORT_LINES are counted only.
 *
 * Pure: no query.
 *
 * @param array  $state
 * @param string $kind   made, skipped or failed
 * @param string $text
 * @param string $count  a counts key to add one to, '' for none
 */
function pg_ltd_state_line($state, $kind, $text, $count = '')
{
    if ($count !== '') $state['counts'][$count] = (int)($state['counts'][$count] ?? 0) + 1;
    if ($kind === 'skipped' || $kind === 'failed') $state['counts'][$kind] = (int)($state['counts'][$kind] ?? 0) + 1;
    $style = pg_ltd_state_progress($state)['style'];
    if (count($state['lines']) < PG_LTD_REPORT_LINES) {
        $state['lines'][] = array('style' => $style, 'kind' => (string)$kind, 'text' => (string)$text);
    } else {
        $state['more'] = (int)$state['more'] + 1;
    }
    return $state;
}

/**
 * The design-wide body classes folded onto a page tree's root classes, as
 * the editor's Save folds them.
 *
 * Pure: no query.
 *
 * @param array  $tree
 * @param string $classes  space-separated, '' for none
 */
function pg_ltd_fold_body_classes($tree, $classes)
{
    if (!is_array($tree) || trim((string)$classes) === '') return $tree;
    if (!isset($tree['props']) || !is_array($tree['props'])) $tree['props'] = array();
    $merged = array();
    foreach (preg_split('/\s+/', (string)$classes . ' ' . (string)($tree['props']['cssClass'] ?? '')) as $cls) {
        if ($cls !== '' && !in_array($cls, $merged, true)) $merged[] = $cls;
    }
    $tree['props']['cssClass'] = implode(' ', $merged);
    return $tree;
}

// ── Cursor and kept reports ─────────────────────────────────────────────

function pg_ltd_state_path()
{
    return PG_FUNCTIONS_DIR . '/data/temp/legacy_to_designer.json';
}

/**
 * The running pass, null when there is none.
 */
function pg_ltd_state_read()
{
    $path = pg_ltd_state_path();
    if (!is_file($path)) return null;
    $state = json_decode((string)@file_get_contents($path), true);
    return (is_array($state) && isset($state['styles'], $state['index'], $state['step'])) ? $state : null;
}

function pg_ltd_state_write($state)
{
    $path = pg_ltd_state_path();
    // Written beside it and renamed: a killed request never leaves half a file.
    if (@file_put_contents($path . '.tmp', json_encode($state)) !== false) {
        @rename($path . '.tmp', $path);
    }
}

function pg_ltd_reports_dir()
{
    return PG_FUNCTIONS_DIR . '/data/legacy_to_designer';
}

/**
 * A finished run kept as data/legacy_to_designer/<run id>.json (outside
 * data/temp, which the clean up tool empties).
 */
function pg_ltd_report_keep($state)
{
    $dir = pg_ltd_reports_dir();
    if (!is_dir($dir)) @mkdir($dir, 0755, true);
    $id = preg_replace('/[^0-9a-z-]/', '', (string)$state['id']);
    if ($id === '') return false;
    $state['finished'] = time();
    return @file_put_contents($dir . '/' . $id . '.json', json_encode($state)) !== false;
}

/**
 * The kept runs, newest first.
 *
 * @param int $limit
 * @return array  run states with 'finished'
 */
function pg_ltd_reports($limit = 10)
{
    $files = glob(pg_ltd_reports_dir() . '/*.json');
    if (!$files) return array();
    rsort($files);
    $out = array();
    foreach (array_slice($files, 0, max(1, (int)$limit)) as $file) {
        $run = json_decode((string)@file_get_contents($file), true);
        if (is_array($run) && isset($run['id'])) $out[] = $run;
    }
    return $out;
}

// ── The run ─────────────────────────────────────────────────────────────

/**
 * Starts a run of the chosen styles; refused while one is under way.
 *
 * @return string  '' when started, else the reason
 */
function pg_ltd_run_start($style_ids, $user)
{
    if ((int)$user['role'] > 1) return lang('Only a designer can open a classic style in the visual designer.');
    if (pg_ltd_state_read() !== null) return lang('A move is already under way.');
    $state = pg_ltd_state_new($style_ids, $user, time());
    if (!$state['styles']) return lang('Choose the styles to move first.');
    foreach (db_items("SELECT style_id, style_name FROM style WHERE style_id IN (" . implode(',', $state['styles']) . ")") as $r) {
        $state['names'][(string)(int)$r['style_id']] = (string)$r['style_name'];
    }
    pg_ltd_state_write($state);
    return is_file(pg_ltd_state_path()) ? '' : lang('The move could not be started: data/temp is not writable.');
}

/**
 * Works the running pass for about $budget seconds, one step at a time.
 *
 * @return array  'status' ('none', 'busy', 'running', 'done', 'failed'),
 *                'progress' (pg_ltd_state_progress()), 'counts'
 */
function pg_ltd_run($budget)
{
    $answer = array('status' => 'none', 'progress' => null, 'counts' => array());
    if ((int)db_value("SELECT GET_LOCK('pg_ltd', 0)") !== 1) {
        $answer['status'] = 'busy';
        return $answer;
    }
    $state = pg_ltd_state_read();
    if ($state === null) {
        db("SELECT RELEASE_LOCK('pg_ltd')");
        return $answer;
    }

    // The run works as the designer who started it, whose role is read
    // again: one demoted meanwhile ends it.
    $user = db_item("SELECT user_id AS id, user_username AS username, user_role AS role FROM user WHERE user_id = '" . (int)$state['user_id'] . "' LIMIT 1");
    if (!is_array($user) || (int)$user['role'] > 1) {
        $state = pg_ltd_state_line($state, 'failed', lang('The move stopped: the designer who started it can no longer convert styles.'));
        $state['index'] = count($state['styles']);
    } elseif (!isset($_SESSION['sessionusername'])) {
        // The converters log under the session's user name; the job has none.
        $_SESSION['sessionusername'] = (string)$user['username'];
    }

    $stop = microtime(true) + max(1, (int)$budget);
    $stepped = false;
    while (!pg_ltd_state_done($state) && microtime(true) < $stop) {
        $state = pg_ltd_run_step($state, $user);
        pg_ltd_state_write($state);
        $stepped = true;
    }

    // A moved style draws its pages anew; the general job works the pass
    // outside a signed-in request, so the file cache is told here.
    if ($stepped && function_exists('pg_page_cache_mark_dirty')) {
        pg_page_cache_mark_dirty();
    }

    if (pg_ltd_state_done($state)) {
        pg_ltd_report_keep($state);
        @unlink(pg_ltd_state_path());
        $c = $state['counts'];
        log_activity(lang(array('string' => 'the move to the visual designer ended: {var:1} styles, {var:2} pages, {var:3} components, {var:4} widgets, {var:5} skipped, {var:6} failed',
            'vars' => array($c['styles'], $c['pages'], $c['components'], $c['widgets'], $c['skipped'], $c['failed']))), (string)$state['username']);
        $answer['status'] = $c['failed'] > 0 ? 'failed' : 'done';
    } else {
        $answer['status'] = 'running';
    }
    db("SELECT RELEASE_LOCK('pg_ltd')");
    $answer['progress'] = pg_ltd_state_progress($state);
    $answer['counts'] = $state['counts'];
    return $answer;
}

/**
 * The general job's share: a running pass is worked for the tick's budget,
 * so it carries on with the screen closed. With none it reads one missing
 * file.
 */
function pg_ltd_job_tick($budget)
{
    if (!is_file(pg_ltd_state_path())) return null;
    return pg_ltd_run($budget);
}

/**
 * One step of the style under the cursor, then the cursor moved on.
 */
function pg_ltd_run_step($state, $user)
{
    $style_id = pg_ltd_state_progress($state)['style'];
    $row = db_item("SELECT style_id, style_name, style_type, style_layout, style_code, additional_body_classes FROM style WHERE style_id = '$style_id' LIMIT 1");
    if (!is_array($row)) {
        $state = pg_ltd_state_line($state, 'skipped', lang('The style no longer exists.'));
        return pg_ltd_state_advance($state, true);
    }
    $designed = (string)$row['style_layout'] === 'visual_designer';

    switch ($state['step']) {
        case 'regions':
            // A style already a design (made before, or by an earlier run)
            // keeps its regions as they are; its later steps still run.
            if ($designed) break;
            $refusal = pg_classic_style_refusal($row);
            if ($refusal !== '') {
                $state = pg_ltd_state_line($state, 'skipped', $refusal);
                return pg_ltd_state_advance($state, true);
            }
            foreach (pg_ltd_style_tags((string)$row['style_code'])['regions'] as $r) {
                if ($r['type'] !== 'common') continue;
                $rid = (int)db_value("SELECT cregion_id FROM cregion WHERE cregion_name = '" . e($r['name']) . "' AND cregion_designer_type = 'no' LIMIT 1");
                if ($rid <= 0) continue;
                $warnings = array();
                $made = pg_common_region_component($rid, $user, $warnings);
                if (is_array($made) && $made['created']) {
                    $state = pg_ltd_state_line($state, 'made', lang(array('string' => 'The common region "{var:1}" is now a shared component.', 'vars' => array($r['name']))), 'components');
                }
                foreach ($warnings as $w) $state = pg_ltd_state_line($state, 'skipped', $w);
            }
            break;

        case 'design':
            if ($designed) break;
            $state = pg_ltd_run_design($state, $row, $user);
            break;

        case 'page_regions':
            $state = pg_ltd_run_page_regions($state, $style_id, $user);
            break;

        case 'frame':
            $state = pg_ltd_run_frame($state, $row, $user);
            break;
    }
    return pg_ltd_state_advance($state);
}

/**
 * The style to a design: the frame component and the trees of its own
 * pages, each saved through pg_designer_save_page() (validated first, a page
 * somebody holds skipped), the folder pages, then the style flipped. The
 * design-wide body classes go onto the pages, as the editor's Save puts them.
 */
function pg_ltd_run_design($state, $row, $user)
{
    $style_id = (int)$row['style_id'];
    $open = pg_classic_style_design_open($style_id, $user);
    if ($open['error'] !== '') {
        $state = pg_ltd_state_line($state, 'failed', $open['error']);
        return pg_ltd_state_advance($state, true);
    }
    foreach ($open['warnings'] as $w) $state = pg_ltd_state_line($state, 'skipped', (string)$w);

    require_once(PG_FUNCTIONS_DIR . '/includes/designer_collab.php');
    $classes = trim((string)$row['additional_body_classes']);
    foreach ($open['pages'] as $page) {
        if (empty($page['converted'])) continue;
        if (!pg_collab_may_edit_page('', (int)$page['page_id'])) {
            $state = pg_ltd_state_line($state, 'skipped', lang(array('string' => 'Skipped: "{var:1}" is being edited in the visual designer.', 'vars' => array($page['page_name']))));
            continue;
        }
        if ($classes !== '') {
            $page['tree_json'] = pg_designer_tree_encode(pg_ltd_fold_body_classes(json_decode((string)$page['tree_json'], true), $classes));
        }
        $check = pg_designer_save_page($style_id, $page, $user, true);
        $saved = $check['ok'] ? pg_designer_save_page($style_id, $page, $user, false) : $check;
        if ($saved['ok']) {
            $state = pg_ltd_state_line($state, 'made', lang(array('string' => 'The page "{var:1}" now has a tree.', 'vars' => array($page['page_name']))), 'pages');
        } else {
            $state = pg_ltd_state_line($state, 'failed', $page['page_name'] . ': ' . implode(' ', (array)$saved['errors']));
        }
    }

    $folder = pg_classic_style_convert_folder_pages($style_id, $user, $classes);
    foreach ($folder as $page_id) {
        $state = pg_ltd_state_line($state, 'made', lang(array('string' => 'The page "{var:1}" now has a tree.', 'vars' => array((string)db_value("SELECT page_name FROM page WHERE page_id = '" . (int)$page_id . "' LIMIT 1")))), 'pages');
    }
    if ($classes !== '') db("UPDATE style SET additional_body_classes = '' WHERE style_id = '$style_id' LIMIT 1");

    pg_classic_style_mark_design($style_id, 'custom', (string)$user['username']);
    return pg_ltd_state_line($state, 'made', lang('The style is now a visual design.'), 'styles');
}

/**
 * The page regions of the style's pages that had a tree of their own moved
 * into it (pg_page_region_blocks(), pg_ltd_page_region_splice()). A page
 * whose page regions sit in a shared component, or that somebody holds,
 * keeps them.
 */
function pg_ltd_run_page_regions($state, $style_id, $user)
{
    require_once(PG_FUNCTIONS_DIR . '/includes/designer_region_import.php');
    require_once(PG_FUNCTIONS_DIR . '/includes/designer_collab.php');
    foreach (pg_designer_load_pages($style_id, '', true) as $page) {
        $tree = json_decode((string)$page['tree_json'], true);
        if (!is_array($tree)) continue;
        $found = pg_ltd_page_regions($tree, array());
        if ($found['own'] === 0) continue;
        if (!pg_collab_may_edit_page('', (int)$page['page_id'])) {
            $state = pg_ltd_state_line($state, 'skipped', lang(array('string' => 'Skipped: "{var:1}" is being edited in the visual designer.', 'vars' => array($page['page_name']))));
            continue;
        }
        $shared = array();
        foreach (pg_ltd_shared_ids($tree) as $sid) {
            $t = json_decode((string)db_value("SELECT tree_json FROM shared_components WHERE id = '" . (int)$sid . "' LIMIT 1"), true);
            if (is_array($t)) $shared[$sid] = $t;
        }
        if (pg_ltd_page_regions($tree, $shared)['shared'] > 0) {
            $state = pg_ltd_state_line($state, 'skipped', lang(array('string' => '"{var:1}" keeps its page regions: some of them sit in a shared component.', 'vars' => array($page['page_name']))));
            continue;
        }
        $blocks = pg_page_region_blocks((int)$page['page_id'], $found['own']);
        $spliced = pg_ltd_page_region_splice($tree, $blocks['blocks']);
        if ($spliced === null) continue;
        $page['tree_json'] = pg_designer_tree_encode($spliced);
        $check = pg_designer_save_page($style_id, $page, $user, true);
        $saved = $check['ok'] ? pg_designer_save_page($style_id, $page, $user, false) : $check;
        if ($saved['ok']) {
            $state = pg_ltd_state_line($state, 'made', lang(array('string' => 'The page regions of "{var:1}" are now part of its tree.', 'vars' => array($page['page_name']))), 'pages');
            foreach ($blocks['warnings'] as $w) $state = pg_ltd_state_line($state, 'skipped', $page['page_name'] . ': ' . $w);
        } else {
            $state = pg_ltd_state_line($state, 'failed', $page['page_name'] . ': ' . implode(' ', (array)$saved['errors']));
        }
    }
    return $state;
}

/**
 * The shared component ids a tree places directly.
 *
 * Pure: no query.
 */
function pg_ltd_shared_ids($tree)
{
    $ids = array();
    $walk = function ($node) use (&$walk, &$ids) {
        if (!is_array($node)) return;
        if (($node['type'] ?? '') === 'shared_ref') {
            $sid = (int)($node['props']['sharedId'] ?? 0);
            if ($sid > 0 && !in_array($sid, $ids, true)) $ids[] = $sid;
            return;
        }
        foreach ((array)($node['children'] ?? array()) as $c) $walk($c);
    };
    $walk($tree);
    return $ids;
}

/**
 * The frame component's designer regions made shared components and its
 * named menu, menu sequence, ad and tag cloud regions made widgets (one per
 * region), each put in the region node's place; the frame row written once.
 * A frame already done has no such nodes left, so nothing is made twice.
 */
function pg_ltd_run_frame($state, $row, $user)
{
    $style_id = (int)$row['style_id'];
    $frame_row = db_item("SELECT id, tree_json FROM shared_components
                          WHERE category = '" . e(pg_classic_style_component_category($style_id)) . "'
                            AND (system_region_config IS NULL OR system_region_config = '')
                          ORDER BY id ASC LIMIT 1");
    if (!is_array($frame_row)) return $state;
    $frame = json_decode((string)$frame_row['tree_json'], true);
    if (!is_array($frame)) return $state;

    require_once(PG_FUNCTIONS_DIR . '/includes/designer_region_import.php');
    $targets = pg_ltd_frame_targets($frame);
    $made = array();
    foreach ($targets['designer'] as $name) {
        $warnings = array();
        $c = pg_designer_region_component($name, $user, $warnings);
        if (!is_array($c)) continue;
        $made["designer\n" . $name] = $c;
        if ($c['created']) {
            $state = pg_ltd_state_line($state, 'made', lang(array('string' => 'The designer region "{var:1}" is now a shared component.', 'vars' => array($name))), 'components');
        }
        foreach ($warnings as $w) $state = pg_ltd_state_line($state, 'skipped', $w);
    }
    foreach ($targets['widgets'] as $t) {
        $tree = pg_ltd_widget_tree($t['kind']);
        $tree_json = pg_designer_tree_encode($tree);
        if ($tree_json === '') continue;
        $name = _sc_unique_name(pg_ltd_widget_name((string)$row['style_name'], $t['kind']));
        $now = time();
        db("INSERT INTO shared_components (name, description, tree_json, system_region_config, category, created_by, created_at, updated_at)
            VALUES ('" . e($name) . "', '', '" . e($tree_json) . "', '" . e(json_encode(pg_ltd_widget_config($t))) . "', '', '" . (int)$user['id'] . "', '$now', '$now')");
        $sid = (int)mysqli_insert_id(db::$con);
        if ($sid <= 0) {
            $state = pg_ltd_state_line($state, 'failed', lang(array('string' => 'The widget for "{var:1}" could not be saved.', 'vars' => array($t['name']))));
            continue;
        }
        $made[$t['type'] . "\n" . $t['name']] = array('id' => $sid, 'name' => $name);
        $state = pg_ltd_state_line($state, 'made', lang(array('string' => 'The region "{var:1}" is now the widget {var:2}.', 'vars' => array($t['name'], $name))), 'widgets');
    }
    if (!$made) return $state;

    $count = 0;
    $frame = pg_ltd_frame_splice($frame, $made, $count);
    $json = pg_designer_tree_encode($frame);
    if ($count > 0 && $json !== '') {
        db("UPDATE shared_components SET tree_json = '" . e($json) . "', updated_at = '" . time() . "' WHERE id = '" . (int)$frame_row['id'] . "' LIMIT 1");
    }
    return $state;
}
