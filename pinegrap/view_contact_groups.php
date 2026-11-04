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
$user = validate_user();
validate_contacts_access($user);

include_once('liveform.class.php');

// The list's sort map (pg_contact_group_list_order_map()); functions.php
// does not load this module.
require_once(PG_FUNCTIONS_DIR . '/includes/fn/contact_list.php');
require_once(PG_FUNCTIONS_DIR . '/includes/fn/list_table.php');

$liveform = new liveform('view_contact_groups');

// if user has access to manage contact groups, then prepare to output add contact group button
if ($user['role'] < 3) {
    $output_add_contact_group_button = '<a class="btn btn-sm btn-solid theme-primary m-1 " href="add_contact_group.php"><span class="bi bi-plus-circle me-3"></span>' . lang(array('string'=>'Create') ) . '</a>';

// else user does not have access to manage contact groups, so prepare to not output buttons for contact groups
} else {
    $output_add_contact_group_button = '';
}

$keys_and_values = '';

// these are only ever incremented further below, so they have to start out at zero
$all_contact_groups = 0;
$my_contact_groups = 0;

// The page asked for, a whole number of 1 or more (pg_list_page_number()).
$screen = pg_list_page_number($_REQUEST['screen'] ?? '');

// The columns this list sorts by, in the order the table shows them.
// pg_list_state() keeps the choice in the session as a code, so it survives
// a language change, and falls back to the default for anything else.
$sort_columns = array(
    'name'          => lang('Name'),
    'subscription'  => lang('Subscription'),
    'type'          => lang('Type'),
    'description'   => lang('Description'),
    'created'       => lang('Created'),
    'last_modified' => lang('Last Modified'),
);

$sort_default = array('sort' => 'last_modified', 'order' => 'desc');

$list_state = pg_list_state('view_contact_groups', $sort_columns, $sort_default);
$sort_levels = $list_state['sorts'];

// What each sort code orders by (the type and the description on the value
// the row shows). Only codes pg_list_state() let through reach
// pg_list_order_by(), and it writes nothing but these.
$order_by = pg_list_order_by($sort_levels, pg_contact_group_list_order_map());

// get all contact groups
$query =
    "SELECT
        contact_groups.id,
        contact_groups.name,
        contact_groups.description,
        contact_groups.email_subscription,
        contact_groups.email_subscription_type,
        created_user.user_username as created_username,
        contact_groups.created_timestamp,
        last_modified_user.user_username as last_modified_username,
        contact_groups.last_modified_timestamp,
        " . (pg_audience_ready() ? 'contact_groups.kind' : "'static' AS kind") . "
    FROM contact_groups
    LEFT JOIN user AS created_user ON contact_groups.created_user_id = created_user.user_id
    LEFT JOIN user AS last_modified_user ON contact_groups.last_modified_user_id = last_modified_user.user_id
    ORDER BY $order_by";

$result = mysqli_query(db::$con, $query) or output_error('Query failed.');

$contact_groups = array();

while ($row = mysqli_fetch_assoc($result)) {

    // Add one to all contact groups.
    $all_contact_groups++;

    // if user has access to contact group then add contact group to contact groups array
    if (validate_contact_group_access($user, $row['id']) == true) {
        $contact_groups[] = $row;

        // Add one to all contact groups.
        $my_contact_groups++;
    }
}

// Members per group, one query for the whole list.
$member_counts = array();

foreach ((array) db_items("SELECT contact_group_id, COUNT(*) AS members FROM contacts_contact_groups_xref GROUP BY contact_group_id") as $count_row) {
    $member_counts[(int) $count_row['contact_group_id']] = (int) $count_row['members'];
}

// Every group the user may see is printed: the list table driver sorts and
// searches them in the browser (browser mode) and keeps a heading's sort.
$list_rows = array();

foreach ($contact_groups as $contact_group) {
    if ($contact_group['email_subscription']) {
        if ($contact_group['email_subscription_type'] == 'open') {
            $email_subscription_type = '<span class="badge fw-light theme-primary">' . lang('Open') . '</span>';
        } else {
            $email_subscription_type = '<span class="badge fw-light theme-warning">' . lang('Closed') . '</span>';
        }

        $description = $contact_group['description'];

    } else {
        $email_subscription_type = '';
        $description = '';
    }

    if ($contact_group['kind'] === 'dynamic') {
        $output_kind = '<span class="badge fw-light theme-info">' . h(lang('By rule')) . '</span>';
    } else {
        $output_kind = '<span class="badge fw-light theme-secondary">' . h(lang('By hand')) . '</span>';
    }

    $members = $member_counts[(int) $contact_group['id']] ?? 0;

    $list_rows[] = array(
        'id' => (int) $contact_group['id'],
        'cells' => array(
            'name' => pg_list_cell('<a href="edit_contact_group.php?id=' . (int) $contact_group['id'] . '" data-pg-open>' . h($contact_group['name']) . '</a>'),
            'kind' => pg_list_cell($output_kind),
            'members' => pg_list_cell(pg_format_number($members, 0), 'align-middle', array('data-pg-sort-value' => $members)),
            'subscription' => pg_list_table_check_cell(!empty($contact_group['email_subscription'])),
            'type' => pg_list_cell($email_subscription_type),
            'description' => pg_list_cell(h($description)),
            'created' => pg_list_table_when_cell($contact_group['created_timestamp'], (string) $contact_group['created_username']),
            'last_modified' => pg_list_table_when_cell($contact_group['last_modified_timestamp'], (string) $contact_group['last_modified_username']),
        ),
    );
}

// Membership and Members have no sort code the session could keep, so
// their headings do not sort.
$list = pg_list_table_printed(array(
    'list' => 'view_contact_groups',
    'columns' => array(
        'name' => array('heading' => lang('Name')),
        'kind' => array('heading' => lang('Membership'), 'kind' => 'badge', 'sortable' => false),
        'members' => array('heading' => lang('Members'), 'kind' => 'number', 'sortable' => false),
        'subscription' => array('heading' => lang('Subscription'), 'kind' => 'check'),
        'type' => array('heading' => lang('Type'), 'kind' => 'badge'),
        'description' => array('heading' => lang('Description')),
        'created' => array('heading' => lang('Created'), 'kind' => 'date'),
        'last_modified' => array('heading' => lang('Last Modified'), 'kind' => 'date'),
    ),
    'levels' => $sort_levels,
    'rows' => $list_rows,
));

print
    pg_page_shell(
        array(
            'title'=> lang('All Contact Groups'),
            'extra classes'=>'contact',
            'icon'=>'contact', 
            'heading'=>lang('All Contact Groups'),
            'heading_description' => lang('All contact groups used to collect and organize contacts and subscribers'),
        )
    ) . '
<main id="content" class="container-fluid">
    <div class="row">
        <div class="col-12">
            ' . $liveform->output_errors() . '
            ' . $liveform->get_warnings() . '
            ' . $liveform->output_notices() . '
            ' . pg_screen_toolbar(array(
                'primary' => $output_add_contact_group_button,
                'search'  => array(pg_list_table_search_box('view_contact_groups'), pg_list_sort_menu('view_contact_groups.php', $sort_columns, $sort_levels, $sort_default)),
            )) . '
            ' . pg_list_table_html($list['source'], $list['page'], lang('All Contact Groups')) . '
        </div>
    </div>
</main>
' . pg_js_bundle_tags('assets/js/pg_list_table.min.js') .
    output_footer();

$liveform->remove_form();
