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
 * A set of styles (pg_ltd_sets()) moves into one new Legacy design instead,
 * each of its styles in turn:
 *
 *   1. set_design    the design's style row with the set's asset rows, made
 *                    at the set's first style
 *   2. regions       as above
 *   3. set_pages     the frame component, and the style's pages saved to the
 *                    design (page_style), each placing its own style's frame;
 *                    the classic style row stays as it is
 *   4. frame         as above
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
 * The steps of one style, in order: a style moving alone, or one of a set.
 *
 * @param bool $set
 * @return array
 */
function pg_ltd_steps($set = false)
{
    return $set ? array('set_design', 'regions', 'set_pages', 'frame') : array('regions', 'design', 'page_regions', 'frame');
}

/**
 * A new run's cursor. The sets' styles come first, set by set, then the
 * styles chosen alone; a style is worked once.
 *
 * Pure: no query.
 *
 * @param array $style_ids  styles chosen alone, in the order to work them
 * @param array $user       the designer starting it (id, username)
 * @param int   $now
 * @param array $sets       pg_ltd_sets() sets, 'name' the design's (unique)
 * @return array  'sets' lists each set's key, name, theme_id, collection,
 *                settings, styles and 'design' (its style id, 0 until made)
 */
function pg_ltd_state_new($style_ids, $user, $now, $sets = array())
{
    $ids = array();
    $set_rows = array();
    foreach ((array)$sets as $set) {
        $members = array();
        foreach ((array)($set['styles'] ?? array()) as $id) {
            if ((int)$id > 0 && !in_array((int)$id, $ids, true)) {
                $ids[] = (int)$id;
                $members[] = (int)$id;
            }
        }
        if (!$members) continue;
        $set_rows[] = array('key' => (string)($set['key'] ?? ''), 'name' => (string)($set['name'] ?? ''),
                            'theme_id' => (int)($set['theme_id'] ?? 0), 'collection' => (string)($set['collection'] ?? ''),
                            'settings' => (array)($set['settings'] ?? array()), 'styles' => $members, 'design' => 0);
    }
    foreach ((array)$style_ids as $id) {
        if ((int)$id > 0 && !in_array((int)$id, $ids, true)) $ids[] = (int)$id;
    }
    $state = array(
        'id'       => gmdate('Ymd-His', (int)$now) . '-' . substr(md5(implode(',', $ids) . '|' . (int)$now), 0, 6),
        'started'  => (int)$now,
        'user_id'  => (int)($user['id'] ?? 0),
        'username' => (string)($user['username'] ?? ''),
        'styles'   => $ids,
        'sets'     => $set_rows,
        'index'    => 0,
        'step'     => '',
        'names'    => array(),
        'counts'   => array('styles' => 0, 'pages' => 0, 'components' => 0, 'widgets' => 0, 'skipped' => 0, 'failed' => 0),
        'lines'    => array(),
        'more'     => 0,
    );
    $state['step'] = pg_ltd_steps(pg_ltd_state_set($state) >= 0)[0];
    return $state;
}

/**
 * The set the style under the cursor moves with: its place in 'sets', -1
 * for a style moving alone (and in a cursor written before sets).
 *
 * Pure: no query.
 */
function pg_ltd_state_set($state)
{
    $styles = (array)($state['styles'] ?? array());
    $index = (int)($state['index'] ?? 0);
    if (!isset($styles[$index])) return -1;
    foreach ((array)($state['sets'] ?? array()) as $at => $set) {
        if (in_array((int)$styles[$index], array_map('intval', (array)($set['styles'] ?? array())), true)) return (int)$at;
    }
    return -1;
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
    $steps = pg_ltd_steps(pg_ltd_state_set($state) >= 0);
    $at = array_search((string)$state['step'], $steps, true);
    if ($skip_style || $at === false || $at + 1 >= count($steps)) {
        $state['index'] = (int)$state['index'] + 1;
        $state['step'] = pg_ltd_steps(pg_ltd_state_set($state) >= 0)[0];
    } else {
        $state['step'] = $steps[$at + 1];
    }
    return $state;
}

/**
 * The cursor after a step, as the step marked it in 'skip': '' the next
 * step, 'style' the next style, 'set' the first style after the set the
 * style moves with (a style alone: the next one).
 *
 * Pure: no query.
 */
function pg_ltd_state_next($state)
{
    $skip = (string)($state['skip'] ?? '');
    unset($state['skip']);
    $at = ($skip === 'set') ? pg_ltd_state_set($state) : -1;
    if ($at >= 0) {
        foreach ((array)$state['styles'] as $i => $id) {
            if (in_array((int)$id, (array)$state['sets'][$at]['styles'], true)) $state['index'] = max((int)$state['index'], (int)$i);
        }
    }
    return pg_ltd_state_advance($state, $skip !== '');
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
 * A page a set's run moved, kept in the run's `origins`: page id => the
 * classic style it came from and 1 when it took that style from its folder.
 * Back to classic reads it (legacy_to_classic.php); a page whose tree places
 * no frame component cannot be told otherwise.
 *
 * Pure: no query.
 *
 * @param array $state
 * @param int   $page_id
 * @param int   $style_id
 * @param bool  $from_folder
 * @return array
 */
function pg_ltd_state_origin($state, $page_id, $style_id, $from_folder)
{
    if ((int)$page_id > 0 && (int)$style_id > 0) {
        $state['origins'][(string)(int)$page_id] = array((int)$style_id, $from_folder ? 1 : 0);
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
 * Starts a run of the chosen sets and styles; refused while one is under
 * way. A set is read from the report as the dry run read it; one with no
 * page left has nothing to move. Its design takes the set's name, made
 * unique among the styles now.
 *
 * @param array $style_ids  styles chosen alone
 * @param array $user
 * @param array $set_keys   pg_ltd_sets() keys
 * @return string  '' when started, else the reason
 */
function pg_ltd_run_start($style_ids, $user, $set_keys = array())
{
    if ((int)$user['role'] > 1) return lang('Only a designer can open a classic style in the Page Design Studio.');
    if (pg_ltd_state_read() !== null) return lang('A move is already under way.');
    $want = array();
    foreach ((array)$set_keys as $key) {
        if (is_string($key) && preg_match('/^[0-9a-f]{16}$/', $key)) $want[$key] = true;
    }
    $sets = array();
    foreach (($want ? pg_ltd_sets(pg_ltd_report())['sets'] : array()) as $set) {
        if (!isset($want[$set['key']]) || (int)$set['pages'] === 0) continue;
        $set['name'] = get_unique_name(array('name' => $set['name'], 'type' => 'style'));
        $sets[] = $set;
    }
    $state = pg_ltd_state_new($style_ids, $user, time(), $sets);
    if (!$state['styles']) return $want ? lang('The chosen set has no page left to move.') : lang('Choose the styles to move first.');
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
        log_activity(lang(array('string' => 'the move to the Page Design Studio ended: {var:1} styles, {var:2} pages, {var:3} components, {var:4} widgets, {var:5} skipped, {var:6} failed',
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
    $set_at = pg_ltd_state_set($state);
    $row = db_item("SELECT style_id, style_name, style_type, style_layout, style_code, additional_body_classes FROM style WHERE style_id = '$style_id' LIMIT 1");
    if (!is_array($row)) {
        $state = pg_ltd_state_line($state, 'skipped', lang('The style no longer exists.'));
        return pg_ltd_state_advance($state, true);
    }
    $designed = (string)$row['style_layout'] === 'visual_designer';

    switch ($state['step']) {
        case 'set_design':
            if ((int)$state['sets'][$set_at]['design'] <= 0) $state = pg_ltd_run_set_design($state, $set_at, $user);
            break;

        case 'regions':
            // A style already a design (made before, or by an earlier run)
            // keeps its regions as they are; its later steps still run. In
            // a set it is refused below: its pages are a design's already.
            if ($designed && $set_at < 0) break;
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

        case 'set_pages':
            $state = pg_ltd_run_set_pages($state, $row, $user, $state['sets'][$set_at]);
            break;

        case 'frame':
            $state = pg_ltd_run_frame($state, $row, $user);
            break;
    }
    return pg_ltd_state_next($state);
}

/**
 * The style to a design: the frame's shared components and the trees of its
 * own pages, their page regions taken in (pg_ltd_page_regions_into_tree()),
 * each saved through pg_designer_save_page() (validated first, a page
 * somebody holds skipped), the folder pages, then the style flipped. The
 * design-wide body classes go onto the pages, as the editor's Save puts them.
 */
function pg_ltd_run_design($state, $row, $user)
{
    $style_id = (int)$row['style_id'];
    $open = pg_classic_style_design_open($style_id, $user);
    if ($open['error'] !== '') {
        $state = pg_ltd_state_line($state, 'failed', $open['error']);
        $state['skip'] = 'style';
        return $state;
    }
    foreach ($open['warnings'] as $w) $state = pg_ltd_state_line($state, 'skipped', (string)$w);

    require_once(PG_FUNCTIONS_DIR . '/includes/designer_collab.php');
    $classes = trim((string)$row['additional_body_classes']);
    foreach ($open['pages'] as $page) {
        if (empty($page['converted'])) continue;
        if (!pg_collab_may_edit_page('', (int)$page['page_id'])) {
            $state = pg_ltd_state_line($state, 'skipped', lang(array('string' => 'Skipped: "{var:1}" is being edited in the Page Design Studio.', 'vars' => array($page['page_name']))));
            continue;
        }
        $tree = json_decode((string)$page['tree_json'], true);
        $into = is_array($tree) ? pg_ltd_page_regions_into_tree($page, $tree) : array('tree' => null, 'skipped' => '', 'warnings' => array());
        if ($into['skipped'] !== '') $state = pg_ltd_state_line($state, 'skipped', $into['skipped']);
        if ($into['tree'] !== null) $tree = $into['tree'];
        if (is_array($tree)) $page['tree_json'] = pg_designer_tree_encode(pg_ltd_fold_body_classes($tree, $classes));
        $saved = pg_ltd_save_page($style_id, $page, $user);
        if ($saved['ok']) {
            $state = pg_ltd_state_line($state, 'made', lang(array('string' => 'The page "{var:1}" now has a tree.', 'vars' => array($page['page_name']))), 'pages');
            foreach ($into['warnings'] as $w) $state = pg_ltd_state_line($state, 'skipped', $page['page_name'] . ': ' . $w);
        } else {
            $state = pg_ltd_state_line($state, 'failed', $page['page_name'] . ': ' . implode(' ', (array)$saved['errors']));
        }
    }

    $folder = pg_classic_style_convert_folder_pages($style_id, $user, $classes);
    foreach ($folder as $page_id) {
        $state = pg_ltd_state_line($state, 'made', lang(array('string' => 'The page "{var:1}" now has a tree.', 'vars' => array((string)db_value("SELECT page_name FROM page WHERE page_id = '" . (int)$page_id . "' LIMIT 1")))), 'pages');
    }
    if ($classes !== '') db("UPDATE style SET additional_body_classes = '' WHERE style_id = '$style_id' LIMIT 1");

    pg_classic_style_mark_design($style_id, 'legacy', (string)$user['username']);
    return pg_ltd_state_line($state, 'made', lang('The style is now a design.'), 'styles');
}

/**
 * The page regions still in the trees of the style's pages moved into them
 * (pg_page_region_blocks(), pg_ltd_page_region_splice()): pages that had a
 * tree of their own, and folder pages given the split frame's tree. A page
 * whose page regions sit in a shared component, or that somebody holds,
 * keeps them.
 *
 * @param array|null $page_ids  only these pages of the style, null for all
 */
function pg_ltd_run_page_regions($state, $style_id, $user, $page_ids = null)
{
    require_once(PG_FUNCTIONS_DIR . '/includes/designer_collab.php');
    $only = ($page_ids === null) ? null : array_flip(array_map('intval', (array)$page_ids));
    if ($only === array()) return $state;
    foreach (pg_designer_load_pages($style_id, '', true) as $page) {
        if ($only !== null && !isset($only[(int)$page['page_id']])) continue;
        $tree = json_decode((string)$page['tree_json'], true);
        if (!is_array($tree)) continue;
        if (pg_ltd_page_regions($tree, array())['own'] === 0) continue;
        if (!pg_collab_may_edit_page('', (int)$page['page_id'])) {
            $state = pg_ltd_state_line($state, 'skipped', lang(array('string' => 'Skipped: "{var:1}" is being edited in the Page Design Studio.', 'vars' => array($page['page_name']))));
            continue;
        }
        $into = pg_ltd_page_regions_into_tree($page, $tree);
        if ($into['skipped'] !== '') $state = pg_ltd_state_line($state, 'skipped', $into['skipped']);
        if ($into['tree'] === null) continue;
        $page['tree_json'] = pg_designer_tree_encode($into['tree']);
        $saved = pg_ltd_save_page($style_id, $page, $user);
        if ($saved['ok']) {
            $state = pg_ltd_state_line($state, 'made', lang(array('string' => 'The page regions of "{var:1}" are now part of its tree.', 'vars' => array($page['page_name']))), 'pages');
            foreach ($into['warnings'] as $w) $state = pg_ltd_state_line($state, 'skipped', $page['page_name'] . ': ' . $w);
        } else {
            $state = pg_ltd_state_line($state, 'failed', $page['page_name'] . ': ' . implode(' ', (array)$saved['errors']));
        }
    }
    return $state;
}

/**
 * A page's own page regions moved into its tree (pg_page_region_blocks(),
 * pg_ltd_page_region_splice()). A tree with none is left as it is, and so
 * is one whose page regions sit partly in a shared component.
 *
 * @param array $page  page_id, page_name
 * @param array $tree  the page's tree
 * @return array  'tree' (the new tree, null when nothing moved), 'skipped'
 *                (a report line, '' for none), 'warnings'
 */
function pg_ltd_page_regions_into_tree($page, $tree)
{
    $out = array('tree' => null, 'skipped' => '', 'warnings' => array());
    $found = pg_ltd_page_regions($tree, array());
    if ($found['own'] === 0) return $out;
    require_once(PG_FUNCTIONS_DIR . '/includes/designer_region_import.php');
    $shared = array();
    foreach (pg_ltd_shared_ids($tree) as $sid) {
        $t = json_decode((string)db_value("SELECT tree_json FROM shared_components WHERE id = '" . (int)$sid . "' LIMIT 1"), true);
        if (is_array($t)) $shared[$sid] = $t;
    }
    if (pg_ltd_page_regions($tree, $shared)['shared'] > 0) {
        $out['skipped'] = lang(array('string' => '"{var:1}" keeps its page regions: some of them sit in a shared component.', 'vars' => array($page['page_name'])));
        return $out;
    }
    $blocks = pg_page_region_blocks((int)$page['page_id'], $found['own']);
    $out['tree'] = pg_ltd_page_region_splice($tree, $blocks['blocks']);
    $out['warnings'] = $blocks['warnings'];
    return $out;
}

/**
 * A page saved as the editor's Save writes it, through
 * pg_designer_save_page(): checked first, written only when the check
 * passed. Saved to another design, the page moves there (page_style).
 *
 * @return array  pg_designer_save_page()'s answer
 */
function pg_ltd_save_page($style_id, $page, $user)
{
    $check = pg_designer_save_page($style_id, $page, $user, true);
    return $check['ok'] ? pg_designer_save_page($style_id, $page, $user, false) : $check;
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
 * The frame components' designer regions made shared components and their
 * named menu, menu sequence, ad and tag cloud regions made widgets (one per
 * region), each put in the region node's place; each frame row written once.
 * The frame components are the parts of a split frame (pg_ltd_frame_rows()),
 * else the whole frame. A frame already done has no such nodes left, so
 * nothing is made twice.
 */
function pg_ltd_run_frame($state, $row, $user)
{
    $frames = array();
    foreach (pg_ltd_frame_rows((int)$row['style_id']) as $frame_row) {
        $tree = json_decode((string)$frame_row['tree_json'], true);
        if (is_array($tree)) $frames[(int)$frame_row['id']] = $tree;
    }
    if (!$frames) return $state;

    require_once(PG_FUNCTIONS_DIR . '/includes/designer_region_import.php');
    $targets = pg_ltd_frame_targets(pg_di_root(array_values($frames)));
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

    foreach ($frames as $id => $frame) {
        $count = 0;
        $frame = pg_ltd_frame_splice($frame, $made, $count);
        $json = pg_designer_tree_encode($frame);
        if ($count > 0 && $json !== '') {
            db("UPDATE shared_components SET tree_json = '" . e($json) . "', updated_at = '" . time() . "' WHERE id = '" . (int)$id . "' LIMIT 1");
        }
    }
    return $state;
}

/**
 * The frame components of a classic style: the parts of its split frame
 * (category "classic_style:<id>:<part>"), else the whole frame's row.
 *
 * @return array  rows: id, tree_json
 */
function pg_ltd_frame_rows($style_id)
{
    $no_widget = "(system_region_config IS NULL OR system_region_config = '')";
    $parts = db_items("SELECT id, tree_json FROM shared_components
                       WHERE category LIKE '" . e(pg_classic_style_part_category_like((int)$style_id)) . "' AND $no_widget ORDER BY id ASC");
    if (is_array($parts) && $parts) return $parts;
    $whole = db_item("SELECT id, tree_json FROM shared_components
                      WHERE category = '" . e(pg_classic_style_component_category((int)$style_id)) . "' AND $no_widget ORDER BY id ASC LIMIT 1");
    return is_array($whole) ? array($whole) : array();
}

// ── A set ───────────────────────────────────────────────────────────────

/**
 * The style row of a set's design as save_system_style() takes it: a new
 * Legacy design under the set's name, its theme file and collection, the
 * sharing position most of its styles had, the set's asset rows
 * (pg_ltd_set_assets()) without the style names the dry run shows, and the
 * fonts. No tree: every page carries its own.
 *
 * Pure: no query.
 *
 * @param array $set     a cursor set: name, theme_id, collection, settings
 * @param array $assets  pg_ltd_set_assets()
 * @param array $fonts   the importer's font entries
 * @param int   $user_id
 * @return array
 */
function pg_ltd_set_design_row($set, $assets, $fonts, $user_id)
{
    $rows = array('css' => array(), 'js' => array());
    foreach ($rows as $kind => $list) {
        foreach ((array)($assets[$kind] ?? array()) as $a) {
            if (!is_array($a)) continue;
            unset($a['styles']);
            $rows[$kind][] = $a;
        }
    }
    $flags = JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES;
    $collection = (string)($set['collection'] ?? '');
    return array(
        'style_id'                   => 0,
        'user_id'                    => (int)$user_id,
        'name'                       => (string)($set['name'] ?? ''),
        'framework'                  => 'legacy',
        'theme_id'                   => (int)($set['theme_id'] ?? 0),
        'collection'                 => $collection !== '' ? $collection : 'a',
        'social_networking_position' => (string)($set['settings']['social_networking_position'] ?? ''),
        'style_custom_css'           => json_encode($rows['css'], $flags),
        'style_custom_js'            => json_encode($rows['js'], $flags),
        'style_custom_fonts'         => json_encode(array_values((array)$fonts), $flags),
    );
}

/**
 * What a set's design is written with, read from its styles' code as the
 * dry run reads it: the asset rows, a row only some styles have kept to
 * their pages (own and folder), and the fonts. A style the designer refuses
 * adds nothing.
 *
 * @param array $style_ids  the set's styles, in order
 * @return array  'assets' (pg_ltd_set_assets()), 'fonts'
 */
function pg_ltd_set_design_assets($style_ids)
{
    $styles = array();
    $fonts = array();
    foreach ((array)$style_ids as $id) {
        $id = (int)$id;
        $row = db_item("SELECT style_id, style_name, style_type, style_layout, style_code FROM style WHERE style_id = '$id' LIMIT 1");
        if (pg_classic_style_refusal($row) !== '') continue;
        $frame = pg_classic_style_frame((string)$row['style_code'], pg_classic_style_region_lookup());
        $files = pg_classic_style_frame_assets($frame);
        $pages = array_map('intval', db_values("SELECT page_id FROM page WHERE page_style = '$id'" . pg_designer_not_binned_sql('page_folder')));
        foreach (pg_classic_style_folder_pages($id) as $p) $pages[] = (int)$p['page_id'];
        $styles[] = array('id' => $id, 'name' => (string)$row['style_name'], 'pages' => $pages, 'css' => $files['css'], 'js' => $files['js']);
        foreach ((array)$frame['fonts'] as $f) $fonts[] = $f;
    }
    return array('assets' => pg_ltd_set_assets($styles), 'fonts' => pg_di_dedupe_fonts($fonts));
}

/**
 * The set's design made, at its first style: the style row with the asset
 * rows and the layout override most of its styles had. A request that ended
 * between the row's insert and the cursor's write left the row behind: the
 * name was free when the run started, so a visual design of that name made
 * since, with no page yet, is taken. A design that cannot be written skips
 * the set.
 */
function pg_ltd_run_set_design($state, $at, $user)
{
    $set = $state['sets'][$at];
    $id = (int)db_value("SELECT style_id FROM style
                         WHERE style_name = '" . e($set['name']) . "' AND style_layout = 'visual_designer'
                           AND style_timestamp >= '" . (int)$state['started'] . "'
                           AND NOT EXISTS (SELECT 1 FROM page WHERE page.page_style = style.style_id)
                         ORDER BY style_id DESC LIMIT 1");
    if ($id <= 0) {
        $read = pg_ltd_set_design_assets($set['styles']);
        $id = (int)save_system_style(pg_ltd_set_design_row($set, $read['assets'], $read['fonts'], $user['id']));
    }
    if ($id <= 0) {
        $state = pg_ltd_state_line($state, 'failed', lang(array('string' => 'The design "{var:1}" could not be saved.', 'vars' => array($set['name']))));
        $state['skip'] = 'set';
        return $state;
    }
    $layout = (string)($set['settings']['layout_type'] ?? '');
    if ($layout !== '') db("UPDATE style SET layout_type = '" . e($layout) . "' WHERE style_id = '$id' LIMIT 1");
    $state['sets'][$at]['design'] = $id;
    return pg_ltd_state_line($state, 'made', lang(array('string' => 'The design "{var:1}" was made for the set.', 'vars' => array($set['name']))));
}

/**
 * A style of a set moved to the set's design: its frame components made (or
 * the ones made before), then each of its own pages saved to the design
 * through pg_ltd_save_page(), which moves its page_style: a page with no
 * tree gets the tree of the split frame, a page that had one keeps it; the
 * page regions are taken in, a page somebody holds is skipped. Its folder
 * pages follow (pg_classic_style_convert_folder_pages()), then their page
 * regions (pg_ltd_run_page_regions()). The style's body classes go on its
 * pages; the classic style row stays as it is. Each moved page's style is
 * kept in the run's `origins` (pg_ltd_state_origin()) for Back to classic.
 */
function pg_ltd_run_set_pages($state, $row, $user, $set)
{
    $style_id = (int)$row['style_id'];
    $design_id = (int)$set['design'];
    if ($design_id <= 0 || (int)db_value("SELECT style_id FROM style WHERE style_id = '$design_id' AND style_layout = 'visual_designer' LIMIT 1") !== $design_id) {
        $state = pg_ltd_state_line($state, 'failed', lang(array('string' => 'The design "{var:1}" no longer exists.', 'vars' => array($set['name']))));
        $state['skip'] = 'set';
        return $state;
    }
    $open = pg_classic_style_design_open($style_id, $user);
    if ($open['error'] !== '') {
        $state = pg_ltd_state_line($state, 'failed', $open['error']);
        $state['skip'] = 'style';
        return $state;
    }
    foreach ($open['warnings'] as $w) $state = pg_ltd_state_line($state, 'skipped', (string)$w);

    require_once(PG_FUNCTIONS_DIR . '/includes/designer_collab.php');
    $classes = trim((string)$row['additional_body_classes']);
    $moved = function ($state, $page_id, $name, $from_folder) use ($set, $style_id) {
        $state = pg_ltd_state_origin($state, $page_id, $style_id, $from_folder);
        return pg_ltd_state_line($state, 'made', lang(array('string' => 'The page "{var:1}" moved to the design "{var:2}".', 'vars' => array($name, $set['name']))), 'pages');
    };
    foreach ($open['pages'] as $page) {
        if (!pg_collab_may_edit_page('', (int)$page['page_id'])) {
            $state = pg_ltd_state_line($state, 'skipped', lang(array('string' => 'Skipped: "{var:1}" is being edited in the Page Design Studio.', 'vars' => array($page['page_name']))));
            continue;
        }
        $tree = json_decode((string)$page['tree_json'], true);
        $warnings = array();
        if (is_array($tree)) {
            $into = pg_ltd_page_regions_into_tree($page, $tree);
            if ($into['skipped'] !== '') $state = pg_ltd_state_line($state, 'skipped', $into['skipped']);
            if ($into['tree'] !== null) $tree = $into['tree'];
            $warnings = $into['warnings'];
        }
        if (is_array($tree)) $page['tree_json'] = pg_designer_tree_encode(pg_ltd_fold_body_classes($tree, $classes));
        $saved = pg_ltd_save_page($design_id, $page, $user);
        if (!$saved['ok']) {
            $state = pg_ltd_state_line($state, 'failed', $page['page_name'] . ': ' . implode(' ', (array)$saved['errors']));
            continue;
        }
        $state = $moved($state, (int)$page['page_id'], (string)$page['page_name'], false);
        foreach ($warnings as $w) $state = pg_ltd_state_line($state, 'skipped', $page['page_name'] . ': ' . $w);
    }
    $folder = pg_classic_style_convert_folder_pages($style_id, $user, $classes, $design_id);
    foreach ($folder as $page_id) {
        $state = $moved($state, (int)$page_id, (string)db_value("SELECT page_name FROM page WHERE page_id = '" . (int)$page_id . "' LIMIT 1"), true);
    }
    $state = pg_ltd_run_page_regions($state, $design_id, $user, $folder);
    return pg_ltd_state_line($state, 'made', lang(array('string' => 'The style\'s pages now use the design "{var:1}"; the style itself stays as it was.', 'vars' => array($set['name']))), 'styles');
}
