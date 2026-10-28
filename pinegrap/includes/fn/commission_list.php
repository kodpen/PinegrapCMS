<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * The commission lists: all commissions (view_commissions.php) and the
 * recurring commission profiles (view_recurring_commission_profiles.php),
 * shared by the screens and the panel actions that page them
 * (commission_list_rows, commission_profile_list_rows,
 * includes/panel/lists.php).
 *
 * Both screens used to read 100 rows for a page of their own and let
 * DataTables search those, so the search box found nothing on the other
 * pages. The rows, the counts and the search now come from the server.
 *
 * Loaded where it is needed (require_once), not by functions.php.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_FUNCTIONS_DIR')) {
    exit;
}

/**
 * Brings the commissions list's date range in the session into shape and
 * answers it: a month ago to today by default, and every date part a number
 * (mktime() throws for anything else, and one bad address would otherwise
 * break the list for the rest of the session).
 *
 * @return array array('start' => first second, 'stop' => last second)
 */
function pg_commission_list_range()
{
    $state = &$_SESSION['software']['ecommerce']['view_commissions'];
    $month_ago = time() - 2678400;
    $defaults = array(
        'start_month' => date('m', $month_ago),
        'start_day'   => date('d', $month_ago),
        'start_year'  => date('Y', $month_ago),
        'stop_month'  => date('m'),
        'stop_day'    => date('d'),
        'stop_year'   => date('Y'),
    );

    foreach ($defaults as $key => $value) {
        if (!preg_match('/^\d{1,4}$/', (string) ($state[$key] ?? ''))) {
            $state[$key] = $value;
        }
    }

    return array(
        'start' => mktime(0, 0, 0, (int) $state['start_month'], (int) $state['start_day'], (int) $state['start_year']),
        'stop' => mktime(23, 59, 59, (int) $state['stop_month'], (int) $state['stop_day'], (int) $state['stop_year']),
    );
}

/**
 * A search word as the digits of a stored amount: amounts are shown with
 * two decimals and stored in minor units, so the shown digits are the stored
 * ones. '' for a word that is not an amount.
 *
 * @param string $term
 * @return string
 */
function pg_commission_list_amount_term($term)
{
    if (!preg_match('/^[0-9][0-9.,]*$/', (string) $term)) {
        return '';
    }

    return preg_replace('/[^0-9]/', '', (string) $term);
}

/**
 * The commission statuses as the list shows them.
 *
 * @return array stored value => label
 */
function pg_commission_list_statuses()
{
    $labels = array();

    foreach (array('pending', 'payable', 'ineligible', 'paid') as $status) {
        $labels[$status] = lang(ucwords($status));
    }

    return $labels;
}

/**
 * The recurring periods as the lists show them; '' is a one-time
 * commission, which has no profile.
 *
 * @return array stored value => label
 */
function pg_commission_list_periods()
{
    $labels = array('' => lang('One-Time'));

    foreach (get_payment_period_options() as $period) {
        if ($period !== '') {
            $labels[$period] = lang(ucwords($period));
        }
    }

    return $labels;
}

/**
 * The commissions list's sort columns, kept sort and ORDER BY.
 *
 * @return array array('sort_columns', 'sort_default', 'sort_levels', 'order_by')
 */
function pg_commission_list_sort()
{
    $sort_columns = array(
        'affiliate_name' => lang('Affiliate Name'),
        'affiliate_code' => lang('Affiliate Code'),
        'reference_code' => lang('Reference Code'),
        'status'         => lang('Status'),
        'amount'         => lang('Amount'),
        'frequency'      => lang('Frequency'),
        'order_number'   => lang('Order'),
        'product'        => lang('Product'),
        'created'        => lang('Created'),
        'last_modified'  => lang('Last Modified'),
    );
    $sort_default = array('sort' => 'last_modified', 'order' => 'desc');

    // pg_list_state() reads a sort from the request only on the screen; the
    // action's JSON body never fills $_REQUEST.
    $sort_levels = pg_list_state('view_commissions', $sort_columns, $sort_default)['sorts'];

    // What each sort code orders by; the id last keeps the pages apart.
    $order_by = pg_list_order_by($sort_levels, array(
        'affiliate_name' => 'contacts.affiliate_name',
        'affiliate_code' => 'commissions.affiliate_code',
        'reference_code' => 'commissions.reference_code',
        'status'         => 'commissions.status',
        'amount'         => 'commissions.amount',
        'frequency'      => 'recurring_commission_profiles.period',
        'order_number'   => 'orders.order_number',
        'product'        => 'recurring_commission_profiles.product_name',
        'created'        => 'commissions.created_timestamp',
        'last_modified'  => 'commissions.last_modified_timestamp',
    )) . ', commissions.id desc';

    return array('sort_columns' => $sort_columns, 'sort_default' => $sort_default, 'sort_levels' => $sort_levels, 'order_by' => $order_by);
}

/**
 * The commissions list's search columns: the row's text.
 *
 * @return array array('columns', 'labels') for pg_list_search_where()
 */
function pg_commission_list_search_columns()
{
    return array(
        'columns' => array(
            'contacts.affiliate_name',
            'commissions.affiliate_code',
            'commissions.reference_code',
            array('expr' => 'commissions.amount', 'term' => 'pg_commission_list_amount_term'),
            'orders.order_number',
            "CONCAT(IFNULL(recurring_commission_profiles.product_name, ''), ' - ', IFNULL(recurring_commission_profiles.product_short_description, ''))",
            'created_user.user_username',
            'last_modified_user.user_username',
        ),
        'labels' => array(
            array('expr' => 'commissions.status', 'labels' => pg_commission_list_statuses()),
            array('expr' => "IFNULL(recurring_commission_profiles.period, '')", 'labels' => pg_commission_list_periods()),
        ),
    );
}

/**
 * The FROM of the commissions list; each join finds one row at most.
 *
 * @return string
 */
function pg_commission_list_from()
{
    return 'FROM commissions
        LEFT JOIN contacts ON commissions.affiliate_code = contacts.affiliate_code
        LEFT JOIN orders ON commissions.order_id = orders.id
        LEFT JOIN recurring_commission_profiles ON commissions.recurring_commission_profile_id = recurring_commission_profiles.id
        LEFT JOIN user AS created_user ON commissions.created_user_id = created_user.user_id
        LEFT JOIN user AS last_modified_user ON commissions.last_modified_user_id = last_modified_user.user_id';
}

/**
 * "2 hours ago by ali", or the time alone without a user.
 *
 * @param mixed  $timestamp
 * @param string $username
 * @return string escaped
 */
function pg_commission_list_when($timestamp, $username)
{
    return get_relative_time(array('timestamp' => $timestamp))
        . (((string) $username !== '') ? ' ' . lang(array('string' => 'by {var:1}', 'vars' => array(h($username)))) : '');
}

/**
 * One row of the commissions list.
 *
 * @param array $commission
 * @return array array('class', 'cells')
 */
function pg_commission_list_row($commission)
{
    $badges = array(
        'pending' => 'bg-warning fg-black fw-light',
        'payable' => 'bg-primary fg-white fw-light',
        'ineligible' => 'bg-danger fg-white fw-light',
        'paid' => 'bg-success fg-white fw-light',
    );
    $status = (string) $commission['status'];
    $product = (string) $commission['product_name']
        . (((string) $commission['product_short_description'] !== '') ? ' - ' . $commission['product_short_description'] : '');

    return array('class' => '', 'cells' => array(
        pg_list_cell('<button type="button" class="btn-data-control m-1 btn btn-outline theme-primary border-2" title="' . lang('Edit') . '" onclick="window.location.href=\'edit_commission.php?id=' . (int) $commission['id'] . '\'"><i class="bi bi-pencil"></i></button>', 'align-middle text-start col-reorder-none'),
        pg_list_cell(h($commission['affiliate_name']), 'align-middle chart_label'),
        pg_list_cell(h($commission['affiliate_code'])),
        pg_list_cell(h($commission['reference_code'])),
        pg_list_cell('<span class="badge ' . ($badges[$status] ?? 'bg-secondary fg-white fw-light') . '">' . h(lang(ucwords($status))) . '</span>'),
        pg_list_cell(pg_format_money($commission['amount'] / 100, BASE_CURRENCY_SYMBOL), 'align-middle text-end'),
        pg_list_cell(h(pg_commission_list_periods()[(string) $commission['period']] ?? lang(ucwords((string) $commission['period'])))),
        pg_list_cell(h($commission['order_number'])),
        pg_list_cell(h($product)),
        pg_list_cell(pg_commission_list_when($commission['created_timestamp'], (string) $commission['created_username'])),
        pg_list_cell(pg_commission_list_when($commission['last_modified_timestamp'], (string) $commission['last_modified_username'])),
    ));
}

/**
 * One page of the commissions list within the date range.
 *
 * @param array $range  pg_commission_list_range()'s answer
 * @param array $terms  pg_list_search_terms()'s answer
 * @param array $window pg_list_page_window()'s answer
 * @return array|false array('total', 'filtered', 'rows') or false when a
 *                     query failed
 */
function pg_commission_list_page($range, $terms, $window)
{
    $where = ' WHERE (commissions.created_timestamp >= ?) AND (commissions.created_timestamp <= ?)';
    $params = array((string) (int) $range['start'], (string) (int) $range['stop']);
    $columns = pg_commission_list_search_columns();

    return pg_commission_list_query(
        pg_commission_list_from() . $where,
        $params,
        pg_list_search_where($terms, $columns['columns'], $columns['labels']),
        'commissions.id, commissions.affiliate_code, commissions.reference_code, commissions.status, commissions.amount,
            commissions.created_timestamp, commissions.last_modified_timestamp, contacts.affiliate_name, orders.order_number,
            recurring_commission_profiles.period, recurring_commission_profiles.product_name,
            recurring_commission_profiles.product_short_description,
            created_user.user_username AS created_username, last_modified_user.user_username AS last_modified_username',
        pg_commission_list_sort()['order_by'],
        $window,
        'pg_commission_list_row');
}

/**
 * The recurring commission profiles list's sort columns, kept sort and
 * ORDER BY.
 *
 * @return array array('sort_columns', 'sort_default', 'sort_levels', 'order_by')
 */
function pg_commission_profile_list_sort()
{
    $sort_columns = array(
        'affiliate_name' => lang('Affiliate Name'),
        'affiliate_code' => lang('Affiliate Code'),
        'enabled'        => lang('Enabled'),
        'amount'         => lang('Amount'),
        'start_date'     => lang('Start Date'),
        'frequency'      => lang('Frequency'),
        'commissions'    => lang('Commissions'),
        'order_number'   => lang('Order'),
        'product'        => lang('Product'),
        'created'        => lang('Created'),
        'last_modified'  => lang('Last Modified'),
    );
    $sort_default = array('sort' => 'last_modified', 'order' => 'desc');
    $sort_levels = pg_list_state('view_recurring_commission_profiles', $sort_columns, $sort_default)['sorts'];

    $order_by = pg_list_order_by($sort_levels, array(
        'affiliate_name' => 'contacts.affiliate_name',
        'affiliate_code' => 'recurring_commission_profiles.affiliate_code',
        'enabled'        => 'recurring_commission_profiles.enabled',
        'amount'         => 'recurring_commission_profiles.amount',
        'start_date'     => 'recurring_commission_profiles.start_date',
        'frequency'      => 'recurring_commission_profiles.period',
        'commissions'    => 'recurring_commission_profiles.number_of_commissions',
        'order_number'   => 'orders.order_number',
        'product'        => 'recurring_commission_profiles.product_name',
        'created'        => 'recurring_commission_profiles.created_timestamp',
        'last_modified'  => 'recurring_commission_profiles.last_modified_timestamp',
    )) . ', recurring_commission_profiles.id desc';

    return array('sort_columns' => $sort_columns, 'sort_default' => $sort_default, 'sort_levels' => $sort_levels, 'order_by' => $order_by);
}

/**
 * The recurring commission profiles list's search columns: the row's text.
 *
 * @return array array('columns', 'labels') for pg_list_search_where()
 */
function pg_commission_profile_list_search_columns()
{
    return array(
        'columns' => array(
            'contacts.affiliate_name',
            'recurring_commission_profiles.affiliate_code',
            array('expr' => 'recurring_commission_profiles.amount', 'term' => 'pg_commission_list_amount_term'),
            'orders.order_number',
            "CONCAT(recurring_commission_profiles.product_name, ' - ', recurring_commission_profiles.product_short_description)",
            'created_user.user_username',
            'last_modified_user.user_username',
        ),
        'labels' => array(
            array('expr' => 'recurring_commission_profiles.period', 'labels' => pg_commission_list_periods()),
        ),
    );
}

/**
 * One row of the recurring commission profiles list.
 *
 * @param array $profile
 * @return array array('class', 'cells')
 */
function pg_commission_profile_list_row($profile)
{
    $product = (string) $profile['product_name']
        . (((string) $profile['product_short_description'] !== '') ? ' - ' . $profile['product_short_description'] : '');
    $commissions = ((int) $profile['number_of_commissions'] === 0) ? lang('Unlimited') : pg_format_number($profile['number_of_commissions'], 0);

    return array('class' => '', 'cells' => array(
        pg_list_cell('<button type="button" class="btn-data-control m-1 btn btn-outline theme-primary border-2" title="' . lang('Edit') . '" onclick="window.location.href=\'edit_recurring_commission_profile.php?id=' . (int) $profile['id'] . '\'"><i class="bi bi-pencil"></i></button>', 'align-middle text-start col-reorder-none'),
        pg_list_cell(h($profile['affiliate_name']), 'align-middle chart_label'),
        pg_list_cell(h($profile['affiliate_code'])),
        pg_list_cell(($profile['enabled'] == 1) ? '<span class="material-icons">task_alt</span>' : '', 'align-middle text-center'),
        pg_list_cell('<span class="badge theme-primary fw-light">' . pg_format_money($profile['amount'] / 100, BASE_CURRENCY_SYMBOL) . '</span>', 'align-middle text-end'),
        pg_list_cell(prepare_form_data_for_output($profile['start_date'], 'date')),
        pg_list_cell(h(pg_commission_list_periods()[(string) $profile['period']] ?? lang(ucwords((string) $profile['period'])))),
        pg_list_cell('<span class="badge theme-success fw-light">' . h($commissions) . '</span>'),
        pg_list_cell(h($profile['order_number'])),
        pg_list_cell(h($product)),
        pg_list_cell(pg_commission_list_when($profile['created_timestamp'], (string) $profile['created_username'])),
        pg_list_cell(pg_commission_list_when($profile['last_modified_timestamp'], (string) $profile['last_modified_username'])),
    ));
}

/**
 * One page of the recurring commission profiles list.
 *
 * @param array $terms  pg_list_search_terms()'s answer
 * @param array $window pg_list_page_window()'s answer
 * @return array|false array('total', 'filtered', 'rows') or false when a
 *                     query failed
 */
function pg_commission_profile_list_page($terms, $window)
{
    $columns = pg_commission_profile_list_search_columns();

    return pg_commission_list_query(
        'FROM recurring_commission_profiles
            LEFT JOIN contacts ON recurring_commission_profiles.affiliate_code = contacts.affiliate_code
            LEFT JOIN orders ON recurring_commission_profiles.order_id = orders.id
            LEFT JOIN user AS created_user ON recurring_commission_profiles.created_user_id = created_user.user_id
            LEFT JOIN user AS last_modified_user ON recurring_commission_profiles.last_modified_user_id = last_modified_user.user_id
            WHERE 1 = 1',
        array(),
        pg_list_search_where($terms, $columns['columns'], $columns['labels']),
        'recurring_commission_profiles.id, recurring_commission_profiles.affiliate_code, recurring_commission_profiles.enabled,
            recurring_commission_profiles.amount, recurring_commission_profiles.start_date, recurring_commission_profiles.period,
            recurring_commission_profiles.number_of_commissions, recurring_commission_profiles.product_name,
            recurring_commission_profiles.product_short_description, recurring_commission_profiles.created_timestamp,
            recurring_commission_profiles.last_modified_timestamp, contacts.affiliate_name, orders.order_number,
            created_user.user_username AS created_username, last_modified_user.user_username AS last_modified_username',
        pg_commission_profile_list_sort()['order_by'],
        $window,
        'pg_commission_profile_list_row');
}

/**
 * The counts and one page of either list.
 *
 * @param string   $base     FROM … WHERE …, the list's own condition
 * @param array    $params   values for $base's placeholders
 * @param array    $search   pg_list_search_where()'s answer
 * @param string   $select   the SELECT list
 * @param string   $order_by
 * @param array    $window   pg_list_page_window()'s answer
 * @param callable $row      builds a list row from a result row
 * @return array|false array('total', 'filtered', 'rows') or false when a
 *                     query failed
 */
function pg_commission_list_query($base, $params, $search, $select, $order_by, $window, $row)
{
    $search_sql = ($search['sql'] !== '') ? ' AND ' . $search['sql'] : '';
    $search_params = array_merge($params, $search['params']);

    $result = db_q('SELECT COUNT(*) ' . $base, $params);

    if (!$result) {
        return false;
    }

    $total = (int) mysqli_fetch_row($result)[0];
    $filtered = $total;

    if ($search_sql !== '') {
        $result = db_q('SELECT COUNT(*) ' . $base . $search_sql, $search_params);

        if (!$result) {
            return false;
        }

        $filtered = (int) mysqli_fetch_row($result)[0];
    }

    $result = db_q('SELECT ' . $select . ' ' . $base . $search_sql . ' ORDER BY ' . $order_by . pg_list_limit_sql($window), $search_params);

    if (!$result) {
        return false;
    }

    $rows = array();

    while ($record = mysqli_fetch_assoc($result)) {
        $rows[] = call_user_func($row, $record);
    }

    return array('total' => $total, 'filtered' => $filtered, 'rows' => $rows);
}
