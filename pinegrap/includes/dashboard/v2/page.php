<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * The new dashboard, drawn by welcome.php for a person who turned it on:
 * the same page shell, title and heading as the old dashboard, the switch
 * row, the greeting and System Status cards and the card grid, in the
 * person's look. Included in welcome.php's scope
 * ($user, $liveform), which stops after it.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_DASHBOARD_V2')) {
    exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/dashboard/v2/cards.php');
require_once(PG_FUNCTIONS_DIR . '/includes/dashboard/v2/page_greeting.php');
require_once(PG_FUNCTIONS_DIR . '/includes/dashboard/v2/page_grid.php');
require_once(PG_FUNCTIONS_DIR . '/includes/dashboard/v2/page_status.php');

// The site's Appearance values, which the person's own look overrides.
$dashboard_v2_site = false;
$dashboard_v2_result = @mysqli_query(db::$con, "SELECT * FROM dashboard");

if ($dashboard_v2_result) {
    $dashboard_v2_site = mysqli_fetch_assoc($dashboard_v2_result);
}

$dashboard_v2_row = pg_dashboard_v2_row($user['id']);
$dashboard_v2_top = pg_dashboard_v2_greeting_settings((string) ($dashboard_v2_row['layout'] ?? ''), defined('ECOMMERCE') && (ECOMMERCE === true))['top'];

// A person whose role's layout is locked gets no edit button.
$dashboard_v2_edit = pg_dashboard_v2_role_locked((int) $user['role'])
    ? ''
    : '<button type="button" class="btn btn-outline theme-secondary btn-sm" id="pg_dash_edit_btn"><i class="bi bi-pencil" aria-hidden="true"></i> <span class="pg-btn-label">' . h(lang('Edit dashboard')) . '</span></button>';
$dashboard_v2_look = pg_dashboard_v2_look(is_array($dashboard_v2_row) && isset($dashboard_v2_row['look']) ? $dashboard_v2_row['look'] : '', is_array($dashboard_v2_site) ? $dashboard_v2_site : null);

print
pg_page_shell(
    array(
        'title'=> lang('Welcome'),
        'extra classes'=>'setting welcome',
        'icon'=>'welcome',
        'heading'=>lang('Welcome'),
        'heading_description' => lang('Your site at a glance: visitors, orders and health'),
        'head' => '<link rel="stylesheet" href="' . h(pg_dashboard_v2_style_url()) . '">',
    )
) . '<main id="content"' . pg_dashboard_v2_look_attributes($dashboard_v2_look) . '>
    ' . pg_dashboard_v2_switch_row(true, $dashboard_v2_edit) . '
    ' . $liveform->output_errors() . '
    ' . $liveform->get_warnings() . '
    ' . $liveform->output_notices() . '
    <div class="pg-dash-top">
        ' . pg_dashboard_v2_greeting($user) . '
        ' . pg_dashboard_v2_status($user, null, !$dashboard_v2_top['status']) . '
    </div>
    ' . pg_dashboard_v2_grid($user, $dashboard_v2_row) . '
    ' . pg_js_bundle_tags('assets/js/pg_dash.min.js') . '
</main>' .
output_footer();
$liveform->remove_form();
