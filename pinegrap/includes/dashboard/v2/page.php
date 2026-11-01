<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * The new dashboard, drawn by welcome.php for a person who turned it on:
 * the same page shell, title and heading as the old dashboard, the switch
 * row, the greeting card and the card grid. Included in welcome.php's scope
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
) . '<main id="content" class="container-fluid pg-dash">
    ' . pg_dashboard_v2_switch_row(true) . '
    ' . $liveform->output_errors() . '
    ' . $liveform->get_warnings() . '
    ' . $liveform->output_notices() . '
    <div class="pg-dash-top">
        ' . pg_dashboard_v2_greeting($user, pg_dashboard_v2_row($user['id'])) . '
    </div>
    ' . pg_dashboard_v2_grid($user) . '
    ' . pg_js_bundle_tags('assets/js/pg_dash.min.js') . '
</main>' .
output_footer();
$liveform->remove_form();
