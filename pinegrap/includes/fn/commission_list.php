<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * The commission lists: all commissions (view_commissions.php) and the
 * recurring commission profiles (view_recurring_commission_profiles.php),
 * drawn by the list table driver (includes/fn/list_table.php). Their
 * sources (pg_commission_list_source(), pg_commission_profile_list_source())
 * serve the screens and the driver's list_table_rows action alike: the
 * screen prints the first rows, the next ones come in batches as the list
 * scrolls, searched and sorted on the server.
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
 * answers it: a month ago to today worked out on every request unless the
 * user picked a range (pg_list_period_state()).
 *
 * @param array $request the request's values; the rows action passes none,
 *                       so it reads the range the screen kept
 * @return array array('start' => first second, 'stop' => last second)
 */
function pg_commission_list_range(array $request = array())
{
    $state = pg_list_period_state($_SESSION['software']['ecommerce']['view_commissions'] ?? array(), $request, time());
    $_SESSION['software']['ecommerce']['view_commissions'] = $state;

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
 * A posted commission status when it is one of the stored values, else ''.
 * The status column is an enum and the site runs a non-strict sql_mode, so
 * an unknown value would be stored as '' and the commission would drop out
 * of every status total.
 *
 * @param mixed $value
 * @return string
 */
function pg_commission_posted_status($value)
{
    if (!is_string($value) || !array_key_exists($value, pg_commission_list_statuses())) {
        return '';
    }

    return $value;
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
 * Whether a user may open the commission lists: the screens' own gate
 * (validate_ecommerce_access()), a role above User or the commerce right.
 *
 * @param mixed $user the session user
 * @return bool
 */
function pg_commission_list_allowed($user)
{
    if (!is_array($user) || !isset($user['role']) || !is_numeric($user['role'])) {
        return false;
    }

    return ((int) $user['role'] < 3) || !empty($user['manage_ecommerce']);
}

/**
 * The commissions list's columns for the list table driver
 * (pg_list_table_columns()): the affiliate's name leads as the editor link,
 * the edit button closes the row. Each sort is the SQL the column orders
 * by; a status or a frequency orders by the label shown.
 *
 * @return array key => spec
 */
function pg_commission_list_columns()
{
    return array(
        'affiliate_name' => array('heading' => lang('Affiliate Name'), 'width' => '14rem', 'kind' => 'link', 'sort' => 'contacts.affiliate_name'),
        'affiliate_code' => array('heading' => lang('Affiliate Code'), 'width' => '10rem', 'sort' => 'commissions.affiliate_code'),
        'reference_code' => array('heading' => lang('Reference Code'), 'width' => '10rem', 'sort' => 'commissions.reference_code'),
        'status' => array('heading' => lang('Status'), 'width' => '10rem', 'kind' => 'badge', 'sort' => pg_list_label_order('commissions.status', pg_commission_list_statuses())),
        'amount' => array('heading' => lang('Amount'), 'width' => '8rem', 'kind' => 'money', 'sort' => 'commissions.amount'),
        'frequency' => array('heading' => lang('Frequency'), 'width' => '9rem', 'sort' => pg_list_label_order("IFNULL(recurring_commission_profiles.period, '')", pg_commission_list_periods())),
        'order_number' => array('heading' => lang('Order'), 'width' => '8rem', 'sort' => 'orders.order_number'),
        'product' => array('heading' => lang('Product'), 'width' => '14rem', 'sort' => 'recurring_commission_profiles.product_name'),
        'created' => array('heading' => lang('Created'), 'width' => '11rem', 'kind' => 'date', 'sort' => 'commissions.created_timestamp'),
        'last_modified' => array('heading' => lang('Last Modified'), 'width' => '11rem', 'kind' => 'date', 'sort' => 'commissions.last_modified_timestamp'),
        'actions' => array('heading' => lang('Action'), 'width' => '5rem', 'kind' => 'actions'),
    );
}

/**
 * A commission list's sort menu and kept sort (pg_list_state()), read from
 * its driver columns.
 *
 * @param string $list    the list's pg_list_state() key
 * @param array  $columns the list's column specs
 * @return array array('sort_columns', 'sort_default', 'sort_levels')
 */
function pg_commission_list_sort_state($list, array $columns)
{
    require_once(PG_FUNCTIONS_DIR . '/includes/fn/list_table.php');

    $sort_columns = pg_list_table_sort_columns(pg_list_table_columns($columns));
    $sort_default = array('sort' => 'last_modified', 'order' => 'desc');

    // pg_list_state() reads a sort from the request only on the screen; the
    // rows action's JSON body never fills $_REQUEST.
    $sort_levels = pg_list_state($list, $sort_columns, $sort_default)['sorts'];

    return array('sort_columns' => $sort_columns, 'sort_default' => $sort_default, 'sort_levels' => $sort_levels);
}

/**
 * The commissions list's sort menu and kept sort.
 *
 * @return array array('sort_columns', 'sort_default', 'sort_levels')
 */
function pg_commission_list_sort()
{
    return pg_commission_list_sort_state('view_commissions', pg_commission_list_columns());
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
 * The join of the affiliate behind a code: one contact per code, the oldest,
 * as the API finds it (api_commission_affiliates()). Contacts imported before
 * the importer refused a taken code can share one, and a plain join on the
 * code gave a row per contact, so a commission was listed twice.
 *
 * @param string $code_column the joined table's affiliate code column
 * @return string
 */
function pg_commission_affiliate_join($code_column)
{
    return 'LEFT JOIN (SELECT affiliate_code, MIN(id) AS id FROM contacts WHERE affiliate_code <> \'\' GROUP BY affiliate_code) AS affiliate_contact
            ON affiliate_contact.affiliate_code = ' . $code_column . '
        LEFT JOIN contacts ON contacts.id = affiliate_contact.id';
}

/**
 * The joins of the commissions list; each finds one row at most.
 *
 * @return string
 */
function pg_commission_list_from()
{
    return 'commissions
        ' . pg_commission_affiliate_join('commissions.affiliate_code') . '
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
    return pg_list_when($timestamp, $username);
}

/**
 * The "Cancelled order" badge of a commission or profile whose order is
 * cancelled, '' otherwise. Every status gets it, so a paid commission of a
 * cancelled order is seen for clawback; it reads orders.status, so orders
 * cancelled before their commissions were voided automatically are marked
 * too.
 *
 * @param mixed $order_status orders.status, null without an order
 * @return string escaped
 */
function pg_commission_list_cancelled_badge($order_status)
{
    if ((string) $order_status !== 'cancelled') {
        return '';
    }

    return ' <span class="badge badge-subtle theme-danger border fw-light">' . h(lang('Cancelled order')) . '</span>';
}

/**
 * The Summary card of an edit screen's status column, so the operator sees
 * which commission or profile the status change is for: affiliate name and
 * code, the reference code (a commission), the amount, the order (linked to
 * its screen, marked when cancelled) and, for a profile, the product, start
 * date, frequency and number of commissions. A key the record lacks prints
 * no row.
 *
 * @param array $record the commission or profile row with its affiliate_name,
 *                      order_number and order_status
 * @return string escaped HTML
 */
function pg_commission_edit_summary($record)
{
    $rows = array();
    $rows[lang('Affiliate Name')] = h((string) ($record['affiliate_name'] ?? ''));
    $rows[lang('Affiliate Code')] = h((string) ($record['affiliate_code'] ?? ''));

    if (isset($record['reference_code'])) {
        $rows[lang('Reference Code')] = h((string) $record['reference_code']);
    }

    $rows[lang('Amount')] = pg_format_money(((int) ($record['amount'] ?? 0)) / 100, BASE_CURRENCY_SYMBOL);

    $order = '';
    if (((int) ($record['order_id'] ?? 0) > 0) && ((string) ($record['order_number'] ?? '') !== '')) {
        $order = '<a href="' . OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/view_order.php?id=' . (int) $record['order_id'] . '">' . h($record['order_number']) . '</a>';
    }
    $rows[lang('Order')] = $order . pg_commission_list_cancelled_badge($record['order_status'] ?? null);

    if (array_key_exists('product_name', $record)) {
        $rows[lang('Product')] = h((string) $record['product_name']
            . (((string) ($record['product_short_description'] ?? '') !== '') ? ' - ' . $record['product_short_description'] : ''));
        $rows[lang('Start Date')] = prepare_form_data_for_output($record['start_date'] ?? '', 'date');
        $rows[lang('Frequency')] = h(pg_commission_list_periods()[(string) ($record['period'] ?? '')] ?? lang(ucwords((string) ($record['period'] ?? ''))));
        $rows[lang('Commissions')] = ((int) ($record['number_of_commissions'] ?? 0) === 0) ? h(lang('Unlimited')) : pg_format_number($record['number_of_commissions'], 0);
    }

    $output = '';
    $last = count($rows);
    $number = 0;
    foreach ($rows as $label => $value) {
        $number++;
        $output .= '
                                            <div class="d-flex justify-content-between gap-3 py-2' . (($number < $last) ? ' border-bottom' : '') . '">
                                                <dt class="fw-normal">' . h($label) . '</dt>
                                                <dd class="mb-0 fw-bold text-end text-break">' . (($value !== '') ? $value : '&mdash;') . '</dd>
                                            </div>';
    }

    return '
                                <div class="card mb-7">
                                    <h2 class="card-header bg-reset border-0 pg-title-section">
                                        ' . lang('Summary') . '
                                    </h2>
                                    <div class="card-body pt-0">
                                        <dl class="mb-0">' . $output . '
                                        </dl>
                                    </div>
                                </div>';
}

/**
 * The row of a commission or profile edit screen (the product_builder
 * layout, ui/edit-screen-wide-layout.md): the form column with the given
 * cards, the sticky status column with the record's Summary
 * (pg_commission_edit_summary()) and the sticky save bar.
 *
 * @param string $cards    The form column's cards
 * @param array  $record   The record pg_commission_edit_summary() reads
 * @param string $buttons  The buttons inside the bar's group
 * @return string
 */
function pg_commission_edit_layout($cards, $record, $buttons)
{
    return '
                    <div class="row">
                        <div class="col-12 lg:col-8 2xl:col-9 order-1">' . $cards . '
                        </div>
                        <div class="col-12 lg:col-4 2xl:col-3 order-2">
                            <!-- 3.3rem: the fixed control panel header covers the top of the window. -->
                            <div class="position-sticky" style="top:3.3rem;">' . pg_commission_edit_summary($record) . '
                            </div>
                        </div>
                        <div class="col-12 order-3 position-sticky" style="bottom:0; z-index:3;">
                            <nav class="buttons navigation text-center mb-7" aria-label="' . h(lang('data edit buttons')) . '">
                                <div class="container">
                                    <div class="btn-group flex-wrap justify-content-center">' . $buttons . '
                                    </div>
                                </div>
                            </nav>
                        </div>
                    </div>';
}

/**
 * The cells that open a commission's or profile's editor: the affiliate's
 * name, followed by a double-click or Enter on the row (data-pg-open), and
 * the edit button.
 *
 * @param string $url  the editor's address
 * @param mixed  $name the affiliate's name
 * @return array array('affiliate_name' => pg_list_cell(), 'actions' => pg_list_cell())
 */
function pg_commission_list_edit_cells($url, $name)
{
    $url = h($url);
    $name = (string) $name;

    return array(
        'affiliate_name' => pg_list_cell('<a class="pg-link-body-emphasis text-decoration-none" data-pg-open href="' . $url . '">' . (($name !== '') ? h($name) : '&mdash;') . '</a>', 'align-middle chart_label'),
        'actions' => pg_list_cell('<a class="m-1 btn-data-control btn btn-outline theme-primary border-2" title="' . h(lang('Edit')) . '" aria-label="' . h(lang('Edit')) . '" href="' . $url . '"><i class="bi bi-pencil" aria-hidden="true"></i></a>', 'align-middle text-nowrap action-buttons'),
    );
}

/**
 * One row of the commissions list.
 *
 * @param array $commission
 * @return array array('id', 'class', 'cells' => column key => pg_list_cell())
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

    return array('id' => (int) $commission['id'], 'class' => '', 'cells' => pg_commission_list_edit_cells('edit_commission.php?id=' . (int) $commission['id'], $commission['affiliate_name']) + array(
        'affiliate_code' => pg_list_cell(h($commission['affiliate_code'])),
        'reference_code' => pg_list_cell(h($commission['reference_code'])),
        'status' => pg_list_cell('<span class="badge ' . ($badges[$status] ?? 'theme-secondary fg-white fw-light') . '">' . h(lang(ucwords($status))) . '</span>' . pg_commission_list_cancelled_badge($commission['order_status'] ?? null)),
        'amount' => pg_list_cell(pg_format_money($commission['amount'] / 100, BASE_CURRENCY_SYMBOL), 'align-middle text-end'),
        'frequency' => pg_list_cell(h(pg_commission_list_periods()[(string) $commission['period']] ?? lang(ucwords((string) $commission['period'])))),
        'order_number' => pg_list_cell(h($commission['order_number'])),
        'product' => pg_list_cell(h($product)),
        'created' => pg_list_cell(pg_commission_list_when($commission['created_timestamp'], (string) $commission['created_username'])),
        'last_modified' => pg_list_cell(pg_commission_list_when($commission['last_modified_timestamp'], (string) $commission['last_modified_username'])),
    ));
}

/**
 * The commissions list's source for the list table driver: the commissions
 * of the date range the screen keeps in the session.
 *
 * @param array $user   the session user
 * @param array $params the list parameters the browser sends back (none)
 * @return array|null pg_list_table_source(), or null when the user may not
 *                    open the list
 */
function pg_commission_list_source($user, $params)
{
    if (!pg_commission_list_allowed($user)) {
        return null;
    }

    require_once(PG_FUNCTIONS_DIR . '/includes/fn/list_table.php');

    $range = pg_commission_list_range();

    return pg_list_table_source(array(
        'list' => 'view_commissions',
        'columns' => pg_commission_list_columns(),
        'from' => pg_commission_list_from(),
        'where' => '(commissions.created_timestamp >= ?) AND (commissions.created_timestamp <= ?)',
        'params' => array((string) (int) $range['start'], (string) (int) $range['stop']),
        'id' => 'commissions.id',
        'id_order' => 'desc',
        'select' => 'commissions.id, commissions.affiliate_code, commissions.reference_code, commissions.status, commissions.amount,
            commissions.created_timestamp, commissions.last_modified_timestamp, contacts.affiliate_name, orders.order_number, orders.status AS order_status,
            recurring_commission_profiles.period, recurring_commission_profiles.product_name,
            recurring_commission_profiles.product_short_description,
            created_user.user_username AS created_username, last_modified_user.user_username AS last_modified_username',
        'search' => pg_commission_list_search_columns(),
        'levels' => pg_commission_list_sort()['sort_levels'],
        'created' => 'commissions.created_timestamp',
        'rows' => function ($records) {
            return array_map('pg_commission_list_row', $records);
        },
    ));
}

/**
 * The recurring commission profiles list's columns for the list table
 * driver (pg_list_table_columns()).
 *
 * @return array key => spec
 */
function pg_commission_profile_list_columns()
{
    return array(
        'affiliate_name' => array('heading' => lang('Affiliate Name'), 'width' => '14rem', 'kind' => 'link', 'sort' => 'contacts.affiliate_name'),
        'affiliate_code' => array('heading' => lang('Affiliate Code'), 'width' => '10rem', 'sort' => 'recurring_commission_profiles.affiliate_code'),
        'enabled' => array('heading' => lang('Enabled'), 'width' => '6rem', 'kind' => 'check', 'sort' => 'recurring_commission_profiles.enabled'),
        'amount' => array('heading' => lang('Amount'), 'width' => '8rem', 'kind' => 'money', 'sort' => 'recurring_commission_profiles.amount'),
        'start_date' => array('heading' => lang('Start Date'), 'width' => '9rem', 'kind' => 'date', 'sort' => 'recurring_commission_profiles.start_date'),
        'frequency' => array('heading' => lang('Frequency'), 'width' => '9rem', 'sort' => pg_list_label_order("IFNULL(recurring_commission_profiles.period, '')", pg_commission_list_periods())),
        'commissions' => array('heading' => lang('Commissions'), 'width' => '8rem', 'kind' => 'badge', 'sort' => 'recurring_commission_profiles.number_of_commissions'),
        'order_number' => array('heading' => lang('Order'), 'width' => '8rem', 'sort' => 'orders.order_number'),
        'product' => array('heading' => lang('Product'), 'width' => '14rem', 'sort' => 'recurring_commission_profiles.product_name'),
        'created' => array('heading' => lang('Created'), 'width' => '11rem', 'kind' => 'date', 'sort' => 'recurring_commission_profiles.created_timestamp'),
        'last_modified' => array('heading' => lang('Last Modified'), 'width' => '11rem', 'kind' => 'date', 'sort' => 'recurring_commission_profiles.last_modified_timestamp'),
        'actions' => array('heading' => lang('Action'), 'width' => '5rem', 'kind' => 'actions'),
    );
}

/**
 * The recurring commission profiles list's sort menu and kept sort.
 *
 * @return array array('sort_columns', 'sort_default', 'sort_levels')
 */
function pg_commission_profile_list_sort()
{
    return pg_commission_list_sort_state('view_recurring_commission_profiles', pg_commission_profile_list_columns());
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
 * @return array array('id', 'class', 'cells' => column key => pg_list_cell())
 */
function pg_commission_profile_list_row($profile)
{
    $product = (string) $profile['product_name']
        . (((string) $profile['product_short_description'] !== '') ? ' - ' . $profile['product_short_description'] : '');
    $commissions = ((int) $profile['number_of_commissions'] === 0) ? lang('Unlimited') : pg_format_number($profile['number_of_commissions'], 0);

    return array('id' => (int) $profile['id'], 'class' => '', 'cells' => pg_commission_list_edit_cells('edit_recurring_commission_profile.php?id=' . (int) $profile['id'], $profile['affiliate_name']) + array(
        'affiliate_code' => pg_list_cell(h($profile['affiliate_code'])),
        'enabled' => pg_list_cell(($profile['enabled'] == 1) ? '<span class="material-icons" aria-hidden="true">task_alt</span><span class="visually-hidden">' . h(lang('Yes')) . '</span>' : '', 'align-middle text-center'),
        'amount' => pg_list_cell('<span class="badge theme-primary fw-light">' . pg_format_money($profile['amount'] / 100, BASE_CURRENCY_SYMBOL) . '</span>', 'align-middle text-end'),
        'start_date' => pg_list_cell(prepare_form_data_for_output($profile['start_date'], 'date')),
        'frequency' => pg_list_cell(h(pg_commission_list_periods()[(string) $profile['period']] ?? lang(ucwords((string) $profile['period'])))),
        'commissions' => pg_list_cell('<span class="badge theme-success fw-light">' . h($commissions) . '</span>'),
        'order_number' => pg_list_cell(h($profile['order_number']) . pg_commission_list_cancelled_badge($profile['order_status'] ?? null)),
        'product' => pg_list_cell(h($product)),
        'created' => pg_list_cell(pg_commission_list_when($profile['created_timestamp'], (string) $profile['created_username'])),
        'last_modified' => pg_list_cell(pg_commission_list_when($profile['last_modified_timestamp'], (string) $profile['last_modified_username'])),
    ));
}

/**
 * The recurring commission profiles list's source for the list table
 * driver.
 *
 * @param array $user   the session user
 * @param array $params the list parameters the browser sends back (none)
 * @return array|null pg_list_table_source(), or null when the user may not
 *                    open the list
 */
function pg_commission_profile_list_source($user, $params)
{
    if (!pg_commission_list_allowed($user)) {
        return null;
    }

    require_once(PG_FUNCTIONS_DIR . '/includes/fn/list_table.php');

    return pg_list_table_source(array(
        'list' => 'view_recurring_commission_profiles',
        'columns' => pg_commission_profile_list_columns(),
        'from' => pg_commission_profile_list_from(),
        'id' => 'recurring_commission_profiles.id',
        'id_order' => 'desc',
        'select' => 'recurring_commission_profiles.id, recurring_commission_profiles.affiliate_code, recurring_commission_profiles.enabled,
            recurring_commission_profiles.amount, recurring_commission_profiles.start_date, recurring_commission_profiles.period,
            recurring_commission_profiles.number_of_commissions, recurring_commission_profiles.product_name,
            recurring_commission_profiles.product_short_description, recurring_commission_profiles.created_timestamp,
            recurring_commission_profiles.last_modified_timestamp, contacts.affiliate_name, orders.order_number, orders.status AS order_status,
            created_user.user_username AS created_username, last_modified_user.user_username AS last_modified_username',
        'search' => pg_commission_profile_list_search_columns(),
        'levels' => pg_commission_profile_list_sort()['sort_levels'],
        'created' => 'recurring_commission_profiles.created_timestamp',
        'rows' => function ($records) {
            return array_map('pg_commission_profile_list_row', $records);
        },
    ));
}

/**
 * The joins of the recurring commission profiles list; each finds one row
 * at most.
 *
 * @return string
 */
function pg_commission_profile_list_from()
{
    return 'recurring_commission_profiles
        ' . pg_commission_affiliate_join('recurring_commission_profiles.affiliate_code') . '
        LEFT JOIN orders ON recurring_commission_profiles.order_id = orders.id
        LEFT JOIN user AS created_user ON recurring_commission_profiles.created_user_id = created_user.user_id
        LEFT JOIN user AS last_modified_user ON recurring_commission_profiles.last_modified_user_id = last_modified_user.user_id';
}

/**
 * The rows of the commissions screen's status totals (status, total) as one minor-unit total per
 * status; a status without commissions is 0. Pure.
 *
 * @param array $rows status, total
 * @return array status => int
 */
function pg_commission_list_totals(array $rows)
{
    $totals = array_fill_keys(array_keys(pg_commission_list_statuses()), 0);

    foreach ($rows as $row) {
        $status = (string) ($row['status'] ?? '');

        if (array_key_exists($status, $totals)) {
            $totals[$status] = (int) round((float) $row['total']);
        }
    }

    return $totals;
}

/**
 * What commission.status_changed carries for a commission moved to $status:
 * the row's identity and money, the new and the old status. Pure.
 *
 * @param array  $row    id, reference_code, affiliate_code, order_id, amount, status (the status before)
 * @param string $status the status written
 * @return array
 */
function pg_commission_status_event($row, $status)
{
    return array(
        'id'              => (int) ($row['id'] ?? 0),
        'reference_code'  => (string) ($row['reference_code'] ?? ''),
        'status'          => (string) $status,
        'previous_status' => (string) ($row['status'] ?? ''),
        'affiliate_code'  => (string) ($row['affiliate_code'] ?? ''),
        'order_id'        => ((int) ($row['order_id'] ?? 0) > 0) ? (int) $row['order_id'] : null,
        'amount'          => (int) ($row['amount'] ?? 0),
    );
}

/**
 * Sets a commission's status, as edit_commission.php and the external API
 * (POST /commissions/{id}) do: only a stored status is written
 * (pg_commission_posted_status()), and the mark the order's cancellation
 * left is cleared, so restoring the order does not undo the operator's
 * choice. Announces commission.status_changed when the status moved; a
 * save that keeps the status writes the user and the time and announces
 * nothing.
 *
 * @param int    $id
 * @param mixed  $status
 * @param int    $user_id the person or the application's owner
 * @return string|false the status before the save; false when the status
 *                      is not a stored one, the commission does not exist
 *                      or the query failed
 */
function pg_commission_status_save($id, $status, $user_id)
{
    $status = pg_commission_posted_status($status);

    if ($status === '') {
        return false;
    }

    $row = db_item("SELECT id, reference_code, affiliate_code, order_id, amount, status FROM commissions WHERE id = '" . (int) $id . "'");

    if (!$row) {
        return false;
    }

    $cancel_marker_sql = pg_schema_has('commissions', 'status_before_cancel') ? "status_before_cancel = ''," : '';

    $result = db_q(
        "UPDATE commissions
        SET
            status = ?,
            " . $cancel_marker_sql . "
            last_modified_user_id = ?,
            last_modified_timestamp = UNIX_TIMESTAMP()
        WHERE id = ?",
        array($status, (string) (int) $user_id, (string) (int) $id));

    if (!$result) {
        return false;
    }

    if ((string) $row['status'] !== $status) {
        pg_announce('commission.status_changed', pg_commission_status_event($row, $status));
    }

    return (string) $row['status'];
}
