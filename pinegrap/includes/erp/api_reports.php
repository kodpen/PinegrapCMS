<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * ERP - the profit report, the cash flow report and the dashboard figures
 * through the external API.
 *
 * Each one reads the function its screen reads (profit.php, cashflow.php and
 * the functions behind the dashboard cards), so a figure an application gets
 * is the one an operator sees for the same day. Money is in the store
 * currency, in minor units.
 *
 * The cash flow is about the tills, which the panel keeps behind the cash
 * right: it needs the erp_cash:read scope and an owner who holds that right.
 * The dashboard is ledger reading; its till figures are filled in only for a
 * key that could read the tills, and are null otherwise, the way the panel
 * leaves the till card out rather than showing it as zero.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_API_ENTRY') && !defined('PG_API_PANEL') && !defined('PG_INIT_LOADED')) {
    exit;
}

if (!defined('PG_FUNCTIONS_DIR')) {
    exit;
}

/* ---------------------------------------------------------------------------
   Profit and loss
   --------------------------------------------------------------------------- */

/**
 * One set of profit figures, with its margins.
 *
 * @param array $figures  erp_profit_blank() shape, finished
 * @return array
 */
function erp_api_profit_figures_present($figures)
{
    return array(
        'sales' => api_money($figures['sales']),
        'order_sales' => api_money($figures['order_sales']),
        'returns' => api_money($figures['returns']),
        'revenue' => api_money($figures['revenue']),
        'cogs' => api_money($figures['cogs']),
        'cogs_estimated' => api_money($figures['cogs_estimated']),
        'gross' => api_money($figures['gross']),
        'purchase_costs' => api_money($figures['purchase_costs']),
        'expenses' => api_money($figures['expenses']),
        'net' => api_money($figures['net']),
        'gross_margin' => erp_profit_margin($figures['gross'], $figures['revenue']),
        'net_margin' => erp_profit_margin($figures['net'], $figures['revenue']),
    );
}

// What erp_api_profit_figures_present() returns.
function erp_api_profit_figures_schema()
{
    return array(
        'sales' => 'integer',
        'order_sales' => 'integer',
        'returns' => 'integer',
        'revenue' => 'integer',
        'cogs' => 'integer',
        'cogs_estimated' => 'integer',
        'gross' => 'integer',
        'purchase_costs' => 'integer',
        'expenses' => 'integer',
        'net' => 'integer',
        'gross_margin' => 'number?',
        'net_margin' => 'number?',
    );
}

function erp_api_profit_month_present($month, $figures)
{
    return array_merge(array('month' => (string) $month), erp_api_profit_figures_present($figures));
}

function erp_api_profit_product_present($product)
{
    return array(
        'product_id' => (int) $product['product_id'],
        'sku' => (string) $product['sku'],
        'name' => (string) $product['name'],
        'quantity' => (float) $product['quantity'],
        'revenue' => api_money($product['revenue']),
        'cost' => api_money($product['cost']),
        'profit' => api_money($product['profit']),
        'margin' => $product['margin'],
        'lines' => (int) $product['lines'],
        'estimated_lines' => (int) $product['estimated'],
        'uncosted_lines' => (int) $product['uncosted'],
    );
}

function erp_api_profit_account_present($account)
{
    return array(
        'account_id' => (int) $account['account_id'],
        'title' => (string) $account['title'],
        'documents' => (int) $account['documents'],
        'revenue' => api_money($account['revenue']),
        'cost' => api_money($account['cost']),
        'profit' => api_money($account['profit']),
        'margin' => $account['margin'],
    );
}

function erp_api_profit_category_present($category)
{
    return array(
        'category_id' => (int) $category['category_id'],
        'name' => (string) $category['name'],
        'code' => (string) $category['code'],
        'count' => (int) $category['count'],
        'net' => api_money($category['net']),
        'tax' => api_money($category['tax']),
        'deductible_tax' => api_money($category['deductible_tax']),
        'total' => api_money($category['total']),
        'cost' => api_money($category['cost']),
        'unpaid' => api_money($category['unpaid']),
    );
}

/**
 * @param array $report  erp_profit_report()
 * @param int   $limit   How many product and account rows to list
 * @return array
 */
function erp_api_profit_report_present($report, $limit)
{
    return array(
        'from' => (string) $report['from'],
        'to' => (string) $report['to'],
        'currency' => erp_base_currency(),
        'totals' => erp_api_profit_figures_present($report['totals']),
        'months' => array_values(array_map('erp_api_profit_month_present', array_keys($report['months']), array_values($report['months']))),
        'products' => array_values(array_map('erp_api_profit_product_present', array_slice($report['products'], 0, $limit))),
        'products_count' => count($report['products']),
        'accounts' => array_values(array_map('erp_api_profit_account_present', array_slice($report['accounts'], 0, $limit))),
        'accounts_count' => count($report['accounts']),
        'expense_categories' => array_values(array_map('erp_api_profit_category_present', (array) ($report['expenses']['categories'] ?? array()))),
        'purchase_cost_lines' => (int) $report['purchase_cost_lines'],
        'estimated_lines' => (int) $report['estimated_lines'],
        'uncosted_lines' => (int) $report['uncosted_lines'],
        'uninvoiced_orders' => array(
            'included' => !empty($report['orders']['included']),
            'count' => (int) $report['orders']['count'],
            'net' => api_money($report['orders']['net']),
        ),
    );
}

// What erp_api_profit_report_present() returns, declared for the OpenAPI document.
function erp_api_profit_report_schema()
{
    return array(
        'from' => 'string',
        'to' => 'string',
        'currency' => 'string',
        'totals' => erp_api_profit_figures_schema(),
        'months' => array(array_merge(array('month' => 'string'), erp_api_profit_figures_schema())),
        'products' => array(array(
            'product_id' => 'integer',
            'sku' => 'string',
            'name' => 'string',
            'quantity' => 'number',
            'revenue' => 'integer',
            'cost' => 'integer',
            'profit' => 'integer',
            'margin' => 'number?',
            'lines' => 'integer',
            'estimated_lines' => 'integer',
            'uncosted_lines' => 'integer',
        )),
        'products_count' => 'integer',
        'accounts' => array(array(
            'account_id' => 'integer',
            'title' => 'string',
            'documents' => 'integer',
            'revenue' => 'integer',
            'cost' => 'integer',
            'profit' => 'integer',
            'margin' => 'number?',
        )),
        'accounts_count' => 'integer',
        'expense_categories' => array(array(
            'category_id' => 'integer',
            'name' => 'string',
            'code' => 'string',
            'count' => 'integer',
            'net' => 'integer',
            'tax' => 'integer',
            'deductible_tax' => 'integer',
            'total' => 'integer',
            'cost' => 'integer',
            'unpaid' => 'integer',
        )),
        'purchase_cost_lines' => 'integer',
        'estimated_lines' => 'integer',
        'uncosted_lines' => 'integer',
        'uninvoiced_orders' => array(
            'included' => 'boolean',
            'count' => 'integer',
            'net' => 'integer',
        ),
    );
}

function erp_api_reports_profit($params)
{
    $period = erp_api_report_period($params);
    $limit = (int) ($params['limit'] ?? 50);

    // The screen remembers the orders choice in a cookie; a caller says it
    // each time.
    $report = erp_profit_report($period['from'], $period['to'], array('orders' => !empty($params['orders'])));

    api_ok(erp_api_profit_report_present($report, $limit));
}

/* ---------------------------------------------------------------------------
   Cash flow
   --------------------------------------------------------------------------- */

function erp_api_cashflow_row_present($row)
{
    return array(
        'key' => (string) $row['key'],
        'start' => (string) $row['start'],
        'end' => (string) $row['end'],
        'in' => api_money($row['in']),
        'out' => api_money($row['out']),
        'net' => api_money($row['net']),
        'count' => (int) $row['count'],
        'balance' => api_money($row['balance']),
    );
}

function erp_api_cashflow_till_present($till)
{
    return array(
        'cash_account_id' => (int) $till['id'],
        'name' => (string) $till['name'],
        'kind' => (string) $till['kind'],
        'currency' => (string) $till['currency'],
        'is_active' => (bool) $till['is_active'],
        'in_base' => (bool) $till['in_base'],
        'opening' => api_money($till['opening']),
        'in' => api_money($till['in']),
        'out' => api_money($till['out']),
        'net' => api_money($till['net']),
        'closing' => api_money($till['closing']),
        'count' => (int) $till['count'],
    );
}

function erp_api_cashflow_method_present($method, $sums)
{
    return array(
        'method' => (string) $method,
        'in' => api_money($sums['in']),
        'out' => api_money($sums['out']),
        'count' => (int) $sums['count'],
    );
}

/**
 * @param array $options  erp_cashflow_options()
 * @param array $report   erp_cashflow_report()
 * @param array $tills    erp_cashflow_by_till()
 * @param array $methods  erp_cashflow_by_method()
 * @return array
 */
function erp_api_cashflow_report_present($options, $report, $tills, $methods)
{
    return array(
        'from' => (string) $options['from'],
        'to' => (string) $options['to'],
        'group' => (string) $options['group'],
        'cash_account_id' => ((int) $options['till'] > 0) ? (int) $options['till'] : null,
        'currency' => erp_base_currency(),
        'opening' => api_money($report['opening']),
        'in' => api_money($report['in']),
        'out' => api_money($report['out']),
        'net' => api_money($report['net']),
        'closing' => api_money($report['closing']),
        'count' => (int) $report['count'],
        'foreign_tills' => (int) $report['foreign_tills'],
        'rows' => array_values(array_map('erp_api_cashflow_row_present', $report['rows'])),
        'tills' => array_values(array_map('erp_api_cashflow_till_present', $tills)),
        'methods' => array_values(array_map('erp_api_cashflow_method_present', array_keys($methods), array_values($methods))),
    );
}

// What erp_api_cashflow_report_present() returns, declared for the OpenAPI document.
function erp_api_cashflow_report_schema()
{
    return array(
        'from' => 'string',
        'to' => 'string',
        'group' => 'string',
        'cash_account_id' => 'integer?',
        'currency' => 'string',
        'opening' => 'integer',
        'in' => 'integer',
        'out' => 'integer',
        'net' => 'integer',
        'closing' => 'integer',
        'count' => 'integer',
        'foreign_tills' => 'integer',
        'rows' => array(array(
            'key' => 'string',
            'start' => 'string',
            'end' => 'string',
            'in' => 'integer',
            'out' => 'integer',
            'net' => 'integer',
            'count' => 'integer',
            'balance' => 'integer',
        )),
        'tills' => array(array(
            'cash_account_id' => 'integer',
            'name' => 'string',
            'kind' => 'string',
            'currency' => 'string',
            'is_active' => 'boolean',
            'in_base' => 'boolean',
            'opening' => 'integer',
            'in' => 'integer',
            'out' => 'integer',
            'net' => 'integer',
            'closing' => 'integer',
            'count' => 'integer',
        )),
        'methods' => array(array(
            'method' => 'string',
            'in' => 'integer',
            'out' => 'integer',
            'count' => 'integer',
        )),
    );
}

function erp_api_reports_cashflow($params)
{
    if (!erp_api_owner()['cash']) {
        api_fail(403, 'forbidden', lang('The application owner does not hold the ERP cash right.'));
    }

    $today = date('Y-m-d');
    $to = isset($params['to']) ? erp_api_day($params['to']) : $today;
    $from = isset($params['from']) ? erp_api_day($params['from']) : substr($to, 0, 7) . '-01';

    // The screen trims what it cannot show; a caller is told instead, so the
    // figures never cover less than was asked for.
    if ($to > $today) {
        api_fail_validation(lang('The cash flow covers what has happened, so the end date cannot be after today.'), 'to');
    }

    if ($from > $to) {
        api_fail_validation(lang('Choose a start date on or before the end date.'), 'from');
    }

    if (((int) ((strtotime($to) - strtotime($from)) / 86400) + 1) > ERP_CASHFLOW_MAX_DAYS) {
        api_fail_validation(lang(array(
            'string' => 'The report covers at most {var:1} days at a time.',
            'vars' => ERP_CASHFLOW_MAX_DAYS,
        )), 'from');
    }

    $till = (int) ($params['cash_account_id'] ?? 0);

    if (($till > 0) && !is_array(db_item("SELECT id FROM erp_cash_accounts WHERE id = '" . $till . "' LIMIT 1"))) {
        api_fail_validation(lang('That till or bank account could not be found.'), 'cash_account_id');
    }

    $options = erp_cashflow_options(array(
        'from' => $from,
        'to' => $to,
        'group' => (string) ($params['group'] ?? ''),
        'till' => $till,
    ));

    api_ok(erp_api_cashflow_report_present($options, erp_cashflow_report($options), erp_cashflow_by_till($options), erp_cashflow_by_method($options)));
}

/* ---------------------------------------------------------------------------
   Dashboard
   --------------------------------------------------------------------------- */

/**
 * The receivables or payables card: what is open, what of it is late, what
 * falls due within the week, by age, and the accounts owing the oldest money.
 *
 * @param array $summary  erp_aging_summary()
 * @return array
 */
function erp_api_dashboard_side_present($summary)
{
    return array(
        'total' => api_money($summary['total_base']),
        'count' => (int) $summary['count'],
        'overdue' => api_money($summary['overdue_base']),
        'overdue_count' => (int) $summary['overdue_count'],
        'due_soon' => api_money($summary['due_soon_base']),
        'due_soon_count' => (int) $summary['due_soon_count'],
        'buckets' => erp_api_aging_buckets_present($summary['buckets']),
        'top_overdue' => array_values(array_map('erp_api_aging_account_present', $summary['top'])),
    );
}

// What erp_api_dashboard_side_present() returns.
function erp_api_dashboard_side_schema()
{
    return array(
        'total' => 'integer',
        'count' => 'integer',
        'overdue' => 'integer',
        'overdue_count' => 'integer',
        'due_soon' => 'integer',
        'due_soon_count' => 'integer',
        'buckets' => erp_api_aging_buckets_schema(),
        'top_overdue' => array(array(
            'account_id' => 'integer',
            'title' => 'string',
            'count' => 'integer',
            'oldest_days' => 'integer',
            'total' => 'integer',
            'buckets' => erp_api_aging_buckets_schema(),
        )),
    );
}

/**
 * One till on the dashboard.
 *
 * @param array $till      erp_aging_cash_total() row
 * @param bool  $added_in  Whether its balance is in the total
 * @return array
 */
function erp_api_dashboard_till_present($till, $added_in)
{
    return array(
        'cash_account_id' => (int) $till['id'],
        'name' => (string) $till['name'],
        'kind' => (string) $till['kind'],
        'currency' => strtoupper(trim((string) $till['currency'])),
        'balance' => api_money($till['balance']),
        'added_in' => (bool) $added_in,
    );
}

/**
 * What the tills hold and what moved through them today. A till kept in
 * another currency is listed with added_in false and is not in the total.
 *
 * @param array $cash   erp_aging_cash_total()
 * @param array $today  erp_cashflow_report() for today
 * @return array
 */
function erp_api_dashboard_cash_present($cash, $today)
{
    return array(
        'total' => api_money($cash['total']),
        'tills' => array_merge(
            array_map('erp_api_dashboard_till_present', $cash['tills'], array_fill(0, count($cash['tills']), true)),
            array_map('erp_api_dashboard_till_present', $cash['foreign'], array_fill(0, count($cash['foreign']), false))
        ),
        'today_in' => api_money($today['in']),
        'today_out' => api_money($today['out']),
        'today_net' => api_money($today['net']),
    );
}

// What erp_api_dashboard_cash_present() returns, declared for the OpenAPI document.
function erp_api_dashboard_cash_schema()
{
    return array(
        'total' => 'integer',
        'tills' => array(array(
            'cash_account_id' => 'integer',
            'name' => 'string',
            'kind' => 'string',
            'currency' => 'string',
            'balance' => 'integer',
            'added_in' => 'boolean',
        )),
        'today_in' => 'integer',
        'today_out' => 'integer',
        'today_net' => 'integer',
    );
}

/**
 * @param array $data  The figures erp_api_dashboard() gathered
 * @return array
 */
function erp_api_dashboard_present($data)
{
    $month = $data['month'];

    return array(
        'as_of' => (string) $data['as_of'],
        'currency' => erp_base_currency(),
        'receivables' => erp_api_dashboard_side_present($data['receivables']),
        'payables' => erp_api_dashboard_side_present($data['payables']),
        'month' => array(
            'from' => (string) $month['from'],
            'to' => (string) $month['to'],
            'revenue' => api_money($month['totals']['revenue']),
            'gross' => api_money($month['totals']['gross']),
            'net' => api_money($month['totals']['net']),
            'net_margin' => erp_profit_margin($month['totals']['net'], $month['totals']['revenue']),
            'expenses' => api_money($month['expenses']['total']['total'] ?? 0),
            'expense_count' => (int) ($month['expenses']['total']['count'] ?? 0),
            'uninvoiced_orders_included' => !empty($month['orders']['included']),
        ),
        'expenses_to_pay' => array(
            'total' => api_money($data['unpaid']['total']),
            'count' => (int) $data['unpaid']['count'],
            'overdue_count' => (int) $data['unpaid']['overdue'],
        ),
        'low_stock_count' => (int) $data['low_stock'],
        'over_credit_limit' => array(
            'count' => count($data['over_limit']),
            'total' => api_money(array_sum(array_map('intval', array_column($data['over_limit'], 'over_by')))),
        ),
        'quotes' => array(
            'open' => (int) $data['quotes_open'],
            'expiring_7_days' => (int) $data['quotes_expiring'],
        ),
        'purchase_orders' => array(
            'waiting' => (int) $data['orders_waiting'],
            'late' => (int) $data['orders_late'],
        ),
        'cash' => ($data['cash'] !== null) ? erp_api_dashboard_cash_present($data['cash'], $data['cash_today']) : null,
    );
}

// What erp_api_dashboard_present() returns, declared for the OpenAPI document.
function erp_api_dashboard_schema()
{
    return array(
        'as_of' => 'string',
        'currency' => 'string',
        'receivables' => erp_api_dashboard_side_schema(),
        'payables' => erp_api_dashboard_side_schema(),
        'month' => array(
            'from' => 'string',
            'to' => 'string',
            'revenue' => 'integer',
            'gross' => 'integer',
            'net' => 'integer',
            'net_margin' => 'number?',
            'expenses' => 'integer',
            'expense_count' => 'integer',
            'uninvoiced_orders_included' => 'boolean',
        ),
        'expenses_to_pay' => array(
            'total' => 'integer',
            'count' => 'integer',
            'overdue_count' => 'integer',
        ),
        'low_stock_count' => 'integer',
        'over_credit_limit' => array(
            'count' => 'integer',
            'total' => 'integer',
        ),
        'quotes' => array(
            'open' => 'integer',
            'expiring_7_days' => 'integer',
        ),
        'purchase_orders' => array(
            'waiting' => 'integer',
            'late' => 'integer',
        ),
        'cash' => 'ErpDashboardCash?',
    );
}

function erp_api_dashboard($params)
{
    $today = date('Y-m-d');
    $top = (int) ($params['top'] ?? 5);

    // The till card is shown to someone who may read the tills; for anyone
    // else it is left out, not shown as zero.
    $cash_visible = api_has_scope(api_current_scopes(), 'erp_cash:read') && erp_api_owner()['cash'];

    $data = array(
        'as_of' => $today,
        'receivables' => erp_aging_summary('sales', $today, $top),
        'payables' => erp_aging_summary('purchase', $today, $top),
        'month' => erp_profit_report(date('Y-m-01'), date('Y-m-t'), array('orders' => !empty($params['orders']))),
        'unpaid' => function_exists('erp_expense_unpaid_summary') ? erp_expense_unpaid_summary() : array('count' => 0, 'total' => 0, 'overdue' => 0),
        'low_stock' => function_exists('erp_stock_low_count') ? erp_stock_low_count() : 0,
        'over_limit' => function_exists('erp_credit_over_accounts') ? erp_credit_over_accounts(100) : array(),
        'quotes_open' => (function_exists('erp_quotes_ready') && erp_quotes_ready()) ? erp_quote_open_count() : 0,
        'quotes_expiring' => (function_exists('erp_quotes_ready') && erp_quotes_ready()) ? erp_quote_expiring_count(7) : 0,
        'orders_waiting' => (function_exists('erp_purchase_orders_ready') && erp_purchase_orders_ready()) ? erp_purchase_order_waiting_count() : 0,
        'orders_late' => (function_exists('erp_purchase_orders_ready') && erp_purchase_orders_ready()) ? erp_purchase_order_late_count() : 0,
        'cash' => $cash_visible ? erp_aging_cash_total() : null,
        'cash_today' => $cash_visible ? erp_cashflow_report(erp_cashflow_options(array('from' => $today, 'to' => $today, 'group' => 'day'))) : null,
    );

    api_ok(erp_api_dashboard_present($data));
}
