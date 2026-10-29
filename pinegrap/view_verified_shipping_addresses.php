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

$output_state_options = '';

foreach ($states as $state) {
    $selected = ($state['id'] == ($_SESSION['software']['ecommerce']['view_verified_shipping_addresses']['state_id'] ?? '')) ? ' selected="selected"' : '';

    $output_state_label = $foreign_states
        ? h(pg_geo_country_label($state['country_name'], $state['country_code'])) . ': ' . h($state['name'])
        : h($state['name']);

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

// What each sort code orders by. Only codes pg_list_state() let through
// reach pg_list_order_by(), and it writes nothing but these.
$order_by = pg_list_order_by($sort_levels, array(
    'company'       => 'verified_shipping_addresses.company',
    'address_1'     => 'verified_shipping_addresses.address_1',
    'address_2'     => 'verified_shipping_addresses.address_2',
    'city'          => 'verified_shipping_addresses.city',
    'state'         => 'states.name',
    'zip_code'      => 'verified_shipping_addresses.zip_code',
    'country'       => 'countries.name',
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

$output_rows = '';

foreach ($verified_shipping_addresses as $address) {

    $created_username = '';

    if ($address['created_username'] != '') {
        $created_username = ' ' . lang(array('string' => 'by {var:1}', 'vars' => array(h($address['created_username']))));
    }

    $last_modified_username = '';

    if ($address['last_modified_username'] != '') {
        $last_modified_username = ' ' . lang(array('string' => 'by {var:1}', 'vars' => array(h($address['last_modified_username']))));
    }

    $output_rows .=
        '<tr>
            <td></td>
            <td class="align-middle text-start">
                <button type="button" class="m-1 btn-data-control btn btn-outline theme-primary border-2" title="' . lang('Edit') . '" onclick="window.location.href=\'edit_verified_shipping_address.php?id=' . $address['id'] . '\'"><i class="bi bi-pencil"></i></button>
            </td>
            <td class="chart_label align-middle">' . h($address['company']) . '</td>
            <td class="align-middle">' . h($address['address_1']) . '</td>
            <td class="align-middle">' . h($address['address_2']) . '</td>
            <td class="align-middle">' . h($address['city']) . '</td>
            <td class="align-middle">' . h($address['state_name']) . '</td>
            <td class="align-middle">' . h($address['zip_code']) . '</td>
            <td class="align-middle">' . h(pg_geo_country_label($address['country_name'], $address['country_code'])) . '</td>
            <td class="align-middle">' . get_relative_time(array('timestamp' => $address['created_timestamp'])) . $created_username . '</td>
            <td class="align-middle">' . get_relative_time(array('timestamp' => $address['last_modified_timestamp'])) . $last_modified_username . '</td>
        </tr>';
}

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

                <div class="row mb-3 flex-wrap">
                    <div class="col-12 text-center md:text-start">
                        
                        <nav id="button_bar" class="navigation" aria-label="' . h(lang('Button Bar')) . '">
                            <a class="btn btn-sm btn-solid theme-primary m-1" href="add_verified_shipping_address.php?state_id=' . h(urlencode($_SESSION['software']['ecommerce']['view_verified_shipping_addresses']['state_id'] ?? '')) . '"><span class="bi bi-plus-circle me-3"></span>' . lang('Create') . '</a>
                            <div class="pg-toolbar-grow"></div>
                            ' . pg_list_sort_menu('view_verified_shipping_addresses.php', $sort_columns, $sort_levels, $sort_default) . '
                        </nav>
                    </div>
                </div>
                <div class="card my-7">
                    <div class="card-body p-0 position-relative">
                        <div class="p-5 border-bottom">
                            <form id="state_filter" action="view_verified_shipping_addresses.php" method="get" class="d-flex align-items-center gap-3 flex-wrap">
                                <label for="state_id" class="fw-semibold">' . lang('State') . ':</label>
                                <select id="state_id" name="state_id" class="form-control form-control-sm w-auto" onchange="document.getElementById(\'state_filter\').submit()">
                                    <option value="[All]"' . (($_SESSION['software']['ecommerce']['view_verified_shipping_addresses']['state_id'] ?? '') == '[All]' ? ' selected="selected"' : '') . '>[' . lang('All') . '] (' . pg_format_number($all_verified_shipping_addresses, 0) . ')</option>
                                    ' . $output_state_options . '
                                </select>
                            </form>
                        </div>
                        <table class="chart table-hover table" style="width:100%;display:none">
                            <thead>
                                <tr>
                                    <th class="noVis"></th>
                                    <th class="noVis">' . lang('Action') . '</th>
                                    <th>' . get_column_heading(lang('Company'), $sort_levels, '', '', 'company') . '</th>
                                    <th>' . get_column_heading(lang('Address 1'), $sort_levels, '', '', 'address_1') . '</th>
                                    <th>' . get_column_heading(lang('Address 2'), $sort_levels, '', '', 'address_2') . '</th>
                                    <th>' . get_column_heading(lang('City'), $sort_levels, '', '', 'city') . '</th>
                                    <th>' . get_column_heading(lang('State'), $sort_levels, '', '', 'state') . '</th>
                                    <th>' . get_column_heading(lang('Zip Code'), $sort_levels, '', '', 'zip_code') . '</th>
                                    <th>' . get_column_heading(lang('Country'), $sort_levels, '', '', 'country') . '</th>
                                    <th>' . get_column_heading(lang('Created'), $sort_levels, '', '', 'created') . '</th>
                                    <th>' . get_column_heading(lang('Last Modified'), $sort_levels, '', '', 'last_modified') . '</th>
                                </tr>
                            </thead>
                            <tbody>' . $output_rows . '</tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    
</main>' .
    output_footer();

$liveform->remove_form();
