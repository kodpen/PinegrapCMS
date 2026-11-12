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
require_once(PG_FUNCTIONS_DIR . '/includes/fn/list_table.php');

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
    'status'       => lang('Status'),
    'order_date'   => lang('Order Date'),
);

$sort_default = array('sort' => 'order_date', 'order' => 'desc');

$list_state = pg_list_state('view_orders_for_contact', $sort_columns, $sort_default);
$sort_levels = $list_state['sorts'];

// The sort menu's links keep the contact in the address, so the access
// check at the top sees the same contact the list shows.
$sort_query = array('id' => $id);

// The Awaiting Payment badge reads paid_at, which an older schema lacks.
$paid_at_column_exists = pg_schema_has('orders', 'paid_at');

// The status changes in place as on the orders list, through its own
// screen's answer (view_orders.php), for a user who may change orders.
$quick_edit = (($user['role'] < 3) || !empty($user['manage_ecommerce'])) ? pg_order_quick_edit_columns() : array();

// Every order of the contact is printed: the list table driver sorts and
// searches them in the browser (browser mode). An open cart (status
// incomplete) is not an order yet: it has no number and a zero total, and
// the contacts list's My Customers view leaves it out too.
$list_rows = array();

$query = "SELECT id, order_number, total, order_date, status, payment_method, parasut_exported, parasut_invoice_id" . ($paid_at_column_exists ? ", paid_at" : "") . "
    FROM orders
    WHERE contact_id = ? AND status != 'incomplete'
    ORDER BY order_date DESC";
$result = db_q($query, array((string) $id)) or output_error('Query failed.');

while ($row = mysqli_fetch_assoc($result)) {
    $order_id = (int) $row['id'];
    $status = (string) $row['status'];
    $list_rows[] = array(
        'id' => $order_id,
        'cells' => array(
            'order_number' => pg_list_cell('<a href="view_order.php?id=' . $order_id . '&amp;send_to=' . h(urlencode(REQUEST_URL)) . '" data-pg-open>' . h(((string) $row['order_number'] !== '') ? $row['order_number'] : '#' . $order_id) . '</a>'),
            'total' => pg_list_cell(pg_format_money($row['total'] / 100, BASE_CURRENCY_SYMBOL), 'align-middle text-nowrap', array('data-pg-sort-value' => (int) $row['total'])),
            'status' => pg_list_cell(pg_order_list_status_html($row, $paid_at_column_exists), 'align-middle text-nowrap',
                (isset($quick_edit['status']['options'][$status]) ? pg_list_edit_cell_attributes($quick_edit, 'status', $order_id, $status) : array()) + array('data-pg-sort-value' => $status)),
            'order_date' => pg_list_cell(get_relative_time(array('timestamp' => $row['order_date'])), 'align-middle text-nowrap', array('data-pg-sort-value' => (int) $row['order_date'])),
        ),
    );
}

$list = pg_list_table_printed(array(
    'list' => 'view_orders_for_contact',
    'columns' => array(
        'order_number' => array('heading' => lang('Order Number')),
        'total' => array('heading' => lang('Total'), 'kind' => 'money'),
        'status' => array('heading' => lang('Status'), 'kind' => 'badge', 'editable' => $quick_edit ? 'select' : false),
        'order_date' => array('heading' => lang('Order Date'), 'kind' => 'date'),
    ),
    'levels' => $sort_levels,
    'rows' => $list_rows,
    'edit' => $quick_edit ? array('url' => 'view_orders.php', 'columns' => $quick_edit) : null,
));

echo
pg_page_shell(
    array(
        'title'=> lang('All Orders for Contact') . ' : ' . h($first_name . ' ' . $last_name),
        'extra classes'=>'contact pg-measure-list',
        'icon'=>'contact',
        'heading'=>lang('All Orders for Contact'),
        'heading_description' => lang('All orders for this contact.'),
        'cancel'=>array('enable'=>'true','url'=>'edit_contact.php?id=' . (int) $id),
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
            ' . pg_screen_toolbar(array(
                'search' => array(pg_list_table_search_box('view_orders_for_contact'), pg_list_sort_menu('view_orders_for_contact.php', $sort_columns, $sort_levels, $sort_default, $sort_query)),
            )) . '
            ' . pg_list_table_html($list['source'], $list['page'], lang('All Orders for Contact')) . '
        </div>
    </div>
</main>
' . pg_js_bundle_tags('assets/js/pg_list_table.min.js') . '
' .
    output_footer();