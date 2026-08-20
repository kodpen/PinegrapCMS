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
$liveform = new liveform('view_gift_cards');

$current_date = date('Y-m-d');

// store all values collected in request to session
foreach ($_REQUEST as $key => $value) {
    // if the value is a string then add it to the session
    // we have to do this check because cookie arrays are sometimes included in the $_REQUEST array,
    // for certain php.ini settings
    if (is_string($value) == TRUE) {
        $_SESSION['software']['ecommerce']['view_gift_cards'][$key] = trim($value);
    }
}

// If a screen was passed and it is a positive integer, then use it.
// These checks are necessary in order to avoid SQL errors below for a bogus screen value.
if (
    isset($_REQUEST['screen'])
    and $_REQUEST['screen']
    and is_numeric($_REQUEST['screen'])
    and $_REQUEST['screen'] > 0
    and $_REQUEST['screen'] == round($_REQUEST['screen'])
) {
    $screen = (int) $_REQUEST['screen'];

// Otherwise, use the default, which is the first screen.
} else {
    $screen = 1;
}



// if the sort is not set yet, then default it to empty so that the switch below falls
// through to its default case
if (isset($_SESSION['software']['ecommerce']['view_gift_cards']['sort']) == false) {
    $_SESSION['software']['ecommerce']['view_gift_cards']['sort'] = '';
}

switch (($_SESSION['software']['ecommerce']['view_gift_cards']['sort'] ?? '')) {
    case lang('Code'):
        $sort_column = 'gift_cards.code';
        break;

    case lang('Balance'):
        $sort_column = 'gift_cards.balance';
        break;
        
    case lang('Original Amt'):
        $sort_column = 'gift_cards.amount';
        break;

    case lang('Expiration Date'):
        $sort_column = 'gift_cards.expiration_date';
        break;

    case lang('Notes'):
        $sort_column = 'gift_cards.notes';
        break;

    case lang('From'):
        $sort_column = 'gift_cards.from_name';
        break;
        
    case lang('Recipient'):
        $sort_column = 'gift_cards.recipient_email_address';
        break;

    case lang('Delivery Date'):
        $sort_column = 'gift_cards.delivery_date';
        break;

    case lang('Created'):
        $sort_column = 'gift_cards.created_timestamp';
        break;

    case lang('Last Modified'):
        $sort_column = 'gift_cards.last_modified_timestamp';
        break;

    default:
        $sort_column = 'gift_cards.last_modified_timestamp';
        $_SESSION['software']['ecommerce']['view_gift_cards']['sort'] = lang('Last Modified');
        $_SESSION['software']['ecommerce']['view_gift_cards']['order'] = 'desc';
        break;
}

// if order is not set, set to ascending
if (isset($_SESSION['software']['ecommerce']['view_gift_cards']['order']) == false) {
    $_SESSION['software']['ecommerce']['view_gift_cards']['order'] = 'asc';
}

$where = "";


// If user requested to export gift cards, then export them.
if (($_GET['submit_data'] ?? '') == 'Export Gift Cards') {
    header("Content-type: text/csv");
    header("Content-disposition: attachment; filename=gift_cards.csv");

    // Output column headings for CSV data.
    echo
        '"code",' .
        '"balance",' .
        '"amount",' .
        '"expiration_date",' .
        '"notes",' .
        '"order_number",' .
        '"from_name",' .
        '"recipient_email_address",' .
        '"message",' .
        '"delivery_date",' .
        '"created",' .
        '"created_username",' .
        '"last_modified",' .
        '"last_modified_username"' . "\n";

    // Get all gift cards.
    $gift_cards = db_items(
        "SELECT
            gift_cards.code,
            gift_cards.balance,
            gift_cards.amount,
            gift_cards.expiration_date,
            gift_cards.notes,
            orders.order_number,
            gift_cards.from_name,
            gift_cards.recipient_email_address,
            gift_cards.message,
            gift_cards.delivery_date,
            gift_cards.created_timestamp,
            created_user.user_username AS created_username,
            gift_cards.last_modified_timestamp,
            last_modified_user.user_username AS last_modified_username
        FROM gift_cards
        LEFT JOIN orders ON gift_cards.order_id = orders.id
        LEFT JOIN user AS created_user ON gift_cards.created_user_id = created_user.user_id
        LEFT JOIN user AS last_modified_user ON gift_cards.last_modified_user_id = last_modified_user.user_id
        $where
        ORDER BY $sort_column " . escape(($_SESSION['software']['ecommerce']['view_gift_cards']['order'] ?? '')));

    // If the date format is month and then day, then use that format.
    if (DATE_FORMAT == 'month_day') {
        $month_and_day_format = 'n/j';

    // Otherwise the date format is day and then month, so use that format.
    } else {
        $month_and_day_format = 'j/n';
    }

    // Loop through the gift cards in order to output CSV data.
    foreach ($gift_cards as $gift_card) {
        $expiration_date = '';

        if ($gift_card['expiration_date'] != '0000-00-00') {
            $expiration_date = $gift_card['expiration_date'];
        }

        if (($gift_card['recipient_email_address'] != '') && ($gift_card['from_name'] == '')) {
            $gift_card['from_name'] = 'Anonymous';
        }

        $delivery_date = '';

        // If there is a recipient, then output the delivery date.
        if ($gift_card['recipient_email_address'] != '') {
            if ($gift_card['delivery_date'] == '0000-00-00') {
                $delivery_date = 'Immediate';

            } else {
                $delivery_date = $gift_card['delivery_date'];
            }
        }

        echo
            '"' . output_gift_card_code($gift_card['code']) . '",' .
            '"' . sprintf('%01.2lf', $gift_card['balance'] / 100) . '",' .
            '"' . sprintf('%01.2lf', $gift_card['amount'] / 100) . '",' .
            '"' . $expiration_date . '",' .
            '"' . escape_csv($gift_card['notes']) . '",' .
            '"' . $gift_card['order_number'] . '",' .
            '"' . escape_csv($gift_card['from_name']) . '",' .
            '"' . escape_csv($gift_card['recipient_email_address']) . '",' .
            '"' . escape_csv($gift_card['message']) . '",' .
            '"' . $delivery_date . '",' .
            '"' . date($month_and_day_format . '/Y g:i:s A T', $gift_card['created_timestamp']) . '",' .
            '"' . escape_csv($gift_card['created_username']) . '",' .
            '"' . date($month_and_day_format . '/Y g:i:s A T', $gift_card['last_modified_timestamp']) . '",' .
            '"' . escape_csv($gift_card['last_modified_username']) . '"' . "\n";
    }

    exit;

// Otherwise the user did not select to export gift cards, so just list gift cards.
} else {
    $all_gift_cards = 0;

    // get the total number of gift cards
    $query = "SELECT COUNT(*) FROM gift_cards";
    $result = mysqli_query(db::$con, $query) or output_error('Query failed.');
    $row = mysqli_fetch_row($result);
    $all_gift_cards = $row[0];

    // Get all gift cards.
    $query =
        "SELECT
            gift_cards.id,
            gift_cards.code,
            gift_cards.amount,
            gift_cards.expiration_date,
            gift_cards.balance,
            gift_cards.notes,
            gift_cards.from_name,
            gift_cards.recipient_email_address,
            gift_cards.delivery_date,
            created_user.user_username AS created_username,
            gift_cards.created_timestamp,
            last_modified_user.user_username AS last_modified_username,
            gift_cards.last_modified_timestamp
        FROM gift_cards
        LEFT JOIN orders ON gift_cards.order_id = orders.id
        LEFT JOIN user AS created_user ON gift_cards.created_user_id = created_user.user_id
        LEFT JOIN user AS last_modified_user ON gift_cards.last_modified_user_id = last_modified_user.user_id
        $where
        ORDER BY $sort_column " . escape(($_SESSION['software']['ecommerce']['view_gift_cards']['order'] ?? ''));
    $result = mysqli_query(db::$con, $query) or output_error('Query failed.');
    $gift_cards = mysqli_fetch_items($result);

    $output_rows = '';

    // if there is at least one result to display
    if ($gift_cards) {

        foreach ($gift_cards as $gift_card) {
            // If this gift card has a balance and has not expired,
            // then use class that shows green color.
            if (
                ($gift_card['balance'])
                &&
                (
                    ($gift_card['expiration_date'] == '0000-00-00')
                    || ($gift_card['expiration_date'] >= $current_date)
                )
            ) {
                $output_status_class = 'status_enabled text-success';
            
            // Otherwise this gift card has expired, so use class that shows red color.
            } else {
                $output_status_class = 'status_disabled text-danger';
            }

            $output_expiration_date = '';

            if ($gift_card['expiration_date'] != '0000-00-00') {
                $output_expiration_date = get_absolute_time(array('timestamp' => strtotime($gift_card['expiration_date']), 'type' => 'date'));
            }

            $output_from_name = '';
            $output_delivery_date = '';

            // If there is a recipient, then output order info.
            if ($gift_card['recipient_email_address'] != '') {
                if ($gift_card['from_name'] != '') {
                    $output_from_name = h($gift_card['from_name']);

                } else {
                    $output_from_name = lang('Anonymous');
                }
                
                if ($gift_card['delivery_date'] == '0000-00-00') {
                    $output_delivery_date = lang('Immediate');

                } else {
                    $output_delivery_date = get_absolute_time(array('timestamp' => strtotime($gift_card['delivery_date']), 'type' => 'date'));
                }
            }

            $created_username = '';
            
            if ($gift_card['created_username']) {
                $created_username = lang(array('string'=>'by {var:1}','vars'=>array( h($gift_card['created_username']) ) ) );
            }
            
            $last_modified_username = '';
            
            if ($gift_card['last_modified_username']) {
                $last_modified_username = lang(array('string'=>'by {var:1}','vars'=>array( h($gift_card['last_modified_username']) ) ) );
            }

            $output_rows .=
                '<tr>
                    <td class="align-middle text-start">
                        <button type="button" class="m-1 btn-data-control btn btn-outline-primary border-2 " data-loading-content=" " title="' . lang('Edit') . '" onclick="window.location.href=\'edit_gift_card.php?id=' . $gift_card['id'] . '\'"><i class="bi bi-pencil"></i></button>
                        <!--<button type="button" class="m-1 btn-data-control btn btn-outline-danger border-2 " data-loading-content=" " title="' . lang('Delete') . '" ><i class="material-icons">delete</i></button>-->
                    </td>
                    <td class="chart_label align-middle"><span class=" badge fw-lighter ' . $output_status_class . '">' . output_gift_card_code($gift_card['code']) . '</span></td>
                    <td class="align-middle text-end"><span class=" badge bg-secondary  fw-lighter">' . BASE_CURRENCY_SYMBOL . number_format($gift_card['balance'] / 100, 2) . '</span></td>
                    <td class="align-middle text-end"><span class=" badge bg-primary  fw-lighter">' . BASE_CURRENCY_SYMBOL . number_format($gift_card['amount'] / 100, 2) . '</span></td>
                    <td class="align-middle">' . $output_expiration_date . '</td>
                    <td class="align-middle">' . nl2br(h($gift_card['notes'])) . '</td>
                    <td class="align-middle">' . $output_from_name . '</td>
                    <td class="align-middle">' . h($gift_card['recipient_email_address']) . '</td>
                    <td class="align-middle">' . $output_delivery_date . '</td>
                    <td class="align-middle">' . get_relative_time(array('timestamp' => $gift_card['created_timestamp'])) . ' ' . $created_username . '</td>
                    <td class="align-middle">' . get_relative_time(array('timestamp' => $gift_card['last_modified_timestamp'])) . ' ' . $last_modified_username . '</td>
                </tr>';
        }
    }

    // Get active balance.

    if ($where == '')  {
        $sql_active_filter = "WHERE ";
    } else {
        $sql_active_filter = "AND ";
    }

    $sql_active_filter .= "((gift_cards.expiration_date = '0000-00-00') OR (gift_cards.expiration_date >= '" . $current_date . "'))";

    $active_balance = db_value(
        "SELECT SUM(gift_cards.balance)
        FROM gift_cards
        LEFT JOIN orders ON gift_cards.order_id = orders.id
        LEFT JOIN user AS created_user ON gift_cards.created_user_id = created_user.user_id
        LEFT JOIN user AS last_modified_user ON gift_cards.last_modified_user_id = last_modified_user.user_id
        $where
        $sql_active_filter");

    // Get expired balance.

    if ($where == '')  {
        $sql_expired_filter = "WHERE ";
    } else {
        $sql_expired_filter = "AND ";
    }

    $sql_expired_filter .= "((gift_cards.expiration_date != '0000-00-00') AND (gift_cards.expiration_date < '" . $current_date . "'))";

    $expired_balance = db_value(
        "SELECT SUM(gift_cards.balance)
        FROM gift_cards
        LEFT JOIN orders ON gift_cards.order_id = orders.id
        LEFT JOIN user AS created_user ON gift_cards.created_user_id = created_user.user_id
        LEFT JOIN user AS last_modified_user ON gift_cards.last_modified_user_id = last_modified_user.user_id
        $where
        $sql_expired_filter");

    // Get total balance.
    $total_balance = $active_balance + $expired_balance;


    echo
    pg_page_shell([
        'title'=> lang('All Gift Cards'),
        'extra classes'=>'products',
        'icon'=>'store',
        'heading'=>lang('All Gift Cards')
    ]) . '
            <div class="row">
            <div class="col-12">
                ' . $liveform->output_errors() . '
                ' . $liveform->get_warnings() . '
                ' . $liveform->output_notices() . '
               
                <div class="row mb-2  flex-wrap">
                    <div class="col-12 text-center text-md-start">
                        <h2 class="d-inline-block " data-bs-content="' . lang('Gift cards are automatically created when a Gift Card Product is ordered. You can also manually create Gift Cards.') . '" title="' . lang('All Gift Cards') . '">' . lang('All Gift Cards') . '</h2>
                        <nav id="button_bar" class="navigation " aria-label="Button Bar">
                            <a class="btn btn-sm btn-primary m-1 " href="add_gift_card.php" data-loading-content="' . lang(array('string'=>'Loading') ) . '"><span class="bi bi-plus-circle me-2"></span>' . lang(array('string'=>'Create') ) . '</a>
                            <form id="export_form" class="disable_shortcut d-inline-block" method="get">
                                <div class=" btn-group btn-group-sm flex-wrap">
                                    <button type="submit" name="submit_data" value="Export Gift Cards" class="btn btn-link link-secondary py-0 m-1"><span class="bi bi-file-earmark-arrow-down bi-me-2"></span>' . lang(array('string'=>'Export') ) . '</button>
                                </div>
                            </form>
                        </nav>
                    </div>
                </div>
                <div class="card my-4">
                    <div class="card-body p-0 position-relative">
                        <table class="chart table-hover table " style="width:100%;display:none">
                            <thead>
                                <tr>
                                    <th class="noVis">' . lang(array('string'=>'Action') ) . '</th>
                                    <th>' . get_column_heading(lang('Code'), ($_SESSION['software']['ecommerce']['view_gift_cards']['sort'] ?? ''), ($_SESSION['software']['ecommerce']['view_gift_cards']['order'] ?? '')) . '</th>
                                    <th class="text-center">' . get_column_heading(lang('Balance'), ($_SESSION['software']['ecommerce']['view_gift_cards']['sort'] ?? ''), ($_SESSION['software']['ecommerce']['view_gift_cards']['order'] ?? '')) . '</th>
                                    <th class="text-center">' . get_column_heading(lang('Original Amt'), ($_SESSION['software']['ecommerce']['view_gift_cards']['sort'] ?? ''), ($_SESSION['software']['ecommerce']['view_gift_cards']['order'] ?? '')) . '</th>
                                    <th>' . get_column_heading(lang('Expiration Date'), ($_SESSION['software']['ecommerce']['view_gift_cards']['sort'] ?? ''), ($_SESSION['software']['ecommerce']['view_gift_cards']['order'] ?? '')) . '</th>
                                    <th>' . get_column_heading(lang('Notes'), ($_SESSION['software']['ecommerce']['view_gift_cards']['sort'] ?? ''), ($_SESSION['software']['ecommerce']['view_gift_cards']['order'] ?? '')) . '</th>
                                    <th>' . get_column_heading(lang('From'), ($_SESSION['software']['ecommerce']['view_gift_cards']['sort'] ?? ''), ($_SESSION['software']['ecommerce']['view_gift_cards']['order'] ?? '')) . '</th>
                                    <th>' . get_column_heading(lang('Recipient'), ($_SESSION['software']['ecommerce']['view_gift_cards']['sort'] ?? ''), ($_SESSION['software']['ecommerce']['view_gift_cards']['order'] ?? '')) . '</th>
                                    <th>' . get_column_heading(lang('Delivery Date'), ($_SESSION['software']['ecommerce']['view_gift_cards']['sort'] ?? ''), ($_SESSION['software']['ecommerce']['view_gift_cards']['order'] ?? '')) . '</th>
                                    <th>' . get_column_heading(lang('Created'), ($_SESSION['software']['ecommerce']['view_gift_cards']['sort'] ?? ''), ($_SESSION['software']['ecommerce']['view_gift_cards']['order'] ?? '')) . '</th>
                                    <th>' . get_column_heading(lang('Last Modified'), ($_SESSION['software']['ecommerce']['view_gift_cards']['sort'] ?? ''), ($_SESSION['software']['ecommerce']['view_gift_cards']['order'] ?? '')) . '</th>
                                </tr>
                            </thead>
                            <tbody>' . $output_rows . '</tbody>
                        </table>
                    </div>
                </div>
                <div class="clearfix">
                    <div class="col-12 col-sm-6 col-md-4 col-lg-3  float-sm-end">
                        <div class="card my-4 border-primary">
                            <div class="card-body ">
                                <div class="row">
                                    <div class="col-12 text-center">
                                        <h5>' . lang('Active Balance') . '</h5>
                                        <p class="fw-bolder">' . BASE_CURRENCY_SYMBOL . number_format($active_balance / 100, 2) . '</p>
                                        <hr/>
                                    </div>
                                    <div class="col-12 text-center">
                                        <h5>' . lang('Expired Balance') . '</h5>
                                        <p class="fw-bolder">' . BASE_CURRENCY_SYMBOL . number_format($expired_balance / 100, 2) . '</p>
                                        <hr/>
                                    </div>
                                    <div class="col-12 text-center ">
                                        <h5>' . lang('Total Balance') . '</h5>
                                        <p class="fw-bolder">' . BASE_CURRENCY_SYMBOL . number_format($total_balance / 100, 2) . '</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>' .
    output_footer();

    $liveform->remove_form();
}