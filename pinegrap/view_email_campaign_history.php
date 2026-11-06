<?php
/**
 * PineGrap - Enterprise Website Platform
 *
 * Originally developed as LiveSite by Camelback Web Architects.
 * Since 2017, maintained and evolved by Erdal Güral (Kodpen) under the name PineGrap.
 * The final LiveSite update (2019) has been integrated into PineGrap.
 * LiveSite remains available as a separate downloadable legacy version.
 *
 * @author      Camelback Web Architects
 *              Erdal Güral (Kodpen)
 * @link        https://livesite.com
 *              https://kodpen.com
 * @copyright   2001–2019 Camelback Consulting, Inc.
 *              2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

include('init.php');
require_once(PG_FUNCTIONS_DIR . '/includes/fn/email_campaign_list.php');

$user = validate_user();
validate_email_access($user);

$liveform = new liveform('view_email_campaign_history');

// The columns this list sorts by; pg_list_state() falls back to the default
// for anything else and keeps the choice in the session as a code, so it
// survives a language change. Every campaign is printed: the list table
// driver sorts and searches them in the browser (browser mode).
$sort_columns = pg_email_campaign_list_sort_columns(email_campaign_job_enabled());
$sort_default = pg_email_campaign_list_sort_default(email_campaign_job_enabled());
$list_state = pg_list_state('view_email_campaign_history', $sort_columns, $sort_default);
$sort_levels = $list_state['sorts'];
$list = pg_email_campaign_list('view_email_campaign_history', array('cancelled', 'complete'), $user, $sort_levels);

echo
pg_page_shell(
    array(
        'title'=> lang('My Campaign History'),
        'extra classes'=>'campaign',
        'icon'=>'campaign', 
        'heading'=>lang('My Campaign History'),
        'heading_description' => lang('All completed and cancelled e-mail campaigns that I can manage.'),
                
    )
)  . '
<main id="content" class="container-fluid">
    <div class="row">
        <div class="col-12">
            ' . $liveform->output_errors() . '
            ' . $liveform->get_warnings() . '
            ' . $liveform->output_notices() . '
            ' . pg_screen_toolbar(array(
                'primary'   => array('label' => lang('Create'), 'url' => OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/add_email_campaign.php', 'icon' => 'bi-plus-circle'),
                'secondary' => array('label' => lang('My Campaigns'), 'url' => 'view_email_campaigns.php', 'icon' => 'bi-megaphone'),
                'search'    => array(pg_list_table_search_box('view_email_campaign_history'), pg_list_sort_menu('view_email_campaign_history.php', $sort_columns, $sort_levels, $sort_default)),
            )) . '
            <form name="form" action="' . OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/delete_email_campaigns.php" method="post" class="disable_shortcut">
                ' . get_token_field() . '
                <input type="hidden" name="send_to" value="' . h(REQUEST_URL) . '" />
                ' . pg_list_table_html($list['source'], $list['page'], lang('My Campaign History'), pg_email_campaign_list_delete_button()) . '
            </form>
        </div>
    </div>
</main>
' . pg_js_bundle_tags('assets/js/pg_list_table.min.js') .
output_footer();
$liveform->remove_form();