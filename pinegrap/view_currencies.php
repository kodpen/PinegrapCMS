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
require_once(PG_FUNCTIONS_DIR . '/includes/forms/currency_form.php');
require_once(PG_FUNCTIONS_DIR . '/includes/fn/currency_list_edit.php');

// Every value the currency's screen changes is a field.
$quick_edit = pg_currency_edit_columns();

// A value changed in place on the list (pg_list_edit_request() in
// includes/fn/output.php). Answered in JSON, before anything below draws the
// screen, with the currency screen's rights (validate_ecommerce_access())
// and checks (pg_currency_quick_update()).
if (pg_list_edit_requested()) {
    $edit = pg_list_edit_request($quick_edit, function ($user) {
        return ($user['role'] < 3) || !empty($user['manage_ecommerce']);
    });

    $saved = pg_currency_quick_update($edit['id'], $edit['field'], $edit['value'], (int) $edit['user']['id'], $quick_edit);

    pg_list_edit_reply($saved, $saved['ok'] ? 200 : 422);
}

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

$query = pg_currency_list_sql() . " ORDER BY $order_by";

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
    $list_rows[] = array('id' => (int) $currency['id'], 'cells' => pg_currency_list_cells($currency, $quick_edit, (string) BASE_CURRENCY_CODE));
}

$list = pg_list_table_printed(array(
    'list' => 'view_currencies',
    'columns' => pg_currency_list_columns(true),
    'levels' => $sort_levels,
    'rows' => $list_rows,
    'edit' => array('url' => 'view_currencies.php', 'columns' => $quick_edit),
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
