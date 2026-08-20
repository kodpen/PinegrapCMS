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
$liveform = new liveform('view_currencies');

// store all values collected in request to session
foreach ($_REQUEST as $key => $value) {
    // if the value is a string then add it to the session
    // we have to do this check because cookie arrays are sometimes included in the $_REQUEST array,
    // for certain php.ini settings
    if (is_string($value) == TRUE) {
        $_SESSION['software']['ecommerce']['view_currencies'][$key] = trim($value);
    }
}



// if the sort is not set yet, then default it to empty so that the switch below falls
// through to its default case
if (isset($_SESSION['software']['ecommerce']['view_currencies']['sort']) == false) {
    $_SESSION['software']['ecommerce']['view_currencies']['sort'] = '';
}

switch (($_SESSION['software']['ecommerce']['view_currencies']['sort'] ?? '')) {
    case 'Name':
        $sort_column = 'name';
        break;

    case 'Base':
        $sort_column = 'base';
        break;

    case 'Code':
        $sort_column = 'code';
        break;

    case 'Symbol':
        $sort_column = 'symbol';
        break;

    case 'Exchange Rate':
        $sort_column = 'exchange_rate';
        break;

    case 'Created':
        $sort_column = 'created_timestamp';
        break;

    case 'Last Modified':
        $sort_column = 'last_modified_timestamp';
        break;

    default:
        $sort_column = 'last_modified_timestamp';
        $_SESSION['software']['ecommerce']['view_currencies']['sort'] = 'Last Modified';
        $_SESSION['software']['ecommerce']['view_currencies']['order'] = 'desc';
        break;
}

// if order is not set, default to ascending
if (isset($_SESSION['software']['ecommerce']['view_currencies']['order']) == false) {
    $_SESSION['software']['ecommerce']['view_currencies']['order'] = 'asc';
}

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
    ORDER BY $sort_column " . escape(($_SESSION['software']['ecommerce']['view_currencies']['order'] ?? ''));

$result = mysqli_query(db::$con, $query) or output_error('Query failed.');

$currencies = array();

while ($row = mysqli_fetch_assoc($result)) {
    $currencies[] = $row;
}

$output_rows = '';

// if there is at least one result to display
if ($currencies) {
   foreach ($currencies as $currency) {

        $output_base_check_mark = '';
        if ($currency['base'] == 1) {
            $output_base_check_mark = '<span class="material-icons">task_alt</span>';
        }

        $created_username = '';
        if ($currency['created_username'] != '') {
            $created_username = ' ' . lang(array('string'=>'by {var:1}','vars'=>array( h($currency['created_username']) ) ) );
        }
        
        $last_modified_username = '';
        
        if ($currency['last_modified_username'] != '') {
            $last_modified_username = ' ' . lang(array('string'=>'by {var:1}','vars'=>array( h($currency['last_modified_username']) ) ) );
        }



        $output_link_url = 'edit_currency.php?id=' . $currency['id'];


    

        $output_rows .=
            '<tr>
                <td></td>
                <td class="align-middle text-start action-buttons">
                    <button type="button" class="m-1 btn-data-control btn btn-outline-primary border-2 " data-loading-content=" " title="' . lang('Edit') . '" onclick="window.location.href=\'' . $output_link_url . '\'"><i class="bi bi-pencil"></i></button>
                </td>
                <td class="chart_label align-middle">' . h($currency['name']) . '</td>
                <td class="align-middle text-center">' . $output_base_check_mark . '</td>
                <td class="align-middle text-center">' . h($currency['code']) . '</td>
                <td class="align-middle text-center">' . $currency['symbol'] . '</td>
                <td class="align-middle text-start">' . h($currency['exchange_rate']) . '</td>
                <td class="align-middle">' . get_relative_time(array('timestamp' => $currency['created_timestamp'])) . $created_username . '</td>
                <td class="align-middle">' . get_relative_time(array('timestamp' => $currency['last_modified_timestamp'])) . $last_modified_username . '</td>
            </tr>';
    }
}

print
pg_page_shell(
    array(
        'title'=> lang('Currencies'),
        'extra classes'=>'setting',
        'icon'=>'setting', 
        'heading'=>lang('Currencies'),
    )
) . '
    <div class="row">
        <div class="col-12">
            ' . $liveform->output_errors() . '
            ' . $liveform->get_warnings() . '
            ' . $liveform->output_notices() . '
            <div class="row mb-2  flex-wrap">
                <div class="col-12 text-center text-md-start">
                    <h2 class="d-inline-block " data-bs-content="' . lang('All currencies available for customers to select.') . '" title="' . lang('All Currencies') . '">' . lang('All Currencies') . '</h2>
                    <nav id="button_bar" class="navigation " aria-label="Button Bar">
                        <a class="btn btn-sm btn-primary m-1 " href="add_currency.php" data-loading-content="' . lang(array('string'=>'Loading') ) . '"><span class="bi bi-plus-circle me-2"></span>' . lang(array('string'=>'Create') ) . '</a>
                        <div class=" btn-group btn-group-sm flex-wrap">
                            <a class="btn btn-link link-secondary py-0 m-1 bi bi-currency-exchange bi-me-2" href="update_exchange_rates.php?send_to=' . h(get_request_uri()) . '" data-loading-content="' . lang(array('string'=>'Loading') ) . '">' . lang(array('string'=>'Update Exchange Rates') ) . '</a>
                        </div>
                    </nav>
                </div>
            </div>
            <div class="card my-4">
                <div class="card-body p-0 position-relative">
                    <table class="chart table-hover table " style="width:100%;display:none">
                        <thead>
                            <tr>
                                <th class="noVis"></th>
                                <th class="noVis text-start">' . lang(array('string'=>'Action') ) . '</th> 
                                <th>' . get_column_heading(lang('Name'), ($_SESSION['software']['ecommerce']['view_currencies']['sort'] ?? ''), ($_SESSION['software']['ecommerce']['view_currencies']['order'] ?? '')) . '</th>
                                <th class="text-center">' . get_column_heading(lang('Base'), ($_SESSION['software']['ecommerce']['view_currencies']['sort'] ?? ''), ($_SESSION['software']['ecommerce']['view_currencies']['order'] ?? '')) . '</th>
                                <th class="text-center">' . get_column_heading(lang('Code'), ($_SESSION['software']['ecommerce']['view_currencies']['sort'] ?? ''), ($_SESSION['software']['ecommerce']['view_currencies']['order'] ?? '')) . '</th>
                                <th class="text-center">' . get_column_heading(lang('Symbol'), ($_SESSION['software']['ecommerce']['view_currencies']['sort'] ?? ''), ($_SESSION['software']['ecommerce']['view_currencies']['order'] ?? '')) . '</th>
                                <th class="text-start">' . get_column_heading(lang('Exchange Rate'), ($_SESSION['software']['ecommerce']['view_currencies']['sort'] ?? ''), ($_SESSION['software']['ecommerce']['view_currencies']['order'] ?? '')) . '</th>
                                <th>' . get_column_heading(lang('Created'), ($_SESSION['software']['ecommerce']['view_currencies']['sort'] ?? ''), ($_SESSION['software']['ecommerce']['view_currencies']['order'] ?? '')) . '</th>
                                <th>' . get_column_heading(lang('Last Modified'), ($_SESSION['software']['ecommerce']['view_currencies']['sort'] ?? ''), ($_SESSION['software']['ecommerce']['view_currencies']['order'] ?? '')) . '</th>
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
$liveform->clear_notices();
$liveform->remove_form();
?>