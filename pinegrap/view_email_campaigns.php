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

$liveform = new liveform('view_email_campaigns');

if (email_campaign_job_enabled()) {
    $output_subheading_ready_status = lang('scheduled');
} else {
    $output_subheading_ready_status = lang('ready-to-send');
}


// The columns this list sorts by; pg_list_state() falls back to the default
// for anything else and keeps the choice in the session as a code, so it
// survives a language change. Every campaign is printed: the list table
// driver sorts and searches them in the browser (browser mode).
$sort_columns = pg_email_campaign_list_sort_columns(email_campaign_job_enabled());
$sort_default = pg_email_campaign_list_sort_default(email_campaign_job_enabled());
$list_state = pg_list_state('view_email_campaigns', $sort_columns, $sort_default);
$sort_levels = $list_state['sorts'];
$list = pg_email_campaign_list('view_email_campaigns', array('ready', 'paused'), $user, $sort_levels);

// SMS campaigns sit beside the e-mail campaigns once SMS is set up
// (view_sms_campaigns.php).
$output_sms_campaigns_link = '';

if (pg_sms_ready() && pg_schema_has('sms_campaigns')) {
    $output_sms_campaigns_link = array('label' => lang('My SMS Campaigns'), 'url' => 'view_sms_campaigns.php', 'icon' => 'bi-chat-text');
}

echo
pg_page_shell(
    array(
        'title'=> lang('My Campaigns'),
        'extra classes'=>'campaign',
        'icon'=>'campaign', 
        'heading'=>lang('My Campaigns'),
        'heading_description' => lang(array('string'=>'All {var:1} and paused e-mail campaigns that I can manage.  See My Campaign History for completed and cancelled campaigns.','vars'=>$output_subheading_ready_status)),
                
    )
) . '
<main id="content" class="container-fluid">
    <div class="row">
        <div class="col-12">
            ' . $liveform->output_errors() . '
            ' . $liveform->get_warnings() . '
            ' . $liveform->output_notices() . '
           
            ' . pg_screen_toolbar(array(
                'primary'   => array('label' => lang('Create'), 'url' => 'add_email_campaign.php', 'icon' => 'bi-plus-circle'),
                'secondary' => array(
                    array('label' => lang('My Campaign History'), 'url' => 'view_email_campaign_history.php', 'icon' => 'bi-clock-history'),
                    $output_sms_campaigns_link,
                ),
                'search'    => array(pg_list_table_search_box('view_email_campaigns'), pg_list_sort_menu('view_email_campaigns.php', $sort_columns, $sort_levels, $sort_default)),
            )) . '
            <form name="form" action="delete_email_campaigns.php" method="post" class="disable_shortcut">
                ' . get_token_field() . '
                <input type="hidden" name="send_to" value="' . h(REQUEST_URL) . '" />
                ' . pg_list_table_html($list['source'], $list['page'], lang('My Campaigns'), pg_email_campaign_list_delete_button()) . '
            </form>
        </div>
    </div>
</main>
' . pg_js_bundle_tags('assets/js/pg_list_table.min.js') .
output_footer();
$liveform->remove_form();