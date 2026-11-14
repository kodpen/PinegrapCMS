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
require_once(PG_FUNCTIONS_DIR . '/includes/fn/messages_area.php');
require_once(PG_FUNCTIONS_DIR . '/includes/fn/email_campaign_list.php');
require_once(PG_FUNCTIONS_DIR . '/includes/fn/sms_campaigns.php');
require_once(PG_FUNCTIONS_DIR . '/includes/fn/messages_campaigns.php');

$user = validate_user();
validate_email_access($user);

$liveform = new liveform('view_email_campaigns');

// SMS campaigns are listed once their tables are there; the channel filter
// and the SMS choice of New campaign follow.
$sms = pg_sms_campaigns_ready();
$filters = pg_messages_campaign_filters($_GET, $sms);
$self = 'view_email_campaigns.php';
$self_params = array_filter(array(
    'channel' => ($filters['channel'] !== 'all') ? $filters['channel'] : '',
    'state' => ($filters['state'] !== 'active') ? $filters['state'] : '',
), 'strlen');
$self_query = http_build_query($self_params);

// Delete Selected: the list's ids name both tables (even e-mail, odd SMS);
// a role 3 user deletes only campaigns of their own. A queued SMS campaign's
// messages stay in the SMS queue.
if ($_POST) {
    validate_token_field();

    $ids = pg_messages_campaign_split_ids($_POST['campaigns'] ?? array());
    $deleted = pg_email_campaigns_delete($ids['email'], $user);

    if ($sms) {
        $deleted += pg_sms_campaigns_delete($ids['sms'], $user);
    }

    if ($deleted > 0) {
        $message = lang(array('string' => '{var:1} campaign(s) were deleted', 'vars' => $deleted));
        log_activity($message, $_SESSION['sessionusername']);
        $liveform->add_notice($message);
    }

    go(PATH . SOFTWARE_DIRECTORY . '/' . $self . (($self_query !== '') ? '?' . $self_query : ''));
}

// Every campaign the user may manage in the filters is printed: the list
// table driver sorts and searches them in the browser (browser mode).
$columns = pg_messages_campaign_columns();
$sort_columns = pg_list_table_printed_sort_columns($columns);
$sort_default = array('sort' => 'last_modified', 'order' => 'desc');
$list_state = pg_list_state('view_email_campaigns', $sort_columns, $sort_default);
$list = pg_messages_campaign_list($filters, $user, $list_state['sorts'], $sms);

// The two filters are segmented controls on one row (look A).
$filter_forms = array();

if ($sms) {
    $filter_forms[] = pg_messages_seg_filter('channel', array('all' => lang('All channels'), 'email' => lang('E-mail'), 'sms' => lang('SMS')), $filters['channel'], 'all', $self, array('state' => $self_params['state'] ?? ''), lang('Channel'));
}

$filter_forms[] = pg_messages_seg_filter('state', array('active' => lang('Active'), 'finished' => lang('Finished')), $filters['state'], 'active', $self, array('channel' => $self_params['channel'] ?? ''), lang('Status'));
$filter_forms = '<div class="pg-messages-filters">' . implode('', $filter_forms) . '</div>';

// The Campaigns card of Site Settings (Opt-In Label, Plain Text Footer) is
// reached from here by who may open the settings.
$secondary = array();

if ($user['role'] < 3) {
    $secondary[] = array('label' => lang('Campaign settings'), 'url' => pg_settings_link('contact', 'pgset-campaigns'), 'icon' => 'bi-sliders');
}

$warning = '';

if ($sms && ($filters['channel'] !== 'email') && !pg_sms_ready()) {
    $warning = pg_messages_note(lang('SMS is not set up: a campaign is kept, but every number is refused until a provider is set up under Settings, Communication.'), ($user['role'] < 3) ? lang('Set up SMS') : '', pg_settings_link('contact', 'pgset-sms'));
}

echo
pg_page_shell(
    array(
        'title' => lang('Campaigns'),
        'extra classes' => 'campaign pg-measure-list',
        'icon' => 'campaign',
        'heading' => lang('Communication'),
        'heading_description' => lang('E-mail and SMS campaigns sent once to many.'),
    )
) . '
<main id="content" class="container-fluid">
    <div class="row">
        <div class="col-12">
            ' . $liveform->output_errors() . '
            ' . $liveform->get_warnings() . '
            ' . $liveform->output_notices() . '
            ' . pg_messages_tab_strip_for('campaigns', $user) . '
            ' . $warning . '
            ' . pg_screen_toolbar(array(
                'primary'   => pg_messages_new_campaign_button($sms),
                'secondary' => $secondary,
                'grow'      => $filter_forms,
                'search'    => array(pg_list_table_search_box('view_email_campaigns'), pg_list_sort_menu($self, $sort_columns, $list_state['sorts'], $sort_default, $self_params)),
            )) . '
            <form name="form" action="' . h($self . (($self_query !== '') ? '?' . $self_query : '')) . '" method="post" class="disable_shortcut">
                ' . get_token_field() . '
                ' . pg_list_table_html($list['source'], $list['page'], lang('Campaigns'), pg_email_campaign_list_delete_button()) . '
            </form>
        </div>
    </div>
</main>
' . pg_js_bundle_tags('assets/js/pg_list_table.min.js') .
output_footer();
$liveform->remove_form();
