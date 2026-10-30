<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Subscriptions: the store-run recurring payments (includes/fn/subscriptions.php)
 * with their customer, product, amount, period, status, next charge and
 * card, filtered by status and next-charge range and searched by customer.
 * ?id= shows one subscription: its facts, its history and its renewal
 * orders, with the moves staff may make for a customer who asks (pause,
 * resume, cancel at the end of the paid period:
 * includes/fn/subscriptions_operator.php). Refunds stay on the renewal
 * order's own screen.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

include('init.php');

$user = validate_user();
validate_ecommerce_access($user);

if (!defined('ECOMMERCE') || (ECOMMERCE !== true)) {
    output_error(lang('E-commerce is not enabled on this site.'));
}

$liveform = new liveform('view_subscriptions');
$subscription_id = is_scalar($_GET['id'] ?? null) ? max(0, (int) $_GET['id']) : 0;

// Pause, resume or cancel at the end of the period, from the row page.
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validate_token_field();

    $subscription_id = is_scalar($_POST['id'] ?? null) ? max(0, (int) $_POST['id']) : 0;
    $action = is_scalar($_POST['action'] ?? null) ? (string) $_POST['action'] : '';
    $result = pg_subscription_operator_action($user, $subscription_id, $action);

    if ($result['result'] === 'done') {
        $notices = array(
            'pause'  => lang('The subscription was paused. Nothing is charged until it is resumed.'),
            'resume' => lang('The subscription was resumed. It is charged again from its next charge date.'),
            'cancel' => lang('The subscription was cancelled. Nothing more is charged; it runs until the end of the period already paid.'),
        );
        $liveform->add_notice(h($notices[$action]));
    } else {
        $liveform->mark_error('_error', h($result['reason']));
    }

    go(PATH . SOFTWARE_DIRECTORY . '/view_subscriptions.php' . (($subscription_id > 0) ? '?id=' . $subscription_id : ''));
}

$ready = pg_subscriptions_ready();
$limit = 500;
$badges = array('active' => 'badge theme-success', 'past_due' => 'badge theme-warning', 'paused' => 'badge theme-secondary', 'cancelled' => 'badge theme-danger', 'ended' => 'badge theme-secondary');

$output_amount = function ($amount, $currency) {
    return h(trim(pg_format_number((int) $amount / 100, 2) . ' ' . (string) $currency));
};

$output_date = function ($time) {
    return ((int) $time > 0) ? h(date('d.m.Y', (int) $time)) : '<span class="fg-secondary">&ndash;</span>';
};

$output_customer = function ($row) {
    $name = ((string) $row['customer_name'] !== '') ? (string) $row['customer_name'] : (string) $row['customer_username'];

    if ($name === '') {
        $name = '#' . (int) $row['user_id'];
    }

    return h($name) . (((string) $row['customer_email'] !== '') ? '<div class="small fg-secondary">' . h((string) $row['customer_email']) . '</div>' : '');
};

$output_status = function ($status) use ($badges) {
    return '<span class="' . h($badges[(string) $status] ?? 'badge theme-secondary') . '">' . h(pg_subscription_customer_status_label((string) $status)) . '</span>';
};

$output_not_ready = $ready ? '' : '
            <div class="alert theme-warning" role="status">' . h(lang('Subscriptions are not installed yet: the database has to be updated first.')) . '</div>';

$breadcrumb = array(array('label' => lang('All Orders'), 'url' => OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/view_orders.php'));

if ($subscription_id > 0) {

    // One subscription.
    $record = pg_subscription_operator_record($subscription_id);

    if ($record === null) {
        output_error(lang('The subscription was not found.') . ' <a href="view_subscriptions.php">' . h(lang('All subscriptions')) . '</a>');
    }

    $row = $record['row'];
    $status = (string) $row['status'];
    $actions = pg_subscription_operator_actions($row);
    $next_label = (($status === 'cancelled') && ((int) $row['retry_count'] === 0)) ? lang('Runs until') : lang('Next charge');

    $facts = array(
        array(lang('Customer'), $output_customer($row)),
        array(lang('Product'), h((string) $row['product_name'])),
        array(lang('Amount'), $output_amount($row['amount'], $row['currency'])),
        array(lang('Period'), h(pg_subscription_operator_period_label((string) $row['period']))),
        array(lang('Status'), $output_status($status)
            . (((int) $row['retry_count'] > 0) ? ' <span class="small fg-secondary">' . h(lang(array('string' => 'Failed payment attempts: {var:1}', 'vars' => array((int) $row['retry_count'])))) . '</span>' : '')),
        array($next_label, in_array($status, array('active', 'past_due', 'paused', 'cancelled'), true) ? $output_date($row['next_charge_at']) : $output_date(0)),
        array(lang('Card'), ((string) $row['card_last4'] !== '') ? h(lang(array('string' => 'Card ending {var:1}', 'vars' => array((string) $row['card_last4'])))) : $output_date(0)),
        array(lang('Payment service'), h((string) $row['driver'])),
        array(lang('First order'), ((int) $row['order_id'] > 0)
            ? '<a href="view_order.php?id=' . (int) $row['order_id'] . '">' . h(((string) $row['order_number'] !== '') ? (string) $row['order_number'] : '#' . (int) $row['order_id']) . '</a>'
            : $output_date(0)),
        array(lang('Started'), $output_date($row['created_at'])),
    );

    if ((int) $row['cancelled_at'] > 0) {
        $facts[] = array(lang('Cancelled'), $output_date($row['cancelled_at']));
    }

    if ((int) $row['ended_at'] > 0) {
        $facts[] = array(lang('Ended'), $output_date($row['ended_at']));
    }

    $output_facts = '';

    foreach ($facts as $fact) {
        $output_facts .= '
                        <dt class="sm:col-4 lg:col-3 fg-secondary fw-normal">' . h($fact[0]) . '</dt>
                        <dd class="sm:col-8 lg:col-9">' . $fact[1] . '</dd>';
    }

    // Each move is its own small form beside the others, never in a table.
    $buttons = array(
        'pause'  => array('label' => lang('Pause'), 'icon' => 'pause-circle', 'class' => 'theme-secondary', 'confirm' => lang('Nothing is charged until the subscription is resumed. The customer is not e-mailed.')),
        'resume' => array('label' => lang('Resume'), 'icon' => 'play-circle', 'class' => 'theme-primary', 'confirm' => lang('The subscription is charged again from its next charge date.')),
        'cancel' => array('label' => lang('Cancel at the end of the period'), 'icon' => 'x-circle', 'class' => 'theme-danger', 'confirm' => ($status === 'active')
            ? lang('Nothing more is charged. The subscription runs until the end of the period already paid, and the customer is e-mailed. This cannot be undone.')
            : lang('Nothing more is charged and the subscription ends; the customer is e-mailed. This cannot be undone.')),
    );
    $output_actions = '';

    foreach ($actions as $action) {
        $button = $buttons[$action];
        $output_actions .= '
                <form action="view_subscriptions.php?id=' . (int) $row['id'] . '" method="post" class="d-inline-block">
                    ' . get_token_field() . '
                    <input type="hidden" name="id" value="' . (int) $row['id'] . '">
                    <input type="hidden" name="action" value="' . h($action) . '">
                    <button type="submit" class="btn btn-sm btn-outline border-2 ' . $button['class'] . ' rounded-pill px-12" data-confirm-content="' . h($button['confirm']) . '"><i class="bi bi-' . $button['icon'] . ' me-1" aria-hidden="true"></i>' . h($button['label']) . '</button>
                </form>';
    }

    $output_events = '';

    foreach ($record['events'] as $event) {
        $label = pg_subscription_customer_event_label((string) $event['kind']);
        $output_events .= '
                            <tr>
                                <td class="text-nowrap">' . h(date('d.m.Y H:i', (int) $event['created_at'])) . '</td>
                                <td>' . h(($label !== '') ? $label : (string) $event['kind']) . '</td>
                                <td>' . (((int) $event['order_id'] > 0) ? '<a href="view_order.php?id=' . (int) $event['order_id'] . '">#' . (int) $event['order_id'] . '</a>' : '') . '</td>
                                <td class="text-end text-nowrap">' . (((int) $event['amount'] > 0) ? $output_amount($event['amount'], $row['currency']) : '') . '</td>
                                <td class="small text-break">' . h((string) $event['detail']) . '</td>
                            </tr>';
    }

    $output_orders = '';

    foreach ($record['orders'] as $order) {
        $output_orders .= '
                            <tr>
                                <td><a href="view_order.php?id=' . (int) $order['id'] . '">' . h(((string) $order['order_number'] !== '') ? (string) $order['order_number'] : '#' . (int) $order['id']) . '</a></td>
                                <td class="text-nowrap">' . (((int) $order['order_date'] > 0) ? h(date('d.m.Y H:i', (int) $order['order_date'])) : '') . '</td>
                                <td>' . h((string) $order['status']) . '</td>
                                <td class="text-end text-nowrap">' . $output_amount($order['total'], $row['currency']) . '</td>
                            </tr>';
    }

    $title = lang('Subscription') . ' #' . (int) $row['id'];
    $breadcrumb[] = array('label' => lang('Subscriptions'), 'url' => OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/view_subscriptions.php');
    $breadcrumb[] = array('label' => $title);

    $output_content = '
            <nav id="button_bar" class="pg-toolbar navigation" aria-label="' . h(lang('Button Bar')) . '">
                ' . $output_actions . '
                <a class="btn btn-sm btn-text theme-secondary" href="view_subscriptions.php"><i class="bi bi-list-ul me-1" aria-hidden="true"></i>' . h(lang('All subscriptions')) . '</a>
            </nav>
            <div class="card my-7">
                <div class="card-body">
                    <dl class="row mb-0">' . $output_facts . '
                    </dl>
                </div>
            </div>
            <div class="card my-7">
                <div class="card-header"><h2 class="h6 mb-0">' . h(lang('Renewal orders')) . '</h2></div>
                <div class="card-body p-0">' . (($output_orders !== '') ? '
                    <table class="table table-hover mb-0">
                        <thead>
                            <tr>
                                <th>' . h(lang('Order')) . '</th>
                                <th>' . h(lang('Date')) . '</th>
                                <th>' . h(lang('Status')) . '</th>
                                <th class="text-end">' . h(lang('Total')) . '</th>
                            </tr>
                        </thead>
                        <tbody>' . $output_orders . '
                        </tbody>
                    </table>' : '
                    <p class="p-12 mb-0 fg-secondary">' . h(lang('No renewal order yet.')) . '</p>') . '
                </div>
            </div>
            <div class="card my-7">
                <div class="card-header"><h2 class="h6 mb-0">' . h(lang('History')) . '</h2></div>
                <div class="card-body p-0">
                    <table class="table table-hover mb-0">
                        <thead>
                            <tr>
                                <th>' . h(lang('Date')) . '</th>
                                <th>' . h(lang('Event')) . '</th>
                                <th>' . h(lang('Order')) . '</th>
                                <th class="text-end">' . h(lang('Amount')) . '</th>
                                <th>' . h(lang('Details')) . '</th>
                            </tr>
                        </thead>
                        <tbody>' . $output_events . '
                        </tbody>
                    </table>
                </div>
            </div>';

} else {

    // The list.
    $title = lang('Subscriptions');
    $breadcrumb[] = array('label' => $title);
    $filter = pg_subscription_operator_filter($_GET);
    $rows = $ready ? pg_subscription_operator_rows($filter, $limit) : array();
    $output_rows = '';

    foreach ($rows as $row) {
        $id = (int) $row['id'];
        $status = (string) $row['status'];
        $next = in_array($status, array('active', 'past_due', 'paused'), true) ? (int) $row['next_charge_at'] : 0;

        $output_rows .= '
            <tr id="' . $id . '">
                <td class="align-middle" data-order="' . $id . '"><a href="view_subscriptions.php?id=' . $id . '" aria-label="' . h(lang('Subscription') . ' #' . $id) . '">#' . $id . '</a></td>
                <td class="align-middle">' . $output_customer($row) . '</td>
                <td class="align-middle">' . h((string) $row['product_name']) . '</td>
                <td class="align-middle text-nowrap" data-order="' . (int) $row['amount'] . '">' . $output_amount($row['amount'], $row['currency']) . '</td>
                <td class="align-middle">' . h(pg_subscription_operator_period_label((string) $row['period'])) . '</td>
                <td class="align-middle">' . $output_status($status) . '</td>
                <td class="align-middle" data-order="' . $next . '">' . $output_date($next) . '</td>
                <td class="align-middle">' . h((string) $row['card_last4']) . '</td>
            </tr>';
    }

    $status_options = array('' => lang('All statuses'));

    foreach (pg_subscription_statuses() as $status) {
        $status_options[$status] = pg_subscription_customer_status_label($status);
    }

    $output_status_options = '';

    foreach ($status_options as $value => $label) {
        $output_status_options .= '<option value="' . h($value) . '"' . (($filter['status'] === $value) ? ' selected' : '') . '>' . h($label) . '</option>';
    }

    $filtered = ($filter !== pg_subscription_operator_filter(array()));

    $output_limit = (count($rows) >= $limit) ? '
            <p class="small fg-secondary">' . h(lang(array('string' => 'The first {var:1} subscriptions are shown. Narrow the list with the filters.', 'vars' => array($limit)))) . '</p>' : '';

    $output_content = '
            <nav id="button_bar" class="pg-toolbar navigation" aria-label="' . h(lang('Button Bar')) . '">
                <a class="btn btn-sm btn-text theme-secondary" href="view_orders.php"><i class="bi bi-cart4 me-1" aria-hidden="true"></i>' . h(lang('All Orders')) . '</a>
                <div class="pg-toolbar-grow"></div>
                <form action="view_subscriptions.php" method="get" class="d-flex flex-wrap align-items-center gap-1 row-gap-3" role="search">
                    <select name="status" class="form-control form-control-sm w-auto" aria-label="' . h(lang('Status')) . '">' . $output_status_options . '</select>
                    <span class="d-inline-flex align-items-center gap-1">
                        <input type="date" data-pg-date name="next_from" value="' . h($filter['next_from']) . '" class="form-control form-control-sm w-auto" aria-label="' . h(lang('Next charge from')) . '" title="' . h(lang('Next charge from')) . '">
                        <span class="fg-secondary">&ndash;</span>
                        <input type="date" data-pg-date name="next_to" value="' . h($filter['next_to']) . '" class="form-control form-control-sm w-auto" aria-label="' . h(lang('Next charge to')) . '" title="' . h(lang('Next charge to')) . '">
                    </span>
                    <input type="search" name="q" value="' . h($filter['q']) . '" maxlength="100" class="form-control form-control-sm w-auto" placeholder="' . h(lang('Customer name or e-mail')) . '" aria-label="' . h(lang('Customer name or e-mail')) . '">
                    <button type="submit" class="btn btn-sm btn-solid theme-primary rounded-pill px-12"><i class="bi bi-funnel me-1" aria-hidden="true"></i>' . h(lang('Filter')) . '</button>
                    ' . ($filtered ? '<a class="btn btn-sm btn-text theme-secondary" href="view_subscriptions.php">' . h(lang('Clear')) . '</a>' : '') . '
                </form>
            </nav>
            ' . $output_limit . '
            <div class="card my-7">
                <div class="card-body p-0 position-relative">
                    <table class="chart table-hover table" style="width:100%;display:none;">
                        <thead>
                            <tr>
                                <th>' . h(lang('Subscription')) . '</th>
                                <th>' . h(lang('Customer')) . '</th>
                                <th>' . h(lang('Product')) . '</th>
                                <th>' . h(lang('Amount')) . '</th>
                                <th>' . h(lang('Period')) . '</th>
                                <th>' . h(lang('Status')) . '</th>
                                <th>' . h(lang('Next charge')) . '</th>
                                <th>' . h(lang('Card')) . '</th>
                            </tr>
                        </thead>
                        <tbody>
                            ' . $output_rows . '
                        </tbody>
                    </table>
                </div>
            </div>';
}

echo
    pg_page_shell(array(
        'title'               => $title,
        'extra classes'       => 'store',
        'icon'                => 'store',
        'heading'             => $title,
        'heading_description' => lang('Recurring payments the store charges from the customer\'s stored card.'),
        'cancel'              => false,
        'breadcrumb'          => $breadcrumb,
    )) . '
<main id="content" class="container-fluid">
    <div class="row">
        <div class="col-12">
            ' . $liveform->output_errors() . '
            ' . $liveform->output_notices() . '
            ' . $output_not_ready . '
            ' . $output_content . '
        </div>
    </div>
</main>' . get_date_picker_format() . pg_fields_script() . output_footer();

$liveform->remove_form();
