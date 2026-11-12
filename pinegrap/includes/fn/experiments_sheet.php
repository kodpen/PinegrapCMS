<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * The Experiments sheet of a designed page (page_experiments.php): set up an
 * experiment with a variant draft, start, pause and end it, publish the
 * winner, and the holds that keep a variant draft from being published or
 * binned while its experiment is open.
 *
 * The model, the draw and the bucket cookie are in experiments.php. A
 * variant is a draft page made as a copy of the live page in the Drafts
 * folder, edited with the designer as any draft. Publishing the winner saves
 * the variant's tree into the live page through pg_designer_save_page(), so
 * the page lock, the content gate and a revision apply as to any save; the
 * experiment then ends and the draft is a plain draft again.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_FUNCTIONS_DIR')) {
    exit;
}

/**
 * The goal kinds an experiment counts (experiments.goal_kind).
 *
 * @return array
 */
function pg_experiment_goal_kinds()
{
    return array('form', 'order', 'click', 'page');
}

/**
 * The Experiments sheet's form cleaned: name (1-100 characters), goal kind,
 * goal target (a click goal's name of letters, digits, - and _; a page
 * goal's page id; empty for the others) and the variant's weight (1-99, the
 * live page takes the rest). Pure.
 *
 * @param array $in name, goal_kind, goal_target, weight_b
 * @return array errors (codes: name, goal, target, weight), values
 */
function pg_experiment_sheet_clean(array $in)
{
    $errors = array();

    $name = trim(isset($in['name']) && is_string($in['name']) ? $in['name'] : '');
    $name_length = function_exists('mb_strlen') ? mb_strlen($name, 'UTF-8') : strlen($name);

    if (($name === '') || ($name_length > 100)) {
        $errors[] = 'name';
    }

    $kind = isset($in['goal_kind']) && is_string($in['goal_kind']) ? $in['goal_kind'] : '';

    if (!in_array($kind, pg_experiment_goal_kinds(), true)) {
        $errors[] = 'goal';
        $kind = '';
    }

    $target = trim(isset($in['goal_target']) && is_scalar($in['goal_target']) ? (string) $in['goal_target'] : '');

    if ($kind === 'click') {
        if (preg_match('/^[A-Za-z0-9_-]{1,64}$/', $target) !== 1) {
            $errors[] = 'target';
        }
    } elseif ($kind === 'page') {
        if (preg_match('/^[1-9][0-9]{0,9}$/', $target) !== 1) {
            $errors[] = 'target';
        }
    } else {
        $target = '';
    }

    $weight = isset($in['weight_b']) ? $in['weight_b'] : '';

    if (!is_numeric($weight) || ((int) $weight != $weight) || ((int) $weight < 1) || ((int) $weight > 99)) {
        $errors[] = 'weight';
        $weight = 50;
    }

    return array(
        'errors' => $errors,
        'values' => array(
            'name' => $name,
            'goal_kind' => $kind,
            'goal_target' => $target,
            'weight_b' => (int) $weight,
        ),
    );
}

/**
 * The two weights from the variant's share: the live page (a) takes the
 * rest of 100. The share is held inside 1-99. Pure.
 *
 * @param int $weight_b
 * @return array a, b
 */
function pg_experiment_sheet_weights($weight_b)
{
    $b = max(1, min(99, (int) $weight_b));

    return array('a' => 100 - $b, 'b' => $b);
}

/**
 * The buttons the sheet offers for an experiment in a status. The winner is
 * published from a running or paused one; a draft is deleted, never ended.
 * Pure.
 *
 * @param string $status
 * @return array
 */
function pg_experiment_sheet_actions($status)
{
    $actions = array(
        'draft' => array('start', 'delete'),
        'running' => array('pause', 'end', 'publish_winner'),
        'paused' => array('start', 'end', 'publish_winner'),
    );

    return isset($actions[$status]) ? $actions[$status] : array();
}

/**
 * The status a sheet action moves an experiment to, '' for an action that
 * is not a move. Pure.
 *
 * @param string $action
 * @return string
 */
function pg_experiment_sheet_action_status($action)
{
    $to = array('start' => 'running', 'pause' => 'paused', 'end' => 'ended');

    return isset($to[$action]) ? $to[$action] : '';
}

/**
 * Whether an experiment in a status holds its variant drafts: from set-up
 * until it ends, a variant can be neither published nor binned. Pure.
 *
 * @param string $status
 * @return bool
 */
function pg_experiment_variant_held($status)
{
    return in_array((string) $status, array('draft', 'running', 'paused'), true);
}

/**
 * The chip's theme for an experiment's status; '' draws no chip. Pure.
 *
 * @param string $status
 * @return string
 */
function pg_experiment_chip_theme($status)
{
    $themes = array('running' => 'success', 'paused' => 'warning', 'draft' => 'secondary');

    return isset($themes[$status]) ? $themes[$status] : '';
}

/**
 * Whether the experiment tables exist (the 2026.11.0 upgrade, 11.0.153).
 *
 * @return bool
 */
function pg_experiment_sheet_ready()
{
    return function_exists('pg_schema_has') && pg_schema_has('experiments') && pg_schema_has('experiment_variants');
}

/**
 * The message for a sheet form error code.
 *
 * @param string $code pg_experiment_sheet_clean() error
 * @return string
 */
function pg_experiment_sheet_error_message($code)
{
    switch ($code) {
        case 'name':
            return lang('Give the experiment a name of up to 100 characters.');
        case 'goal':
            return lang('Choose what counts as a conversion.');
        case 'target':
            return lang('A click goal needs a goal name of letters, digits, - and _; a page goal needs a page.');
        default:
            return lang('The variant\'s share of visitors is a whole number from 1 to 99.');
    }
}

/**
 * A designed page an experiment can run on: on the site (not a draft), not
 * in the Recycle Bin.
 *
 * @param int $page_id
 * @return array|null page_id, page_name, page_folder, page_style
 */
function pg_experiment_sheet_live_page($page_id)
{
    $page_id = (int) $page_id;

    if ($page_id <= 0) {
        return null;
    }

    $page = db_item(
        "SELECT page_id, page_name, page_folder, page_style FROM page
        WHERE page_id = '$page_id' AND " . pg_page_visual_design_sql() . pg_designer_not_binned_sql() . "
        LIMIT 1");

    if (!is_array($page) || pg_page_is_draft($page['page_folder'])) {
        return null;
    }

    return $page;
}

/**
 * A page a "page" goal can point at: on the site (not a draft), not in the
 * Recycle Bin; any page type, not only designed ones.
 *
 * @param int|string $page_id
 * @return array|null page_id, page_name
 */
function pg_experiment_goal_page($page_id)
{
    $page_id = (int) $page_id;

    if ($page_id <= 0) {
        return null;
    }

    $page = db_item("SELECT page_id, page_name, page_folder FROM page WHERE page_id = '$page_id'" . pg_designer_not_binned_sql() . " LIMIT 1");

    if (!is_array($page) || pg_page_is_draft($page['page_folder'])) {
        return null;
    }

    return $page;
}

/**
 * An experiment's goal as shown: the goal kind's label and, for a click, the
 * goal name; for a page, that page's name while it is on the site, else its
 * id.
 *
 * @param array $experiment goal_kind, goal_target
 * @param array $goal_labels goal kind => label
 * @return string not escaped
 */
function pg_experiment_goal_text(array $experiment, array $goal_labels)
{
    $kind = (string) ($experiment['goal_kind'] ?? '');
    $target = (string) ($experiment['goal_target'] ?? '');
    $goal = isset($goal_labels[$kind]) ? $goal_labels[$kind] : '';

    if ($target === '') {
        return $goal;
    }

    if ($kind === 'page') {
        $page = pg_experiment_goal_page($target);

        if ($page) {
            $target = $page['page_name'];
        }
    }

    return $goal . ' (' . $target . ')';
}

/**
 * The open experiment (set up, running or paused) a draft page is a variant
 * of, with its live page; null when it is not held. Such a draft is neither
 * published nor binned until the experiment ends.
 *
 * @param int $page_id
 * @return array|null experiment_id, status, live_page_id, live_page_name
 */
function pg_experiment_variant_hold($page_id)
{
    $page_id = (int) $page_id;

    if (($page_id <= 0) || !pg_experiment_sheet_ready()) {
        return null;
    }

    $row = db_item(
        "SELECT experiments.id AS experiment_id, experiments.status, experiments.page_id AS live_page_id,
            page.page_name AS live_page_name
        FROM experiment_variants
        JOIN experiments ON experiments.id = experiment_variants.experiment_id
        LEFT JOIN page ON page.page_id = experiments.page_id
        WHERE experiment_variants.draft_page_id = '$page_id' AND experiments.status <> 'ended'
        ORDER BY experiments.id
        LIMIT 1");

    if (!is_array($row) || !pg_experiment_variant_held($row['status'])) {
        return null;
    }

    $row['experiment_id'] = (int) $row['experiment_id'];
    $row['live_page_id'] = (int) $row['live_page_id'];
    $row['live_page_name'] = (string) $row['live_page_name'];

    return $row;
}

/**
 * The refusal for publishing or binning a held variant draft, '' when the
 * page is not held.
 *
 * @param int    $page_id
 * @param string $page_name
 * @return string
 */
function pg_experiment_variant_hold_message($page_id, $page_name)
{
    $hold = pg_experiment_variant_hold($page_id);

    if (!$hold) {
        return '';
    }

    return lang(array(
        'string' => 'The page "{var:1}" is a variant of "{var:2}" in an experiment, so it stays a draft until the experiment ends.',
        'vars' => array((string) $page_name, $hold['live_page_name'])));
}

/**
 * The experiment states of a list of pages, for a status chip: a live page
 * with an open experiment, and a variant draft of one.
 *
 * @param array $page_ids
 * @return array page id => array(role 'live'|'variant', status, live_page_id, live_page_name)
 */
function pg_experiment_page_states($page_ids)
{
    $ids = array_values(array_unique(array_filter(array_map('intval', (array) $page_ids))));
    $out = array();

    if (!$ids || !pg_experiment_sheet_ready()) {
        return $out;
    }

    $list = implode(',', $ids);

    $rows = db_items(
        "SELECT experiments.page_id, experiments.status, experiment_variants.draft_page_id, page.page_name
        FROM experiments
        JOIN experiment_variants ON experiment_variants.experiment_id = experiments.id
        LEFT JOIN page ON page.page_id = experiments.page_id
        WHERE experiments.status <> 'ended'
            AND (experiments.page_id IN ($list) OR experiment_variants.draft_page_id IN ($list))
        ORDER BY experiments.id DESC");

    foreach ((array) $rows as $row) {
        $live = (int) $row['page_id'];
        $draft = (int) $row['draft_page_id'];

        if (in_array($live, $ids, true)) {
            $out[$live] = array('role' => 'live', 'status' => $row['status'], 'live_page_id' => $live, 'live_page_name' => (string) $row['page_name']);
        }

        if (($draft > 0) && in_array($draft, $ids, true)) {
            $out[$draft] = array('role' => 'variant', 'status' => $row['status'], 'live_page_id' => $live, 'live_page_name' => (string) $row['page_name']);
        }
    }

    return $out;
}

/**
 * The chip for a page's experiment state: "Experiment running" (paused, set
 * up) on the live page, "Variant of <page>" on a variant draft; '' for none.
 *
 * @param array|null $state pg_experiment_page_states() entry
 * @return string HTML
 */
function pg_experiment_chip_html($state)
{
    if (!is_array($state) || (pg_experiment_chip_theme($state['status']) === '')) {
        return '';
    }

    $labels = array(
        'running' => lang('Experiment running'),
        'paused' => lang('Experiment paused'),
        'draft' => lang('Experiment set up'),
    );

    $text = ($state['role'] === 'variant')
        ? lang(array('string' => 'Variant of {var:1}', 'vars' => $state['live_page_name']))
        : $labels[$state['status']];

    return ' <span class="badge theme-' . pg_experiment_chip_theme($state['status']) . ' pg-experiment-chip" title="' . h($labels[$state['status']]) . '">'
        . '<i class="bi bi-signpost-split me-1" aria-hidden="true"></i>' . h($text) . '</span>';
}

/**
 * A page's experiments, newest first, with their variants and the views and
 * goals the hourly rollup holds for each variant.
 *
 * @param int $page_id
 * @return array
 */
function pg_experiment_sheet_list($page_id)
{
    $page_id = (int) $page_id;

    if (($page_id <= 0) || !pg_experiment_sheet_ready()) {
        return array();
    }

    $experiments = array();

    foreach ((array) db_items("SELECT * FROM experiments WHERE page_id = '$page_id' ORDER BY id DESC") as $row) {
        $row['variants'] = array();
        $experiments[(int) $row['id']] = $row;
    }

    if (!$experiments) {
        return array();
    }

    $ids = implode(',', array_keys($experiments));

    $variants = db_items(
        "SELECT experiment_variants.*, page.page_name, page.page_style
        FROM experiment_variants
        LEFT JOIN page ON page.page_id = experiment_variants.draft_page_id
        WHERE experiment_variants.experiment_id IN ($ids)
        ORDER BY experiment_variants.variant_key");

    $figures = array();

    if (pg_schema_has('experiment_hourly')) {
        $sums = db_items(
            "SELECT variant_id, SUM(views) AS views, SUM(goals) AS goals
            FROM experiment_hourly
            WHERE experiment_id IN ($ids)
            GROUP BY variant_id");

        foreach ((array) $sums as $sum) {
            $figures[(int) $sum['variant_id']] = array('views' => (int) $sum['views'], 'goals' => (int) $sum['goals']);
        }
    }

    foreach ((array) $variants as $variant) {
        $variant['views'] = isset($figures[(int) $variant['id']]) ? $figures[(int) $variant['id']]['views'] : 0;
        $variant['goals'] = isset($figures[(int) $variant['id']]) ? $figures[(int) $variant['id']]['goals'] : 0;
        $experiments[(int) $variant['experiment_id']]['variants'][$variant['variant_key']] = $variant;
    }

    return array_values($experiments);
}

/**
 * One experiment row by id, or null.
 *
 * @param int $id
 * @return array|null
 */
function pg_experiment_sheet_get($id)
{
    $id = (int) $id;

    if (($id <= 0) || !pg_experiment_sheet_ready()) {
        return null;
    }

    $row = db_item("SELECT * FROM experiments WHERE id = '$id' LIMIT 1");

    return is_array($row) ? $row : null;
}

/**
 * A draft page that may become an experiment's variant: a draft of the
 * same design as the live page, not the live page, not held by another
 * open experiment.
 *
 * @param array $live pg_experiment_sheet_live_page()
 * @return array draft pages (page_id, page_name)
 */
function pg_experiment_sheet_variant_choices($live)
{
    $folder = pg_page_draft_ready() ? (int) pg_page_draft_folder_id(false) : 0;

    if ($folder <= 0) {
        return array();
    }

    $choices = array();

    $rows = db_items(
        "SELECT page_id, page_name FROM page
        WHERE page_folder = '$folder' AND page_style = '" . (int) $live['page_style'] . "'
            AND page_id <> '" . (int) $live['page_id'] . "' AND " . pg_page_visual_design_sql() . "
        ORDER BY page_name");

    foreach ((array) $rows as $row) {
        if (!pg_experiment_variant_hold($row['page_id'])) {
            $choices[] = $row;
        }
    }

    return $choices;
}

/**
 * Sets up an experiment on a live page: the live page as variant a, a draft
 * as variant b (a new copy of the live page in the Drafts folder when none
 * is chosen), the goal and the weights. A page has one open experiment at a
 * time; it starts as a draft experiment.
 *
 * @param array $live    pg_experiment_sheet_live_page()
 * @param array $values  pg_experiment_sheet_clean() values
 * @param array $user    the signed-in user
 * @param int   $draft_page_id a chosen draft, 0 for a new copy
 * @return array ok, error, field (the form field the error belongs to),
 *               id, draft_page_id
 */
function pg_experiment_sheet_create($live, $values, $user, $draft_page_id = 0)
{
    $fail = function ($error, $field = 'name') {
        return array('ok' => false, 'error' => $error, 'field' => $field, 'id' => 0, 'draft_page_id' => 0);
    };

    $live_id = (int) $live['page_id'];

    if (db_value("SELECT id FROM experiments WHERE page_id = '$live_id' AND status <> 'ended' LIMIT 1")) {
        return $fail(lang('This page already has an experiment; end it before setting up another.'));
    }

    // A draft or a binned page is never reached by a visitor, so it is no goal.
    if (($values['goal_kind'] === 'page') && !pg_experiment_goal_page($values['goal_target'])) {
        return $fail(lang('The goal page was not found.'), 'goal_target');
    }

    $draft_page_id = (int) $draft_page_id;

    if ($draft_page_id > 0) {
        $allowed = false;

        foreach (pg_experiment_sheet_variant_choices($live) as $choice) {
            $allowed = $allowed || ((int) $choice['page_id'] === $draft_page_id);
        }

        if (!$allowed) {
            return $fail(lang('That draft cannot be a variant of this page.'), 'draft_page_id');
        }
    } else {
        $folder = pg_page_draft_ready() ? (int) pg_page_draft_folder_id(true) : 0;

        if ($folder <= 0) {
            return $fail(lang('Drafts are not available yet. Please run the upgrade.'));
        }

        require_once(PG_FUNCTIONS_DIR . '/duplicate_page_f.php');

        $copy = duplicate_page(array('page' => array('id' => $live_id), 'folder' => array('id' => $folder)));

        if (!isset($copy['status']) || ($copy['status'] !== 'success') || empty($copy['page']['id'])) {
            return $fail(isset($copy['message']) ? $copy['message'] : lang('The variant draft could not be made.'));
        }

        $draft_page_id = (int) $copy['page']['id'];

        // The copy goes back to the live page's folder if it is ever
        // published as a page of its own.
        db("INSERT IGNORE INTO page_drafts (page_id, folder_id, drafted_at, drafted_by)
            VALUES ('$draft_page_id', '" . (int) $live['page_folder'] . "', UNIX_TIMESTAMP(), '" . (int) $user['id'] . "')");
    }

    $weights = pg_experiment_sheet_weights($values['weight_b']);
    $now = time();

    $ok = db_q(
        "INSERT INTO experiments (page_id, name, status, goal_kind, goal_target, created_user_id, created_at, updated_at)
        VALUES (?, ?, 'draft', ?, ?, ?, ?, ?)",
        array($live_id, $values['name'], $values['goal_kind'], $values['goal_target'], (int) $user['id'], $now, $now));

    $id = $ok ? (int) mysqli_insert_id(db::$con) : 0;

    if ($id <= 0) {
        return $fail(lang('The experiment could not be saved.'));
    }

    db("INSERT INTO experiment_variants (experiment_id, draft_page_id, variant_key, weight) VALUES
        ('$id', 0, 'a', '" . $weights['a'] . "'),
        ('$id', '$draft_page_id', 'b', '" . $weights['b'] . "')");

    return array('ok' => true, 'error' => '', 'field' => '', 'id' => $id, 'draft_page_id' => $draft_page_id);
}

/**
 * The checks of a status move, before anything is written: the move is one
 * pg_experiment_may_move() allows, and a start finds the live page still on
 * the site, every variant still a draft and no other experiment running on
 * the page. The panel's move and the API's rehearsal meet the same refusals.
 *
 * @param array  $experiment pg_experiment_sheet_get()
 * @param string $to
 * @return array ok, error
 */
function pg_experiment_sheet_move_check($experiment, $to)
{
    if (!pg_experiment_may_move($experiment['status'], $to)) {
        return array('ok' => false, 'error' => lang('The experiment cannot make that change now.'));
    }

    $id = (int) $experiment['id'];

    if ($to === 'running') {
        if (!pg_experiment_sheet_live_page($experiment['page_id'])) {
            return array('ok' => false, 'error' => lang('The page is not on the site, so the experiment cannot run.'));
        }

        $drafts = db_values("SELECT draft_page_id FROM experiment_variants WHERE experiment_id = '$id' AND draft_page_id > 0");

        foreach ((array) $drafts as $draft) {
            $folder = db_value("SELECT page_folder FROM page WHERE page_id = '" . (int) $draft . "' LIMIT 1");

            if (($folder === null) || !pg_page_is_draft($folder)) {
                return array('ok' => false, 'error' => lang('A variant of this experiment is no longer a draft.'));
            }
        }

        if (db_value("SELECT id FROM experiments WHERE page_id = '" . (int) $experiment['page_id'] . "' AND status = 'running' AND id <> '$id' LIMIT 1")) {
            return array('ok' => false, 'error' => lang('Another experiment is running on this page.'));
        }
    }

    return array('ok' => true, 'error' => '');
}

/**
 * Moves an experiment to another status after pg_experiment_sheet_move_check();
 * the first start stamps started_at, an end ended_at.
 *
 * @param array  $experiment pg_experiment_sheet_get()
 * @param string $to
 * @param int    $winner_variant_id stamped on an end, 0 for none
 * @return array ok, error
 */
function pg_experiment_sheet_move($experiment, $to, $winner_variant_id = 0)
{
    $checked = pg_experiment_sheet_move_check($experiment, $to);

    if (!$checked['ok']) {
        return $checked;
    }

    $id = (int) $experiment['id'];

    $set = "status = '" . e($to) . "', updated_at = UNIX_TIMESTAMP()";

    if (($to === 'running') && ((int) $experiment['started_at'] === 0)) {
        $set .= ', started_at = UNIX_TIMESTAMP()';
    }

    if ($to === 'ended') {
        $set .= ", ended_at = UNIX_TIMESTAMP(), winner_variant_id = '" . (int) $winner_variant_id . "'";
    }

    // The status in the WHERE keeps two clicks from moving it twice.
    db("UPDATE experiments SET $set WHERE id = '$id' AND status = '" . e($experiment['status']) . "'");

    if (mysqli_affected_rows(db::$con) < 1) {
        return array('ok' => false, 'error' => lang('The experiment was changed meanwhile; look again.'));
    }

    // Every stored copy of the page names the experiment's weights: a move
    // makes them old, from the panel and from the API alike.
    if (function_exists('pg_page_cache_mark_dirty')) {
        pg_page_cache_mark_dirty();
    }

    $event = pg_experiment_move_event($experiment['status'], $to);

    if (($event !== '') && function_exists('pg_announce')) {
        pg_announce($event, array(
            'id' => $id,
            'page_id' => (int) $experiment['page_id'],
            'name' => (string) $experiment['name'],
            'goal_kind' => (string) $experiment['goal_kind'],
            'winner_variant_id' => ($to === 'ended') ? (int) $winner_variant_id : 0,
        ));
    }

    return array('ok' => true, 'error' => '');
}

/**
 * The event a status move announces: experiment.started for the first start
 * (from draft), experiment.ended for an end; '' for a pause or a resume.
 * Pure.
 *
 * @param string $from
 * @param string $to
 * @return string
 */
function pg_experiment_move_event($from, $to)
{
    if (((string) $from === 'draft') && ((string) $to === 'running')) {
        return 'experiment.started';
    }

    return ((string) $to === 'ended') ? 'experiment.ended' : '';
}

/**
 * Deletes an experiment that never ran, with its variants. The variant
 * draft stays as a plain draft.
 *
 * @param array $experiment pg_experiment_sheet_get()
 * @return bool
 */
function pg_experiment_sheet_delete($experiment)
{
    if ($experiment['status'] !== 'draft') {
        return false;
    }

    $id = (int) $experiment['id'];

    db("DELETE FROM experiments WHERE id = '$id' AND status = 'draft'");

    if (mysqli_affected_rows(db::$con) < 1) {
        return false;
    }

    db("DELETE FROM experiment_variants WHERE experiment_id = '$id'");

    return true;
}

/**
 * Publishes the winner and ends the experiment. For the live page (a) the
 * experiment only ends; for a variant its tree is saved into the live page
 * through pg_designer_save_page(), refused while somebody has the live page
 * open in the editor, so the save's revision and content gate apply. The
 * variant draft is a plain draft afterwards.
 *
 * @param array  $experiment pg_experiment_sheet_get()
 * @param string $key        the winning variant key
 * @param array  $user
 * @return array ok, error
 */
function pg_experiment_sheet_publish_winner($experiment, $key, $user)
{
    if (!in_array('publish_winner', pg_experiment_sheet_actions($experiment['status']), true) || !pg_experiment_key_valid($key)) {
        return array('ok' => false, 'error' => lang('The experiment cannot make that change now.'));
    }

    $variant = db_item("SELECT id, draft_page_id FROM experiment_variants
        WHERE experiment_id = '" . (int) $experiment['id'] . "' AND variant_key = '" . e($key) . "' LIMIT 1");

    if (!is_array($variant)) {
        return array('ok' => false, 'error' => lang('That variant was not found.'));
    }

    if ((int) $variant['draft_page_id'] > 0) {
        $live = pg_experiment_sheet_live_page($experiment['page_id']);

        if (!$live) {
            return array('ok' => false, 'error' => lang('The page is not on the site, so the experiment cannot run.'));
        }

        require_once(PG_FUNCTIONS_DIR . '/includes/designer_collab.php');

        if (!pg_collab_may_edit_page('', $live['page_id'])) {
            return array('ok' => false, 'error' => lang('Somebody has this page open in the Page Design Studio. Try again when they are done.'));
        }

        $tree = (string) db_value("SELECT page_tree_json FROM page WHERE page_id = '" . (int) $variant['draft_page_id'] . "' LIMIT 1");

        if ($tree === '') {
            return array('ok' => false, 'error' => lang('The variant draft has no design to publish.'));
        }

        $fields = null;

        foreach (pg_designer_load_pages($live['page_style']) as $row) {
            if ((int) $row['page_id'] === (int) $live['page_id']) {
                $fields = $row;
                break;
            }
        }

        if (!$fields) {
            return array('ok' => false, 'error' => lang('The page could not be read.'));
        }

        // Body only: the live page keeps its name, title, meta tags and the
        // rest of its settings, which $fields carries as stored.
        $fields['tree_json'] = $tree;
        $fields['revision_source'] = 'publish';
        $saved = pg_designer_save_page($live['page_style'], $fields, $user);

        if (!$saved['ok']) {
            return array('ok' => false, 'error' => implode(' ', $saved['errors']));
        }
    }

    return pg_experiment_sheet_move($experiment, 'ended', (int) $variant['id']);
}

/**
 * The visual editor's experiments block: whether the sheet can be opened
 * (ready: designers and administrators, after the upgrade), its address, and the
 * state of each open page by id as pg_experiment_page_states() gives it
 * (role, status, the live page's name, whether it holds the page as a
 * draft).
 *
 * @param array $page_ids the editor's page ids
 * @param array $user
 * @return array ready, url, pages
 */
function pg_experiment_editor_config($page_ids, $user)
{
    $ready = pg_experiment_sheet_ready() && ((int) $user['role'] <= 1);
    $pages = array();

    // The states reach every operator: a held variant's Publish is refused
    // for anybody who could press it.
    if (pg_experiment_sheet_ready()) {
        foreach (pg_experiment_page_states($page_ids) as $id => $state) {
            $state['held'] = ($state['role'] === 'variant') && pg_experiment_variant_held($state['status']);
            $pages[(string) $id] = $state;
        }
    }

    return array(
        'ready' => $ready,
        'url' => PATH . SOFTWARE_DIRECTORY . '/page_experiments.php?page_id=',
        'pages' => (object) $pages,
    );
}
