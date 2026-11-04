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
validate_ecommerce_access($user);

include_once('liveform.class.php');
$liveform = new liveform('view_currencies');

// The columns this list sorts by, in the order the table shows them.
// pg_list_state() keeps the choice in the session as a code, so it survives
// a language change, and falls back to the default for anything else.
$sort_columns = array(
    'name'          => lang('Name'),
    'base'          => lang('Base'),
    'code'          => lang('Code'),
    'symbol'        => lang('Symbol'),
    'exchange_rate' => lang('Exchange Rate'),
    'created'       => lang('Created'),
    'last_modified' => lang('Last Modified'),
);

$sort_default = array('sort' => 'last_modified', 'order' => 'desc');

$list_state = pg_list_state('view_currencies', $sort_columns, $sort_default);
$sort_levels = $list_state['sorts'];

// What each sort code orders by. Only codes pg_list_state() let through
// reach pg_list_order_by(), and it writes nothing but these.
$order_by = pg_list_order_by($sort_levels, array(
    'name'          => 'currencies.name',
    'base'          => 'currencies.base',
    'code'          => 'currencies.code',
    'symbol'        => 'currencies.symbol',
    'exchange_rate' => 'currencies.exchange_rate',
    'created'       => 'currencies.created_timestamp',
    'last_modified' => 'currencies.last_modified_timestamp',
));

// get all of the currency information. Join user_id with username
$query =
    "SELECT
        currencies.id,
        currencies.name,
        currencies.base,
        currencies.code,
        currencies.symbol,
        currencies.exchange_rate,
        currencies.created_user_id,
        currencies.created_timestamp,
        currencies.last_modified_user_id,
        currencies.last_modified_timestamp,
        created_user.user_username as created_username,
        last_modified_user.user_username as last_modified_username
    FROM currencies
    LEFT JOIN user as created_user ON currencies.created_user_id = created_user.user_id
    LEFT JOIN user as last_modified_user ON currencies.last_modified_user_id = last_modified_user.user_id
    ORDER BY $order_by";

$result = mysqli_query(db::$con, $query) or output_error('Query failed.');

$currencies = array();

while ($row = mysqli_fetch_assoc($result)) {
    $currencies[] = $row;
}

// The name and the symbol are shown translated and decoded, so the order
// is settled on what the screen shows, in the panel language's alphabet.
$currencies = pg_currency_list_sort($currencies, $sort_levels);

// Every currency is printed: the list table driver sorts and searches them
// in the browser (browser mode) and keeps a heading's sort.
$list_rows = array();

foreach ($currencies as $currency) {

    // The stored rate is units of this currency per 1 base unit; the
    // reverse (1 USD = 49,95 TRY) is how a rate is usually quoted.
    $output_inverse_rate = '';

    if (($currency['base'] != 1) && ((float) $currency['exchange_rate'] > 0)) {
        $output_inverse_rate = '<small class="d-block fg-secondary">1 ' . h($currency['code']) . ' = ' . pg_format_number(1 / (float) $currency['exchange_rate'], 4) . ' ' . h((string) BASE_CURRENCY_CODE) . '</small>';
    }

    $list_rows[] = array(
        'id' => (int) $currency['id'],
        'cells' => array(
            'name' => pg_list_cell('<a href="edit_currency.php?id=' . (int) $currency['id'] . '" data-pg-open>' . h(pg_currency_label($currency['name'])) . '</a>'),
            'base' => pg_list_table_check_cell($currency['base'] == 1),
            'code' => pg_list_cell(h($currency['code'])),
            // The symbol is stored as HTML (an entity like &#36;).
            'symbol' => pg_list_cell($currency['symbol']),
            'exchange_rate' => pg_list_cell(pg_format_number((float) $currency['exchange_rate'], 5) . $output_inverse_rate, 'align-middle', array('data-pg-sort-value' => (float) $currency['exchange_rate'])),
            'created' => pg_list_table_when_cell($currency['created_timestamp'], (string) $currency['created_username']),
            'last_modified' => pg_list_table_when_cell($currency['last_modified_timestamp'], (string) $currency['last_modified_username']),
        ),
    );
}

$list = pg_list_table_printed(array(
    'list' => 'view_currencies',
    'columns' => array(
        'name' => array('heading' => lang('Name')),
        'base' => array('heading' => lang('Base'), 'kind' => 'check'),
        'code' => array('heading' => lang('Code')),
        'symbol' => array('heading' => lang('Symbol')),
        'exchange_rate' => array('heading' => lang('Exchange Rate'), 'kind' => 'number'),
        'created' => array('heading' => lang('Created'), 'kind' => 'date'),
        'last_modified' => array('heading' => lang('Last Modified'), 'kind' => 'date'),
    ),
    'levels' => $sort_levels,
    'rows' => $list_rows,
));

print
pg_page_shell(
    array(
        'title'=> lang('All Currencies'),
        'extra classes'=>'setting',
        'icon'=>'setting', 
        'heading'=>lang('All Currencies'),
        'heading_description' => lang('All currencies available for customers to select.'),
    )
) . '
<main id="content" class="container-fluid">
    <div class="row">
        <div class="col-12">
            ' . $liveform->output_errors() . '
            ' . $liveform->get_warnings() . '
            ' . $liveform->output_notices() . '
            ' . pg_screen_toolbar(array(
                'primary'   => array('label' => lang('Create'), 'url' => 'add_currency.php', 'icon' => 'bi-plus-circle'),
                'secondary' => array('label' => lang('Update Exchange Rates'), 'url' => 'update_exchange_rates.php?send_to=' . urlencode(get_request_uri()) . '&token=' . ($_SESSION['software']['token'] ?? ''), 'icon' => 'bi-currency-exchange'),
                'search'    => array(pg_list_table_search_box('view_currencies'), pg_list_sort_menu('view_currencies.php', $sort_columns, $sort_levels, $sort_default)),
            )) . '
            ' . pg_list_table_html($list['source'], $list['page'], lang('All Currencies')) . '
        </div>
    </div>
</main>
' . pg_js_bundle_tags('assets/js/pg_list_table.min.js') .
output_footer();
$liveform->clear_notices();
$liveform->remove_form();
