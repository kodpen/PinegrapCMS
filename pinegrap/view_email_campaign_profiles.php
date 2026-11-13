<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Originally developed as LiveSite by Camelback Web Architects.
 * Since 2017, maintained and evolved by Erdal Güral (Kodpen) under the name Pinegrap.
 * The final LiveSite update (2019) has been integrated into Pinegrap.
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
// functions.php does not load the list table driver; the list below is drawn
// with it (pg_list_table_printed() and its cell helpers).
require_once(PG_FUNCTIONS_DIR . '/includes/fn/list_table.php');
require_once(PG_FUNCTIONS_DIR . '/includes/fn/email_campaign_schedule.php');
require_once(PG_FUNCTIONS_DIR . '/includes/forms/email_campaign_profile_form.php');
$user = validate_user();
validate_email_access($user);

include_once('liveform.class.php');
$liveform = new liveform('view_email_campaign_profiles');

// The columns this list sorts by, in the order the table shows them.
// pg_list_state() keeps the choice in the session as a code, so it survives
// a language change, and falls back to the default for anything else. The
// CSV export is written in the same order.
$sort_columns = array(
    'name'          => lang('Name'),
    'enabled'       => lang('Enabled'),
    'action'        => lang('Action'),
    'subject'       => lang('Subject'),
    'page_to_send'  => lang('Page to Send'),
    'purpose'       => lang('Purpose'),
    'created'       => lang('Created'),
    'last_modified' => lang('Last Modified'),
);

$sort_default = array('sort' => 'last_modified', 'order' => 'desc');

$list_state = pg_list_state('view_email_campaign_profiles', $sort_columns, $sort_default);
$sort_levels = $list_state['sorts'];

// The name each action is shown under; the Action column sorts by it.
$action_names = array(
    'calendar_event_reserved' => lang('Calendar Event Reserved'),
    'custom_form_submitted' => lang('Custom Form Submitted'),
    'email_campaign_sent' => lang('Auto Campaign Sent'),
    'order_abandoned' => lang('Order Abandoned'),
    'order_completed' => lang('Order Completed'),
    'order_review_request' => lang('Order Review Request'),
    'order_shipped' => lang('Order Shipped'),
    'order_delivered' => lang('Order Delivered'),
    'subscription_renewed' => lang('Subscription Renewed'),
    'subscription_failed' => lang('Subscription Payment Failed'),
    'subscription_cancelled' => lang('Subscription Cancelled'),
    'subscription_price_changed' => lang('Subscription Price Changed'),
    'product_ordered' => lang('Product Ordered'),
    'booking_confirmed' => lang('Booking Confirmed'),
    'booking_reminder' => lang('Booking Reminder'),
    'booking_rescheduled' => lang('Booking Rescheduled'),
    'booking_cancelled' => lang('Booking Cancelled'),
    'contact_group_joined' => lang('Joined a Contact Group'),
    'credit_issued' => lang('Store Credit Issued'),
    'credit_expiring' => lang('Store Credit Expiring'),
);

// What each sort code orders by. Only codes pg_list_state() let through
// reach pg_list_order_by(), and it writes nothing but these.
$order_by = pg_list_order_by($sort_levels, array(
    'name'          => 'email_campaign_profiles.name',
    'enabled'       => 'email_campaign_profiles.enabled',
    'action'        => pg_list_label_order('email_campaign_profiles.action', $action_names),
    'subject'       => 'email_campaign_profiles.subject',
    'page_to_send'  => 'page.page_name',
    'purpose'       => 'email_campaign_profiles.purpose',
    'created'       => 'email_campaign_profiles.created_timestamp',
    'last_modified' => 'email_campaign_profiles.last_modified_timestamp',
));

$all_email_campaign_profiles = 0;

// get the total number of email campaign profiles
$query = "SELECT COUNT(*) FROM email_campaign_profiles";
$result = mysqli_query(db::$con, $query) or output_error('Query failed.');
$row = mysqli_fetch_row($result);
$all_email_campaign_profiles = $row[0];

$where = "";


// If user has a user role, then only get profiles that he/she created.
if (USER_ROLE == 3) {
    // If where is blank, then prepare where in a certain way.
    if ($where == '') {
        $where = "WHERE (email_campaign_profiles.created_user_id = '" . USER_ID . "')";

    // Otherwise where is not blank, so prepare where in a different way.
    } else {
        $where .= "AND (email_campaign_profiles.created_user_id = '" . USER_ID . "')";
    }
}

// If user requested to export, then export them.
if (isset($_GET['export'])) {
    pg_csv_download_start('campaign_profiles.csv');

    // Output column headings for CSV data.
    echo
        '"name",' .
        '"enabled",' .
        '"action",' .
        '"action_item",' .
        '"action_item_id",' .
        '"subject",' .
        '"format",' .
        '"body",' .
        '"page_name",' .
        '"page_id",' .
        '"from_name",' .
        '"from_email_address",' .
        '"reply_email_address",' .
        '"bcc_email_address",' .
        '"schedule_length",' .
        '"schedule_unit",' .
        '"schedule_period",' .
        '"schedule_base",' .
        '"schedule_time",' .
        '"purpose",' .
        '"created",' .
        '"created_username",' .
        '"last_modified",' .
        '"last_modified_username"' . "\n";

    // Get all campaign profiles.
    $email_campaign_profiles = db_items(
        "SELECT
            email_campaign_profiles.name,
            email_campaign_profiles.enabled,
            email_campaign_profiles.action,
            email_campaign_profiles.action_item_id,
            email_campaign_profiles.subject,
            email_campaign_profiles.format,
            email_campaign_profiles.body,
            email_campaign_profiles.page_id,
            email_campaign_profiles.from_name,
            email_campaign_profiles.from_email_address,
            email_campaign_profiles.reply_email_address,
            email_campaign_profiles.bcc_email_address,
            email_campaign_profiles.schedule_time,
            email_campaign_profiles.schedule_length,
            email_campaign_profiles.schedule_unit,
            email_campaign_profiles.schedule_period,
            email_campaign_profiles.schedule_base,
            email_campaign_profiles.purpose,
            email_campaign_profiles.created_timestamp,
            created_user.user_username AS created_username,
            email_campaign_profiles.last_modified_timestamp,
            last_modified_user.user_username AS last_modified_username
        FROM email_campaign_profiles
        LEFT JOIN page ON email_campaign_profiles.page_id = page.page_id
        LEFT JOIN user AS created_user ON email_campaign_profiles.created_user_id = created_user.user_id
        LEFT JOIN user AS last_modified_user ON email_campaign_profiles.last_modified_user_id = last_modified_user.user_id
        $where
        ORDER BY $order_by");

    // Loop through the campaign profiles in order to output CSV data.
    foreach ($email_campaign_profiles as $email_campaign_profile) {
        // The label of the trigger item, "(deleted)" when its row is gone.
        $action_item = pg_email_campaign_profile_action_item_label(
            $email_campaign_profile['action'],
            $email_campaign_profile['action_item_id'],
            pg_email_campaign_profile_action_item_row($email_campaign_profile['action'], $email_campaign_profile['action_item_id'])
        );

        $page_name = '';

        // If the format is "html", then prepare values for that.
        if ($email_campaign_profile['format'] == 'html') {
            $email_campaign_profile['body'] = '';

            // A binned page under its former name, a gone one as "(deleted)".
            $page_name = pg_page_record_name($email_campaign_profile['page_id']);

        // Otherwise the format is "plain_text", so prepare values for that.
        } else {
            $email_campaign_profile['page_id'] = '';
        }

        $schedule_time = '';

        if ($email_campaign_profile['schedule_time'] != '00:00:00') {
            $schedule_time = prepare_form_data_for_output($email_campaign_profile['schedule_time'], 'time');
        }

        echo
            '"' . escape_csv($email_campaign_profile['name']) . '",' .
            '"' . $email_campaign_profile['enabled'] . '",' .
            '"' . $email_campaign_profile['action'] . '",' .
            '"' . escape_csv($action_item) . '",' .
            '"' . $email_campaign_profile['action_item_id'] . '",' .
            '"' . escape_csv($email_campaign_profile['subject']) . '",' .
            '"' . $email_campaign_profile['format'] . '",' .
            '"' . escape_csv($email_campaign_profile['body']) . '",' .
            '"' . escape_csv($page_name) . '",' .
            '"' . $email_campaign_profile['page_id'] . '",' .
            '"' . escape_csv($email_campaign_profile['from_name']) . '",' .
            '"' . escape_csv($email_campaign_profile['from_email_address']) . '",' .
            '"' . escape_csv($email_campaign_profile['reply_email_address']) . '",' .
            '"' . escape_csv($email_campaign_profile['bcc_email_address']) . '",' .
            '"' . $email_campaign_profile['schedule_length'] . '",' .
            '"' . $email_campaign_profile['schedule_unit'] . '",' .
            '"' . $email_campaign_profile['schedule_period'] . '",' .
            '"' . $email_campaign_profile['schedule_base'] . '",' .
            '"' . $schedule_time . '",' .
            '"' . $email_campaign_profile['purpose'] . '",' .
            '"' . pg_csv_export_time($email_campaign_profile['created_timestamp']) . '",' .
            '"' . escape_csv($email_campaign_profile['created_username']) . '",' .
            '"' . pg_csv_export_time($email_campaign_profile['last_modified_timestamp']) . '",' .
            '"' . escape_csv($email_campaign_profile['last_modified_username']) . '"' . "\n";
    }

    exit;

// Otherwise the user did not select to export, so just list the campaign profiles.
} else {

    // Get all email campaign profiles.
    $query =
        "SELECT
            email_campaign_profiles.id,
            email_campaign_profiles.name,
            email_campaign_profiles.enabled,
            email_campaign_profiles.action,
            email_campaign_profiles.action_item_id,
            email_campaign_profiles.subject,
            email_campaign_profiles.format,
            email_campaign_profiles.page_id,
            email_campaign_profiles.schedule_time,
            email_campaign_profiles.schedule_length,
            email_campaign_profiles.schedule_unit,
            email_campaign_profiles.schedule_period,
            email_campaign_profiles.schedule_base,
            email_campaign_profiles.purpose,
            created_user.user_username AS created_username,
            email_campaign_profiles.created_timestamp,
            last_modified_user.user_username AS last_modified_username,
            email_campaign_profiles.last_modified_timestamp
        FROM email_campaign_profiles
        LEFT JOIN page ON email_campaign_profiles.page_id = page.page_id
        LEFT JOIN user AS created_user ON email_campaign_profiles.created_user_id = created_user.user_id
        LEFT JOIN user AS last_modified_user ON email_campaign_profiles.last_modified_user_id = last_modified_user.user_id
        $where
        ORDER BY $order_by";
    $result = mysqli_query(db::$con, $query) or output_error('Query failed.');
    $email_campaign_profiles = mysqli_fetch_items($result);

    // Every profile is printed: the list table driver sorts and searches
    // them in the browser (browser mode) and keeps a heading's sort. The
    // name opens the profile.
    $list_rows = array();

    foreach ($email_campaign_profiles as $email_campaign_profile) {
        $output_action = isset($action_names[$email_campaign_profile['action']]) ? $action_names[$email_campaign_profile['action']] : '';

        // The label of the trigger item, "(deleted)" when its row is gone.
        $output_action_item = h(pg_email_campaign_profile_action_item_label(
            $email_campaign_profile['action'],
            $email_campaign_profile['action_item_id'],
            pg_email_campaign_profile_action_item_row($email_campaign_profile['action'], $email_campaign_profile['action_item_id'])
        ));

        $output_page_name = '';

        // An HTML profile sends a page: a binned page under its former name,
        // a gone one as "(deleted)".
        if ($email_campaign_profile['format'] == 'html') {
            $output_page_name = h(pg_page_record_name($email_campaign_profile['page_id']));
        }

        $output_schedule_time = '';

        if ($email_campaign_profile['schedule_time'] != '00:00:00') {
            $output_schedule_time = date(pg_time_format_code(), strtotime($email_campaign_profile['schedule_time']));
        }

        $output_schedule = h(pg_email_campaign_profile_schedule_label($email_campaign_profile['schedule_length'], $email_campaign_profile['schedule_unit'], $email_campaign_profile['schedule_period'], $email_campaign_profile['schedule_base'], $output_schedule_time));

        // A disabled profile's name is shown in the danger colour.
        $name_class = ($email_campaign_profile['enabled'] == 1) ? '' : ' class="fg-danger"';

        $list_rows[] = array(
            'id' => (int) $email_campaign_profile['id'],
            'cells' => array(
                'name' => pg_list_cell('<a href="edit_email_campaign_profile.php?id=' . (int) $email_campaign_profile['id'] . '"' . $name_class . ' data-pg-open>' . h($email_campaign_profile['name']) . '</a>'),
                'enabled' => pg_list_table_check_cell($email_campaign_profile['enabled'] == 1),
                'action' => pg_list_cell($output_action !== '' ? '<span class="badge theme-primary fw-light">' . h($output_action) . '</span>' : '', 'align-middle', array('data-pg-sort-value' => $output_action)),
                'action_item' => pg_list_cell($output_action_item),
                'subject' => pg_list_cell(h($email_campaign_profile['subject'])),
                'page_to_send' => pg_list_cell($output_page_name),
                'schedule' => pg_list_cell('<span class="badge border border-1 border-inverse fw-light bg-subtle-secondary fg-emphasis-secondary">' . $output_schedule . '</span>'),
                'purpose' => pg_list_cell(lang(h(ucwords((string) $email_campaign_profile['purpose'])))),
                'created' => pg_list_table_when_cell($email_campaign_profile['created_timestamp'], (string) $email_campaign_profile['created_username']),
                'last_modified' => pg_list_table_when_cell($email_campaign_profile['last_modified_timestamp'], (string) $email_campaign_profile['last_modified_username']),
            ),
        );
    }

    $list = pg_list_table_printed(array(
        'list' => 'view_email_campaign_profiles',
        'columns' => array(
            'name' => array('heading' => lang('Name')),
            'enabled' => array('heading' => lang('Enabled')),
            'action' => array('heading' => lang('Action'), 'kind' => 'badge'),
            'action_item' => array('heading' => lang('Action Item'), 'sortable' => false),
            'subject' => array('heading' => lang('Subject')),
            'page_to_send' => array('heading' => lang('Page to Send')),
            'schedule' => array('heading' => lang('Schedule'), 'sortable' => false),
            'purpose' => array('heading' => lang('Purpose')),
            'created' => array('heading' => lang('Created'), 'kind' => 'date'),
            'last_modified' => array('heading' => lang('Last Modified'), 'kind' => 'date'),
        ),
        'levels' => $sort_levels,
        'rows' => $list_rows,
    ));

    print
    pg_page_shell(
        array(
            'title'=> lang('My Campaign Profiles'),
            'extra classes'=>'campaign pg-measure-list',
            'icon'=>'campaign', 
            'heading'=> lang('My Campaign Profiles'),
            'heading_description' => lang('Use Campaign Profiles to schedule e-mails automatically for certain actions (e.g. Calendar Event reservation).'),
            'cancel'=>array('enable'=>'true','url'=>'view_email_campaigns.php')
        )
    )    . '
<main id="content" class="container-fluid">
            <div class="row">
            <div class="col-12">
                ' . $liveform->output_errors() . '
                ' . $liveform->get_warnings() . '
                ' . $liveform->output_notices() . '
               
                ' . pg_screen_toolbar(array(
                    'primary'   => array('label' => lang('Create'), 'url' => 'add_email_campaign_profile.php', 'icon' => 'bi-plus-circle'),
                    'secondary' => array(
                        array('label' => lang('Import'), 'url' => 'import_email_campaign_profiles.php', 'icon' => 'bi-box-arrow-in-right'),
                        '<form class="disable_shortcut" method="get"><button type="submit" name="export" value="Export" class="btn btn-sm btn-text theme-secondary"><i class="bi bi-file-earmark-arrow-down me-1" aria-hidden="true"></i>' . lang('Export') . '</button></form>',
                    ),
                    'search'    => array(pg_list_table_search_box('view_email_campaign_profiles'), pg_list_sort_menu('view_email_campaign_profiles.php', $sort_columns, $sort_levels, $sort_default)),
                )) . '
                ' . pg_list_table_html($list['source'], $list['page'], lang('My Campaign Profiles')) . '
            </div>
        </div>
    
</main>
' . pg_js_bundle_tags('assets/js/pg_list_table.min.js') .
    output_footer();
    $liveform->remove_form();
}
?>