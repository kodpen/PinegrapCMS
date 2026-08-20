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
 *              2016–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

include('init.php');

$user = validate_user();
validate_ecommerce_access($user);

include_once('liveform.class.php');
$liveform = new liveform('view_verified_shipping_addresses');

// store all values collected in request to session
foreach ($_REQUEST as $key => $value) {
    if (is_string($value) == TRUE) {
        $_SESSION['software']['ecommerce']['view_verified_shipping_addresses'][$key] = trim($value);
    }
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
        countries.name AS country_name
    FROM states
    LEFT JOIN countries ON states.country_id = countries.id
    ORDER BY countries.name ASC, states.name ASC");

$output_state_options = '';

foreach ($states as $state) {
    $selected = ($state['id'] == ($_SESSION['software']['ecommerce']['view_verified_shipping_addresses']['state_id'] ?? '')) ? ' selected="selected"' : '';

    $output_state_label = $foreign_states
        ? h($state['country_name']) . ': ' . h($state['name'])
        : h($state['name']);

    $count = (int) db_value("SELECT COUNT(*) FROM verified_shipping_addresses WHERE state_id = '" . escape($state['id']) . "'");

    $output_state_options .= '<option value="' . $state['id'] . '"' . $selected . '>' . $output_state_label . ' (' . number_format($count) . ')</option>';
}

// if the sort is not set yet, then default it to empty so that the switch below falls
// through to its default case
if (isset($_SESSION['software']['ecommerce']['view_verified_shipping_addresses']['sort']) == false) {
    $_SESSION['software']['ecommerce']['view_verified_shipping_addresses']['sort'] = '';
}

switch (($_SESSION['software']['ecommerce']['view_verified_shipping_addresses']['sort'] ?? '')) {
    case lang('Company'):
        $sort_column = 'verified_shipping_addresses.company';
        break;

    case lang('Address 1'):
        $sort_column = 'verified_shipping_addresses.address_1';
        break;

    case lang('Address 2'):
        $sort_column = 'verified_shipping_addresses.address_2';
        break;

    case lang('City'):
        $sort_column = 'verified_shipping_addresses.city';
        break;

    case lang('State'):
        $sort_column = 'states.name';
        break;

    case lang('Zip Code'):
        $sort_column = 'verified_shipping_addresses.zip_code';
        break;

    case lang('Country'):
        $sort_column = 'countries.name';
        break;

    case lang('Created'):
        $sort_column = 'verified_shipping_addresses.created_timestamp';
        break;

    case lang('Last Modified'):
        $sort_column = 'verified_shipping_addresses.last_modified_timestamp';
        break;

    default:
        $sort_column = 'verified_shipping_addresses.last_modified_timestamp';
        $_SESSION['software']['ecommerce']['view_verified_shipping_addresses']['sort'] = lang('Last Modified');
        $_SESSION['software']['ecommerce']['view_verified_shipping_addresses']['order'] = 'desc';
        break;
}

// if order is not set, set to ascending
if (isset($_SESSION['software']['ecommerce']['view_verified_shipping_addresses']['order']) == false) {
    $_SESSION['software']['ecommerce']['view_verified_shipping_addresses']['order'] = 'asc';
}

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
    ORDER BY $sort_column " . escape(($_SESSION['software']['ecommerce']['view_verified_shipping_addresses']['order'] ?? '')));

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
                <button type="button" class="m-1 btn-data-control btn btn-outline-primary border-2" data-loading-content=" " title="' . lang('Edit') . '" onclick="window.location.href=\'edit_verified_shipping_address.php?id=' . $address['id'] . '\'"><i class="bi bi-pencil"></i></button>
            </td>
            <td class="chart_label align-middle">' . h($address['company']) . '</td>
            <td class="align-middle">' . h($address['address_1']) . '</td>
            <td class="align-middle">' . h($address['address_2']) . '</td>
            <td class="align-middle">' . h($address['city']) . '</td>
            <td class="align-middle">' . h($address['state_name']) . '</td>
            <td class="align-middle">' . h($address['zip_code']) . '</td>
            <td class="align-middle">' . h($address['country_name']) . '</td>
            <td class="align-middle">' . get_relative_time(array('timestamp' => $address['created_timestamp'])) . $created_username . '</td>
            <td class="align-middle">' . get_relative_time(array('timestamp' => $address['last_modified_timestamp'])) . $last_modified_username . '</td>
        </tr>';
}

echo
    pg_page_shell([
        'title'=> lang('All Verified Shipping Addresses'),
        'extra classes'=>'products',
        'icon'=>'store',
        'heading'=>lang('All Verified Shipping Addresses')
    ]) . '
            <div class="row">
            <div class="col-12">
                ' . $liveform->output_errors() . '
                ' . $liveform->get_warnings() . '
                ' . $liveform->output_notices() . '

                <div class="row mb-2 flex-wrap">
                    <div class="col-12 text-center text-md-start">
                        <h2 class="d-inline-block" data-bs-content="' . lang('All optional shipping addresses that can be selected on Shipping Address & Arrival Pages.') . '" title="' . lang('All Verified Shipping Addresses') . '">' . lang('All Verified Shipping Addresses') . '</h2>
                        <nav id="button_bar" class="navigation" aria-label="Button Bar">
                            <a class="btn btn-sm btn-primary m-1" href="add_verified_shipping_address.php?state_id=' . escape(($_SESSION['software']['ecommerce']['view_verified_shipping_addresses']['state_id'] ?? '')) . '" data-loading-content="' . lang('Loading') . '"><span class="bi bi-plus-circle me-2"></span>' . lang('Create') . '</a>
                        </nav>
                    </div>
                </div>
                <div class="card my-4">
                    <div class="card-body p-0 position-relative">
                        <div class="p-3 border-bottom">
                            <form id="state_filter" action="view_verified_shipping_addresses.php" method="get" class="d-flex align-items-center gap-2 flex-wrap">
                                <label class="fw-semibold">' . lang('State') . ':</label>
                                <select name="state_id" class="form-select form-select-sm w-auto" onchange="document.getElementById(\'state_filter\').submit()">
                                    <option value="[All]"' . (($_SESSION['software']['ecommerce']['view_verified_shipping_addresses']['state_id'] ?? '') == '[All]' ? ' selected="selected"' : '') . '>[' . lang('All') . '] (' . number_format($all_verified_shipping_addresses) . ')</option>
                                    ' . $output_state_options . '
                                </select>
                            </form>
                        </div>
                        <table class="chart table-hover table" style="width:100%;display:none">
                            <thead>
                                <tr>
                                    <th class="noVis"></th>
                                    <th class="noVis">' . lang('Action') . '</th>
                                    <th>' . get_column_heading(lang('Company'), ($_SESSION['software']['ecommerce']['view_verified_shipping_addresses']['sort'] ?? ''), ($_SESSION['software']['ecommerce']['view_verified_shipping_addresses']['order'] ?? '')) . '</th>
                                    <th>' . get_column_heading(lang('Address 1'), ($_SESSION['software']['ecommerce']['view_verified_shipping_addresses']['sort'] ?? ''), ($_SESSION['software']['ecommerce']['view_verified_shipping_addresses']['order'] ?? '')) . '</th>
                                    <th>' . get_column_heading(lang('Address 2'), ($_SESSION['software']['ecommerce']['view_verified_shipping_addresses']['sort'] ?? ''), ($_SESSION['software']['ecommerce']['view_verified_shipping_addresses']['order'] ?? '')) . '</th>
                                    <th>' . get_column_heading(lang('City'), ($_SESSION['software']['ecommerce']['view_verified_shipping_addresses']['sort'] ?? ''), ($_SESSION['software']['ecommerce']['view_verified_shipping_addresses']['order'] ?? '')) . '</th>
                                    <th>' . get_column_heading(lang('State'), ($_SESSION['software']['ecommerce']['view_verified_shipping_addresses']['sort'] ?? ''), ($_SESSION['software']['ecommerce']['view_verified_shipping_addresses']['order'] ?? '')) . '</th>
                                    <th>' . get_column_heading(lang('Zip Code'), ($_SESSION['software']['ecommerce']['view_verified_shipping_addresses']['sort'] ?? ''), ($_SESSION['software']['ecommerce']['view_verified_shipping_addresses']['order'] ?? '')) . '</th>
                                    <th>' . get_column_heading(lang('Country'), ($_SESSION['software']['ecommerce']['view_verified_shipping_addresses']['sort'] ?? ''), ($_SESSION['software']['ecommerce']['view_verified_shipping_addresses']['order'] ?? '')) . '</th>
                                    <th>' . get_column_heading(lang('Created'), ($_SESSION['software']['ecommerce']['view_verified_shipping_addresses']['sort'] ?? ''), ($_SESSION['software']['ecommerce']['view_verified_shipping_addresses']['order'] ?? '')) . '</th>
                                    <th>' . get_column_heading(lang('Last Modified'), ($_SESSION['software']['ecommerce']['view_verified_shipping_addresses']['sort'] ?? ''), ($_SESSION['software']['ecommerce']['view_verified_shipping_addresses']['order'] ?? '')) . '</th>
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
