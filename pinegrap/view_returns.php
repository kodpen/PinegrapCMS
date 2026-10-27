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

if (pg_post_body_discarded()) {
    output_error(lang('The request was too large for this server and arrived empty.'), 413);
}

$user = validate_user();
validate_ecommerce_access($user);

if (!defined('ECOMMERCE') || (ECOMMERCE !== true)) {
    output_error(lang('E-commerce is not enabled on this site.'));
}

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
$output_rows = '';

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

    // Only a request waiting for an answer can be approved from the list.
    $selectable = ($status === 'requested');

    $output_rows .= '
            <tr id="' . $id . '"' . ($selectable ? '' : ' class="unselectable"') . '>
                <td class="select-all align-middle text-start">' . ($selectable ? '<input class="check" type="checkbox" name="returns[]" value="' . $id . '" aria-label="' . h(lang(array('string' => 'Select return request #{var:1}', 'vars' => array($id)))) . '">' : '') . '</td>
                <td class="align-middle text-start col-reorder-none action-buttons">
                    <a class="btn-data-control m-1 btn btn-outline theme-primary border-2" href="view_return.php?id=' . $id . '" title="' . h(lang('Open the return')) . '" aria-label="' . h(lang('Open the return')) . '"><i class="bi bi-box-arrow-in-right" aria-hidden="true"></i></a>
                </td>
                <td class="align-middle" data-order="' . $id . '">#' . $id . '</td>
                <td class="align-middle"><a href="view_order.php?id=' . (int) $row['order_id'] . '">' . h($order_number) . '</a></td>
                <td class="align-middle">' . h($row['customer']) . (((string) $row['email'] !== '') ? '<div class="small fg-secondary">' . h((string) $row['email']) . '</div>' : '') . '</td>
                <td class="align-middle" title="' . h(implode(', ', $names)) . '">' . h(lang(array('string' => '{var:1} item(s)', 'vars' => array($quantity)))) . '</td>
                <td class="align-middle">' . h(isset($reasons[(string) $row['reason']]) ? $reasons[(string) $row['reason']] : '') . '</td>
                <td class="align-middle"><span class="' . h(pg_return_badge_class($status)) . '">' . h(pg_return_status_label($status)) . '</span></td>
                <td class="align-middle" data-order="' . (int) $row['requested_at'] . '">' . h(pg_return_time_text($row['requested_at'])) . '</td>
                <td class="align-middle" data-order="' . h($refund_due) . '">' . $output_refund_due . '</td>
            </tr>';
}

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
        'extra classes'       => 'store',
        'icon'                => 'store',
        'heading'             => lang('Returns'),
        'heading_description' => lang('Answer the customers\' return requests and follow the goods back.'),
        'cancel'              => false,
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
                <a class="btn btn-sm btn-ghost" href="view_orders.php"><i class="bi bi-cart4 me-1" aria-hidden="true"></i>' . h(lang('All Orders')) . '</a>
                <div class="pg-toolbar-grow"></div>
                ' . pg_filter_select('status', pg_return_filter_options(pg_return_status_counts()), $filter, array('label' => lang('Status'), 'action' => 'view_returns.php', 'id' => 'status')) . '
            </nav>
            ' . $output_limit . '
            <div class="card my-7">
                <div class="card-body p-0 position-relative">
                    <form name="form" action="view_returns.php" method="post">
                        ' . get_token_field() . '
                        <table class="chart table-hover table" style="width:100%;display:none;">
                            <thead>
                                <tr>
                                    <th class="noVis">
                                        <div class="form-field">
                                            <div class="switch"><input class="" title="' . h(lang('Select/Deselect All')) . '" type="checkbox" id="select_all"></div>
                                        </div>
                                    </th>
                                    <th class="noVis">' . h(lang('Action')) . '</th>
                                    <th>' . h(lang('Return No.')) . '</th>
                                    <th>' . h(lang('Order Number')) . '</th>
                                    <th>' . h(lang('Customer')) . '</th>
                                    <th>' . h(lang('Items')) . '</th>
                                    <th>' . h(lang('Reason')) . '</th>
                                    <th>' . h(lang('Status')) . '</th>
                                    <th>' . h(lang('Requested on')) . '</th>
                                    <th>' . h(lang('Refund due by')) . '</th>
                                </tr>
                            </thead>
                            <tbody>
                                ' . $output_rows . '
                            </tbody>
                        </table>
                        <nav class="buttons navigation text-center position-sticky" style="bottom:.5rem;" aria-label="' . h(lang('Actions on the selected returns')) . '">
                            <div class="container">
                                <div class="btn-group btn-group-sm flex-wrap justify-content-center mb-0 enable-on-selected">
                                    <button type="submit" name="bulk" value="approve" class="btn mb-1 mt-1 btn-solid theme-primary disabled" data-confirm-content="' . h(lang('The selected requests will be approved, each with the return carrier and code its own screen offers, and every customer is told by e-mail.')) . '"><i class="bi bi-check2-circle me-3" aria-hidden="true"></i>' . h(lang('Approve Selected')) . '</button>
                                </div>
                            </div>
                        </nav>
                    </form>
                </div>
            </div>
        </div>
    </div>
</main>' . output_footer();

$liveform->remove_form();
