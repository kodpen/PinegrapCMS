<?php
/**
 * Shared Component invariant diagnostics.
 *
 * One-shot admin page — walks every visual-designer style and reports whether the
 * placeholder-only invariant holds:
 *
 *   INVARIANT: for every `shared_ref` node in `style.style_tree_json`,
 *              `children` MUST equal []. Content is resolved at render time from
 *              `shared_components.tree_json`, NOT baked into the style tree.
 *
 * Also reports whether `style.style_code` still contains inline-expanded shared
 * HTML (legacy) vs the new `<!--pg-shared-ref:ID-->` markers.
 *
 * Usage: visit `.../software/check_shared_invariant.php` while logged in as admin.
 * Safe to run repeatedly — read-only diagnostic, never mutates anything.
 * To remediate any offender row, re-save the style in Visual Pinegrap Editor — the save path
 * sanitizes tree_json and regenerates style_code with the marker format.
 */

include('init.php');

$user = validate_user();
validate_area_access($user, 'administrator');

// ── Collect every visual-designer layout with a tree ─────────────────────────
// Since the multi-page designer the tree and its generated code live on the
// PAGE (page_tree_json / page_tree_code); one style can own several pages.
// Each page is checked as its own unit, labelled by page name. The style's
// own tree/code is the fallback for pages saved before the swap, and the
// whole scan collapses to the old per-style query on an un-migrated database.
if (pg_multi_page_design_ready()) {
    $rows = db_items(
        "SELECT page.page_id AS style_id, page.page_name AS style_name,
                " . pg_page_tree_sql_expr() . " AS style_tree_json,
                COALESCE(NULLIF(page.page_tree_code, ''), style.style_code) AS style_code
         FROM page
         INNER JOIN style ON page.page_style = style.style_id
         WHERE style.style_layout = 'visual_designer'
         HAVING style_tree_json IS NOT NULL AND style_tree_json != ''
         ORDER BY style_name");
} else {
    $rows = db_items("SELECT style_id, style_name, style_tree_json, style_code FROM style WHERE style_layout = 'visual_designer' AND style_tree_json IS NOT NULL AND style_tree_json != '' ORDER BY style_name");
}

$total           = 0;
$dirty_trees     = array(); // styles whose tree_json still has inline shared_ref children
$dirty_codes     = array(); // styles whose style_code is missing markers but should have them
$clean           = 0;

function _ci_find_dirty_shared_refs($node, &$acc, $path = '$') {
    if (!is_array($node)) return;
    if (isset($node['type']) && $node['type'] === 'shared_ref') {
        $sid  = isset($node['props']['sharedId']) ? (int)$node['props']['sharedId'] : 0;
        $cnt  = isset($node['children']) && is_array($node['children']) ? count($node['children']) : 0;
        if ($cnt > 0) $acc[] = array('path' => $path, 'sharedId' => $sid, 'children_count' => $cnt);
        return; // black box
    }
    if (isset($node['children']) && is_array($node['children'])) {
        foreach ($node['children'] as $i => $c) {
            _ci_find_dirty_shared_refs($c, $acc, $path . '.children[' . $i . ']');
        }
    }
}

function _ci_count_shared_refs($node) {
    if (!is_array($node)) return 0;
    if (isset($node['type']) && $node['type'] === 'shared_ref') return 1;
    $n = 0;
    if (isset($node['children']) && is_array($node['children'])) {
        foreach ($node['children'] as $c) $n += _ci_count_shared_refs($c);
    }
    return $n;
}

if ($rows) {
    foreach ($rows as $r) {
        $total++;
        $tree = json_decode($r['style_tree_json'], true);
        if (!is_array($tree)) continue;

        $dirty = array();
        _ci_find_dirty_shared_refs($tree, $dirty);
        $ref_count = _ci_count_shared_refs($tree);

        if (!empty($dirty)) {
            $dirty_trees[] = array(
                'style_id'   => $r['style_id'],
                'style_name' => $r['style_name'],
                'dirty'      => $dirty,
            );
        } else if ($ref_count > 0) {
            // Tree is clean — check style_code has markers (post-migration state)
            $has_marker = strpos($r['style_code'], '<!--pg-shared-ref:') !== false;
            if (!$has_marker) {
                $dirty_codes[] = array(
                    'style_id'   => $r['style_id'],
                    'style_name' => $r['style_name'],
                    'ref_count'  => $ref_count,
                );
            } else {
                $clean++;
            }
        } else {
            $clean++;
        }
    }
}

echo pg_page_shell(array(
    'title'   => 'Shared Component Invariant Check',
    'heading' => 'Shared Component Invariant Check',
    'hide_menu' => true,
)) . '<main id="content" class="container-fluid">';

$status_ok = (empty($dirty_trees) && empty($dirty_codes));
$bg_class  = $status_ok ? 'success' : 'warning';
$icon      = $status_ok ? 'check-circle-fill' : 'exclamation-triangle-fill';
$headline  = $status_ok ? 'INVARIANT HOLDS' : 'INVARIANT VIOLATED';

echo '<div class="alert alert-' . h($bg_class) . ' d-flex align-items-center mb-4">
        <i class="bi bi-' . h($icon) . ' me-2 fs-3"></i>
        <div>
            <strong>' . h($headline) . '</strong><br>
            ' . (int)$total . ' visual-designer style(s) checked — '
            . (int)$clean . ' clean, '
            . (int)count($dirty_trees) . ' tree(s) with inline shared content, '
            . (int)count($dirty_codes) . ' style_code(s) still without markers.
        </div>
      </div>';

if (!empty($dirty_trees)) {
    echo '<div class="card mb-4">
            <div class="card-header bg-danger text-white"><strong>Styles with inline-expanded shared_ref children</strong></div>
            <div class="card-body">
                <p class="small text-muted mb-2">These trees violate the placeholder-only invariant. Re-save the style in Visual Pinegrap Editor to remediate (save path sanitizes).</p>
                <table class="table table-sm">
                    <thead><tr><th>style_id</th><th>style_name</th><th>offenders</th></tr></thead>
                    <tbody>';
    foreach ($dirty_trees as $d) {
        echo '<tr><td>' . (int)$d['style_id'] . '</td><td>' . h($d['style_name']) . '</td><td><code>';
        foreach ($d['dirty'] as $off) {
            echo h($off['path']) . ' → sharedId=' . (int)$off['sharedId'] . ', children=' . (int)$off['children_count'] . '<br>';
        }
        echo '</code></td></tr>';
    }
    echo '</tbody></table></div></div>';
}

if (!empty($dirty_codes)) {
    echo '<div class="card mb-4">
            <div class="card-header bg-warning"><strong>Styles whose style_code is missing markers</strong></div>
            <div class="card-body">
                <p class="small text-muted mb-2">Tree_json is clean but style_code was generated before the marker architecture. Re-save the style in Visual Pinegrap Editor to regenerate it.</p>
                <table class="table table-sm">
                    <thead><tr><th>style_id</th><th>style_name</th><th>shared_ref count</th></tr></thead>
                    <tbody>';
    foreach ($dirty_codes as $d) {
        echo '<tr><td>' . (int)$d['style_id'] . '</td><td>' . h($d['style_name']) . '</td><td>' . (int)$d['ref_count'] . '</td></tr>';
    }
    echo '</tbody></table></div></div>';
}

echo '<div class="card">
        <div class="card-header"><strong>What this page checks</strong></div>
        <div class="card-body small">
            <ol>
                <li><strong>tree_json invariant:</strong> every <code>shared_ref</code> node has <code>children === []</code>.</li>
                <li><strong>style_code format:</strong> if the tree has any shared refs, style_code contains <code>&lt;!--pg-shared-ref:ID--&gt;</code> markers (not inline-expanded HTML).</li>
            </ol>
            <p class="mb-0 text-muted">Read-only. Safe to run at any time.</p>
        </div>
      </div>';

echo '';
echo '</main>' . output_footer();
