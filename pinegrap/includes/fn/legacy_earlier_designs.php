<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Designs moved earlier: the Legacy designs already in the Page Design
 * Studio, on the move screen (legacy_to_designer.php). Each with how many of
 * its pages are fully editable (pg_classic_closed_census()), its dry run and
 * a run of the move's page_parts and types steps on its pages alone
 * (legacy_to_designer_run.php), the design's row left as it is. A design
 * moved before the frame split keeps its page content inside one shared
 * component: it is sent back to classic and moved again instead.
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
 * Whether a component's tree holds a Page region node: the page content of a
 * design moved before the frame split sits there. Placed components are not
 * entered.
 *
 * Pure: no query.
 *
 * @param mixed $tree
 * @return bool
 */
function pg_lte_holds_page_region($tree)
{
    $stack = is_array($tree) ? array($tree) : array();
    while ($stack) {
        $node = array_pop($stack);
        if (!is_array($node)) continue;
        $type = isset($node['type']) ? (string)$node['type'] : '';
        if ($type === 'shared_ref') continue;
        if ($type === 'region' && (string)($node['props']['regionType'] ?? '') === 'page') return true;
        $children = ($type !== '') ? ($node['children'] ?? array()) : $node;
        if (is_array($children)) {
            foreach ($children as $child) $stack[] = $child;
        }
    }
    return false;
}

/**
 * The card's rows: each Legacy design with pages, by the order given, with
 * its pages, how many are fully editable, whether it was moved before the
 * frame split (a component its pages place holds a Page region) and whether
 * Back to classic lists it. A design with no page is left out.
 *
 * Pure: no query.
 *
 * @param array $designs  design id => name, in the card's order
 * @param array $measure  pg_ltd_closed_measure(): 'censuses', 'pages'
 *                        (page id => design id), 'components' (id => tree)
 * @param array $back     the design ids Back to classic lists
 * @return array  list of: id, name, pages, editable, left, before_split, back
 */
function pg_lte_rows($designs, $measure, $back)
{
    $by_design = array();
    foreach ((array)($measure['pages'] ?? array()) as $page_id => $design_id) {
        $census = $measure['censuses'][$page_id] ?? null;
        if (!is_array($census)) continue;
        $by_design[(int)$design_id][] = $census;
    }
    $holds = array();
    foreach ((array)($measure['components'] ?? array()) as $id => $tree) {
        if (pg_lte_holds_page_region($tree)) $holds[(int)$id] = true;
    }
    $back = array_flip(array_map('intval', (array)$back));
    $out = array();
    foreach ((array)$designs as $id => $name) {
        $id = (int)$id;
        if (empty($by_design[$id])) continue;
        $editable = 0;
        $before = false;
        foreach ($by_design[$id] as $census) {
            if ((int)($census['total'] ?? 0) === 0) ++$editable;
            foreach ((array)($census['components'] ?? array()) as $cid) {
                if (isset($holds[(int)$cid])) $before = true;
            }
        }
        $pages = count($by_design[$id]);
        $out[] = array('id' => $id, 'name' => (string)$name, 'pages' => $pages, 'editable' => $editable,
                       'left' => $pages - $editable, 'before_split' => $before, 'back' => isset($back[$id]));
    }
    return $out;
}

/**
 * The card's rows read from the site: the Legacy designs by name and the
 * move screen's measure.
 *
 * @param array $measure  pg_ltd_closed_measure()
 * @param array $back     the design ids Back to classic lists
 * @return array  pg_lte_rows()
 */
function pg_lte_designs($measure, $back = array())
{
    if (!pg_multi_page_design_ready() || !pg_style_framework_ready()) return array();
    $names = array();
    foreach ((array)db_items("SELECT style_id, style_name FROM style WHERE style_layout = 'visual_designer' AND style_framework = 'legacy'
                              ORDER BY style_name ASC, style_id ASC") as $r) {
        $names[(int)$r['style_id']] = (string)$r['style_name'];
    }
    return pg_lte_rows($names, $measure, $back);
}

/**
 * One card row by its design id, null when the card does not list it.
 *
 * Pure: no query.
 */
function pg_lte_row($rows, $design_id)
{
    foreach ((array)$rows as $row) {
        if ((int)$row['id'] === (int)$design_id) return $row;
    }
    return null;
}

/**
 * An earlier design's dry run entry, in the shape of a style's pg_ltd_plan()
 * entry, so pg_ltd_plan_groups() and pg_ltd_plan_type_kinds() read it: the
 * region nodes its pages' trees still hold that the page_parts step makes
 * components and widgets of, the page types the types step converts, what
 * stays code and the held pages. No page content moves: it is in the trees
 * already.
 *
 * Pure: no query.
 *
 * @param array $design        a pg_lte_rows() row
 * @param array $pages         list of: page_id, page_name, tree (decoded),
 *                             holder (null when nobody holds the page)
 * @param array $types         pg_ltd_type_target() of the pages not held
 * @param array $designer_php  designer regions among the targets holding PHP
 * @return array
 */
function pg_lte_plan_entry($design, $pages, $types, $designer_php)
{
    $entry = array('kind' => 'design', 'id' => (int)$design['id'], 'name' => (string)$design['name'],
                   'skip' => !empty($design['before_split']) ? 'before_split' : '',
                   'pages' => array(), 'components' => array(), 'widgets' => array(),
                   'parts' => pg_ltd_part_targets(array()), 'stays' => array(), 'designer_php' => array(),
                   'custom_layouts' => 0, 'types' => array());
    if ($entry['skip'] !== '') return $entry;
    $trees = array();
    foreach ((array)$pages as $p) {
        $held = isset($p['holder']) && $p['holder'] !== null;
        $entry['pages'][] = array('page_id' => (int)$p['page_id'], 'page_name' => (string)$p['page_name'],
                                  'action' => $held ? 'held' : 'kept', 'holder' => $held ? (string)$p['holder'] : '');
        if (!$held && is_array($p['tree'] ?? null)) $trees[] = $p['tree'];
    }
    $root = array('type' => 'root', 'props' => array(), 'children' => $trees);
    $entry['parts'] = pg_ltd_part_targets($root);
    $entry['stays'] = pg_ltd_stay_targets($root);
    $entry['designer_php'] = array_values(array_map('strval', (array)$designer_php));
    foreach ((array)$types as $t) {
        if (($t['state'] ?? '') === 'file') {
            ++$entry['custom_layouts'];
        } else {
            $entry['types'][] = $t;
        }
    }
    return $entry;
}

/**
 * The dry run of an earlier design, read from its pages. Writes nothing.
 *
 * @param array $design  a pg_lte_rows() row
 * @return array  pg_lte_plan_entry()
 */
function pg_lte_plan($design)
{
    if (!empty($design['before_split'])) return pg_lte_plan_entry($design, array(), array(), array());
    $holders = array();
    require_once(PG_FUNCTIONS_DIR . '/includes/designer_collab.php');
    if (pg_collab_ready()) {
        $cut = time() - PG_COLLAB_STALE;
        foreach ((array)db_items("SELECT l.page_id, u.user_username FROM designer_page_lock l
                                  LEFT JOIN user u ON u.user_id = l.user_id
                                  WHERE l.last_seen >= '$cut'") as $r) {
            $holders[(int)$r['page_id']] = (string)$r['user_username'];
        }
    }
    $pages = array();
    $own_system = array();
    foreach (pg_designer_load_pages((int)$design['id'], '', true) as $page) {
        $pid = (int)$page['page_id'];
        $tree = json_decode((string)$page['tree_json'], true);
        if (!is_array($tree)) continue;
        $holder = isset($holders[$pid]) ? ($holders[$pid] !== '' ? $holders[$pid] : lang('Unknown')) : null;
        $pages[] = array('page_id' => $pid, 'page_name' => (string)$page['page_name'], 'tree' => $tree, 'holder' => $holder);
        if ($holder === null && pg_ltd_tree_has_own_system($tree)) $own_system[$pid] = (string)$page['page_name'];
    }
    $types = array();
    if ($own_system) {
        $hosted = pg_hosted();
        foreach (pg_classic_type_facts(array_keys($own_system)) as $page_id => $facts) {
            $types[] = pg_ltd_type_target($own_system[$page_id], $facts, $hosted);
        }
    }
    $entry = pg_lte_plan_entry($design, $pages, $types, array());
    $designer = $entry['parts']['designer'];
    if ($designer) {
        $entry['designer_php'] = array_map('strval', db_values("SELECT cregion_name FROM cregion WHERE cregion_designer_type = 'yes'
            AND cregion_name IN ('" . implode("','", array_map('e', $designer)) . "') AND cregion_content LIKE '%<?%' ORDER BY cregion_name"));
    }
    return $entry;
}

/**
 * Starts the run of one earlier design: the page_parts and types steps on
 * its pages (pg_ltd_steps(false, true)), its row left as it is. Refused for
 * a role above 1, while a move runs, for a design the card does not list
 * and for one moved before the frame split.
 *
 * @param int   $design_id
 * @param array $user
 * @param array $starters  pg_ltd_starters_clean() of the design's dry run
 * @return string  '' when started, else the reason
 */
function pg_lte_run_start($design_id, $user, $starters = array())
{
    if ((int)$user['role'] > 1) return lang('Only a designer can open a classic style in the Page Design Studio.');
    if (pg_ltd_state_read() !== null || is_file(PG_FUNCTIONS_DIR . '/data/temp/legacy_to_bootstrap.json')) return lang('A move is already under way.');
    $row = pg_lte_row(pg_lte_designs(pg_ltd_closed_measure()), $design_id);
    if ($row === null) return lang('The design is not a Legacy design with pages any more.');
    if ($row['before_split']) return lang('This design was moved before page content went into the pages: send it back to classic, then move it again.');
    $state = pg_lte_state_new($row, $user, time());
    pg_ltd_starters_keep($starters);
    pg_ltd_state_write($state);
    return is_file(pg_ltd_state_path()) ? '' : lang('The move could not be started: data/temp is not writable.');
}

/**
 * A run's cursor for one earlier design: the design listed as its one
 * "style" and in 'earlier', so the cursor walks its two steps.
 *
 * Pure: no query.
 *
 * @param array $row   a pg_lte_rows() row
 * @param array $user
 * @param int   $now
 * @return array
 */
function pg_lte_state_new($row, $user, $now)
{
    $state = pg_ltd_state_new(array((int)$row['id']), $user, $now);
    $state['earlier'] = array((int)$row['id']);
    $state['step'] = pg_ltd_state_steps($state)[0];
    $state['names'][(string)(int)$row['id']] = (string)$row['name'];
    return $state;
}

/**
 * The "Designs moved earlier" card: a list row a design, its name, how many
 * of its pages are fully editable, a pill (teal all editable, amber pages
 * left), "Dry run" at the row's end and Back to classic in the row's menu.
 * A design moved before the frame split says where its page content sits
 * and offers only "Send back to classic, then move again". '' with no row.
 *
 * @param array $rows  pg_lte_rows()
 * @return string
 */
function pg_lte_card_html($rows)
{
    if (!$rows) return '';
    $out = '';
    foreach ($rows as $r) {
        $line = lang(array('string' => '{var:1} of {var:2} pages fully editable', 'vars' => array(pg_format_number($r['editable'], 0), pg_format_number($r['pages'], 0))));
        $pill = ($r['left'] === 0)
            ? '<span class="pg-pill pg-tone-teal">' . h(lang('All editable')) . '</span>'
            : '<span class="pg-pill pg-tone-amber">' . h(lang(array('string' => '{var:1} pages left', 'vars' => array(pg_format_number($r['left'], 0))))) . '</span>';
        $actions = '';
        if ($r['before_split']) {
            $line .= ' · ' . lang('Moved before page content went into the pages: its page content sits in one shared part.');
            if ($r['back']) {
                $actions = '<a class="pg-button pg-button-quiet pg-button-sm" href="#ltd-back-' . (int)$r['id'] . '">' . h(lang('Send back to classic, then move again')) . '</a>';
            }
        } else {
            $actions = '<a class="pg-button pg-button-quiet pg-button-sm" href="legacy_to_designer.php?earlier=' . (int)$r['id'] . '#ltd-earlier">' . h(lang('Dry run')) . '</a>';
            if ($r['back']) {
                $actions .= '<span class="pg-menu-wrap"><button type="button" class="pg-icon-button pg-icon-button-sm" data-bs-toggle="menu" data-bs-container="body" aria-expanded="false" aria-label="' . h(lang('More actions')) . '"><i class="bi bi-three-dots" aria-hidden="true"></i></button>'
                    . '<ul class="menu pg-menu-end"><li><a class="menu-item" href="#ltd-back-' . (int)$r['id'] . '"><i class="bi bi-arrow-counterclockwise me-3" aria-hidden="true"></i>' . h(lang('Back to classic')) . '</a></li></ul></span>';
            }
        }
        $out .= '<div class="pg-list-row"><span class="pg-icon-tile pg-tone-blue" aria-hidden="true"><span class="bi bi-brush"></span></span>'
            . '<div class="pg-list-row-main"><div class="pg-list-row-head pg-list-row-head-wrap"><span class="pg-list-row-title">' . h($r['name']) . '</span>' . $pill
            . ($actions !== '' ? '<span class="d-inline-flex align-items-center gap-2">' . $actions . '</span>' : '') . '</div>'
            . '<div class="pg-list-row-text">' . h($line) . '</div></div></div>';
    }
    return '
    <div class="card my-7" id="ltd-earlier">
        <div class="card-header"><h2 class="h5 mb-0">' . h(lang('Designs moved earlier')) . '</h2>
            <div class="small fg-secondary">' . h(lang('Legacy designs already in the Page Design Studio. A dry run shows what is still left to convert on their pages; the design itself stays as it is.')) . '</div></div>
        <div class="card-body">' . $out . '</div>
    </div>';
}

/**
 * An earlier design's dry run drawer, open with the screen: the three groups
 * of pg_ltd_plan_groups(), the note when a common region converts, and a
 * foot with "Convert" filled and "Close" quiet. No Convert while a move runs,
 * for a design moved before the frame split or when nothing is left.
 *
 * @param array $row    a pg_lte_rows() row
 * @param array $entry  pg_lte_plan()
 * @param bool  $busy   a move runs
 * @return string
 */
function pg_lte_drawer_html($row, $entry, $busy)
{
    $groups = pg_ltd_plan_groups($entry);
    if ($entry['skip'] === 'before_split') {
        $body = '<p class="mb-0">' . h(lang('This design was moved before page content went into the pages: send it back to classic, then move it again.')) . '</p>';
    } else {
        $body = pg_ltd_plan_groups_html($groups);
        if (!$groups['editable']) $body = '<p class="mb-3">' . h(lang('Nothing is left to convert on its pages.')) . '</p>' . $body;
        if ($groups['common']) {
            $body .= '<p class="pg-field-help mb-0"><i class="bi bi-info-circle me-1" aria-hidden="true"></i>' . h(lang('From now on these common regions are edited in the Page Design Studio, on classic pages too.')) . '</p>';
        }
    }
    $convert = ($entry['skip'] === '' && $groups['editable'] && !$busy);
    $kinds = $convert ? pg_ltd_plan_type_kinds(array($entry)) : array();
    $foot = '<span class="small fg-secondary flex-grow-1" id="ltd_earlier_convert_note" aria-live="polite">'
        . ($convert ? h(lang('Writes what is listed above. A page somebody holds is skipped; undo is a page\'s revision history and deleting a shared component.')) : '') . '</span>'
        . '<button type="button" class="pg-button pg-button-quiet" data-bs-dismiss="drawer">' . h(lang('Close')) . '</button>';
    if ($convert) {
        $foot = '<form method="post" action="legacy_to_designer.php" id="ltd_earlier_convert" class="d-flex flex-wrap align-items-center gap-3 w-100" data-kinds="' . h(json_encode($kinds)) . '">'
            . get_token_field() . '<input type="hidden" name="earlier_convert" value="1"><input type="hidden" name="earlier" value="' . (int)$row['id'] . '"><input type="hidden" name="starters" value="">'
            . $foot . '<button type="submit" class="pg-button pg-button-fill"><i class="bi bi-magic" aria-hidden="true"></i>' . h(lang('Convert')) . '</button></form>';
    }
    $pill = ($row['left'] === 0)
        ? '<span class="pg-pill pg-tone-teal">' . h(lang('All editable')) . '</span>'
        : '<span class="pg-pill pg-tone-amber">' . h(lang(array('string' => '{var:1} pages left', 'vars' => array(pg_format_number($row['left'], 0))))) . '</span>';
    return '
    <dialog class="drawer drawer-end" id="ltd_earlier" aria-labelledby="ltd_earlier_title" style="width:min(560px,100%)" data-open="1">
        <div class="d-flex flex-column h-100">
            <div class="drawer-header"><div class="d-flex align-items-start gap-3 w-100">
                <span class="pg-icon-tile pg-icon-tile-lg pg-tone-blue" aria-hidden="true"><i class="bi bi-brush"></i></span>
                <div class="flex-grow-1 min-w-0"><h5 class="drawer-title mb-0" id="ltd_earlier_title">' . h($row['name']) . '</h5>
                    <p class="small fg-2 mb-0 mt-1">' . h(lang(array('string' => '{var:1} of {var:2} pages fully editable', 'vars' => array(pg_format_number($row['editable'], 0), pg_format_number($row['pages'], 0))))) . '</p></div>
                ' . $pill . '
                <button type="button" class="btn-close" data-bs-dismiss="drawer" aria-label="' . h(lang('Close')) . '"></button>
            </div></div>
            <div class="drawer-body flex-grow-1 overflow-auto">
                <div class="pg-text-meta mb-3">' . h(lang('What converting the regions left on its pages would write. Nothing has been changed.')) . '</div>
                ' . $body . '
            </div>
            <div class="drawer-footer border-top p-5 d-flex flex-wrap align-items-center gap-3">' . $foot . '</div>
        </div>
    </dialog>
<script>
(function () {
    var phone = window.matchMedia("(max-width: 575.98px)");
    // pgUI comes with the footer, after this script: the drawer opens from
    // the right or, on a phone, as a sheet from the bottom.
    window.addEventListener("load", function () {
        var drawer = document.getElementById("ltd_earlier");
        if (!drawer || !window.pgUI) { return; }
        drawer.classList.toggle("drawer-end", !phone.matches);
        drawer.classList.toggle("drawer-bottom", phone.matches);
        pgUI.drawer.show(drawer);
    });
})();
</script>' . ($kinds ? pg_ltd_convert_script('ltd_earlier_convert') : '');
}
