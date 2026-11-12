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
 * would write for each; Convert starts that move (legacy_to_designer_run.php),
 * worked by this screen's short requests and the general job.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2026 Erdal Güral. All rights reserved.
 * @license     Proprietary, see license-editors.txt
 */

include('init.php');

require_once(PG_FUNCTIONS_DIR . '/includes/fn/legacy_to_designer_run.php');

// The run's short requests (ajax=1) are read by the screen's script as JSON:
// an ended session, a missing right or a stale token is answered in that
// shape.
if (($_SERVER['REQUEST_METHOD'] === 'POST') && (($_POST['ajax'] ?? '') === '1')) {
    $ltd_json = function ($status, $body) {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store');
        echo json_encode($body);
        exit;
    };
    if (!defined('USER_LOGGED_IN') || !USER_LOGGED_IN) {
        $ltd_json(401, array('ok' => false, 'text' => lang('Your session has expired. Reload the page and sign in again.')));
    }
    if (!defined('USER_ROLE') || ((int)USER_ROLE > 1)) {
        $ltd_json(403, array('ok' => false, 'text' => lang('Access denied.')));
    }
    $ltd_token = (string)($_SESSION['software']['token'] ?? '');
    $ltd_post = (isset($_POST['token']) && is_string($_POST['token'])) ? $_POST['token'] : '';
    if ($ltd_token === '' || !hash_equals($ltd_token, $ltd_post) || ($_POST['step'] ?? '') !== 'run') {
        $ltd_json(403, array('ok' => false, 'text' => lang('Your session expired. Reload the page and try again.')));
    }
    // Other panel tabs do not wait behind a step.
    session_write_close();
    if (function_exists('ignore_user_abort')) @ignore_user_abort(true);
    $run = pg_ltd_run(8);
    $ltd_json(200, array('ok' => true, 'status' => $run['status'], 'progress' => $run['progress'], 'counts' => $run['counts']));
}

$user = validate_user();
validate_area_access($user, 'designer');

// The dry run of the chosen sets and styles writes nothing. Convert starts
// the run of the sets and styles a dry run listed, then the screen works it.
$chosen = array();
$chosen_sets = array();
$plan = null;
$run_error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validate_token_field();
    foreach ((array)($_POST['styles'] ?? array()) as $id) {
        if ((int)$id > 0) $chosen[(int)$id] = true;
    }
    foreach ((array)($_POST['sets'] ?? array()) as $key) {
        if (is_string($key) && preg_match('/^[0-9a-f]{16}$/', $key)) $chosen_sets[$key] = true;
    }
    if (!empty($_POST['convert'])) {
        $run_error = pg_ltd_run_start(array_keys($chosen), $user, array_keys($chosen_sets));
        if ($run_error === '') {
            $started = pg_ltd_state_read();
            log_activity(lang(array('string' => 'the move of {var:1} styles to the visual designer was started', 'vars' => array(count((array)($started['styles'] ?? array()))))), $_SESSION['sessionusername']);
            header('Location: ' . PATH . SOFTWARE_DIRECTORY . '/legacy_to_designer.php#ltd-run');
            exit;
        }
    }
    $plan = pg_ltd_plan(array_keys($chosen), array_keys($chosen_sets));
}

$running = pg_ltd_state_read();
$report = pg_ltd_report();
$sets = pg_ltd_sets($report);

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

foreach ($report as $s) {
    ++$totals[$s['state']];
    $total_pages += count($s['own']) + count($s['folder']);
}

// One row per style, under its set's head row or under the styles that
// move alone.
$style_row = function ($s) use ($state_badges, $region_labels, $page_list, $chosen) {
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
    if ($s['mobile'] > 0 && defined('MOBILE') && MOBILE) {
        $lines[] = array('fg-secondary', lang('A folder or page uses it as its mobile style: the classic style stays and keeps serving phones.'));
    }
    $output_notes = '';
    foreach ($lines as $l) {
        $output_notes .= '<div class="small ' . $l[0] . '">' . h($l[1]) . '</div>';
    }

    $output_action = '';
    if ($s['state'] !== 'blocked') {
        $output_action .= '<input class="check m-1" type="checkbox" name="styles[]" value="' . (int)$s['id'] . '" form="ltd_dry_run" aria-label="' . h($s['name']) . '"' . (isset($chosen[$s['id']]) ? ' checked' : '') . '>';
        $output_action .= '<a class="m-1 btn-data-control btn btn-outline theme-secondary border-2" title="' . h(lang('Open in the visual designer')) . '" href="edit_system_style.php?id=' . (int)$s['id'] . '&amp;classic=1"><i class="bi bi-magic"></i></a>';
    }

    return '
        <tr>
            <td class="align-middle text-start">' . $output_action . '</td>
            <td class="align-middle"><a href="edit_' . h($s['type']) . '_style.php?id=' . (int)$s['id'] . '">' . h($s['name']) . '</a>'
                . ($s['theme'] !== '' ? '<div class="small fg-secondary">' . h($s['theme']) . '</div>' : '') . '</td>
            <td class="align-middle"><span class="badge rounded-pill ' . $badge[0] . '">' . h($badge[1]) . '</span></td>
            <td class="align-middle">' . $output_pages . '</td>
            <td class="align-middle">' . $output_regions . '</td>
            <td class="align-middle">' . $output_notes . '</td>
        </tr>';
};

// A set: its box (for the dry run), its design's name, how many styles and
// pages, its theme file and the stylesheets and scripts its styles share,
// then its styles. The styles that move alone close the table.
$by_id = array();
foreach ($report as $s) $by_id[$s['id']] = $s;
$output_rows = '';
foreach ($sets['sets'] as $set) {
    $files = '';
    foreach (array('css' => 'bi-filetype-css', 'js' => 'bi-filetype-js') as $kind => $icon) {
        foreach ($set['refs'][$kind] as $address) {
            $files .= '<li class="text-break"><i class="bi ' . $icon . ' me-1" aria-hidden="true"></i>' . h($address) . '</li>';
        }
    }
    $summary = lang(array('string' => '{var:1} stylesheets, {var:2} scripts', 'vars' => array(count($set['refs']['css']), count($set['refs']['js']))));
    $output_rows .= '
        <tbody class="ltd-set">
        <tr class="ltd-set-head">
            <td class="align-middle text-start"><input class="check m-1" type="checkbox" name="sets[]" value="' . h($set['key']) . '" form="ltd_dry_run" aria-label="' . h($set['name']) . '"' . (isset($chosen_sets[$set['key']]) ? ' checked' : '') . '></td>
            <th scope="rowgroup" colspan="5" class="align-middle fw-normal">
                <span class="fw-semibold">' . h($set['name']) . '</span>
                <span class="small fg-secondary ms-2">' . h(lang(array('string' => '{var:1} styles, {var:2} pages', 'vars' => array(pg_format_number(count($set['styles']), 0), pg_format_number($set['pages'], 0))))) . '</span>
                <div class="small fg-secondary">' . h($set['theme'] !== '' ? lang(array('string' => 'Theme file: {var:1}', 'vars' => array($set['theme']))) : lang('The site\'s activated theme')) . '</div>'
                . ($files !== '' ? '<details class="small"><summary>' . h($summary) . '</summary><ol class="mb-0 mt-1 ps-12">' . $files . '</ol></details>' : '') . '
            </th>
        </tr>';
    foreach ($set['styles'] as $id) $output_rows .= $style_row($by_id[$id]);
    $output_rows .= '
        </tbody>';
}
if ($sets['alone']) {
    $output_rows .= '
        <tbody>
        <tr class="ltd-set-head">
            <td></td>
            <th scope="rowgroup" colspan="5" class="align-middle fw-normal"><span class="fw-semibold">' . h(lang('Styles that move alone')) . '</span>
                <div class="small fg-secondary">' . h(lang('No other style loads the same files with the same theme file and collection, or the visual designer cannot open the style.')) . '</div></th>
        </tr>';
    foreach ($report as $s) {
        if (isset($sets['alone'][$s['id']])) $output_rows .= $style_row($s);
    }
    $output_rows .= '
        </tbody>';
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
                    <th>' . lang('Status') . '</th>
                    <th>' . lang('Pages') . '</th>
                    <th>' . lang('Regions') . '</th>
                    <th>' . lang('Notes') . '</th>
                </tr>
            </thead>
            ' . $output_rows . '
        </table>'
    : '<p class="p-7 mb-0">' . h(lang('There is no classic style left: every style is a visual design.')) . '</p>';

// What stays classic after every style is moved: refused styles, dynamic
// regions, custom layouts and a card gateway no payment driver serves.
$frozen_gateway = '';
if (defined('ECOMMERCE') && ECOMMERCE && defined('ECOMMERCE_CREDIT_DEBIT_CARD') && ECOMMERCE_CREDIT_DEBIT_CARD
    && defined('ECOMMERCE_PAYMENT_GATEWAY') && in_array((string)ECOMMERCE_PAYMENT_GATEWAY, pg_payment_legacy_gateways(), true)
    && pg_payment_driver_for_gateway((string)ECOMMERCE_PAYMENT_GATEWAY) === '') {
    $frozen_gateway = (string)ECOMMERCE_PAYMENT_GATEWAY;
}
$output_classic = '';
foreach (pg_ltd_stays_classic($report, $frozen_gateway) as $item) {
    $styles = $item['styles'] ? ' <span class="fg-secondary">(' . h(implode(', ', $item['styles'])) . ')</span>' : '';
    switch ($item['code']) {
        case 'blocked':
            $text = lang(array('string' => '{var:1} styles the visual designer cannot open stay as they are.', 'vars' => array($item['count'])));
            break;
        case 'dynamic':
            $text = lang(array('string' => '{var:1} dynamic regions stay PHP, edited from their node.', 'vars' => array($item['count'])));
            break;
        case 'custom_layout':
            $text = lang(array('string' => '{var:1} pages keep a custom layout inside the system region.', 'vars' => array($item['count'])));
            break;
        default:
            $text = lang(array('string' => 'Card payments stay on the {var:1} gateway code; a moved checkout page posts to it unchanged.', 'vars' => array($item['gateway'])));
    }
    $output_classic .= '<li>' . h($text) . $styles . '</li>';
}
if ($output_classic !== '') {
    $output_classic = '
    <div class="card my-7" id="ltd-stays-classic">
        <div class="card-header"><h2 class="h5 mb-0">' . h(lang('What stays classic')) . '</h2>
            <div class="small fg-secondary">' . h(lang('Kept and working after the move; nothing here is converted.')) . '</div></div>
        <div class="card-body"><ul class="small mb-0 ps-12">' . $output_classic . '</ul></div>
    </div>';
}

$output_plan = '';
if ($plan !== null) {
    $output_plan = $plan ? '' : '<p class="mb-0">' . h(lang('Choose a set or the styles to move first.')) . '</p>';
    foreach ($plan as $p) {
        $output_plan .= pg_ltd_plan_html($p);
    }
    $output_convert = '';
    if ($plan && $running === null) {
        $hidden = '';
        foreach ($plan as $p) {
            if ($p['skip'] !== '') continue;
            if ($p['kind'] === 'style') $hidden .= '<input type="hidden" name="styles[]" value="' . (int)$p['id'] . '">';
            if ($p['kind'] === 'set') $hidden .= '<input type="hidden" name="sets[]" value="' . h($p['key']) . '">';
        }
        if ($hidden !== '') {
            $output_convert .= '
        <form method="post" action="legacy_to_designer.php" class="card-footer d-flex flex-wrap align-items-center gap-3">' . get_token_field() . $hidden . '
            <button type="submit" name="convert" value="1" class="btn btn-sm btn-solid theme-primary rounded-pill px-5"><i class="bi bi-magic me-1"></i>' . h(lang('Move to the visual designer')) . '</button>
            <span class="small fg-secondary">' . h(lang('Writes what is listed above. A page somebody holds is skipped; undo is a page\'s revision history and deleting a shared component.')) . '</span>
        </form>';
        }
    }
    $output_plan = '
    <div class="card my-7" id="ltd-dry-run">
        <div class="card-header"><h2 class="h5 mb-0">' . h(lang('Dry run')) . '</h2>
            <div class="small fg-secondary">' . h(lang('What moving the chosen sets and styles would write. Nothing has been changed.')) . '</div></div>
        <div class="card-body">' . ($run_error !== '' ? '<div class="fg-danger mb-3">' . h($run_error) . '</div>' : '') . $output_plan . '</div>' . $output_convert . '
    </div>';
}

// The run under way, worked by this screen's requests while it is open and
// by the general job when it is not.
$output_run = '';
if ($running !== null) {
    $progress = pg_ltd_state_progress($running);
    $output_run = '
    <div class="card my-7" id="ltd-run">
        <div class="card-header"><h2 class="h5 mb-0">' . h(lang('Moving to the visual designer')) . '</h2></div>
        <div class="card-body"><div id="ltd_progress" aria-live="polite">' . h(lang(array('string' => '{var:1} of {var:2} styles done', 'vars' => array($progress['done'], $progress['total'])))) . '</div>
            <div class="small fg-secondary mt-1">' . h(lang('You can leave this screen: the general job carries the move on.')) . '</div></div>
    </div>
<script>
(function () {
    var token = ' . json_encode((string)($_SESSION['software']['token'] ?? '')) . ';
    var texts = ' . json_encode(array('progress' => lang('{var:1} of {var:2} styles done'), 'error' => lang('The connection failed. Reload the page to carry on.'))) . ';
    var box = document.getElementById("ltd_progress");
    var step = function () {
        var body = new URLSearchParams();
        body.set("ajax", "1");
        body.set("step", "run");
        body.set("token", token);
        fetch("legacy_to_designer.php", {method: "POST", body: body, credentials: "same-origin"}).then(function (r) { return r.json(); }).then(function (a) {
            if (!a.ok) { box.textContent = a.text || texts.error; return; }
            if (a.status === "running" || a.status === "busy") {
                if (a.progress) { box.textContent = texts.progress.replace("{var:1}", a.progress.done).replace("{var:2}", a.progress.total); }
                setTimeout(step, a.status === "busy" ? 3000 : 200);
            } else {
                window.location.href = "legacy_to_designer.php?ended=1#ltd-runs";
            }
        }, function () { box.textContent = texts.error; });
    };
    step();
})();
</script>';
}

// The kept reports of the last runs.
$output_runs = '';
foreach (pg_ltd_reports(5) as $r) {
    $c = $r['counts'];
    $lines = '';
    foreach ($r['lines'] as $l) {
        $tone = ($l['kind'] === 'failed') ? 'fg-danger' : (($l['kind'] === 'skipped') ? 'fg-warning' : '');
        $style = isset($r['names'][(string)$l['style']]) ? $r['names'][(string)$l['style']] . ': ' : '';
        $lines .= '<li' . ($tone !== '' ? ' class="' . $tone . '"' : '') . '>' . h($style . $l['text']) . '</li>';
    }
    if (!empty($r['more'])) $lines .= '<li class="fg-secondary">' . h(lang(array('string' => '{var:1} more lines', 'vars' => array(pg_format_number($r['more'], 0))))) . '</li>';
    $output_runs .= '
        <details class="mb-3"><summary>' . h(get_absolute_time(array('timestamp' => (int)($r['finished'] ?? $r['started']), 'format' => 'plain_text'))) . ' · '
        . h(lang(array('string' => '{var:1} styles, {var:2} pages, {var:3} components, {var:4} widgets, {var:5} skipped, {var:6} failed',
            'vars' => array($c['styles'], $c['pages'], $c['components'], $c['widgets'], $c['skipped'], $c['failed'])))) . ' · ' . h((string)$r['username']) . '</summary>
            <ul class="small mb-0 mt-1 ps-12">' . $lines . '</ul></details>';
}
if ($output_runs !== '') {
    $output_runs = '
    <div class="card my-7" id="ltd-runs">
        <div class="card-header"><h2 class="h5 mb-0">' . h(lang('Earlier moves')) . '</h2></div>
        <div class="card-body">' . $output_runs . '</div>
    </div>';
}

// Dry run is the toolbar's primary action; it submits this hidden form, which
// carries the token and the ticked styles (their checkboxes name it in form=).
// Neither is drawn while a move runs.
$output_form = '';
$toolbar_primary = '';
$toolbar_grow = '';
if ($report && $running === null) {
    $toolbar_primary = '<button type="submit" form="ltd_dry_run" class="btn btn-sm btn-solid theme-primary rounded-pill px-5"><i class="bi bi-clipboard-check me-2" aria-hidden="true"></i>' . h(lang('Dry run')) . '</button>';
    $toolbar_grow = '<span class="small fg-secondary">' . h(lang('Tick a set or the styles to move, then see what would be written for each.')) . '</span>';
    $output_form = '<form id="ltd_dry_run" method="post" action="legacy_to_designer.php#ltd-dry-run" hidden>' . get_token_field() . '</form>';
}

print
pg_page_shell([
        'title' => lang('Move to the visual designer'),
        'extra classes' => 'design',
        'icon' => 'design',
        'heading' => lang('Move to the visual designer'),
        'heading_description' => lang('Every classic style with its pages and regions, and what each needs before it is opened in the visual designer. Nothing is changed here.'),
        'breadcrumb' => array(array('label' => lang('All Page Styles'), 'url' => OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/view_styles.php'), array('label' => lang('Move to the visual designer'))),
    ]) . '
<main id="content" class="container-fluid">
    ' . pg_screen_toolbar(array(
        'primary' => $toolbar_primary,
        'grow' => $toolbar_grow,
        'secondary' => array('label' => lang('All Page Styles'), 'url' => 'view_styles.php', 'icon' => 'bi-arrow-left'),
    )) . '
    <div class="row g-7 my-7">' . $output_summary . '</div>
    ' . $output_form . $output_run . $output_plan . '
    <div class="card my-7" id="ltd-styles">
        <div class="card-header"><h2 class="h5 mb-0">' . h(lang('Styles')) . '</h2>
            <div class="small fg-secondary">' . h(lang(array('string' => 'Sets: {var:1}. Styles that load the same stylesheets and scripts in the same order, with the same theme file and collection, form a set and move as one Legacy design.', 'vars' => array(count($sets['sets']))))) . '</div></div>
        <div class="card-body p-0 table-responsive">' . $output_table . '</div>
    </div>
    ' . $output_classic . $output_runs . '
</main>
' .
output_footer();
