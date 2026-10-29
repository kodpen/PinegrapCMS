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

// A gift card's notes changed in place on the list (pg_list_edit_request() in
// includes/fn/output.php). Answered in JSON, before anything below draws the
// screen; the write is the one the gift card's screen makes
// (pg_gift_card_quick_update()).
if (pg_list_edit_requested()) {
    $edit = pg_list_edit_request(pg_gift_card_edit_columns(), function ($user) {
        return ($user['role'] < 3) || !empty($user['manage_ecommerce']);
    });

    $saved = pg_gift_card_quick_update($edit['id'], $edit['field'], $edit['value'], (int) $edit['user']['id']);

    pg_list_edit_reply($saved, $saved['ok'] ? 200 : 422);
}

$user = validate_user();
validate_ecommerce_access($user);

include_once('liveform.class.php');
$liveform = new liveform('view_gift_cards');

// What an operator can change on the list without opening the gift card.
$quick_edit = pg_gift_card_edit_columns();

$current_date = date('Y-m-d');

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



// The columns this list sorts by, in the order the table shows them.
// pg_list_state() keeps the choice in the session as a code, so it survives
// a language change, and falls back to the default for anything else.
$sort_columns = array(
    'code'            => lang('Code'),
    'balance'         => lang('Balance'),
    'original_amount' => lang('Original Amt'),
    'expiration_date' => lang('Expiration Date'),
    'notes'           => lang('Notes'),
    'from'            => lang('From'),
    'recipient'       => lang('Recipient'),
    'delivery_date'   => lang('Delivery Date'),
    'created'         => lang('Created'),
    'last_modified'   => lang('Last Modified'),
);

$sort_default = array('sort' => 'last_modified', 'order' => 'desc');

$list_state = pg_list_state('view_gift_cards', $sort_columns, $sort_default);
$sort_levels = $list_state['sorts'];

// What each sort code orders by. Only codes pg_list_state() let through
// reach pg_list_order_by(), and it writes nothing but these.
$order_by = pg_list_order_by($sort_levels, array(
    'code'            => 'gift_cards.code',
    'balance'         => 'gift_cards.balance',
    'original_amount' => 'gift_cards.amount',
    'expiration_date' => 'gift_cards.expiration_date',
    'notes'           => 'gift_cards.notes',
    'from'            => 'gift_cards.from_name',
    'recipient'       => 'gift_cards.recipient_email_address',
    'delivery_date'   => 'gift_cards.delivery_date',
    'created'         => 'gift_cards.created_timestamp',
    'last_modified'   => 'gift_cards.last_modified_timestamp',
));

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
        ORDER BY $order_by");

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
        ORDER BY $order_by";
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
                $output_status_class = 'status_enabled fg-success';
                $output_state = '';

            // Otherwise this gift card has expired or is used up, so use class
            // that shows red color and say which.
            } else {
                $output_status_class = 'status_disabled fg-danger';
                $output_state = (($gift_card['expiration_date'] != '0000-00-00') && ($gift_card['expiration_date'] < $current_date))
                    ? pg_list_status_word(lang('Expired'))
                    : pg_list_status_word(lang('No balance'));
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
            
            // The cells a quick edit can change, drawn by the same function
            // that draws them again in the answer to an edit. Notes on more
            // than one line change only on the gift card's screen.
            $list_cells = pg_gift_card_list_cells($gift_card);

            $output_rows .=
                '<tr>
                    <td class="align-middle text-start">
                        <button type="button" class="m-1 btn-data-control btn btn-outline theme-primary border-2 " title="' . lang('Edit') . '" onclick="window.location.href=\'edit_gift_card.php?id=' . $gift_card['id'] . '\'"><i class="bi bi-pencil"></i></button>
                        <!--<button type="button" class="m-1 btn-data-control btn btn-outline theme-danger border-2 " title="' . lang('Delete') . '" ><i class="material-icons">delete</i></button>-->
                    </td>
                    <td class="chart_label align-middle"><span class=" badge fw-lighter ' . $output_status_class . '">' . output_gift_card_code($gift_card['code']) . '</span>' . $output_state . '</td>
                    <td class="align-middle text-end"><span class=" badge theme-secondary  fw-lighter">' . pg_format_money($gift_card['balance'] / 100, BASE_CURRENCY_SYMBOL) . '</span></td>
                    <td class="align-middle text-end"><span class=" badge theme-primary  fw-lighter">' . pg_format_money($gift_card['amount'] / 100, BASE_CURRENCY_SYMBOL) . '</span></td>
                    <td class="align-middle">' . $output_expiration_date . '</td>
                    <td class="' . $list_cells['notes']['class'] . '"' . (pg_gift_card_notes_fit_list($gift_card['notes']) ? pg_list_edit_cell($quick_edit, 'notes', $gift_card['id'], $gift_card['notes']) : '') . '>' . $list_cells['notes']['html'] . '</td>
                    <td class="align-middle">' . $output_from_name . '</td>
                    <td class="align-middle">' . h($gift_card['recipient_email_address']) . '</td>
                    <td class="align-middle">' . $output_delivery_date . '</td>
                    <td class="align-middle">' . get_relative_time(array('timestamp' => $gift_card['created_timestamp'])) . ' ' . $created_username . '</td>
                    <td class="' . $list_cells['last_modified']['class'] . '" data-pg-cell="last_modified">' . $list_cells['last_modified']['html'] . '</td>
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
        'heading'=>lang('All Gift Cards'),
        'heading_description' => lang('Gift cards are automatically created when a Gift Card Product is ordered. You can also manually create Gift Cards.'),
    ]) . '
<main id="content" class="container-fluid">
            <div class="row">
            <div class="col-12">
                ' . $liveform->output_errors() . '
                ' . $liveform->get_warnings() . '
                ' . $liveform->output_notices() . '
               
                <div class="row mb-3  flex-wrap">
                    <div class="col-12 text-center md:text-start">
                        
                        <nav id="button_bar" class="navigation " aria-label="' . h(lang('Button Bar')) . '">
                            <a class="btn btn-sm btn-solid theme-primary m-1 " href="add_gift_card.php"><span class="bi bi-plus-circle me-3"></span>' . lang(array('string'=>'Create') ) . '</a>
                            <form id="export_form" class="disable_shortcut d-inline-block" method="get">
                                <div class=" btn-group btn-group-sm flex-wrap">
                                    <button type="submit" name="submit_data" value="Export Gift Cards" class="btn btn-link pg-link-secondary py-0 m-1"><span class="bi bi-file-earmark-arrow-down bi-me-2"></span>' . lang(array('string'=>'Export') ) . '</button>
                                </div>
                            </form>
                            <div class="pg-toolbar-grow"></div>
                            ' . pg_list_sort_menu('view_gift_cards.php', $sort_columns, $sort_levels, $sort_default) . '
                        </nav>
                    </div>
                </div>
                <div class="card my-7">
                    <div class="card-body p-0 position-relative">
                        <table class="chart table-hover table " style="width:100%;display:none"' . pg_list_edit_table('view_gift_cards.php', $quick_edit) . '>
                            <thead>
                                <tr>
                                    <th class="noVis">' . lang(array('string'=>'Action') ) . '</th>
                                    <th>' . get_column_heading(lang('Code'), $sort_levels, '', '', 'code') . '</th>
                                    <th class="text-center">' . get_column_heading(lang('Balance'), $sort_levels, '', '', 'balance') . '</th>
                                    <th class="text-center">' . get_column_heading(lang('Original Amt'), $sort_levels, '', '', 'original_amount') . '</th>
                                    <th>' . get_column_heading(lang('Expiration Date'), $sort_levels, '', '', 'expiration_date') . '</th>
                                    <th>' . get_column_heading(lang('Notes'), $sort_levels, '', '', 'notes') . '</th>
                                    <th>' . get_column_heading(lang('From'), $sort_levels, '', '', 'from') . '</th>
                                    <th>' . get_column_heading(lang('Recipient'), $sort_levels, '', '', 'recipient') . '</th>
                                    <th>' . get_column_heading(lang('Delivery Date'), $sort_levels, '', '', 'delivery_date') . '</th>
                                    <th>' . get_column_heading(lang('Created'), $sort_levels, '', '', 'created') . '</th>
                                    <th>' . get_column_heading(lang('Last Modified'), $sort_levels, '', '', 'last_modified') . '</th>
                                </tr>
                            </thead>
                            <tbody>' . $output_rows . '</tbody>
                        </table>
                    </div>
                </div>
                <div class="d-flow-root">
                    <div class="col-12 sm:col-6 md:col-4 lg:col-3  sm:float-end">
                        <div class="card my-7 border-primary">
                            <div class="card-body ">
                                <div class="row">
                                    <div class="col-12 text-center">
                                        <h5>' . lang('Active Balance') . '</h5>
                                        <p class="fw-bolder">' . pg_format_money($active_balance / 100, BASE_CURRENCY_SYMBOL) . '</p>
                                        <hr/>
                                    </div>
                                    <div class="col-12 text-center">
                                        <h5>' . lang('Expired Balance') . '</h5>
                                        <p class="fw-bolder">' . pg_format_money($expired_balance / 100, BASE_CURRENCY_SYMBOL) . '</p>
                                        <hr/>
                                    </div>
                                    <div class="col-12 text-center ">
                                        <h5>' . lang('Total Balance') . '</h5>
                                        <p class="fw-bolder">' . pg_format_money($total_balance / 100, BASE_CURRENCY_SYMBOL) . '</p>
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