<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * An order's profit on the order screens: the Profit card of view_order.php
 * and the profit column and "Sold at a loss" filter of view_orders.php. It
 * only reads erp_order_profit and erp_order_costs, written by the pass of
 * includes/erp/order_profit.php.
 *
 * Profit is seen only with ERP access: product cost is seen nowhere else.
 * With Accounting off, or without the right, nothing is read or drawn.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_ERP_ENTRY')) {
    exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/erp/order_profit.php');
require_once(PG_FUNCTIONS_DIR . '/includes/erp/profit.php');

/**
 * Whether a user may see an order's profit: Accounting on and ERP access
 * (validate_erp_access()'s rule, without its refusal page).
 *
 * @param bool  $enabled  ERP_ENABLED
 * @param array $user     the signed-in user: role, manage_erp
 * @return bool
 */
function erp_order_profit_access($enabled, $user)
{
    if (!$enabled || !is_array($user)) {
        return false;
    }

    return ((int) ($user['role'] ?? 3) < 3) || !empty($user['manage_erp']);
}

/**
 * Whether the order screens show profit to this user: the access rule, and
 * the tables of 2026.11.0 in place.
 *
 * @param array $user
 * @return bool
 */
function erp_order_profit_shown($user)
{
    return erp_order_profit_access(defined('ERP_ENABLED') && ERP_ENABLED, $user) && erp_order_profit_ready();
}

/**
 * The name of a kind of cost, as the Profit card prints it.
 *
 * @param string $kind
 * @return string
 */
function erp_order_profit_kind_label($kind)
{
    switch ((string) $kind) {
        case 'product':
            return lang('Product cost');
        case 'payment':
            return lang('Payment fee');
        case 'marketplace':
            return lang('Marketplace deductions');
        case 'shipping':
            return lang('Shipping cost');
        default:
            return lang('Other costs');
    }
}

/**
 * Where a cost row's figure came from, in words.
 *
 * @param string $source  erp_order_costs.source
 * @return string
 */
function erp_order_profit_source_label($source)
{
    switch ((string) $source) {
        case 'invoice':
            return lang('from the invoice');
        case 'average':
            return lang('average cost');
        case 'bundle':
            return lang('bundle components');
        case 'gateway':
            return lang('from the payment service');
        case 'marketplace':
            return lang('from the marketplace');
        case 'label':
            return lang('from the shipping label');
        case 'rate':
            return lang('typed rate');
        case 'estimate':
            return lang('estimate');
        default:
            return '';
    }
}

/**
 * The state of an order's profit in words, with the look of its badge.
 *
 * @param string $state  final, estimate or incomplete
 * @return array ['label' => string, 'theme' => success|warning|danger]
 */
function erp_order_profit_state_badge($state)
{
    switch ((string) $state) {
        case 'final':
            return array('label' => lang('Final'), 'theme' => 'success');
        case 'estimate':
            return array('label' => lang('Estimate'), 'theme' => 'warning');
        default:
            return array('label' => lang('Incomplete'), 'theme' => 'danger');
    }
}

/**
 * The Profit card's cost lines: one per kind, in erp_order_profit_kinds()'s
 * order, the amount from the profit row and the distinct sources of the
 * kind's cost rows. A kind with no amount and no row is left out, but for
 * product cost: its absence is what makes an order incomplete.
 *
 * @param array $profit  erp_order_profit row
 * @param array $costs   the order's erp_order_costs rows: kind, source, amount
 * @return array list of ['kind', 'amount', 'sources' => string[]]
 */
function erp_order_profit_card_lines($profit, $costs)
{
    $sources = array();

    foreach ((array) $costs as $row) {
        $kind = (string) ($row['kind'] ?? '');
        $source = (string) ($row['source'] ?? '');

        if (!isset($sources[$kind])) {
            $sources[$kind] = array();
        }

        if (($source !== '') && !in_array($source, $sources[$kind], true)) {
            $sources[$kind][] = $source;
        }
    }

    $lines = array();

    foreach (erp_order_profit_kinds() as $kind) {
        $amount = (int) ($profit['cost_' . $kind] ?? 0);

        if (($kind !== 'product') && ($amount === 0) && empty($sources[$kind])) {
            continue;
        }

        $lines[] = array('kind' => $kind, 'amount' => $amount, 'sources' => $sources[$kind] ?? array());
    }

    return $lines;
}

/**
 * The SQL condition of the order list's "Sold at a loss" filter, added to
 * the list's WHERE: orders whose computed net profit is below zero.
 *
 * @param string $choice  the filter's value: 'loss', anything else is no filter
 * @return string
 */
function erp_order_profit_filter_sql($choice)
{
    if ((string) $choice !== 'loss') {
        return '';
    }

    return "AND (orders.id IN (SELECT erp_order_profit.order_id FROM erp_order_profit WHERE erp_order_profit.net < 0))";
}

/**
 * The profit rows of a list of orders, order id => row, read in chunks.
 *
 * @param int[] $order_ids
 * @return array
 */
function erp_order_profit_for_orders($order_ids)
{
    $ids = array_values(array_unique(array_filter(array_map('intval', (array) $order_ids))));
    $profits = array();

    foreach (array_chunk($ids, 1000) as $chunk) {
        foreach ((array) db_items("SELECT order_id, net, margin, state FROM erp_order_profit
            WHERE order_id IN (" . implode(',', $chunk) . ")") as $row) {
            $profits[(int) $row['order_id']] = $row;
        }
    }

    return $profits;
}

/**
 * A stored margin (DECIMAL as a string, or NULL) as erp_profit_margin_text()
 * prints it: a dash with none.
 *
 * @param float|string|null $margin
 * @return string
 */
function erp_order_profit_margin_text($margin)
{
    return erp_profit_margin_text((($margin === null) || ($margin === '')) ? null : (float) $margin);
}

/**
 * The order list's profit cell: the net profit, red below zero, the margin
 * under it; a dash for an order the pass has not computed.
 *
 * @param array|null $profit  erp_order_profit row: net, margin, state
 * @return string  HTML
 */
function erp_order_profit_list_cell($profit)
{
    if (!is_array($profit)) {
        return '<span class="fg-secondary">—</span>';
    }

    $net = (int) $profit['net'];
    $state = (string) ($profit['state'] ?? '');
    $note = ($state !== 'final') ? ' title="' . h(erp_order_profit_state_badge($state)['label']) . '"' : '';

    return '<span class="text-nowrap' . (($net < 0) ? ' fg-danger' : '') . '"' . $note . '>' . h(erp_money_out($net)) . (($state !== 'final') ? '*' : '') . '</span>'
        . '<div class="pg-text-meta fg-secondary">' . h(erp_order_profit_margin_text($profit['margin'] ?? null)) . '</div>';
}

/**
 * The order screen's Profit card: revenue, each cost with its source, net
 * profit, margin and state. Nothing for an order that is not a finished
 * sale; a note for a finished one the pass has not reached yet.
 *
 * @param int    $order_id
 * @param string $status   orders.status
 * @return string  HTML
 */
function erp_order_profit_card($order_id, $status)
{
    $order_id = (int) $order_id;
    $profit = db_item("SELECT revenue, cost_product, cost_payment, cost_marketplace, cost_shipping, cost_other,
            net, margin, state, stale, computed_at
        FROM erp_order_profit WHERE order_id = '" . $order_id . "' LIMIT 1");

    if (!is_array($profit)) {
        if (!in_array((string) $status, erp_order_billable_statuses(), true)) {
            return '';
        }

        $body = '<p class="fg-secondary mb-0">' . lang('Not computed yet: the general job works out each finished order\'s profit within the hour.') . '</p>';

        return erp_order_profit_card_frame($body, '');
    }

    $costs = (array) db_items("SELECT kind, source, amount FROM erp_order_costs WHERE order_id = '" . $order_id . "' ORDER BY id");
    $money = function ($kurus) {
        $kurus = (int) $kurus;

        return '<span class="text-nowrap pg-tabular' . (($kurus < 0) ? ' fg-danger' : '') . '">' . h(erp_money_out($kurus)) . '</span>';
    };

    $rows = pg_order_view_row(lang('Revenue'), $money($profit['revenue']));

    foreach (erp_order_profit_card_lines($profit, $costs) as $line) {
        $sources = array_filter(array_map('erp_order_profit_source_label', $line['sources']), 'strlen');
        $label = h(erp_order_profit_kind_label($line['kind']))
            . (!empty($sources) ? '<span class="d-block small">' . h(implode(', ', $sources)) . '</span>' : '');

        $rows .= pg_order_view_row($label, ((int) $line['amount'] === 0) && ($line['kind'] === 'product') && ((string) $profit['state'] === 'incomplete')
            ? '<span class="fg-emphasis-warning">' . lang('no cost known') . '</span>'
            : $money(-$line['amount']));
    }

    $rows .= '<hr/>'
        . pg_order_view_row('<span class="fw-semibold">' . lang('Net profit') . '</span>', '<span class="fw-semibold">' . $money($profit['net']) . '</span>')
        . pg_order_view_row(lang('Profit margin'), h(erp_order_profit_margin_text($profit['margin'])));

    $notes = '';

    if ((string) $profit['state'] === 'incomplete') {
        $notes .= '<p class="small fg-secondary mt-3 mb-0">' . lang('A product of this order has no cost in the ERP yet, so the profit is overstated.') . '</p>';
    } elseif ((string) $profit['state'] === 'estimate') {
        $notes .= '<p class="small fg-secondary mt-3 mb-0">' . lang('Some costs are a typed rate or an estimate until the service\'s own figure is read.') . '</p>';
    }

    if (!empty($profit['stale'])) {
        $notes .= '<p class="small fg-secondary mt-3 mb-0">' . lang('A cost changed: the general job works it out again within the hour.') . '</p>';
    }

    $badge = erp_order_profit_state_badge((string) $profit['state']);

    return erp_order_profit_card_frame($rows . $notes, '<span class="badge badge-subtle theme-' . $badge['theme'] . ' ms-auto">' . h($badge['label']) . '</span>');
}

/**
 * The card around the Profit card's body, as the order screen's other cards.
 *
 * @param string $body_html
 * @param string $badge_html
 * @return string
 */
function erp_order_profit_card_frame($body_html, $badge_html)
{
    return '<div class="card pg-card mt-5" id="order_profit">
                                        <div class="card-header bg-reset border-0 d-flex align-items-center">
                                            <h2 class="pg-title-section mb-0">' . lang('Profit') . '</h2>' . $badge_html . '
                                        </div>
                                        <div class="card-body">
                                            ' . $body_html . '
                                        </div>
                                    </div>';
}
