<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Back to classic: the pages of a Legacy design that a set's move made
 * (legacy_to_designer_run.php) returned to the classic style each came
 * from, all of a design's pages or one. The classic style never left. The
 * page's tree is kept as its newest revision and taken off the page, as a
 * tree on the page row would still draw it; the design and its shared
 * components stay for a later move.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2026 Erdal Güral. All rights reserved.
 * @license     Proprietary, see license-editors.txt
 */

if (!defined('PG_FUNCTIONS_DIR')) {
    exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/fn/legacy_to_designer_run.php');

/**
 * The classic style a shared component was made from: "classic_style:<id>"
 * for a whole frame, "classic_style:<id>:<part>" for a part of a split one;
 * 0 for any other category.
 *
 * Pure: no query.
 *
 * @param string $category
 * @return int
 */
function pg_ltc_category_style($category)
{
    return preg_match('/^classic_style:([1-9][0-9]*)(:[a-z]+:[0-9]+)?$/', (string)$category, $m) ? (int)$m[1] : 0;
}

/**
 * The classic style a moved page came from: the one style whose frame
 * components its tree places directly. 0 when it places none, or frames of
 * two styles.
 *
 * Pure: no query.
 *
 * @param array $tree          the page's tree
 * @param array $frame_styles  shared component id => the style it was made from
 * @return int
 */
function pg_ltc_origin_style($tree, $frame_styles)
{
    $found = 0;
    foreach (pg_ltd_shared_ids($tree) as $sid) {
        $style = (int)($frame_styles[$sid] ?? 0);
        if ($style <= 0) continue;
        if ($found > 0 && $found !== $style) return 0;
        $found = $style;
    }
    return $found;
}

/**
 * The page_style of a page going back: the style for a page that had it as
 * its own; for one that took it from its folder (or whose run kept no
 * record), 0 while its folder still gives that style, else the style.
 *
 * Pure: no query.
 *
 * @param int   $origin
 * @param bool  $own            the page had the style as its own page_style
 * @param int   $folder_id
 * @param array $folder_styles  pg_classic_style_folder_styles()
 * @return int
 */
function pg_ltc_target_style($origin, $own, $folder_id, $folder_styles)
{
    if ($own) return (int)$origin;
    return ((int)($folder_styles[(int)$folder_id] ?? 0) === (int)$origin) ? 0 : (int)$origin;
}

/**
 * The pages the kept runs moved (pg_ltd_state_origin()), a later run's
 * record over an earlier one's: page id => array(style id, from folder).
 *
 * Pure: no query.
 *
 * @param array $runs  kept run states, oldest first
 * @return array
 */
function pg_ltc_origins_merge($runs)
{
    $out = array();
    foreach ((array)$runs as $run) {
        foreach ((array)($run['origins'] ?? array()) as $page_id => $o) {
            if ((int)$page_id > 0 && is_array($o) && (int)($o[0] ?? 0) > 0) {
                $out[(int)$page_id] = array((int)$o[0], !empty($o[1]));
            }
        }
    }
    return $out;
}

/**
 * The moved pages of every kept run (pg_ltd_report_keep()).
 *
 * @return array  pg_ltc_origins_merge()
 */
function pg_ltc_run_origins()
{
    $files = glob(pg_ltd_reports_dir() . '/*.json');
    if (!$files) return array();
    sort($files);
    $runs = array();
    foreach ($files as $file) {
        $run = json_decode((string)@file_get_contents($file), true);
        if (is_array($run) && !empty($run['origins'])) $runs[] = $run;
    }
    return pg_ltc_origins_merge($runs);
}

/**
 * Why a page cannot go back, '' when it can.
 *
 * Pure: no query.
 *
 * @param array $page  origin, origin_classic, experiment, held
 * @return string  'no_origin', 'origin', 'experiment', 'held' or ''
 */
function pg_ltc_refusal($page)
{
    if ((int)($page['origin'] ?? 0) <= 0) return 'no_origin';
    if (empty($page['origin_classic'])) return 'origin';
    if (!empty($page['experiment'])) return 'experiment';
    if (!empty($page['held'])) return 'held';
    return '';
}

/**
 * The sentence of a refusal.
 *
 * @param string $code  pg_ltc_refusal()
 * @return string
 */
function pg_ltc_refusal_text($code)
{
    switch ($code) {
        case 'no_origin':
            return lang('It came from no classic style, so it stays in the design.');
        case 'origin':
            return lang('Its classic style is gone or is a design now.');
        case 'experiment':
            return lang('An experiment uses it: end the experiment first.');
        case 'held':
            return lang('It is being edited in the Page Design Studio.');
    }
    return '';
}

/**
 * The Legacy designs holding pages that came from other classic styles (a
 * set's design), by name, each with its pages. A page's style is the one
 * its run kept (pg_ltc_run_origins()), else the one whose frame its tree
 * places (runs kept before the record). A design moved alone is left out:
 * its pages came from the design's own style.
 *
 * @param int $only  one design's id, 0 for all
 * @return array  list of: id, name, pages (list of: page_id, page_name,
 *                origin, origin_name, target, refusal)
 */
function pg_ltc_designs($only = 0)
{
    if (!pg_multi_page_design_ready() || !pg_style_framework_ready()) return array();
    $only = (int)$only;
    $designs = db_items("SELECT style_id, style_name FROM style
                         WHERE style_layout = 'visual_designer' AND style_framework = 'legacy'"
                         . ($only > 0 ? " AND style_id = '$only'" : '') . "
                         ORDER BY style_name ASC, style_id ASC");
    if (!is_array($designs) || !$designs) return array();

    $frame_styles = array();
    $rows = db_items("SELECT id, category FROM shared_components WHERE category LIKE 'classic\\_style:%'");
    foreach ((is_array($rows) ? $rows : array()) as $r) {
        $style = pg_ltc_category_style($r['category']);
        if ($style > 0) $frame_styles[(int)$r['id']] = $style;
    }
    $recorded = pg_ltc_run_origins();
    $origin_ids = array_unique(array_merge(array_values($frame_styles), array_column($recorded, 0)));
    if (!$origin_ids) return array();

    $styles = array();
    $rows = db_items("SELECT style_id, style_name, style_layout FROM style
                      WHERE style_id IN (" . implode(',', array_map('intval', $origin_ids)) . ")");
    foreach ((is_array($rows) ? $rows : array()) as $r) {
        $styles[(int)$r['style_id']] = array('name' => (string)$r['style_name'], 'classic' => ((string)$r['style_layout'] !== 'visual_designer'));
    }
    $folders = db_items("SELECT folder_id, folder_parent, folder_style, folder_level FROM folder");
    $folder_styles = pg_classic_style_folder_styles(is_array($folders) ? $folders : array());

    require_once(PG_FUNCTIONS_DIR . '/includes/designer_collab.php');
    $out = array();
    foreach ($designs as $d) {
        $id = (int)$d['style_id'];
        $rows = db_items("SELECT page_id, page_name, page_folder, page_tree_json FROM page
                          WHERE page_style = '$id' AND page_tree_json IS NOT NULL AND page_tree_json <> ''
                          ORDER BY page_name ASC, page_id ASC");
        $pages = array();
        $from_others = false;
        foreach ((is_array($rows) ? $rows : array()) as $r) {
            $pid = (int)$r['page_id'];
            $own = false;
            if (isset($recorded[$pid])) {
                list($origin, $from_folder) = $recorded[$pid];
                $own = !$from_folder;
            } else {
                $tree = json_decode((string)$r['page_tree_json'], true);
                $origin = is_array($tree) ? pg_ltc_origin_style($tree, $frame_styles) : 0;
            }
            if ($origin === $id) $origin = 0;
            if ($origin > 0) $from_others = true;
            $pages[] = array(
                'page_id' => $pid,
                'page_name' => (string)$r['page_name'],
                'origin' => $origin,
                'origin_name' => isset($styles[$origin]) ? $styles[$origin]['name'] : '',
                'origin_classic' => !empty($styles[$origin]['classic']),
                'target' => pg_ltc_target_style($origin, $own, (int)$r['page_folder'], $folder_styles),
                'held' => !pg_collab_may_edit_page('', $pid),
            );
        }
        if (!$from_others) continue;

        $experiments = array();
        if (file_exists(PG_FUNCTIONS_DIR . '/includes/fn/experiments_sheet.php')) {
            require_once(PG_FUNCTIONS_DIR . '/includes/fn/experiments_sheet.php');
            $experiments = pg_experiment_page_states(array_column($pages, 'page_id'));
        }
        foreach ($pages as $i => $p) {
            $p['experiment'] = isset($experiments[$p['page_id']]);
            $pages[$i]['refusal'] = pg_ltc_refusal($p);
            unset($pages[$i]['origin_classic'], $pages[$i]['held']);
        }
        $out[] = array('id' => $id, 'name' => (string)$d['style_name'], 'pages' => $pages);
    }
    return $out;
}

/**
 * Pages of a Legacy design sent back to the classic style each came from.
 * The tree is recorded as the page's newest revision first (nothing is
 * written when the newest one is the same), then page_style is set
 * (pg_ltc_target_style()) and the tree and its drawn body are taken off the
 * page, so the classic style's code draws it again. A page that cannot go
 * back is listed with its reason. Only a designer.
 *
 * @param int        $design_id
 * @param array|null $page_ids  only these pages, null for every page of the design
 * @param array      $user      id, role, username
 * @return array  'moved' (page names), 'skipped' (sentences)
 */
function pg_ltc_back($design_id, $page_ids, $user)
{
    $out = array('moved' => array(), 'skipped' => array());
    $design_id = (int)$design_id;
    if ((int)$user['role'] > 1 || $design_id <= 0) return $out;
    $designs = pg_ltc_designs($design_id);
    if (!$designs) return $out;

    $only = ($page_ids === null) ? null : array_flip(array_map('intval', (array)$page_ids));
    $uid = (int)$user['id'];
    foreach ($designs[0]['pages'] as $p) {
        if ($only !== null && !isset($only[$p['page_id']])) continue;
        if ($p['refusal'] !== '') {
            // A whole design's pages that came from no classic style simply stay.
            if ($p['refusal'] !== 'no_origin' || $only !== null) {
                $out['skipped'][] = $p['page_name'] . ': ' . pg_ltc_refusal_text($p['refusal']);
            }
            continue;
        }
        $pid = (int)$p['page_id'];
        $row = db_item("SELECT page_tree_json, page_tree_code FROM page WHERE page_id = '$pid' AND page_style = '$design_id' LIMIT 1");
        if (!is_array($row)) continue;
        pg_page_revision_record($pid, $design_id, (string)$row['page_tree_json'], (string)$row['page_tree_code'], $uid);
        db("UPDATE page
            SET page_style = '" . (int)$p['target'] . "', page_tree_json = NULL, page_tree_code = NULL,
                page_timestamp = UNIX_TIMESTAMP(), page_user = '$uid'
            WHERE page_id = '$pid' AND page_style = '$design_id'
            LIMIT 1");
        if (mysqli_affected_rows(db::$con) > 0) $out['moved'][] = $p['page_name'];
    }
    if ($out['moved']) {
        log_activity(lang(array('string' => '{var:1} pages of the design ({var:2}) went back to their classic styles',
            'vars' => array(count($out['moved']), $designs[0]['name']))), (string)($user['username'] ?? ''));
    }
    return $out;
}
