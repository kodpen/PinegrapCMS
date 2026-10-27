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

// only ever appended to further below, so it has to start out empty
$output_rows = '';

$user = validate_user();
validate_contacts_access($user);

// An id sent as an array (?id[]=x) counts as not given: kept in the session,
// it gave every later visit an error page.
if (isset($_GET['id']) && !is_string($_GET['id'])) {
    unset($_GET['id']);
}

// if user does not have access to contact, then output error
if (validate_contact_access($user, ($_GET['id'] ?? '')) == false) {
    log_activity(lang('access denied to view orders for contact because user does not have access to a contact group that the contact is in'), $_SESSION['sessionusername']);
    output_error(lang('Access denied.'));
}

// if id was set, update session
if (isset($_GET['id'])) {
    // store id in session
    $_SESSION['software']['ecommerce']['view_orders_for_contact']['id'] = $_GET['id'];
}

$id = ($_SESSION['software']['ecommerce']['view_orders_for_contact']['id'] ?? '');

if (!is_scalar($id)) {
    $id = '';
}

// If a screen was passed and it is a positive integer, then use it.
// These checks are necessary in order to avoid SQL errors below for a bogus screen value.
if (
    isset($_REQUEST['screen'])
    and $_REQUEST['screen']
    and is_numeric($_REQUEST['screen'])
    and $_REQUEST['screen'] > 0
    and $_REQUEST['screen'] == round($_REQUEST['screen'])
) {
    $screen = (int) $_REQUEST['screen'];

// Otherwise, use the default, which is the first screen.
} else {
    $screen = 1;
}

// Get contact name and last name
$query = "SELECT first_name, last_name FROM contacts WHERE id = ?";
$result = db_q($query, array((string) $id)) or output_error('Query failed.');
$row = mysqli_fetch_assoc($result);

// Stop when there is no such contact (no id given, stale link, contact deleted
// meanwhile): a "not found" screen instead of an empty heading, PHP notices
// and an order list matched on a blank contact.
if (!$row) {
    output_error(lang('Record not found.') . ' <a href="javascript:history.go(-1)">' . lang('Go back') . '</a>.', 404);
}

$first_name = $row['first_name'];
$last_name = $row['last_name'];

// The columns this list sorts by, in the order the table shows them.
// pg_list_state() keeps the choice in the session as a code, so it survives
// a language change, and falls back to the default for anything else.
$sort_columns = array(
    'order_number' => lang('Order Number'),
    'total'        => lang('Total'),
    'order_date'   => lang('Order Date'),
);

$sort_default = array('sort' => 'order_date', 'order' => 'desc');

$list_state = pg_list_state('view_orders_for_contact', $sort_columns, $sort_default);
$sort_levels = $list_state['sorts'];

// What each sort code orders by. Only codes pg_list_state() let through
// reach pg_list_order_by(), and it writes nothing but these.
$order_by = pg_list_order_by($sort_levels, array(
    'order_number' => 'order_number',
    'total'        => 'total',
    'order_date'   => 'order_date',
));

// The sort links keep the contact in the address, so the access check at
// the top sees the same contact the list shows.
$sort_query = array('id' => $id);
$sort_extras = h('&id=' . urlencode($id));

// set where clause
$where = "WHERE contact_id = ? ";


// get total number of results for all screens, so that we can output links to different screens
$query = "SELECT count(id) " .
         "FROM orders " .
         $where;
$result = db_q($query, array((string) $id)) or output_error('Query failed.');
$row = mysqli_fetch_row($result);
$number_of_results = $row[0];


/* get results for just this screen*/
$query = "SELECT id, order_number, total, order_date ".
         "FROM orders ".
         $where.
         "ORDER BY $order_by ";
$result = db_q($query, array((string) $id)) or output_error('Query failed.');
while ($row = mysqli_fetch_assoc($result)) {
    $row['total'] =                 sprintf("%01.2lf", $row['total'] / 100);
    $row['order_date'] = get_relative_time(array('timestamp' => $row['order_date']));

    $output_link_url = 'view_order.php?id=' . $row['id'];

    $output_rows .=
    '<tr class="unselectable ">
        
        <td class="align-middle text-start action-buttons">
            <button type="button" class="m-1 btn-data-control btn btn-outline theme-primary border-2 " title="' . lang('Edit') . '" onclick="window.location.href=\'' . $output_link_url . '\'"><i class="bi bi-pencil"></i></button>
            <!--<button type="button" class="m-1 btn-data-control btn btn-outline theme-danger border-2 " title="' . lang('Delete') . '" ><i class="material-icons">delete</i></button>-->
        </td>
        <td>' . h($row['order_number'] ?? '') . '</td>
        <td nowrap>' . h($row['total']) . '</td>
        <td nowrap>' . $row['order_date'] . '</td>
    </tr>';
}

echo
pg_page_shell(
    array(
        'title'=> lang('All Orders for Contact') . ' : ' . h($first_name . ' ' . $last_name),
        'extra classes'=>'contact',
        'icon'=>'contact',
        'heading'=>lang('All Orders for Contact'),
        'heading_description' => lang('All orders for this contact.'),
        'cancel'=>array('enable'=>'true','url'=>'view_contacts.php'),
        'breadcrumb' => array(
            array('label' => lang('All My Contacts'), 'url' => OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/view_contacts.php'),
            array('label' => lang('Edit Contact'), 'url' => OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/edit_contact.php?id=' . $id),
            array('label' => lang('All Orders for Contact')),
        ),
    )
) . '
<main id="content" class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="row mb-3  flex-wrap">
                <div class="col-12 text-center md:text-start">
                    <nav id="button_bar" class="navigation " aria-label="Button Bar">
                        <div class="pg-toolbar-grow"></div>
                        ' . pg_list_sort_menu('view_orders_for_contact.php', $sort_columns, $sort_levels, $sort_default, $sort_query) . '
                    </nav>
                </div>
            </div>
            <div class="card my-7">
                <div class="card-body p-0 position-relative">
                    <table class="chart table-hover table " style="width:100%;display:none">
                        <thead>
                            <tr>
                                <th class="noVis">' . lang(array('string'=>'Action') ) . '</th>
                                <th nowrap>' . get_column_heading(lang('Order Number'), $sort_levels, '', $sort_extras, 'order_number') . '</th>
                                <th nowrap>' . get_column_heading(lang('Total'), $sort_levels, '', $sort_extras, 'total') . '</th>
                                <th nowrap>' . get_column_heading(lang('Order Date'), $sort_levels, '', $sort_extras, 'order_date') . '</th>
                            </tr>
                        </thead>
                        ' . $output_rows . '
                    </table>
                </div>
            </div>
        </div>
    </div>
</main>
' .
    output_footer();