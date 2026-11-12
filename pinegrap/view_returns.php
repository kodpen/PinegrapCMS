<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Returns: the customers' return requests, newest first, filtered by status
 * (open ones by default). Staff open a request to answer it
 * (view_return.php) or approve several at once from here; each approval
 * takes the carrier and code the request's own screen would offer and mails
 * the customer (includes/fn/returns.php).
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

include('init.php');
require_once(PG_FUNCTIONS_DIR . '/includes/fn/list_table.php');

if (pg_post_body_discarded()) {
    output_error(lang('The request was too large for this server and arrived empty.'), 413);
}

$user = validate_user();
validate_ecommerce_access($user);

if (!defined('ECOMMERCE') || (ECOMMERCE !== true)) {
    output_error(lang('E-commerce is not enabled on this site.'));
}

// One value: one sent as an array counts as not given.
pg_request_single_values(array('bulk'));

$liveform = new liveform('view_returns');

// Approve Selected.
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validate_token_field();

    if (!pg_returns_ready()) {
        $liveform->mark_error('_error', h(lang('Returns are not installed yet: the database has to be updated first.')));
        go(PATH . SOFTWARE_DIRECTORY . '/view_returns.php');
    }

    if ((isset($_POST['bulk']) ? (string) $_POST['bulk'] : '') !== 'approve') {
        $liveform->mark_error('_error', h(lang('Invalid request.')));
        go(PATH . SOFTWARE_DIRECTORY . '/view_returns.php');
    }

    $result = pg_returns_bulk_approve(isset($_POST['returns']) ? (array) $_POST['returns'] : array(), (int) $user['id']);

    if ($result['approved'] > 0) {
        $liveform->add_notice(h(lang(array('string' => '{var:1} return request(s) approved.', 'vars' => array($result['approved'])))));
    }

    if ($result['mailed'] < $result['approved']) {
        $liveform->add_notice(h(lang(array('string' => '{var:1} of the customers could not be told by e-mail.', 'vars' => array($result['approved'] - $result['mailed'])))));
    }

    if ($result['skipped'] > 0) {
        $liveform->add_notice(h(lang(array('string' => '{var:1} return(s) were no longer waiting for an answer and were left as they are.', 'vars' => array($result['skipped'])))));
    }

    foreach ($result['errors'] as $error) {
        $liveform->mark_error('_error', h($error));
    }

    if (($result['approved'] === 0) && ($result['skipped'] === 0) && empty($result['errors'])) {
        $liveform->mark_error('_error', h(lang('Choose at least one return request.')));
    }

    go(PATH . SOFTWARE_DIRECTORY . '/view_returns.php');
}

// The status filter is kept for the session, like the order list's.
if (isset($_GET['status']) && is_string($_GET['status'])) {
    $_SESSION['software']['ecommerce']['view_returns']['status'] = $_GET['status'];
}

$filter = isset($_SESSION['software']['ecommerce']['view_returns']['status']) ? (string) $_SESSION['software']['ecommerce']['view_returns']['status'] : 'open';

if (($filter !== 'open') && ($filter !== 'all') && (pg_return_status($filter) === '')) {
    $filter = 'open';
}

$ready = pg_returns_ready();
$rows = $ready ? pg_returns_panel_rows(pg_return_filter_statuses($filter)) : array();
$reasons = pg_return_reasons();
$today = date('Y-m-d');
$list_rows = array();

foreach ($rows as $row) {
    $id = (int) $row['id'];
    $status = (string) $row['status'];
    $quantity = 0;
    $names = array();

    foreach ($row['items'] as $item) {
        $quantity += (int) $item['quantity'];
        $names[] = (string) $item['name'] . ' × ' . (int) $item['quantity'];
    }

    $order_number = ((string) $row['order_number'] !== '') ? (string) $row['order_number'] : (string) (int) $row['order_id'];
    $refund_due = pg_return_refund_due($row);
    $output_refund_due = '';

    if ($refund_due !== '') {
        $output_refund_due = (strcmp($today, $refund_due) > 0)
            ? '<span class="fg-danger fw-semibold">' . h(pg_return_day_text($refund_due)) . '</span>'
            : h(pg_return_day_text($refund_due));
    }

    // The return's number opens it. Approve Selected acts on the requests
    // still waiting for an answer and leaves the others as they are.
    $list_rows[] = array(
        'id' => $id,
        'cells' => array(
            'return' => pg_list_cell('<a href="view_return.php?id=' . $id . '" data-pg-open>#' . $id . '</a>', 'align-middle', array('data-pg-sort-value' => $id)),
            'order' => pg_list_cell('<a href="view_order.php?id=' . (int) $row['order_id'] . '">' . h($order_number) . '</a>'),
            'customer' => pg_list_cell(h($row['customer']) . (((string) $row['email'] !== '') ? '<div class="small fg-secondary">' . h((string) $row['email']) . '</div>' : '')),
            'items' => pg_list_cell(h(lang(array('string' => '{var:1} item(s)', 'vars' => array($quantity)))) . (($names !== array()) ? '<div class="small fg-secondary text-wrap">' . h(implode(', ', $names)) . '</div>' : ''), 'align-middle', array('data-pg-sort-value' => $quantity)),
            'reason' => pg_list_cell(h(isset($reasons[(string) $row['reason']]) ? $reasons[(string) $row['reason']] : '')),
            'status' => pg_list_cell('<span class="' . h(pg_return_panel_badge_class($status)) . '">' . h(pg_return_status_label($status)) . '</span>'),
            'requested' => pg_list_cell(h(pg_return_time_text($row['requested_at'])), 'align-middle', array('data-pg-sort-value' => (int) $row['requested_at'])),
            'refund_due' => pg_list_cell($output_refund_due, 'align-middle', array('data-pg-sort-value' => $refund_due)),
        ),
    );
}

// Every return of the filter is printed: the list table driver sorts and
// searches them in the browser (browser mode). The status workflow (approve,
// receive, refund) runs on the return's own screen, so nothing changes in
// place here.
$columns = array(
    'return' => array('heading' => lang('Return No.')),
    'order' => array('heading' => lang('Order Number')),
    'customer' => array('heading' => lang('Customer')),
    'items' => array('heading' => lang('Items')),
    'reason' => array('heading' => lang('Reason')),
    'status' => array('heading' => lang('Status'), 'kind' => 'badge'),
    'requested' => array('heading' => lang('Requested on'), 'kind' => 'date'),
    'refund_due' => array('heading' => lang('Refund due by'), 'kind' => 'date'),
);
$sort_columns = array();

foreach ($columns as $key => $spec) {
    $sort_columns[$key] = $spec['heading'];
}

$list_state = pg_list_state('view_returns', $sort_columns, array('sort' => 'requested', 'order' => 'desc'));
$list = pg_list_table_printed(array('list' => 'view_returns', 'columns' => $columns, 'levels' => $list_state['sorts'], 'rows' => $list_rows, 'ids_field' => 'returns[]'));

$output_not_ready = $ready ? '' : '
                <div class="alert theme-warning" role="status">' . h(lang('Returns are not installed yet: the database has to be updated first.')) . '</div>';

$output_limit = (count($rows) >= PG_RETURN_PANEL_LIMIT) ? '
                <p class="small fg-secondary">' . h(lang(array('string' => 'The newest {var:1} returns are shown. Choose a status to narrow the list.', 'vars' => array(PG_RETURN_PANEL_LIMIT)))) . '</p>' : '';

// The customers' side is switched off: staff are reminded that only the
// requests they record or that came through the API will show up.
$output_customer_switch = (pg_returns_customer_enabled() || !$ready) ? '' : '
                <div class="alert theme-info small" role="status"><i class="bi bi-info-circle me-3" aria-hidden="true"></i>' . h(lang('Customers cannot ask for returns from their account yet. The switch is ECOMMERCE_ORDER_RETURNS_ALLOWED in the site\'s configuration file.')) . '</div>';

echo
    pg_page_shell(array(
        'title'               => lang('Returns'),
        'extra classes'       => 'store pg-measure-list',
        'icon'                => 'store',
        'heading'             => lang('Returns'),
        'heading_description' => lang('Answer the customers\' return requests and follow the goods back.'),
        'cancel'              => array('enable' => 'true', 'url' => 'view_orders.php', 'title' => lang('All Orders')),
        'breadcrumb'          => array(
            array('label' => lang('All Orders'), 'url' => OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/view_orders.php'),
            array('label' => lang('Returns')),
        ),
    )) . '
<main id="content" class="container-fluid">
    <div class="row">
        <div class="col-12">
            ' . $liveform->output_errors() . '
            ' . $liveform->output_notices() . '
            ' . $output_not_ready . '
            ' . $output_customer_switch . '
            <nav id="button_bar" class="pg-toolbar navigation" aria-label="' . h(lang('Button Bar')) . '">
                ' . ($ready ? '<a class="btn btn-sm btn-solid theme-primary rounded-pill px-5" href="add_return.php"><i class="bi bi-plus-lg me-1" aria-hidden="true"></i>' . h(lang('Record a Return')) . '</a>' : '') . '
                <div class="pg-toolbar-grow"></div>
                ' . pg_list_table_search_box('view_returns') . '
                ' . pg_filter_select('status', pg_return_filter_options(pg_return_status_counts()), $filter, array('label' => lang('Status'), 'action' => 'view_returns.php', 'id' => 'status')) . '
            </nav>
            ' . $output_limit . '
            <form name="form" action="view_returns.php" method="post">
                ' . get_token_field() . '
                ' . pg_list_table_html($list['source'], $list['page'], lang('Returns'), '<button type="submit" name="bulk" value="approve" class="btn btn-sm btn-solid theme-primary" data-confirm-content="' . h(lang('The selected requests will be approved, each with the return carrier and code its own screen offers, and every customer is told by e-mail.')) . '"><i class="bi bi-check2-circle me-1" aria-hidden="true"></i>' . h(lang('Approve Selected')) . '</button>') . '
            </form>
        </div>
    </div>
</main>
' . pg_js_bundle_tags('assets/js/pg_list_table.min.js') . output_footer();

$liveform->remove_form();
