<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Moving a Legacy design's pages to its trial design on Bootstrap 5.3.8
 * (legacy_bootstrap_trial.php), all of them or the chosen ones, and sending
 * them back to the Legacy design, one page or all.
 *
 * A move writes the page's Legacy tree as its newest revision, keeps that
 * tree in data/legacy_to_bootstrap/pages/<page id>.json, then saves the
 * converted tree (pg_lbs_tree(), the parts' places pointing at the trial's
 * copies) with the trial as the page's design. Send back records the tree
 * the page has now as a revision and gives the page its kept tree and its
 * Legacy design back; the kept file is then deleted.
 *
 * A run works as the bulk move to the Page Design Studio does
 * (legacy_to_designer_run.php): the cursor data/temp/legacy_to_bootstrap.json,
 * written after every page; the screen's short requests and the general
 * job's tick work it one at a time under GET_LOCK('pg_lbs_move', 0); a
 * finished run is kept in data/legacy_to_bootstrap/runs/. Only one move of
 * either kind runs at a time. A page held in the Page Design Studio or used
 * by an open experiment is skipped with its reason, as Back to classic does.
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

// Report lines kept per run; the rest are counted.
const PG_LBS_MOVE_LINES = 2000;

// ── Pure helpers ────────────────────────────────────────────────────────

/**
 * A new run's cursor.
 *
 * Pure: no query.
 *
 * @param string $direction  'move' (Legacy to trial) or 'back'
 * @param int    $legacy_id
 * @param int    $trial_id
 * @param array  $page_ids
 * @param array  $user       id, username
 * @param int    $now
 * @return array
 */
function pg_lbs_move_state_new($direction, $legacy_id, $trial_id, $page_ids, $user, $now)
{
    $pages = array();
    foreach ((array)$page_ids as $id) {
        if ((int)$id > 0 && !in_array((int)$id, $pages, true)) $pages[] = (int)$id;
    }
    return array(
        'id'        => date('Ymd-His', (int)$now) . '-' . substr(sha1($direction . $legacy_id . implode(',', $pages) . $now), 0, 8),
        'direction' => ($direction === 'back') ? 'back' : 'move',
        'legacy'    => (int)$legacy_id,
        'trial'     => (int)$trial_id,
        'names'     => array(),
        'pages'     => $pages,
        'index'     => 0,
        'user_id'   => (int)($user['id'] ?? 0),
        'username'  => (string)($user['username'] ?? ''),
        'started'   => (int)$now,
        'counts'    => array('moved' => 0, 'skipped' => 0, 'failed' => 0),
        'lines'     => array(),
        'more'      => 0,
        'origins'   => array(),
    );
}

/**
 * Whether every page of the run has been worked.
 *
 * Pure: no query.
 */
function pg_lbs_move_state_done($state)
{
    return (int)$state['index'] >= count((array)$state['pages']);
}

/**
 * How far a run is: pages worked, pages chosen.
 *
 * Pure: no query.
 *
 * @return array  'done', 'total'
 */
function pg_lbs_move_state_progress($state)
{
    $total = count((array)$state['pages']);
    return array('done' => min($total, (int)$state['index']), 'total' => $total);
}

/**
 * A line of the run's report, counted under its kind (moved, skipped,
 * failed). Lines past PG_LBS_MOVE_LINES are counted only.
 *
 * Pure: no query.
 */
function pg_lbs_move_state_line($state, $kind, $text)
{
    $state['counts'][$kind] = (int)($state['counts'][$kind] ?? 0) + 1;
    if (count($state['lines']) < PG_LBS_MOVE_LINES) {
        $state['lines'][] = array('kind' => (string)$kind, 'text' => (string)$text);
    } else {
        $state['more'] = (int)$state['more'] + 1;
    }
    return $state;
}

/**
 * Why a page cannot be moved or sent back, '' when it can.
 *
 * Pure: no query.
 *
 * @param array $page  'kept' (back: its Legacy tree is kept), 'legacy'
 *                     (back: its Legacy design is still one), 'experiment',
 *                     'held'
 * @param string $direction  'move' or 'back'
 * @return string  'no_origin', 'origin', 'experiment', 'held' or ''
 */
function pg_lbs_move_refusal($page, $direction)
{
    if ($direction === 'back') {
        if (empty($page['kept'])) return 'no_origin';
        if (empty($page['legacy'])) return 'origin';
    }
    if (!empty($page['experiment'])) return 'experiment';
    if (!empty($page['held'])) return 'held';
    return '';
}

/**
 * The sentence of a refusal.
 *
 * @param string $code  pg_lbs_move_refusal()
 * @return string
 */
function pg_lbs_move_refusal_text($code)
{
    switch ($code) {
        case 'no_origin':
            return lang('No move to the trial design kept its Legacy tree, so it stays.');
        case 'origin':
            return lang('Its Legacy design is gone or is no Legacy design now.');
        case 'experiment':
            return lang('An experiment uses it: end the experiment first.');
        case 'held':
            return lang('It is being edited in the Page Design Studio.');
    }
    return '';
}

/**
 * The shared parts a tree places that the trial design has no copy of
 * (a part placed after the trial was made): they keep their place.
 *
 * Pure: no query.
 *
 * @param array $tree
 * @param array $map   shared part id => its copy's id
 * @return array  part ids
 */
function pg_lbs_move_unmapped_parts($tree, $map)
{
    $out = array();
    foreach (pg_ltd_shared_ids($tree) as $sid) {
        if (!isset($map[$sid])) $out[] = $sid;
    }
    return $out;
}

// ── Files: cursor, kept runs, kept trees ────────────────────────────────

function pg_lbs_move_state_path()
{
    return PG_FUNCTIONS_DIR . '/data/temp/legacy_to_bootstrap.json';
}

/**
 * The running move to or from a trial design, null when there is none.
 */
function pg_lbs_move_state_read()
{
    $path = pg_lbs_move_state_path();
    if (!is_file($path)) return null;
    $state = json_decode((string)@file_get_contents($path), true);
    return (is_array($state) && isset($state['pages'], $state['index'], $state['direction'])) ? $state : null;
}

function pg_lbs_move_state_write($state)
{
    $path = pg_lbs_move_state_path();
    if (!is_dir(dirname($path))) @mkdir(dirname($path), 0755, true);
    // Written beside it and renamed: a killed request never leaves half a file.
    if (@file_put_contents($path . '.tmp', json_encode($state)) !== false) {
        @rename($path . '.tmp', $path);
    }
}

/**
 * Whether a move of either kind runs: to the Page Design Studio
 * (legacy_to_designer_run.php) or to and from a trial design.
 *
 * @return bool
 */
function pg_lbs_moves_running()
{
    return is_file(pg_ltd_state_path()) || is_file(pg_lbs_move_state_path());
}

function pg_lbs_move_runs_dir()
{
    return PG_FUNCTIONS_DIR . '/data/legacy_to_bootstrap/runs';
}

/**
 * A finished run kept as data/legacy_to_bootstrap/runs/<run id>.json
 * (outside data/temp, which the clean up tool empties).
 */
function pg_lbs_move_report_keep($state)
{
    $dir = pg_lbs_move_runs_dir();
    if (!is_dir($dir)) @mkdir($dir, 0755, true);
    $id = preg_replace('/[^0-9a-z-]/', '', (string)$state['id']);
    if ($id === '') return false;
    $state['finished'] = time();
    return @file_put_contents($dir . '/' . $id . '.json', json_encode($state)) !== false;
}

/**
 * The kept runs of one Legacy design (0 for all), newest first.
 *
 * @param int $legacy_id
 * @param int $limit
 * @return array  run states with 'finished'
 */
function pg_lbs_move_reports($legacy_id = 0, $limit = 5)
{
    $files = glob(pg_lbs_move_runs_dir() . '/*.json');
    if (!$files) return array();
    rsort($files);
    $out = array();
    foreach ($files as $file) {
        $run = json_decode((string)@file_get_contents($file), true);
        if (!is_array($run) || !isset($run['id'], $run['counts'])) continue;
        if ((int)$legacy_id > 0 && (int)($run['legacy'] ?? 0) !== (int)$legacy_id) continue;
        $out[] = $run;
        if (count($out) >= max(1, (int)$limit)) break;
    }
    return $out;
}

function pg_lbs_move_kept_path($page_id)
{
    return PG_FUNCTIONS_DIR . '/data/legacy_to_bootstrap/pages/' . (int)$page_id . '.json';
}

/**
 * A moved page's kept Legacy tree: 'legacy' (design id), 'trial', 'tree'
 * (the page_tree_json it had, '' for none), 'moved', 'run'. Null when none.
 */
function pg_lbs_move_kept_read($page_id)
{
    $path = pg_lbs_move_kept_path($page_id);
    if (!is_file($path)) return null;
    $kept = json_decode((string)@file_get_contents($path), true);
    return (is_array($kept) && (int)($kept['legacy'] ?? 0) > 0 && isset($kept['tree'])) ? $kept : null;
}

function pg_lbs_move_kept_write($page_id, $kept)
{
    $path = pg_lbs_move_kept_path($page_id);
    if (!is_dir(dirname($path))) @mkdir(dirname($path), 0755, true);
    if (@file_put_contents($path . '.tmp', json_encode($kept)) === false) return false;
    return @rename($path . '.tmp', $path);
}

// ── The pages ───────────────────────────────────────────────────────────

/**
 * The pages of a trial design that came from its Legacy design, by name,
 * each with what keeps it there (pg_lbs_move_refusal(), 'back').
 *
 * @param int $legacy_id
 * @param int $trial_id
 * @return array  list of: id, name, refusal
 */
function pg_lbs_moved_pages($legacy_id, $trial_id)
{
    $rows = db_items("SELECT page_id, page_name FROM page WHERE page_style = '" . (int)$trial_id . "'
                      ORDER BY page_name ASC, page_id ASC");
    $out = array();
    foreach ((is_array($rows) ? $rows : array()) as $r) {
        $kept = pg_lbs_move_kept_read((int)$r['page_id']);
        if ($kept === null || (int)$kept['legacy'] !== (int)$legacy_id) continue;
        $out[] = array('id' => (int)$r['page_id'], 'name' => (string)$r['page_name']);
    }
    $states = pg_lbs_move_page_states(array_column($out, 'id'));
    $legacy = pg_lbs_move_is_legacy($legacy_id);
    foreach ($out as $i => $p) {
        $out[$i]['refusal'] = pg_lbs_move_refusal(array('kept' => true, 'legacy' => $legacy) + $states[$p['id']], 'back');
    }
    return $out;
}

/**
 * Whether a design is a Legacy design.
 */
function pg_lbs_move_is_legacy($style_id)
{
    return (string)db_value("SELECT style_framework FROM style WHERE style_id = '" . (int)$style_id . "' LIMIT 1") === 'legacy';
}

/**
 * Of each page: 'experiment' (an open experiment uses it) and 'held' (it is
 * open in the Page Design Studio).
 *
 * @param array $page_ids
 * @return array  page id => array('experiment' => bool, 'held' => bool)
 */
function pg_lbs_move_page_states($page_ids)
{
    require_once(PG_FUNCTIONS_DIR . '/includes/designer_collab.php');
    $experiments = array();
    if ($page_ids && file_exists(PG_FUNCTIONS_DIR . '/includes/fn/experiments_sheet.php')) {
        require_once(PG_FUNCTIONS_DIR . '/includes/fn/experiments_sheet.php');
        $experiments = pg_experiment_page_states($page_ids);
    }
    $out = array();
    foreach ((array)$page_ids as $id) {
        $out[(int)$id] = array('experiment' => isset($experiments[(int)$id]), 'held' => !pg_collab_may_edit_page('', (int)$id));
    }
    return $out;
}

// ── The run ─────────────────────────────────────────────────────────────

/**
 * Starts moving a Legacy design's pages to its trial design ('move', its
 * pages out of the recycle bin), or the trial's pages that came from it back
 * ('back'): every page, or the chosen ones. Refused while a move of either
 * kind runs.
 *
 * @param string     $direction  'move' or 'back'
 * @param int        $legacy_id
 * @param array|null $page_ids   null for every page
 * @param array      $user       id, role, username
 * @return string  '' when started, else the reason
 */
function pg_lbs_move_start($direction, $legacy_id, $page_ids, $user)
{
    if ((int)$user['role'] > 1) return lang('Only a designer can move pages between designs.');
    if (pg_lbs_moves_running()) return lang('A move is already under way.');
    $legacy_id = (int)$legacy_id;
    $trial = pg_lbs_trial_of($legacy_id);
    if ($trial === null) return lang('This design has no trial design.');

    $have = ($direction === 'back')
        ? array_column(pg_lbs_moved_pages($legacy_id, $trial['trial']), 'id')
        : array_column(pg_lbs_trial_pages($legacy_id), 'id');
    $want = ($page_ids === null) ? $have : array_values(array_intersect($have, array_map('intval', (array)$page_ids)));
    if (!$want) return ($direction === 'back') ? lang('No page of the trial design came from this design.') : lang('Choose the pages to move first.');

    $state = pg_lbs_move_state_new($direction, $legacy_id, $trial['trial'], $want, $user, time());
    foreach (array($legacy_id, (int)$trial['trial']) as $id) {
        $state['names'][(string)$id] = (string)db_value("SELECT style_name FROM style WHERE style_id = '$id' LIMIT 1");
    }
    pg_lbs_move_state_write($state);
    if (!is_file(pg_lbs_move_state_path())) return lang('The move could not be started: data/temp is not writable.');
    log_activity(lang(array('string' => 'the move of {var:1} pages between the design ({var:2}) and its trial design was started',
        'vars' => array(count($want), $state['names'][(string)$legacy_id]))), (string)($user['username'] ?? ''));
    return '';
}

/**
 * Works the running move for about $budget seconds, one page at a time.
 *
 * @return array  'status' ('none', 'busy', 'running', 'done', 'failed'),
 *                'progress' (pg_lbs_move_state_progress()), 'counts'
 */
function pg_lbs_move_run($budget)
{
    $answer = array('status' => 'none', 'progress' => null, 'counts' => array());
    if ((int)db_value("SELECT GET_LOCK('pg_lbs_move', 0)") !== 1) {
        $answer['status'] = 'busy';
        return $answer;
    }
    $state = pg_lbs_move_state_read();
    if ($state === null) {
        db("SELECT RELEASE_LOCK('pg_lbs_move')");
        return $answer;
    }

    // The run works as the designer who started it, whose role is read
    // again: one demoted meanwhile ends it.
    $user = db_item("SELECT user_id AS id, user_username AS username, user_role AS role FROM user WHERE user_id = '" . (int)$state['user_id'] . "' LIMIT 1");
    $record = pg_lbs_trial_of($state['legacy']);
    if (!is_array($user) || (int)$user['role'] > 1) {
        $state = pg_lbs_move_state_line($state, 'failed', lang('The move stopped: the designer who started it can no longer move pages between designs.'));
        $state['index'] = count($state['pages']);
    } elseif ($record === null || (int)$record['trial'] !== (int)$state['trial']) {
        $state = pg_lbs_move_state_line($state, 'failed', lang('The move stopped: the trial design is gone.'));
        $state['index'] = count($state['pages']);
    }

    $stop = microtime(true) + max(1, (int)$budget);
    $stepped = false;
    while (!pg_lbs_move_state_done($state) && microtime(true) < $stop) {
        $page_id = (int)$state['pages'][(int)$state['index']];
        $state = ($state['direction'] === 'back')
            ? pg_lbs_move_back_page($state, $page_id, $user)
            : pg_lbs_move_page($state, $page_id, $record, $user);
        $state['index'] = (int)$state['index'] + 1;
        pg_lbs_move_state_write($state);
        $stepped = true;
    }

    // A moved page is drawn anew; the general job works the run outside a
    // signed-in request, so the file cache is told here.
    if ($stepped && function_exists('pg_page_cache_mark_dirty')) {
        pg_page_cache_mark_dirty();
    }

    if (pg_lbs_move_state_done($state)) {
        pg_lbs_move_report_keep($state);
        @unlink(pg_lbs_move_state_path());
        $c = $state['counts'];
        log_activity(lang(array('string' => 'the move between the design ({var:1}) and its trial design ended: {var:2} pages moved, {var:3} skipped, {var:4} failed',
            'vars' => array((string)($state['names'][(string)$state['legacy']] ?? ''), $c['moved'], $c['skipped'], $c['failed']))), (string)$state['username']);
        $answer['status'] = $c['failed'] > 0 ? 'failed' : 'done';
    } else {
        $answer['status'] = 'running';
    }
    db("SELECT RELEASE_LOCK('pg_lbs_move')");
    $answer['progress'] = pg_lbs_move_state_progress($state);
    $answer['counts'] = $state['counts'];
    return $answer;
}

/**
 * The general job's share: a running move is worked for the tick's budget,
 * so it carries on with the screen closed.
 */
function pg_lbs_move_job_tick($budget)
{
    if (!is_file(pg_lbs_move_state_path())) return null;
    return pg_lbs_move_run($budget);
}

/**
 * Writes a page's tree, its drawn body and its design, recording the tree
 * it had as its newest revision first and the new one after.
 *
 * @param array  $row        page_id, page_name, page_tree_json, page_tree_code
 * @param int    $from       the design it leaves
 * @param int    $to         the design it gets
 * @param string $tree_json  '' for a page drawn from its design's tree
 * @param int    $user_id
 * @return bool  whether the row was written
 */
function pg_lbs_move_write_page($row, $from, $to, $tree_json, $user_id)
{
    $pid = (int)$row['page_id'];
    $from = (int)$from;
    $to = (int)$to;
    $tree = ($tree_json !== '') ? json_decode($tree_json, true) : null;
    $code = '';
    if (is_array($tree)) {
        $abc = (string)db_value("SELECT additional_body_classes FROM style WHERE style_id = '$to' LIMIT 1");
        $code = generate_style_code_from_tree($tree, (string)$row['page_name'], $abc);
    }
    if ((string)$row['page_tree_json'] !== '') {
        pg_page_revision_record($pid, $from, (string)$row['page_tree_json'], (string)$row['page_tree_code'], $user_id);
    }
    $set = is_array($tree) ? "page_tree_json = '" . e($tree_json) . "', page_tree_code = '" . e($code) . "'" : 'page_tree_json = NULL, page_tree_code = NULL';
    db("UPDATE page SET page_style = '$to', $set, page_timestamp = UNIX_TIMESTAMP(), page_user = '" . (int)$user_id . "'
        WHERE page_id = '$pid' AND page_style = '$from' LIMIT 1");
    if (mysqli_affected_rows(db::$con) <= 0) return false;
    if (is_array($tree)) pg_page_revision_record($pid, $to, $tree_json, $code, $user_id);
    return true;
}

/**
 * One page moved from the Legacy design to its trial: its tree kept, then
 * converted with its parts' places pointing at the trial's copies.
 *
 * @param array $state
 * @param int   $page_id
 * @param array $record   pg_lbs_trial_of()
 * @param array $user
 * @return array  the state with the page's line
 */
function pg_lbs_move_page($state, $page_id, $record, $user)
{
    $legacy = (int)$state['legacy'];
    $row = db_item("SELECT page_id, page_name, page_tree_json, page_tree_code FROM page
                    WHERE page_id = '" . (int)$page_id . "' AND page_style = '$legacy' LIMIT 1");
    if (!is_array($row)) {
        return pg_lbs_move_state_line($state, 'skipped', lang(array('string' => 'Page {var:1}: it is no longer on the Legacy design.', 'vars' => array((int)$page_id))));
    }
    $name = (string)$row['page_name'];
    $refusal = pg_lbs_move_refusal(pg_lbs_move_page_states(array((int)$page_id))[(int)$page_id], 'move');
    if ($refusal !== '') return pg_lbs_move_state_line($state, 'skipped', $name . ': ' . pg_lbs_move_refusal_text($refusal));

    $json = '';
    $old = (string)$row['page_tree_json'];
    $tree = ($old !== '') ? json_decode($old, true) : null;
    if ($old !== '' && !is_array($tree)) {
        return pg_lbs_move_state_line($state, 'failed', $name . ': ' . lang('Its tree could not be read.'));
    }
    $unmapped = array();
    if (is_array($tree)) {
        $unmapped = pg_lbs_move_unmapped_parts($tree, $record['map']);
        $converted = pg_lbs_tree($tree);
        $json = pg_designer_tree_encode(pg_lbs_trial_refs($converted['tree'], $record['map']));
    }
    $kept = array('legacy' => $legacy, 'trial' => (int)$state['trial'], 'tree' => $old, 'moved' => time(), 'run' => (string)$state['id']);
    if (!pg_lbs_move_kept_write((int)$page_id, $kept)) {
        return pg_lbs_move_state_line($state, 'failed', $name . ': ' . lang('Its Legacy tree could not be kept in data/legacy_to_bootstrap: the page was not moved.'));
    }
    if (!pg_lbs_move_write_page($row, $legacy, (int)$state['trial'], $json, (int)$user['id'])) {
        @unlink(pg_lbs_move_kept_path((int)$page_id));
        return pg_lbs_move_state_line($state, 'failed', $name . ': ' . lang('The page could not be saved.'));
    }
    $state['origins'][(string)(int)$page_id] = $legacy;
    $state = pg_lbs_move_state_line($state, 'moved', $name);
    if ($unmapped && count($state['lines']) < PG_LBS_MOVE_LINES) {
        $state['lines'][] = array('kind' => 'note', 'text' => lang(array('string' => '{var:1}: {var:2} shared parts placed after the trial design was made stay as they are.',
            'vars' => array($name, count($unmapped)))));
    }
    return $state;
}

/**
 * One page of the trial design sent back: its kept Legacy tree and its
 * Legacy design given back, the kept file deleted.
 */
function pg_lbs_move_back_page($state, $page_id, $user)
{
    $trial = (int)$state['trial'];
    $row = db_item("SELECT page_id, page_name, page_tree_json, page_tree_code FROM page
                    WHERE page_id = '" . (int)$page_id . "' AND page_style = '$trial' LIMIT 1");
    if (!is_array($row)) {
        return pg_lbs_move_state_line($state, 'skipped', lang(array('string' => 'Page {var:1}: it is no longer on the trial design.', 'vars' => array((int)$page_id))));
    }
    $name = (string)$row['page_name'];
    $kept = pg_lbs_move_kept_read((int)$page_id);
    $legacy = (int)$state['legacy'];
    $page = array('kept' => $kept !== null && (int)$kept['legacy'] === $legacy, 'legacy' => pg_lbs_move_is_legacy($legacy))
        + pg_lbs_move_page_states(array((int)$page_id))[(int)$page_id];
    $refusal = pg_lbs_move_refusal($page, 'back');
    if ($refusal !== '') return pg_lbs_move_state_line($state, 'skipped', $name . ': ' . pg_lbs_move_refusal_text($refusal));

    if (!pg_lbs_move_write_page($row, $trial, $legacy, (string)$kept['tree'], (int)$user['id'])) {
        return pg_lbs_move_state_line($state, 'failed', $name . ': ' . lang('The page could not be saved.'));
    }
    @unlink(pg_lbs_move_kept_path((int)$page_id));
    $state['origins'][(string)(int)$page_id] = $legacy;
    return pg_lbs_move_state_line($state, 'moved', $name);
}
