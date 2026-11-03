<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Move to the visual designer: the readiness report of the classic styles
 * (includes/fn/legacy_to_designer.php). Each style with its pages, own and
 * by folder, its regions by type, what stays classic, what needs a look
 * first and whether the designer opens it. Reached from All Page Styles;
 * designers and administrators, as the conversion itself. The chosen styles'
 * dry run (includes/fn/legacy_to_designer_plan.php) lists what a bulk move
 * would write for each. Writes nothing.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

include('init.php');
$user = validate_user();
validate_area_access($user, 'designer');

require_once(PG_FUNCTIONS_DIR . '/includes/fn/legacy_to_designer_plan.php');

$report = pg_ltd_report();

// The dry run of the chosen styles: read only, so a token check is all a
// post needs.
$chosen = array();
$plan = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validate_token_field();
    foreach ((array)($_POST['styles'] ?? array()) as $id) {
        if ((int)$id > 0) $chosen[(int)$id] = true;
    }
    $plan = pg_ltd_plan(array_keys($chosen));
}

$state_badges = array(
    'ready'     => array('theme-success', lang('Ready')),
    'attention' => array('theme-warning', lang('Needs attention')),
    'blocked'   => array('bg-1 fg-body border', lang('Cannot be opened')),
);

$region_labels = array(
    'page'             => lang('Page regions'),
    'system'           => lang('System region'),
    'common'           => lang('Common regions'),
    'common_converted' => lang('Common regions already components'),
    'designer'         => lang('Designer regions'),
    'missing'          => lang('Common regions not found'),
    'dynamic'          => lang('Dynamic regions'),
    'menu'             => lang('Menus'),
    'menu_sequence'    => lang('Menu sequences'),
    'login'            => lang('Login regions'),
    'ad'               => lang('Ad regions'),
    'cart'             => lang('Cart regions'),
    'tag_cloud'        => lang('Tag clouds'),
    'mobile_switch'    => lang('Mobile switches'),
    'pdf'              => lang('PDF regions'),
    'comments_block'   => lang('Comments blocks'),
);

$totals = array('ready' => 0, 'attention' => 0, 'blocked' => 0);
$total_pages = 0;

// The names of a page list, folded under a summary.
$page_list = function ($pages, $summary) {
    if (!$pages) return '';
    $items = '';
    foreach ($pages as $p) {
        $extra = isset($p['holder']) ? ' <span class="fg-secondary">(' . h(lang(array('string' => 'held by {var:1}', 'vars' => array($p['holder'] !== '' ? $p['holder'] : lang('Unknown'))))) . ')</span>' : '';
        $items .= '<li>' . h($p['page_name']) . $extra . '</li>';
    }
    return '<details><summary>' . h($summary) . '</summary><ul class="mb-0 mt-1 ps-12">' . $items . '</ul></details>';
};

$output_rows = '';
foreach ($report as $s) {
    ++$totals[$s['state']];
    $pages = count($s['own']) + count($s['folder']);
    $total_pages += $pages;
    $badge = $state_badges[$s['state']];

    $output_pages = $page_list($s['own'], lang(array('string' => '{var:1} own', 'vars' => array(pg_format_number(count($s['own']), 0)))))
        . $page_list($s['folder'], lang(array('string' => '{var:1} by folder', 'vars' => array(pg_format_number(count($s['folder']), 0)))));
    if ($s['trees'] > 0) {
        $output_pages .= '<div class="small fg-secondary">' . h(lang(array('string' => '{var:1} already designed', 'vars' => array(pg_format_number($s['trees'], 0))))) . '</div>';
    }
    if ($output_pages === '') $output_pages = '<span class="fg-secondary">0</span>';

    $output_regions = '';
    foreach ($region_labels as $type => $label) {
        if (empty($s['regions']['counts'][$type])) continue;
        $tone = ($type === 'missing') ? 'theme-warning' : 'bg-1 fg-body border';
        $output_regions .= '<span class="badge rounded-pill ' . $tone . ' me-1 mb-1 fw-normal">' . h($label) . ': ' . (int)$s['regions']['counts'][$type] . '</span>';
    }
    if ($s['page_regions'] > 0) {
        $output_regions .= '<div class="small fg-secondary">' . h(lang(array('string' => '{var:1} page region contents on its pages', 'vars' => array(pg_format_number($s['page_regions'], 0))))) . '</div>';
    }

    $lines = array();
    foreach ($s['reasons'] as $code) {
        switch ($code) {
            case 'refusal':
                $lines[] = array('fg-danger', $s['refusal']);
                break;
            case 'php':
                $lines[] = array('fg-warning', lang('The style\'s code holds PHP, which the import into the visual designer does not keep: check the frame before saving.'));
                break;
            case 'missing':
                $lines[] = array('fg-warning', lang(array('string' => 'Common regions with no record: {var:1}', 'vars' => array(implode(', ', $s['regions']['missing'])))));
                break;
            case 'locked':
                $lines[] = array('fg-warning', lang(array('string' => '{var:1} pages are being edited in the visual designer.', 'vars' => array(count($s['locked'])))));
                break;
            case 'open':
                $lines[] = array('fg-warning', lang(array('string' => 'Open in the visual designer by {var:1}.', 'vars' => array(implode(', ', $s['open'])))));
                break;
        }
    }
    foreach ($s['notes'] as $code) {
        switch ($code) {
            case 'dynamic':
                $lines[] = array('fg-secondary', lang('Dynamic regions stay PHP, edited from their node.'));
                break;
            case 'custom_layout':
                $lines[] = array('fg-secondary', lang(array('string' => '{var:1} pages keep a custom layout inside the system region.', 'vars' => array($s['custom_layouts']))));
                break;
            case 'no_pages':
                $lines[] = array('fg-secondary', lang('No page uses this style.'));
                break;
        }
    }
    $output_notes = '';
    foreach ($lines as $l) {
        $output_notes .= '<div class="small ' . $l[0] . '">' . h($l[1]) . '</div>';
    }

    $output_action = '';
    if ($s['state'] !== 'blocked') {
        $output_action .= '<input class="check m-1" type="checkbox" name="styles[]" value="' . (int)$s['id'] . '" form="ltd_dry_run" aria-label="' . h($s['name']) . '"' . (isset($chosen[$s['id']]) ? ' checked' : '') . '>';
        $output_action = '<a class="m-1 btn-data-control btn btn-outline theme-secondary border-2" title="' . h(lang('Open in the visual designer')) . '" href="edit_system_style.php?id=' . (int)$s['id'] . '&amp;classic=1"><i class="bi bi-magic"></i></a>';
    }

    $output_rows .= '
        <tr>
            <td class="align-middle text-start">' . $output_action . '</td>
            <td class="align-middle"><a href="edit_' . h($s['type']) . '_style.php?id=' . (int)$s['id'] . '">' . h($s['name']) . '</a>'
                . ($s['theme'] !== '' ? '<div class="small fg-secondary">' . h($s['theme']) . '</div>' : '') . '</td>
            <td class="align-middle"><span class="badge rounded-pill ' . $badge[0] . '">' . h($badge[1]) . '</span></td>
            <td class="align-middle">' . $output_pages . '</td>
            <td class="align-middle">' . $output_regions . '</td>
            <td class="align-middle">' . $output_notes . '</td>
        </tr>';
}

$output_summary = '';
foreach (array(
    array(lang('Ready'), $totals['ready']),
    array(lang('Needs attention'), $totals['attention']),
    array(lang('Cannot be opened'), $totals['blocked']),
    array(lang('Pages'), $total_pages),
) as $tile) {
    $output_summary .= '
        <div class="col-6 md:col-3">
            <div class="card h-100"><div class="card-body">
                <div class="small fg-secondary">' . h($tile[0]) . '</div>
                <div class="fs-2xl fw-semibold">' . pg_format_number($tile[1], 0) . '</div>
            </div></div>
        </div>';
}

$output_table = $report
    ? '<table class="table table-hover mb-0">
            <thead>
                <tr>
                    <th>' . lang('Action') . '</th>
                    <th>' . lang('Style') . '</th>
                    <th>' . lang('State') . '</th>
                    <th>' . lang('Pages') . '</th>
                    <th>' . lang('Regions') . '</th>
                    <th>' . lang('Notes') . '</th>
                </tr>
            </thead>
            <tbody>' . $output_rows . '</tbody>
        </table>'
    : '<p class="p-7 mb-0">' . h(lang('There is no classic style left: every style is a visual design.')) . '</p>';

$output_plan = '';
if ($plan !== null) {
    $output_plan = $plan ? '' : '<p class="mb-0">' . h(lang('Choose the styles to move first.')) . '</p>';
    foreach ($plan as $p) {
        $output_plan .= pg_ltd_plan_html($p);
    }
    $output_plan = '
    <div class="card my-7" id="ltd-dry-run">
        <div class="card-header"><h2 class="h5 mb-0">' . h(lang('Dry run')) . '</h2>
            <div class="small fg-secondary">' . h(lang('What moving the chosen styles would write. Nothing has been changed.')) . '</div></div>
        <div class="card-body">' . $output_plan . '</div>
    </div>';
}

$output_form = $report
    ? '<form id="ltd_dry_run" method="post" action="legacy_to_designer.php#ltd-dry-run" class="d-flex align-items-center gap-3 my-7">'
        . get_token_field() . '
        <button type="submit" class="btn btn-sm btn-solid theme-primary rounded-pill px-5"><i class="bi bi-clipboard-check me-1"></i>' . h(lang('Dry run')) . '</button>
        <span class="small fg-secondary">' . h(lang('Tick the styles to move, then see what would be written for each.')) . '</span>
    </form>'
    : '';

print
pg_page_shell([
        'title' => lang('Move to the visual designer'),
        'extra classes' => 'design',
        'icon' => 'design',
        'heading' => lang('Move to the visual designer'),
        'heading_description' => lang('Every classic style with its pages and regions, and what each needs before it is opened in the visual designer. Nothing is changed here.'),
    ]) . '
<main id="content" class="container-fluid">
    ' . pg_screen_toolbar(array(
        'secondary' => array('label' => lang('All Page Styles'), 'url' => 'view_styles.php', 'icon' => 'bi-arrow-left'),
    )) . '
    <div class="row g-7 my-7">' . $output_summary . '</div>
    ' . $output_plan . $output_form . '
    <div class="card my-7">
        <div class="card-body p-0 table-responsive">' . $output_table . '</div>
    </div>
</main>
' .
output_footer();
