<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Accessibility report: the WCAG 2.2 checklist of the published pages and
 * what the nightly accessibility pass (seo_a11y_job.php, includes/fn/a11y.php)
 * found on each, with the checklist as a PDF to download. Reached from the
 * Pages list beside the SEO buttons; managers and up, as the SEO tools.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

include('init.php');
$user = validate_user();
validate_area_access($user, 'manager');

require_once(dirname(__FILE__) . '/seo.php');
require_once(dirname(__FILE__) . '/seo_structure.php');
require_once(PG_FUNCTIONS_DIR . '/includes/fn/a11y.php');

include_once('liveform.class.php');
$liveform = new liveform('view_accessibility');

$ready = pg_seo_structure_schema_ready() && class_exists('DOMDocument');

// ---- Check now -------------------------------------------------------------

// The same pass as the nightly job with a short budget, for a site that has
// not run the job yet. It carries on where the job stopped and the job
// carries on where this stopped. POST with the token: it renders pages.
if ($ready && isset($_POST['submit_check'])) {
    validate_token_field();

    $check_run = pg_a11y_run(defined('SEO_ANALYZE_BUTTON_BUDGET') ? (int) SEO_ANALYZE_BUTTON_BUDGET : 20);

    if ($check_run['done']) {
        pg_cron_ran('seo_a11y_job');
        $liveform->add_notice(lang(array('string' => 'Every published page has been checked ({var:1} in this run).', 'vars' => array($check_run['checked']))));
    } else {
        $liveform->add_notice(lang(array('string' => '{var:1} pages were checked. Click again to continue with the rest.', 'vars' => array($check_run['checked']))));
    }

    log_activity(lang('ran the accessibility check'), $_SESSION['sessionusername']);
    go(PATH . SOFTWARE_DIRECTORY . '/view_accessibility.php');
}

$code = (isset($_GET['code']) && is_string($_GET['code']) && isset(pg_a11y_rules()[$_GET['code']])) ? $_GET['code'] : '';
$runs = pg_cron_last_runs();
$last_run = isset($runs['seo_a11y_job']) ? (int) $runs['seo_a11y_job'] : 0;

// ---- PDF -------------------------------------------------------------------

if ($ready && isset($_GET['pdf'])) {
    $pdf = pg_privacy_export_pdf(pg_a11y_report_html(pg_a11y_report_summary(), pg_a11y_report_rows('', 2000), count(pg_a11y_pages()), $last_run, HOSTNAME_SETTING));

    if ($pdf === false) {
        $liveform->add_notice(lang('The PDF library is not installed.'));
        go(PATH . SOFTWARE_DIRECTORY . '/view_accessibility.php');
    }

    header('Content-Type: application/pdf');
    header('Content-Disposition: attachment; filename="accessibility-report-' . date('Y-m-d') . '.pdf"');
    header('Content-Length: ' . strlen($pdf));
    echo $pdf;
    exit();
}

$shell = array(
    'title'               => lang('Accessibility report'),
    'extra classes'       => 'page',
    'icon'                => 'page',
    'heading'             => lang('Accessibility report'),
    'heading_description' => lang('What the nightly accessibility check found on the published pages, against WCAG 2.2 Level A and the contrast and target size of Level AA. Only what the markup shows can be checked automatically; a person must review the rest.'),
    'breadcrumb'          => array(
        array('label' => lang('Pages'), 'url' => OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/view_pages.php'),
        array('label' => lang('Accessibility report')),
    ),
);

if (!$ready) {
    echo pg_page_shell($shell) . '
<main id="content" class="container-fluid">
    <div class="alert theme-warning my-7">
        <i class="bi bi-exclamation-triangle me-3" aria-hidden="true"></i>' . h(lang('The accessibility check needs the software upgrade and the PHP DOM extension.')) . '
    </div>
</main>' . output_footer();

    $liveform->remove_form();
    exit();
}

// ---- Checklist -------------------------------------------------------------

$summary = pg_a11y_report_summary();
$criteria = pg_a11y_criteria();
$rules = pg_a11y_rules();
$output_checklist = '';

foreach ($rules as $rule_code => $rule) {
    $found = isset($summary[$rule_code]) ? $summary[$rule_code] : null;
    $result = $found
        ? '<a class="link-danger" href="view_accessibility.php?code=' . h($rule_code) . '">' . h(lang(array('string' => '{var:1} page(s), {var:2} time(s)', 'vars' => array($found['pages'], $found['occurrences'])))) . '</a>'
        : '<span class="fg-success"><i class="bi bi-check-lg me-1" aria-hidden="true"></i>' . h(lang('No problem found')) . '</span>';

    $output_checklist .=
        '<tr>
            <td class="align-middle text-nowrap">' . h($rule['criterion']) . ' <span class="fg-secondary">' . h(isset($criteria[$rule['criterion']]) ? $criteria[$rule['criterion']] : '') . '</span></td>
            <td class="align-middle">' . h($rule['level']) . '</td>
            <td class="align-middle">' . h(pg_a11y_issue_label($rule_code)) . '</td>
            <td class="align-middle">' . $result . '</td>
        </tr>';
}

// ---- Findings --------------------------------------------------------------

$row_limit = 1000;
$output_rows = '';
$severity_badges = array(
    'error'   => array('text-bg-danger', lang('Error')),
    'warning' => array('text-bg-warning', lang('Warning')),
    'notice'  => array('text-bg-light border', lang('Notice')),
);

foreach (pg_a11y_report_rows($code, $row_limit) as $row) {
    $badge = isset($severity_badges[$row['severity']]) ? $severity_badges[$row['severity']] : $severity_badges['notice'];

    $output_rows .=
        '<tr>
            <td class="align-middle"><a href="' . OUTPUT_PATH . h(encode_url_path($row['page_name'])) . '" target="_blank" rel="noopener">' . h($row['page_name']) . '</a></td>
            <td class="align-middle"><span class="badge rounded-pill ' . $badge[0] . ' me-1">' . h($badge[1]) . '</span>' . h(pg_a11y_issue_label($row['code'])) . '</td>
            <td class="align-middle">' . (int) $row['occurrences'] . '</td>
            <td class="align-middle small font-monospace text-break">' . h($row['detail']) . '</td>
        </tr>';
}

$code_options = array('' => lang('All checks'));

foreach ($rules as $rule_code => $rule) {
    $code_options[$rule_code] = $rule['criterion'] . ' ' . pg_a11y_issue_label($rule_code);
}

// HTML: the relative time is a <time> element; the texts are escaped here.
$output_status = $last_run
    ? lang(array('string' => 'Last full check: {var:1}.', 'vars' => array(get_relative_time(array('timestamp' => $last_run)))))
    : h(lang('No full check has finished yet.'));

if (!pg_cron_job_is_enabled('seo_a11y_job')) {
    $output_status .= ' ' . h(lang('The nightly check is off: switch on "Accessibility report" under the scheduled jobs in Settings, or check the pages now.'));
}

echo pg_page_shell($shell) . '
<main id="content" class="container-fluid">
    <div class="row">
        <div class="col-12">
            ' . $liveform->output_errors() . '
            ' . $liveform->output_notices() . '
            <nav id="button_bar" class="pg-toolbar my-3" aria-label="' . h(lang('Button Bar')) . '">
                <form method="post" action="view_accessibility.php" class="d-inline">
                    ' . get_token_field() . '
                    <button type="submit" name="submit_check" value="1" class="btn btn-sm btn-ghost"><i class="bi bi-arrow-repeat me-1" aria-hidden="true"></i>' . h(lang('Check Pages Now')) . '</button>
                </form>
                <a class="btn btn-sm btn-ghost" href="view_accessibility.php?pdf=1"><i class="bi bi-file-earmark-pdf me-1" aria-hidden="true"></i>' . h(lang('Download PDF')) . '</a>
                <div class="pg-toolbar-grow"></div>
                ' . pg_filter_select('code', $code_options, $code, array('label' => lang('Check'), 'icon' => 'funnel', 'action' => 'view_accessibility.php')) . '
            </nav>
            <p class="small fg-secondary mb-0">' . $output_status . '</p>
            <div class="card my-7">
                <div class="card-header">' . h(lang('Checklist')) . '</div>
                <div class="card-body p-0">
                    <table class="table table-sm mb-0">
                        <thead><tr><th>' . h(lang('Criterion')) . '</th><th>' . h(lang('Level')) . '</th><th>' . h(lang('Check')) . '</th><th>' . h(lang('Result')) . '</th></tr></thead>
                        <tbody>' . $output_checklist . '</tbody>
                    </table>
                </div>
            </div>
            <div class="card my-7">
                <div class="card-header">' . h(lang('Findings')) . '</div>
                <div class="card-body p-0">
                    ' . (($output_rows === '')
                        ? '<p class="fg-secondary m-7">' . h(lang('Nothing was found.')) . '</p>'
                        : '<table class="table table-sm table-hover mb-0">
                            <thead><tr><th>' . h(lang('Page')) . '</th><th>' . h(lang('Problem')) . '</th><th>' . h(lang('Times')) . '</th><th>' . h(lang('Example')) . '</th></tr></thead>
                            <tbody>' . $output_rows . '</tbody>
                        </table>') . '
                </div>
            </div>
        </div>
    </div>
</main>
' . output_footer();

$liveform->remove_form();
