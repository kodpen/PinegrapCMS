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
 *              2016–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

include('init.php');

// only ever appended to further below, so it has to start out empty
$output_rows = '';

$user = validate_user();
validate_contacts_access($user);

// if user does not have access to contact, then output error
if (validate_contact_access($user, $_GET['id']) == false) {
    log_activity(lang('access denied to view orders for contact because user does not have access to a contact group that the contact is in'), $_SESSION['sessionusername']);
    output_error(lang('Access denied.'));
}

// if id was set, update session
if (isset($_GET['id'])) {
    // store id in session
    $_SESSION['software']['ecommerce']['view_orders_for_contact']['id'] = $_GET['id'];
}

$id = ($_SESSION['software']['ecommerce']['view_orders_for_contact']['id'] ?? '');

// if sort was set, update session
if (isset($_REQUEST['sort'])) {
    // store sort in session
    $_SESSION['software']['ecommerce']['view_orders_for_contact']['sort'] = $_REQUEST['sort'];
}

// if order was set, update session
if (isset($_REQUEST['order'])) {
    // store sort in session
    $_SESSION['software']['ecommerce']['view_orders_for_contact']['order'] = $_REQUEST['order'];
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
$query = "SELECT first_name, last_name FROM contacts WHERE id = '" . escape($id) . "'";
$result = mysqli_query(db::$con, $query) or output_error('Query failed.');
$row = mysqli_fetch_assoc($result);
$first_name = $row['first_name'];
$last_name = $row['last_name'];

// if the sort is not set yet, then default it to empty so that the switch below falls
// through to its default case
if (isset($_SESSION['software']['ecommerce']['view_orders_for_contact']['sort']) == false) {
    $_SESSION['software']['ecommerce']['view_orders_for_contact']['sort'] = '';
}

switch (($_SESSION['software']['ecommerce']['view_orders_for_contact']['sort'] ?? '')) {
    case lang('Order Number'):
        $sort_column = 'order_number';
        break;
    case lang('Total'):
        $sort_column = 'total';
        break;
    case lang('Order Date'):
        $sort_column = 'order_date';
        break;
    default:
        $sort_column = 'order_date';
}
if (!empty($_SESSION['software']['ecommerce']['view_orders_for_contact']['order'])) {
    $asc_desc = ($_SESSION['software']['ecommerce']['view_orders_for_contact']['order'] ?? '');
} else {
    $asc_desc = 'desc';
}

// set where clause
$where = "WHERE contact_id = '" . escape($id) . "' ";


// get total number of results for all screens, so that we can output links to different screens
$query = "SELECT count(id) " .
         "FROM orders " .
         $where;
$result = mysqli_query(db::$con, $query) or output_error('Query failed.');
$row = mysqli_fetch_row($result);
$number_of_results = $row[0];


/* get results for just this screen*/
$query = "SELECT id, order_number, total, order_date ".
         "FROM orders ".
         $where.
         "ORDER BY $sort_column $asc_desc ";
$result = mysqli_query(db::$con, $query) or output_error('Query failed.');
while ($row = mysqli_fetch_assoc($result)) {
    $row['total'] =                 sprintf("%01.2lf", $row['total'] / 100);
    $row['order_date'] = get_relative_time(array('timestamp' => $row['order_date']));

    $output_link_url = 'view_order.php?id=' . $row['id'];

    $output_rows .=
    '<tr class="unselectable ">
        
        <td class="align-middle text-start action-buttons">
            <button type="button" class="m-1 btn-data-control btn btn-outline-primary border-2 " data-loading-content=" " title="' . lang('Edit') . '" onclick="window.location.href=\'' . $output_link_url . '\'"><i class="bi bi-pencil"></i></button>
            <!--<button type="button" class="m-1 btn-data-control btn btn-outline-danger border-2 " data-loading-content=" " title="' . lang('Delete') . '" ><i class="material-icons">delete</i></button>-->
        </td>
        <td class="' . $row_style .'">' . h($row['order_number']) . '</td>
        <td class="' . $row_style .'" nowrap>' . h($row['total']) . '</td>
        <td class="' . $row_style .'" nowrap>' . $row['order_date'] . '</td>
    </tr>';
}

echo
pg_page_shell(
    array(
        'title'=> lang('All Orders for Contact') . ' : ' . $first_name  . ' ' . $last_name,
        'extra classes'=>'contact',
        'icon'=>'contact',
        'heading'=>lang('All Orders for Contact'),
        'cancel'=>array('enable'=>'true','url'=>'view_contacts.php'),
        'breadcrumb' => array(
            array('label' => lang('All My Contacts'), 'url' => OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/view_contacts.php'),
            array('label' => lang('Edit Contact'), 'url' => OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/edit_contact.php?id=' . $id),
            array('label' => lang('All Orders for Contact')),
        ),
    )
) . '
    <div class="row">
        <div class="col-12">



            <div class="row mb-2  flex-wrap">
                <div class="col-12 text-center text-md-start">
<h2 class="d-inline-block text-break" data-bs-content="' . lang('All orders for this contact.') . '" title="' . lang('All Orders for Contact') . '">[' . h($first_name)  . ' ' . h($last_name)  . ']</h2>
                </div>
            </div>
            <div class="card my-4">
                <div class="card-body p-0 position-relative">
                    <table class="chart table-hover table " style="width:100%;display:none">
                        <thead>
                            <tr>
                                <th class="noVis">' . lang(array('string'=>'Action') ) . '</th>
                                <th nowrap>' . asc_or_desc(lang('Order Number'),'view_orders_for_contact', $keys_and_values) . '</td>
                                <th nowrap>' . asc_or_desc(lang('Total'),'view_orders_for_contact', $keys_and_values) . '</td>
                                <th nowrap>' . asc_or_desc(lang('Order Date'),'view_orders_for_contact', $keys_and_values) . '</td>
                            </tr>
                        </thead>
                        ' . $output_rows . '
                    </table>
                </div>
            </div>
        </div>
    </div>
</main>' .
    output_footer();