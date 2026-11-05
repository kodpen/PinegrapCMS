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
require_once(PG_FUNCTIONS_DIR . '/includes/fn/commerce_list.php');
include_once('liveform.class.php');
require_once(PG_FUNCTIONS_DIR . '/includes/forms/gift_card_form.php');
require_once(PG_FUNCTIONS_DIR . '/includes/fn/gift_card_list_edit.php');

// A gift card's expiration date or notes changed in place on the
// list (pg_list_edit_request() in includes/fn/output.php). Answered in JSON,
// before anything below draws the screen; the check and the write are the
// gift card screen's own (pg_gift_card_quick_update()).
if (pg_list_edit_requested()) {
    $quick_edit = pg_gift_card_edit_columns();
    $edit = pg_list_edit_request($quick_edit, function ($user) {
        return ($user['role'] < 3) || !empty($user['manage_ecommerce']);
    });

    $saved = pg_gift_card_quick_update($edit['id'], $edit['field'], $edit['value'], (int) $edit['user']['id'], $quick_edit);

    pg_list_edit_reply($saved, $saved['ok'] ? 200 : 422);
}

$user = validate_user();
validate_ecommerce_access($user);

$liveform = new liveform('view_gift_cards');

// What an operator can change on the list without opening the gift card.
$quick_edit = pg_gift_card_edit_columns();

$current_date = date('Y-m-d');

// The page asked for, a whole number of 1 or more (pg_list_page_number()).
$screen = pg_list_page_number($_REQUEST['screen'] ?? '');



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
    pg_csv_download_start('gift_cards.csv');

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

    // Loop through the gift cards in order to output CSV data.
    foreach ($gift_cards as $gift_card) {
        $expiration_date = '';

        if ($gift_card['expiration_date'] != '0000-00-00') {
            $expiration_date = $gift_card['expiration_date'];
        }

        if (($gift_card['recipient_email_address'] != '') && ($gift_card['from_name'] == '')) {
            $gift_card['from_name'] = lang('Anonymous');
        }

        $delivery_date = '';

        // If there is a recipient, then output the delivery date.
        if ($gift_card['recipient_email_address'] != '') {
            if ($gift_card['delivery_date'] == '0000-00-00') {
                $delivery_date = lang('Immediate');

            } else {
                $delivery_date = $gift_card['delivery_date'];
            }
        }

        echo
            '"' . output_gift_card_code($gift_card['code']) . '",' .
            '"' . sprintf('%01.2lf', $gift_card['balance'] / 100) . '",' .
            '"' . sprintf('%01.2lf', $gift_card['amount'] / 100) . '",' .
            '"' . $expiration_date . '",' .
            '"' . csv_cell($gift_card['notes']) . '",' .
            '"' . $gift_card['order_number'] . '",' .
            '"' . csv_cell($gift_card['from_name']) . '",' .
            '"' . csv_cell($gift_card['recipient_email_address']) . '",' .
            '"' . csv_cell($gift_card['message']) . '",' .
            '"' . $delivery_date . '",' .
            '"' . pg_csv_export_time($gift_card['created_timestamp']) . '",' .
            '"' . csv_cell($gift_card['created_username']) . '",' .
            '"' . pg_csv_export_time($gift_card['last_modified_timestamp']) . '",' .
            '"' . csv_cell($gift_card['last_modified_username']) . '"' . "\n";
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

    // Every gift card is printed: the list table driver sorts and searches
    // them in the browser (browser mode).
    $gift_cards = db_items(pg_gift_card_list_sql() . "
        $where
        ORDER BY $order_by");

    $list_rows = array();

    foreach ($gift_cards as $gift_card) {
        $list_rows[] = array(
            'id' => (int) $gift_card['id'],
            'cells' => pg_gift_card_list_row_cells($gift_card, $current_date, $quick_edit),
        );
    }

    $list = pg_list_table_printed(array(
        'list' => 'view_gift_cards',
        'columns' => pg_commerce_list_specs(pg_gift_card_list_columns(), $sort_columns, $quick_edit),
        'levels' => $sort_levels,
        'rows' => $list_rows,
        'edit' => array('url' => 'view_gift_cards.php', 'columns' => $quick_edit),
    ));

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
               
                ' . pg_screen_toolbar(array(
                    'primary'   => array('label' => lang('Create'), 'url' => 'add_gift_card.php', 'icon' => 'bi-plus-circle'),
                    'secondary' => '<form id="export_form" class="disable_shortcut" method="get"><button type="submit" name="submit_data" value="Export Gift Cards" class="btn btn-sm btn-text theme-secondary"><i class="bi bi-file-earmark-arrow-down me-1" aria-hidden="true"></i>' . lang('Export') . '</button></form>',
                    'search'    => array(pg_list_table_search_box('view_gift_cards'), pg_list_sort_menu('view_gift_cards.php', $sort_columns, $sort_levels, $sort_default)),
                )) . '
                <div class="d-flex flex-wrap gap-5 mb-3 small">
                    <div><span class="fg-secondary">' . lang('Active Balance') . '</span> <strong>' . pg_format_money($active_balance / 100, BASE_CURRENCY_SYMBOL) . '</strong></div>
                    <div><span class="fg-secondary">' . lang('Expired Balance') . '</span> <strong>' . pg_format_money($expired_balance / 100, BASE_CURRENCY_SYMBOL) . '</strong></div>
                    <div><span class="fg-secondary">' . lang('Total Balance') . '</span> <strong>' . pg_format_money($total_balance / 100, BASE_CURRENCY_SYMBOL) . '</strong></div>
                </div>
                ' . pg_list_table_html($list['source'], $list['page'], lang('All Gift Cards')) . '
            </div>
        </div>
    
</main>
' . pg_js_bundle_tags('assets/js/pg_list_table.min.js') .
    output_footer();

    $liveform->remove_form();
}