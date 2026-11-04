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
$liveform = new liveform('view_verified_shipping_addresses');

// The state filter is kept in the session, so the list opens on the state
// picked last. The sort is kept by pg_list_state() below.
if (isset($_REQUEST['state_id']) && is_string($_REQUEST['state_id'])) {
    $_SESSION['software']['ecommerce']['view_verified_shipping_addresses']['state_id'] = trim($_REQUEST['state_id']);
}

// if state is not set yet, set default to [All]
if (isset($_SESSION['software']['ecommerce']['view_verified_shipping_addresses']['state_id']) == false) {
    $_SESSION['software']['ecommerce']['view_verified_shipping_addresses']['state_id'] = '[All]';
}

// check if foreign states exist
$foreign_states = (bool) db_value(
    "SELECT COUNT(*)
    FROM states
    LEFT JOIN countries ON states.country_id = countries.id
    WHERE countries.default_selected = '0'");

// get all states for state filter pick list
$states = db_items(
    "SELECT
        states.id,
        states.name,
        countries.name AS country_name,
        countries.code AS country_code
    FROM states
    LEFT JOIN countries ON states.country_id = countries.id
    ORDER BY countries.name ASC, states.name ASC");

// A remembered state that is gone goes back to [All].
$_SESSION['software']['ecommerce']['view_verified_shipping_addresses']['state_id'] = pg_list_filter_known(
    $_SESSION['software']['ecommerce']['view_verified_shipping_addresses']['state_id'],
    array_column($states, 'id'));

$output_state_options = '';

foreach (pg_geo_state_option_labels($states, $foreign_states) as $state) {
    $selected = ($state['id'] == ($_SESSION['software']['ecommerce']['view_verified_shipping_addresses']['state_id'] ?? '')) ? ' selected="selected"' : '';

    $output_state_label = h($state['label']);

    $count = (int) db_value("SELECT COUNT(*) FROM verified_shipping_addresses WHERE state_id = '" . escape($state['id']) . "'");

    $output_state_options .= '<option value="' . $state['id'] . '"' . $selected . '>' . $output_state_label . ' (' . pg_format_number($count, 0) . ')</option>';
}

// The columns this list sorts by, in the order the table shows them.
// pg_list_state() keeps the choice in the session as a code, so it survives
// a language change, and falls back to the default for anything else.
$sort_columns = array(
    'company'       => lang('Company'),
    'address_1'     => lang('Address 1'),
    'address_2'     => lang('Address 2'),
    'city'          => lang('City'),
    'state'         => lang('State'),
    'zip_code'      => lang('Zip Code'),
    'country'       => lang('Country'),
    'created'       => lang('Created'),
    'last_modified' => lang('Last Modified'),
);

$sort_default = array('sort' => 'last_modified', 'order' => 'desc');

$list_state = pg_list_state('view_verified_shipping_addresses', $sort_columns, $sort_default);
$sort_levels = $list_state['sorts'];

// The country column shows the name in the panel's language, so it sorts
// by that name, not by the stored English one.
$country_labels = array();

foreach (db_items("SELECT code, name FROM countries") as $country) {
    $country_labels[(string) $country['code']] = pg_geo_country_label($country['name'], $country['code']);
}

// What each sort code orders by. Only codes pg_list_state() let through
// reach pg_list_order_by(), and it writes nothing but these.
$order_by = pg_list_order_by($sort_levels, array(
    'company'       => 'verified_shipping_addresses.company',
    'address_1'     => 'verified_shipping_addresses.address_1',
    'address_2'     => 'verified_shipping_addresses.address_2',
    'city'          => 'verified_shipping_addresses.city',
    'state'         => 'states.name',
    'zip_code'      => 'verified_shipping_addresses.zip_code',
    'country'       => pg_list_label_order('countries.code', $country_labels),
    'created'       => 'verified_shipping_addresses.created_timestamp',
    'last_modified' => 'verified_shipping_addresses.last_modified_timestamp',
));

$all_verified_shipping_addresses = (int) db_value("SELECT COUNT(*) FROM verified_shipping_addresses");

$where = '';

// if user has selected a state, then prepare where clause
if (($_SESSION['software']['ecommerce']['view_verified_shipping_addresses']['state_id'] ?? '') != '[All]') {
    $where = "WHERE verified_shipping_addresses.state_id = '" . escape(($_SESSION['software']['ecommerce']['view_verified_shipping_addresses']['state_id'] ?? '')) . "'";
}

$verified_shipping_addresses = db_items(
    "SELECT
        verified_shipping_addresses.id,
        verified_shipping_addresses.company,
        verified_shipping_addresses.address_1,
        verified_shipping_addresses.address_2,
        verified_shipping_addresses.city,
        states.name AS state_name,
        verified_shipping_addresses.zip_code,
        countries.name AS country_name,
        countries.code AS country_code,
        created_user.user_username AS created_username,
        verified_shipping_addresses.created_timestamp,
        last_modified_user.user_username AS last_modified_username,
        verified_shipping_addresses.last_modified_timestamp
    FROM verified_shipping_addresses
    LEFT JOIN states ON verified_shipping_addresses.state_id = states.id
    LEFT JOIN countries ON states.country_id = countries.id
    LEFT JOIN user AS created_user ON verified_shipping_addresses.created_user_id = created_user.user_id
    LEFT JOIN user AS last_modified_user ON verified_shipping_addresses.last_modified_user_id = last_modified_user.user_id
    $where
    ORDER BY $order_by");

// Every address of the state filter is printed: the list table driver
// sorts and searches them in the browser (browser mode) and keeps a
// heading's sort.
$list_rows = array();

foreach ($verified_shipping_addresses as $address) {
    $list_rows[] = array(
        'id' => (int) $address['id'],
        'cells' => array(
            'company' => pg_list_cell('<a href="edit_verified_shipping_address.php?id=' . (int) $address['id'] . '" data-pg-open>' . h(($address['company'] != '') ? $address['company'] : '#' . $address['id']) . '</a>'),
            'address_1' => pg_list_cell(h($address['address_1'])),
            'address_2' => pg_list_cell(h($address['address_2'])),
            'city' => pg_list_cell(h($address['city'])),
            'state' => pg_list_cell(h($address['state_name'])),
            'zip_code' => pg_list_cell(h($address['zip_code'])),
            'country' => pg_list_cell(h(pg_geo_country_label($address['country_name'], $address['country_code']))),
            'created' => pg_list_table_when_cell($address['created_timestamp'], (string) $address['created_username']),
            'last_modified' => pg_list_table_when_cell($address['last_modified_timestamp'], (string) $address['last_modified_username']),
        ),
    );
}

$list = pg_list_table_printed(array(
    'list' => 'view_verified_shipping_addresses',
    'columns' => array(
        'company' => array('heading' => lang('Company')),
        'address_1' => array('heading' => lang('Address 1')),
        'address_2' => array('heading' => lang('Address 2')),
        'city' => array('heading' => lang('City')),
        'state' => array('heading' => lang('State')),
        'zip_code' => array('heading' => lang('Zip Code')),
        'country' => array('heading' => lang('Country')),
        'created' => array('heading' => lang('Created'), 'kind' => 'date'),
        'last_modified' => array('heading' => lang('Last Modified'), 'kind' => 'date'),
    ),
    'levels' => $sort_levels,
    'rows' => $list_rows,
));

// The state filter sits in the toolbar beside the search box.
$output_state_filter = '<form id="state_filter" action="view_verified_shipping_addresses.php" method="get" class="d-inline-flex align-items-center gap-3">
        <label for="state_id" class="small fg-secondary">' . lang('State') . '</label>
        <select id="state_id" name="state_id" class="form-control form-control-sm w-auto max-w-100" onchange="document.getElementById(\'state_filter\').submit()">
            <option value="[All]"' . (($_SESSION['software']['ecommerce']['view_verified_shipping_addresses']['state_id'] ?? '') == '[All]' ? ' selected="selected"' : '') . '>[' . lang('All') . '] (' . pg_format_number($all_verified_shipping_addresses, 0) . ')</option>
            ' . $output_state_options . '
        </select>
    </form>';

echo
    pg_page_shell([
        'title'=> lang('All Verified Shipping Addresses'),
        'extra classes'=>'products',
        'icon'=>'store',
        'heading'=>lang('All Verified Shipping Addresses'),
        'heading_description' => lang('All optional shipping addresses that can be selected on Shipping Address & Arrival Pages.'),
    ]) . '
<main id="content" class="container-fluid">
            <div class="row">
            <div class="col-12">
                ' . $liveform->output_errors() . '
                ' . $liveform->get_warnings() . '
                ' . $liveform->output_notices() . '

                ' . pg_screen_toolbar(array(
                    'primary' => array('label' => lang('Create'), 'url' => 'add_verified_shipping_address.php?state_id=' . urlencode($_SESSION['software']['ecommerce']['view_verified_shipping_addresses']['state_id'] ?? ''), 'icon' => 'bi-plus-circle'),
                    'search'  => array($output_state_filter, pg_list_table_search_box('view_verified_shipping_addresses'), pg_list_sort_menu('view_verified_shipping_addresses.php', $sort_columns, $sort_levels, $sort_default)),
                )) . '
                ' . pg_list_table_html($list['source'], $list['page'], lang('All Verified Shipping Addresses')) . '
            </div>
        </div>
    
</main>
' . pg_js_bundle_tags('assets/js/pg_list_table.min.js') .
    output_footer();

$liveform->remove_form();
